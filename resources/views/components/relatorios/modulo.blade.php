@props(['categoria'])

<section
    class="rp-mod"
    data-relatorios-categoria
    data-modulo="{{ $categoria['slug'] }}"
    aria-labelledby="mod-{{ $categoria['slug'] }}"
>
    <div class="rp-mod__head">
        <span class="rp-mod__icone" aria-hidden="true">
            <x-icon :name="$categoria['icone']" :size="20" />
        </span>
        <span class="rp-mod__nome" id="mod-{{ $categoria['slug'] }}">{{ $categoria['titulo'] }}</span>
        <span class="rp-mod__count" data-modulo-contagem>{{ count($categoria['relatorios']) }}</span>
    </div>
    @foreach ($categoria['relatorios'] as $relatorio)
        <x-relatorios.relatorio :relatorio="$relatorio" />
    @endforeach
</section>
