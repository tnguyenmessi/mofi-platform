<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'portfolio_id' => $this->portfolio_id, 'instrument_id' => $this->instrument_id,
            'kind' => $this->kind, 'trade_date' => $this->trade_date->toDateString(),
            'quantity' => $this->quantity, 'unit_price' => $this->unit_price,
            'gross_amount' => $this->gross_amount, 'fee' => $this->fee, 'tax' => $this->tax,
            'cash_delta' => $this->cash_delta, 'request_key' => $this->request_key,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
