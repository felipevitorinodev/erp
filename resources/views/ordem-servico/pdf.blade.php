@php
    $cliente = $os->cliente;
    $documento = $cliente?->cpf ?: ($cliente?->cnpj ?: '—');
    $telefone = $cliente?->telefone ?: $cliente?->celular;
    $tecnico = $os->tecnico->nome ?? '—';
    $equipamento = trim(implode(' ', array_filter([$os->equipamento, $os->marca, $os->modelo]))) ?: '—';
    $numero = \App\Impressao\CupomDocumento::numero($os->numero);
    $situacaoLabel = $os->situacaoLabel();
@endphp
<x-impressao.documento kind="Ordem de serviço" :nome="'Nº '.$numero" :periodo="$os->data_abertura->format('d/m/Y')">
    <table class="fichas">
        <tr>
            <td class="ficha">
                <div class="ficha-titulo">Cliente</div>
                <p><span>Nome</span> {{ $cliente->nome ?? 'Consumidor final' }}</p>
                @if($documento !== '—')
                    <p><span>CPF/CNPJ</span> {{ $documento }}</p>
                @endif
                @if($telefone)
                    <p><span>Telefone</span> {{ $telefone }}</p>
                @endif
                @if($cliente?->cidade)
                    <p><span>Cidade</span> {{ $cliente->cidade }}{{ $cliente->estado ? '/'.$cliente->estado : '' }}</p>
                @endif
            </td>
            <td class="ficha-gap"></td>
            <td class="ficha">
                <div class="ficha-titulo">Equipamento</div>
                @if($equipamento !== '—')
                    <p><span>Equipamento</span> {{ $equipamento }}</p>
                @endif
                @if($os->numero_serie)
                    <p><span>Nº de série</span> {{ $os->numero_serie }}</p>
                @endif
                @if($os->acessorios)
                    <p><span>Acessórios</span> {{ $os->acessorios }}</p>
                @endif
                @if($os->garantia)
                    <p><span>Garantia</span> {{ $os->garantia }}</p>
                @endif
                @if($tecnico !== '—')
                    <p><span>Técnico</span> {{ $tecnico }}</p>
                @endif
                @if($os->data_previsao)
                    <p><span>Previsão</span> {{ $os->data_previsao->format('d/m/Y') }}</p>
                @endif
                @if($os->venda)
                    <p><span>Venda</span> #{{ $os->venda->numero }}</p>
                @endif
                <p><span>Situação</span> <x-impressao.situacao :texto="$situacaoLabel" /></p>
            </td>
        </tr>
    </table>

    @foreach(['Defeito relatado' => $os->defeito_relatado, 'Diagnóstico' => $os->diagnostico, 'Serviço executado' => $os->servico_executado] as $tituloNota => $texto)
        @if(!empty($texto))
            <div class="nota">
                <strong>{{ $tituloNota }}</strong>
                {!! nl2br(e($texto)) !!}
            </div>
        @endif
    @endforeach

    <div class="sec-title">Peças e serviços</div>
    @include('impressao.tabela-itens', ['itens' => $os->itens])

    <x-impressao.resumo :linhas="array_values(array_filter([
        ['label' => 'Subtotal', 'valor' => 'R$ '.number_format($os->subtotal, 2, ',', '.')],
        (float) $os->desconto > 0 ? ['label' => 'Desconto', 'valor' => '- R$ '.number_format($os->desconto, 2, ',', '.')] : null,
        (float) $os->acrescimo > 0 ? ['label' => 'Acréscimo', 'valor' => '+ R$ '.number_format($os->acrescimo, 2, ',', '.')] : null,
        ['label' => 'Total', 'valor' => 'R$ '.number_format($os->total, 2, ',', '.'), 'destaque' => true],
    ]))" />

    @if(!empty($os->observacoes))
        <div class="nota">
            <strong>Observações</strong>
            {!! nl2br(e($os->observacoes)) !!}
        </div>
    @endif

    <x-impressao.assinaturas :linhas="[
        'Técnico responsável · '.$tecnico,
        'Cliente · ciente do serviço realizado',
    ]" />
</x-impressao.documento>
