<?php

namespace App\Services;

use App\Models\DemoMarketSession;
use Carbon\CarbonImmutable;

class DemoMarketClock
{
    public function currentDate(): string
    {
        $base = CarbonImmutable::parse(config('demo.simulation_date'), config('app.timezone', 'Asia/Ho_Chi_Minh'));

        try {
            $latest = DemoMarketSession::query()->max('session_date');
        } catch (\Throwable) {
            return $base->toDateString();
        }

        if (! $latest) {
            return $base->toDateString();
        }

        $latestDate = CarbonImmutable::parse((string) $latest, config('app.timezone', 'Asia/Ho_Chi_Minh'));

        return ($latestDate->greaterThan($base) ? $latestDate : $base)->toDateString();
    }
}
