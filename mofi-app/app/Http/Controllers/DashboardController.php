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
        $market = Cache::remember('mofi.demo.market.'.config('demo.simulation_date'), now()->addMinutes(2), function () {
            return Instrument::with(['marketPrices' => fn ($q) => $q->where('price_date', '<=', config('demo.simulation_date'))->where('is_demo', true)->where('source', 'demo')->orderByDesc('price_date')->limit(30)])->orderBy('id')->get();
        });

        return Inertia::render('Workspace', [
            'page' => $request->path(), 'user' => $user->only('id', 'name', 'email'),
            'summary' => Cache::remember(PortfolioSummary::cacheKey($portfolio), now()->addSeconds(15), fn () => $summary->forPortfolio($portfolio)), 'market' => $market,
            'transactions' => $portfolio->transactions()->with('instrument')->orderByDesc('id')->paginate(20)->withQueryString(),
            'goals' => $user->goals()->orderBy('id')->get(), 'assets' => $user->manualAssets()->get(),
            'watchlist' => WatchlistItem::where('user_id', $user->id)->get(),
            'tasks' => Task::where('user_id', $user->id)->orderBy('id')->get(),
            'alerts' => AlertRule::with('instrument')->where('user_id', $user->id)->get(),
            'notifications' => Notification::where('user_id', $user->id)->latest()->limit(100)->get(),
            'learning' => LearningProgress::where('user_id', $user->id)->pluck('lesson_slug'),
        ]);
    }
}
