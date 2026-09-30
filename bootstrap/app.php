<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'throttle:auth' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':auth',
            'throttle:api' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
            'throttle:sensitive' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':sensitive',
        ]);

        // Les routes API sont stateless (jeton Bearer) : pas de cookie de
        // session à protéger, le CSRF n'a donc pas lieu d'être. En revanche
        // elles doivent toutes être soumises à un quota, sinon login comme
        // endpoints métier sont endlessly appelables (force brute, énumération).
        $middleware->api(prepend: [
            \Illuminate\Routing\Middleware\ThrottleRequests::class.':api',
        ]);

        // Render publie l'API derrière un proxy : sans cela, $request->ip() vaut
        // l'IP du proxy pour tout le monde, ce qui rendrait le rate limiting
        // global (une seule IP) et la journalisation des IP inutilisables.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request, \Throwable $e) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (\App\Exceptions\BusinessException $e, Request $request) {
            return response()->json(['message' => $e->getMessage()], 422);
        });
    })->create();
