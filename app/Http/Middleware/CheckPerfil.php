<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPerfil
{
    public function handle(Request $request, Closure $next, string ...$perfis): Response
    {
        if (! in_array(auth()->user()->perfil ?? '', $perfis, true)) {
            abort(403, 'Acesso não permitido.');
        }

        return $next($request);
    }
}
