<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Portfolio;
use App\Services\PaperTradingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrderController extends Controller
{
    public function index(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);
        $orders = $portfolio->orders()->with(['instrument', 'reservation', 'execution'])->where('user_id', $request->user()->id)->latest('id')->paginate(min(100, $request->integer('per_page', 25)));

        return response()->json(['data' => $orders->items(), 'meta' => ['current_page' => $orders->currentPage(), 'last_page' => $orders->lastPage()]], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function store(StoreOrderRequest $request, Portfolio $portfolio, PaperTradingService $trading): JsonResponse
    {
        $result = $trading->place($request->user(), $portfolio, $request->validated());
        $order = $result['order'];

        return response()->json(['data' => $order, 'replayed' => $result['replayed'], 'filled' => $result['filled']], $result['replayed'] ? 200 : 201, ['Cache-Control' => 'private, no-store']);
    }

    public function cancel(Request $request, Order $order, PaperTradingService $trading): JsonResponse
    {
        $order = $trading->cancel($request->user(), $order);

        return response()->json(['data' => $order], 200, ['Cache-Control' => 'private, no-store']);
    }
}
