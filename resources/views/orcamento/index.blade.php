@extends('layouts.app')

@section('title', 'Orçamentos')
@section('page_title', 'Orçamentos')

@section('page_actions')
    <a href="{{ route('orcamento.create') }}" class="btn btn--primary btn--sm">+ Novo Orçamento</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('orcamento.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou cliente">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'recusado' => 'Recusado', 'cancelado' => 'Cancelado'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data (início)</label>
                    <input type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data (fim)</label>
                    <input type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('orcamento.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Orçamentos</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:80px;">Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Forma Pgto.</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orcamentos as $orcamento)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $orcamento->numero }}</td>
                                <td data-label="Cliente">{{ $orcamento->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td data-label="Data">{{ $orcamento->data_orcamento->format('d/m/Y') }}</td>
                                <td data-label="Forma Pgto.">{{ $orcamento->forma_pagamento ?? '—' }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    @if($orcamento->situacao === 'aprovado')
                                        <span class="badge badge--success">Aprovado</span>
                                    @elseif($orcamento->situacao === 'recusado')
                                        <span class="badge badge--error">Recusado</span>
                                    @elseif($orcamento->situacao === 'cancelado')
                                        <span class="badge badge--error">Cancelado</span>
                                    @else
                                        <span class="badge badge--warning">Pendente</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('orcamento.show', $orcamento) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($orcamento->situacao === 'pendente')
                                        <a href="{{ route('orcamento.edit', $orcamento) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                    <form method="POST" action="{{ route('orcamento.destroy', $orcamento) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum orçamento cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $orcamentos->withQueryString()->links() }}
        </div>
    </div>

@endsection
