@props([
    'action',
    'limpar' => null,
])

<div class="card mb-2">
    <div class="card__body">
        <form method="GET" action="{{ $action }}" class="form-grid form-grid--col-3">
            {{ $slot }}
            <div class="form-group">
                <div class="filter-actions">
                    <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                    <a href="{{ $limpar ?? $action }}" class="btn btn--ghost btn--sm">Limpar</a>
                </div>
            </div>
        </form>
    </div>
</div>
