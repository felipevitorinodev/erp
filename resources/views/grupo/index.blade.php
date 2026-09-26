@extends('layouts.app')

@section('title', 'Grupos')
@section('page_title', 'Grupos')

@section('page_actions')
    <a href="{{ route('grupo.create') }}" class="btn btn--primary btn--sm">+ Nova grupo</a>
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
            <span class="card__title">Listagem de grupos</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>grupo Pai</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr>
                                <td data-label="Nome">{{ $grupo->nome }}</td>
                                <td data-label="grupo Pai">{{ $grupo->parent->nome ?? '—' }}</td>
                                <td data-label="Situação">
                                    @if($grupo->ativo)
                                        <span class="badge badge--success">Ativa</span>
                                    @else
                                        <span class="badge badge--neutral">Inativa</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('grupo.edit', $grupo) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                        <x-icon name="edit" />
                                    </a>
                                    <form method="POST" action="{{ route('grupo.destroy', $grupo) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                            title="Excluir" aria-label="Excluir"
                                            data-confirm="Excluir este grupo?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="empty-state">
                                    Nenhuma grupo cadastrada.
                                </td>
                            </tr>
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