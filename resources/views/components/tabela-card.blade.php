@props([
    'titulo' => 'Resultados',
    'paginator' => null,
])

<div {{ $attributes->class('card') }}>
    <div class="card__header">
        <span class="card__title">{{ $titulo }}</span>
        {{ $acoes ?? '' }}
    </div>
    <div class="card__body" style="padding:0;">
        <div class="table-wrap">
            <table class="table table--cards">
                {{ $slot }}
            </table>
        </div>
    </div>
    @if($paginator)
        <div class="card__footer">
            {{ $paginator->withQueryString()->links() }}
        </div>
    @endif
</div>
