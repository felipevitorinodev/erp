<div {{ $attributes->class('sk-relatorio') }} role="status" aria-busy="true" aria-label="Carregando relatório">
    <div class="sk-painel__numeros">
        @for ($i = 0; $i < 4; $i++)
            <div class="sk-kpi">
                <span class="sk-bloco sk-bloco--rotulo"></span>
                <span class="sk-bloco sk-bloco--valor"></span>
            </div>
        @endfor
    </div>
    <div class="sk-grafico" aria-hidden="true">
        <span class="sk-bloco sk-bloco--titulo"></span>
        <span class="sk-grafico__barras"><i></i><i></i><i></i><i></i><i></i><i></i><i></i></span>
    </div>
    <x-skeleton-tabela :linhas="6" :colunas="4" />
</div>
