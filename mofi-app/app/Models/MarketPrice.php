<?php

namespace App\Models;

use Database\Factories\MarketPriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPrice extends Model
{
    /** @use HasFactory<MarketPriceFactory> */
    use HasFactory;

    protected $fillable = ['instrument_id', 'price_date', 'close', 'reference_close', 'source', 'is_demo'];

    protected function casts(): array
    {
        return [
            'price_date' => 'date:Y-m-d',
            'close' => 'decimal:8',
            'reference_close' => 'decimal:8',
            'is_demo' => 'boolean',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
