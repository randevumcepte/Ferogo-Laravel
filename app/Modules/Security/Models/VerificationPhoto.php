<?php

namespace App\Modules\Security\Models;

use App\Models\User;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Güvenlik olayı sırasında sürücüden alınan zorunlu doğrulama fotoğrafları.
 *
 * Tipler: selfie | vehicle | plate
 * Durum:  pending_review | approved | rejected | expired
 */
class VerificationPhoto extends Model
{
    use HasFactory;

    public const TYPE_SELFIE  = 'selfie';
    public const TYPE_VEHICLE = 'vehicle';
    public const TYPE_PLATE   = 'plate';

    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED       = 'approved';
    public const STATUS_REJECTED       = 'rejected';
    public const STATUS_EXPIRED        = 'expired';

    protected $fillable = [
        'security_incident_id',
        'driver_id',
        'ride_request_id',
        'type',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'captured_lat',
        'captured_lng',
        'captured_at',
        'flash_used',
        'front_camera',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
        'reviewer_note',
    ];

    protected $casts = [
        'captured_lat' => 'decimal:7',
        'captured_lng' => 'decimal:7',
        'captured_at'  => 'datetime',
        'flash_used'   => 'boolean',
        'front_camera' => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public function incident(): BelongsTo
    {
        return $this->belongsTo(SecurityIncident::class, 'security_incident_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function rideRequest(): BelongsTo
    {
        return $this->belongsTo(RideRequest::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function url(): ?string
    {
        if (! $this->path) return null;
        try {
            return Storage::disk($this->disk ?: 'public')->url($this->path);
        } catch (\Throwable) {
            return null;
        }
    }
}
