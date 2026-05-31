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

        // Auth fail durumunda /api/* HTML login'e değil JSON 401'e gider.
        // Authenticate middleware redirectTo() döner ve route('login') ararsa
        // "Route [login] not defined" patlar — bu callback null dönerse
        // middleware AuthenticationException atar, biz aşağıda JSON'a çeviririz.
        $middleware->redirectGuestsTo(function ($request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }
            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON istekleri için exception'lar JSON döner (HTML hata sayfası değil)
        $exceptions->shouldRenderJsonWhen(function ($request, $throwable) {
            return $request->is('api/*') || $request->expectsJson();
        });

        // AuthenticationException → 401 JSON
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Yetkisiz istek. Tekrar giriş yap.',
                    'code'    => 'unauthenticated',
                ], 401);
            }
        });

        // ValidationException zaten JSON döner default'ta, ama format'ı biz şekillendirelim
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // NotFoundHttpException (404) → JSON
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Kaynak bulunamadı.',
                    'code'    => 'not_found',
                ], 404);
            }
        });
    })->create();
