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

    <div class="rp-toolbar">
        <label class="rp-search">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
            <span class="sr-only">Buscar relatório</span>
            <input
                type="search"
                id="relatorios-busca"
                placeholder="Buscar relatório"
                autocomplete="off"
                enterkeyhint="search"
            >
        </label>
        <div class="rp-chips" role="group" aria-label="Filtrar por módulo">
            <button type="button" class="rp-chip is-on" data-filtro-modulo="">Todos</button>
            @foreach ($categorias as $categoria)
                <button type="button" class="rp-chip" data-filtro-modulo="{{ $categoria['slug'] }}">{{ $categoria['titulo'] }}</button>
            @endforeach
        </div>
    </div>

    <div class="rp-grid">
        @foreach ($categorias as $categoria)
            <x-relatorios.modulo :categoria="$categoria" />
        @endforeach
    </div>

    <p class="rp-vazio rp-vazio--oculto" id="relatorios-vazio" role="status">
        Nenhum relatório encontrado para essa busca.
    </p>

@endsection

@push('scripts')
    <script src="{{ asset('js/relatorios-hub.js') }}?v={{ filemtime(public_path('js/relatorios-hub.js')) }}"></script>
@endpush
