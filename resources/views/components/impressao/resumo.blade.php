@props(['linhas' => []])
@php
    $visiveis = array_values(array_filter($linhas, fn ($linha) => empty($linha['destaque'])));
    $destaque = null;
    foreach ($linhas as $linha) {
        if (! empty($linha['destaque'])) {
            $destaque = $linha;
        }
    }
    if ($destaque === null && $linhas !== []) {
        $destaque = array_pop($visiveis);
    }
@endphp
@if($linhas !== [])
    <table class="summary">
        @foreach($visiveis as $linha)
            <tr>
                <td>{{ $linha['label'] }}</td>
                <td class="r num">{{ $linha['valor'] }}</td>
            </tr>
        @endforeach
        @if($destaque)
            <tr class="grand">
                <td>{{ $destaque['label'] }}</td>
                <td class="r num">{{ $destaque['valor'] }}</td>
            </tr>
        @endif
    </table>
@endif
