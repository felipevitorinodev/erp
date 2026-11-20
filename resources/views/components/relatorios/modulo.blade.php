@props(['categoria'])

<section
    class="rp-mod rp-cor--{{ $categoria['slug'] }}"
    data-relatorios-categoria
    data-modulo="{{ $categoria['slug'] }}"
    aria-labelledby="mod-{{ $categoria['slug'] }}"
>
    <header class="rp-mod__head">
        <span class="rp-mod__icone" aria-hidden="true">
            <x-icon :name="$categoria['icone']" :size="20" />
        </span>
        <div class="rp-mod__titulos">
            <h2 class="rp-mod__nome" id="mod-{{ $categoria['slug'] }}">{{ $categoria['titulo'] }}</h2>
            @if (! empty($categoria['descricao']))
                <p class="rp-mod__desc">{{ $categoria['descricao'] }}</p>
            @endif
        </div>
        <span class="rp-mod__count" data-modulo-contagem title="Relatórios neste módulo">{{ count($categoria['relatorios']) }}</span>
    </header>

    <div class="rp-mod__grade">
        @foreach ($categoria['relatorios'] as $relatorio)
            <x-relatorios.relatorio :relatorio="$relatorio" :modulo="$categoria['titulo']" />
        @endforeach
    </div>
</section>
