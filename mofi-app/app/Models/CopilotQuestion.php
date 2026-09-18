<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CopilotQuestion extends Model
{
    protected $fillable = ['user_id', 'question', 'answer', 'source'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
