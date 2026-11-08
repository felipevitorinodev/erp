@extends('layouts.app')

@section('title', 'Vendas')
@section('page_title', 'Vendas')

@section('page_actions')
    <a href="{{ route('venda.create') }}" class="btn btn--primary btn--sm">+ Nova venda</a>
@endsection

@section('content')

    <x-filtros :action="route('venda.index')">
        <x-filtro-campo
            label="Busca"
            name="busca"
            :value="$filtros['busca'] ?? ''"
            placeholder="Número ou cliente"
            :span="2"
        />
        <x-filtro-campo
            label="Situação"
            name="situacao"
            type="select"
            :value="$filtros['situacao'] ?? ''"
            :options="auth()->user()->pode('vendas.ver_canceladas')
                ? ['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada']
                : ['em_andamento' => 'Em andamento', 'confirmada' => 'Confirmada']"
        />
        <x-filtro-periodo
            :inicio="$filtros['data_inicio'] ?? ''"
            :fim="$filtros['data_fim'] ?? ''"
            label-inicio="Data (início)"
            label-fim="Data (fim)"
        />
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$vendas">
                    <thead>
                        <tr>
                            <th style="width:80px;">Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Pagamento</th>
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
                                <td data-label="Pagamento">{{ $venda->forma_pagamento ?? '—' }}</td>
                                <td data-label="Total" class="col-num"><x-moeda :valor="$venda->total" /></td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="venda" :valor="$venda->situacao" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            @if($venda->situacao === 'em_andamento')
                                                <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                    <x-icon name="edit" />
                                                </a>
                                            @endif
                                        </x-slot:editar>
                                        <x-slot:ver>
                                            <a href="{{ route('venda.show', $venda) }}" class="btn btn--ghost btn--sm btn--icon js-ver" data-no-loader title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                        <x-slot:excluir>
                                            @if(auth()->user()->pode('vendas.cancelar') || $venda->situacao === 'em_andamento')
                                                <form method="POST" action="{{ route('venda.destroy', $venda) }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                        title="Excluir" aria-label="Excluir"
                                                        data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                        <x-icon name="trash" />
                                                    </button>
                                                </form>
                                            @endif
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="carrinho" :colspan="7" titulo="Nenhuma venda registrada" texto="As vendas do PDV e da tela de nova venda aparecem aqui." :acao="route('venda.create')" acao-texto="+ Nova venda" filtravel />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
