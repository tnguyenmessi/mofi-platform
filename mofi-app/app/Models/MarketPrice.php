<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPrice extends Model
{
    use HasFactory;

    protected $fillable = ['instrument_id', 'price_date', 'close', 'reference_close', 'source', 'is_demo'];

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
