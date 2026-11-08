@extends(request()->boolean('modal') ? 'layouts.ver-modal' : 'layouts.app')

@section('title', 'Entrada #' . $entrada->numero)
@section('page_title', 'Entrada #' . $entrada->numero)

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de estoque</a> / #{{ $entrada->numero }}
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

    <x-ficha titulo="Dados gerais">
        <x-slot:status>
            <x-badge-situacao tipo="entrada" :valor="$entrada->situacao" />
        </x-slot:status>
        <x-slot:resumo>
            <div>
                <span>Total</span>
                <strong><x-moeda :valor="$entrada->total" /></strong>
            </div>
            <div>
                <span>Subtotal</span>
                <strong><x-moeda :valor="$entrada->subtotal" /></strong>
            </div>
            <div>
                <span>Desconto</span>
                <strong><x-moeda :valor="$entrada->desconto" /></strong>
            </div>
        </x-slot:resumo>
        <x-ficha-campo label="Número">#{{ $entrada->numero }}</x-ficha-campo>
        <x-ficha-campo label="Fornecedor">{{ $entrada->fornecedor->nome ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Data">{{ $entrada->data_entrada->format('d/m/Y') }}</x-ficha-campo>
        @if($entrada->chave_acesso)
            <x-ficha-campo label="NF-e número/série">{{ $entrada->numero_nfe }} / {{ $entrada->serie_nfe }}</x-ficha-campo>
            <x-ficha-campo label="Data de emissão">{{ $entrada->data_emissao_nfe?->format('d/m/Y') ?? '—' }}</x-ficha-campo>
            <x-ficha-campo label="Chave de acesso" largo mono>{{ $entrada->chave_acesso }}</x-ficha-campo>
        @endif
        @if($entrada->observacoes)
            <x-ficha-campo label="Observações" largo class="ficha__item--texto">{{ $entrada->observacoes }}</x-ficha-campo>
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
                            <th class="col-num">Quantidade</th>
                            <th class="col-num">Preço unitário</th>
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
                                <td data-label="Preço unitário" class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
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
                @if($entrada->valor_nota !== null && round((float) $entrada->valor_nota, 2) !== round((float) $entrada->total, 2))
                    <div class="totals-box__row">
                        <span class="text-muted">Valor da NF-e (conta a pagar)</span>
                        <span>R$ {{ number_format($entrada->valor_nota, 2, ',', '.') }}</span>
                    </div>
                @endif
            </div>
        </div>
    </div>

@endsection
