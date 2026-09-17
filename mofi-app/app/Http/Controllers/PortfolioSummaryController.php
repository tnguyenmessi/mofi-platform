<?php

namespace App\Http\Controllers;

use App\Http\Resources\PortfolioSummaryResource;
use App\Models\Portfolio;
use App\Services\PortfolioSummary;
use Illuminate\Http\Request;

class PortfolioSummaryController extends Controller
{
    public function __invoke(Request $request, Portfolio $portfolio, PortfolioSummary $summary): PortfolioSummaryResource
    {
        abort_unless((string) $portfolio->user_id === (string) $request->user()->id, 404);

        return new PortfolioSummaryResource($summary->forPortfolio($portfolio));
    }
}
