{{-- Início pela manhã: sol nascendo atrás da loja. --}}
<svg viewBox="0 0 200 150" class="ilustracao__svg" focusable="false">
    <g class="il-camada" style="--prof: 2">
        <g class="il-gira-lento" style="transform-origin: 150px 44px">
            <path class="il-destaque" d="M150 14v8M150 66v8M120 44h8M172 44h8M129 23l6 6M165 59l6 6M129 65l6-6M165 29l6-6"/>
        </g>
        <circle class="il-destaque il-destaque-preenche" cx="150" cy="44" r="14"/>
    </g>
    <g class="il-camada il-deriva" style="--prof: 6">
        <path class="il-linha il-fundo-claro" d="M24 40a9 9 0 0 1 17-4 7 7 0 0 1 9 7H24z"/>
    </g>
    @include('partials.ilustracoes._fachada', ['vitrine' => 'il-preenche'])
</svg>
