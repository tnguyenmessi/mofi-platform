<?php

namespace App\Http\Controllers;

use App\Contracts\MarketDataProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class LiveMarketController extends Controller
{
    public function __invoke(Request $request, MarketDataProvider $provider): JsonResponse
    {
        $input = $request->validate(['symbol' => ['sometimes', 'string', 'in:BTCUSDT,ETHUSDT,SOLUSDT'], 'days' => ['sometimes', 'integer', 'in:7,30,90']]);
        $symbol = $input['symbol'] ?? 'BTCUSDT';
        $days = (int) ($input['days'] ?? 30);
        try {
            return response()->json($provider->history($symbol, $days));
        } catch (RuntimeException $exception) {
            return $this->unavailable();
        }
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Nguồn giá công khai hiện không phản hồi. Vui lòng thử lại sau.'], 502);
    }
}
