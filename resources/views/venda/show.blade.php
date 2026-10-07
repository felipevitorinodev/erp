@extends('layouts.app')

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

        <x-export-dropdown :pdf="route('venda.pdf', $venda)" />

        @if($venda->situacao === 'em_andamento')
            <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                <x-icon name="edit" />
            </a>
        @endif

        @if($venda->situacao !== 'cancelada')
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
                    <span>{{ $venda->funcionario->nome ?? ($venda->usuario->name ?? '—') }}</span>
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
                            <th class="col-num">Qtd.</th>
                            <th class="col-num">Preço Unit.</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
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
                                <td class="col-num">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                                <td class="col-num">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                                <td class="col-num">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                                <td class="col-num text-bold">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
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

    {{-- NF-e --}}
    <div class="card" style="margin-top:1rem;">
        <div class="card__header">
            <span class="card__title">NF-e</span>
            @if ($venda->nfe)
                @if ($venda->nfe->situacao === 'autorizada')
                    <span class="badge badge--success">Autorizada</span>
                @elseif ($venda->nfe->situacao === 'rejeitada')
                    <span class="badge badge--error">Rejeitada</span>
                @elseif ($venda->nfe->situacao === 'cancelada')
                    <span class="badge badge--neutral">Cancelada</span>
                @else
                    <span class="badge badge--warning">Pendente</span>
                @endif
            @endif
        </div>
        <div class="card__body">
            @if ($venda->nfe && $venda->nfe->situacao === 'autorizada')
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label">Protocolo</label>
                        <span>{{ $venda->nfe->protocolo ?? '—' }}</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Autorização</label>
                        <span>{{ $venda->nfe->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</span>
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Chave de acesso</label>
                    <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; word-break: break-all; display:block;">
                        {{ $venda->nfe->chave_acesso }}
                    </span>
                </div>
                <div class="filter-actions" style="margin-top:1rem;">
                    <a href="{{ route('nfe.show', $venda->nfe) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
                    <a href="{{ route('nfe.danfe', $venda->nfe) }}" target="_blank" class="btn btn--primary btn--sm">DANFE</a>
                </div>
            @elseif ($venda->nfe && $venda->nfe->situacao === 'cancelada')
                <p class="text-muted" style="margin:0 0 0.75rem;">
                    Esta venda possui NF-e cancelada. Emissão de nova nota para a mesma venda não está disponível nesta versão.
                </p>
                <a href="{{ route('nfe.show', $venda->nfe) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
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
            @else
                <p class="text-muted" style="margin:0;">
                    Confirme a venda antes de emitir a NF-e.
                </p>
            @endif
        </div>
    </div>

@endsection