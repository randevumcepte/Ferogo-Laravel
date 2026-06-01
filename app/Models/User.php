<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'type',
        'phone',
        'tc_no',
        'birth_date',
        'gender',
        'avatar',
        'status',
        // iyzico saklı kart anahtarı — ilk paket ödemesinde set olur
        'iyzico_card_user_key',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->type === 'admin' && $this->status === 'active';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Tenant\Models\Tenant::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(\App\Modules\Driver\Models\Driver::class);
    }

    public function isAdmin(): bool
    {
        return $this->type === 'admin';
    }

    public function isDriver(): bool
    {
        return $this->type === 'driver';
    }

    public function isCustomer(): bool
    {
        return $this->type === 'customer';
    }
}
