@extends('layouts.app')

@section('title', 'Nova Forma de Pagamento')
@section('page_title', 'Nova Forma de Pagamento')

@section('breadcrumb')
    <a href="{{ route('formaPagamento.index') }}">Formas de Pagamento</a> / Nova
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Forma de Pagamento</span>
        </div>

        <form method="POST" action="{{ route('formaPagamento.store') }}">
            @csrf
            <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
            <div class="card__body">
                <div class="form-grid form-grid--col-3">
                    <div class="form-group">
                        <label class="form-label form-label--required">Nome</label>
                        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                            value="{{ old('nome') }}" maxlength="100" placeholder="Ex: Pix, Duplicata...">
                        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label form-label--required">Tipo</label>
                        <select name="tipo" id="tipo-forma" class="form-control @error('tipo') is-invalid @enderror">
                            <option value="avista" {{ old('tipo', 'avista') === 'avista' ? 'selected' : '' }}>À vista</option>
                            <option value="prazo" {{ old('tipo') === 'prazo' ? 'selected' : '' }}>A prazo</option>
                        </select>
                        @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
                        <small class="text-muted">A prazo gera Conta a Receber ao confirmar a venda.</small>
                    </div>

                    <div class="form-group" id="grupo-dias">
                        <label class="form-label">Dias para vencimento</label>
                        <input type="number" name="dias_vencimento" min="0" max="3650"
                            class="form-control @error('dias_vencimento') is-invalid @enderror"
                            value="{{ old('dias_vencimento', 30) }}">
                        @error('dias_vencimento')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>

            <div class="card__footer">
                <a href="{{ route('formaPagamento.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection

@push('scripts')
<script>
(function () {
    var tipo = document.getElementById('tipo-forma');
    var grupoDias = document.getElementById('grupo-dias');

    function toggle() {
        grupoDias.style.display = tipo.value === 'prazo' ? '' : 'none';
    }

    tipo.addEventListener('change', toggle);
    toggle();
})();
</script>
@endpush
