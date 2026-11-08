@extends('layouts.app')

@section('title', 'Permissões')
@section('page_title', 'Permissões')

@section('page_actions')
    <a href="{{ route('permissao.create') }}" class="btn btn--primary btn--sm">+ Novo perfil</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Perfis de acesso</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Perfil</th>
                            <th>Usuários</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($perfis as $perfil)
                            @php
                                $total = (int) ($contagem[$perfil->slug] ?? 0);
                                if ($perfil->slug === 'vendedor') {
                                    $total += (int) ($contagem['operador'] ?? 0);
                                }
                            @endphp
                            <tr>
                                <td data-label="Perfil">
                                    {{ $perfil->nome }}
                                    @if($perfil->sistema)
                                        <span class="badge badge--neutral">Padrão</span>
                                    @endif
                                </td>
                                <td data-label="Usuários">{{ $total }}</td>
                                <td data-label="Situação">
                                    @if($perfil->ativo)
                                        <span class="badge badge--success">Ativo</span>
                                    @else
                                        <span class="badge badge--neutral">Inativo</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('permissao.edit', $perfil) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            @if(!$perfil->sistema)
                                                <form method="POST" action="{{ route('permissao.destroy', $perfil) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                        title="Excluir" aria-label="Excluir"
                                                        data-confirm="Excluir o perfil {{ $perfil->nome }}?"
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
                            <x-tabela-vazia ilustracao="escudo"
                                :colspan="4"
                                titulo="Nenhum perfil cadastrado"
                                texto="Perfis definem o que cada usuário pode acessar."
                                :acao="route('permissao.create')"
                                acao-texto="+ Novo perfil"
                            />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
