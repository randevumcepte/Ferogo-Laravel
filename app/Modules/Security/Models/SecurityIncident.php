<?php

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Booking\Models\Ride;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Güvenlik olayı kaydı.
 *
 * Tipler:
 *   visual_mismatch  : Müşteri "araç/sürücü resmi tutmuyor" dedi
 *   wrong_vehicle    : Sürücü farklı bir araçla geldi
 *   wrong_driver     : Sürücü kimliği uyumsuz
 *   driver_no_show   : Sürücü konuma gelmedi
 *   customer_no_show : Müşteri yerinde değil
 *   safety_concern   : Yolcu/sürücü kendini güvende hissetmiyor
 *   panic_button     : Acil yardım butonu basıldı
 *   other            : Manuel açılan vaka
 *
 * Durum:
 *   open | investigating | resolved_ok | resolved_suspended | escalated_police
 */
class SecurityIncident extends Model
{
    use HasFactory;

    public const TYPE_VISUAL_MISMATCH = 'visual_mismatch';
    public const TYPE_WRONG_VEHICLE   = 'wrong_vehicle';
    public const TYPE_WRONG_DRIVER    = 'wrong_driver';
    public const TYPE_DRIVER_NO_SHOW  = 'driver_no_show';
    public const TYPE_CUSTOMER_NO_SHOW = 'customer_no_show';
    public const TYPE_SAFETY_CONCERN  = 'safety_concern';
    public const TYPE_PANIC_BUTTON    = 'panic_button';
    public const TYPE_OTHER           = 'other';

    public const STATUS_OPEN                = 'open';
    public const STATUS_INVESTIGATING       = 'investigating';
    public const STATUS_RESOLVED_OK         = 'resolved_ok';
    public const STATUS_RESOLVED_SUSPENDED  = 'resolved_suspended';
    public const STATUS_ESCALATED_POLICE    = 'escalated_police';

    protected $fillable = [
        'public_id',
        'ride_request_id',
        'ride_id',
        'driver_id',
        'customer_user_id',
        'type',
        'reported_by',
        'reporter_note',
        'status',
        'severity',
        'handler_user_id',
        'acknowledged_at',
        'resolved_at',
        'resolution_note',
        'lat',
        'lng',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'resolved_at'     => 'datetime',
        'lat'             => 'decimal:7',
        'lng'             => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $incident) {
            if (empty($incident->public_id)) {
                $incident->public_id = (string) Str::ulid();
            }
            if (empty($incident->status)) {
                $incident->status = self::STATUS_OPEN;
            }
            if (empty($incident->severity)) {
                $incident->severity = 'high';
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

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handler_user_id');
    }

    public function verificationPhotos(): HasMany
    {
        return $this->hasMany(VerificationPhoto::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_INVESTIGATING], true);
    }
}
