<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'master' => \App\Http\Middleware\EnsureUserIsMaster::class,
        ]);

        $middleware->redirectUsersTo(fn () => route('dashboard'));

        // Na hospedagem o nginx repassa as requisições ao Apache pela rede interna:
        // confia nos cabeçalhos X-Forwarded-* só quando vêm de endereços privados.
        $middleware->trustProxies(at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
