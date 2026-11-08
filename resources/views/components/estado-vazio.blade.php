{{--
    Estado vazio padrão das listas.
    - Sem registro: ilustração, título, texto de apoio e o botão de criar (quando houver).
    - Com filtro aplicado (filtravel): explica que a busca não achou nada e oferece limpar os filtros.
--}}
@props([
    'titulo',
    'texto' => null,
    'ilustracao' => 'vazio',
    'acao' => null,
    'acaoTexto' => null,
    'filtravel' => false,
    'tamanho' => 'sm',
])

@php
    $ignorar = ['page', 'ordem', 'direcao'];
    $filtrado = $filtravel && collect(request()->query())
        ->except($ignorar)
        ->filter(fn ($valor) => is_array($valor) ? array_filter($valor) !== [] : filled($valor))
        ->isNotEmpty();
@endphp

<div {{ $attributes->class(['estado-vazio', 'estado-vazio--'.$tamanho]) }}>
    <x-ilustracao :nome="$filtrado ? 'sem-resultado' : $ilustracao" :tamanho="$tamanho" interativa />

    @if ($filtrado)
        <p class="estado-vazio__titulo">Nada encontrado com esses filtros</p>
        <p class="estado-vazio__texto">Confira o que foi digitado ou limpe os filtros para ver tudo.</p>
        <a href="{{ url()->current() }}" class="btn btn--ghost btn--sm">Limpar filtros</a>
    @else
        <p class="estado-vazio__titulo">{{ $titulo }}</p>
        @if ($texto)
            <p class="estado-vazio__texto">{{ $texto }}</p>
        @endif
        @if ($acao && $acaoTexto)
            <a href="{{ $acao }}" class="btn btn--primary btn--sm">{{ $acaoTexto }}</a>
        @endif
    @endif
</div>
