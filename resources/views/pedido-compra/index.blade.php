@extends('layouts.app')

@section('title', 'Pedidos de compra')
@section('page_title', 'Pedidos de compra')

@section('page_actions')
    <a href="{{ route('pedido-compra.create') }}" class="btn btn--primary btn--sm">+ Novo pedido</a>
@endsection

@section('content')
    <x-filtros :action="route('pedido-compra.index')" :manter="['situacao']">
        <div class="form-group filtros__principal form-group--span-2">
            <label class="form-label" for="campo-busca">Busca</label>
            <input id="campo-busca" type="search" name="busca" class="form-control"
                value="{{ $filtros['busca'] ?? '' }}" placeholder="Número ou fornecedor">
        </div>
        <div class="form-group">
            <label class="form-label" for="campo-data_inicio">Emissão (início)</label>
            <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control"
                value="{{ $filtros['data_inicio'] ?? '' }}">
        </div>
        <div class="form-group">
            <label class="form-label" for="campo-data_fim">Emissão (fim)</label>
            <input id="campo-data_fim" type="date" name="data_fim" class="form-control"
                value="{{ $filtros['data_fim'] ?? '' }}">
        </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$pedidos">
        <x-slot:abas>
            <x-filtro-abas :opcoes="[
                'rascunho' => 'Rascunhos',
                'aprovado' => 'Aprovados',
                'parcial' => 'Parciais',
                'recebido' => 'Recebidos',
                'cancelado' => 'Cancelados',
            ]" />
        </x-slot:abas>
        <thead>
            <tr>
                <th>Número</th>
                <th>Fornecedor</th>
                <th>Emissão</th>
                <th>Previsão</th>
                <th class="col-num">Total</th>
                <th>Situação</th>
                <th class="col-actions">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse($pedidos as $pedido)
                <tr>
                    <td data-label="Número" class="text-muted">{{ $pedido->numero }}</td>
                    <td data-label="Fornecedor">{{ $pedido->fornecedor->nome ?? '—' }}</td>
                    <td data-label="Emissão">{{ $pedido->data_emissao->format('d/m/Y') }}</td>
                    <td data-label="Previsão">{{ $pedido->data_prevista?->format('d/m/Y') ?? '—' }}</td>
                    <td data-label="Total" class="col-num">R$ {{ number_format($pedido->total, 2, ',', '.') }}</td>
                    <td data-label="Situação"><x-badge-situacao tipo="pedido-compra" :valor="$pedido->situacao" /></td>
                    <td class="col-actions" data-label="Ações">
                        <x-acoes>
                            <x-slot:editar>
                                @if($pedido->situacao === 'rascunho')
                                    <a href="{{ route('pedido-compra.edit', $pedido) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                        <x-icon name="edit" />
                                    </a>
                                @endif
                            </x-slot:editar>
                            <x-slot:ver>
                                <a href="{{ route('pedido-compra.show', $pedido) }}" class="btn btn--ghost btn--sm btn--icon js-ver"
                                    data-no-loader title="Ver" aria-label="Ver">
                                    <x-icon name="eye" />
                                </a>
                            </x-slot:ver>
                            <x-slot:excluir>
                                @if(in_array($pedido->situacao, ['rascunho', 'cancelado'], true))
                                    <form method="POST" action="{{ route('pedido-compra.destroy', $pedido) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Excluir" aria-label="Excluir"
                                            data-confirm="Excluir o pedido {{ $pedido->numero }}?" data-confirm-title="Excluir pedido"
                                            data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                @endif
                            </x-slot:excluir>
                        </x-acoes>
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="estoque" :colspan="7"
                    titulo="Nenhum pedido de compra encontrado"
                    texto="Registre pedidos para acompanhar aprovações e recebimentos parciais."
                    :acao="route('pedido-compra.create')" acao-texto="+ Novo pedido" filtravel />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection
