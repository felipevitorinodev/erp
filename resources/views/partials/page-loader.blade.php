@php $classeArea = ($area ?? null) === 'conteudo' ? 'page-loader--conteudo' : ''; @endphp

<div class="page-progress {{ $classeArea }}" id="page-progress" aria-hidden="true">
    <span class="page-progress__barra" id="page-progress-barra"></span>
</div>

<div class="page-loader {{ $classeArea }}" id="page-loader" hidden>
    <div class="page-loader__status" role="status" aria-live="polite">
        <span class="page-loader__orbita" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="page-loader__texto" id="page-loader-texto">Carregando</span>
    </div>
</div>
