@extends('layouts.app')

@section('title', 'Pedido ' . $pedido->numero)
@section('page_title', 'Pedido ' . $pedido->numero)

@section('breadcrumb')
    <a href="{{ route('pedido-compra.index') }}">Pedidos de compra</a> / {{ $pedido->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('pedido-compra.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>
        @if($pedido->situacao === 'rascunho')
            <a href="{{ route('pedido-compra.edit', $pedido) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>
            <form method="POST" action="{{ route('pedido-compra.aprovar', $pedido) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm"
                    data-confirm="Aprovar o pedido {{ $pedido->numero }}? A conta a pagar só será criada ao confirmar a entrada."
                    data-confirm-title="Aprovar pedido" data-confirm-ok="Aprovar">
                    <x-icon name="check" /> Aprovar
                </button>
            </form>
        @endif
        @if(in_array($pedido->situacao, ['aprovado', 'parcial'], true))
            <a href="{{ route('entrada-estoque.create', ['pedido_compra_id' => $pedido->id]) }}" class="btn btn--primary btn--sm">
                + Registrar recebimento
            </a>
            <form method="POST" action="{{ route('pedido-compra.cancelar', $pedido) }}">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm"
                    data-confirm="Cancelar o saldo pendente de {{ $pedido->numero }}? Recebimentos já confirmados serão mantidos."
                    data-confirm-title="Cancelar pedido" data-confirm-ok="Cancelar" data-confirm-variant="danger">
                    Cancelar saldo
                </button>
            </form>
        @endif
        @if(in_array($pedido->situacao, ['rascunho', 'cancelado'], true))
            <form method="POST" action="{{ route('pedido-compra.destroy', $pedido) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Excluir" aria-label="Excluir"
                    data-confirm="Excluir o pedido {{ $pedido->numero }}?" data-confirm-title="Excluir pedido"
                    data-confirm-ok="Excluir" data-confirm-variant="danger">
                    <x-icon name="trash" />
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')
    <x-ficha titulo="Dados gerais">
        <x-slot:status>
            <x-badge-situacao tipo="pedido-compra" :valor="$pedido->situacao" />
        </x-slot:status>
        <x-slot:resumo>
            <div><span>Total</span><strong><x-moeda :valor="$pedido->total" /></strong></div>
            <div><span>Subtotal</span><strong><x-moeda :valor="$pedido->subtotal" /></strong></div>
            <div><span>Desconto</span><strong><x-moeda :valor="$pedido->desconto" /></strong></div>
        </x-slot:resumo>
        <x-ficha-campo label="Número">{{ $pedido->numero }}</x-ficha-campo>
        <x-ficha-campo label="Fornecedor">{{ $pedido->fornecedor->nome ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Emissão">{{ $pedido->data_emissao->format('d/m/Y') }}</x-ficha-campo>
        <x-ficha-campo label="Previsão">{{ $pedido->data_prevista?->format('d/m/Y') ?? '—' }}</x-ficha-campo>
        @if($pedido->observacoes)
            <x-ficha-campo label="Observações" largo class="ficha__item--texto">{{ $pedido->observacoes }}</x-ficha-campo>
        @endif
    </x-ficha>

    <div class="card mt-2">
        <div class="card__header"><span class="card__title">Itens do pedido</span></div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="col-num">Pedido</th>
                            <th class="col-num">Recebido</th>
                            <th class="col-num">Pendente</th>
                            <th class="col-num">Preço unitário</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pedido->itens as $item)
                            @php $pendente = max(0, (float) $item->quantidade - (float) $item->quantidade_recebida); @endphp
                            <tr>
                                <td data-label="Produto">
                                    {{ $item->produto_nome }}
                                    @if($item->produto_codigo)<span class="text-muted">({{ $item->produto_codigo }})</span>@endif
                                </td>
                                <td data-label="Pedido" class="col-num">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                                <td data-label="Recebido" class="col-num">{{ number_format($item->quantidade_recebida, 3, ',', '.') }}</td>
                                <td data-label="Pendente" class="col-num">{{ number_format($pendente, 3, ',', '.') }}</td>
                                <td data-label="Preço unitário" class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($pedido->entradasEstoque->isNotEmpty())
        <div class="card mt-2">
            <div class="card__header"><span class="card__title">Entradas vinculadas</span></div>
            <div class="card__body card__body--flush">
                <div class="table-wrap">
                    <table class="table table--cards">
                        <thead><tr><th>Nota/entrada</th><th>Data</th><th>Situação</th><th class="col-actions">Ação</th></tr></thead>
                        <tbody>
                            @foreach($pedido->entradasEstoque as $entrada)
                                <tr>
                                    <td data-label="Nota/entrada">#{{ $entrada->numero }}</td>
                                    <td data-label="Data">{{ $entrada->data_entrada->format('d/m/Y') }}</td>
                                    <td data-label="Situação"><x-badge-situacao tipo="entrada" :valor="$entrada->situacao" /></td>
                                    <td class="col-actions" data-label="Ação">
                                        <a href="{{ route('entrada-estoque.show', $entrada) }}" class="btn btn--ghost btn--sm btn--icon" title="Ver entrada" aria-label="Ver entrada">
                                            <x-icon name="eye" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
@endsection
