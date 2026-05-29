<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTP istekleri otomatik HTTPS'e yönlendirir.
 * WebRTC (mikrofon erişimi) HTTPS zorunlu — kullanıcıyı manuel uğraştırmamak için.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri(), 301);
        }
        return $next($request);
    }
}
