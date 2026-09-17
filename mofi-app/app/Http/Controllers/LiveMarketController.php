<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LiveMarketController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $symbol = strtoupper($request->query('symbol', 'BTCUSDT'));
        abort_unless(in_array($symbol, ['BTCUSDT', 'ETHUSDT', 'SOLUSDT'], true), 422, 'Cặp giao dịch chưa được hỗ trợ.');
        $response = Http::connectTimeout(3)->timeout(5)->get('https://data-api.binance.vision/api/v3/klines', ['symbol' => $symbol, 'interval' => '1d', 'limit' => 30]);
        if ($response->failed()) {
            return response()->json(['message' => 'Nguồn giá công khai hiện không phản hồi.'], 502);
        }
        $points = collect($response->json())->map(fn (array $row): array => ['date' => CarbonImmutable::createFromTimestampMs((int) $row[0])->toDateString(), 'close' => (float) $row[4]])->values();

        return response()->json(['symbol' => $symbol, 'fetched_at' => now()->toIso8601String(), 'source' => 'Binance public market data', 'points' => $points]);
    }
}
