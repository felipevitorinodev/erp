@php
    $situacoes = [
        'pendente' => 'Pendente',
        'aprovado' => 'Aprovado',
        'recusado' => 'Recusado',
        'cancelado' => 'Cancelado',
    ];
    $situacaoLabel = $situacoes[$orcamento->situacao] ?? ($orcamento->situacao ?: '—');
    $faixa = match ($orcamento->situacao) {
        'aprovado' => '',
        'recusado', 'cancelado' => 'status-strip--erro',
        default => 'status-strip--espera',
    };
    $cliente = $orcamento->cliente;
    $documento = $cliente?->cpf ?: ($cliente?->cnpj ?: '—');
    $telefone = $cliente?->telefone ?: $cliente?->celular;
    $vendedor = $orcamento->usuario->name ?? '—';
    $pagamento = $orcamento->forma_pagamento ?: '—';
@endphp
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Orçamento #{{ $orcamento->numero }} - Visys</title>
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
                    <span class="label">Orçamento</span>
                    <span class="numero">#{{ $orcamento->numero }}</span>
                    <div class="data">{{ $orcamento->data_orcamento->format('d/m/Y') }}</div>
                </td>
            </tr>
        </table>

        <div class="status-strip {{ $faixa }}">
            <strong>{{ $situacaoLabel }}</strong>
            &nbsp;·&nbsp; Entrega:
            <strong>{{ $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—' }}</strong>
            &nbsp;·&nbsp; Vendedor: <strong>{{ $vendedor }}</strong>
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
                    <div class="info-box-title">Detalhes do Orçamento</div>
                    <div class="info-line"><strong>Vendedor:</strong> {{ $vendedor }}</div>
                    <div class="info-line"><strong>Emissão:</strong> {{ $orcamento->data_orcamento->format('d/m/Y') }}</div>
                    <div class="info-line"><strong>Entrega:</strong>
                        {{ $orcamento->data_entrega ? $orcamento->data_entrega->format('d/m/Y') : '—' }}</div>
                    <div class="info-line"><strong>Pagamento:</strong> {{ $pagamento }}</div>
                    <div class="info-line"><strong>Status:</strong> {{ $situacaoLabel }}</div>
                    @if ($orcamento->venda_id && $orcamento->venda)
                        <div class="info-line"><strong>Venda gerada:</strong> #{{ $orcamento->venda->numero }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="section-heading">Itens do Orçamento</div>
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
                @forelse ($orcamento->itens as $item)
                    <tr>
                        <td>
                            <span class="item-nome">{{ $item->produto_nome }}</span>
                            @if ($item->produto_codigo)
                                <span class="item-codigo">Cód. {{ $item->produto_codigo }}</span>
                            @endif
                        </td>
                        <td class="text-right">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                        <td class="text-right">R$&nbsp;{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                        <td class="text-right">
                            <span style="color:#E66A35;">R$&nbsp;{{ number_format($item->desconto, 2, ',', '.') }}</span>
                        </td>
                        <td class="text-right"><strong>R$&nbsp;{{ number_format($item->total, 2, ',', '.') }}</strong></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center">Nenhum item neste orçamento.</td>
                    </tr>
                @endforelse
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
                                <td class="text-right">R$&nbsp;{{ number_format($orcamento->subtotal, 2, ',', '.') }}</td>
                            </tr>
                            @if ($orcamento->desconto > 0)
                                <tr>
                                    <td class="accent">Desconto</td>
                                    <td class="text-right accent">− R$&nbsp;{{ number_format($orcamento->desconto, 2, ',', '.') }}</td>
                                </tr>
                            @endif
                            @if ($orcamento->acrescimo > 0)
                                <tr>
                                    <td>Acréscimo</td>
                                    <td class="text-right">+ R$&nbsp;{{ number_format($orcamento->acrescimo, 2, ',', '.') }}</td>
                                </tr>
                            @endif
                            <tr class="divider grand-total">
                                <td>Total</td>
                                <td class="text-right">R$&nbsp;{{ number_format($orcamento->total, 2, ',', '.') }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        @if (!empty($orcamento->observacoes))
            <div class="obs-box">
                <strong>Observações</strong>
                {{ $orcamento->observacoes }}
            </div>
        @endif

        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line">
                        <strong>{{ $cliente->nome ?? 'Cliente' }}</strong>
                        Aprovação do orçamento
                    </div>
                </td>
                <td class="gap"></td>
                <td>
                    <div class="sig-line">
                        <strong>{{ $vendedor }}</strong>
                        Vendedor
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
