<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'portfolio_id', 'instrument_id', 'kind', 'trade_date', 'quantity', 'unit_price', 'gross_amount', 'fee', 'tax', 'cash_delta', 'request_key', 'request_hash'];

    protected function casts(): array
    {
        return [
            'trade_date' => 'date:Y-m-d',
            'quantity' => 'decimal:8',
            'unit_price' => 'decimal:8',
            'gross_amount' => 'decimal:0',
            'fee' => 'decimal:0',
            'tax' => 'decimal:0',
            'cash_delta' => 'decimal:0',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
