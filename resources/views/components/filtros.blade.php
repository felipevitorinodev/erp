{{--
    Barra de filtros das listagens (GET).
    - Desktop: todos os campos numa faixa, botões no fim.
    - Celular: fica visível só o campo principal (busca); os demais abrem no botão "Filtros",
      que mostra quantos filtros extras estão aplicados. Abre sozinho se não houver campo principal.
    - "Limpar" só aparece quando há algum filtro na URL.
    - "manter": parâmetros escolhidos fora do formulário (abas de situação) que o envio preserva.
--}}
@props([
    'action',
    'limpar' => null,
    'principal' => 'busca',
    'manter' => [],
])

@php
    $ignorar = ['page', 'ordem', 'direcao', 'por_pagina'];
    $preenchidos = collect(request()->query())
        ->except($ignorar)
        ->filter(fn ($valor) => is_array($valor) ? array_filter($valor) !== [] : filled($valor));
    $extras = $preenchidos->except(array_merge([$principal], (array) $manter))->count();
@endphp

<div {{ $attributes->class(['card', 'filtros', 'mb-2']) }} data-filtros>
    <div class="card__body">
        <form method="GET" action="{{ $action }}" class="form-grid form-grid--col-3 filtros__form" role="search">
            @foreach ((array) $manter as $campo)
                @if (filled(request()->query($campo)) && ! is_array(request()->query($campo)))
                    <input type="hidden" name="{{ $campo }}" value="{{ request()->query($campo) }}">
                @endif
            @endforeach
            {{ $slot }}
            <div class="form-group filtros__acoes">
                <div class="filter-actions">
                    <button type="button" class="btn btn--ghost btn--sm filtros__alternar" data-filtros-alternar
                        aria-expanded="false">
                        <x-icon name="sliders" />
                        <span>Filtros</span>
                        @if ($extras > 0)
                            <span class="filtros__contagem" aria-label="{{ $extras }} aplicado(s)">{{ $extras }}</span>
                        @endif
                    </button>
                    <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                    @if ($preenchidos->isNotEmpty())
                        <a href="{{ $limpar ?? $action }}" class="btn btn--ghost btn--sm">Limpar</a>
                    @endif
                </div>
            </div>
        </form>
    </div>
</div>
