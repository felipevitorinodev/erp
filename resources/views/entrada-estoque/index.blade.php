@extends('layouts.app')

@section('title', 'Entradas de Estoque')
@section('page_title', 'Entradas de Estoque')

@section('page_actions')
    <a href="{{ route('entrada-estoque.create') }}" class="btn btn--primary btn--sm">+ Nova Entrada</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('entrada-estoque.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou fornecedor">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['rascunho' => 'Rascunho', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data (início)</label>
                    <input type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data (fim)</label>
                    <input type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
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
                            <th>Número</th>
                            <th>Fornecedor</th>
                            <th>Data</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entradas as $entrada)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $entrada->numero }}</td>
                                <td data-label="Fornecedor">{{ $entrada->fornecedor->nome ?? '—' }}</td>
                                <td data-label="Data">{{ $entrada->data_entrada->format('d/m/Y') }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($entrada->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    @if($entrada->situacao === 'confirmada')
                                        <span class="badge badge--success">Confirmada</span>
                                    @elseif($entrada->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Rascunho</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('entrada-estoque.show', $entrada) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($entrada->situacao === 'rascunho')
                                        <a href="{{ route('entrada-estoque.edit', $entrada) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma entrada de estoque cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $entradas->withQueryString()->links() }}
        </div>
    </div>

@endsection
