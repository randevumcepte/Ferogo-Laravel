<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Services\CustomerTrustService;
use App\Modules\Booking\Services\PhoneVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneVerificationController extends Controller
{
    public function __construct(
        private PhoneVerificationService $service,
        private CustomerTrustService $trustService,
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

        // Bu telefonun zaten geçerli token'ı varsa → tekrar SMS gönderme, kullanıcıya bunu söyle
        if ($existing = $this->service->activeToken($validated['phone'])) {
            return response()->json([
                'ok'              => true,
                'already_verified' => true,
                'token'           => $existing,
                'message'         => 'Bu telefon zaten doğrulanmış.',
            ]);
        }

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
        }

        $status = $result['ok'] ? 200 : 422;
        return response()->json($result, $status);
    }
}
