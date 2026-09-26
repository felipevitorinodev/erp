@extends('layouts.app')

@section('title', 'Entrada #' . $entrada->numero)
@section('page_title', 'Entrada #' . $entrada->numero)

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de Estoque</a> / #{{ $entrada->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost btn--sm">Voltar</a>

        @if($entrada->situacao === 'rascunho')
            <form method="POST" action="{{ route('entrada-estoque.confirmar', $entrada) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm"
                    data-confirm="Confirmar a entrada #{{ $entrada->numero }}? O estoque será atualizado e uma conta a pagar será gerada."
                    data-confirm-title="Confirmar entrada"
                    data-confirm-ok="Confirmar">
                    ✔ Confirmar
                </button>
            </form>

            <a href="{{ route('entrada-estoque.edit', $entrada) }}" class="btn btn--ghost btn--sm">Editar</a>

            <form method="POST" action="{{ route('entrada-estoque.destroy', $entrada) }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm"
                    data-confirm="Confirmar exclusão da entrada #{{ $entrada->numero }}?"
                    data-confirm-title="Excluir"
                    data-confirm-ok="Excluir"
                    data-confirm-variant="danger">
                    Excluir
                </button>
            </form>
        @endif

        @if($entrada->situacao === 'confirmada')
            <form method="POST" action="{{ route('entrada-estoque.cancelar', $entrada) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn--danger btn--sm"
                    data-confirm="Cancelar a entrada #{{ $entrada->numero }}? O estoque e a conta a pagar serão revertidos."
                    data-confirm-title="Cancelar entrada"
                    data-confirm-ok="Cancelar"
                    data-confirm-variant="danger">
                    Cancelar
                </button>
            </form>
        @endif
    </div>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados Gerais</span>
            <div>
                @if($entrada->situacao === 'confirmada')
                    <span class="badge badge--success">Confirmada</span>
                @elseif($entrada->situacao === 'cancelada')
                    <span class="badge badge--error">Cancelada</span>
                @else
                    <span class="badge badge--warning">Rascunho</span>
                @endif
            </div>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <span>#{{ $entrada->numero }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Fornecedor</label>
                    <span>{{ $entrada->fornecedor->nome ?? '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <span>{{ $entrada->data_entrada->format('d/m/Y') }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <span>
                        @if($entrada->situacao === 'confirmada')
                            <span class="badge badge--success">Confirmada</span>
                        @elseif($entrada->situacao === 'cancelada')
                            <span class="badge badge--error">Cancelada</span>
                        @else
                            <span class="badge badge--warning">Rascunho</span>
                        @endif
                    </span>
                </div>
            </div>
            @if($entrada->observacoes)
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Observações</label>
                    <span>{{ $entrada->observacoes }}</span>
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
                            <th class="col-num">Quantidade</th>
                            <th class="col-num">Preço Unitário</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entrada->itens as $item)
                            <tr>
                                <td data-label="Produto">
                                    {{ $item->produto_nome }}
                                    @if($item->produto_codigo)
                                        <span class="text-muted">({{ $item->produto_codigo }})</span>
                                    @endif
                                </td>
                                <td data-label="Quantidade" class="col-num">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                <td data-label="Preço Unitário" class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td data-label="Desconto" class="col-num">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                                <td data-label="Total" class="col-num text-bold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted" style="padding:1.5rem;">
                                    Nenhum item nesta entrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            <div class="totals-box">
                <div class="totals-box__row">
                    <span class="text-muted">Subtotal</span>
                    <span>R$ {{ number_format($entrada->subtotal, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row">
                    <span class="text-muted">Desconto</span>
                    <span>- R$ {{ number_format($entrada->desconto, 2, ',', '.') }}</span>
                </div>
                <div class="totals-box__row totals-box__row--total">
                    <span>Total</span>
                    <span>R$ {{ number_format($entrada->total, 2, ',', '.') }}</span>
                </div>
            </div>
        </div>
    </div>

@endsection
