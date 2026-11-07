@props(['texto'])
@php
    $classe = \App\Impressao\SituacaoImpressao::classe((string) $texto);
@endphp
@if($classe)
    <span class="pill {{ $classe }}">{{ $texto }}</span>
@else
    {{ $texto }}
@endif
