@extends('layouts.app')

@section('title', 'Relatório — Produtos Mais Vendidos')
@section('page_title', 'Produtos Mais Vendidos')

@section('page_actions')
    <div class="page-actions">
        <x-export-dropdown
            :pdf="route('relatorio.produtos-mais-vendidos.pdf', request()->query())"
            :csv="route('relatorio.produtos-mais-vendidos.csv', request()->query())"
        />
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('relatorio.produtos-mais-vendidos') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Data início</label>
                    <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data fim</label>
                    <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Grupo</label>
                    <select name="grupo_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($grupos ?? [] as $grupo)
                            <option value="{{ $grupo->id }}"
                                {{ (string) ($filtros['grupo_id'] ?? '') === (string) $grupo->id ? 'selected' : '' }}>
                                {{ $grupo->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Ordenar por</label>
                    <select name="ordenar" class="form-control">
                        <option value="qtd" {{ ($filtros['ordenar'] ?? 'qtd') === 'qtd' ? 'selected' : '' }}>Quantidade</option>
                        <option value="valor" {{ ($filtros['ordenar'] ?? '') === 'valor' ? 'selected' : '' }}>Valor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}" placeholder="Nome ou código">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('relatorio.produtos-mais-vendidos') }}" class="btn btn--ghost btn--sm">Limpar</a>
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
                            <th>Posição</th>
                            <th>Produto</th>
                            <th>Código</th>
                            <th class="col-num">Qtd. Vendida</th>
                            <th class="col-num">Valor Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                            <tr>
                                <td data-label="Posição">{{ $produtos->firstItem() + $loop->index }}º</td>
                                <td data-label="Produto">{{ $produto->produto_nome }}</td>
                                <td data-label="Código">{{ $produto->produto_codigo ?: '—' }}</td>
                                <td data-label="Qtd. Vendida" class="col-num">{{ number_format($produto->qtd_total, 2, ',', '.') }}</td>
                                <td data-label="Valor Total" class="col-num">R$ {{ number_format($produto->valor_total, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum produto vendido no período.
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
