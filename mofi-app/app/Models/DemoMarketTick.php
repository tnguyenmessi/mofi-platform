<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoMarketTick extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id', 'instrument_id', 'tick', 'simulated_at', 'reference_price',
        'ceiling_price', 'floor_price', 'last_price', 'last_quantity', 'total_volume',
        'bid1_price', 'bid1_quantity', 'bid2_price', 'bid2_quantity', 'bid3_price', 'bid3_quantity',
        'ask1_price', 'ask1_quantity', 'ask2_price', 'ask2_quantity', 'ask3_price', 'ask3_quantity',
        'source', 'is_demo',
    ];

    protected function casts(): array
    {
        return [
            'tick' => 'integer',
            'simulated_at' => 'datetime',
            'reference_price' => 'decimal:8',
            'ceiling_price' => 'decimal:8',
            'floor_price' => 'decimal:8',
            'last_price' => 'decimal:8',
            'last_quantity' => 'decimal:8',
            'total_volume' => 'decimal:8',
            'bid1_price' => 'decimal:8',
            'bid1_quantity' => 'decimal:8',
            'bid2_price' => 'decimal:8',
            'bid2_quantity' => 'decimal:8',
            'bid3_price' => 'decimal:8',
            'bid3_quantity' => 'decimal:8',
            'ask1_price' => 'decimal:8',
            'ask1_quantity' => 'decimal:8',
            'ask2_price' => 'decimal:8',
            'ask2_quantity' => 'decimal:8',
            'ask3_price' => 'decimal:8',
            'ask3_quantity' => 'decimal:8',
            'is_demo' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DemoMarketSession::class, 'session_id');
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }
}
