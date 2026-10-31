@extends(request()->boolean('modal') ? 'layouts.ver-modal' : 'layouts.app')

@section('title', 'Orçamento #' . $orcamento->numero)
@section('page_title', 'Orçamento #' . $orcamento->numero)

@section('breadcrumb')
    <a href="{{ route('orcamento.index') }}">Orçamentos</a> / #{{ $orcamento->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('orcamento.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        <x-export-dropdown :pdf="route('orcamento.pdf', $orcamento)" />

        @if ($orcamento->situacao === 'pendente')
            <form method="POST" action="{{ route('orcamento.aprovar', $orcamento) }}">
                @csrf
                <button type="submit" class="btn btn--success btn--sm"
                    data-confirm="Aprovar o orçamento #{{ $orcamento->numero }} e gerar uma venda?"
                    data-confirm-title="Aprovar orçamento"
                    data-confirm-ok="Aprovar e gerar venda"
                    data-confirm-variant="success">
                    <x-icon name="check" />
                    Gerar venda
                </button>
            </form>

            @if (auth()->user()->pode('ordens_servico'))
                <form method="POST" action="{{ route('orcamento.gerar-os', $orcamento) }}">
                    @csrf
                    <button type="submit" class="btn btn--outline btn--sm"
                        data-confirm="Converter o orçamento #{{ $orcamento->numero }} em ordem de serviço? Os itens e totais serão copiados e a venda sai depois, ao faturar a OS."
                        data-confirm-title="Gerar ordem de serviço"
                        data-confirm-ok="Gerar OS"
                        data-confirm-variant="success">
                        Gerar OS
                    </button>
                </form>
            @endif

            <a href="{{ route('orcamento.edit', $orcamento) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>

            <form method="POST" action="{{ route('orcamento.recusar', $orcamento) }}">
                @csrf
                <button type="submit" class="btn btn--warning btn--sm btn--icon" title="Recusar" aria-label="Recusar"
                    data-confirm="Recusar o orçamento #{{ $orcamento->numero }}?"
                    data-confirm-title="Recusar orçamento"
                    data-confirm-ok="Recusar"
                    data-confirm-variant="warning">
                    <x-icon name="ban" />
                </button>
            </form>

            <form method="POST" action="{{ route('orcamento.cancelar', $orcamento) }}">
                @csrf
                <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Cancelar" aria-label="Cancelar"
                    data-confirm="Cancelar o orçamento #{{ $orcamento->numero }}?"
                    data-confirm-title="Cancelar orçamento"
                    data-confirm-ok="Cancelar orçamento"
                    data-confirm-variant="danger">
                    <x-icon name="x-circle" />
                </button>
            </form>
        @endif

        @if (in_array($orcamento->situacao, ['pendente', 'cancelado', 'recusado'], true))
            <form method="POST" action="{{ route('orcamento.destroy', $orcamento) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                    title="Excluir" aria-label="Excluir"
                    data-confirm="Confirmar exclusão?"
                    data-confirm-title="Excluir"
                    data-confirm-ok="Excluir"
                    data-confirm-variant="danger">
                    <x-icon name="trash" />
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')

    <x-ficha titulo="Dados Gerais">
        <x-slot:status>
            <x-badge-situacao tipo="orcamento" :valor="$orcamento->situacao" />
        </x-slot:status>
        <x-slot:resumo>
            <div>
                <span>Total</span>
                <strong><x-moeda :valor="$orcamento->total" /></strong>
            </div>
            <div>
                <span>Subtotal</span>
                <strong><x-moeda :valor="$orcamento->subtotal" /></strong>
            </div>
            <div>
                <span>Desconto</span>
                <strong><x-moeda :valor="$orcamento->desconto" /></strong>
            </div>
            <div>
                <span>Acréscimo</span>
                <strong><x-moeda :valor="$orcamento->acrescimo" /></strong>
            </div>
        </x-slot:resumo>
        <x-ficha-campo label="Número">#{{ $orcamento->numero }}</x-ficha-campo>
        <x-ficha-campo label="Data do orçamento">{{ $orcamento->data_orcamento->format('d/m/Y') }}</x-ficha-campo>
        <x-ficha-campo label="Previsão de entrega">{{ $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—' }}</x-ficha-campo>
        <x-ficha-campo label="Forma de pagamento">{{ $orcamento->forma_pagamento ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Cliente">{{ $orcamento->cliente->nome ?? '— Consumidor Final —' }}</x-ficha-campo>
        <x-ficha-campo label="Vendedor">{{ $orcamento->usuario->name ?? '—' }}</x-ficha-campo>
        @if ($orcamento->venda_id && $orcamento->venda)
            <x-ficha-campo label="Venda gerada">
                <a href="{{ route('venda.show', $orcamento->venda) }}">#{{ $orcamento->venda->numero }}</a>
            </x-ficha-campo>
        @endif
        @if ($orcamento->ordem_servico_id && $orcamento->ordemServico)
            <x-ficha-campo label="Ordem de serviço gerada">
                <a href="{{ route('ordem-servico.show', $orcamento->ordemServico) }}">OS #{{ $orcamento->ordemServico->numero }}</a>
            </x-ficha-campo>
        @endif
        @if ($orcamento->observacoes)
            <x-ficha-campo label="Observações" largo class="ficha__item--texto">{{ $orcamento->observacoes }}</x-ficha-campo>
        @endif
    </x-ficha>

    <div class="card" style="margin-top:1rem;">
        <div class="card__header">
            <span class="card__title">Itens</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th class="col-num">Qtd.</th>
                            <th class="col-num">Preço Unit.</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orcamento->itens as $item)
                            <tr>
                                <td data-label="Produto">
                                    {{ $item->produto_nome }}
                                    @if ($item->produto_codigo)
                                        <span class="text-muted"
                                            style="font-size:11px;">({{ $item->produto_codigo }})</span>
                                    @endif
                                </td>
                                <td data-label="Qtd." class="col-num">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                <td data-label="Preço unit." class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td data-label="Desconto" class="col-num">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                                <td data-label="Total" class="col-num text-bold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:1.5rem;">
                                    Nenhum item neste orçamento.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card__footer">
            <div class="totals-box" style="font-size:12.5px;">
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Subtotal</span>
                    <span>R$ {{ number_format($orcamento->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Desconto</span>
                    <span>- R$ {{ number_format($orcamento->desconto, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Acréscimo</span>
                    <span>+ R$ {{ number_format($orcamento->acrescimo, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row totals-box__row--total"
                    style="border-top:2px solid var(--color-border); font-size:14px; margin-top:0.25rem;">
                    <span>Total</span>
                    <span>R$ {{ number_format($orcamento->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

@endsection
