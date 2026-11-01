@props([
    'coluna',
    'label',
    'filtros',
    'action',
    'inicial' => 'asc',
])

@php
    $ativa = ($filtros['ordem'] ?? '') === $coluna;
    $atual = $filtros['direcao'] ?? '';
    $proxima = ! $ativa ? $inicial : ($atual === 'asc' ? 'desc' : 'asc');
    $params = \App\Relatorios\Periodo::limpos(array_merge($filtros, [
        'ordem' => $coluna,
        'direcao' => $proxima,
    ]));
@endphp

<a href="{{ $action.'?'.http_build_query($params) }}">{{ $label }}</a>
