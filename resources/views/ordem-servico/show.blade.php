@extends(request()->boolean('modal') ? 'layouts.ver-modal' : 'layouts.app')

@section('title', 'OS #' . $os->numero)
@section('page_title', 'Ordem de serviço #' . $os->numero)

@section('breadcrumb')
    <a href="{{ route('ordem-servico.index') }}">Ordens de serviço</a> / #{{ $os->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('ordem-servico.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        <x-export-dropdown
            label="Imprimir"
            pdf-rotulo="A4"
            :pdf="route('ordem-servico.pdf', $os)"
            :extras="[
                ['rotulo' => '80 mm', 'url' => route('ordem-servico.recibo', $os)],
            ]"
        />

        @if ($os->estaAberta())
            <form method="POST" action="{{ route('ordem-servico.faturar', $os) }}">
                @csrf
                <button type="submit" class="btn btn--success btn--sm"
                    data-confirm="Faturar a OS #{{ $os->numero }} e gerar uma venda de R$ {{ number_format($os->total, 2, ',', '.') }}?"
                    data-confirm-title="Faturar ordem de serviço"
                    data-confirm-ok="Faturar e gerar venda"
                    data-confirm-variant="success">
                    <x-icon name="check" />
                    Faturar
                </button>
            </form>

            <a href="{{ route('ordem-servico.edit', $os) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>

            <form method="POST" action="{{ route('ordem-servico.cancelar', $os) }}">
                @csrf
                <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Cancelar" aria-label="Cancelar"
                    data-confirm="Cancelar a OS #{{ $os->numero }}?"
                    data-confirm-title="Cancelar ordem de serviço"
                    data-confirm-ok="Cancelar OS"
                    data-confirm-variant="danger">
                    <x-icon name="x-circle" />
                </button>
            </form>
        @endif

        @if ($os->situacao !== 'faturada')
            <form method="POST" action="{{ route('ordem-servico.destroy', $os) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm btn--icon" title="Excluir" aria-label="Excluir"
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

    @if ($os->estaAberta())
        <div class="card mb-2">
            <div class="card__body">
                <form method="POST" action="{{ route('ordem-servico.situacao', $os) }}"
                    style="display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem;">
                    @csrf
                    <span class="text-muted">Andamento:</span>
                    @foreach (\App\Models\OrdensServico\OrdemServico::SITUACOES_ABERTAS as $situacao)
                        <button type="submit" name="situacao" value="{{ $situacao }}"
                            class="btn btn--sm {{ $os->situacao === $situacao ? 'btn--primary' : 'btn--outline' }}"
                            @if ($os->situacao === $situacao) disabled aria-pressed="true" @endif>
                            {{ \App\Models\OrdensServico\OrdemServico::SITUACOES[$situacao] }}
                        </button>
                    @endforeach
                </form>
            </div>
        </div>
    @endif

    <x-ficha titulo="Dados gerais">
        <x-slot:status>
            <span class="badge {{ $os->situacaoBadge() }}">{{ $os->situacaoLabel() }}</span>
        </x-slot:status>
        <x-slot:resumo>
            <div>
                <span>Total</span>
                <strong><x-moeda :valor="$os->total" /></strong>
            </div>
            <div>
                <span>Subtotal</span>
                <strong><x-moeda :valor="$os->subtotal" /></strong>
            </div>
            <div>
                <span>Desconto</span>
                <strong><x-moeda :valor="$os->desconto" /></strong>
            </div>
            <div>
                <span>Acréscimo</span>
                <strong><x-moeda :valor="$os->acrescimo" /></strong>
            </div>
        </x-slot:resumo>
        <x-ficha-campo label="Cliente">{{ $os->cliente->nome ?? '— Consumidor Final —' }}</x-ficha-campo>
        <x-ficha-campo label="Técnico">{{ $os->tecnico->nome ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Abertura">{{ $os->data_abertura->format('d/m/Y') }}</x-ficha-campo>
        <x-ficha-campo label="Previsão">{{ $os->data_previsao ? $os->data_previsao->format('d/m/Y') : '—' }}</x-ficha-campo>
        <x-ficha-campo label="Conclusão">{{ $os->data_conclusao ? $os->data_conclusao->format('d/m/Y') : '—' }}</x-ficha-campo>
        <x-ficha-campo label="Forma de pagamento">{{ $os->forma_pagamento ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Garantia">{{ $os->garantia ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Aberta por">{{ $os->usuario->name ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Origem / venda">
            @if ($os->orcamento)
                <a href="{{ route('orcamento.show', $os->orcamento) }}">Orçamento #{{ $os->orcamento->numero }}</a>
            @endif
            @if ($os->venda)
                {{ $os->orcamento ? '·' : '' }}
                <a href="{{ route('venda.show', $os->venda) }}">Venda #{{ $os->venda->numero }}</a>
            @endif
            @if (! $os->orcamento && ! $os->venda)
                —
            @endif
        </x-ficha-campo>
    </x-ficha>

    <x-ficha titulo="Equipamento">
        <x-ficha-campo label="Equipamento">{{ $os->equipamento ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Marca / modelo">{{ trim(($os->marca ?? '') . ' ' . ($os->modelo ?? '')) ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Nº de série">{{ $os->numero_serie ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Acessórios">{{ $os->acessorios ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Defeito relatado" largo class="ficha__item--texto">{{ $os->defeito_relatado ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Diagnóstico" largo class="ficha__item--texto">{{ $os->diagnostico ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Serviço executado" largo class="ficha__item--texto">{{ $os->servico_executado ?: '—' }}</x-ficha-campo>
        @if ($os->observacoes)
            <x-ficha-campo label="Observações" largo class="ficha__item--texto">{{ $os->observacoes }}</x-ficha-campo>
        @endif
    </x-ficha>

    <div class="card" style="margin-top:1rem;">
        <div class="card__header">
            <span class="card__title">Peças e serviços</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="col-num">Qtd.</th>
                            <th class="col-num">Preço unit.</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($os->itens as $item)
                            <tr>
                                <td data-label="Item">
                                    {{ $item->produto_nome }}
                                    @if ((int) $item->produto?->tipo === 2)
                                        <span class="badge badge--info">Serviço</span>
                                    @endif
                                    @if ($item->produto_codigo)
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
                                    Nenhuma peça ou serviço lançado. Edite a OS para adicionar antes de faturar.
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
                    <span>R$ {{ number_format($os->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Desconto</span>
                    <span>- R$ {{ number_format($os->desconto, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Acréscimo</span>
                    <span>+ R$ {{ number_format($os->acrescimo, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row totals-box__row--total"
                    style="border-top:2px solid var(--color-border); font-size:14px; margin-top:0.25rem;">
                    <span>Total</span>
                    <span>R$ {{ number_format($os->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

@endsection
