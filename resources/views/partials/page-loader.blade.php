@php $classeArea = ($area ?? null) === 'conteudo' ? 'page-loader--conteudo' : ''; @endphp

<div class="page-progress {{ $classeArea }}" id="page-progress" aria-hidden="true">
    <span class="page-progress__barra" id="page-progress-barra"></span>
</div>

<div class="page-loader {{ $classeArea }}" id="page-loader" hidden>
    <div class="page-loader__skeleton" aria-hidden="true">
        <div class="page-loader__sk-topo">
            <span class="page-loader__sk page-loader__sk--titulo"></span>
            <span class="page-loader__sk page-loader__sk--botao"></span>
        </div>
        <div class="page-loader__sk-card">
            <span class="page-loader__sk page-loader__sk--campo"></span>
            <span class="page-loader__sk page-loader__sk--campo"></span>
            <span class="page-loader__sk page-loader__sk--campo"></span>
        </div>
        <div class="page-loader__sk-card page-loader__sk-card--tabela">
            @for ($i = 0; $i < 6; $i++)
                <div class="page-loader__sk-linha" style="--i: {{ $i }}">
                    <span class="page-loader__sk page-loader__sk--curto"></span>
                    <span class="page-loader__sk page-loader__sk--longo"></span>
                    <span class="page-loader__sk page-loader__sk--medio"></span>
                    <span class="page-loader__sk page-loader__sk--curto"></span>
                </div>
            @endfor
        </div>
    </div>

    <div class="page-loader__status" role="status" aria-live="polite">
        <span class="page-loader__orbita" aria-hidden="true"><span></span><span></span><span></span></span>
        <span class="page-loader__texto" id="page-loader-texto">Carregando</span>
    </div>
</div>
