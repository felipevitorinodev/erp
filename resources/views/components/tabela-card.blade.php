{{--
    Card com a tabela da listagem. No celular (até 768px) cada linha vira um card:
    a célula .col-titulo (ou a primeira) é o título, "Situação" vai para o canto e as ações para o rodapé.
    Clicar na linha abre o registro (Ver; sem Ver, Editar) — public/js/app.js.
--}}
@props([
    'titulo' => 'Resultados',
    'paginator' => null,
])

<div {{ $attributes->class('card tabela-card') }}>
    <div class="card__header">
        <span class="card__title">
            {{ $titulo }}
            @if ($paginator && method_exists($paginator, 'total') && $paginator->total() > 0)
                <span class="card__contagem">{{ number_format($paginator->total(), 0, ',', '.') }}</span>
            @endif
        </span>
        {{ $acoes ?? '' }}
    </div>
    @isset($abas)
        <div class="tabela-card__abas">{{ $abas }}</div>
    @endisset
    <div class="card__body card__body--flush">
        <div class="table-wrap">
            <table class="table table--cards">
                {{ $slot }}
            </table>
        </div>
    </div>
    @if ($paginator && $paginator->hasPages())
        <div class="card__footer">
            {{ $paginator->withQueryString()->links() }}
        </div>
    @endif
</div>
