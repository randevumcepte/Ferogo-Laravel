<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
        'verification_token',
        'token_expires_at',
        'token_used_at',
        'ip',
        'fingerprint',
    ];

    protected $casts = [
        'expires_at'       => 'datetime',
        'verified_at'      => 'datetime',
        'token_expires_at' => 'datetime',
        'token_used_at'    => 'datetime',
    ];

    protected $hidden = ['code_hash'];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isTokenValid(): bool
    {
        return $this->verification_token !== null
            && $this->token_used_at === null
            && $this->token_expires_at !== null
            && $this->token_expires_at->isFuture();
    }
}
