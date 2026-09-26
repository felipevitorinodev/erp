@php $categoria = $categoriaFinanceira ?? null; @endphp
<div class="form-grid form-grid--col-3">
    <div class="form-group">
        <label class="form-label form-label--required">Nome</label>
        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
            value="{{ old('nome', $categoria?->nome ?? '') }}" maxlength="100"
            placeholder="Ex: Vendas, Aluguel, Salários...">
        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Tipo</label>
        <select name="tipo" class="form-control @error('tipo') is-invalid @enderror"
            @if($categoria) disabled @endif>
            <option value="receita" {{ old('tipo', $categoria?->tipo ?? 'receita') === 'receita' ? 'selected' : '' }}>Receita</option>
            <option value="despesa" {{ old('tipo', $categoria?->tipo ?? '') === 'despesa' ? 'selected' : '' }}>Despesa</option>
        </select>
        @if($categoria)
            <input type="hidden" name="tipo" value="{{ old('tipo', $categoria?->tipo) }}">
            <small class="text-muted">O tipo não pode ser alterado após o cadastro.</small>
        @endif
        @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    @if($categoria)
        <div class="form-group">
            <label class="form-label">Ativo</label>
            <select name="ativo" class="form-control @error('ativo') is-invalid @enderror">
                <option value="1" {{ old('ativo', $categoria?->ativo) ? 'selected' : '' }}>Sim</option>
                <option value="0" {{ !old('ativo', $categoria?->ativo) ? 'selected' : '' }}>Não</option>
            </select>
            @error('ativo')<span class="form-error">{{ $message }}</span>@enderror
        </div>
    @endif
</div>
