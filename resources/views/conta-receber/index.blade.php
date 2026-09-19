@extends('layouts.app')

@section('title', 'Contas a Receber')
@section('page_title', 'Contas a Receber')

@section('page_actions')
    <a href="{{ route('conta-receber.create') }}" class="btn btn--primary btn--sm">+ Nova Conta</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('conta-receber.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['aberta' => 'Aberta', 'parcial' => 'Parcial', 'paga' => 'Paga', 'cancelada' => 'Cancelada'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Vencimento</label>
                    <input type="date" name="data_vencimento" class="form-control"
                        value="{{ $filtros['data_vencimento'] ?? '' }}">
                </div>
                <div class="form-group" style="justify-content:center;">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('conta-receber.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
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
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Pago</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contas as $conta)
                            @php
                                $vencido = $conta->data_vencimento->lt(now()->startOfDay())
                                    && !in_array($conta->situacao, ['paga', 'cancelada'], true);
                            @endphp
                            <tr>
                                <td class="text-muted">{{ $conta->id }}</td>
                                <td>{{ $conta->descricao }}</td>
                                <td>{{ $conta->cliente->nome ?? '—' }}</td>
                                <td class="{{ $vencido ? 'text--danger' : '' }}">
                                    {{ $conta->data_vencimento->format('d/m/Y') }}
                                </td>
                                <td>R$ {{ number_format($conta->valor, 2, ',', '.') }}</td>
                                <td>R$ {{ number_format($conta->valor_pago, 2, ',', '.') }}</td>
                                <td>
                                    @if($conta->situacao === 'paga')
                                        <span class="badge badge--success">Paga</span>
                                    @elseif($conta->situacao === 'parcial')
                                        <span class="badge badge--info">Parcial</span>
                                    @elseif($conta->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Aberta</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('conta-receber.show', $conta) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if(!in_array($conta->situacao, ['paga', 'cancelada'], true))
                                        <a href="{{ route('conta-receber.edit', $conta) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                    <form method="POST" action="{{ route('conta-receber.destroy', $conta) }}" style="display:inline;">
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
                                <td colspan="8" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma conta a receber cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
