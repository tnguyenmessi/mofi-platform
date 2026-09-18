<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\Instrument;
use App\Models\LearningProgress;
use App\Models\Notification;
use App\Models\Task;
use App\Models\WatchlistItem;
use App\Services\PortfolioSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortfolioSummary $summary): Response
    {
        $user = $request->user();
        $portfolio = $user->portfolio()->firstOrCreate(['user_id' => $user->id], ['name' => 'Danh mục VND của tôi', 'currency' => 'VND']);
        $page = $request->path();
        $dashboard = $page === 'dashboard';
        $marketPages = $dashboard || in_array($page, ['transactions', 'market', 'watchlist', 'alerts', 'notifications'], true);
        $goalsPages = $dashboard || in_array($page, ['goals', 'copilot'], true);
        $assetsPages = $dashboard || $page === 'assets';
        $tasksPages = $dashboard || $page === 'tasks';
        $alertsPages = $dashboard || in_array($page, ['alerts', 'notifications'], true);
        $notificationsPages = in_array($page, ['alerts', 'notifications'], true);
        $learningPages = $dashboard || $page === 'learn';
        $fullSummaryPages = in_array($page, ['dashboard', 'portfolio', 'assets', 'copilot', 'simulation'], true);
        $summaryData = $fullSummaryPages
            ? Cache::remember(PortfolioSummary::cacheKey($portfolio), now()->addSeconds(15), fn () => $summary->forPortfolio($portfolio))
            : ['portfolio_id' => $portfolio->id, 'as_of' => config('demo.simulation_date'), 'cash' => $page === 'transactions' ? (string) $portfolio->transactions()->sum('cash_delta') : '0.00000000', 'holdings' => [], 'history' => [], 'status' => 'partial', 'total_assets' => null, 'securities_value' => null, 'total_pnl' => null];
        $market = $marketPages ? Cache::remember('mofi.demo.market.v2.'.config('demo.simulation_date'), now()->addMinutes(2), function () {
            return Instrument::with(['marketPrices' => fn ($q) => $q->where('price_date', '<=', config('demo.simulation_date'))->where('is_demo', true)->where('source', 'demo')->orderByDesc('price_date')->limit(30)])->orderBy('id')->get()->toArray();
        }) : [];

        return Inertia::render('Workspace', [
            'page' => $request->path(), 'user' => $user->only('id', 'name', 'email'),
            'summary' => $summaryData, 'market' => $market,
            'transactions' => $page === 'transactions' ? $portfolio->transactions()->with('instrument')->orderByDesc('id')->paginate(20)->withQueryString() : ['data' => [], 'current_page' => 1, 'last_page' => 1],
            'goals' => $goalsPages ? $user->goals()->orderBy('id')->get() : [], 'assets' => $assetsPages ? $user->manualAssets()->get() : [],
            'watchlist' => $marketPages ? WatchlistItem::where('user_id', $user->id)->get() : [],
            'tasks' => $tasksPages ? Task::where('user_id', $user->id)->orderBy('id')->get() : [],
            'alerts' => $alertsPages ? AlertRule::with('instrument')->where('user_id', $user->id)->get() : [],
            'notifications' => $notificationsPages ? Notification::where('user_id', $user->id)->latest()->limit(100)->get() : [],
            'learning' => $learningPages ? LearningProgress::where('user_id', $user->id)->pluck('lesson_slug') : [],
        ]);
    }
}
