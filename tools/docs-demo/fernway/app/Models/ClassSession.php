<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSession extends Model
{
    protected $fillable = ['class_type_id', 'instructor_id', 'room', 'starts_at', 'ends_at', 'capacity', 'cancelled'];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled' => 'boolean'];

    public function classType(): BelongsTo
    {
        return $this->belongsTo(ClassType::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function waitlist(): HasMany
    {
        return $this->hasMany(WaitlistEntry::class)->orderBy('position');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('starts_at', '>=', now())->where('cancelled', false)->orderBy('starts_at');
    }

    public function spotsLeft(): int
    {
        return max(0, $this->capacity - $this->bookings()->where('status', 'confirmed')->count());
    }
}
