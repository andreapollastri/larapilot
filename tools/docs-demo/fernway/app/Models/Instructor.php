<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instructor extends Model
{
    protected $fillable = ['studio_id', 'user_id', 'display_name', 'role', 'bio', 'invited_at', 'accepted_at'];

    protected $casts = ['invited_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
