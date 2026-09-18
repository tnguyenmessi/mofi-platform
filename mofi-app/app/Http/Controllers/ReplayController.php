<?php

namespace App\Http\Controllers;

use App\Models\Instrument;
use App\Services\DemoReplayProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReplayController extends Controller
{
    public function candles(Request $request, Instrument $instrument, DemoReplayProvider $provider): JsonResponse
    {
        $data = $request->validate(['days' => ['nullable', 'integer', 'min:1', 'max:90']]);

        return response()->json(['instrument' => $instrument->only('id', 'symbol', 'name'), 'interval' => '1d', 'is_demo' => true, 'points' => $provider->candles($instrument, (int) ($data['days'] ?? 30))], 200, ['Cache-Control' => 'public, max-age=60']);
    }
}
