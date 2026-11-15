{{--
    Abas de filtro rápido (ex.: situação) acima da tabela. Cada aba é um link que troca só este
    parâmetro e mantém os demais filtros da URL (busca, período...). Volta para a página 1.
    Use junto com <x-filtros :manter="['situacao']"> para o formulário não perder a aba escolhida.
--}}
@props([
    'nome' => 'situacao',
    'opcoes' => [],
    'todas' => 'Todas',
    'rotulo' => 'Situação',
])

@php
    $atual = (string) request()->query($nome, '');
    $base = collect(request()->query())->except([$nome, 'page'])
        ->filter(fn ($v) => is_array($v) ? array_filter($v) !== [] : filled($v))
        ->all();
    $link = function (string $valor) use ($base, $nome) {
        $query = $valor === '' ? $base : $base + [$nome => $valor];

        return url()->current().($query ? '?'.http_build_query($query) : '');
    };
    $abas = ['' => $todas] + collect($opcoes)->mapWithKeys(fn ($r, $v) => [(string) $v => $r])->all();
@endphp

<nav {{ $attributes->class('abas-filtro') }} aria-label="Filtrar por {{ mb_strtolower($rotulo) }}">
    @foreach ($abas as $valor => $texto)
        @php($ativa = $atual === (string) $valor)
        <a href="{{ $link((string) $valor) }}" @class(['abas-filtro__item', 'is-ativa' => $ativa])
            @if ($ativa) aria-current="true" @endif>{{ $texto }}</a>
    @endforeach
</nav>
