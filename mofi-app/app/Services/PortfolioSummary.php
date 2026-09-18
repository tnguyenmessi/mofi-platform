<?php

namespace App\Services;

use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\Portfolio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PortfolioSummary
{
    public static function cacheKey(Portfolio $portfolio): string
    {
        return 'mofi.portfolio.summary.'.$portfolio->id.'.'.config('demo.simulation_date');
    }

    public static function forget(Portfolio $portfolio): void
    {
        Cache::forget(self::cacheKey($portfolio));
    }

    /** @return array<string, mixed> */
    public function forPortfolio(Portfolio $portfolio): array
    {
        return DB::transaction(function () use ($portfolio): array {
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            }

            return $this->build($portfolio);
        });
    }

    /** @return array<string, mixed> */
    private function build(Portfolio $portfolio): array
    {
        $asOf = CarbonImmutable::parse(config('demo.simulation_date'))->startOfDay();
        $rows = $portfolio->transactions()->where('user_id', $portfolio->user_id)
            ->where('trade_date', '<=', $asOf->toDateString())->orderBy('trade_date')->orderBy('id')->limit(10001)->get();
        if ($rows->count() > 10000) {
            throw new RuntimeException('Portfolio exceeds the demo calculation limit.');
        }
        $ids = $rows->pluck('instrument_id')->filter()->unique()->values();
        $instruments = Instrument::whereIn('id', $ids)->get()->keyBy('id');
        $prices = MarketPrice::whereIn('instrument_id', $ids)->where('price_date', '<=', $asOf->toDateString())
            ->where('is_demo', true)->where('source', 'demo')->orderBy('price_date')->limit(50001)->get();
        if ($prices->count() > 50000) {
            throw new RuntimeException('Price history exceeds the demo calculation limit.');
        }
        $manual = BigDecimal::zero();
        foreach ($portfolio->user->manualAssets()->get() as $asset) {
            $manual = $manual->plus($asset->current_value);
        }
        $goals = $portfolio->user->goals()->orderBy('id')->get()->map(function ($goal): array {
            $target = BigDecimal::of($goal->target_amount);

            return [
                'id' => $goal->id, 'name' => $goal->name, 'target_amount' => $goal->target_amount,
                'saved_amount' => $goal->saved_amount,
                'progress_percent' => $target->isPositive() ? (string) BigDecimal::of($goal->saved_amount)->multipliedBy(100)->dividedBy($target, 2, RoundingMode::HalfUp) : null,
            ];
        })->all();
        $history = [];
        for ($offset = 29; $offset >= 0; $offset--) {
            $date = $asOf->subDays($offset)->toDateString();
            $point = $this->calculate($rows, $prices, $instruments, $date);
            $history[] = [
                'date' => $date, 'value' => $point['portfolio_value'],
                'known_value' => $point['known_portfolio_value'], 'status' => $point['status'],
            ];
        }
        $summary = $this->calculate($rows, $prices, $instruments, $asOf->toDateString());
        $reservedCash = BigDecimal::zero();
        $reservedQuantity = [];
        foreach ($portfolio->orders()->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])->with('reservation')->get() as $order) {
            if ($order->side === 'BUY') {
                $reservedCash = $reservedCash->plus($order->reservation?->cash_amount ?? 0);
            } else {
                $reservedQuantity[$order->instrument_id] = ($reservedQuantity[$order->instrument_id] ?? BigDecimal::zero())->plus($order->reservation?->quantity ?? 0);
            }
        }
        $availableHoldings = collect($summary['holdings'])->map(function (array $holding) use ($reservedQuantity): array {
            $reserved = $reservedQuantity[$holding['instrument_id']] ?? BigDecimal::zero();
            $holding['reserved_quantity'] = $this->decimal($reserved);
            $holding['available_quantity'] = $this->decimal(BigDecimal::of($holding['quantity'])->minus($reserved));

            return $holding;
        })->all();
        $summary['holdings'] = $availableHoldings;

        return array_merge($summary, [
            'portfolio_id' => $portfolio->id, 'currency' => 'VND', 'as_of' => $asOf->toDateString(), 'is_demo' => true,
            'manual_assets' => $this->decimal($manual),
            'reserved_cash' => $this->decimal($reservedCash),
            'available_cash' => $this->decimal(BigDecimal::of($summary['cash'])->minus($reservedCash)),
            'total_assets' => $summary['portfolio_value'] === null ? null : $this->decimal(BigDecimal::of($summary['portfolio_value'])->plus($manual)),
            'known_total_assets' => $this->decimal(BigDecimal::of($summary['known_portfolio_value'])->plus($manual)),
            'goals' => $goals, 'history' => $history,
        ]);
    }

    /** @return array<string, mixed> */
    private function calculate(Collection $rows, Collection $prices, Collection $instruments, string $date): array
    {
        $cash = $realized = $income = BigDecimal::zero();
        $positions = [];
        foreach ($rows as $row) {
            if ($row->trade_date->toDateString() > $date) {
                continue;
            }
            $cash = $cash->plus($row->cash_delta);
            if ($cash->isNegative()) {
                throw new RuntimeException('Invalid negative cash in portfolio history.');
            }
            if ($row->kind === 'DIVIDEND') {
                $income = $income->plus($row->cash_delta);

                continue;
            }
            if (in_array($row->kind, ['DEPOSIT', 'WITHDRAW'], true)) {
                continue;
            }
            if (! in_array($row->kind, ['BUY', 'SELL'], true)) {
                throw new RuntimeException('Unsupported transaction kind.');
            }
            $instrument = $instruments->get($row->instrument_id);
            if (! $instrument || $instrument->currency !== 'VND' || $instrument->asset_class !== 'stock') {
                throw new RuntimeException('Only VND equity positions can be valued in the demo.');
            }
            $id = $row->instrument_id;
            $positions[$id] ??= ['quantity' => BigDecimal::zero(), 'basis' => BigDecimal::zero()];
            $quantity = BigDecimal::of($row->quantity ?? '0');
            if (! $quantity->isPositive()) {
                throw new RuntimeException('Invalid transaction quantity.');
            }
            if ($row->kind === 'BUY') {
                $positions[$id]['quantity'] = $positions[$id]['quantity']->plus($quantity);
                $positions[$id]['basis'] = $positions[$id]['basis']->plus($row->gross_amount)->plus($row->fee)->plus($row->tax);
            } else {
                $position = $positions[$id];
                if ($quantity->isGreaterThan($position['quantity'])) {
                    throw new RuntimeException('Invalid short position in portfolio history.');
                }
                $soldBasis = $quantity->isEqualTo($position['quantity']) ? $position['basis']
                    : $position['basis']->multipliedBy($quantity)->dividedBy($position['quantity'], 8, RoundingMode::HalfUp);
                $positions[$id]['quantity'] = $position['quantity']->minus($quantity);
                $positions[$id]['basis'] = $position['basis']->minus($soldBasis);
                $realized = $realized->plus($row->gross_amount)->minus($row->fee)->minus($row->tax)->minus($soldBasis);
            }
        }
        $latest = $prices->filter(fn ($price) => $price->price_date->toDateString() <= $date)->keyBy('instrument_id');
        $securities = $basis = BigDecimal::zero();
        $holdings = $missing = [];
        foreach ($positions as $id => $position) {
            if ($position['quantity']->isZero()) {
                continue;
            }
            $quote = $latest->get($id);
            $validQuote = $quote && BigDecimal::of($quote->close)->isPositive();
            $value = $validQuote ? $position['quantity']->multipliedBy($quote->close) : null;
            $basis = $basis->plus($position['basis']);
            if ($value === null) {
                $missing[] = $id;
            } else {
                $securities = $securities->plus($value);
            }
            $holdings[] = [
                'instrument_id' => $id, 'symbol' => $instruments[$id]->symbol,
                'quantity' => $this->decimal($position['quantity']), 'cost_basis' => $this->decimal($position['basis']),
                'price' => $validQuote ? $quote->close : null, 'price_date' => $validQuote ? $quote->price_date->toDateString() : null,
                'market_value' => $value === null ? null : $this->decimal($value),
                'unrealized_pnl' => $value === null ? null : $this->decimal($value->minus($position['basis'])),
                'status' => $value === null ? 'missing_price' : 'complete',
            ];
        }
        $complete = $missing === [];
        $unrealized = $securities->minus($basis);

        return [
            'status' => $complete ? 'complete' : 'missing_price', 'cash' => $this->decimal($cash),
            'cost_basis' => $this->decimal($basis), 'realized_pnl' => $this->decimal($realized), 'net_income' => $this->decimal($income),
            'securities_value' => $complete ? $this->decimal($securities) : null, 'known_securities_value' => $this->decimal($securities),
            'unrealized_pnl' => $complete ? $this->decimal($unrealized) : null,
            'unrealized_return_percent' => $complete && $basis->isPositive() ? (string) $unrealized->multipliedBy(100)->dividedBy($basis, 2, RoundingMode::HalfUp) : null,
            'total_pnl' => $complete ? $this->decimal($realized->plus($income)->plus($unrealized)) : null,
            'portfolio_value' => $complete ? $this->decimal($cash->plus($securities)) : null,
            'known_portfolio_value' => $this->decimal($cash->plus($securities)),
            'holdings' => $holdings, 'missing_instrument_ids' => $missing,
        ];
    }

    private function decimal(BigDecimal $value): string
    {
        return (string) $value->toScale(8, RoundingMode::HalfUp);
    }
}
