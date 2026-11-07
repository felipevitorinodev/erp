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
            :extras="[
                ['rotulo' => '80 mm', 'url' => route('venda.recibo', $venda)],
            ]"
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
    <x-ficha titulo="Dados Gerais">
        <x-slot:status>
            <x-badge-situacao tipo="venda" :valor="$venda->situacao" />
        </x-slot:status>
        <x-slot:resumo>
            <div>
                <span>Total</span>
                <strong><x-moeda :valor="$venda->total" /></strong>
            </div>
            <div>
                <span>Subtotal</span>
                <strong><x-moeda :valor="$venda->subtotal" /></strong>
            </div>
            <div>
                <span>Desconto</span>
                <strong><x-moeda :valor="$venda->desconto" /></strong>
            </div>
            <div>
                <span>Acréscimo</span>
                <strong><x-moeda :valor="$venda->acrescimo" /></strong>
            </div>
        </x-slot:resumo>
        <x-ficha-campo label="Número">#{{ $venda->numero }}</x-ficha-campo>
        <x-ficha-campo label="Data da venda">{{ $venda->data_venda->format('d/m/Y') }}</x-ficha-campo>
        <x-ficha-campo label="Previsão de entrega">{{ $venda->data_entrega ? $venda->data_entrega->format('d/m/Y') : '—' }}</x-ficha-campo>
        <x-ficha-campo label="Forma de pagamento">{{ $venda->forma_pagamento ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Cliente">{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</x-ficha-campo>
        <x-ficha-campo label="Vendedor">{{ $venda->funcionario->nome ?? ($venda->usuario->name ?? '—') }}</x-ficha-campo>
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
                            <th class="col-num">Preço Unit.</th>
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

    @if (auth()->user()->pode('nfe'))
    {{-- NF-e --}}
    <div class="card" style="margin-top:1rem;">
        <div class="ficha__head">
            <span class="card__title">NF-e</span>
            @if ($venda->nfe)
                @if ($venda->nfe->situacao === 'autorizada')
                    <x-badge tom="success">Autorizada</x-badge>
                @elseif ($venda->nfe->situacao === 'rejeitada')
                    <x-badge tom="error">Rejeitada</x-badge>
                @elseif ($venda->nfe->situacao === 'denegada')
                    <x-badge tom="error">Denegada</x-badge>
                @elseif ($venda->nfe->situacao === 'cancelada')
                    <x-badge>Cancelada</x-badge>
                @else
                    <x-badge tom="warning">Pendente</x-badge>
                @endif
            @endif
        </div>
        <div class="card__body">
            @if ($venda->nfe && $venda->nfe->situacao === 'autorizada')
                <dl class="ficha__grid">
                    <x-ficha-campo label="Protocolo">{{ $venda->nfe->protocolo ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Autorização">{{ $venda->nfe->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Chave de acesso" largo mono>{{ $venda->nfe->chave_acesso }}</x-ficha-campo>
                </dl>
                <div class="filter-actions" style="margin-top:1rem;">
                    <a href="{{ route('nfe.show', $venda->nfe) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
                    <a href="{{ route('nfe.danfe', $venda->nfe) }}" target="_blank" class="btn btn--primary btn--sm">DANFE</a>
                </div>
            @elseif ($venda->nfe && $venda->nfe->situacao === 'cancelada')
                <p class="text-muted" style="margin:0 0 0.75rem;">
                    A NF-e anterior foi cancelada. Você pode emitir uma nova nota para esta venda.
                </p>
                <div class="filter-actions">
                    <a href="{{ route('nfe.show', $venda->nfe) }}" class="btn btn--ghost btn--sm">Ver NF-e cancelada</a>
                    @if ($venda->situacao === 'confirmada')
                        <form method="POST" action="{{ route('nfe.emitir', $venda) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm"
                                data-confirm="Emitir uma nova NF-e para a venda #{{ $venda->numero }}?"
                                data-confirm-title="Emitir NF-e"
                                data-confirm-ok="Emitir">
                                Emitir nova NF-e
                            </button>
                        </form>
                    @endif
                </div>
            @elseif ($venda->nfe && in_array($venda->nfe->situacao, ['rejeitada', 'pendente'], true))
                @if ($venda->nfe->motivo_status)
                    <div class="alert alert--error" style="margin-bottom:1rem;">
                        {{ $venda->nfe->motivo_status }}
                        @if ($venda->nfe->codigo_status)
                            <span class="text-muted">(Código {{ $venda->nfe->codigo_status }})</span>
                        @endif
                    </div>
                @endif
                <div class="filter-actions">
                    <a href="{{ route('nfe.show', $venda->nfe) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
                    @if ($venda->nfe->situacao === 'rejeitada' && $venda->situacao === 'confirmada')
                        <form method="POST" action="{{ route('nfe.emitir', $venda) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm"
                                data-confirm="Emitir uma nova NF-e para a venda #{{ $venda->numero }}?"
                                data-confirm-title="Emitir NF-e"
                                data-confirm-ok="Emitir">
                                Tentar novamente
                            </button>
                        </form>
                    @endif
                </div>
            @elseif ($venda->situacao === 'confirmada')
                <p class="text-muted" style="margin:0 0 0.75rem;">
                    A NF-e será enviada diretamente para a SEFAZ.
                </p>
                <form method="POST" action="{{ route('nfe.emitir', $venda) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary"
                        data-confirm="Emitir NF-e da venda #{{ $venda->numero }} agora?"
                        data-confirm-title="Emitir NF-e"
                        data-confirm-ok="Emitir">
                        Emitir NF-e
                    </button>
                </form>
            @elseif ($venda->isRascunhoNfe() && auth()->user()->pode('nfe'))
                <p class="text-muted" style="margin:0 0 0.75rem;">
                    Esta venda é um rascunho de NF-e: o estoque só será baixado na emissão.
                </p>
                <a href="{{ route('nfe.rascunho.edit', $venda) }}" class="btn btn--primary btn--sm">Continuar rascunho da NF-e</a>
            @else
                <p class="text-muted" style="margin:0;">
                    Confirme a venda antes de emitir a NF-e.
                </p>
            @endif
        </div>
    </div>
    @endif

@endsection