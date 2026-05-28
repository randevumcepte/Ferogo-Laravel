<?php

namespace App\Modules\Driver\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Booking\Models\RideMessage;
use App\Modules\Booking\Models\RideRequest;
use App\Modules\Booking\Services\RideRequestService;
use App\Modules\Driver\Models\Driver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class DriverPanelController extends Controller
{
    public function __construct(private RideRequestService $service) {}

    // ────────────────────────────────────────────────────────────
    // AUTH
    // ────────────────────────────────────────────────────────────

    public function showLogin(): View|RedirectResponse
    {
        if ($this->currentDriver()) {
            return redirect()->route('driver.panel');
        }
        return view('driver.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])
            ->where('type', 'driver')
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'E-posta veya şifre hatalı.']);
        }

        if ($user->status !== 'active') {
            return back()->withErrors(['email' => 'Hesabın aktif değil.']);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->route('driver.panel');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('driver.login');
    }

    // ────────────────────────────────────────────────────────────
    // PANEL
    // ────────────────────────────────────────────────────────────

    public function panel(): View|RedirectResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return redirect()->route('driver.login');

        return view('driver.panel', [
            'driver' => $driver->loadMissing('user', 'currentVehicle.vehicleClass'),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // API (polled by panel JS)
    // ────────────────────────────────────────────────────────────

    /**
     * GET /surucu-paneli/api/state — panel her 2-3 sn'de buradan veriyi çeker.
     * Tek endpoint = az network gürültüsü.
     */
    public function state(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['authenticated' => false], 401);

        // Aktif yolculuk (kabul ettiğim, henüz tamamlanmamış)
        // Ride.status: completed olduysa aktif sayma
        $activeRequest = RideRequest::query()
            ->with(['acceptedDriver.user', 'ride'])
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->whereHas('ride', fn ($q) => $q->whereNotIn('status', ['completed', 'cancelled']))
            ->latest('accepted_at')
            ->first();

        // Yeni teklif sadece aktif yolculuk yokken gösterilir (busy iken atla)
        $offer = null;
        if (! $activeRequest && $driver->availability_status !== 'busy') {
            $offer = RideRequest::query()
                ->where('offered_driver_id', $driver->id)
                ->where('status', 'pending')
                ->where('offer_expires_at', '>', now())
                ->orderBy('created_at')
                ->first();
        }

        $lastMessageId = (int) request()->query('since_id', 0);
        $messages = [];
        if ($activeRequest) {
            $messages = $activeRequest->messages()
                ->where('id', '>', $lastMessageId)
                ->limit(100)
                ->get(['id', 'sender', 'body', 'created_at'])
                ->map(fn ($m) => [
                    'id'         => $m->id,
                    'sender'     => $m->sender,
                    'body'       => $m->body,
                    'created_at' => $m->created_at->toIso8601String(),
                ])->all();
        }

        return response()->json([
            'authenticated' => true,
            'driver' => [
                'id'                  => $driver->id,
                'name'                => $driver->user->name,
                'availability_status' => $driver->availability_status,
                'rating'              => (float) $driver->rating,
                'total_rides'         => (int) $driver->total_rides,
            ],
            'offer'  => $offer ? $this->offerPayload($offer) : null,
            'active' => $activeRequest ? $this->activeRequestPayload($activeRequest) : null,
            'messages' => $messages,
        ]);
    }

    /**
     * POST /surucu-paneli/api/availability — online/offline toggle.
     */
    public function setAvailability(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'status' => ['required', 'in:online,offline'],
        ]);

        // 'busy' zaten aktif yolculuktan otomatik kuruluyor — sürücü el ile değiştiremez
        if ($driver->availability_status !== 'busy') {
            $driver->update(['availability_status' => $validated['status']]);
        }

        return response()->json(['ok' => true, 'status' => $driver->fresh()->availability_status]);
    }

    /**
     * POST /surucu-paneli/api/offers/{publicId}/accept
     */
    public function acceptOffer(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();

        try {
            $this->service->accept($req, $driver);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * POST /surucu-paneli/api/offers/{publicId}/reject
     */
    public function rejectOffer(string $publicId): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::where('public_id', $publicId)->firstOrFail();
        $this->service->reject($req, $driver);

        return response()->json(['ok' => true]);
    }

    /**
     * POST /surucu-paneli/api/active/message
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $req = RideRequest::query()
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        $msg = RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'driver',
            'body'            => $validated['body'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => [
                'id'         => $msg->id,
                'sender'     => $msg->sender,
                'body'       => $msg->body,
                'created_at' => $msg->created_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * POST /surucu-paneli/api/active/complete — yolculuk tamamlandı.
     */
    public function completeRide(): JsonResponse
    {
        $driver = $this->currentDriver();
        if (! $driver) return response()->json(['ok' => false], 401);

        $req = RideRequest::query()
            ->with('ride')
            ->where('accepted_driver_id', $driver->id)
            ->where('status', 'accepted')
            ->latest('accepted_at')
            ->firstOrFail();

        if ($req->ride) {
            $req->ride->update([
                'status'       => 'completed',
                'completed_at' => now(),
            ]);
        }
        $driver->update(['availability_status' => 'online']);
        $driver->increment('total_rides');

        RideMessage::create([
            'ride_request_id' => $req->id,
            'sender'          => 'system',
            'body'            => 'Yolculuk tamamlandı. Geri bildirim için teşekkürler.',
        ]);

        return response()->json(['ok' => true]);
    }

    // ────────────────────────────────────────────────────────────
    // HELPERS
    // ────────────────────────────────────────────────────────────

    private function currentDriver(): ?Driver
    {
        $user = Auth::user();
        if (! $user || $user->type !== 'driver') return null;
        return Driver::where('user_id', $user->id)->first();
    }

    private function offerPayload(RideRequest $req): array
    {
        return [
            'public_id'         => $req->public_id,
            'customer_name'     => $req->customer_name,
            'pickup_address'    => $req->pickup_address,
            'dropoff_address'   => $req->dropoff_address,
            'distance_km'       => (float) $req->distance_km,
            'duration_minutes'  => (int) $req->duration_minutes,
            'estimated_fare'    => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'expires_at'        => $req->offer_expires_at?->toIso8601String(),
            'seconds_remaining' => max(0, (int) round(now()->diffInSeconds($req->offer_expires_at, false))),
        ];
    }

    private function activeRequestPayload(RideRequest $req): array
    {
        return [
            'public_id'         => $req->public_id,
            'customer_name'     => $req->customer_name,
            'customer_phone'    => $req->customer_phone, // sürücü görür (faz 5'te maskelenir)
            'pickup_address'    => $req->pickup_address,
            'pickup_lat'        => (float) $req->pickup_lat,
            'pickup_lng'        => (float) $req->pickup_lng,
            'dropoff_address'   => $req->dropoff_address,
            'distance_km'       => (float) $req->distance_km,
            'duration_minutes'  => (int) $req->duration_minutes,
            'estimated_fare'    => $req->estimated_fare ? (float) $req->estimated_fare : null,
            'accepted_at'       => $req->accepted_at?->toIso8601String(),
            'ride_status'       => $req->ride?->status,
        ];
    }
}
