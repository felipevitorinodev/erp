<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermissao
{
    public function handle(Request $request, Closure $next, string ...$permissoes): Response
    {
        $usuario = auth()->user();

        foreach ($permissoes as $permissao) {
            if ($usuario && $usuario->pode($permissao)) {
                return $next($request);
            }
        }

        abort(403, 'Acesso não permitido.');
    }
}
