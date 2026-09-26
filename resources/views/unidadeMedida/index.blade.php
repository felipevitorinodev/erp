@extends('layouts.app')

@section('title', 'Unidades de Medida')
@section('page_title', 'Unidades de Medida')

@section('page_actions')
    <a href="{{ route('unidadeMedida.create') }}" class="btn btn--primary btn--sm">+ Nova Unidade</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('unidadeMedida.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome ou sigla">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('unidadeMedida.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Unidades de Medida</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Sigla</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($unidadesMedida as $unidade)
                            <tr>
                                <td>{{ $unidade->nome }}</td>
                                <td>{{ $unidade->sigla }}</td>
                                <td>{{ $unidade->ativo ? 'Ativa' : 'Inativa' }}</td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('unidadeMedida.edit', $unidade) }}"
                                        class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('unidadeMedida.destroy', $unidade) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Excluir esta unidade?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma unidade cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $unidadesMedida->withQueryString()->links() }}
        </div>
    </div>

@endsection