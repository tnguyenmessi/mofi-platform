<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\Instrument;
use App\Models\Order;
use App\Models\OrderReservation;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaperTradingService
{
    public function place(User $user, Portfolio $portfolio, array $input): array
    {
        Gate::forUser($user)->authorize('recordTransaction', $portfolio);
        $payload = $this->normalize($input);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $portfolio, $payload, $hash): array {
            $locked = Portfolio::whereKey($portfolio->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $existing = Order::where('portfolio_id', $locked->id)->where('request_key', $payload['request_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('Mã yêu cầu đã được dùng cho lệnh khác.');
                }

                return ['order' => $existing->load(['instrument', 'reservation', 'execution']), 'replayed' => true, 'filled' => $existing->status === 'FILLED'];
            }
            $instrument = Instrument::whereKey($payload['instrument_id'])->lockForUpdate()->first();
            if (! $instrument || ! $instrument->tradable || $instrument->asset_class !== 'stock' || $instrument->market !== 'VN' || $instrument->currency !== 'VND') {
                $this->reject('instrument_id', 'Mã cổ phiếu mô phỏng không được giao dịch.');
            }
            $quote = $instrument->marketPrices()->whereDate('price_date', config('demo.simulation_date'))->where('source', 'demo')->where('is_demo', true)->latest('price_date')->first();
            if (! $quote || ! BigDecimal::of($quote->close)->isPositive()) {
                $this->reject('instrument_id', 'Mã chưa có giá mô phỏng của phiên hiện tại.');
            }
            $price = $payload['order_type'] === 'MARKET' ? BigDecimal::of($quote->close) : BigDecimal::of($payload['limit_price']);
            $cash = BigDecimal::zero();
            $held = BigDecimal::zero();
            foreach ($locked->transactions()->lockForUpdate()->get() as $row) {
                $cash = $cash->plus($row->cash_delta);
                if ($row->instrument_id === $instrument->id) {
                    $held = $held->plus($row->kind === 'BUY' ? $row->quantity : ($row->kind === 'SELL' ? BigDecimal::of($row->quantity)->negated() : BigDecimal::zero()));
                }
            }
            $reservedCash = BigDecimal::zero();
            $reservedQty = BigDecimal::zero();
            foreach ($locked->orders()->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])->with('reservation')->lockForUpdate()->get() as $open) {
                if ($open->side === 'BUY') {
                    $reservedCash = $reservedCash->plus($open->reservation?->cash_amount ?? 0);
                } elseif ($open->instrument_id === $instrument->id) {
                    $reservedQty = $reservedQty->plus($open->reservation?->quantity ?? 0);
                }
            }
            $gross = BigDecimal::of($payload['quantity'])->multipliedBy($price)->toScale(0, RoundingMode::HalfUp);
            if ($payload['side'] === 'BUY' && $cash->minus($reservedCash)->isLessThan($gross)) {
                $this->reject('quantity', 'Không đủ tiền khả dụng cho lệnh mô phỏng.');
            }
            if ($payload['side'] === 'SELL' && $held->minus($reservedQty)->isLessThan($payload['quantity'])) {
                $this->reject('quantity', 'Không đủ cổ phiếu khả dụng cho lệnh mô phỏng.');
            }
            $order = $locked->orders()->create(array_merge($payload, ['user_id' => $user->id, 'request_hash' => $hash, 'filled_quantity' => '0', 'status' => 'OPEN']));
            $reservation = $order->reservation()->create(['portfolio_id' => $locked->id, 'cash_amount' => $payload['side'] === 'BUY' ? (string) $gross : '0', 'quantity' => $payload['side'] === 'SELL' ? $payload['quantity'] : '0']);
            $fillable = $payload['order_type'] === 'MARKET' || ($payload['side'] === 'BUY' ? BigDecimal::of($quote->close)->isLessThanOrEqualTo($payload['limit_price']) : BigDecimal::of($quote->close)->isGreaterThanOrEqualTo($payload['limit_price']));
            if ($fillable) {
                $this->fill($order, $reservation, $instrument, BigDecimal::of($quote->close));
            }
            DB::afterCommit(fn () => PortfolioSummary::forget($locked));

            return ['order' => $order->fresh(['instrument', 'reservation', 'execution']), 'replayed' => false, 'filled' => $fillable];
        }, 3);
    }

    public function cancel(User $user, Order $order): Order
    {
        Gate::forUser($user)->authorize('view', $order->portfolio);

        return DB::transaction(function () use ($order): Order {
            $portfolio = Portfolio::whereKey($order->portfolio_id)->lockForUpdate()->firstOrFail();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'OPEN') {
                $order->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
                $order->reservation?->update(['released_at' => now()]);
            }
            DB::afterCommit(fn () => PortfolioSummary::forget($portfolio));

            return $order->fresh(['instrument', 'reservation', 'execution']);
        });
    }

    private function fill(Order $order, OrderReservation $reservation, Instrument $instrument, BigDecimal $price): void
    {
        if ($order->execution()->exists()) {
            return;
        }
        $gross = BigDecimal::of($order->quantity)->multipliedBy($price)->toScale(0, RoundingMode::HalfUp);
        $delta = $order->side === 'BUY' ? $gross->negated() : $gross;
        $transaction = Transaction::create(['user_id' => $order->user_id, 'portfolio_id' => $order->portfolio_id, 'instrument_id' => $instrument->id, 'kind' => $order->side, 'trade_date' => config('demo.simulation_date'), 'quantity' => $order->quantity, 'unit_price' => (string) $price, 'gross_amount' => (string) $gross, 'fee' => '0', 'tax' => '0', 'cash_delta' => (string) $delta, 'request_key' => 'order-'.$order->id, 'request_hash' => hash('sha256', 'order-'.$order->id)]);
        Execution::create(['order_id' => $order->id, 'portfolio_id' => $order->portfolio_id, 'instrument_id' => $instrument->id, 'transaction_id' => $transaction->id, 'quantity' => $order->quantity, 'unit_price' => (string) $price, 'gross_amount' => (string) $gross, 'fee' => '0', 'tax' => '0', 'source' => 'demo_replay', 'executed_at' => now()]);
        $order->update(['status' => 'FILLED', 'filled_quantity' => $order->quantity, 'filled_at' => now()]);
        $reservation->update(['released_at' => now()]);
    }

    private function normalize(array $input): array
    {
        $quantity = BigDecimal::of($input['quantity'])->toScale(8);
        if (! $quantity->isPositive()) {
            $this->reject('quantity', 'Số lượng phải lớn hơn 0.');
        }
        $type = $input['order_type'];
        $limit = $type === 'LIMIT' ? BigDecimal::of($input['limit_price'])->toScale(8) : null;
        if ($limit !== null && ! $limit->isPositive()) {
            $this->reject('limit_price', 'Giá giới hạn phải lớn hơn 0.');
        }

        return ['instrument_id' => (int) $input['instrument_id'], 'side' => $input['side'], 'order_type' => $type, 'quantity' => (string) $quantity, 'limit_price' => $limit ? (string) $limit : null, 'request_key' => strtolower($input['request_key'])];
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
