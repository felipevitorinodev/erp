@extends('layouts.app')

@section('title', 'Venda #' . $venda->numero)
@section('page_title', 'Venda #' . $venda->numero)

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> / #{{ $venda->numero }}
@endsection

@section('page_actions')
    @if($venda->situacao === 'em_andamento')
        <form method="POST" action="{{ route('venda.confirmar', $venda) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--primary btn--sm"
                data-confirm="Confirmar a venda #{{ $venda->numero }}? O estoque será atualizado."
                data-confirm-title="Confirmar venda"
                data-confirm-ok="Confirmar venda">
                ✔ Confirmar Venda
            </button>
        </form>
    @endif
    <a href="{{ route('venda.pdf', $venda) }}" class="btn btn--ghost btn--sm" target="_blank" title="Gerar PDF">PDF</a>
    @if($venda->situacao === 'em_andamento')
        <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm" title="Editar">Editar</a>
    @endif
    <a href="{{ route('venda.index') }}" class="btn btn--ghost btn--sm" title="Voltar">Voltar</a>

    @if($venda->situacao !== 'cancelada')
        <form method="POST" action="{{ route('venda.cancelar', $venda) }}" style="display:inline;">
            @csrf
            <button type="submit" class="btn btn--danger btn--sm"
                data-confirm="Cancelar a venda #{{ $venda->numero }}?"
                data-confirm-title="Cancelar venda"
                data-confirm-ok="Cancelar venda"
                data-confirm-variant="danger">
                Cancelar Venda
            </button>
        </form>
    @endif

    @if(in_array($venda->situacao, ['cancelada', 'em_andamento'], true))
        <form method="POST" action="{{ route('venda.destroy', $venda) }}" style="display:inline;">
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

    {{-- Cabeçalho --}}
    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados Gerais</span>
            <div>
                @if($venda->situacao === 'confirmada')
                    <span class="badge badge--success">Confirmada</span>
                @elseif($venda->situacao === 'cancelada')
                    <span class="badge badge--error">Cancelada</span>
                @else
                    <span class="badge badge--warning">Em andamento</span>
                @endif
            </div>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <span>#{{ $venda->numero }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Data da Venda</label>
                    <span>{{ $venda->data_venda->format('d/m/Y') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Previsão de Entrega</label>
                    <span>{{ $venda->data_entrega ? $venda->data_entrega->format('d/m/Y') : '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Forma de Pagamento</label>
                    <span>{{ $venda->forma_pagamento ?? '—' }}</span>
                </div>
            </div>
            <div class="form-grid form-grid--col-2" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Cliente</label>
                    <span>{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Vendedor</label>
                    <span>{{ $venda->usuario->name ?? '—' }}</span>
                </div>
            </div>
            @if($venda->observacoes)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Observações</label>
                    <span>{{ $venda->observacoes }}</span>
                </div>
            @endif
        </div>
    </div>

    

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
                            <th class="text-right">Qtd.</th>
                            <th class="text-right">Preço Unit.</th>
                            <th class="text-right">Desconto</th>
                            <th class="text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($venda->itens as $item)
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
                                    Nenhum item nesta venda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Totais --}}
        <div class="card__footer">
            <div class="totals-box" style="font-size:12.5px;">
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Subtotal</span>
                    <span>R$ {{ number_format($venda->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Desconto</span>
                    <span>- R$ {{ number_format($venda->desconto, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row" style="border-bottom:none; padding:0.25rem 0;">
                    <span class="text-muted">Acréscimo</span>
                    <span>+ R$ {{ number_format($venda->acrescimo, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row totals-box__row--total"
                    style="border-top:2px solid var(--color-border); font-size:14px; margin-top:0.25rem;">
                    <span>Total</span>
                    <span>R$ {{ number_format($venda->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

@endsection