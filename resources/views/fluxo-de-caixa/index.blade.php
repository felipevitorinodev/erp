@extends('layouts.app')

@section('title', 'Fluxo de Caixa')
@section('page_title', 'Fluxo de Caixa')

@section('page_actions')
    <x-export-dropdown :csv="route('fluxo-de-caixa.csv', request()->query())" />
@endsection

@section('content')

    <x-filtros :action="route('fluxo-de-caixa.index')">
        <x-filtro-periodo :inicio="$filtros['data_inicio'] ?? ''" :fim="$filtros['data_fim'] ?? ''" />
    </x-filtros>

    <x-relatorio-kpis>
        <x-relatorio-kpi
            label="Total de entradas"
            :valor="'R$ ' . number_format($total_entradas, 2, ',', '.')"
            tom="success"
            icone="trending-up"
        />
        <x-relatorio-kpi
            label="Total de saídas"
            :valor="'R$ ' . number_format($total_saidas, 2, ',', '.')"
            tom="danger"
            icone="download"
        />
        <x-relatorio-kpi
            label="Saldo do período"
            :valor="'R$ ' . number_format($saldo_periodo, 2, ',', '.')"
            :tom="$saldo_periodo >= 0 ? 'success' : 'danger'"
            icone="dollar"
            destaque
        />
    </x-relatorio-kpis>

    <x-tabela-card titulo="Movimentação diária" :paginator="$dias">
        <thead>
            <tr>
                <th>Data</th>
                <th class="col-num">Entradas</th>
                <th class="col-num">Saídas</th>
                <th class="col-num">Saldo do Dia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($dias as $dia)
                <tr>
                    <td data-label="Data">{{ $dia['data']->format('d/m/Y') }}</td>
                    <td data-label="Entradas" class="col-num"><x-moeda :valor="$dia['entradas']" /></td>
                    <td data-label="Saídas" class="col-num"><x-moeda :valor="$dia['saidas']" /></td>
                    <td data-label="Saldo do Dia" @class(['col-num', 'text--danger' => $dia['saldo_dia'] < 0])>
                        <x-moeda :valor="$dia['saldo_dia']" />
                    </td>
                </tr>
            @empty
                <x-tabela-vazia :colspan="4">Nenhum movimento no período selecionado.</x-tabela-vazia>
            @endforelse
        </tbody>
    </x-tabela-card>

@endsection
