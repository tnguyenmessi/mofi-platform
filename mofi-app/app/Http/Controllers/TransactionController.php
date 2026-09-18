<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Portfolio;
use App\Services\RecordTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public function index(Request $request, Portfolio $portfolio): JsonResponse
    {
        Gate::authorize('view', $portfolio);
        $input = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,100'], 'page' => ['sometimes', 'integer', 'between:1,100000']]);
        $rows = $portfolio->transactions()->with('instrument')->where('user_id', $request->user()->id)
            ->orderByDesc('trade_date')->orderByDesc('id')->paginate($input['per_page'] ?? 25);

        return TransactionResource::collection($rows)->response()->header('Cache-Control', 'private, no-store');
    }

    public function export(Request $request, Portfolio $portfolio): StreamedResponse
    {
        Gate::authorize('view', $portfolio);
        $input = $request->validate([
            'kind' => ['nullable', 'in:DEPOSIT,WITHDRAW,BUY,SELL,DIVIDEND'],
            'symbol' => ['nullable', 'string', 'max:20'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
        $query = $portfolio->transactions()->with('instrument')->where('user_id', $request->user()->id)
            ->orderBy('trade_date')->orderBy('id');
        if (! empty($input['kind'])) {
            $query->where('kind', $input['kind']);
        }
        if (! empty($input['symbol'])) {
            $query->whereHas('instrument', fn ($q) => $q->where('symbol', 'like', '%'.$input['symbol'].'%'));
        }
        if (! empty($input['from'])) {
            $query->whereDate('trade_date', '>=', $input['from']);
        }
        if (! empty($input['to'])) {
            $query->whereDate('trade_date', '<=', $input['to']);
        }
        $rows = $query->get();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Ngày', 'Loại', 'Mã', 'Số lượng', 'Giá đơn vị', 'Giá trị gộp', 'Phí', 'Thuế', 'Dòng tiền']);
            foreach ($rows as $row) {
                fputcsv($out, [$row->trade_date->toDateString(), $row->kind, $row->instrument?->symbol ?? 'Tiền mặt', $row->quantity, $row->unit_price, $row->gross_amount, $row->fee, $row->tax, $row->cash_delta]);
            }
            fclose($out);
        }, 'mofi-giao-dich.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function store(StoreTransactionRequest $request, Portfolio $portfolio, RecordTransaction $record): JsonResponse
    {
        $result = $record->handle($request->user(), $portfolio, $request->validated());

        $transaction = $result['transaction']->loadMissing('instrument');

        return (new TransactionResource($transaction))->additional([
            'replayed' => $result['replayed'], 'summary' => $result['summary'], 'receipt' => [
                'id' => $transaction->id, 'kind' => $transaction->kind,
                'trade_date' => $transaction->trade_date->toDateString(), 'created_at' => $transaction->created_at?->toIso8601String(),
                'gross_amount' => $transaction->gross_amount, 'fee' => $transaction->fee, 'tax' => $transaction->tax,
                'cash_delta' => $transaction->cash_delta, 'replayed' => $result['replayed'],
                'summary' => $transaction->kind.' · '.($transaction->instrument?->symbol ?? 'Tiền mặt'),
            ],
        ])->response()->setStatusCode($result['replayed'] ? 200 : 201)->header('Cache-Control', 'private, no-store');
    }
}
