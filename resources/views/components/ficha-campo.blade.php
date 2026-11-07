@props([
    'label',
    'largo' => false,
    'mono' => false,
])

<div {{ $attributes->class(['ficha__item', 'ficha__item--largo' => $largo, 'ficha__item--mono' => $mono]) }}>
    <dt>{{ $label }}</dt>
    <dd>{{ $slot }}</dd>
</div>
