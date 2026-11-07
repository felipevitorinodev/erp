<div {{ $attributes->class(['acoes']) }}>
    @if (isset($extra) && ! $extra->isEmpty())
        <div class="acoes__extra">{{ $extra }}</div>
    @endif
    <div class="acoes__grade">
        @isset($editar)
            <span class="acoes__slot">{{ $editar }}</span>
        @endisset
        @isset($ver)
            <span class="acoes__slot">{{ $ver }}</span>
        @endisset
        @isset($excluir)
            <span class="acoes__slot">{{ $excluir }}</span>
        @endisset
    </div>
</div>
