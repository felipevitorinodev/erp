@extends('layouts.app')

@section('title', 'Relatório — Vendas por Período')
@section('page_title', 'Vendas por Período')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('relatorio.vendas.csv', request()->query()) }}" class="btn btn--ghost btn--sm">Exportar CSV</a>
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimir</button>
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('relatorio.vendas') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Data início</label>
                    <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data fim</label>
                    <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('relatorio.vendas') }}" class="btn btn--ghost btn--sm">Limpar</a>
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
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Forma de Pagamento</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendas as $venda)
                            <tr>
                                <td data-label="Número">#{{ $venda->numero }}</td>
                                <td data-label="Cliente">{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td data-label="Data">{{ $venda->data_venda->format('d/m/Y') }}</td>
                                <td data-label="Forma de Pagamento">{{ $venda->forma_pagamento ?? '—' }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    @if($venda->situacao === 'confirmada')
                                        <span class="badge badge--success">Confirmada</span>
                                    @elseif($venda->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Em andamento</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma venda encontrada no período.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer" style="flex-wrap:wrap; gap:0.75rem;">
            <div style="flex:1; min-width:200px;">
                {{ $vendas->links() }}
            </div>
            <span class="text-bold">Total confirmadas: R$ {{ number_format($total_confirmadas, 2, ',', '.') }}</span>
        </div>
    </div>

@endsection
