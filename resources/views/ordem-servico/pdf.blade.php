@php
    $faixa = match ($os->situacao) {
        'concluida', 'faturada' => '',
        'cancelada' => 'status-strip--erro',
        default => 'status-strip--espera',
    };
    $cliente = $os->cliente;
    $documento = $cliente?->cpf ?: ($cliente?->cnpj ?: '—');
    $telefone = $cliente?->telefone ?: $cliente?->celular;
    $tecnico = $os->tecnico->nome ?? '—';
    $equipamento = trim(implode(' ', array_filter([$os->equipamento, $os->marca, $os->modelo]))) ?: '—';
@endphp
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Ordem de Serviço #{{ $os->numero }} - Visys</title>
    @include('pdf._estilos')
</head>

<body>
    <div class="page">

        <table class="header">
            <tr>
                <td style="width: 55%;">
                    <img src="{{ public_path('img/logo-v-sem-fundo.png') }}" alt="Visys" class="logo">
                    <div class="brand-sub">Sistemas &amp; Gestão Empresarial</div>
                </td>
                <td class="doc-badge" style="width: 45%;">
                    <span class="label">Ordem de Serviço</span>
                    <span class="numero">#{{ $os->numero }}</span>
                    <div class="data">{{ $os->data_abertura->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>

        <div class="status-strip {{ $faixa }}">
            <strong>{{ $os->situacaoLabel() }}</strong>
            &nbsp;·&nbsp; Previsão:
            <strong>{{ $os->data_previsao ? $os->data_previsao->format('d/m/Y') : '—' }}</strong>
            &nbsp;·&nbsp; Técnico: <strong>{{ $tecnico }}</strong>
        </div>

        <table class="info-section">
            <tr>
                <td class="info-box">
                    <div class="info-box-title">Dados do Cliente</div>
                    <div class="info-line"><strong>Nome:</strong> {{ $cliente->nome ?? '— Consumidor Final —' }}</div>
                    <div class="info-line"><strong>CPF/CNPJ:</strong> {{ $documento }}</div>
                    @if ($telefone)
                        <div class="info-line"><strong>Telefone:</strong> {{ $telefone }}</div>
                    @endif
                    @if ($cliente?->cidade)
                        <div class="info-line"><strong>Cidade:</strong>
                            {{ $cliente->cidade }}{{ $cliente->estado ? '/' . $cliente->estado : '' }}</div>
                    @endif
                </td>
                <td class="info-box-gap"></td>
                <td class="info-box">
                    <div class="info-box-title">Equipamento</div>
                    <div class="info-line"><strong>Equipamento:</strong> {{ $equipamento }}</div>
                    <div class="info-line"><strong>Nº de série:</strong> {{ $os->numero_serie ?: '—' }}</div>
                    <div class="info-line"><strong>Acessórios:</strong> {{ $os->acessorios ?: '—' }}</div>
                    @if ($os->garantia)
                        <div class="info-line"><strong>Garantia:</strong> {{ $os->garantia }}</div>
                    @endif
                    @if ($os->venda)
                        <div class="info-line"><strong>Venda gerada:</strong> #{{ $os->venda->numero }}</div>
                    @endif
                </td>
            </tr>
        </table>

        @foreach (['Defeito relatado' => $os->defeito_relatado, 'Diagnóstico' => $os->diagnostico, 'Serviço executado' => $os->servico_executado] as $titulo => $texto)
            @if (! empty($texto))
                <div class="obs-box">
                    <strong>{{ $titulo }}</strong>
                    {!! nl2br(e($texto)) !!}
                </div>
            @endif
        @endforeach

        @if ($os->itens->isNotEmpty())
            <div class="section-heading">Peças e Serviços</div>
            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 42%; text-align: left;">Produto / Serviço</th>
                        <th class="text-right" style="width: 10%;">Qtd.</th>
                        <th class="text-right" style="width: 16%;">Preço Unit.</th>
                        <th class="text-right" style="width: 14%;">Desconto</th>
                        <th class="text-right" style="width: 18%;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($os->itens as $item)
                        <tr>
                            <td>
                                <span class="item-nome">{{ $item->produto_nome }}</span>
                                @if ($item->produto_codigo)
                                    <span class="item-codigo">Cód. {{ $item->produto_codigo }}</span>
                                @endif
                            </td>
                            <td class="text-right">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                            <td class="text-right">R$&nbsp;{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                            <td class="text-right">R$&nbsp;{{ number_format($item->desconto, 2, ',', '.') }}</td>
                            <td class="text-right"><strong>R$&nbsp;{{ number_format($item->total, 2, ',', '.') }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <table class="totals-wrap">
                <tr>
                    <td style="width: 50%;"></td>
                    <td style="width: 50%;">
                        <div class="totals-box">
                            <table class="totals-inner">
                                <tr>
                                    <td>Subtotal</td>
                                    <td class="text-right">R$&nbsp;{{ number_format($os->subtotal, 2, ',', '.') }}</td>
                                </tr>
                                @if ($os->desconto > 0)
                                    <tr>
                                        <td class="accent">Desconto</td>
                                        <td class="text-right accent">− R$&nbsp;{{ number_format($os->desconto, 2, ',', '.') }}</td>
                                    </tr>
                                @endif
                                @if ($os->acrescimo > 0)
                                    <tr>
                                        <td>Acréscimo</td>
                                        <td class="text-right">+ R$&nbsp;{{ number_format($os->acrescimo, 2, ',', '.') }}</td>
                                    </tr>
                                @endif
                                <tr class="divider grand-total">
                                    <td>Total</td>
                                    <td class="text-right">R$&nbsp;{{ number_format($os->total, 2, ',', '.') }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        @endif

        @if (! empty($os->observacoes))
            <div class="obs-box">
                <strong>Observações</strong>
                {!! nl2br(e($os->observacoes)) !!}
            </div>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line">
                        <strong>{{ $cliente->nome ?? 'Cliente' }}</strong>
                        Cliente
                    </div>
                </td>
                <td class="gap"></td>
                <td>
                    <div class="sig-line">
                        <strong>{{ $tecnico }}</strong>
                        Técnico responsável
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer">
            Visys Sistemas
            <span class="dot">·</span>
            Documento gerado em {{ date('d/m/Y \à\s H:i:s') }}
            <span class="dot">·</span>
            Visys Gestão
        </div>

    </div>
</body>

</html>
