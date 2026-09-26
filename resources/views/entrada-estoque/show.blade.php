@extends('layouts.app')

@section('title', 'Entrada #' . $entrada->numero)
@section('page_title', 'Entrada #' . $entrada->numero)

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de Estoque</a> / #{{ $entrada->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        @if($entrada->situacao === 'rascunho')
            <form method="POST" action="{{ route('entrada-estoque.confirmar', $entrada) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm"
                    data-confirm="Confirmar a entrada #{{ $entrada->numero }}? O estoque será atualizado e uma conta a pagar será gerada."
                    data-confirm-title="Confirmar entrada"
                    data-confirm-ok="Confirmar">
                    <x-icon name="check" />
                    Confirmar
                </button>
            </form>

            <a href="{{ route('entrada-estoque.edit', $entrada) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>
        @endif

        @if($entrada->situacao === 'confirmada')
            <form method="POST" action="{{ route('entrada-estoque.cancelar', $entrada) }}">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm btn--icon" title="Cancelar" aria-label="Cancelar"
                    data-confirm="Cancelar a entrada #{{ $entrada->numero }}? O estoque e a conta a pagar serão revertidos."
                    data-confirm-title="Cancelar entrada"
                    data-confirm-ok="Cancelar"
                    data-confirm-variant="danger">
                    <x-icon name="x-circle" />
                </button>
            </form>
        @endif

        @if(in_array($entrada->situacao, ['rascunho', 'confirmada', 'cancelada'], true))
            <form method="POST" action="{{ route('entrada-estoque.destroy', $entrada) }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                    title="Excluir" aria-label="Excluir"
                    data-confirm="{{ $entrada->situacao === 'confirmada'
                        ? 'Excluir a compra #' . $entrada->numero . '? O estoque e a conta a pagar serão revertidos e o registro será removido.'
                        : 'Confirmar exclusão da compra #' . $entrada->numero . '?' }}"
                    data-confirm-title="Excluir compra"
                    data-confirm-ok="Excluir"
                    data-confirm-variant="danger">
                    <x-icon name="trash" />
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

            @if($entrada->chave_acesso)
                <div style="margin-top:1.5rem;padding-top:1rem;border-top:1px solid var(--border);">
                    <span class="text-muted text-bold" style="font-size:0.85rem;display:block;margin-bottom:0.5rem;">
                        Nota Fiscal de Origem
                    </span>
                    <dl style="display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:0.75rem;margin:0;">
                        <div>
                            <dt class="text-muted" style="font-size:0.8rem;">NF-e Número/Série</dt>
                            <dd style="margin:0;font-weight:500;">{{ $entrada->numero_nfe }} / {{ $entrada->serie_nfe }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted" style="font-size:0.8rem;">Data de Emissão</dt>
                            <dd style="margin:0;">{{ $entrada->data_emissao_nfe?->format('d/m/Y') }}</dd>
                        </div>
                        <div style="grid-column:1/-1;">
                            <dt class="text-muted" style="font-size:0.8rem;">Chave de Acesso</dt>
                            <dd style="margin:0;font-family:monospace;font-size:12px;word-break:break-all;">
                                {{ $entrada->chave_acesso }}
                            </dd>
                        </div>
                    </dl>
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
