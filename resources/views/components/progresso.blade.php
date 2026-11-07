@props([
    'valor' => null,
    'mensagem' => 'Processando...',
])

@php
    $definido = is_numeric($valor);
    $percentual = $definido ? max(0, min(100, (float) $valor)) : null;
@endphp

<div {{ $attributes->class(['erp-progresso', 'is-indeterminado' => ! $definido]) }}
    role="progressbar"
    aria-valuemin="0"
    aria-valuemax="100"
    @if ($definido)
        aria-valuenow="{{ (int) round($percentual) }}"
    @else
        aria-valuetext="{{ $mensagem }}"
    @endif>
    <div class="erp-progresso__trilha">
        <div class="erp-progresso__valor" @if ($definido) style="width: {{ $percentual }}%" @endif></div>
    </div>
    <p class="erp-progresso__msg">
        <span class="erp-progresso__texto">{{ $mensagem }}</span>
        @if ($definido)
            <span class="erp-progresso__pct">{{ (int) round($percentual) }}%</span>
        @endif
    </p>
</div>
