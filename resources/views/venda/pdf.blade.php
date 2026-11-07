@php
    $situacoes = [
        'em_andamento' => 'Em andamento',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
    ];
    $situacaoLabel = $situacoes[$venda->situacao] ?? ($venda->situacao ?: '—');
    $cliente = $venda->cliente;
    $documento = $cliente?->cpf ?: ($cliente?->cnpj ?: '—');
    $telefone = $cliente?->telefone ?: $cliente?->celular;
    $vendedor = $venda->funcionario->nome ?? $venda->usuario->name ?? '—';
    $pagamento = is_string($venda->forma_pagamento) ? $venda->forma_pagamento : ($venda->forma_pagamento->nome ?? null);
    $pagamento = $pagamento ?: '—';
    $numero = \App\Impressao\CupomDocumento::numero($venda->numero);
@endphp
<x-impressao.documento kind="Comprovante de venda" :nome="'Nº '.$numero" :periodo="$venda->data_venda->format('d/m/Y')">
    <x-impressao.filtros :chips="[
        ['rotulo' => 'Situação', 'valor' => $situacaoLabel],
        ['rotulo' => 'Vendedor', 'valor' => $vendedor],
        ['rotulo' => 'Pagamento', 'valor' => $pagamento],
        ['rotulo' => 'Entrega', 'valor' => $venda->data_entrega ? $venda->data_entrega->format('d/m/Y') : '—'],
    ]" />

    <table class="fichas">
        <tr>
            <td class="ficha">
                <div class="ficha-titulo">Cliente</div>
                <p><span>Nome</span> {{ $cliente->nome ?? 'Consumidor final' }}</p>
                <p><span>CPF/CNPJ</span> {{ $documento }}</p>
                @if($telefone)
                    <p><span>Telefone</span> {{ $telefone }}</p>
                @endif
                @if($cliente?->cidade)
                    <p><span>Cidade</span> {{ $cliente->cidade }}{{ $cliente->estado ? '/'.$cliente->estado : '' }}</p>
                @endif
            </td>
            <td class="ficha-gap"></td>
            <td class="ficha">
                <div class="ficha-titulo">Operação</div>
                <p><span>Vendedor</span> {{ $vendedor }}</p>
                <p><span>Pagamento</span> {{ $pagamento }}</p>
                <p><span>Entrega</span> {{ $venda->data_entrega ? $venda->data_entrega->format('d/m/Y') : '—' }}</p>
                <p><span>Situação</span> <x-impressao.situacao :texto="$situacaoLabel" /></p>
            </td>
        </tr>
    </table>

    <div class="sec-title">Itens da venda</div>
    @include('impressao.tabela-itens', ['itens' => $venda->itens])

    <x-impressao.resumo :linhas="array_values(array_filter([
        ['label' => 'Subtotal', 'valor' => 'R$ '.number_format($venda->subtotal, 2, ',', '.')],
        (float) $venda->desconto > 0 ? ['label' => 'Desconto', 'valor' => '- R$ '.number_format($venda->desconto, 2, ',', '.')] : null,
        (float) $venda->acrescimo > 0 ? ['label' => 'Acréscimo', 'valor' => '+ R$ '.number_format($venda->acrescimo, 2, ',', '.')] : null,
        ['label' => 'Total', 'valor' => 'R$ '.number_format($venda->total, 2, ',', '.'), 'destaque' => true],
    ]))" />

    @if($venda->observacoes)
        <div class="nota">
            <strong>Observações</strong>
            {{ $venda->observacoes }}
        </div>
    @endif

    <x-impressao.assinaturas :linhas="[
        'Cliente · '.($cliente->nome ?? 'Nome e data'),
        'Responsável pela venda · '.$vendedor,
    ]" />
</x-impressao.documento>
