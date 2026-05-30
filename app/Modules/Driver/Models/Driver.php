<?php

namespace App\Modules\Driver\Models;

use App\Models\User;
use App\Modules\Shared\Models\City;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Vehicle\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'insurance_file_path',
        'insurance_expires_at',
        'inspection_file_path',
        'inspection_expires_at',
        'experience_band',
        'commission_rate',
        'availability_status',
        'current_lat',
        'current_lng',
        'last_location_updated_at',
        'approval_status',
        'approved_at',
        'approved_by',
        'rejection_reason',
        'rating',
        'total_rides',
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
        'rating' => 'decimal:2',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
}
