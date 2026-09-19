<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'portfolio_id', 'instrument_id', 'side', 'order_type', 'quantity', 'limit_price', 'filled_quantity', 'status', 'request_key', 'request_hash', 'filled_at', 'cancelled_at'];

    protected $hidden = ['request_key', 'request_hash'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:8', 'limit_price' => 'decimal:8', 'filled_quantity' => 'decimal:8', 'filled_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(OrderReservation::class);
    }

    public function execution(): HasOne
    {
        return $this->hasOne(Execution::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }
}
