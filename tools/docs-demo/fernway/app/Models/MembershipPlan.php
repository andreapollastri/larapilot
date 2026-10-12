<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPlan extends Model
{
    protected $fillable = ['studio_id', 'name', 'kind', 'credits', 'valid_days', 'price_pence'];

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }
}
