<?php

namespace App\Modules\Mobile\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mobil bildirim kutusu (inbox).
 * Uygulamadaki "Bildirimler" ekranı buradan beslenir.
 * Tüm endpoint'ler auth:sanctum + device middleware ardında (her iki rol).
 */
class NotificationController extends Controller
{
    /** GET /api/v1/notifications?cursor=... — sayfalı liste (yeniden eskiye). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = UserNotification::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->limit(30)
            ->when($request->integer('before_id') > 0, fn ($q) => $q->where('id', '<', $request->integer('before_id')))
            ->get();

        return response()->json([
            'ok'           => true,
            'unread_count' => $this->unread($user->id),
            'items'        => $items->map(fn (UserNotification $n) => [
                'id'        => $n->id,
                'type'      => $n->type,
                'title'     => $n->title,
                'body'      => $n->body,
                'image_url' => $n->image_url,
                'deep_link' => $n->deep_link,
                'data'      => $n->data,
                'is_read'   => $n->read_at !== null,
                'created_at'=> $n->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** GET /api/v1/notifications/unread-count — rozet sayısı. */
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'ok'           => true,
            'unread_count' => $this->unread($request->user()->id),
        ]);
    }

    /** POST /api/v1/notifications/{id}/read — tek bildirimi okundu işaretle. */
    public function markRead(Request $request, int $id): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok'           => true,
            'unread_count' => $this->unread($request->user()->id),
        ]);
    }

    /** POST /api/v1/notifications/read-all — hepsini okundu işaretle. */
    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'unread_count' => 0]);
    }

    private function unread(int $userId): int
    {
        return (int) UserNotification::where('user_id', $userId)->whereNull('read_at')->count();
    }
}
