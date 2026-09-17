<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'title', 'body', 'source_rule_id', 'observed_price', 'source_date', 'read_at'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
