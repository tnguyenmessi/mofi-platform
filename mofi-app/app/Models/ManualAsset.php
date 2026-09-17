<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualAsset extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'category', 'current_value', 'valued_on'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
