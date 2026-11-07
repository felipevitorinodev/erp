@props(['relatorio'])

<a
    href="{{ route($relatorio['rota']) }}"
    class="rp-rep"
    data-relatorio
    data-busca="{{ \Illuminate\Support\Str::lower($relatorio['titulo'].' '.$relatorio['descricao']) }}"
>
    <span class="rp-rep__body">
        <span class="rp-rep__nome">{{ $relatorio['titulo'] }}</span>
        <span class="rp-rep__desc">{{ $relatorio['descricao'] }}</span>
    </span>
    <span class="rp-rep__go" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14M13 6l6 6-6 6"></path>
        </svg>
    </span>
</a>
