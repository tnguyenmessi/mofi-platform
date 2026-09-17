<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Instrument extends Model
{
    use HasFactory;

    protected $fillable = ['symbol', 'name', 'market', 'asset_class', 'sector', 'currency', 'price_unit', 'tradable'];

    public function marketPrices(): HasMany
    {
        return $this->hasMany(MarketPrice::class);
    }
}
