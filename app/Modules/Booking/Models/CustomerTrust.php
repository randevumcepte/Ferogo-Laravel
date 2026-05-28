<?php

namespace App\Modules\Booking\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTrust extends Model
{
    protected $table = 'customer_trust';

    protected $fillable = [
        'phone',
        'total_requests',
        'total_completed',
        'total_customer_cancellations',
        'total_no_shows',
        'no_shows_24h',
        'no_shows_24h_window_start',
        'trust_score',
        'banned_until',
        'ban_reason',
        'is_blacklisted',
        'blacklisted_at',
        'blacklist_reason',
        'last_request_at',
        'last_no_show_at',
        'last_completed_at',
        'last_ip',
        'last_fingerprint',
    ];

    protected $casts = [
        'no_shows_24h_window_start' => 'datetime',
        'banned_until'              => 'datetime',
        'is_blacklisted'            => 'boolean',
        'blacklisted_at'            => 'datetime',
        'last_request_at'           => 'datetime',
        'last_no_show_at'           => 'datetime',
        'last_completed_at'         => 'datetime',
    ];

    public function isBanned(): bool
    {
        if ($this->is_blacklisted) return true;
        return $this->banned_until !== null && $this->banned_until->isFuture();
    }

    public function isNewCustomer(): bool
    {
        return $this->total_completed === 0;
    }

    public function trustLabel(): string
    {
        return match (true) {
            $this->trust_score >= 80 => 'guvenilir',
            $this->trust_score >= 50 => 'normal',
            $this->trust_score >= 25 => 'riskli',
            default                  => 'cok_riskli',
        };
    }
}
