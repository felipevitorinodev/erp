<div class="nav-barra" id="nav-barra" hidden>
    <span class="nav-barra__ind"></span>
</div>

<div id="erp-status" class="sr-only" role="status" aria-live="polite"></div>

<div class="erp-processo" id="erp-processo" hidden>
    <div class="erp-progresso is-indeterminado" id="erp-progresso" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuetext="Processando">
        <div class="erp-progresso__trilha">
            <div class="erp-progresso__valor" id="erp-progresso-valor"></div>
        </div>
        <p class="erp-progresso__msg">
            <span class="erp-progresso__texto" id="erp-progresso-msg">Processando...</span>
            <span class="erp-progresso__pct" id="erp-progresso-pct" hidden></span>
        </p>
    </div>
</div>

<div class="nav-skeleton" id="nav-skeleton" hidden></div>

<template id="tpl-skeleton-tabela">
    <x-skeleton-tabela />
</template>
<template id="tpl-skeleton-formulario">
    <x-skeleton-formulario />
</template>
<template id="tpl-skeleton-painel">
    <x-skeleton-painel />
</template>
<template id="tpl-skeleton-ficha">
    <x-skeleton-ficha />
</template>
<template id="tpl-skeleton-relatorio">
    <x-skeleton-relatorio />
</template>
<template id="tpl-skeleton-grafico">
    <x-skeleton-grafico />
</template>
