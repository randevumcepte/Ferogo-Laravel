<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // nginx arkasında çalışıyoruz; X-Forwarded-* header'larına güven (HTTPS algılaması için)
        $middleware->trustProxies(at: '*');

        // HTTP istekleri otomatik HTTPS'e yönlenir (WebRTC mikrofon için zorunlu)
        $middleware->web(prepend: [
            \App\Http\Middleware\ForceHttps::class,
        ]);

        // Mobile API katmanı — her isteğe güvenlik header'ları eklenir
        $middleware->api(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Named middleware aliases — route'ta ->middleware('role:driver') şeklinde kullanılır
        $middleware->alias([
            'role'    => \App\Http\Middleware\EnsureRoleMatchesToken::class,
            'device'  => \App\Http\Middleware\TouchDevice::class,
            'ability' => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
            'abilities' => \Laravel\Sanctum\Http\Middleware\CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON istekleri için exception'lar JSON döner (HTML hata sayfası değil)
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });
    })->create();
