<?php

namespace App\Services\Relatorios;

use App\Models\Usuarios\User;
use App\Support\CatalogoRelatorios;
use Illuminate\Contracts\View\View;

class HubService
{
    public function index(): View
    {
        $usuario = auth()->user();

        if (! $usuario instanceof User) {
            abort(403, 'Acesso não permitido.');
        }

        $categorias = CatalogoRelatorios::agrupados($usuario);

        if ($categorias === []) {
            abort(403, 'Acesso não permitido.');
        }

        return view('relatorios.hub', [
            'categorias' => $categorias,
        ]);
    }
}
