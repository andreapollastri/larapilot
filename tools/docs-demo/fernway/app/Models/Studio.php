<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Studio extends Model
{
    use SoftDeletes;

    protected $fillable = ['owner_id', 'name', 'slug', 'city', 'timezone', 'late_cancel_minutes', 'brand_color'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function instructors(): HasMany
    {
        return $this->hasMany(Instructor::class);
    }

    public function classTypes(): HasMany
    {
        return $this->hasMany(ClassType::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
