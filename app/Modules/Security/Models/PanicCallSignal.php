<?php

namespace App\Modules\Security\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Panik WebRTC sinyalleşme mesajı (SDP/ICE).
 * from_role: user (arayan kişi) | operator (destek çalışanı)
 */
class PanicCallSignal extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'panic_alert_id',
        'from_role',
        'type',
        'payload',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(PanicAlert::class, 'panic_alert_id');
    }
}
