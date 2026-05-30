<?php

namespace App\Modules\Driver\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DriverChangeRequest extends Model
{
    protected $fillable = [
        'driver_id',
        'type',
        'payload',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by',
    ];

    protected $casts = [
        'payload'     => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
