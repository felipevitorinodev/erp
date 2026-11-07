<div {{ $attributes->class('sk-painel') }} role="status" aria-busy="true" aria-label="Carregando painel">
    <div class="sk-painel__numeros">
        @for ($i = 0; $i < 3; $i++)
            <div class="sk-kpi">
                <span class="sk-bloco sk-bloco--rotulo"></span>
                <span class="sk-bloco sk-bloco--valor"></span>
            </div>
        @endfor
    </div>
    <div class="sk-painel__graficos">
        <div class="sk-grafico" aria-hidden="true">
            <span class="sk-bloco sk-bloco--titulo"></span>
            <span class="sk-grafico__barras"><i></i><i></i><i></i><i></i><i></i><i></i></span>
        </div>
        <div class="sk-grafico" aria-hidden="true">
            <span class="sk-bloco sk-bloco--titulo"></span>
            <span class="sk-grafico__barras"><i></i><i></i><i></i><i></i><i></i><i></i></span>
        </div>
    </div>
</div>
