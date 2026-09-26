<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <title>{{ $titulo }} - Visys</title>
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
                    <span class="label">Relatório</span>
                    <span class="numero" style="font-size:16px;">{{ $titulo }}</span>
                    <div class="data">{{ $periodo }}</div>
                </td>
            </tr>
        </table>

        @if (!empty($resumo))
            <div class="status-strip">
                {{ $resumo }}
            </div>
        @endif

        <div class="section-heading">{{ $secao ?? 'Resultados' }}</div>
        <table class="items">
            <thead>
                <tr>
                    @foreach ($colunas as $coluna)
                        <th class="{{ ($coluna['align'] ?? 'left') === 'right' ? 'text-right' : '' }}"
                            @if (!empty($coluna['width'])) style="width: {{ $coluna['width'] }};" @endif>
                            {{ $coluna['label'] }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($linhas as $linha)
                    <tr>
                        @foreach ($linha as $indice => $celula)
                            <td class="{{ ($colunas[$indice]['align'] ?? 'left') === 'right' ? 'text-right' : '' }}">
                                {{ $celula }}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ count($colunas) }}" class="text-center">Nenhum registro para os filtros informados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if (!empty($totais))
            <table class="totals-wrap">
                <tr>
                    <td style="width: 45%;"></td>
                    <td style="width: 55%;">
                        <div class="totals-box totals-box--wide">
                            <table class="totals-inner">
                                @foreach ($totais as $total)
                                    <tr class="{{ !empty($total['destaque']) ? 'divider grand-total' : '' }}">
                                        <td>{{ $total['label'] }}</td>
                                        <td class="text-right">{{ $total['valor'] }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        @endif

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
