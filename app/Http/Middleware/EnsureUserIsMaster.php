<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsMaster
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isMaster(), 403, 'Acesso restrito a usuários master.');

        return $next($request);
    }
}
