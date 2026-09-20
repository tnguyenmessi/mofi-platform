<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoMarketSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'instrument_id', 'session_date', 'status', 'current_tick', 'simulated_at',
        'last_advanced_at', 'real_interval_seconds', 'simulated_interval_minutes', 'revision',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date:Y-m-d',
            'current_tick' => 'integer',
            'simulated_at' => 'datetime',
            'last_advanced_at' => 'datetime',
            'real_interval_seconds' => 'integer',
            'simulated_interval_minutes' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function instrument(): BelongsTo
    {
        return $this->belongsTo(Instrument::class);
    }

    public function ticks(): HasMany
    {
        return $this->hasMany(DemoMarketTick::class, 'session_id');
    }
}
