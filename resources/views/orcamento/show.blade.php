@extends('layouts.app')

@section('title', 'Orçamento #' . $orcamento->numero)
@section('page_title', 'Orçamento #' . $orcamento->numero)

@section('breadcrumb')
    <a href="{{ route('orcamento.index') }}">Orçamentos</a> / #{{ $orcamento->numero }}
@endsection

@section('page_actions')
    @if($orcamento->situacao === 'pendente')
        <form method="POST" action="{{ route('orcamento.aprovar', $orcamento) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--success btn--sm"
                data-confirm="Aprovar o orçamento #{{ $orcamento->numero }} e gerar uma venda?"
                data-confirm-title="Aprovar orçamento"
                data-confirm-ok="Aprovar e gerar venda"
                data-confirm-variant="success">
                ✔ Aprovar
            </button>
        </form>
        <a href="{{ route('orcamento.edit', $orcamento) }}" class="btn btn--ghost btn--sm">Editar</a>
        <form method="POST" action="{{ route('orcamento.cancelar', $orcamento) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--danger btn--sm"
                data-confirm="Cancelar o orçamento #{{ $orcamento->numero }}?"
                data-confirm-title="Cancelar orçamento"
                data-confirm-ok="Cancelar orçamento"
                data-confirm-variant="danger">
                Cancelar
            </button>
        </form>
    @endif

    <a href="{{ route('orcamento.index') }}" class="btn btn--ghost btn--sm">Voltar</a>

    @if(in_array($orcamento->situacao, ['pendente', 'cancelado', 'recusado'], true))
        <form method="POST" action="{{ route('orcamento.destroy', $orcamento) }}" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn--danger btn--sm"
                data-confirm="Confirmar exclusão?"
                data-confirm-title="Excluir"
                data-confirm-ok="Excluir"
                data-confirm-variant="danger">
                Excluir
            </button>
        </form>
    @endif
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados Gerais</span>
            <div>
                @if($orcamento->situacao === 'aprovado')
                    <span class="badge badge--success">Aprovado</span>
                @elseif($orcamento->situacao === 'recusado')
                    <span class="badge badge--error">Recusado</span>
                @elseif($orcamento->situacao === 'cancelado')
                    <span class="badge badge--error">Cancelado</span>
                @else
                    <span class="badge badge--warning">Pendente</span>
                @endif
            </div>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <span>#{{ $orcamento->numero }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Data do Orçamento</label>
                    <span>{{ $orcamento->data_orcamento->format('d/m/Y') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Previsão de Entrega</label>
                    <span>{{ $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de Pagamento</label>
                    <span>{{ $orcamento->forma_pagamento ?? '—' }}</span>
                </div>
            </div>
            <div class="form-grid form-grid--col-2" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Cliente</label>
                    <span>{{ $orcamento->cliente->nome ?? '— Consumidor Final —' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Vendedor</label>
                    <span>{{ $orcamento->usuario->name ?? '—' }}</span>
                </div>
            </div>
            @if($orcamento->venda_id && $orcamento->venda)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Venda gerada</label>
                    <span>
                        <a href="{{ route('venda.show', $orcamento->venda) }}">#{{ $orcamento->venda->numero }}</a>
                    </span>
                </div>
            @endif
            @if($orcamento->observacoes)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Observações</label>
                    <span>{{ $orcamento->observacoes }}</span>
                </div>
            @endif
        </div>
    </div>

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
                            <th class="text-right">Qtd.</th>
                            <th class="text-right">Preço Unit.</th>
                            <th class="text-right">Desconto</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orcamento->itens as $item)
                            <tr>
                                <td>
                                    {{ $item->produto_nome }}
                                    @if($item->produto_codigo)
                                        <span class="text-muted" style="font-size:11px;">({{ $item->produto_codigo }})</span>
                                    @endif
                                </td>
                                <td class="text-right">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                                <td class="text-right">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="text-right">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                                <td class="text-right text-bold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
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
