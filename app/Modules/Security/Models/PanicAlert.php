<?php

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Acil yardım (panic) butonu kaydı.
 *
 * Sürücü veya müşteri "ACİL YARDIM" butonuna bastığında oluşur.
 *
 * Statüler:
 *   triggered | acknowledged | contacting | police_dispatched | resolved | false_alarm
 */
class PanicAlert extends Model
{
    use HasFactory;

    public const STATUS_TRIGGERED         = 'triggered';
    public const STATUS_ACKNOWLEDGED      = 'acknowledged';
    public const STATUS_CONTACTING        = 'contacting';
    public const STATUS_POLICE_DISPATCHED = 'police_dispatched';
    public const STATUS_RESOLVED          = 'resolved';
    public const STATUS_FALSE_ALARM       = 'false_alarm';

    public const TRIGGER_DRIVER   = 'driver';
    public const TRIGGER_CUSTOMER = 'customer';

    protected $fillable = [
        'public_id',
        'ride_request_id',
        'ride_id',
        'triggered_by_type',
        'triggered_by_user_id',
        'driver_id',
        'triggered_by_phone',
        'lat',
        'lng',
        'location_accuracy_m',
        'ip_address',
        'user_agent',
        'device_fingerprint',
        'status',
        'severity',
        'handler_user_id',
        'acknowledged_at',
        'first_contact_at',
        'police_called_at',
        'resolved_at',
        'operator_notes',
        'security_incident_id',
    ];

    protected $casts = [
        'lat'                  => 'decimal:7',
        'lng'                  => 'decimal:7',
        'location_accuracy_m'  => 'decimal:2',
        'acknowledged_at'      => 'datetime',
        'first_contact_at'     => 'datetime',
        'police_called_at'     => 'datetime',
        'resolved_at'          => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $alert) {
            if (empty($alert->public_id)) {
                $alert->public_id = (string) Str::ulid();
            }
            if (empty($alert->status)) {
                $alert->status = self::STATUS_TRIGGERED;
            }
            if (empty($alert->severity)) {
                $alert->severity = 'critical';
            }
        });
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function ride(): BelongsTo
    {
        return $this->belongsTo(Ride::class);
    }

    public function triggeredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_user_id');
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_FALSE_ALARM,
        ], true);
    }
}
