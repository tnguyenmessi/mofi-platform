<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Services\DemoReplayProvider;
use App\Services\QuoteBoardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController extends Controller
{
    public function candles(Request $request, Instrument $instrument, DemoReplayProvider $provider): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:90']]);

        return response()->json(['instrument' => $instrument->only('id', 'symbol', 'name'), 'interval' => '1d', 'is_demo' => true, 'points' => $provider->candles($instrument, (int) ($data['days'] ?? 30))], 200, ['Cache-Control' => 'public, max-age=60']);
    }

    public function board(Instrument $instrument, QuoteBoardService $boards): JsonResponse
    {
        abort_unless($instrument->tradable && $instrument->asset_class === 'stock' && $instrument->market === 'VN' && $instrument->currency === 'VND', 404);

        return response()->json($boards->board($instrument), 200, ['Cache-Control' => 'private, no-store']);
    }
}
