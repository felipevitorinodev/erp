@props([
    'kind' => 'Relatório',
    'nome',
    'periodo' => null,
    'pdf' => true,
])
@php
    $empresa = \App\Impressao\EmpresaDocumento::atual();
    $logo = $pdf ? $empresa->logoArquivo : $empresa->logoUrl;
    $periodoTexto = $periodo;
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $nome }} - Visys</title>
    @include('impressao.estilos', ['pdf' => $pdf])
    @if($pdf)
        <style>
            body { margin: 36pt 40pt 32pt 40pt; }
        </style>
    @endif
</head>
<body>
    <table class="doc-head">
        <tr>
            <td>
                <table>
                    <tr>
                        <td class="co-logo-cell">
                            @if($logo)
                                <img src="{{ $logo }}" alt="" class="co-logo-img">
                            @else
                                <div class="co-logo">Logo</div>
                            @endif
                        </td>
                        <td>
                            <div class="co-name">{{ $empresa->nome }}</div>
                            @if($empresa->linhas() !== [])
                                <div class="co-meta">
                                    @foreach($empresa->linhas() as $linha)
                                        {{ $linha }}@if(! $loop->last)<br>@endif
                                    @endforeach
                                </div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="doc-title">
                <div class="doc-kind">{{ $kind }}</div>
                <div class="doc-name">{{ $nome }}</div>
                @if($periodoTexto)
                    <div class="doc-period">{{ $periodoTexto }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{ $slot }}

    <table class="doc-foot">
        <tr>
            <td>Emitido em {{ now()->format('d/m/Y') }} às {{ now()->format('H:i') }}</td>
            <td class="r">Gerado pelo Visys</td>
        </tr>
    </table>
</body>
</html>
