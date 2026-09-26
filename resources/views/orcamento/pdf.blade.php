<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>Orçamento #{{ $orcamento->numero }} - Visys</title>
    <style>
        @font-face {
            font-family: 'DejaVuSans';
            src: local('DejaVu Sans'), local('DejaVuSans');
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            font-family: 'DejaVuSans', Arial, Helvetica, sans-serif;
            font-size: 11px;
            color: #1E293B;
            background: #ffffff;
            -webkit-print-color-adjust: exact;
        }

        .page {
            padding: 28px 32px 24px;
        }

        /* ── CABEÇALHO ── */
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 3px solid #1C2B42;
        }

        .header td {
            vertical-align: middle;
        }

        .logo {
            height: 44px;
            width: auto;
        }

        .brand-sub {
            font-size: 9px;
            color: #64748B;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 3px;
        }

        .doc-badge {
            text-align: right;
        }

        .doc-badge .label {
            display: inline-block;
            background: #163A5F;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 5px 14px;
            border-radius: 3px;
        }

        .doc-badge .numero {
            display: block;
            font-size: 20px;
            font-weight: bold;
            color: #1C2B42;
            margin-top: 5px;
        }

        .doc-badge .data {
            font-size: 10px;
            color: #64748B;
            margin-top: 2px;
        }

        /* ── FAIXA DE VALIDADE ── */
        .status-strip {
            width: 100%;
            background: #FFF8F0;
            border: 1px solid #FDDCB5;
            border-radius: 4px;
            padding: 7px 14px;
            margin-bottom: 20px;
            font-size: 10px;
            color: #92400E;
        }

        .status-strip strong {
            font-size: 11px;
        }

        /* ── INFO BOXES ── */
        .info-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 22px;
        }

        .info-box {
            width: 48%;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 12px 14px;
            vertical-align: top;
            background: #F8FAFC;
        }

        .info-box-gap {
            width: 4%;
        }

        .info-box-title {
            font-size: 9px;
            font-weight: bold;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            padding-bottom: 7px;
            margin-bottom: 7px;
            border-bottom: 1px solid #E2E8F0;
        }

        .info-line {
            margin-bottom: 5px;
            line-height: 1.45;
            color: #334155;
        }

        .info-line strong {
            color: #1E293B;
        }

        /* ── SECTION HEADING ── */
        .section-heading {
            font-size: 9px;
            font-weight: bold;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            margin-bottom: 6px;
            padding-left: 2px;
        }

        /* ── ITENS ── */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }

        table.items thead th {
            background: #1C2B42;
            color: #ffffff;
            padding: 9px 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        table.items thead th:first-child {
            border-radius: 3px 0 0 0;
        }

        table.items thead th:last-child {
            border-radius: 0 3px 0 0;
        }

        table.items tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #E2E8F0;
            vertical-align: middle;
            color: #334155;
        }

        table.items tbody tr:last-child td {
            border-bottom: none;
        }

        table.items tbody tr:nth-child(even) td {
            background: #F8FAFC;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .item-nome {
            font-weight: bold;
            color: #1E293B;
        }

        /* ── TOTAIS ── */
        .totals-wrap {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        .totals-box {
            width: 280px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            background: #F8FAFC;
            padding: 14px 16px;
            margin-left: auto;
        }

        .totals-inner {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-inner td {
            padding: 4px 0;
            font-size: 11px;
            color: #475569;
        }

        .totals-inner tr.divider td {
            border-top: 1px solid #CBD5E1;
            padding-top: 8px;
        }

        .totals-inner tr.grand-total td {
            font-size: 15px;
            font-weight: bold;
            color: #1C2B42;
            padding-top: 8px;
        }

        .accent {
            color: #E35A12;
        }

        /* ── CONDIÇÕES / OBSERVAÇÕES ── */
        .obs-box {
            margin-top: 22px;
            border: 1px solid #E2E8F0;
            border-radius: 4px;
            padding: 10px 14px;
            background: #FFFBF7;
            font-size: 10px;
            color: #64748B;
            line-height: 1.6;
        }

        .obs-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #94A3B8;
            margin-bottom: 5px;
        }

        /* ── ASSINATURAS ── */
        .signatures {
            margin-top: 52px;
            width: 100%;
            border-collapse: collapse;
        }

        .signatures td {
            width: 40%;
            text-align: center;
            vertical-align: bottom;
        }

        .signatures .gap {
            width: 20%;
        }

        .sig-line {
            border-top: 1px solid #94A3B8;
            padding-top: 7px;
            font-size: 10px;
            color: #64748B;
        }

        .sig-line strong {
            display: block;
            color: #1E293B;
            font-size: 10px;
        }

        /* ── RODAPÉ ── */
        .footer {
            margin-top: 36px;
            padding-top: 10px;
            border-top: 1px solid #E2E8F0;
            font-size: 9px;
            color: #94A3B8;
            text-align: center;
            line-height: 1.6;
        }

        .footer .dot {
            margin: 0 4px;
        }
    </style>
</head>

<body>
    <div class="page">

        <!-- ── CABEÇALHO ── -->
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

        <!-- ── FAIXA DE VALIDADE ── -->
        <div class="status-strip">
            <strong>Orçamento em aberto</strong>
            &nbsp;·&nbsp; Válido até: <strong>{{ $orcamento->validade ?? '—' }}</strong>
            &nbsp;·&nbsp; Vendedor: <strong>{{ $orcamento->usuario->name ?? '—' }}</strong>
        </div>

        <!-- ── DADOS ── -->
        <table class="info-section">
            <tr>
                <td class="info-box">
                    <div class="info-box-title">Dados do Cliente</div>
                    <div class="info-line"><strong>Nome:</strong> {{ $orcamento->cliente->nome ?? '—' }}</div>
                    <div class="info-line"><strong>CPF/CNPJ:</strong>
                        {{ $orcamento->cliente->cpf ?: $orcamento->cliente->cnpj ?? '—' }}</div>
                </td>
                <td class="info-box-gap"></td>
                <td class="info-box">
                    <div class="info-box-title">Detalhes do Orçamento</div>
                    <div class="info-line"><strong>Vendedor:</strong> {{ $orcamento->usuario->name ?? '—' }}</div>
                    <div class="info-line"><strong>Emissão:</strong> {{ $orcamento->data_orcamento->format('d/m/Y') }}
                    </div>
                    <div class="info-line"><strong>Validade:</strong> {{ $orcamento->validade ?? '—' }}</div>
                    <div class="info-line"><strong>Status:</strong> Em aberto</div>
                </td>
            </tr>
        </table>

        <!-- ── ITENS ── -->
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
                @foreach ($orcamento->itens as $item)
                    <tr>
                        <td><span class="item-nome">{{ $item->produto_nome }}</span></td>
                        <td class="text-right">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                        <td class="text-right">R$&nbsp;{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                        <td class="text-right">
                            @if ($item->desconto > 0)
                                <span class="accent">R$&nbsp;{{ number_format($item->desconto, 2, ',', '.') }}</span>
                            @else
                                <span style="color: #E66A35;">R$&nbsp;0,00</span>
                            @endif
                        </td>
                        <td class="text-right"><strong>R$&nbsp;{{ number_format($item->total, 2, ',', '.') }}</strong>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- ── TOTAIS ── -->
        <table class="totals-wrap">
            <tr>
                <td style="width: 50%;"></td>
                <td style="width: 50%;">
                    <div class="totals-box">
                        <table class="totals-inner">
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-right">R$&nbsp;{{ number_format($orcamento->subtotal, 2, ',', '.') }}
                                </td>
                            </tr>
                            @if ($orcamento->desconto > 0)
                                <tr>
                                    <td class="accent">Desconto</td>
                                    <td class="text-right accent">−
                                        R$&nbsp;{{ number_format($orcamento->desconto, 2, ',', '.') }}</td>
                                </tr>
                            @endif
                            @if ($orcamento->acrescimo > 0)
                                <tr>
                                    <td>Acréscimo</td>
                                    <td class="text-right">+
                                        R$&nbsp;{{ number_format($orcamento->acrescimo, 2, ',', '.') }}</td>
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

        <!-- ── OBSERVAÇÕES (opcional — remova o bloco se não usar) ── -->
        @if (!empty($orcamento->observacoes))
            <div class="obs-box">
                <div class="obs-title">Observações</div>
                {{ $orcamento->observacoes }}
            </div>
        @endif

        <!-- ── ASSINATURAS ── -->
        <table class="signatures">
            <tr>
                <td>
                    <div class="sig-line">
                        <strong>{{ $orcamento->cliente->nome ?? 'Cliente' }}</strong>
                        Aprovação do orçamento
                    </div>
                </td>
            </tr>
        </table>

        <!-- ── RODAPÉ ── -->
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
