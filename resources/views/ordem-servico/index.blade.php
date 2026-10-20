@extends('layouts.app')

@section('title', 'Ordens de Serviço')
@section('page_title', 'Ordens de Serviço')

@section('page_actions')
    <a href="{{ route('ordem-servico.create') }}" class="btn btn--primary btn--sm">+ Nova OS</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('ordem-servico.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número, cliente, equipamento ou nº de série">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach (\App\Models\OrdensServico\OrdemServico::SITUACOES as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Abertura (início)</label>
                    <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Abertura (fim)</label>
                    <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('ordem-servico.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Ordens de Serviço</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:80px;">Número</th>
                            <th>Cliente</th>
                            <th>Equipamento</th>
                            <th>Técnico</th>
                            <th>Abertura</th>
                            <th>Previsão</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordens as $os)
                            @php $atrasada = $os->estaAberta() && $os->situacao !== 'concluida' && $os->data_previsao && $os->data_previsao->isPast() && ! $os->data_previsao->isToday(); @endphp
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $os->numero }}</td>
                                <td data-label="Cliente">{{ $os->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td data-label="Equipamento">
                                    {{ $os->equipamento ?: '—' }}
                                    @if ($os->numero_serie)
                                        <span class="text-muted" style="font-size:11px;">(S/N {{ $os->numero_serie }})</span>
                                    @endif
                                </td>
                                <td data-label="Técnico">{{ $os->tecnico->nome ?? '—' }}</td>
                                <td data-label="Abertura">{{ $os->data_abertura->format('d/m/Y') }}</td>
                                <td data-label="Previsão">
                                    {{ $os->data_previsao ? $os->data_previsao->format('d/m/Y') : '—' }}
                                    @if ($atrasada)
                                        <span class="badge badge--error">Atrasada</span>
                                    @endif
                                </td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($os->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    <span class="badge {{ $os->situacaoBadge() }}">{{ $os->situacaoLabel() }}</span>
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('ordem-servico.show', $os) }}" class="btn btn--ghost btn--sm btn--icon" title="Ver" aria-label="Ver">
                                        <x-icon name="eye" />
                                    </a>
                                    @if ($os->estaAberta())
                                        <a href="{{ route('ordem-servico.edit', $os) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                            <x-icon name="edit" />
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted empty-state">
                                    Nenhuma ordem de serviço cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $ordens->withQueryString()->links() }}
        </div>
    </div>

@endsection
