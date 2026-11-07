@if ($paginator->total() > 0)
    <nav class="pagination" role="navigation" aria-label="Paginação">
        <span class="pagination__info">
            Mostrando
            <strong>{{ $paginator->firstItem() }}</strong>
            –
            <strong>{{ $paginator->lastItem() }}</strong>
            de
            <strong>{{ $paginator->total() }}</strong>
        </span>

        <div class="pagination__links">
            @if ($paginator->onFirstPage())
                <span class="pagination__btn pagination__btn--disabled">Anterior</span>
            @else
                <a class="pagination__btn" href="{{ $paginator->previousPageUrl() }}" rel="prev">Anterior</a>
            @endif

            @if ($paginator->hasPages())
                @foreach ($elements as $element)
                    @if (is_string($element))
                        <span class="pagination__btn pagination__btn--disabled">{{ $element }}</span>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <span class="pagination__btn pagination__btn--active" aria-current="page">{{ $page }}</span>
                            @else
                                <a class="pagination__btn" href="{{ $url }}">{{ $page }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            @else
                <span class="pagination__btn pagination__btn--active" aria-current="page">1</span>
            @endif

            @if ($paginator->hasMorePages())
                <a class="pagination__btn" href="{{ $paginator->nextPageUrl() }}" rel="next">Próxima</a>
            @else
                <span class="pagination__btn pagination__btn--disabled">Próxima</span>
            @endif
        </div>
    </nav>
@endif
