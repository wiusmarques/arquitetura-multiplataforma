<?php

use App\Http\Middleware\AutenticaJwt;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // `jwt:role` protege rotas por papel do realm (ex.: jwt:proponente).
        $middleware->alias(['jwt' => AutenticaJwt::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
