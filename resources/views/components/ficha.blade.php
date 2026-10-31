@props([
    'titulo',
])

<div {{ $attributes->class(['card', 'ficha']) }}>
    <div class="ficha__head">
        <span class="card__title">{{ $titulo }}</span>
        @isset($status)
            {{ $status }}
        @endisset
    </div>
    <div class="ficha__body">
        @isset($resumo)
            <div class="ficha__resumo">{{ $resumo }}</div>
        @endisset
        @if($slot->isNotEmpty())
            <dl class="ficha__grid">
                {{ $slot }}
            </dl>
        @endif
    </div>
</div>
