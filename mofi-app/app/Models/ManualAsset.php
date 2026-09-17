<?php

namespace App\Models;

use Database\Factories\ManualAssetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAsset extends Model
{
    /** @use HasFactory<ManualAssetFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'category', 'current_value', 'valued_on'];

    protected function casts(): array
    {
        return [
            'current_value' => 'decimal:0',
            'valued_on' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
