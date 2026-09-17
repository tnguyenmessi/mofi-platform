<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Goal extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'category', 'target_amount', 'saved_amount', 'target_date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
