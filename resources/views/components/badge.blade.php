@props([
    'tom' => 'neutral',
])

<span {{ $attributes->class(['badge', "badge--{$tom}"]) }}>{{ $slot }}</span>
