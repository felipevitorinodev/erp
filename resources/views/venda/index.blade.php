@extends('layouts.app')

@section('title', 'Vendas')
@section('page_title', 'Vendas')

@section('page_actions')
    <a href="{{ route('venda.create') }}" class="btn btn--primary btn--sm">+ Nova Venda</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('venda.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou cliente">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'] as $valor => $label)
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
                        <a href="{{ route('venda.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Vendas</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:80px;">Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Forma Pgto.</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendas as $venda)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $venda->numero }}</td>
                                <td data-label="Cliente">{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td data-label="Data">{{ $venda->data_venda->format('d/m/Y') }}</td>
                                <td data-label="Forma Pgto.">{{ $venda->forma_pagamento ?? '—' }}</td>
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
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('venda.show', $venda) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($venda->situacao !== 'cancelada')
                                        <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                    <form method="POST" action="{{ route('venda.destroy', $venda) }}" class="d-inline">
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
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma venda cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $vendas->withQueryString()->links() }}
        </div>
    </div>

@endsection
