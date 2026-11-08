{{-- Início à tarde: sol alto e nuvens passando. --}}
<svg viewBox="0 0 200 150" class="ilustracao__svg" focusable="false">
    <g class="il-camada" style="--prof: 2">
        <circle class="il-destaque il-destaque-preenche" cx="44" cy="26" r="12"/>
        <g class="il-gira-lento" style="transform-origin: 44px 26px">
            <path class="il-destaque" d="M44 6v5M44 41v5M24 26h5M59 26h5"/>
        </g>
    </g>
    <g class="il-camada il-deriva" style="--prof: 7">
        <path class="il-linha il-fundo-claro" d="M126 30a11 11 0 0 1 21-5 8 8 0 0 1 11 8h-32z"/>
    </g>
    <g class="il-camada il-deriva il-atraso-3" style="--prof: 5; animation-duration: 12s">
        <path class="il-linha il-fundo-claro" d="M84 18a7 7 0 0 1 13-3 5 5 0 0 1 7 5H84z"/>
    </g>
    @include('partials.ilustracoes._fachada', ['vitrine' => 'il-preenche'])
</svg>
