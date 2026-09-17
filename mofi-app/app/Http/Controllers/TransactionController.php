<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Portfolio;
use App\Services\RecordTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransactionController extends Controller
{
    public function index(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);
        $input = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'between:1,100000']]);
        $rows = $portfolio->transactions()->where('user_id', $request->user()->id)
            ->orderByDesc('trade_date')->orderByDesc('id')->paginate($input['per_page'] ?? 25);

        return TransactionResource::collection($rows)->response()->header('Cache-Control', 'private, no-store');
    }

    public function store(StoreTransactionRequest $request, Portfolio $portfolio, RecordTransaction $record): JsonResponse
    {
        $result = $record->handle($request->user(), $portfolio, $request->validated());

        return (new TransactionResource($result['transaction']))->additional([
            'replayed' => $result['replayed'], 'summary' => $result['summary'],
        ])->response()->setStatusCode($result['replayed'] ? 200 : 201)->header('Cache-Control', 'private, no-store');
    }
}
