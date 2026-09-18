<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\Portfolio;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function health(): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false, 'demo_portfolio' => false, 'market_fixture' => false];
        try {
            DB::select('select 1');
            $checks['database'] = true;
            $checks['demo_portfolio'] = Portfolio::exists();
            $checks['market_fixture'] = MarketPrice::whereDate('price_date', config('demo.simulation_date'))->where('source', 'demo')->where('is_demo', true)->exists();
        } catch (\Throwable) {
            // Return only availability; connection errors can contain credentials.
        }
        $key = 'mofi.health.'.Str::uuid();
        try {
            Cache::put($key, 'ok', 10);
            $checks['cache'] = Cache::get($key) === 'ok';
            Cache::forget($key);
        } catch (\Throwable) {
        }

        return response()->json(['checks' => $checks, 'checked_at' => now()->toIso8601String()], $checks['database'] && $checks['cache'] ? 200 : 503)->header('Cache-Control', 'private, no-store');
    }

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'user_search' => ['nullable', 'string', 'max:120'],
            'active' => ['nullable', 'in:0,1'],
            'instrument_search' => ['nullable', 'string', 'max:40'],
        ]);
        $usersQuery = User::select(['id', 'name', 'email', 'role', 'active'])->orderBy('id');
        if (! empty($filters['user_search'])) {
            $term = $filters['user_search'];
            $usersQuery->where(fn ($query) => $query->where('name', 'like', '%'.$term.'%')->orWhere('email', 'like', '%'.$term.'%'));
        }
        if (array_key_exists('active', $filters) && $filters['active'] !== null) {
            $usersQuery->where('active', (bool) $filters['active']);
        }
        $instrumentsQuery = Instrument::withCount('marketPrices')->orderBy('symbol');
        if (! empty($filters['instrument_search'])) {
            $term = $filters['instrument_search'];
            $instrumentsQuery->where(fn ($query) => $query->where('symbol', 'like', '%'.$term.'%')->orWhere('name', 'like', '%'.$term.'%'));
        }

        return view('admin', [
            'admin' => $request->user(),
            'users' => $usersQuery->paginate(20, ['*'], 'users_page')->withQueryString(),
            'instruments' => $instrumentsQuery->paginate(20, ['*'], 'instruments_page')->withQueryString(),
            'stats' => ['users' => User::count(), 'instruments' => Instrument::count(), 'prices' => MarketPrice::count()],
            'logs' => DB::table('admin_audit_logs')->orderByDesc('id')->paginate(20, ['*'], 'logs_page')->withQueryString(),
        ]);
    }

    public function toggleUser(User $user, Request $request): RedirectResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);
        DB::transaction(function () use ($user, $request): void {
            $target = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($target->isAdmin(), 422, 'Không thể khóa tài khoản quản trị.');
            if ($target->active === $request->boolean('active')) {
                return;
            }
            $target->forceFill(['active' => $request->boolean('active'), 'remember_token' => null])->save();
            $this->audit($request, $target->active ? 'user.unlocked' : 'user.locked', 'user', $target->id);
        });

        return back()->with('status', $request->boolean('active') ? 'Đã mở khóa tài khoản.' : 'Đã khóa tài khoản.');
    }

    public function toggleInstrument(Instrument $instrument, Request $request): RedirectResponse
    {
        $request->validate(['tradable' => ['required', 'boolean']]);
        DB::transaction(function () use ($instrument, $request): void {
            $target = Instrument::whereKey($instrument->id)->lockForUpdate()->firstOrFail();
            if ($target->tradable === $request->boolean('tradable')) {
                return;
            }
            $target->update(['tradable' => $request->boolean('tradable')]);
            $this->audit($request, $target->tradable ? 'instrument.enabled' : 'instrument.disabled', 'instrument', $target->id);
            DB::afterCommit(fn () => Cache::forget('mofi.demo.market.v2.'.config('demo.simulation_date')));
        });

        return back()->with('status', $request->boolean('tradable') ? 'Đã bật giao dịch.' : 'Đã tắt giao dịch.');
    }

    private function audit(Request $request, string $action, string $type, int $id): void
    {
        DB::table('admin_audit_logs')->insert(['admin_id' => $request->user()->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
