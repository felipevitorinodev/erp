{{--
    Ilustração em SVG da pasta partials/ilustracoes.
    - tamanho: sm (dentro de tabela), md ou lg (tela de erro).
    - interativa: acompanha o ponteiro e reage ao clique (public/js/ilustracoes.js).
    Decorativa: o texto ao lado é que explica a situação.
--}}
@props([
    'nome',
    'tamanho' => 'md',
    'interativa' => false,
])

<span {{ $attributes->class(['ilustracao', 'ilustracao--'.$tamanho, 'ilustracao--'.$nome]) }}
      @if ($interativa) data-ilustracao-interativa @endif
      aria-hidden="true">
    @includeFirst(['partials.ilustracoes.'.$nome, 'partials.ilustracoes.generico'])
</span>
