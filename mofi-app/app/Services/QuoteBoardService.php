<?php

namespace App\Services;

use App\Models\DemoMarketSession;
use App\Models\DemoMarketTick;
use App\Models\Instrument;
use App\Models\Order;
use App\Models\Portfolio;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class QuoteBoardService
{
    public function __construct(private MarketMatchingService $matching, private DemoMarketClock $clock) {}

    /** @return array<string, mixed> */
    public function board(Instrument $instrument): array
    {
        return DB::transaction(function () use ($instrument): array {
            return $this->serialize($this->synchronizeLocked($instrument));
        }, 3);
    }

    public function synchronizeLocked(Instrument $instrument): DemoMarketSession
    {
        $session = $this->ensureSessionLocked($instrument);
        if ($session->status !== 'OPEN') {
            return $session;
        }

        $next = DemoMarketTick::query()
            ->where('session_id', $session->id)
            ->where('tick', $session->current_tick + 1)
            ->lockForUpdate()
            ->first();
        if (! $next) {
            $session->update(['status' => 'CLOSED', 'last_advanced_at' => now(), 'revision' => $session->revision + 1]);

            return $this->ensureSessionLocked($instrument);
        }
        if (! $this->isDue($session)) {
            return $session;
        }
        $this->matching->matchOpenOrders($instrument, $next);
        $session->update([
            'current_tick' => $next->tick,
            'simulated_at' => $next->simulated_at,
            'last_advanced_at' => now(),
            'revision' => $session->revision + 1,
        ]);

        return $session;
    }

    public function sessionLocked(Instrument $instrument): DemoMarketSession
    {
        return $this->ensureSessionLocked($instrument);
    }

    public function advanceToTickLocked(Instrument $instrument, int $requestedTick): DemoMarketSession
    {
        $session = $this->ensureSessionLocked($instrument);
        if ($requestedTick < $session->current_tick) {
            return $session;
        }

        while ($session->current_tick < $requestedTick) {
            $next = DemoMarketTick::query()
                ->where('session_id', $session->id)
                ->where('tick', $session->current_tick + 1)
                ->lockForUpdate()
                ->first();
            if (! $next) {
                break;
            }
            $this->matching->matchOpenOrders($instrument, $next);
            $session->update([
                'current_tick' => $next->tick,
                'simulated_at' => $next->simulated_at,
                'last_advanced_at' => now(),
                'revision' => $session->revision + 1,
            ]);
        }

        $hasNext = DemoMarketTick::query()
            ->where('session_id', $session->id)
            ->where('tick', $session->current_tick + 1)
            ->exists();
        if (! $hasNext && $session->status === 'OPEN') {
            $session->update(['status' => 'CLOSED', 'last_advanced_at' => now(), 'revision' => $session->revision + 1]);

            return $this->ensureSessionLocked($instrument);
        }

        return $session;
    }

    public function currentTickLocked(DemoMarketSession $session): DemoMarketTick
    {
        return DemoMarketTick::query()
            ->where('session_id', $session->id)
            ->where('tick', $session->current_tick)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function ensureSessionLocked(Instrument $instrument): DemoMarketSession
    {
        // Serialize first-use session creation so two browser polls cannot race the unique key.
        $instrument = Instrument::query()->lockForUpdate()->findOrFail($instrument->id);
        $session = DemoMarketSession::query()
            ->where('instrument_id', $instrument->id)
            ->orderByDesc('session_date')
            ->lockForUpdate()
            ->first();

        $date = $this->clock->currentDate();
        if ($session?->status === 'CLOSED') {
            $this->expireOpenOrders($instrument);
            $nextDate = $this->nextTradingDate(CarbonImmutable::parse($session->session_date->toDateString(), config('app.timezone', 'Asia/Ho_Chi_Minh')));
            $date = max($date, $nextDate->toDateString());
            $session = null;
        } elseif ($session && $session->session_date->toDateString() < $date) {
            $session->update(['status' => 'CLOSED', 'last_advanced_at' => now(), 'revision' => $session->revision + 1]);
            $this->expireOpenOrders($instrument);
            $session = null;
        } elseif ($session) {
            $date = $session->session_date->toDateString();
        }

        if (! $session) {
            $session = DemoMarketSession::create([
                'instrument_id' => $instrument->id,
                'session_date' => $date,
                'status' => 'OPEN',
                'current_tick' => 0,
                'real_interval_seconds' => config('demo.market_real_interval_seconds', 5),
                'simulated_interval_minutes' => config('demo.market_simulated_interval_minutes', 5),
                'last_advanced_at' => now(),
            ]);
        }

        if ($session->ticks()->count() === 0) {
            $this->seedTicks($session, $instrument);
            $first = $session->ticks()->where('tick', 0)->firstOrFail();
            $session->update(['simulated_at' => $first->simulated_at, 'last_advanced_at' => $session->last_advanced_at ?? now()]);
            $session->refresh();
        }

        return $session;
    }

    private function nextTradingDate(CarbonImmutable $date): CarbonImmutable
    {
        do {
            $date = $date->addDay();
        } while ($date->isWeekend());

        return $date;
    }

    private function expireOpenOrders(Instrument $instrument): void
    {
        $orders = Order::query()
            ->where('instrument_id', $instrument->id)
            ->whereIn('status', ['OPEN', 'PARTIALLY_FILLED'])
            ->with('reservation')
            ->lockForUpdate()
            ->get();
        $portfolioIds = [];

        foreach ($orders as $order) {
            $order->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);
            $order->reservation?->update(['released_at' => now(), 'cash_amount' => '0', 'quantity' => '0']);
            $portfolioIds[$order->portfolio_id] = true;
        }

        foreach (array_keys($portfolioIds) as $portfolioId) {
            $portfolio = Portfolio::find($portfolioId);
            if ($portfolio) {
                DB::afterCommit(fn () => PortfolioSummary::forget($portfolio));
            }
        }
    }

    private function seedTicks(DemoMarketSession $session, Instrument $instrument): void
    {
        $baseRow = $instrument->marketPrices()
            ->where('source', 'demo')
            ->where('is_demo', true)
            ->whereDate('price_date', '<=', $session->session_date)
            ->latest('price_date')
            ->first();
        $base = (float) ($baseRow?->close ?? 100000);
        $reference = (float) ($baseRow?->reference_close ?: $base);
        $step = $this->priceStep($base);
        $floor = $this->roundPrice($reference * 0.93, $step);
        $ceiling = $this->roundPrice($reference * 1.07, $step);
        $date = CarbonImmutable::parse($session->session_date->toDateString(), config('app.timezone', 'Asia/Ho_Chi_Minh'));
        $times = [];
        $interval = max(1, (int) $session->simulated_interval_minutes);
        foreach ([['09:00', 150], ['13:00', 120]] as [$start, $minutes]) {
            $startAt = $date->setTimeFromTimeString($start);
            for ($offset = 0; $offset < $minutes; $offset += $interval) {
                $times[] = $startAt->addMinutes($offset);
            }
        }

        $rows = [];
        $totalVolume = 0;
        foreach ($times as $tick => $simulatedAt) {
            $seed = abs((int) crc32($instrument->id.'|'.$session->session_date->toDateString().'|'.$tick));
            $wave = sin($tick / 4.7) * 0.003;
            $trend = (($tick % 17) - 8) * 0.00015;
            $noise = (($seed % 1001) - 500) / 100000;
            $last = $this->roundPrice(max($floor, min($ceiling, $base * (1 + $wave + $trend + $noise))), $step);
            $lastQuantity = 50 + ($seed % 180);
            $totalVolume += $lastQuantity;
            $bid1 = max($floor, $last - $step);
            $bid2 = max($floor, $last - (2 * $step));
            $bid3 = max($floor, $last - (3 * $step));
            $ask1 = min($ceiling, $last + $step);
            $ask2 = min($ceiling, $last + (2 * $step));
            $ask3 = min($ceiling, $last + (3 * $step));
            $rows[] = [
                'session_id' => $session->id,
                'instrument_id' => $instrument->id,
                'tick' => $tick,
                'simulated_at' => $simulatedAt,
                'reference_price' => $this->decimal($reference),
                'ceiling_price' => $this->decimal($ceiling),
                'floor_price' => $this->decimal($floor),
                'last_price' => $this->decimal($last),
                'last_quantity' => $this->decimal($lastQuantity),
                'total_volume' => $this->decimal($totalVolume),
                'bid1_price' => $this->decimal($bid1), 'bid1_quantity' => $this->decimal(80 + ($seed % 250)),
                'bid2_price' => $this->decimal($bid2), 'bid2_quantity' => $this->decimal(70 + (($seed >> 3) % 220)),
                'bid3_price' => $this->decimal($bid3), 'bid3_quantity' => $this->decimal(60 + (($seed >> 6) % 190)),
                'ask1_price' => $this->decimal($ask1), 'ask1_quantity' => $this->decimal(75 + (($seed >> 2) % 240)),
                'ask2_price' => $this->decimal($ask2), 'ask2_quantity' => $this->decimal(65 + (($seed >> 5) % 210)),
                'ask3_price' => $this->decimal($ask3), 'ask3_quantity' => $this->decimal(55 + (($seed >> 8) % 180)),
                'source' => 'demo_market',
                'is_demo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DemoMarketTick::query()->insert($rows);
    }

    /** @return array<string, mixed> */
    private function serialize(DemoMarketSession $session): array
    {
        $tick = $this->currentTickLocked($session);
        $reference = BigDecimal::of($tick->reference_price);
        $change = BigDecimal::of($tick->last_price)->minus($reference)->toScale(8, RoundingMode::Down);
        $changePercent = $reference->isZero() ? BigDecimal::zero() : $change->dividedBy($reference, 8, RoundingMode::Down)->multipliedBy(100);
        $next = DemoMarketTick::query()->where('session_id', $session->id)->where('tick', $session->current_tick + 1)->first();
        $status = $session->status;

        return [
            'instrument' => $session->instrument()->firstOrFail()->only('id', 'symbol', 'name', 'market', 'currency', 'price_unit'),
            'session' => [
                'date' => $session->session_date->toDateString(),
                'status' => $status,
                'market_status' => $status === 'OPEN' ? 'Đang giao dịch' : 'Đã đóng cửa',
                'current_tick' => $session->current_tick,
                'total_ticks' => (int) ($session->ticks()->max('tick') ?? 0) + 1,
                'simulated_at' => $tick->simulated_at->toIso8601String(),
                'simulated_time' => $tick->simulated_at->setTimezone(config('app.timezone', 'Asia/Ho_Chi_Minh'))->format('H:i'),
                'next_simulated_at' => $next?->simulated_at?->toIso8601String(),
                'next_update_at' => $session->status === 'OPEN' && $session->last_advanced_at
                    ? $session->last_advanced_at->addSeconds($session->real_interval_seconds)->toIso8601String()
                    : null,
                'real_interval_seconds' => $session->real_interval_seconds,
                'simulated_interval_minutes' => $session->simulated_interval_minutes,
                'revision' => $session->revision,
            ],
            'quote' => [
                'reference_price' => (string) $tick->reference_price,
                'ceiling_price' => (string) $tick->ceiling_price,
                'floor_price' => (string) $tick->floor_price,
                'last_price' => (string) $tick->last_price,
                'change' => (string) $change,
                'change_percent' => (string) $changePercent->toScale(2, RoundingMode::Down),
                'last_quantity' => (string) $tick->last_quantity,
                'matched_volume' => (string) $tick->last_quantity,
                'total_volume' => (string) $tick->total_volume,
                'bids' => $this->levelsForResponse($tick, 'bid'),
                'asks' => $this->levelsForResponse($tick, 'ask'),
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function levelsForResponse(DemoMarketTick $tick, string $side): array
    {
        return collect([1, 2, 3])->map(fn (int $level): array => [
            'level' => $level,
            'price' => (string) $tick->{$side.$level.'_price'},
            'quantity' => (string) $tick->{$side.$level.'_quantity'},
        ])->all();
    }

    private function isDue(DemoMarketSession $session): bool
    {
        if (! $session->last_advanced_at) {
            return true;
        }

        return now()->getTimestamp() - $session->last_advanced_at->getTimestamp() >= $session->real_interval_seconds;
    }

    private function priceStep(float $price): int
    {
        return match (true) {
            $price >= 100000 => 100,
            $price >= 50000 => 50,
            $price >= 10000 => 10,
            default => 1,
        };
    }

    private function roundPrice(float $value, int $step): int
    {
        return max($step, (int) (round($value / $step) * $step));
    }

    private function decimal(float|int $value): string
    {
        return number_format($value, 8, '.', '');
    }
}
