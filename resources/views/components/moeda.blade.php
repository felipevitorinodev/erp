@props([
    'valor' => 0,
])

R$ {{ number_format((float) $valor, 2, ',', '.') }}
