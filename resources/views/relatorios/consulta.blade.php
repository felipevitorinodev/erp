@extends('layouts.app')

@section('title', 'Relatório — '.$definicao['titulo'])
@section('page_title', $definicao['titulo'])

@section('breadcrumb')
    <a href="{{ route('relatorios.index') }}">Relatórios</a>
    / {{ $definicao['categoria'] }}
    / {{ $definicao['titulo'] }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('relatorios.index') }}" class="btn btn--ghost btn--sm">Voltar</a>
        <x-export-dropdown :pdf="$pdf" :csv="$csv" />
    </div>
@endsection

@section('content')

    <p class="relatorio-pagina__descricao">{{ $definicao['descricao'] }}</p>

    <details class="relatorio-filtros" id="relatorio-filtros">
        <summary class="relatorio-filtros__resumo">
            Filtros
            @if ($ativos > 0)
                <span class="relatorio-filtros__contador">{{ $ativos }}</span>
            @endif
        </summary>
        <form method="GET" action="{{ $action }}" id="form-relatorio" class="relatorio-filtros__corpo">
            @if ($comPeriodo)
                <div class="relatorio-atalhos" role="group" aria-label="Atalhos de período">
                    @foreach ($atalhos as $atalho)
                        <a href="{{ $atalho['url'] }}" @class(['relatorio-atalho', 'is-ativo' => $atalho['ativo']])>{{ $atalho['rotulo'] }}</a>
                    @endforeach
                    <button type="button" class="relatorio-atalho" data-periodo-personalizado>Personalizado</button>
                </div>
                <div class="form-grid form-grid--col-4">
                    <x-filtro-periodo :inicio="$filtros['data_inicio']" :fim="$filtros['data_fim']" />
                    @include('relatorios.parcial.campos')
                </div>
            @else
                <div class="form-grid form-grid--col-4">
                    @include('relatorios.parcial.campos')
                </div>
            @endif
            @if ($errors->any())
                <p class="form-error" role="alert">{{ $errors->first() }}</p>
            @endif
            <div class="filter-actions">
                <button type="submit" class="btn btn--primary" data-relatorio-aplicar>Aplicar</button>
                <a href="{{ $limpar }}" class="btn btn--ghost">Limpar</a>
            </div>
        </form>
    </details>
    <script>
        (function () {
            var painel = document.getElementById('relatorio-filtros');
            if (painel && window.matchMedia('(min-width: 769px)').matches) {
                painel.setAttribute('open', '');
            }
        })();
    </script>

    <x-relatorio-kpis class="kpi-grid--relatorio">
        @foreach ($kpis as $kpi)
            <x-relatorio-kpi
                :label="$kpi['label']"
                :valor="$kpi['valor']"
                :detalhe="$kpi['detalhe']"
                :tom="$kpi['tom']"
                :icone="$kpi['icone']"
                :destaque="$kpi['destaque']"
            />
        @endforeach
    </x-relatorio-kpis>

    <div class="relatorio-graficos" id="relatorio-graficos" data-url="{{ $resumoUrl }}">
        <section class="relatorio-grafico" data-grafico>
            <div class="relatorio-grafico__topo">
                <h2 class="relatorio-grafico__titulo">Gráfico</h2>
            </div>
            <div class="relatorio-grafico__area">
                <canvas hidden></canvas>
                <p class="relatorio-grafico__estado relatorio-vazio--oculto"></p>
            </div>
        </section>
        <section class="relatorio-grafico" data-grafico>
            <div class="relatorio-grafico__topo">
                <h2 class="relatorio-grafico__titulo">Gráfico</h2>
            </div>
            <div class="relatorio-grafico__area">
                <canvas hidden></canvas>
                <p class="relatorio-grafico__estado relatorio-vazio--oculto"></p>
            </div>
        </section>
    </div>
    <p class="relatorio-graficos__erro relatorio-vazio--oculto" id="relatorio-graficos-erro" role="alert">
        <span data-graficos-mensagem>Não foi possível carregar os gráficos.</span>
        <button type="button" class="btn btn--ghost btn--sm" data-graficos-retry>Tentar novamente</button>
    </p>

    @include($parcial)

@endsection

@push('scripts')
    <script src="{{ asset('js/chart.umd.min.js') }}?v={{ filemtime(public_path('js/chart.umd.min.js')) }}"></script>
    <script src="{{ asset('js/relatorio-consulta.js') }}?v={{ filemtime(public_path('js/relatorio-consulta.js')) }}"></script>
@endpush
