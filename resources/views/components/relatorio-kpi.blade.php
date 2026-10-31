@props([
    'label',
    'valor',
    'detalhe' => null,
    'tom' => 'primary',
    'icone' => null,
    'destaque' => false,
])

<div {{ $attributes->class(['kpi-card', "kpi-card--{$tom}", 'kpi-card--destaque' => $destaque]) }}>
    <div class="kpi-card__head">
        <span class="kpi-card__label">{{ $label }}</span>
        @if($icone)
            <span class="kpi-card__icon"><x-icon :name="$icone" :size="15" /></span>
        @endif
    </div>
    <span class="kpi-card__value">{{ $valor }}</span>
    @if($detalhe)
        <span class="kpi-card__sub">{{ $detalhe }}</span>
    @endif
</div>
