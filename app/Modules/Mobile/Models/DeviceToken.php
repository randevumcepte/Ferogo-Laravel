<?php

namespace App\Modules\Mobile\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceToken extends Model
{
    protected $fillable = [
        'user_id',
        'personal_access_token_id',
        'device_id',
        'fcm_token',
        'platform',
        'app_version',
        'os_version',
        'device_model',
        'locale',
        'last_ip',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
