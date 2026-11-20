@props(['relatorio', 'modulo' => ''])

<a
    href="{{ route($relatorio['rota']) }}"
    class="rp-rep"
    data-relatorio
    data-slug="{{ $relatorio['slug'] }}"
    data-busca="{{ \Illuminate\Support\Str::lower($relatorio['titulo'].' '.$relatorio['descricao'].' '.$modulo) }}"
>
    <span class="rp-rep__icone">
        <x-icon :name="$relatorio['icone']" :size="18" />
    </span>
    <span class="rp-rep__body">
        <span class="rp-rep__nome">{{ $relatorio['titulo'] }}</span>
        <span class="rp-rep__desc">{{ $relatorio['descricao'] }}</span>
    </span>
    <x-icon name="chevron-right" :size="18" class="rp-rep__seta" />
</a>
