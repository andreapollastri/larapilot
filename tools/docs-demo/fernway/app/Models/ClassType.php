<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassType extends Model
{
    protected $fillable = ['studio_id', 'name', 'intensity', 'duration_minutes', 'default_capacity'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClassSession::class);
    }
}
