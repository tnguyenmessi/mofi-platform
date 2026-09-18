<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin', [
            'admin' => $request->user(),
            'users' => User::select(['id', 'name', 'email', 'role', 'active'])->orderBy('id')->paginate(20, ['*'], 'users_page')->withQueryString(),
            'instruments' => Instrument::withCount('marketPrices')->orderBy('symbol')->paginate(20, ['*'], 'instruments_page')->withQueryString(),
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
        });

        return back()->with('status', $request->boolean('tradable') ? 'Đã bật giao dịch.' : 'Đã tắt giao dịch.');
    }

    private function audit(Request $request, string $action, string $type, int $id): void
    {
        DB::table('admin_audit_logs')->insert(['admin_id' => $request->user()->id, 'action' => $action, 'target_type' => $type, 'target_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
    }
}
