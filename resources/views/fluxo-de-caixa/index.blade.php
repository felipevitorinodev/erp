@extends('layouts.app')

@section('title', 'Fluxo de Caixa')
@section('page_title', 'Fluxo de Caixa')

@section('page_actions')
    <a href="{{ route('fluxo-de-caixa.csv', request()->query()) }}" class="btn btn--ghost btn--sm">Exportar CSV</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('fluxo-de-caixa.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Data início</label>
                    <input type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data fim</label>
                    <input type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('fluxo-de-caixa.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="stats-grid mb-2">
        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge badge--success">R$ {{ number_format($total_entradas, 2, ',', '.') }}</span>
                </div>
                <div class="text-muted">Total Entradas</div>
            </div>
        </div>
        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge badge--error">R$ {{ number_format($total_saidas, 2, ',', '.') }}</span>
                </div>
                <div class="text-muted">Total Saídas</div>
            </div>
        </div>
        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge {{ $saldo_periodo >= 0 ? 'badge--success' : 'badge--error' }}">
                        R$ {{ number_format($saldo_periodo, 2, ',', '.') }}
                    </span>
                </div>
                <div class="text-muted">Saldo do Período</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Movimentação diária</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th class="col-num">Entradas</th>
                            <th class="col-num">Saídas</th>
                            <th class="col-num">Saldo do Dia</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dias as $dia)
                            <tr>
                                <td data-label="Data">{{ $dia['data']->format('d/m/Y') }}</td>
                                <td data-label="Entradas" class="col-num">R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</td>
                                <td data-label="Saídas" class="col-num">R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</td>
                                <td data-label="Saldo do Dia" class="col-num {{ $dia['saldo_dia'] < 0 ? 'text--danger' : '' }}">
                                    R$ {{ number_format($dia['saldo_dia'], 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum movimento no período selecionado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $dias->withQueryString()->links() }}
        </div>
    </div>

@endsection
