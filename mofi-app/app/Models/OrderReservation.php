<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReservation extends Model
{
    use HasFactory;

    protected $fillable = ['order_id', 'portfolio_id', 'cash_amount', 'quantity', 'released_at'];

    protected function casts(): array
    {
        return ['cash_amount' => 'decimal:0', 'quantity' => 'decimal:8', 'released_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
