<?php

namespace App\Modules\Security\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Security\Models\SecurityIncident;
use App\Modules\Security\Models\VerificationPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Faz 6 — Güvenlik olayı + zorunlu doğrulama fotoğrafları.
 *
 * Akış:
 *   1. Müşteri visual_verify=NO → RideRequestController::visualVerify create incident
 *   2. Sürücü panelinde "Acil kimlik doğrulama" forced photo capture açılır
 *   3. Sürücü 3 fotoğraf çeker: selfie, vehicle, plate
 *   4. POST /api/security-incidents/{publicId}/photo (tek tek)
 *   5. Çağrı merkezi operatörü Filament'te inceler (Faz 7)
 *   6. Approved → ride devam, Rejected → driver.is_suspended=true
 */
class SecurityIncidentController extends Controller
{
    /**
     * GET /api/security-incidents/{publicId} — durumu ve eksik foto'ları
     */
    public function show(string $publicId): JsonResponse
    {
        $incident = SecurityIncident::with(['verificationPhotos', 'driver.user'])
            ->where('public_id', $publicId)
            ->firstOrFail();

        $required = [
            VerificationPhoto::TYPE_SELFIE,
            VerificationPhoto::TYPE_VEHICLE,
            VerificationPhoto::TYPE_PLATE,
        ];
        $uploaded = $incident->verificationPhotos->pluck('type')->unique()->all();
        $missing  = array_values(array_diff($required, $uploaded));

        return response()->json([
            'success'      => true,
            'incident'     => [
                'public_id' => $incident->public_id,
                'type'      => $incident->type,
                'status'    => $incident->status,
                'severity'  => $incident->severity,
            ],
            'photos_uploaded' => $uploaded,
            'photos_missing'  => $missing,
            'all_uploaded'    => empty($missing),
        ]);
    }

    /**
     * POST /api/security-incidents/{publicId}/photo
     * Sürücü zorunlu doğrulama fotoğrafı yükler.
     *
     * multipart/form-data:
     *   - photo: file (image/*, max 8MB)
     *   - type: 'selfie' | 'vehicle' | 'plate'
     *   - flash_used: 0|1 (gece beyaz ekran flash kullanıldı mı)
     *   - front_camera: 0|1 (ön kamera mı)
     *   - captured_lat, captured_lng (nullable)
     */
    public function uploadPhoto(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'photo'         => ['required', 'file', 'image', 'max:8192'],
            'type'          => ['required', Rule::in([
                VerificationPhoto::TYPE_SELFIE,
                VerificationPhoto::TYPE_VEHICLE,
                VerificationPhoto::TYPE_PLATE,
            ])],
            'flash_used'    => ['nullable', 'boolean'],
            'front_camera'  => ['nullable', 'boolean'],
            'captured_lat'  => ['nullable', 'numeric', 'between:-90,90'],
            'captured_lng'  => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $incident = SecurityIncident::where('public_id', $publicId)->firstOrFail();
        if (! $incident->isOpen()) {
            return response()->json(['success' => false, 'message' => 'Bu olay kapatılmış.'], 422);
        }

        $file = $request->file('photo');
        $path = $file->store("verification-photos/{$incident->public_id}", 'public');

        $photo = VerificationPhoto::create([
            'security_incident_id' => $incident->id,
            'driver_id'            => $incident->driver_id,
            'ride_request_id'      => $incident->ride_request_id,
            'type'                 => $validated['type'],
            'disk'                 => 'public',
            'path'                 => $path,
            'mime_type'            => $file->getMimeType(),
            'size_bytes'           => $file->getSize(),
            'captured_lat'         => $validated['captured_lat'] ?? null,
            'captured_lng'         => $validated['captured_lng'] ?? null,
            'captured_at'          => now(),
            'flash_used'           => (bool) ($validated['flash_used'] ?? false),
            'front_camera'         => (bool) ($validated['front_camera'] ?? ($validated['type'] === 'selfie')),
            'status'               => VerificationPhoto::STATUS_PENDING_REVIEW,
        ]);

        // Tüm 3 foto yüklendi mi → olayı investigating'e al + chat mesajı
        $uploadedTypes = $incident->fresh()->verificationPhotos->pluck('type')->unique()->all();
        $allUploaded = count(array_intersect($uploadedTypes, [
            VerificationPhoto::TYPE_SELFIE,
            VerificationPhoto::TYPE_VEHICLE,
            VerificationPhoto::TYPE_PLATE,
        ])) === 3;

        if ($allUploaded && $incident->status === SecurityIncident::STATUS_OPEN) {
            $incident->update(['status' => SecurityIncident::STATUS_INVESTIGATING]);
            if ($incident->ride_request_id) {
                RideMessage::create([
                    'ride_request_id' => $incident->ride_request_id,
                    'sender'          => 'system',
                    'body'            => '✓ Sürücü 3 doğrulama fotoğrafını yükledi. Çağrı merkezi inceliyor.',
                ]);
            }
        }

        return response()->json([
            'success'  => true,
            'photo_id' => $photo->id,
            'type'     => $photo->type,
            'all_uploaded' => $allUploaded,
        ]);
    }
}
