@extends('layouts.app')

@section('title', 'Usuários')
@section('page_title', 'Usuários')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('usuario.create') }}" class="btn btn--primary btn--sm">+ Novo usuário</a>
    </div>
@endsection

@section('content')

    <x-filtros :action="route('usuario.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $busca ?? '' }}"
                        placeholder="Nome ou e-mail">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$usuarios">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Perfil</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($usuarios as $usuario)
                            <tr>
                                <td data-label="Nome" class="col-titulo">{{ $usuario->name }}</td>
                                <td data-label="E-mail">{{ $usuario->email }}</td>
                                <td data-label="Perfil">
                                    <span class="badge badge--info">{{ $usuario->nomeDoPerfil() }}</span>
                                </td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativo" :valor="(bool) ($usuario->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('usuario.edit', $usuario) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            @if((int) $usuario->id !== (int) auth()->id())
                                                <form method="POST" action="{{ route('usuario.destroy', $usuario) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                        title="Excluir" aria-label="Excluir"
                                                        data-confirm="Confirmar exclusão do usuário {{ $usuario->name }}?"
                                                        data-confirm-title="Excluir"
                                                        data-confirm-ok="Excluir"
                                                        data-confirm-variant="danger">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </form>
                                            @endif
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="cracha"
                                :colspan="5"
                                titulo="Nenhum usuário cadastrado"
                                texto="Cada pessoa que usa o sistema deve ter o próprio acesso."
                                :acao="route('usuario.create')"
                                acao-texto="+ Novo usuário"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
