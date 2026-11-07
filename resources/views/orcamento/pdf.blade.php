@php
    $situacoes = [
        'pendente' => 'Em aberto',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];
    $situacaoLabel = $situacoes[$orcamento->situacao] ?? ($orcamento->situacao ?: '—');
    $cliente = $orcamento->cliente;
    $documento = $cliente?->cpf ?: ($cliente?->cnpj ?: '—');
    $telefone = $cliente?->telefone ?: $cliente?->celular;
    $vendedor = $orcamento->usuario->name ?? '—';
    $pagamento = $orcamento->forma_pagamento ?: '—';
    $numero = \App\Impressao\CupomDocumento::numero($orcamento->numero);
@endphp
<x-impressao.documento kind="Orçamento" :nome="'Nº '.$numero" :periodo="$orcamento->data_orcamento->format('d/m/Y')">
    <x-impressao.filtros :chips="array_values(array_filter([
        ['rotulo' => 'Situação', 'valor' => $situacaoLabel],
        ['rotulo' => 'Vendedor', 'valor' => $vendedor],
        ['rotulo' => 'Pagamento', 'valor' => $pagamento],
        ['rotulo' => 'Entrega', 'valor' => $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—'],
        $orcamento->venda_id && $orcamento->venda ? ['rotulo' => 'Venda', 'valor' => '#'.$orcamento->venda->numero] : null,
    ]))" />

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
                <div class="ficha-titulo">Orçamento</div>
                <p><span>Vendedor</span> {{ $vendedor }}</p>
                <p><span>Emissão</span> {{ $orcamento->data_orcamento->format('d/m/Y') }}</p>
                <p><span>Entrega</span> {{ $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—' }}</p>
                <p><span>Situação</span> <x-impressao.situacao :texto="$situacaoLabel" /></p>
            </td>
        </tr>
    </table>

    <div class="sec-title">Itens do orçamento</div>
    @include('impressao.tabela-itens', ['itens' => $orcamento->itens])

    <x-impressao.resumo :linhas="array_values(array_filter([
        ['label' => 'Subtotal', 'valor' => 'R$ '.number_format($orcamento->subtotal, 2, ',', '.')],
        (float) $orcamento->desconto > 0 ? ['label' => 'Desconto', 'valor' => '- R$ '.number_format($orcamento->desconto, 2, ',', '.')] : null,
        (float) $orcamento->acrescimo > 0 ? ['label' => 'Acréscimo', 'valor' => '+ R$ '.number_format($orcamento->acrescimo, 2, ',', '.')] : null,
        ['label' => 'Total', 'valor' => 'R$ '.number_format($orcamento->total, 2, ',', '.'), 'destaque' => true],
    ]))" />

    @if(!empty($orcamento->observacoes))
        <div class="nota">
            <strong>Observações</strong>
            {{ $orcamento->observacoes }}
        </div>
    @endif

    <x-impressao.assinaturas :linhas="[
        'Aceite do cliente · '.($cliente->nome ?? 'Nome e data'),
        'Responsável pelo orçamento · '.$vendedor,
    ]" />
</x-impressao.documento>
