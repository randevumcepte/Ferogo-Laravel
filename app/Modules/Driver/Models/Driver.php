<?php

namespace App\Modules\Driver\Models;

use App\Models\User;
use App\Modules\Shared\Models\City;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'city_id',
        'current_vehicle_id',
        'license_class',
        'driver_category_id',
        'license_issued_at',
        'license_expires_at',
        'license_file_path',
        'src_certificate_number',
        'src_expires_at',
        'src_file_path',
        'psychotechnic_test_at',
        'psychotechnic_file_path',
        'criminal_record_at',
        'criminal_record_file_path',
        'selfie_file_path',
        'selfie_approved_at',
        'submitted_at',
        'insurance_file_path',
        'insurance_expires_at',
        'inspection_file_path',
        'inspection_expires_at',
        'experience_band',
        'commission_rate',
        'availability_status',
        'women_passengers_only',
        'current_lat',
        'current_lng',
        'last_location_updated_at',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rating',
        'total_rides',
        // ─── Paket aboneliği (Martı TAG benzeri) — dispatch buradan kontrol eder ───
        'package_active_until',
        // ─── Güvenlik askıya alma (security suspension) ───
        'is_suspended',
        'suspended_at',
        'suspended_reason',
        'suspended_by_user_id',
        'suspended_via_incident_id',
        'reinstated_at',
    ];

    protected $casts = [
        'license_issued_at' => 'date',
        'license_expires_at' => 'date',
        'src_expires_at' => 'date',
        'psychotechnic_test_at' => 'date',
        'criminal_record_at' => 'date',
        'insurance_expires_at' => 'date',
        'inspection_expires_at' => 'date',
        'commission_rate' => 'decimal:2',
        'current_lat' => 'decimal:7',
        'current_lng' => 'decimal:7',
        'last_location_updated_at' => 'datetime',
        'approved_at' => 'datetime',
        'selfie_approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'rating' => 'decimal:2',
        'women_passengers_only' => 'boolean',
        'package_active_until' => 'datetime',
        'is_suspended'   => 'boolean',
        'suspended_at'   => 'datetime',
        'reinstated_at'  => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DriverCategory::class, 'driver_category_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function currentVehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'current_vehicle_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Bu sürücüyü favorileyen müşteriler. Sosyal kanıt için sayım kaynağı
     * (radar listesinde "♥ N" rozeti).
     */
    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'customer_favorite_drivers',
            'driver_id',
            'user_id',
        );
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by_user_id');
    }

    public function suspendedViaIncident(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Security\Models\SecurityIncident::class, 'suspended_via_incident_id');
    }

    public function packages()
    {
        return $this->hasMany(\App\Modules\Payment\Models\DriverPackage::class);
    }

    public function activePackage()
    {
        return $this->hasOne(\App\Modules\Payment\Models\DriverPackage::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latestOfMany('expires_at');
    }

    /**
     * Sürücünün aktif paketi var mı? (radar/dispatch için)
     * package_active_until — driver_packages tablosundan cache'lenmiş alan.
     */
    public function hasActivePackage(): bool
    {
        // Test/QA modu: config('services.driver.enforce_packages') = false ise
        // paket zorunluluğunu tamamen bypass et. Prod'da mutlaka true olmalı.
        if (! config('services.driver.enforce_packages', true)) {
            return true;
        }

        return $this->package_active_until !== null
            && $this->package_active_until->isFuture();
    }

    /**
     * Sürücü dispatch'e dahil edilebilir mi?
     * (Onaylı + online + askıda değil + aktif paket var)
     */
    public function isDispatchable(): bool
    {
        return $this->approval_status === 'approved'
            && $this->availability_status === 'online'
            && ! $this->is_suspended
            && $this->hasActivePackage();
    }
}
