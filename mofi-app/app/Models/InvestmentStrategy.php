<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentStrategy extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $strategy): void {
            $strategy->user_id ??= auth()->id();
        });
    }

    use HasFactory;

    protected $table = 'investment_strategies';

    protected $fillable = ['user_id', 'name', 'risk_profile', 'allocation', 'notes'];

    protected function casts(): array
    {
        return ['allocation' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
