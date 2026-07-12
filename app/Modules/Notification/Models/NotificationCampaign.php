<?php

namespace App\Modules\Notification\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Admin panelden yazılıp gönderilen toplu bildirim kampanyası.
 *
 * Akış: draft → (Gönder / Zamanla) → scheduled → sending → sent.
 * Zamanlı kampanyaları `notifications:dispatch` komutu her dakika tarayıp gönderir.
 */
class NotificationCampaign extends Model
{
    public const TYPES = [
        'announcement' => 'Duyuru',
        'promo'        => 'Kampanya / İndirim',
        'info'         => 'Bilgilendirme',
    ];

    public const AUDIENCES = [
        'all'       => 'Herkes (tüm kullanıcılar)',
        'customers' => 'Sadece müşteriler',
        'drivers'   => 'Sadece sürücüler',
    ];

    public const STATUSES = [
        'draft'     => 'Taslak',
        'scheduled' => 'Zamanlandı',
        'sending'   => 'Gönderiliyor',
        'sent'      => 'Gönderildi',
        'cancelled' => 'İptal',
    ];

    protected $fillable = [
        'public_id',
        'title',
        'body',
        'image_url',
        'deep_link',
        'type',
        'show_as_popup',
        'audience',
        'target',
        'status',
        'scheduled_at',
        'sent_at',
        'recipients_count',
        'sent_count',
        'failed_count',
        'opened_count',
        'created_by',
    ];

    protected $casts = [
        'show_as_popup'    => 'boolean',
        'target'           => 'array',
        'scheduled_at'     => 'datetime',
        'sent_at'          => 'datetime',
        'recipients_count' => 'integer',
        'sent_count'       => 'integer',
        'failed_count'     => 'integer',
        'opened_count'     => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->public_id)) {
                $c->public_id = (string) Str::ulid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Gönderilmeye hazır (zamanı gelmiş) zamanlı kampanyalar. */
    public function scopeDue(Builder $query): Builder
    {
        return $query
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now());
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
