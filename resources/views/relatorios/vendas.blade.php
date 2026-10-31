@extends('layouts.app')

@section('title', 'Relatório — Vendas por Período')
@section('page_title', 'Vendas por Período')

@section('page_actions')
    <div class="page-actions">
        <x-export-dropdown
            :pdf="route('relatorio.vendas.pdf', request()->query())"
            :csv="route('relatorio.vendas.csv', request()->query())"
        />
    </div>
@endsection

@section('content')

    <x-filtros :action="route('relatorio.vendas')">
        <x-filtro-periodo :inicio="$filtros['data_inicio']" :fim="$filtros['data_fim']" />
        <x-filtro-campo
            label="Situação"
            name="situacao"
            type="select"
            :value="$filtros['situacao'] ?? ''"
            :options="['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada']"
        />
        <x-filtro-campo
            label="Forma de pagamento"
            name="forma_pagamento"
            :value="$filtros['forma_pagamento'] ?? ''"
            placeholder="Forma de pagamento"
        />
        <x-filtro-campo
            label="Busca"
            name="busca"
            :value="$filtros['busca'] ?? ''"
            placeholder="Número ou cliente"
        />
    </x-filtros>

    <x-relatorio-kpis>
        <x-relatorio-kpi
            label="Faturamento"
            :valor="'R$ ' . number_format($totais['valor_confirmadas'], 2, ',', '.')"
            :detalhe="$totais['qtd_confirmadas'] . ($totais['qtd_confirmadas'] === 1 ? ' venda confirmada' : ' vendas confirmadas')"
            tom="success"
            icone="trending-up"
            destaque
        />
        <x-relatorio-kpi
            label="Ticket médio"
            :valor="'R$ ' . number_format($totais['ticket_medio'], 2, ',', '.')"
            detalhe="Por venda confirmada"
            icone="bar-chart"
        />
        <x-relatorio-kpi
            label="Em andamento"
            :valor="'R$ ' . number_format($totais['valor_em_andamento'], 2, ',', '.')"
            :detalhe="$totais['qtd_em_andamento'] . ($totais['qtd_em_andamento'] === 1 ? ' venda' : ' vendas')"
            tom="warning"
            icone="history"
        />
        <x-relatorio-kpi
            label="Canceladas"
            :valor="'R$ ' . number_format($totais['valor_canceladas'], 2, ',', '.')"
            :detalhe="$totais['qtd_canceladas'] . ($totais['qtd_canceladas'] === 1 ? ' venda' : ' vendas')"
            tom="danger"
            icone="x-circle"
        />
    </x-relatorio-kpis>

    <x-tabela-card :paginator="$vendas">
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
                    <td data-label="Total" class="col-num"><x-moeda :valor="$venda->total" /></td>
                    <td data-label="Situação">
                        <x-badge-situacao tipo="venda" :valor="$venda->situacao" />
                    </td>
                </tr>
            @empty
                <x-tabela-vazia :colspan="6">Nenhuma venda encontrada no período.</x-tabela-vazia>
            @endforelse
        </tbody>
    </x-tabela-card>

@endsection
