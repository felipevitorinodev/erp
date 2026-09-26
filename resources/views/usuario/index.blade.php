@extends('layouts.app')

@section('title', 'Usuários')
@section('page_title', 'Usuários')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('usuario.create') }}" class="btn btn--primary btn--sm">+ Novo Usuário</a>
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('usuario.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $busca ?? '' }}"
                        placeholder="Nome ou e-mail">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('usuario.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Situação</th>
                            <th class="text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usuarios as $usuario)
                            <tr>
                                <td data-label="Nome">{{ $usuario->name }}</td>
                                <td data-label="E-mail">{{ $usuario->email }}</td>
                                <td data-label="Perfil">
                                    @if($usuario->perfil === 'admin')
                                        <span class="badge badge--info">Admin</span>
                                    @elseif($usuario->perfil === 'financeiro')
                                        <span class="badge badge--warning">Financeiro</span>
                                    @else
                                        <span class="badge badge--success">Operador</span>
                                    @endif
                                </td>
                                <td data-label="Situação">
                                    @if($usuario->ativo)
                                        <span class="badge badge--success">Ativo</span>
                                    @else
                                        <span class="badge badge--error">Inativo</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('usuario.edit', $usuario) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @if((int) $usuario->id !== (int) auth()->id())
                                        <form method="POST" action="{{ route('usuario.destroy', $usuario) }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn--danger btn--sm"
                                                data-confirm="Confirmar exclusão do usuário {{ $usuario->name }}?"
                                                data-confirm-title="Excluir"
                                                data-confirm-ok="Excluir"
                                                data-confirm-variant="danger">
                                                Excluir
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum usuário cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $usuarios->withQueryString()->links() }}
        </div>
    </div>

@endsection
