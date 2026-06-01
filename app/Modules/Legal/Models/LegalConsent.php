<?php

namespace App\Modules\Legal\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hukuki onay (consent) audit log kaydı.
 *
 * Kayıt değiştirilemez/silinemez — yasal saklama gereksinimi.
 * Her kayıt, kullanıcının hangi metni (text_version_id), hangi koşullarda
 * (ip/user_agent/url), hangi yöntemle (modal/checkbox/sms_otp) kabul ettiğini
 * mahkemede ispatlanabilir biçimde tutar.
 */
class LegalConsent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'session_id',
        'phone',
        'device_fingerprint',
        'text_version_id',
        'text_key_snapshot',
        'version_snapshot',
        'sha256_snapshot',
        'accepted_at',
        'accepted_via',
        'consent_type',
        'ip_address',
        'user_agent',
        'locale',
        'request_url',
        'referer',
        'raw_payload',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'raw_payload' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function textVersion(): BelongsTo
    {
        return $this->belongsTo(LegalTextVersion::class, 'text_version_id');
    }
}
