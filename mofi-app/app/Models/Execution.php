<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Execution extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'portfolio_id', 'instrument_id', 'transaction_id', 'quantity', 'unit_price', 'gross_amount', 'fee', 'tax', 'source', 'executed_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:8', 'unit_price' => 'decimal:8', 'gross_amount' => 'decimal:0', 'fee' => 'decimal:0', 'tax' => 'decimal:0', 'executed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
