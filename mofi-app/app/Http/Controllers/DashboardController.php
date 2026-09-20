<?php

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Models\CommunityPost;
use App\Models\Instrument;
use App\Models\LearningProgress;
use App\Models\Notification;
use App\Models\Task;
use App\Models\WatchlistItem;
use App\Services\DemoMarketClock;
use App\Services\PortfolioSummary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortfolioSummary $summary, DemoMarketClock $clock): Response
    {
        $user = $request->user();
        $portfolio = $user->portfolio()->firstOrCreate(['user_id' => $user->id], ['name' => 'Danh mục VND của tôi', 'currency' => 'VND']);
        $page = $request->path();
        if ($page === 'transactions') {
            $request->validate([
                'kind' => ['nullable', 'in:DEPOSIT,WITHDRAW,BUY,SELL,DIVIDEND'],
                'symbol' => ['nullable', 'string', 'max:20'],
                'from' => ['nullable', 'date_format:Y-m-d'],
                'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
            ]);
        }
        $dashboard = $page === 'dashboard';
        $marketPages = $dashboard || in_array($page, ['transactions', 'market', 'watchlist', 'alerts', 'notifications'], true);
        $goalsPages = $dashboard || in_array($page, ['goals', 'copilot'], true);
        $assetsPages = $dashboard || $page === 'assets';
        $tasksPages = $dashboard || $page === 'tasks';
        $alertsPages = $dashboard || in_array($page, ['alerts', 'notifications'], true);
        $notificationsPages = in_array($page, ['alerts', 'notifications'], true);
        $learningPages = $dashboard || $page === 'learn';
        $strategyPages = $dashboard || $page === 'strategies';
        $simulationPages = $dashboard || $page === 'simulation';
        $communityPages = $dashboard || $page === 'community';
        $copilotPages = $dashboard || $page === 'copilot';
        $fullSummaryPages = in_array($page, ['dashboard', 'portfolio', 'assets', 'copilot', 'simulation'], true);
        $summaryData = $fullSummaryPages
            ? Cache::remember(PortfolioSummary::cacheKey($portfolio), now()->addSeconds(15), fn () => $summary->forPortfolio($portfolio))
            : ['portfolio_id' => $portfolio->id, 'as_of' => $clock->currentDate(), 'cash' => $page === 'transactions' ? (string) $portfolio->transactions()->sum('cash_delta') : '0.00000000', 'holdings' => [], 'history' => [], 'status' => 'partial', 'total_assets' => null, 'securities_value' => null, 'total_pnl' => null];
        $marketDate = $clock->currentDate();
        $market = $marketPages ? Cache::remember('mofi.demo.market.v2.'.$marketDate, now()->addMinutes(2), function () use ($marketDate) {
            return Instrument::with(['marketPrices' => fn ($q) => $q->where('price_date', '<=', $marketDate)->where('is_demo', true)->where('source', 'demo')->orderByDesc('price_date')->limit(30)])->orderBy('id')->get()->toArray();
        }) : [];

        $transactionQuery = $portfolio->transactions()->with('instrument')->orderByDesc('trade_date')->orderByDesc('id');
        if ($request->filled('kind') && in_array($request->string('kind')->toString(), ['DEPOSIT', 'WITHDRAW', 'BUY', 'SELL', 'DIVIDEND'], true)) {
            $transactionQuery->where('kind', $request->string('kind')->toString());
        }
        if ($request->filled('symbol')) {
            $transactionQuery->whereHas('instrument', fn ($query) => $query->where('symbol', 'like', '%'.$request->string('symbol')->toString().'%'));
        }
        if ($request->filled('from')) {
            $transactionQuery->whereDate('trade_date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $transactionQuery->whereDate('trade_date', '<=', $request->date('to'));
        }

        return Inertia::render('Workspace', [
            'page' => $request->path(), 'user' => $user->only('id', 'name', 'email'),
            'summary' => $summaryData, 'market' => $market,
            'transactions' => $page === 'transactions' ? $transactionQuery->paginate(20)->withQueryString() : ['data' => [], 'current_page' => 1, 'last_page' => 1],
            'orders' => $marketPages ? $portfolio->orders()->with(['instrument', 'reservation', 'execution', 'executions'])->latest('id')->limit(30)->get() : [],
            'goals' => $goalsPages ? $user->goals()->orderBy('id')->get() : [], 'assets' => $assetsPages ? $user->manualAssets()->get() : [],
            'watchlist' => $marketPages ? WatchlistItem::where('user_id', $user->id)->get() : [],
            'tasks' => $tasksPages ? Task::where('user_id', $user->id)->orderBy('id')->get() : [],
            'alerts' => $alertsPages ? AlertRule::with('instrument')->where('user_id', $user->id)->get() : [],
            'notifications' => $notificationsPages ? Notification::where('user_id', $user->id)->latest()->limit(100)->get() : [],
            'learning' => $learningPages ? LearningProgress::where('user_id', $user->id)->pluck('lesson_slug') : [],
            'strategies' => $strategyPages ? $user->investmentStrategies()->latest()->get() : [],
            'scenarios' => $simulationPages ? $user->simulationScenarios()->latest()->limit(10)->get() : [],
            'communityPosts' => $communityPages ? CommunityPost::with('user:id,name')->latest()->limit(30)->get() : [],
            'copilotHistory' => $copilotPages ? $user->copilotQuestions()->latest()->limit(20)->get() : [],
        ]);
    }
}
