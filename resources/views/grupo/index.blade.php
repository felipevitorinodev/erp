@extends('layouts.app')

@section('title', 'Grupos')
@section('page_title', 'Grupos')

@section('page_actions')
    <a href="{{ route('grupo.create') }}" class="btn btn--primary btn--sm">+ Novo grupo</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('grupo.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome do grupo">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('grupo.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Resultados</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Grupo pai</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr>
                                <td data-label="Nome">{{ $grupo->nome }}</td>
                                <td data-label="Grupo pai">{{ $grupo->parent->nome ?? '—' }}</td>
                                <td data-label="Situação">
                                    @if($grupo->ativo)
                                        <span class="badge badge--success">Ativo</span>
                                    @else
                                        <span class="badge badge--neutral">Inativo</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('grupo.edit', $grupo) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('grupo.destroy', $grupo) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este grupo?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="etiquetas"
                                :colspan="4"
                                titulo="Nenhum grupo cadastrado"
                                texto="Grupos organizam os produtos e facilitam buscas e relatórios."
                                :acao="route('grupo.create')"
                                acao-texto="+ Novo grupo"
                                filtravel
                            />
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $grupos->withQueryString()->links() }}
        </div>
    </div>

@endsection