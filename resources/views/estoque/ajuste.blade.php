@extends('layouts.app')

@section('title', 'Ajuste de Estoque')
@section('page_title', 'Ajuste de Estoque')

@section('breadcrumb')
    <a href="{{ route('estoque.historico') }}">Histórico de Estoque</a> / Ajuste
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('estoque.historico') }}" class="btn btn--ghost btn--sm">Histórico</a>
    </div>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Registrar ajuste</span>
        </div>

        <form method="POST" action="{{ route('estoque.ajuste.store') }}">
            @csrf
            <div class="card__body">
                <div class="form-grid form-grid--col-3">
                    <div class="form-group form-group--span-2">
                        <label class="form-label form-label--required">Produto</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-produto" class="form-control"
                                value="{{ old('produto_nome') }}"
                                placeholder="Digite para buscar..." autocomplete="off">
                            <input type="hidden" name="produto_id" id="hidden-produto-id"
                                value="{{ old('produto_id') }}">
                        </div>
                        @error('produto_id')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label">Estoque atual</label>
                        <input type="text" id="estoque-atual-display" class="form-control" value="—" readonly>
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label--required">Tipo</label>
                        <select name="tipo" class="form-control @error('tipo') is-invalid @enderror">
                            <option value="entrada" {{ old('tipo') === 'entrada' ? 'selected' : '' }}>Entrada</option>
                            <option value="saida" {{ old('tipo') === 'saida' ? 'selected' : '' }}>Saída</option>
                            <option value="ajuste" {{ old('tipo') === 'ajuste' ? 'selected' : '' }}>Ajuste</option>
                        </select>
                        @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label--required">Quantidade</label>
                        @include('components.input-quantity', [
                            'name' => 'quantidade',
                            'value' => old('quantidade', '1,000'),
                            'class' => 'text-right',
                            'decimals' => 2,
                        ])
                        @error('quantidade')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group form-group--span-2">
                        <label class="form-label form-label--required">Motivo</label>
                        <input type="text" name="motivo" class="form-control @error('motivo') is-invalid @enderror"
                            maxlength="200" value="{{ old('motivo') }}" placeholder="Justificativa do ajuste">
                        @error('motivo')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <div class="card__footer">
                <a href="{{ route('estoque.historico') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Registrar Ajuste</button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script>
(function ($) {
    function formatQtd(val) {
        var n = parseFloat(val || 0);
        return n.toFixed(2).replace('.', ',');
    }

    initAutocomplete('#input-produto', '#hidden-produto-id', '{{ route('api.produtos.busca') }}', function (item) {
        return (item.codigo ? item.codigo + ' — ' : '') + item.nome;
    }, function (item) {
        document.getElementById('estoque-atual-display').value = formatQtd(item.estoque_atual);
    });
})(jQuery);
</script>
@endpush
