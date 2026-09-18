<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationScenario extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $scenario): void {
            $scenario->user_id ??= auth()->id();
        });
    }

    protected $fillable = ['user_id', 'name', 'shock_percent', 'before_value', 'after_value', 'change_value'];

    protected function casts(): array
    {
        return ['shock_percent' => 'integer', 'before_value' => 'decimal:0', 'after_value' => 'decimal:0', 'change_value' => 'decimal:0'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
