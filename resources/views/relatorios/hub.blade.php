@extends('layouts.app')

@section('title', 'Relatórios')
@section('page_title', 'Relatórios')

@php
    $totalModulos = count($categorias);
    $totalRelatorios = collect($categorias)->sum(fn ($categoria) => count($categoria['relatorios']));
@endphp

@section('breadcrumb')
    <span
        id="relatorios-resumo"
        data-modulos="{{ $totalModulos }}"
        data-relatorios="{{ $totalRelatorios }}"
    >{{ $totalModulos }} {{ $totalModulos === 1 ? 'módulo' : 'módulos' }} · {{ $totalRelatorios }} {{ $totalRelatorios === 1 ? 'relatório' : 'relatórios' }}</span>
@endsection

@section('content')
    {{-- Catálogo: busca e filtro em public/js/relatorios-hub.js; visual em app.css (.rp-*). --}}
    <div class="rp-toolbar">
        <label class="rp-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
            <span class="sr-only">Buscar relatório</span>
            <input type="search" id="relatorios-busca" placeholder="Buscar relatório" autocomplete="off" enterkeyhint="go">
        </label>

        <div class="rp-chips" role="group" aria-label="Filtrar por módulo">
            <button type="button" class="rp-chip is-on" data-filtro-modulo="" aria-pressed="true">
                Todos <span class="rp-chip__qtd">{{ $totalRelatorios }}</span>
            </button>
            @foreach ($categorias as $categoria)
                <button type="button" class="rp-chip" data-filtro-modulo="{{ $categoria['slug'] }}" aria-pressed="false">
                    {{ $categoria['titulo'] }} <span class="rp-chip__qtd">{{ count($categoria['relatorios']) }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- Preenchido pelo navegador com os últimos relatórios abertos (fica só neste aparelho). --}}
    <section class="rp-recentes" id="relatorios-recentes" data-usuario="{{ auth()->id() }}" aria-labelledby="rp-recentes-titulo" hidden>
        <h2 class="rp-recentes__titulo" id="rp-recentes-titulo">Abertos recentemente:</h2>
        <div class="rp-recentes__lista" id="relatorios-recentes-lista"></div>
    </section>

    <div class="rp-modulos">
        @foreach ($categorias as $categoria)
            <x-relatorios.modulo :categoria="$categoria" />
        @endforeach
    </div>

    <div class="rp-vazio rp-vazio--oculto" id="relatorios-vazio" role="status">
        <p class="rp-vazio__titulo">Nenhum relatório encontrado para essa busca.</p>
        <p class="rp-vazio__texto">Tente outra palavra, como “estoque”, “cliente” ou “vencido”.</p>
        <button type="button" class="btn btn--ghost btn--sm" data-limpar-busca>Limpar busca</button>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('js/relatorios-hub.js') }}?v={{ filemtime(public_path('js/relatorios-hub.js')) }}"></script>
@endpush
