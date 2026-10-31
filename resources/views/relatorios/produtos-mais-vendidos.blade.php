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

    <x-filtros :action="route('relatorio.produtos-mais-vendidos')">
        <x-filtro-periodo :inicio="$filtros['data_inicio']" :fim="$filtros['data_fim']" />
        <x-filtro-campo
            label="Grupo"
            name="grupo_id"
            type="select"
            :value="$filtros['grupo_id'] ?? ''"
            :options="($grupos ?? collect())->pluck('nome', 'id')"
            vazio="— Todos —"
        />
        <x-filtro-campo
            label="Ordenar por"
            name="ordenar"
            type="select"
            :value="$filtros['ordenar'] ?? 'qtd'"
            :options="['qtd' => 'Quantidade', 'valor' => 'Valor']"
            :vazio="false"
        />
        <x-filtro-campo
            label="Busca"
            name="busca"
            :value="$filtros['busca'] ?? ''"
            placeholder="Nome ou código"
        />
    </x-filtros>

    <x-relatorio-kpis>
        <x-relatorio-kpi
            label="Valor vendido"
            :valor="'R$ ' . number_format($totais['valor_total'], 2, ',', '.')"
            detalhe="Somente vendas confirmadas"
            tom="success"
            icone="trending-up"
            destaque
        />
        <x-relatorio-kpi
            label="Quantidade vendida"
            :valor="number_format($totais['qtd_total'], 2, ',', '.')"
            detalhe="Unidades somadas"
            icone="package"
        />
        <x-relatorio-kpi
            label="Produtos distintos"
            :valor="number_format($totais['produtos'], 0, ',', '.')"
            detalhe="Com venda no período"
            tom="accent"
            icone="layers"
        />
    </x-relatorio-kpis>

    <x-tabela-card :paginator="$produtos">
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
                    <td data-label="Valor Total" class="col-num"><x-moeda :valor="$produto->valor_total" /></td>
                </tr>
            @empty
                <x-tabela-vazia :colspan="5">Nenhum produto vendido no período.</x-tabela-vazia>
            @endforelse
        </tbody>
    </x-tabela-card>

@endsection
