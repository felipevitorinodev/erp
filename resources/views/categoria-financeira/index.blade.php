@extends('layouts.app')

@section('title', 'Categorias Financeiras')
@section('page_title', 'Categorias Financeiras')

@section('page_actions')
    <a href="{{ route('categoria-financeira.create') }}" class="btn btn--primary btn--sm">+ Nova Categoria</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('categoria-financeira.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $busca ?? '' }}"
                        placeholder="Nome da categoria">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('categoria-financeira.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Categorias Financeiras</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                            <tr>
                                <td>{{ $categoria->nome }}</td>
                                <td>
                                    @if($categoria->tipo === 'receita')
                                        <span class="badge badge--success">Receita</span>
                                    @else
                                        <span class="badge badge--warning">Despesa</span>
                                    @endif
                                </td>
                                <td>
                                    @if($categoria->ativo)
                                        <span class="badge badge--success">Ativa</span>
                                    @else
                                        <span class="badge badge--neutral">Inativa</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('categoria-financeira.edit', $categoria) }}"
                                        class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('categoria-financeira.destroy', $categoria) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma categoria financeira cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $categorias->withQueryString()->links() }}
        </div>
    </div>

@endsection
