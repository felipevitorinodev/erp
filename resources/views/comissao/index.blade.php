@extends('layouts.app')

@section('title', 'Comissões')
@section('page_title', 'Comissões de vendedores e técnicos')

@section('content')

    <x-filtros :action="route('comissao.index')">
        <x-filtro-campo
            label="Vendedor"
            name="funcionario_id"
            type="select"
            :value="$filtros['funcionario_id'] ?? ''"
            :options="$funcionarios->pluck('nome', 'id')"
            vazio="— Todos —"
        />
        <x-filtro-campo
            label="Situação"
            name="situacao"
            type="select"
            :value="$filtros['situacao'] ?? ''"
            :options="['pendente' => 'Pendente', 'paga' => 'Paga']"
        />
        <x-filtro-periodo
            :inicio="$filtros['data_inicio'] ?? ''"
            :fim="$filtros['data_fim'] ?? ''"
            label-inicio="Data da venda (início)"
            label-fim="Data da venda (fim)"
        />
    </x-filtros>

    <x-relatorio-kpis>
        <x-relatorio-kpi
            label="Total pendente"
            :valor="'R$ ' . number_format($totais['pendentes'], 2, ',', '.')"
            tom="warning"
            icone="history"
        />
        <x-relatorio-kpi
            label="Total pago"
            :valor="'R$ ' . number_format($totais['pagas'], 2, ',', '.')"
            tom="success"
            icone="check"
            destaque
        />
    </x-relatorio-kpis>

    <x-tabela-card titulo="Resultados" :paginator="$comissoes">
                    <thead>
                        <tr>
                            <th style="width:60px;">Nº</th>
                            <th>Vendedor</th>
                            <th>Venda</th>
                            <th>Data venda</th>
                            <th class="col-num">Base</th>
                            <th class="col-num">%</th>
                            <th class="col-num">Valor</th>
                            <th>Situação</th>
                            <th>Pagamento</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comissoes as $comissao)
                            <tr>
                                <td data-label="Nº" class="text-muted">{{ $comissao->id }}</td>
                                <td data-label="Vendedor">{{ $comissao->funcionario->nome ?? '—' }}</td>
                                <td data-label="Venda">
                                    @if($comissao->venda)
                                        <a href="{{ route('venda.show', $comissao->venda) }}">#{{ $comissao->venda->numero }}</a>
                                        @if ($comissao->venda->ordemServico)
                                            <span class="text-muted" style="font-size:11px;">(OS #{{ $comissao->venda->ordemServico->numero }})</span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Data venda">{{ $comissao->venda?->data_venda?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="Base" class="col-num">{{ $comissao->base_calculo !== null ? 'R$ ' . number_format($comissao->base_calculo, 2, ',', '.') : '—' }}</td>
                                <td data-label="%" class="col-num">{{ number_format($comissao->percentual, 2, ',', '.') }}%</td>
                                <td data-label="Valor" class="col-num"><x-moeda :valor="$comissao->valor" /></td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="comissao" :valor="$comissao->situacao" />
                                </td>
                                <td data-label="Pagamento">{{ $comissao->data_pagamento ? $comissao->data_pagamento->format('d/m/Y') : '—' }}</td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:excluir>
                                            @if($comissao->situacao === 'pendente')
                                                <form method="POST" action="{{ route('comissao.pagar', $comissao) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn--primary btn--sm btn--icon"
                                                        title="Pagar" aria-label="Pagar"
                                                        data-confirm="Marcar esta comissão como paga?"
                                                        data-confirm-title="Pagar comissão"
                                                        data-confirm-ok="Confirmar">
                                                        <x-icon name="dollar" />
                                                    </button>
                                                </form>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="cofrinho" :colspan="10" titulo="Nenhuma comissão encontrada" texto="As comissões aparecem a partir das vendas e ordens de serviço com vendedor ou técnico." filtravel />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
