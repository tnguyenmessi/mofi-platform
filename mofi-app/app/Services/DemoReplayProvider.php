<?php

namespace App\Services;

use App\Models\Instrument;
use Brick\Math\BigDecimal;

class DemoReplayProvider
{
    /** @return array<int, array<string, string>> */
    public function candles(Instrument $instrument, int $days = 30): array
    {
        $prices = $instrument->marketPrices()->where('source', 'demo')->where('is_demo', true)
            ->where('price_date', '<=', config('demo.simulation_date'))->latest('price_date')->limit(max(1, min(90, $days)))->get()->reverse()->values();
        $previous = null;

        return $prices->map(function ($price) use (&$previous): array {
            $close = BigDecimal::of($price->close);
            $open = $previous ?? $close;
            $spread = $close->multipliedBy('0.01');
            $high = $close->plus($spread);
            $low = $close->minus($spread)->isPositive() ? $close->minus($spread) : BigDecimal::zero();
            $previous = $close;

            return ['time' => $price->price_date->toDateString(), 'open' => (string) $open, 'high' => (string) $high, 'low' => (string) $low, 'close' => (string) $close, 'volume' => '1000.00000000', 'source' => 'demo_replay'];
        })->all();
    }

    /** @return array<string, string>|null */
    public function candleAt(Instrument $instrument, int $tick): ?array
    {
        $candles = $this->candles($instrument, 90);

        return $candles[$tick] ?? null;
    }
}
