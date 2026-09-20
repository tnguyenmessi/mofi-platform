<?php

namespace App\Services;

use App\Models\DemoMarketTick;
use App\Models\Execution;
use App\Models\Instrument;
use App\Models\Order;
use App\Models\OrderReservation;
use App\Models\Portfolio;
use App\Models\Transaction;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MarketMatchingService
{
    /** @return array<int, array<string, string>> */
    public function matchOpenOrders(Instrument $instrument, DemoMarketTick $tick): array
    {
        $orders = Order::query()
            ->with('reservation')
            ->where('instrument_id', $instrument->id)
            ->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])
            ->where(function ($query) use ($tick): void {
                $query->whereNull('placed_tick')->orWhere('placed_tick', '<=', $tick->tick);
            })
            ->orderByRaw("CASE WHEN order_type = 'MARKET' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN side = 'BUY' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN side = 'BUY' THEN COALESCE(limit_price, 0) ELSE 0 END DESC")
            ->orderByRaw("CASE WHEN side = 'SELL' THEN COALESCE(limit_price, 0) ELSE 0 END ASC")
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $filled = [];
        foreach ($orders as $order) {
            $filled = [...$filled, ...$this->matchOrder($order, $tick)];
        }

        return $filled;
    }

    /** @return array<int, array<string, string>> */
    public function matchOrder(Order $order, DemoMarketTick $tick): array
    {
        $locked = Order::query()->with('reservation')->lockForUpdate()->findOrFail($order->id);
        if (! in_array($locked->status, ['OPEN', 'PARTIALLY_FILLED'], true)) {
            return [];
        }
        if ($locked->placed_tick !== null && $locked->placed_tick > $tick->tick) {
            return [];
        }

        $remaining = BigDecimal::of($locked->quantity)->minus($locked->filled_quantity);
        if (! $remaining->isPositive() || ! $locked->reservation || $locked->reservation->released_at !== null) {
            return [];
        }

        $levels = $this->levels($locked->side);
        $fills = [];
        foreach ($levels as $index => $level) {
            $price = BigDecimal::of($tick->{$level['price']});
            $available = BigDecimal::of($tick->{$level['quantity']});
            if (! $available->isPositive() || ! $this->isEligible($locked, $price)) {
                continue;
            }

            $quantity = $remaining->isLessThan($available) ? $remaining : $available;
            $fill = $this->fill($locked, $locked->reservation, $tick->instrument ?? $locked->instrument, $tick, $price, $quantity);
            $fills[] = $fill;
            $remaining = $remaining->minus($quantity);
            $tick->{$level['quantity']} = (string) $available->minus($quantity)->toScale(8, RoundingMode::Down);
            $tick->total_volume = (string) BigDecimal::of($tick->total_volume)->plus($quantity)->toScale(8, RoundingMode::Down);
            $tick->save();
            $locked->refresh();
            if (! $remaining->isPositive()) {
                break;
            }
        }

        return $fills;
    }

    /** @return array<int, array{price: string, quantity: string}> */
    private function levels(string $side): array
    {
        if ($side === 'BUY') {
            return [
                ['price' => 'ask1_price', 'quantity' => 'ask1_quantity'],
                ['price' => 'ask2_price', 'quantity' => 'ask2_quantity'],
                ['price' => 'ask3_price', 'quantity' => 'ask3_quantity'],
            ];
        }

        return [
            ['price' => 'bid1_price', 'quantity' => 'bid1_quantity'],
            ['price' => 'bid2_price', 'quantity' => 'bid2_quantity'],
            ['price' => 'bid3_price', 'quantity' => 'bid3_quantity'],
        ];
    }

    private function isEligible(Order $order, BigDecimal $price): bool
    {
        if ($order->order_type === 'MARKET') {
            return true;
        }

        return $order->side === 'BUY'
            ? $price->isLessThanOrEqualTo($order->limit_price)
            : $price->isGreaterThanOrEqualTo($order->limit_price);
    }

    /** @return array<string, string> */
    private function fill(Order $order, OrderReservation $reservation, Instrument $instrument, DemoMarketTick $tick, BigDecimal $price, BigDecimal $quantity): array
    {
        if (! $quantity->isPositive()) {
            return ['order_id' => (string) $order->id, 'quantity' => '0', 'status' => $order->status];
        }

        $gross = $quantity->multipliedBy($price)->toScale(0, RoundingMode::HalfUp);
        $delta = $order->side === 'BUY' ? $gross->negated() : $gross;
        $executionKey = (string) Str::uuid();
        $transaction = Transaction::create([
            'user_id' => $order->user_id,
            'portfolio_id' => $order->portfolio_id,
            'instrument_id' => $instrument->id,
            'kind' => $order->side,
            'trade_date' => $tick->simulated_at->toDateString(),
            'quantity' => (string) $quantity->toScale(8),
            'unit_price' => (string) $price->toScale(8),
            'gross_amount' => (string) $gross,
            'fee' => '0',
            'tax' => '0',
            'cash_delta' => (string) $delta,
            'request_key' => $executionKey,
            'request_hash' => hash('sha256', $executionKey.'|'.$order->id.'|'.$tick->tick),
        ]);
        $execution = Execution::create([
            'order_id' => $order->id,
            'portfolio_id' => $order->portfolio_id,
            'instrument_id' => $instrument->id,
            'transaction_id' => $transaction->id,
            'execution_key' => $executionKey,
            'quantity' => (string) $quantity->toScale(8),
            'unit_price' => (string) $price->toScale(8),
            'gross_amount' => (string) $gross,
            'fee' => '0',
            'tax' => '0',
            'source' => 'demo_market_board',
            'executed_at' => now(),
            'simulated_at' => $tick->simulated_at,
        ]);

        $filled = BigDecimal::of($order->filled_quantity)->plus($quantity);
        $complete = $filled->isGreaterThanOrEqualTo($order->quantity);
        $order->update([
            'status' => $complete ? 'FILLED' : 'PARTIALLY_FILLED',
            'filled_quantity' => (string) $filled->toScale(8),
            'filled_at' => $complete ? now() : null,
        ]);
        if ($complete) {
            $reservation->update(['cash_amount' => '0', 'quantity' => '0', 'released_at' => now()]);
        } else {
            $remaining = BigDecimal::of($order->quantity)->minus($filled);
            $reservePrice = $order->reserved_unit_price ?? $order->limit_price ?? $price;
            $cash = $order->side === 'BUY'
                ? $remaining->multipliedBy($reservePrice)->toScale(0, RoundingMode::HalfUp)
                : BigDecimal::zero();
            $reservedQuantity = $order->side === 'SELL' ? $remaining : BigDecimal::zero();
            $reservation->update(['cash_amount' => (string) $cash, 'quantity' => (string) $reservedQuantity]);
        }

        $portfolio = Portfolio::find($order->portfolio_id);
        if ($portfolio) {
            DB::afterCommit(fn () => PortfolioSummary::forget($portfolio));
        }

        return [
            'order_id' => (string) $order->id,
            'execution_id' => (string) $execution->id,
            'quantity' => (string) $quantity->toScale(8),
            'unit_price' => (string) $price->toScale(8),
            'simulated_at' => $tick->simulated_at->toIso8601String(),
            'status' => $complete ? 'FILLED' : 'PARTIALLY_FILLED',
        ];
    }
}
