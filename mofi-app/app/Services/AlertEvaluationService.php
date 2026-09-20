<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\DemoMarketSession;
use App\Models\MarketPrice;
use App\Models\Notification;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class AlertEvaluationService
{
    public function __construct(private DemoMarketClock $clock) {}

    public function evaluate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $date = $this->clock->currentDate();
            $rules = AlertRule::where('user_id', $user->id)->with('instrument')->where('enabled', true)->orderBy('id')->lockForUpdate()->get();
            foreach ($rules as $rule) {
                $session = DemoMarketSession::query()->where('instrument_id', $rule->instrument_id)->whereDate('session_date', $date)->latest('id')->first();
                $tick = $session?->ticks()->where('tick', $session->current_tick)->first();
                $observedPrice = $tick?->last_price;
                $sourceDate = $session?->session_date ?? $date;
                if ($observedPrice === null) {
                    $price = MarketPrice::where('instrument_id', $rule->instrument_id)->whereDate('price_date', $date)->where('source', 'demo')->where('is_demo', true)->latest('price_date')->first();
                    $observedPrice = $price?->close;
                    $sourceDate = $price?->price_date;
                }
                if ($observedPrice === null || ! BigDecimal::of($observedPrice)->isPositive()) {
                    continue;
                }
                $hit = $rule->operator === 'GTE' ? BigDecimal::of($observedPrice)->isGreaterThanOrEqualTo($rule->threshold) : BigDecimal::of($observedPrice)->isLessThanOrEqualTo($rule->threshold);
                if ($hit && ! $rule->last_condition) {
                    Notification::create(['user_id' => $user->id, 'source_rule_id' => $rule->id, 'title' => 'Giá mô phỏng đạt ngưỡng: '.$rule->instrument->symbol, 'body' => 'Giá đã đạt điều kiện cảnh báo bạn thiết lập. Đây là dữ liệu mô phỏng.', 'observed_price' => $observedPrice, 'source_date' => $sourceDate]);
                }
                $rule->update(['last_condition' => $hit, 'last_checked_at' => now()]);
            }
        });
    }
}
