<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['user_id', 'portfolio_id', 'instrument_id', 'kind', 'trade_date', 'quantity', 'unit_price', 'gross_amount', 'fee', 'tax', 'cash_delta', 'request_key', 'request_hash'];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
