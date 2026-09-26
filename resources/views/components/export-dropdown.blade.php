@props([
    'pdf' => null,
    'csv' => null,
    'label' => 'Exportar',
])

@if($pdf || $csv)
    <div class="export-dropdown" data-export-dropdown>
        <button type="button"
            class="btn btn--ghost btn--sm export-dropdown__toggle"
            aria-expanded="false"
            aria-haspopup="true"
            data-export-toggle>
            {{ $label }}
            <svg class="export-dropdown__caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <polyline points="6 9 12 15 18 9"></polyline>
            </svg>
        </button>
        <div class="export-dropdown__menu" role="menu" hidden>
            @if($pdf)
                <a href="{{ $pdf }}" class="export-dropdown__item" role="menuitem" target="_blank" rel="noopener">
                    PDF
                </a>
            @endif
            @if($csv)
                <a href="{{ $csv }}" class="export-dropdown__item" role="menuitem" data-no-loader download>
                    CSV
                </a>
            @endif
        </div>
    </div>
@endif
