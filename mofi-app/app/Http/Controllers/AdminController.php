<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Models\MarketPrice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('admin', [
            'admin' => $request->user(),
            'users' => User::select(['id', 'name', 'email', 'role'])->orderBy('id')->paginate(20, ['*'], 'users_page')->withQueryString(),
            'instruments' => Instrument::withCount('marketPrices')->orderBy('symbol')->paginate(20, ['*'], 'instruments_page')->withQueryString(),
            'stats' => ['users' => User::count(), 'instruments' => Instrument::count(), 'prices' => MarketPrice::count()],
        ]);
    }
}
