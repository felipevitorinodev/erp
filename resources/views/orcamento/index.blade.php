@extends('layouts.app')

@section('title', 'Orçamentos')
@section('page_title', 'Orçamentos')

@section('page_actions')
    <a href="{{ route('orcamento.create') }}" class="btn btn--primary btn--sm">+ Novo orçamento</a>
@endsection

@section('content')

    <x-filtros :action="route('orcamento.index')" :manter="['situacao']">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou cliente">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_inicio">Data (início)</label>
                    <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_fim">Data (fim)</label>
                    <input id="campo-data_fim" type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$orcamentos">
        <x-slot:abas>
            <x-filtro-abas :opcoes="['pendente' => 'Pendentes', 'aprovado' => 'Aprovados', 'recusado' => 'Recusados', 'cancelado' => 'Cancelados']" />
        </x-slot:abas>
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
                        @forelse($orcamentos as $orcamento)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $orcamento->numero }}</td>
                                <td data-label="Cliente">{{ $orcamento->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td data-label="Data">{{ $orcamento->data_orcamento->format('d/m/Y') }}</td>
                                <td data-label="Pagamento">{{ $orcamento->forma_pagamento ?? '—' }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="orcamento" :valor="$orcamento->situacao" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            @if($orcamento->situacao === 'pendente')
                                                <a href="{{ route('orcamento.edit', $orcamento) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                    <x-icon name="edit" />
                                                </a>
                                            @endif
                                        </x-slot:editar>
                                        <x-slot:ver>
                                            <a href="{{ route('orcamento.show', $orcamento) }}" class="btn btn--ghost btn--sm btn--icon js-ver" data-no-loader title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('orcamento.destroy', $orcamento) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="prancheta"
                                :colspan="7"
                                titulo="Nenhum orçamento cadastrado"
                                texto="Orçamento aprovado vira venda ou ordem de serviço sem redigitar os itens."
                                :acao="route('orcamento.create')"
                                acao-texto="+ Novo orçamento"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
