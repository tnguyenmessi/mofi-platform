<?php

namespace App\Services;

use App\Models\Execution;
use App\Models\Instrument;
use App\Models\Order;
use App\Models\Portfolio;
use App\Models\User;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PaperTradingService
{
    public function __construct(
        private QuoteBoardService $boards,
        private MarketMatchingService $matching,
    ) {}

    /** @return array{tick: int, filled: array<int, array<string, string>>} */
    public function advance(User $user, Portfolio $portfolio, int $instrumentId, int $tick): array
    {
        Gate::forUser($user)->authorize('recordTransaction', $portfolio);

        return DB::transaction(function () use ($portfolio, $instrumentId, $tick): array {
            $instrument = Instrument::query()->lockForUpdate()->findOrFail($instrumentId);
            $this->assertTradable($instrument);
            $replay = DB::table('replay_ticks')
                ->where('portfolio_id', $portfolio->id)
                ->where('instrument_id', $instrument->id)
                ->lockForUpdate()
                ->first();
            if ($replay !== null && $tick <= $replay->current_tick) {
                $this->reject('tick', 'Phiên mô phỏng này đã được xử lý. Hãy chuyển sang tick tiếp theo.');
            }

            $before = (int) (Execution::query()->max('id') ?? 0);
            if ($replay === null) {
                $session = $this->boards->sessionLocked($instrument);
                $this->matching->matchOpenOrders($instrument, $this->boards->currentTickLocked($session));
            }
            $session = $this->boards->advanceToTickLocked($instrument, $tick);
            $filled = Execution::query()
                ->with('order')
                ->where('id', '>', $before)
                ->where('instrument_id', $instrument->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Execution $execution): array => [
                    'order_id' => (string) $execution->order_id,
                    'quantity' => (string) $execution->quantity,
                    'unit_price' => (string) $execution->unit_price,
                    'status' => (string) ($execution->order?->status ?? 'FILLED'),
                ])->all();

            DB::table('replay_ticks')->updateOrInsert(
                ['portfolio_id' => $portfolio->id, 'instrument_id' => $instrument->id],
                ['current_tick' => $session->current_tick, 'updated_at' => now(), 'created_at' => $replay?->created_at ?? now()],
            );

            return ['tick' => $session->current_tick, 'filled' => $filled];
        }, 3);
    }

    /** @return array{order: Order, replayed: bool, filled: bool, matched_quantity: string} */
    public function place(User $user, Portfolio $portfolio, array $input): array
    {
        Gate::forUser($user)->authorize('recordTransaction', $portfolio);
        $payload = $this->normalize($input);
        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $portfolio, $payload, $hash): array {
            $instrument = Instrument::query()->lockForUpdate()->find($payload['instrument_id']);
            if (! $instrument) {
                $this->reject('instrument_id', 'Mã cổ phiếu mô phỏng không tồn tại.');
            }
            $this->assertTradable($instrument);
            $locked = Portfolio::query()->whereKey($portfolio->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $existing = Order::query()->where('portfolio_id', $locked->id)->where('request_key', $payload['request_key'])->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('Mã yêu cầu đã được dùng cho lệnh khác.');
                }

                return [
                    'order' => $existing->load(['instrument', 'reservation', 'execution', 'executions']),
                    'replayed' => true,
                    'filled' => $existing->status === 'FILLED',
                    'matched_quantity' => (string) $existing->filled_quantity,
                ];
            }

            $session = $this->boards->synchronizeLocked($instrument);
            if ($session->status !== 'OPEN') {
                $this->reject('instrument_id', 'Phiên giao dịch mô phỏng đã đóng.');
            }
            $quote = $this->boards->currentTickLocked($session);

            $reservePrice = $payload['side'] === 'BUY'
                ? ($payload['limit_price'] ?? (string) $quote->ceiling_price)
                : null;
            $cash = BigDecimal::zero();
            $held = BigDecimal::zero();
            foreach ($locked->transactions()->lockForUpdate()->get() as $row) {
                $cash = $cash->plus($row->cash_delta);
                if ($row->instrument_id === $instrument->id) {
                    $held = $held->plus($row->kind === 'BUY'
                        ? $row->quantity
                        : ($row->kind === 'SELL' ? BigDecimal::of($row->quantity)->negated() : BigDecimal::zero()));
                }
            }
            $reservedCash = BigDecimal::zero();
            $reservedQuantity = BigDecimal::zero();
            foreach ($locked->orders()->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])->with('reservation')->lockForUpdate()->get() as $open) {
                if ($open->reservation?->released_at !== null) {
                    continue;
                }
                if ($open->side === 'BUY') {
                    $reservedCash = $reservedCash->plus($open->reservation?->cash_amount ?? 0);
                } elseif ($open->instrument_id === $instrument->id) {
                    $reservedQuantity = $reservedQuantity->plus($open->reservation?->quantity ?? 0);
                }
            }

            $grossReservation = $payload['side'] === 'BUY'
                ? BigDecimal::of($payload['quantity'])->multipliedBy($reservePrice)->toScale(0, RoundingMode::HalfUp)
                : BigDecimal::zero();
            if ($payload['side'] === 'BUY' && $cash->minus($reservedCash)->isLessThan($grossReservation)) {
                $this->reject('quantity', 'Không đủ tiền khả dụng cho lệnh mô phỏng.');
            }
            if ($payload['side'] === 'SELL' && $held->minus($reservedQuantity)->isLessThan($payload['quantity'])) {
                $this->reject('quantity', 'Không đủ cổ phiếu khả dụng cho lệnh mô phỏng.');
            }

            $order = $locked->orders()->create([
                ...$payload,
                'user_id' => $user->id,
                'request_hash' => $hash,
                'filled_quantity' => '0',
                'status' => 'OPEN',
                'placed_tick' => $quote->tick,
                'placed_at_simulated' => $quote->simulated_at,
                'reserved_unit_price' => $reservePrice,
            ]);
            $order->reservation()->create([
                'portfolio_id' => $locked->id,
                'cash_amount' => (string) $grossReservation,
                'quantity' => $payload['side'] === 'SELL' ? $payload['quantity'] : '0',
            ]);
            $this->matching->matchOrder($order->load('reservation'), $quote);
            $fresh = $order->fresh(['instrument', 'reservation', 'execution', 'executions']);
            PortfolioSummary::forget($locked);

            return [
                'order' => $fresh,
                'replayed' => false,
                'filled' => $fresh->status === 'FILLED',
                'matched_quantity' => (string) $fresh->filled_quantity,
            ];
        }, 3);
    }

    public function cancel(User $user, Order $order): Order
    {
        Gate::forUser($user)->authorize('view', $order->portfolio);

        return DB::transaction(function () use ($order): Order {
            $locked = Order::query()->with('reservation')->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, ['OPEN', 'PARTIALLY_FILLED'], true)) {
                $locked->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
                $locked->reservation?->update(['released_at' => now(), 'cash_amount' => '0', 'quantity' => '0']);
                if ($portfolio = Portfolio::find($locked->portfolio_id)) {
                    DB::afterCommit(fn () => PortfolioSummary::forget($portfolio));
                }
            }

            return $locked->fresh(['instrument', 'reservation', 'execution', 'executions']);
        });
    }

    /** @return array<string, mixed> */
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

        return [
            'instrument_id' => (int) $input['instrument_id'],
            'side' => $input['side'],
            'order_type' => $type,
            'quantity' => (string) $quantity,
            'limit_price' => $limit ? (string) $limit : null,
            'request_key' => strtolower($input['request_key']),
        ];
    }

    private function assertTradable(Instrument $instrument): void
    {
        if (! $instrument->tradable || $instrument->asset_class !== 'stock' || $instrument->market !== 'VN' || $instrument->currency !== 'VND') {
            $this->reject('instrument_id', 'Mã cổ phiếu mô phỏng không được giao dịch.');
        }
    }

    private function reject(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
