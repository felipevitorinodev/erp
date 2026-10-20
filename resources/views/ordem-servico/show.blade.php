@extends('layouts.app')

@section('title', 'OS #' . $os->numero)
@section('page_title', 'Ordem de Serviço #' . $os->numero)

@section('breadcrumb')
    <a href="{{ route('ordem-servico.index') }}">Ordens de Serviço</a> / #{{ $os->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('ordem-servico.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        <x-export-dropdown :pdf="route('ordem-servico.pdf', $os)" />

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

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados gerais</span>
            <span class="badge {{ $os->situacaoBadge() }}">{{ $os->situacaoLabel() }}</span>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Cliente</label>
                    <span>{{ $os->cliente->nome ?? '— Consumidor Final —' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Técnico</label>
                    <span>{{ $os->tecnico->nome ?? '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Abertura</label>
                    <span>{{ $os->data_abertura->format('d/m/Y') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Previsão / Conclusão</label>
                    <span>
                        {{ $os->data_previsao ? $os->data_previsao->format('d/m/Y') : '—' }}
                        /
                        {{ $os->data_conclusao ? $os->data_conclusao->format('d/m/Y') : '—' }}
                    </span>
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de pagamento</label>
                    <span>{{ $os->forma_pagamento ?? '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Garantia</label>
                    <span>{{ $os->garantia ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Aberta por</label>
                    <span>{{ $os->usuario->name ?? '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Origem / Venda</label>
                    <span>
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
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:1rem;">
        <div class="card__header">
            <span class="card__title">Equipamento</span>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Equipamento</label>
                    <span>{{ $os->equipamento ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Marca / Modelo</label>
                    <span>{{ trim(($os->marca ?? '') . ' ' . ($os->modelo ?? '')) ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Nº de série</label>
                    <span>{{ $os->numero_serie ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Acessórios</label>
                    <span>{{ $os->acessorios ?: '—' }}</span>
                </div>
            </div>
            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Defeito relatado</label>
                    <span style="white-space:pre-line;">{{ $os->defeito_relatado ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Diagnóstico</label>
                    <span style="white-space:pre-line;">{{ $os->diagnostico ?: '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Serviço executado</label>
                    <span style="white-space:pre-line;">{{ $os->servico_executado ?: '—' }}</span>
                </div>
            </div>
            @if ($os->observacoes)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Observações</label>
                    <span style="white-space:pre-line;">{{ $os->observacoes }}</span>
                </div>
            @endif
        </div>
    </div>

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
                            <th class="col-num">Preço Unit.</th>
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
                                <td data-label="Preço Unit." class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
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
