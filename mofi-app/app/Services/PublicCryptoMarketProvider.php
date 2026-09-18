<?php

namespace App\Services;

use App\Contracts\MarketDataProvider;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PublicCryptoMarketProvider implements MarketDataProvider
{
    public function history(string $symbol, int $days): array
    {
        $key = 'market:binance:'.$symbol.':'.$days;
        if ($cached = Cache::get($key)) {
            return $cached;
        }
        try {
            $response = Http::connectTimeout(3)->timeout(5)->get('https://data-api.binance.vision/api/v3/klines', ['symbol' => $symbol, 'interval' => '1d', 'limit' => $days]);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Nguồn giá công khai hiện không phản hồi.', previous: $exception);
        }
        $rows = $response->json();
        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            throw new RuntimeException('Nguồn giá công khai hiện không phản hồi.');
        }
        $points = [];
        foreach ($rows as $row) {
            if (! is_array($row) || count($row) < 7 || ! is_numeric($row[0]) || ! is_numeric($row[4]) || (float) $row[4] <= 0) {
                throw new RuntimeException('Nguồn giá công khai trả dữ liệu không hợp lệ.');
            }
            $points[] = ['date' => CarbonImmutable::createFromTimestampMs((int) $row[0], 'UTC')->toDateString(), 'close' => (float) $row[4]];
        }
        $result = ['symbol' => $symbol, 'days' => $days, 'fetched_at' => now()->toIso8601String(), 'source' => 'Binance public market data', 'points' => $points, 'status' => 'fresh', 'is_demo' => false, 'stale_after' => 60];
        Cache::put($key, $result, 60);

        return $result;
    }
}
