<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = auth()->user()->empresa_id;
        $busca = $request->get('busca');

        $usuarios = User::where('empresa_id', $empresaId)
            ->when($busca, function ($q) use ($busca) {
                $q->where(function ($inner) use ($busca) {
                    $inner->where('name', 'like', "%{$busca}%")
                        ->orWhere('email', 'like', "%{$busca}%");
                });
            })
            ->orderBy('name')
            ->paginate(20);

        return view('usuario.index', compact('usuarios', 'busca'));
    }

    public function create()
    {
        return view('usuario.create');
    }

    public function store(UsuarioRequest $request)
    {
        User::create([
            'empresa_id' => auth()->user()->empresa_id,
            'name'       => $request->name,
            'email'      => $request->email,
            'password'   => $request->password,
            'perfil'     => $request->perfil,
            'ativo'      => $request->boolean('ativo', true),
        ]);

        return redirect()->route('usuario.index')
            ->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $usuario)
    {
        $this->garantirEmpresa($usuario);

        return view('usuario.edit', compact('usuario'));
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $this->garantirEmpresa($usuario);

        if ((int) $usuario->id === (int) auth()->id() && ! $request->boolean('ativo', true)) {
            return back()
                ->with('error', 'Você não pode inativar o próprio usuário.')
                ->withInput();
        }

        $dados = [
            'name'   => $request->name,
            'email'  => $request->email,
            'perfil' => $request->perfil,
            'ativo'  => $request->boolean('ativo', true),
        ];

        if ($request->filled('password')) {
            $dados['password'] = $request->password;
        }

        $usuario->update($dados);

        if (! $usuario->ativo) {
            DB::table('sessions')->where('user_id', $usuario->id)->delete();
        }

        return redirect()->route('usuario.index')
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    public function destroy(User $usuario)
    {
        $this->garantirEmpresa($usuario);

        if ((int) $usuario->id === (int) auth()->id()) {
            return redirect()->route('usuario.index')
                ->with('error', 'Você não pode excluir o próprio usuário.');
        }

        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        $usuario->delete();

        return redirect()->route('usuario.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }

    private function garantirEmpresa(User $usuario): void
    {
        if ((int) $usuario->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
