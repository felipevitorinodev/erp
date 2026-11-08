{{--
    Contagem regressiva com anel de progresso (public/js/ilustracoes.js).
    - acao "liberar": ao zerar, habilita o botão indicado em "botao" (seletor CSS).
    - acao "recarregar": ao zerar, recarrega a página.
--}}
@props([
    'segundos',
    'acao' => 'liberar',
    'botao' => null,
])

<div {{ $attributes->class('contagem') }}
     data-contagem="{{ (int) $segundos }}"
     data-contagem-acao="{{ $acao }}"
     @if ($botao) data-contagem-botao="{{ $botao }}" @endif>
    <span class="contagem__anel" aria-hidden="true">
        <svg viewBox="0 0 36 36" focusable="false">
            <circle class="contagem__trilho" cx="18" cy="18" r="15.5"/>
            <circle class="contagem__progresso" data-contagem-anel cx="18" cy="18" r="15.5" pathLength="100"/>
        </svg>
        <span class="contagem__numero" data-contagem-numero>{{ (int) $segundos }}</span>
    </span>
    <span class="contagem__texto" role="status" data-contagem-texto>{{ $slot }}</span>
</div>
