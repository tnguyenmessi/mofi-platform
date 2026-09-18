<?php

namespace App\Services;

use App\Models\AlertRule;
use App\Models\MarketPrice;
use App\Models\Notification;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

class AlertEvaluationService
{
    public function evaluate(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $rules = AlertRule::where('user_id', $user->id)->with('instrument')->where('enabled', true)->orderBy('id')->lockForUpdate()->get();
            foreach ($rules as $rule) {
                $price = MarketPrice::where('instrument_id', $rule->instrument_id)->whereDate('price_date', config('demo.simulation_date'))->where('source', 'demo')->where('is_demo', true)->latest('price_date')->first();
                if (! $price || ! BigDecimal::of($price->close)->isPositive()) {
                    continue;
                }
                $hit = $rule->operator === 'GTE' ? BigDecimal::of($price->close)->isGreaterThanOrEqualTo($rule->threshold) : BigDecimal::of($price->close)->isLessThanOrEqualTo($rule->threshold);
                if ($hit && ! $rule->last_condition) {
                    Notification::create(['user_id' => $user->id, 'source_rule_id' => $rule->id, 'title' => 'Giá mô phỏng đạt ngưỡng: '.$rule->instrument->symbol, 'body' => 'Giá đã đạt điều kiện cảnh báo bạn thiết lập. Đây là dữ liệu mô phỏng.', 'observed_price' => $price->close, 'source_date' => $price->price_date]);
                }
                $rule->update(['last_condition' => $hit, 'last_checked_at' => now()]);
            }
        });
    }
}
