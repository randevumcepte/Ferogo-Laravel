<?php

namespace App\Modules\Legal\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Legal\Services\LegalConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

/**
 * Hukuki onay kaydı endpoint'i.
 *
 * Frontend (modal "Anladım", form checkbox vb.) bu endpoint'i çağırır.
 * Sunucu tarafında kalıcı audit log oluşur (legal_consents tablosu).
 */
class LegalConsentController extends Controller
{
    public function __construct(private LegalConsentService $service) {}

    /**
     * POST /api/legal-consent
     *
     * Body:
     *   {
     *     "consent_type": "platform_notice" | "terms" | "kvkk" | ... ,
     *     "accepted_via": "modal" | "checkbox" | "driver_registration" | ... ,
     *     "fingerprint": "<optional>",
     *     "phone": "<optional>",
     *     "items": [                       // bulk mode (opsiyonel)
     *       { "type": "terms" },
     *       { "type": "kvkk" }
     *     ]
     *   }
     */
    public function store(Request $request): JsonResponse
    {
        // Rate-limit: aynı IP'den dakikada max 30 (kötü niyetli flood'a karşı)
        $rateKey = 'legal-consent:' . $request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 30)) {
            return response()->json([
                'success' => false,
                'message' => 'Çok fazla istek. Lütfen biraz bekleyin.',
            ], 429);
        }
        RateLimiter::hit($rateKey, 60);

        $allowedTypes = [
            'platform_notice',
            'terms',
            'kvkk',
            'distance_sales',
            'cookies',
            'ride_sharing',
            'driver_registration',
            'reservation_kvkk',
        ];
        $allowedVia = [
            'modal',
            'checkbox',
            'driver_registration',
            'reservation',
            'sms_otp',
        ];

        $validated = $request->validate([
            'consent_type'  => ['nullable', 'string', Rule::in($allowedTypes)],
            'accepted_via'  => ['required', 'string', Rule::in($allowedVia)],
            'fingerprint'   => ['nullable', 'string', 'max:128'],
            'phone'         => ['nullable', 'string', 'max:32'],
            'items'         => ['nullable', 'array', 'max:10'],
            'items.*.type'  => ['required_with:items', 'string', Rule::in($allowedTypes)],
            'items.*.key'   => ['nullable', 'string', 'max:64'],
        ]);

        // Bulk veya tek
        if (! empty($validated['items'])) {
            $created = $this->service->recordMany(
                request:      $request,
                items:        $validated['items'],
                acceptedVia:  $validated['accepted_via'],
                extraPayload: ['source' => 'bulk'],
            );
            return response()->json([
                'success'  => true,
                'recorded' => count($created),
                'ids'      => collect($created)->pluck('id')->all(),
            ]);
        }

        if (empty($validated['consent_type'])) {
            return response()->json([
                'success' => false,
                'message' => 'consent_type veya items zorunludur.',
            ], 422);
        }

        $consent = $this->service->record(
            request:      $request,
            consentType:  $validated['consent_type'],
            acceptedVia:  $validated['accepted_via'],
            extraPayload: ['source' => 'single'],
        );

        if (! $consent) {
            return response()->json([
                'success' => false,
                'message' => 'Aktif metin versiyonu bulunamadı.',
            ], 422);
        }

        return response()->json([
            'success'  => true,
            'id'       => $consent->id,
            'version'  => $consent->version_snapshot,
            'sha256'   => $consent->sha256_snapshot,
        ]);
    }
}
