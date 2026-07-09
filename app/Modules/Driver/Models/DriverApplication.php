<?php

namespace App\Modules\Driver\Models;

use App\Models\User;
use App\Modules\Shared\Models\City;
use App\Modules\Vehicle\Models\VehicleMake;
use App\Modules\Vehicle\Models\VehicleModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'full_name',
        'tc_no',
        'phone',
        'email',
        'password_hash',
        'city_id',
        'birth_year',
        'gender',
        'license_class',
        'driver_category_id',
        'experience_band',
        'has_src',
        'has_vehicle',
        'vehicle_info',
        'vehicle_make_id',
        'vehicle_model_id',
        'vehicle_year',
        'vehicle_color',
        'vehicle_capacity',
        'vehicle_plate',
        // Fotoğraflar & belgeler
        'selfie_file_path',
        'id_front_file_path',
        'id_back_file_path',
        'license_front_file_path',
        'license_back_file_path',
        'vehicle_photos',
        'registration_file_path',
        'insurance_file_path',
        'inspection_file_path',
        'criminal_record_file_path',
        'src_file_path',
        'taksi_plaka_file_path',
        'taksimetre_file_path',
        'oda_kaydi_file_path',
        'psychotechnic_file_path',
        'helmet_file_path',
        // Meta
        'notes',
        'status',
        'source',
        'ip_address',
        'submitted_at',
        'reviewed_at',
        'reviewed_by',
        'rejection_reason',
    ];

    protected $casts = [
        'has_src'        => 'boolean',
        'has_vehicle'    => 'boolean',
        'birth_year'     => 'integer',
        'vehicle_year'   => 'integer',
        'vehicle_photos' => 'array',
        'submitted_at'   => 'datetime',
        'reviewed_at'    => 'datetime',
    ];

    protected $hidden = [
        'password_hash',
    ];

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DriverCategory::class, 'driver_category_id');
    }

    public function vehicleMake(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class);
    }

    public function vehicleModel(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
