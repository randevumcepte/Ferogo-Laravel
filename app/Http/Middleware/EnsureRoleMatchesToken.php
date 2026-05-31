<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Token'ın hangi rol için verildiyse, kullanıcının User.type kolonu eşleşmiyorsa reddet.
 *
 * Senaryo: Birisi müşteri tokeniyle sürücü endpoint'lerini çağırıyorsa hesap karışıklığı/saldırı sayılır
 * — 403 döner. (Sanctum'un kendi tokenCan() yöntemiyle complementary.)
 *
 * Kullanım: ->middleware('role:driver') veya ->middleware('role:customer')
 */
class EnsureRoleMatchesToken
{
    public function handle(Request $request, Closure $next, string $expectedRole): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Unauthenticated.'], 401);
        }

        if ($user->type !== $expectedRole) {
            return response()->json([
                'ok' => false,
                'message' => 'Bu işlem için yetkin yok.',
                'code'    => 'role_mismatch',
            ], 403);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'ok' => false,
                'message' => 'Hesabın askıya alınmış. Destekle iletişime geç.',
                'code'    => 'account_inactive',
            ], 403);
        }

        return $next($request);
    }
}
