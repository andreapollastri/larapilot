<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = ['class_session_id', 'user_id', 'status', 'checked_in_at', 'cancelled_at'];

    protected $casts = ['checked_in_at' => 'datetime', 'cancelled_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
