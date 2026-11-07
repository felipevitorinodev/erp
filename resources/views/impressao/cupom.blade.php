@php
    $empresa = \App\Impressao\EmpresaDocumento::atual();
    $pdf = $pdf ?? false;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $dados['titulo'] }}</title>
    @unless($pdf)
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&display=swap">
    @endunless
    @include('impressao.estilos', ['pdf' => $pdf])
    @if($pdf)
        <style>
            @page { margin: 0; }
            html, body { height: auto; }
            body { margin: 14pt 12pt 12pt 12pt; background: #fff; font-family: 'DejaVu Sans Mono', monospace; }
            .cupom, .cupom * { font-family: 'DejaVu Sans Mono', monospace; }
            .cupom { width: 100%; margin: 0; padding: 0; box-shadow: none; border: 0; page-break-inside: avoid; }
            .c-title, .c-paid { letter-spacing: 0; }
            .cupom table, .cupom tr, .cupom td, .cupom div { page-break-inside: avoid; }
        </style>
    @else
        <style>
            body.cupom-pagina { background: #E7EDF3; }
            .cupom-barra { width: 302px; margin: 16px auto 0; display: flex; justify-content: flex-end; }
            .cupom-barra button { border: 0; border-radius: 8px; background: #163A5F; color: #fff; font: 600 13px 'IBM Plex Sans', sans-serif; padding: 8px 14px; cursor: pointer; }
            .cupom { margin: 12px auto 28px; box-shadow: 0 8px 28px rgba(22, 58, 95, .12); }
            @page { size: 80mm auto; margin: 3mm; }
            @media print {
                body.cupom-pagina { background: #fff; }
                .cupom-barra { display: none !important; }
                .cupom { margin: 0; box-shadow: none; }
            }
        </style>
    @endif
</head>
<body @class(['cupom-pagina' => ! $pdf])>
    @unless($pdf)
        <div class="cupom-barra">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    @endunless
    <div class="cupom">
        <div class="c-head">
            <div class="c-name">{{ $empresa->nome }}</div>
            @foreach($empresa->linhas() as $linha)
                <div class="c-meta">{{ $linha }}</div>
            @endforeach
        </div>

        <div class="c-dash"></div>

        <div class="c-title">{{ $dados['titulo'] }}</div>
        <div class="c-sub">{{ $dados['subtitulo'] }}</div>

        <div class="c-dash"></div>

        @forelse($dados['itens'] as $item)
            <table class="c-item">
                <tr>
                    <td class="n">{{ $item['nome'] }}</td>
                    <td class="v">{{ $item['valor'] }}</td>
                </tr>
                @if(!empty($item['detalhe']))
                    <tr>
                        <td class="d" colspan="2">{{ $item['detalhe'] }}</td>
                    </tr>
                @endif
            </table>
        @empty
            <table class="c-item">
                <tr>
                    <td class="n">Nenhum item</td>
                    <td class="v"></td>
                </tr>
            </table>
        @endforelse

        <div class="c-dash"></div>

        @foreach($dados['linhas'] as $linha)
            <table class="c-kv">
                <tr>
                    <td>{{ $linha[0] }}</td>
                    <td class="v">{{ $linha[1] }}</td>
                </tr>
            </table>
        @endforeach
        @foreach($dados['ajustes'] as $linha)
            <table class="c-kv adj">
                <tr>
                    <td>{{ $linha[0] }}</td>
                    <td class="v">{{ $linha[1] }}</td>
                </tr>
            </table>
        @endforeach

        <div class="c-dash"></div>

        <table class="c-total">
            <tr>
                <td>TOTAL</td>
                <td class="v">{{ $dados['total'] }}</td>
            </tr>
        </table>

        @if($dados['pagamentos'] !== [])
            <div class="c-dash"></div>
            @foreach($dados['pagamentos'] as $linha)
                <table class="c-kv">
                    <tr>
                        <td>{{ $linha[0] }}</td>
                        <td class="v">{{ $linha[1] }}</td>
                    </tr>
                </table>
            @endforeach
        @endif

        @if(!empty($dados['pago']))
            <div class="c-paid">{{ $dados['pago'] }}</div>
        @endif

        @if($dados['extras'] !== [])
            <div class="c-dash"></div>
            @foreach($dados['extras'] as $linha)
                <table class="c-kv">
                    <tr>
                        <td>{{ $linha[0] }}</td>
                        <td class="v">{{ $linha[1] }}</td>
                    </tr>
                </table>
            @endforeach
        @endif

        <div class="c-thanks">{{ $dados['agradecimento'] }}</div>
        <div class="c-small">{{ $dados['mensagem'] }}</div>
    </div>
</body>
</html>
