<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\PhoneVerificationService;
use App\Modules\Legal\Services\LegalConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private PhoneVerificationService $service,
        private CustomerTrustService $trustService,
        private LegalConsentService $consents,
    ) {}

    /**
     * POST /api/phone/send-otp
     * Body: { phone, fingerprint? }
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'       => ['required', 'string', 'max:32'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
        ]);

        // Banlı/kara listede ise OTP bile gönderme (kaynak israfı)
        $check = $this->trustService->canRequestRide(
            $validated['phone'],
            $request->ip(),
            $validated['fingerprint'] ?? null,
        );
        if (! $check['ok']) {
            return response()->json([
                'ok'      => false,
                'message' => $check['reason'] ?? 'Şu anda yeni çağrı yapamazsın.',
            ], 429);
        }

        // Güvenlik: activeToken kontrolü kaldırıldı.
        // Aksi halde server'da token saklayan bir telefon için herkes (numarayı bilen)
        // SMS gönderilmeden o token'ı alabilirdi. Her "Kod gönder" isteği gerçek SMS yollar.

        $result = $this->service->sendOtp(
            $validated['phone'],
            $request->ip(),
            $validated['fingerprint'] ?? null,
        );

        $status = $result['ok'] ? 200 : 429;
        return response()->json($result, $status);
    }

    /**
     * POST /api/phone/verify-otp
     * Body: { phone, code, fingerprint?, name? }
     * Başarılı olursa: müşteri hesabı otomatik yaratılır + session'a login olur.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone'       => ['required', 'string', 'max:32'],
            'code'        => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
            'fingerprint' => ['nullable', 'string', 'max:64'],
            'name'        => ['nullable', 'string', 'max:120'],
        ]);

        $result = $this->service->verifyOtp(
            $validated['phone'],
            $validated['code'],
            $request->ip(),
            $validated['fingerprint'] ?? null,
            $validated['name'] ?? null,
        );

        if ($result['ok']) {
            $request->session()->regenerate();
            // Bu session'daki anonim consent log'larına telefonu backfill et
            // → ileride dava olursa "bu telefon bu metni okudu" zinciri kurulur
            try {
                $this->consents->identifyByPhone($request, $validated['phone']);
            } catch (\Throwable $e) {
                // Sessiz başarısızlık — OTP akışı engellenmesin
            }
        }

        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }
}
