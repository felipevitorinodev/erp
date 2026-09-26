@extends('layouts.app')

@section('title', 'Relatório — Contas a Pagar')
@section('page_title', 'Contas a Pagar')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('relatorio.contas-pagar.csv', request()->query()) }}" class="btn btn--ghost btn--sm">Exportar CSV</a>
        <button type="button" class="btn btn--ghost btn--sm" onclick="window.print()">Imprimir</button>
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('relatorio.contas-pagar') }}" class="form-grid form-grid--col-3">
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
                        @foreach(['aberta' => 'Aberta', 'parcial' => 'Parcial', 'paga' => 'Paga', 'cancelada' => 'Cancelada'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach($categorias ?? [] as $categoria)
                            <option value="{{ $categoria->id }}"
                                {{ (string) ($filtros['categoria_id'] ?? '') === (string) $categoria->id ? 'selected' : '' }}>
                                {{ $categoria->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('relatorio.contas-pagar') }}" class="btn btn--ghost btn--sm">Limpar</a>
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
                            <th>Nº</th>
                            <th>Descrição</th>
                            <th>Fornecedor</th>
                            <th>Categoria</th>
                            <th>Vencimento</th>
                            <th class="col-num">Valor</th>
                            <th class="col-num">Pago</th>
                            <th>Situação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contas as $conta)
                            @php
                                $vencido = $conta->data_vencimento->lt(now()->startOfDay())
                                    && !in_array($conta->situacao, ['paga', 'cancelada'], true);
                            @endphp
                            <tr>
                                <td data-label="Nº" class="text-muted">{{ $conta->id }}</td>
                                <td data-label="Descrição">{{ $conta->descricao }}</td>
                                <td data-label="Fornecedor">{{ $conta->fornecedor->nome ?? '—' }}</td>
                                <td data-label="Categoria">{{ $conta->categoria->nome ?? '—' }}</td>
                                <td data-label="Vencimento" class="{{ $vencido ? 'text--danger' : '' }}">
                                    {{ $conta->data_vencimento->format('d/m/Y') }}
                                </td>
                                <td data-label="Valor" class="col-num">R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                                <td data-label="Pago" class="col-num">R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    @if($conta->situacao === 'paga')
                                        <span class="badge badge--success">Paga</span>
                                    @elseif($conta->situacao === 'parcial')
                                        <span class="badge badge--info">Parcial</span>
                                    @elseif($conta->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Aberta</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma conta a pagar no período.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer" style="flex-wrap:wrap; gap:0.75rem;">
            <div style="flex:1; min-width:200px;">
                {{ $contas->links() }}
            </div>
            <span>Soma valor: <strong>R$ {{ number_format($soma_valor, 2, ',', '.') }}</strong></span>
            <span>Soma pago: <strong>R$ {{ number_format($soma_pago, 2, ',', '.') }}</strong></span>
        </div>
    </div>

    @if(isset($subtotais_categoria) && $subtotais_categoria->isNotEmpty())
        <div class="card mt-2">
            <div class="card__header">
                <span class="card__title">Subtotais por categoria</span>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table table--cards">
                        <thead>
                            <tr>
                                <th>Categoria</th>
                                <th class="col-num">Soma valor</th>
                                <th class="col-num">Soma pago</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subtotais_categoria as $subtotal)
                                <tr>
                                    <td>{{ $subtotal['nome'] }}</td>
                                    <td class="col-num">R$ {{ number_format($subtotal['soma_valor'], 2, ',', '.') }}</td>
                                    <td class="col-num">R$ {{ number_format($subtotal['soma_pago'], 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

@endsection
