{{-- Início à noite: lua, estrelas e vitrine acesa. --}}
<svg viewBox="0 0 200 150" class="ilustracao__svg" focusable="false">
    <g class="il-camada" style="--prof: 2">
        <path class="il-linha il-preenche" d="M158 16a18 18 0 1 0 14 28 15 15 0 0 1-14-28z"/>
        <path class="il-destaque il-pisca" d="M36 22v8M32 26h8"/>
        <path class="il-destaque il-pisca il-atraso-2" d="M112 14v6M109 17h6"/>
        <circle class="il-luz il-pisca il-atraso-1" cx="72" cy="30" r="2"/>
        <circle class="il-luz il-pisca il-atraso-3" cx="186" cy="58" r="2"/>
    </g>
    @include('partials.ilustracoes._fachada', ['vitrine' => 'il-destaque-preenche'])
</svg>
