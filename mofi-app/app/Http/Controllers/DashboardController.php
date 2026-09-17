<?php

namespace App\Http\Controllers;

use App\Services\PortfolioSummary;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PortfolioSummary $summary): View
    {
        $portfolio = $request->user()->portfolio()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['name' => 'Danh mục VND của tôi', 'currency' => 'VND'],
        );

        return view('mofi', [
            'mode' => 'dashboard',
            'user' => $request->user(),
            'summary' => $summary->forPortfolio($portfolio),
        ]);
    }
}
