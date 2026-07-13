<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\CallSignal;
use App\Modules\Booking\Models\RideCall;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil WebRTC sesli görüşme — web CallController'ın Sanctum'lu karşılığı.
 * Sinyalleşme HTTP polling, ses akışı P2P. Rol (customer/driver) Sanctum
 * kullanıcısından çözülür; web istemcisiyle birebir uyumludur (aynı RideCall/
 * CallSignal tabloları, aynı offer/answer/ice protokolü).
 */
class CallController extends Controller
{
    private const RING_TIMEOUT_SECONDS = 35;
    private const SIGNAL_RETENTION_SECONDS = 60;

    public function start(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        if (! $req->isAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'Sesli görüşme yolculuk eşleşince aktif olur.',
            ], 422);
        }

        $existing = RideCall::where('ride_request_id', $req->id)
            ->whereIn('status', ['ringing', 'accepted'])
            ->latest('id')
            ->first();

        $call = $existing ?? RideCall::create([
            'ride_request_id' => $req->id,
            'initiator'       => $role,
            'status'          => 'ringing',
            'started_at'      => now(),
        ]);

        return response()->json([
            'success'     => true,
            'call'        => $this->callPayload($call),
            'role'        => $role,
            'ice_servers' => $this->iceServers(),
        ]);
    }

    public function accept(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        $call = $this->activeCall($req);
        if (! $call || $call->status !== 'ringing') {
            return response()->json(['success' => false, 'message' => 'Aktif çağrı yok.'], 422);
        }
        if ($call->initiator === $role) {
            return response()->json(['success' => false, 'message' => 'Kendi çağrını kabul edemezsin.'], 422);
        }

        $call->update(['status' => 'accepted', 'accepted_at' => now()]);

        return response()->json([
            'success'     => true,
            'call'        => $this->callPayload($call->refresh()),
            'role'        => $role,
            'ice_servers' => $this->iceServers(),
        ]);
    }

    public function end(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        $call = $this->activeCall($req);
        if (! $call) {
            return response()->json(['success' => true, 'call' => null]);
        }

        $newStatus = match ($call->status) {
            'ringing'  => $call->initiator === $role ? 'ended' : 'rejected',
            'accepted' => 'ended',
            default    => $call->status,
        };

        $duration = $call->accepted_at
            ? (int) max(0, now()->diffInSeconds($call->accepted_at))
            : null;

        $call->update([
            'status'           => $newStatus,
            'ended_at'         => now(),
            'duration_seconds' => $duration,
        ]);

        CallSignal::create([
            'ride_call_id' => $call->id,
            'from_role'    => $role,
            'type'         => 'bye',
            'payload'      => ['reason' => $newStatus],
            'created_at'   => now(),
        ]);

        return response()->json([
            'success' => true,
            'call'    => $this->callPayload($call->refresh()),
        ]);
    }

    public function state(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        $call = RideCall::where('ride_request_id', $req->id)->latest('id')->first();

        // Ring timeout — kimse cevaplamadıysa missed
        if ($call && $call->status === 'ringing' && $call->started_at
            && $call->started_at->diffInSeconds(now()) > self::RING_TIMEOUT_SECONDS) {
            $call->update(['status' => 'missed', 'ended_at' => now()]);
            $call->refresh();
        }

        return response()->json([
            'success'     => true,
            'role'        => $role,
            'call'        => $call ? $this->callPayload($call) : null,
            'ice_servers' => $this->iceServers(),
        ]);
    }

    public function pushSignal(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'in:offer,answer,ice'],
            'payload' => ['required', 'array'],
        ]);

        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        $call = $this->activeCall($req);
        if (! $call) {
            return response()->json(['success' => false, 'message' => 'Aktif çağrı yok.'], 422);
        }

        CallSignal::create([
            'ride_call_id' => $call->id,
            'from_role'    => $role,
            'type'         => $validated['type'],
            'payload'      => $validated['payload'],
            'created_at'   => now(),
        ]);

        return response()->json(['success' => true]);
    }

    public function pullSignals(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($request, $req);
        if ($role === null) return response()->json(['success' => false, 'message' => 'Yetkisiz.'], 403);

        $call = RideCall::where('ride_request_id', $req->id)->latest('id')->first();
        if (! $call) {
            return response()->json(['success' => true, 'signals' => []]);
        }

        $sinceId = (int) $request->query('since_id', 0);

        $signals = CallSignal::where('ride_call_id', $call->id)
            ->where('from_role', '!=', $role)
            ->where('id', '>', $sinceId)
            ->orderBy('id')
            ->limit(50)
            ->get(['id', 'from_role', 'type', 'payload', 'created_at']);

        CallSignal::where('ride_call_id', $call->id)
            ->where('created_at', '<', now()->subSeconds(self::SIGNAL_RETENTION_SECONDS))
            ->delete();

        return response()->json([
            'success' => true,
            'signals' => $signals->map(fn ($s) => [
                'id'         => $s->id,
                'from_role'  => $s->from_role,
                'type'       => $s->type,
                'payload'    => $s->payload,
                'created_at' => $s->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    // ────────────────────────────────────────────────────────────

    /**
     * Sanctum kullanıcısından rolü çöz + yolculuğa aidiyeti doğrula.
     * driver → accepted_driver_id eşleşmeli; customer → telefon eşleşmeli.
     * Aksi halde null (403).
     */
    private function resolveRole(Request $request, RideRequest $req): ?string
    {
        $user = $request->user();
        if (! $user) return null;

        if ($user->type === 'driver') {
            $driver = Driver::where('user_id', $user->id)->first();
            return ($driver && $driver->id === $req->accepted_driver_id) ? 'driver' : null;
        }

        // Müşteri: bu talebin sahibi mi? (telefon eşleşmesi)
        if ($user->phone !== null && $req->customer_phone !== null
            && $this->normalizePhone($user->phone) === $this->normalizePhone($req->customer_phone)) {
            return 'customer';
        }
        return null;
    }

    private function normalizePhone(string $p): string
    {
        return preg_replace('/\D+/', '', $p) ?? $p;
    }

    private function activeCall(RideRequest $req): ?RideCall
    {
        return RideCall::where('ride_request_id', $req->id)
            ->whereIn('status', ['ringing', 'accepted'])
            ->latest('id')
            ->first();
    }

    private function callPayload(RideCall $call): array
    {
        return [
            'id'                   => $call->id,
            'initiator'            => $call->initiator,
            'status'               => $call->status,
            'started_at'           => $call->started_at?->toIso8601String(),
            'accepted_at'          => $call->accepted_at?->toIso8601String(),
            'ended_at'             => $call->ended_at?->toIso8601String(),
            'duration_seconds'     => $call->duration_seconds,
            'ring_timeout_seconds' => self::RING_TIMEOUT_SECONDS,
        ];
    }

    /** ICE sunucu listesi (config/services.php webrtc). WebRTC istemci formatı. */
    private function iceServers(): array
    {
        $stun     = (array) config('services.webrtc.stun_urls', []);
        $turnUrls = (array) config('services.webrtc.turn_urls', []);
        $turnUser = config('services.webrtc.turn_username');
        $turnCred = config('services.webrtc.turn_credential');

        $list = [];
        foreach ($stun as $u) {
            if ($u) $list[] = ['urls' => $u];
        }
        if (! empty($turnUrls) && $turnUser && $turnCred) {
            foreach ($turnUrls as $u) {
                if ($u) $list[] = ['urls' => $u, 'username' => $turnUser, 'credential' => $turnCred];
            }
        }
        if (empty($list)) {
            $list[] = ['urls' => 'stun:stun.l.google.com:19302'];
        }
        return $list;
    }
}
