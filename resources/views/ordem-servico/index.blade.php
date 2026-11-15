@extends('layouts.app')

@section('title', 'Ordens de serviço')
@section('page_title', 'Ordens de serviço')

@section('page_actions')
    <a href="{{ route('ordem-servico.create') }}" class="btn btn--primary btn--sm">+ Nova OS</a>
@endsection

@section('content')

    <x-filtros :action="route('ordem-servico.index')" :manter="['situacao']">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número, cliente, equipamento ou nº de série">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_inicio">Abertura (início)</label>
                    <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_fim">Abertura (fim)</label>
                    <input id="campo-data_fim" type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$ordens">
        <x-slot:abas>
            {{-- Mesmas chaves de OrdemServico::SITUACOES, no plural como nas outras listagens. --}}
            <x-filtro-abas :opcoes="array_intersect_key([
                'aberta' => 'Abertas',
                'em_execucao' => 'Em execução',
                'aguardando_peca' => 'Aguardando peça',
                'concluida' => 'Concluídas',
                'faturada' => 'Faturadas',
                'cancelada' => 'Canceladas',
            ], \App\Models\OrdensServico\OrdemServico::SITUACOES)" />
        </x-slot:abas>
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
                                    <x-acoes>
                                        <x-slot:editar>
                                            @if ($os->estaAberta())
                                                <a href="{{ route('ordem-servico.edit', $os) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                    <x-icon name="edit" />
                                                </a>
                                            @endif
                                        </x-slot:editar>
                                        <x-slot:ver>
                                            <a href="{{ route('ordem-servico.show', $os) }}" class="btn btn--ghost btn--sm btn--icon js-ver" data-no-loader title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="ferramentas"
                                :colspan="9"
                                titulo="Nenhuma ordem de serviço cadastrada"
                                texto="Registre o equipamento e o problema e acompanhe até o faturamento."
                                :acao="route('ordem-servico.create')"
                                acao-texto="+ Nova OS"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
