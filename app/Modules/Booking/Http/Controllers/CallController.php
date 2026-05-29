<?php

namespace App\Modules\Booking\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\CallSignal;
use App\Modules\Booking\Models\RideCall;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * WebRTC sesli görüşme — sinyalleşme HTTP polling ile, ses akışı P2P.
 * Müşteri public_id ile, sürücü Auth + driver kontrolü ile yetkilendirilir.
 */
class CallController extends Controller
{
    private const RING_TIMEOUT_SECONDS = 35;
    private const SIGNAL_RETENTION_SECONDS = 60;

    /**
     * POST /api/ride-requests/{publicId}/call/start
     * Çağrıyı başlatır. Aktif (ringing/accepted) çağrı varsa onu döner.
     */
    public function start(Request $request, string $publicId): JsonResponse
    {
        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        if (! $req->isAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'Sesli görüşme yolculuk başlayınca aktif olur.',
            ], 422);
        }

        $role = $this->resolveRole($req);

        $existing = RideCall::where('ride_request_id', $req->id)
            ->whereIn('status', ['ringing', 'accepted'])
            ->latest('id')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => true,
                'call'    => $this->callPayload($existing),
                'role'    => $role,
            ]);
        }

        $call = RideCall::create([
            'ride_request_id' => $req->id,
            'initiator'       => $role,
            'status'          => 'ringing',
            'started_at'      => now(),
        ]);

        return response()->json([
            'success' => true,
            'call'    => $this->callPayload($call),
            'role'    => $role,
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/call/accept
     * Karşı taraf kabul eder. Yalnız initiator olmayan taraf kabul edebilir.
     */
    public function accept(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($req);
        $call = $this->activeCall($req);

        if (! $call || $call->status !== 'ringing') {
            return response()->json(['success' => false, 'message' => 'Aktif çağrı yok.'], 422);
        }
        if ($call->initiator === $role) {
            return response()->json(['success' => false, 'message' => 'Kendi çağrını kabul edemezsin.'], 422);
        }

        $call->update(['status' => 'accepted', 'accepted_at' => now()]);

        return response()->json([
            'success' => true,
            'call'    => $this->callPayload($call->refresh()),
            'role'    => $role,
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/call/end
     * Çağrıyı sonlandırır (reject veya hangup).
     */
    public function end(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($req);
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

    /**
     * GET /api/ride-requests/{publicId}/call/state
     * Çağrı durumunu polling ile çek (her iki taraf).
     */
    public function state(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($req);

        $call = RideCall::where('ride_request_id', $req->id)
            ->latest('id')
            ->first();

        // Ring timeout — sürücü cevaplamadıysa missed
        if ($call && $call->status === 'ringing' && $call->started_at
            && $call->started_at->diffInSeconds(now()) > self::RING_TIMEOUT_SECONDS) {
            $call->update(['status' => 'missed', 'ended_at' => now()]);
            $call->refresh();
        }

        return response()->json([
            'success' => true,
            'role'    => $role,
            'call'    => $call ? $this->callPayload($call) : null,
        ]);
    }

    /**
     * POST /api/ride-requests/{publicId}/call/signal
     * WebRTC SDP/ICE mesajı kuyruğa yazar.
     */
    public function pushSignal(Request $request, string $publicId): JsonResponse
    {
        $validated = $request->validate([
            'type'    => ['required', 'in:offer,answer,ice'],
            'payload' => ['required', 'array'],
        ]);

        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($req);
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

    /**
     * GET /api/ride-requests/{publicId}/call/signals?since_id=N
     * Karşı tarafın yolladığı sinyalleri çeker (kendininkini değil).
     */
    public function pullSignals(Request $request, string $publicId): JsonResponse
    {
        $req  = RideRequest::where('public_id', $publicId)->firstOrFail();
        $role = $this->resolveRole($req);
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

        // Eski sinyalleri temizle (60sn'den eski + tüketilmiş)
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

    private function resolveRole(RideRequest $req): string
    {
        $user = Auth::user();
        if ($user && $user->type === 'driver') {
            $driver = Driver::where('user_id', $user->id)->first();
            if ($driver && $driver->id === $req->accepted_driver_id) {
                return 'driver';
            }
        }
        return 'customer';
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
            'id'               => $call->id,
            'initiator'        => $call->initiator,
            'status'           => $call->status,
            'started_at'       => $call->started_at?->toIso8601String(),
            'accepted_at'      => $call->accepted_at?->toIso8601String(),
            'ended_at'         => $call->ended_at?->toIso8601String(),
            'duration_seconds' => $call->duration_seconds,
            'ring_timeout_seconds' => self::RING_TIMEOUT_SECONDS,
        ];
    }
}
