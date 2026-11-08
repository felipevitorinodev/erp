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
    $iniciais = '';
    foreach (array_slice(array_values(array_filter(preg_split('/\s+/u', trim($empresa->nome)) ?: [])), 0, 2) as $parte) {
        $iniciais .= mb_strtoupper(mb_substr($parte, 0, 1));
    }
    $iniciais = $iniciais !== '' ? $iniciais : 'V';
@endphp
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $nome }} - Visys</title>
    @include('impressao.estilos', ['pdf' => $pdf])
    @if($pdf)
        <style>
            body { margin: 0 36pt 26pt; }
            .doc-accent { margin: 0 -36pt 18px; }
        </style>
    @endif
</head>
<body>
    <div class="doc-accent"></div>
    <table class="doc-head">
        <tr>
            <td class="doc-brand">
                <table>
                    <tr>
                        <td class="co-logo-cell">
                            @if($logo)
                                <img src="{{ $logo }}" alt="" class="co-logo-img">
                            @else
                                <div class="co-logo">{{ $iniciais }}</div>
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
