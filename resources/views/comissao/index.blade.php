@extends('layouts.app')

@section('title', 'Comissões')
@section('page_title', 'Comissões de Vendedores')

@section('content')

    <div class="form-grid form-grid--col-2 mb-2">
        <div class="card">
            <div class="card__body">
                <p class="text-muted" style="margin:0 0 .25rem;">Total pendente</p>
                <p class="card__title" style="margin:0;">R$ {{ number_format($totais['pendentes'], 2, ',', '.') }}</p>
            </div>
        </div>
        <div class="card">
            <div class="card__body">
                <p class="text-muted" style="margin:0 0 .25rem;">Total pago</p>
                <p class="card__title" style="margin:0;">R$ {{ number_format($totais['pagas'], 2, ',', '.') }}</p>
            </div>
        </div>
    </div>

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('comissao.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Vendedor</label>
                    <select name="funcionario_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($funcionarios as $funcionario)
                            <option value="{{ $funcionario->id }}"
                                {{ (string) ($filtros['funcionario_id'] ?? '') === (string) $funcionario->id ? 'selected' : '' }}>
                                {{ $funcionario->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['pendente' => 'Pendente', 'paga' => 'Paga'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data da venda (início)</label>
                    <input type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data da venda (fim)</label>
                    <input type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group" style="justify-content:center;">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('comissao.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:60px;">Nº</th>
                            <th>Vendedor</th>
                            <th>Venda</th>
                            <th>Data venda</th>
                            <th class="col-num">%</th>
                            <th class="col-num">Valor</th>
                            <th>Situação</th>
                            <th>Pagamento</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($comissoes as $comissao)
                            <tr>
                                <td data-label="Nº" class="text-muted">{{ $comissao->id }}</td>
                                <td data-label="Vendedor">{{ $comissao->funcionario->nome ?? '—' }}</td>
                                <td data-label="Venda">
                                    @if($comissao->venda)
                                        <a href="{{ route('venda.show', $comissao->venda) }}">#{{ $comissao->venda->numero }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Data venda">{{ $comissao->venda?->data_venda?->format('d/m/Y') ?? '—' }}</td>
                                <td data-label="%" class="col-num">{{ number_format($comissao->percentual, 2, ',', '.') }}%</td>
                                <td data-label="Valor" class="col-num">R$ {{ number_format($comissao->valor, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    @if($comissao->situacao === 'paga')
                                        <span class="badge badge--success">Paga</span>
                                    @else
                                        <span class="badge badge--warning">Pendente</span>
                                    @endif
                                </td>
                                <td data-label="Pagamento">{{ $comissao->data_pagamento ? $comissao->data_pagamento->format('d/m/Y') : '—' }}</td>
                                <td class="col-actions" data-label="Ações">
                                    @if($comissao->situacao === 'pendente')
                                        <form method="POST" action="{{ route('comissao.pagar', $comissao) }}" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn--primary btn--sm"
                                                data-confirm="Marcar esta comissão como paga?"
                                                data-confirm-title="Pagar comissão"
                                                data-confirm-ok="Confirmar">
                                                Pagar
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding:2rem;">Nenhuma comissão encontrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $comissoes->withQueryString()->links() }}
        </div>
    </div>

@endsection
