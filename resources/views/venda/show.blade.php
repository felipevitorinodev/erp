@extends(request()->boolean('modal') ? 'layouts.ver-modal' : 'layouts.app')

@section('title', 'Venda #' . $venda->numero)
@section('page_title', 'Venda #' . $venda->numero)

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> / #{{ $venda->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('venda.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        @if($venda->situacao === 'em_andamento')
            <form method="POST" action="{{ route('venda.confirmar', $venda) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm"
                    data-confirm="Confirmar a venda #{{ $venda->numero }}? O estoque será atualizado."
                    data-confirm-title="Confirmar venda"
                    data-confirm-ok="Confirmar venda">
                    <x-icon name="check" />
                    Confirmar
                </button>
            </form>
        @endif

        <x-export-dropdown
            label="Imprimir"
            pdf-rotulo="A4"
            :pdf="route('venda.pdf', $venda)"
            :extras="array_values(array_filter([
                ['rotulo' => '80 mm', 'url' => route('venda.recibo', $venda)],
                $nfce?->situacao === 'autorizada' ? ['rotulo' => 'NFC-e (cupom fiscal)', 'url' => route('venda.nfce.danfe', $venda)] : null,
            ]))"
        />

        @if($venda->situacao === 'em_andamento')
            <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>
        @endif

        @if($venda->situacao !== 'cancelada' && ($venda->situacao !== 'confirmada' || auth()->user()->pode('vendas.cancelar')))
            <form method="POST" action="{{ route('venda.cancelar', $venda) }}">
                @csrf
                <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Cancelar" aria-label="Cancelar"
                    data-confirm="Cancelar a venda #{{ $venda->numero }}?"
                    data-confirm-title="Cancelar venda"
                    data-confirm-ok="Cancelar venda"
                    data-confirm-variant="danger">
                    <x-icon name="x-circle" />
                </button>
            </form>
        @endif

        @if(in_array($venda->situacao, ['cancelada', 'em_andamento'], true))
            <form method="POST" action="{{ route('venda.destroy', $venda) }}">
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

    {{-- Cabeçalho --}}
    <x-ficha titulo="Dados gerais">
        <x-slot:status>
            <x-badge-situacao tipo="venda" :valor="$venda->situacao" />
        </x-slot:status>
        @php
            $notaDoResumo = $nfeDaVenda && in_array($nfeDaVenda->situacao, ['autorizada', 'pendente', 'denegada'], true) ? $nfeDaVenda : ($nfce ?? $nfeDaVenda);
            $qtdItens = $venda->itens->count();
        @endphp
        <x-slot:resumo>
            <div>
                <span>Total</span>
                <strong><x-moeda :valor="$venda->total" /></strong>
            </div>
            <div>
                <span>Pagamento</span>
                <strong class="ficha__resumo-texto">{{ $venda->forma_pagamento ?: '—' }}</strong>
            </div>
            <div>
                <span>Itens</span>
                <strong>{{ $qtdItens }} {{ $qtdItens === 1 ? 'produto' : 'produtos' }}</strong>
            </div>
            <div>
                <span>Nota fiscal</span>
                <strong class="ficha__resumo-texto">
                    @if ($notaDoResumo)
                        {{ (int) $notaDoResumo->modelo === \App\Models\Nfes\Nfe::MODELO_NFCE ? 'NFC-e' : 'NF-e' }}
                        {{ mb_strtolower(\App\Support\Situacoes::de('nfe', $notaDoResumo->situacao)[0]) }}
                    @else
                        Sem nota
                    @endif
                </strong>
            </div>
        </x-slot:resumo>
        <x-ficha-campo label="Data da venda">{{ $venda->data_venda->format('d/m/Y') }}{{ $venda->created_at ? ' às '.$venda->created_at->format('H:i') : '' }}</x-ficha-campo>
        <x-ficha-campo label="Cliente">{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</x-ficha-campo>
        <x-ficha-campo label="Vendedor">{{ $venda->funcionario->nome ?? ($venda->usuario->name ?? '—') }}</x-ficha-campo>
        @if ($venda->data_entrega)
            <x-ficha-campo label="Previsão de entrega">{{ $venda->data_entrega->format('d/m/Y') }}</x-ficha-campo>
        @endif
        @if($venda->observacoes)
            <x-ficha-campo label="Observações" largo class="ficha__item--texto">{{ $venda->observacoes }}</x-ficha-campo>
        @endif
    </x-ficha>

    {{-- Itens --}}
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
                            <th class="col-num">Preço unit.</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($venda->itens as $item)
                            <tr>
                                <td data-label="Produto">
                                    {{ $item->produto_nome }}
                                    @if($item->produto_codigo)
                                        <span class="text-muted" style="font-size:11px;">({{ $item->produto_codigo }})</span>
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
                                    Nenhum item nesta venda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="venda-doc__totais">
            <div class="totals-box totals-box--sale">
                <div class="totals-box__row">
                    <span class="text-muted">Subtotal</span>
                    <span>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row">
                    <span class="text-muted">Desconto</span>
                    <span>- R$ {{ number_format($venda->desconto, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row">
                    <span class="text-muted">Acréscimo</span>
                    <span>+ R$ {{ number_format($venda->acrescimo, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row totals-box__row--total">
                    <span>Total</span>
                    <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

    @include('venda._pagamento')

    @include('venda._nota-fiscal')

@endsection