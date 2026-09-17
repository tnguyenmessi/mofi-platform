<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LiveMarketController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate(['symbol' => ['sometimes', 'string', 'in:BTCUSDT,ETHUSDT,SOLUSDT'], 'days' => ['sometimes', 'integer', 'in:7,30,90']]);
        $symbol = $input['symbol'] ?? 'BTCUSDT';
        $days = (int) ($input['days'] ?? 30);
        $key = 'market:binance:'.$symbol.':'.$days;
        if ($cached = Cache::get($key)) {
            return response()->json($cached);
        }
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://data-api.binance.vision/api/v3/klines', ['symbol' => $symbol, 'interval' => '1d', 'limit' => $days]);
        } catch (ConnectionException $exception) {
            return $this->unavailable();
        }
        $rows = $response->json();
        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            return $this->unavailable();
        }
        $points = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 7 || ! is_numeric($row[0]) || ! is_numeric($row[4]) || (float) $row[4] <= 0) {
                return $this->unavailable();
            }
            $points[] = ['date' => CarbonImmutable::createFromTimestampMs((int) $row[0], 'UTC')->toDateString(), 'close' => (float) $row[4]];
        }
        $result = ['symbol' => $symbol, 'days' => $days, 'fetched_at' => now()->toIso8601String(), 'source' => 'Binance public market data', 'points' => $points];
        Cache::put($key, $result, 60);

        return response()->json($result);
    }

    private function unavailable(): JsonResponse
    {
        return response()->json(['message' => 'Nguồn giá công khai hiện không phản hồi. Vui lòng thử lại sau.'], 502);
    }
}
