<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Goal;
use App\Models\InvestmentStrategy;
use App\Models\LearningProgress;
use App\Models\ManualAsset;
use App\Models\MarketPrice;
use App\Models\Notification;
use App\Models\SimulationScenario;
use App\Models\Task;
use App\Models\WatchlistItem;
use App\Services\PortfolioSummary;
use Brick\Math\BigDecimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    private const MODELS = ['goals' => Goal::class, 'assets' => ManualAsset::class, 'tasks' => Task::class, 'watchlist' => WatchlistItem::class, 'alerts' => AlertRule::class, 'notifications' => Notification::class, 'strategies' => InvestmentStrategy::class, 'scenarios' => SimulationScenario::class];

    public function save(Request $request, string $section, ?int $id = null): RedirectResponse
    {
        abort_unless(isset(self::MODELS[$section]), 404);
        $class = self::MODELS[$section];
        $row = $id ? $class::where('user_id', $request->user()->id)->findOrFail($id) : null;
        $money = ['required', 'regex:/\A[0-9]{1,18}\z/'];
        $rules = match ($section) {
            'goals' => ['name' => ['required', 'string', 'max:120'], 'target_amount' => [...$money, 'not_in:0'], 'saved_amount' => $money, 'target_date' => ['nullable', 'date_format:Y-m-d']],
            'assets' => ['name' => ['required', 'string', 'max:120'], 'current_value' => $money],
            'tasks' => $row ? ['completed' => ['required', 'boolean']] : ['title' => ['required', 'string', 'max:240']],
            'watchlist' => ['instrument_id' => ['required', 'integer', 'exists:instruments,id']],
            'alerts' => $row ? ['enabled' => ['required', 'boolean']] : ['instrument_id' => ['required', 'integer', 'exists:instruments,id'], 'operator' => ['required', Rule::in(['GTE', 'LTE'])], 'threshold' => ['required', 'regex:/\A[0-9]{1,16}(?:\.[0-9]{1,8})?\z/', 'gt:0']],
            'notifications' => [],
            'strategies' => [
                'name' => ['required', 'string', 'max:120'], 'risk_profile' => ['required', Rule::in(['conservative', 'balanced', 'growth'])],
                'cash_percent' => ['required', 'integer', 'between:0,100'], 'stock_percent' => ['required', 'integer', 'between:0,100'], 'other_percent' => ['required', 'integer', 'between:0,100'], 'notes' => ['nullable', 'string', 'max:1000'],
            ],
            'scenarios' => [
                'name' => ['required', 'string', 'max:120'], 'shock_percent' => ['required', 'integer', 'between:0,50'], 'before_value' => ['required', 'regex:/\A[0-9]{1,18}\z/'], 'after_value' => ['required', 'regex:/\A[0-9]{1,18}\z/'], 'change_value' => ['required', 'regex:/\A-?[0-9]{1,18}\z/'],
            ],
        };
        $data = $request->validate($rules);
        if ($section === 'goals') {
            abort_if(! BigDecimal::of($data['target_amount'])->isPositive(), 422, 'Mục tiêu phải lớn hơn 0.');
            $data['category'] = 'other';
        }
        if ($section === 'assets') {
            $data += ['category' => 'other', 'valued_on' => config('demo.simulation_date')];
        }
        if ($section === 'tasks' && $row) {
            $data = ['completed_at' => $request->boolean('completed') ? now() : null];
        }
        if ($section === 'notifications') {
            abort_unless($row, 404);
            $data = ['read_at' => $row->read_at ?? now()];
        }
        if ($section === 'strategies') {
            abort_unless($data['cash_percent'] + $data['stock_percent'] + $data['other_percent'] === 100, 422, 'Tỷ trọng phải cộng đúng 100%.');
            $data['allocation'] = ['cash' => $data['cash_percent'], 'stocks' => $data['stock_percent'], 'other' => $data['other_percent']];
            unset($data['cash_percent'], $data['stock_percent'], $data['other_percent']);
        }
        if ($section === 'watchlist') {
            WatchlistItem::firstOrCreate(['user_id' => $request->user()->id, 'instrument_id' => $data['instrument_id']]);
        } elseif ($row) {
            $row->update($data);
        } else {
            $class::create(['user_id' => $request->user()->id, ...$data]);
        }
        if (in_array($section, ['goals', 'assets'], true)) {
            $portfolio = $request->user()->portfolio;
            if ($portfolio) {
                PortfolioSummary::forget($portfolio);
            }
        }

        return back()->with('success', 'Đã lưu thay đổi.');
    }

    public function destroy(Request $request, string $section, int $id): RedirectResponse
    {
        abort_unless(isset(self::MODELS[$section]) && $section !== 'notifications', 404);
        self::MODELS[$section]::where('user_id', $request->user()->id)->findOrFail($id)->delete();
        if (in_array($section, ['goals', 'assets'], true)) {
            $portfolio = $request->user()->portfolio;
            if ($portfolio) {
                PortfolioSummary::forget($portfolio);
            }
        }

        return back()->with('success', 'Đã xóa mục đã chọn.');
    }

    public function checkAlerts(Request $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $rules = AlertRule::where('user_id', $request->user()->id)->where('enabled', true)->orderBy('id')->lockForUpdate()->get();
            foreach ($rules as $rule) {
                $price = MarketPrice::where('instrument_id', $rule->instrument_id)->where('price_date', '<=', config('demo.simulation_date'))->where('source', 'demo')->where('is_demo', true)->latest('price_date')->first();
                if (! $price || ! BigDecimal::of($price->close)->isPositive()) {
                    continue;
                }
                $hit = $rule->operator === 'GTE' ? BigDecimal::of($price->close)->isGreaterThanOrEqualTo($rule->threshold) : BigDecimal::of($price->close)->isLessThanOrEqualTo($rule->threshold);
                if ($hit && ! $rule->last_condition) {
                    Notification::create(['user_id' => $request->user()->id, 'source_rule_id' => $rule->id, 'title' => 'Giá mô phỏng đạt ngưỡng: '.$rule->instrument->symbol, 'body' => 'Giá đã đạt điều kiện cảnh báo bạn thiết lập. Đây là dữ liệu mô phỏng.', 'observed_price' => $price->close, 'source_date' => $price->price_date]);
                }
                $rule->update(['last_condition' => $hit, 'last_checked_at' => now()]);
            }
        });

        return back()->with('success', 'Đã kiểm tra giá mô phỏng. Xem kết quả trong Thông báo.');
    }

    public function lesson(Request $request): RedirectResponse
    {
        $data = $request->validate(['lesson_slug' => ['required', Rule::in(['cash-flow', 'diversification', 'compound-interest'])]]);
        LearningProgress::firstOrCreate(['user_id' => $request->user()->id, ...$data], ['completed_at' => now()]);

        return back()->with('success', 'Đã lưu tiến độ học tập.');
    }

    public function settings(Request $request): RedirectResponse
    {
        $request->user()->update($request->validate(['name' => ['required', 'string', 'max:120']]));

        return back()->with('success', 'Đã cập nhật tên của bạn.');
    }
}
