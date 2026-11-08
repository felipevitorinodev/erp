<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Http\Requests\Usuarios\UsuarioRequest;
use App\Models\Usuarios\User;
use App\Support\Auditoria;
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
        $usuario = User::create([
            'empresa_id' => auth()->user()->empresa_id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
            'perfil' => $request->perfil,
            'ativo' => $request->boolean('ativo', true),
        ]);

        Auditoria::registrar('usuario.criado', 'usuario', $usuario->id, [
            'perfil' => $usuario->perfil,
            'ativo' => (bool) $usuario->ativo,
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

        $deixaDeSerAdminAtivo = $request->perfil !== 'admin' || ! $request->boolean('ativo', true);

        if ($usuario->isAdmin() && $usuario->ativo && $deixaDeSerAdminAtivo && ! $this->existeOutroAdminAtivo($usuario)) {
            return back()
                ->with('error', 'É preciso manter ao menos um administrador ativo.')
                ->withInput();
        }

        $dados = [
            'name' => $request->name,
            'email' => $request->email,
            'perfil' => $request->perfil,
            'ativo' => $request->boolean('ativo', true),
        ];

        if ($request->filled('password')) {
            $dados['password'] = $request->password;
        }

        $antes = ['perfil' => $usuario->perfil, 'ativo' => (bool) $usuario->ativo];

        $usuario->update($dados);

        Auditoria::registrar('usuario.alterado', 'usuario', $usuario->id, [
            'antes' => $antes,
            'depois' => ['perfil' => $usuario->perfil, 'ativo' => (bool) $usuario->ativo],
            'senha_alterada' => $request->filled('password'),
        ]);

        $sessoes = DB::table('sessions')->where('user_id', $usuario->id);
        if (! $usuario->ativo) {
            $sessoes->delete();
        } elseif ($request->filled('password')) {
            $sessoes->where('id', '!=', $request->session()->getId())->delete();
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

        if ($usuario->isAdmin() && ! auth()->user()->isAdmin()) {
            return redirect()->route('usuario.index')
                ->with('error', 'Somente administradores podem excluir administradores.');
        }

        if ($usuario->isAdmin() && $usuario->ativo && ! $this->existeOutroAdminAtivo($usuario)) {
            return redirect()->route('usuario.index')
                ->with('error', 'É preciso manter ao menos um administrador ativo.');
        }

        DB::table('sessions')->where('user_id', $usuario->id)->delete();
        $usuario->delete();

        Auditoria::registrar('usuario.excluido', 'usuario', $usuario->id, [
            'perfil' => $usuario->perfil,
        ]);

        return redirect()->route('usuario.index')
            ->with('success', 'Usuário excluído com sucesso.');
    }

    private function existeOutroAdminAtivo(User $usuario): bool
    {
        return User::where('empresa_id', $usuario->empresa_id)
            ->where('perfil', 'admin')
            ->where('ativo', true)
            ->whereKeyNot($usuario->id)
            ->exists();
    }

    private function garantirEmpresa(User $usuario): void
    {
        if ((int) $usuario->empresa_id !== (int) auth()->user()->empresa_id) {
            abort(404);
        }
    }
}
