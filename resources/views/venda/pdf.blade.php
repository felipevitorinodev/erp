<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Venda #{{ $venda->numero }}</title>
    <style>
        @font-face {
            font-family: 'DejaVuSans';
            src: local('DejaVu Sans'), local('DejaVuSans');
        }
        :root {
            --color-primary: #163A5F;
            --color-accent: #E35A12;
            --color-surface: #FFFFFF;
            --color-bg: #EEF2F6;
            --color-border: #D5DCE5;
            --color-text: #152232;
            --color-muted: #7A8B9E;
            --primary-soft: #E8EEF5;
        }
        html, body { font-family: DejaVuSans, Arial, sans-serif; font-size: 12px; color: var(--color-text); background: #fff; }
        .pdf-header {
            border-bottom: 6px solid var(--color-accent);
            padding: 12px 18px 10px 18px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .pdf-header .brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .logo {
            height: 46px;
        }
        .doc-title {
            margin-left: auto;
            text-align: right;
        }
        .doc-title h1 {
            margin: 0;
            font-size: 16px;
            color: var(--color-primary);
            letter-spacing: 0.02em;
        }
        .doc-meta {
            font-size: 11px;
            color: var(--color-muted);
        }
        .section {
            padding: 14px 18px;
        }
        .info {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .info .col {
            width: 48%;
            font-size: 12px;
            color: var(--color-text);
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 12px;
        }
        table.items thead th {
            background: var(--primary-soft);
            color: var(--color-primary);
            text-align: left;
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            font-weight: 700;
            font-size: 11px;
        }
        table.items tbody td {
            padding: 8px 10px;
            border: 1px solid var(--color-border);
            vertical-align: middle;
        }
        table.items tbody tr:nth-child(even) {
            background: #FBFCFD;
        }
        .text-right { text-align: right; }
        .totals {
            width: 320px;
            margin-left: auto;
            margin-top: 12px;
            border: 1px solid var(--color-border);
            padding: 10px;
            background: #F7F9FC;
            font-size: 12px;
        }
        .totals .row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid var(--color-border); }
        .totals .row.total { font-weight:800; border-bottom: none; color: var(--color-primary); font-size: 14px; padding-top:10px; }
        .footer {
            position: absolute;
            bottom: 18px;
            left: 18px;
            right: 18px;
            font-size: 11px;
            color: var(--color-muted);
        }
    </style>
</head>
<body>
    <div class="pdf-header" role="banner">
        <div class="brand">
            <img src="{{ public_path('img/logo-sem-fundo.png') }}" alt="Visys" class="logo">
            <div>
                <div style="font-weight:800; color:var(--color-primary);">Visys · Gestão empresarial</div>
                <div style="font-size:11px; color:var(--color-muted);">Comprovante de venda</div>
            </div>
        </div>

        <div class="doc-title" role="doc-title">
            <h1>Venda #{{ $venda->numero }}</h1>
            <div class="doc-meta">{{ $venda->data_venda->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="section">
        <div class="info" role="details">
            <div class="col">
                <strong>Cliente</strong><br>
                {{ $venda->cliente->nome ?? '—' }}<br>
                {{ $venda->cliente->documento ?? '' }}<br>
                {{ $venda->cliente->endereco ?? '' }}
            </div>
            <div class="col">
                <strong>Vendedor</strong><br>
                {{ $venda->usuario->name ?? '—' }}<br>
                <br>
                <strong>Pagamento:</strong> {{ $venda->forma_pagamento->nome ?? ($venda->forma_pagamento_text ?? '—') }}
            </div>
        </div>

        <table class="items" role="table">
            <thead>
                <tr>
                    <th>Produto</th>
                    <th class="text-right">Qtd.</th>
                    <th class="text-right">Preço Unit.</th>
                    <th class="text-right">Desconto</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venda->itens as $item)
                <tr>
                    <td>{{ $item->produto_nome }}</td>
                    <td class="text-right">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                    <td class="text-right">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals" role="complementary" aria-label="Totais">
            <div class="row"><div>Subtotal</div><div>R$ {{ number_format($venda->subtotal,2,',','.') }}</div></div>
            <div class="row"><div>Desconto</div><div>R$ {{ number_format($venda->desconto,2,',','.') }}</div></div>
            <div class="row"><div>Acréscimo</div><div>R$ {{ number_format($venda->acrescimo,2,',','.') }}</div></div>
            <div class="row total"><div>Total</div><div>R$ {{ number_format($venda->total,2,',','.') }}</div></div>
        </div>
    </div>

    <div class="footer">
        <div>Visys · {{ date('Y') }} · {{ config('app.name', '') }}</div>
    </div>
</body>
</html>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Venda #{{ $venda->numero }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #222; }
        .header { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px; }
        th { background: #f7f7f7; text-align: left; }
        .text-right { text-align: right; }
        .totals { margin-top: 12px; width: 100%; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Venda #{{ $venda->numero }}</h2>
        <div>{{ $venda->data_venda->format('d/m/Y') }}</div>
    </div>

    <div>
        <strong>Cliente:</strong> {{ $venda->cliente->nome ?? '—' }}<br/>
        <strong>Vendedor:</strong> {{ $venda->usuario->name ?? '—' }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Produto</th>
                <th class="text-right">Qtd.</th>
                <th class="text-right">Preço Unit.</th>
                <th class="text-right">Desconto</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venda->itens as $item)
            <tr>
                <td>{{ $item->produto_nome }}</td>
                <td class="text-right">{{ number_format($item->quantidade, 3, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($item->desconto, 2, ',', '.') }}</td>
                <td class="text-right">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr><td>Subtotal</td><td class="text-right">R$ {{ number_format($venda->subtotal,2,',','.') }}</td></tr>
            <tr><td>Desconto</td><td class="text-right">R$ {{ number_format($venda->desconto,2,',','.') }}</td></tr>
            <tr><td>Acréscimo</td><td class="text-right">R$ {{ number_format($venda->acrescimo,2,',','.') }}</td></tr>
            <tr><th>Total</th><th class="text-right">R$ {{ number_format($venda->total,2,',','.') }}</th></tr>
        </table>
    </div>
</body>
</html>

