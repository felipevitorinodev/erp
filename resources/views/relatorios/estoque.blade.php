@extends('layouts.app')

@section('title', 'Relatório — Posição de Estoque')
@section('page_title', 'Posição de Estoque')

@section('page_actions')
    <div class="page-actions">
        <x-export-dropdown
            :pdf="route('relatorio.estoque.pdf', request()->query())"
            :csv="route('relatorio.estoque.csv', request()->query())"
        />
    </div>
@endsection

@section('content')

    <x-filtros :action="route('relatorio.estoque')">
        <x-filtro-campo
            label="Grupo"
            name="grupo_id"
            type="select"
            :value="$filtros['grupo_id'] ?? ''"
            :options="$grupos->pluck('nome', 'id')"
            vazio="— Todos —"
        />
        <x-filtro-campo
            label="Estoque"
            name="estoque"
            type="select"
            :value="$filtros['estoque'] ?? ''"
            :options="['zerado' => 'Zerado', 'com_estoque' => 'Com estoque']"
            vazio="— Todos —"
        />
        <x-filtro-campo
            label="Busca"
            name="busca"
            :value="$filtros['busca'] ?? ''"
            placeholder="Nome ou código"
        />
        <x-filtro-campo
            type="checkbox"
            name="apenas_critico"
            label="Mostrar somente estoque crítico"
            :checked="(bool) ($filtros['apenas_critico'] ?? false)"
        />
    </x-filtros>

    <x-relatorio-kpis>
        <x-relatorio-kpi
            label="Produtos"
            :valor="number_format($totais['produtos'], 0, ',', '.')"
            detalhe="Com controle de estoque"
            icone="package"
        />
        <x-relatorio-kpi
            label="Valor em estoque"
            :valor="'R$ ' . number_format($totais['valor_custo'], 2, ',', '.')"
            detalhe="A preço de custo"
            tom="success"
            icone="dollar"
            destaque
        />
        <x-relatorio-kpi
            label="Estoque crítico"
            :valor="number_format($totais['criticos'], 0, ',', '.')"
            detalhe="Atual no mínimo ou abaixo"
            tom="warning"
            icone="alert-triangle"
        />
        <x-relatorio-kpi
            label="Sem estoque"
            :valor="number_format($totais['zerados'], 0, ',', '.')"
            detalhe="Saldo zerado ou negativo"
            tom="danger"
            icone="x-circle"
        />
    </x-relatorio-kpis>

    <x-tabela-card :paginator="$produtos">
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
                <tr @class(['text--danger' => $critico])>
                    <td data-label="Código">{{ $produto->codigo ?: '—' }}</td>
                    <td data-label="Produto">{{ $produto->nome }}</td>
                    <td data-label="Grupo">{{ $produto->grupos->nome ?? '—' }}</td>
                    <td data-label="Unidade">{{ $produto->unidadeMedida->sigla ?? ($produto->unidadeMedida->nome ?? '—') }}</td>
                    <td data-label="Estoque Mínimo" class="col-num">{{ number_format($produto->estoque_minimo, 2, ',', '.') }}</td>
                    <td data-label="Estoque Atual" class="col-num">{{ number_format($produto->estoque_atual, 2, ',', '.') }}</td>
                </tr>
            @empty
                <x-tabela-vazia :colspan="6">Nenhum produto encontrado.</x-tabela-vazia>
            @endforelse
        </tbody>
    </x-tabela-card>

@endsection
