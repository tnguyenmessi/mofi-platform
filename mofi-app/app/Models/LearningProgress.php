<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningProgress extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'lesson_slug', 'completed_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
