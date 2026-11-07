@props([
    'tipo' => 'conta',
    'valor',
])

@php
    $mapas = [
        'venda' => [
            'confirmada'   => ['success', 'Confirmada'],
            'cancelada'    => ['error', 'Cancelada'],
            'em_andamento' => ['warning', 'Em andamento'],
        ],
        'conta' => [
            'paga'      => ['success', 'Paga'],
            'parcial'   => ['info', 'Parcial'],
            'cancelada' => ['error', 'Cancelada'],
            'aberta'    => ['warning', 'Aberta'],
        ],
        'entrada' => [
            'confirmada' => ['success', 'Confirmada'],
            'cancelada'  => ['error', 'Cancelada'],
            'rascunho'   => ['warning', 'Rascunho'],
        ],
        'orcamento' => [
            'aprovado'  => ['success', 'Aprovado'],
            'recusado'  => ['error', 'Recusado'],
            'cancelado' => ['error', 'Cancelado'],
            'pendente'  => ['warning', 'Pendente'],
        ],
        'comissao' => [
            'paga'     => ['success', 'Paga'],
            'pendente' => ['warning', 'Pendente'],
        ],
        'ativo' => [
            '1' => ['success', 'Ativo'],
            '0' => ['neutral', 'Inativo'],
        ],
        'ativa' => [
            '1' => ['success', 'Ativa'],
            '0' => ['neutral', 'Inativa'],
        ],
    ];

    $chave = is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;
    [$tom, $texto] = $mapas[$tipo][$chave] ?? ['neutral', $chave !== '' ? $chave : '—'];
@endphp

<x-badge :tom="$tom">{{ $texto }}</x-badge>
