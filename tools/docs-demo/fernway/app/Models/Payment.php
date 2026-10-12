<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['membership_id', 'provider', 'provider_reference', 'amount_pence', 'currency', 'status', 'paid_at'];

    protected $casts = ['paid_at' => 'datetime'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }
}
