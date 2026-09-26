@extends('layouts.app')

@section('title', 'Relatório — Posição de Estoque')
@section('page_title', 'Posição de Estoque')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('relatorio.estoque.csv', request()->query()) }}" class="btn btn--ghost btn--sm">Exportar CSV</a>
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimir</button>
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('relatorio.estoque') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Grupo</label>
                    <select name="grupo_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($grupos as $grupo)
                            <option value="{{ $grupo->id }}" {{ (string) ($filtros['grupo_id'] ?? '') === (string) $grupo->id ? 'selected' : '' }}>
                                {{ $grupo->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Apenas crítico</label>
                    <label class="form-label">
                        <input type="checkbox" name="apenas_critico" value="1" {{ ($filtros['apenas_critico'] ?? false) ? 'checked' : '' }}>
                        Mostrar somente estoque crítico
                    </label>
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('relatorio.estoque') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Resultados</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Produto</th>
                            <th>Grupo</th>
                            <th>Unidade</th>
                            <th class="col-num">Estoque Mínimo</th>
                            <th class="col-num">Estoque Atual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                            @php
                                $critico = (float) $produto->estoque_atual <= (float) $produto->estoque_minimo;
                            @endphp
                            <tr class="{{ $critico ? 'text--danger' : '' }}">
                                <td data-label="Código">{{ $produto->codigo ?: '—' }}</td>
                                <td data-label="Produto">{{ $produto->nome }}</td>
                                <td data-label="Grupo">{{ $produto->grupos->nome ?? '—' }}</td>
                                <td data-label="Unidade">{{ $produto->unidadeMedida->sigla ?? ($produto->unidadeMedida->nome ?? '—') }}</td>
                                <td data-label="Estoque Mínimo" class="col-num">{{ number_format($produto->estoque_minimo, 2, ',', '.') }}</td>
                                <td data-label="Estoque Atual" class="col-num">{{ number_format($produto->estoque_atual, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum produto encontrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $produtos->links() }}
        </div>
    </div>

@endsection
