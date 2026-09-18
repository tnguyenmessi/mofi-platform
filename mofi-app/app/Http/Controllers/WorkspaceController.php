<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\CommunityPost;
use App\Models\CopilotQuestion;
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
use Brick\Math\RoundingMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkspaceController extends Controller
{
    private const MODELS = ['goals' => Goal::class, 'assets' => ManualAsset::class, 'tasks' => Task::class, 'watchlist' => WatchlistItem::class, 'alerts' => AlertRule::class, 'notifications' => Notification::class, 'strategies' => InvestmentStrategy::class, 'scenarios' => SimulationScenario::class, 'community' => CommunityPost::class];

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
                'name' => ['required', 'string', 'max:120'], 'shock_percent' => ['required', 'integer', 'between:0,50'],
            ],
            'community' => [
                'title' => ['required', 'string', 'max:160'], 'body' => ['required', 'string', 'max:5000'],
            ],
        };
        $data = $request->validate($rules);
        if ($section === 'scenarios') {
            $portfolio = $request->user()->portfolio;
            if (! $portfolio) {
                throw ValidationException::withMessages(['name' => 'Hãy tạo danh mục trước khi lưu kịch bản.']);
            }
            $summary = app(PortfolioSummary::class)->forPortfolio($portfolio);
            if ($summary['total_assets'] === null || $summary['securities_value'] === null) {
                throw ValidationException::withMessages(['shock_percent' => 'Chưa đủ giá để lưu kịch bản.']);
            }
            $before = BigDecimal::of($summary['total_assets'])->toScale(0, RoundingMode::HalfUp);
            $loss = BigDecimal::of($summary['securities_value'])->multipliedBy($data['shock_percent'])
                ->dividedBy(100, 0, RoundingMode::HalfUp);
            $data += ['before_value' => (string) $before, 'after_value' => (string) $before->minus($loss), 'change_value' => (string) $loss->negated()];
        }
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

    public function askCopilot(Request $request, PortfolioSummary $portfolioSummary): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $user = $request->user();
        $portfolio = $user->portfolio()->firstOrCreate(['user_id' => $user->id], ['name' => 'Danh mục VND của tôi', 'currency' => 'VND']);
        $summary = $portfolioSummary->forPortfolio($portfolio);
        $question = mb_strtolower(trim($data['question']));
        if (str_contains($question, 'tiền mặt') || str_contains($question, 'tỷ trọng')) {
            $answer = $summary['total_assets'] === null || (float) $summary['total_assets'] <= 0
                ? 'Chưa đủ dữ liệu để tính tỷ trọng tiền mặt.'
                : 'Tiền mặt hiện chiếm '.number_format((float) $summary['cash'] * 100 / (float) $summary['total_assets'], 2, ',', '.').'% tổng tài sản. Đây là mô tả dữ liệu mô phỏng, không phải khuyến nghị.';
        } elseif (str_contains($question, 'mục tiêu') || str_contains($question, 'tiến độ')) {
            $goals = $user->goals()->get();
            $answer = $goals->isEmpty() ? 'Bạn chưa có mục tiêu tài chính.' : $goals->map(fn ($goal) => $goal->name.': '.number_format(min(100, (float) $goal->saved_amount * 100 / max(1, (float) $goal->target_amount)), 2, ',', '.').'%')->implode('; ');
        } else {
            $answer = 'Danh mục có '.count($summary['holdings']).' mã, tiền mặt '.number_format((float) $summary['cash'], 0, ',', '.').' đồng và lãi/lỗ tổng '.number_format((float) ($summary['total_pnl'] ?? 0), 0, ',', '.').' đồng. Đây là tóm tắt theo quy tắc từ dữ liệu mô phỏng, không phải khuyến nghị mua bán.';
        }
        CopilotQuestion::create(['user_id' => $user->id, 'question' => trim($data['question']), 'answer' => $answer, 'source' => 'rules']);

        return back()->with('success', 'Đã lưu câu hỏi và câu trả lời vào lịch sử Copilot.');
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
