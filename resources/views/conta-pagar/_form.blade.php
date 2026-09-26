@php $conta = $conta ?? null; @endphp
<div class="form-grid form-grid--col-2">
    <div class="form-group">
        <label class="form-label form-label--required">Descrição</label>
        <input type="text" name="descricao"
            class="form-control @error('descricao') is-invalid @enderror"
            value="{{ old('descricao', $conta->descricao ?? '') }}"
            maxlength="200" required>
        @error('descricao')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Fornecedor</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-fornecedor" class="form-control"
                value="{{ old('fornecedor_nome', $conta && $conta->fornecedor ? $conta->fornecedor->nome : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="fornecedor_id" id="hidden-fornecedor-id"
                value="{{ old('fornecedor_id', $conta->fornecedor_id ?? '') }}">
        </div>
        @error('fornecedor_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-3" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label form-label--required">Valor</label>
        @include('components.input-numeric', [
            'name' => 'valor',
            'value' => old('valor', isset($conta) ? number_format($conta->valor, 2, ',', '.') : ''),
            'class' => 'input-moeda ' . ($errors->has('valor') ? 'is-invalid' : ''),
            'decimals' => 2,
            'placeholder' => '0,00',
            'required' => true
        ])
        @error('valor')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Vencimento</label>
        <input type="date" name="data_vencimento"
            class="form-control @error('data_vencimento') is-invalid @enderror"
            value="{{ old('data_vencimento', isset($conta) ? $conta->data_vencimento?->format('Y-m-d') : date('Y-m-d')) }}"
            required>
        @error('data_vencimento')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Forma de Pagamento</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-forma-pagamento" class="form-control"
                value="{{ old('forma_pagamento', $conta->forma_pagamento ?? '') }}"
                placeholder="Selecione a forma de pagamento..." autocomplete="off">
            <input type="hidden" name="forma_pagamento" id="hidden-forma-pagamento"
                value="{{ old('forma_pagamento', $conta->forma_pagamento ?? '') }}">
        </div>
        @error('forma_pagamento')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Categoria</label>
        <select name="categoria_id" class="form-control @error('categoria_id') is-invalid @enderror">
            <option value="">— Sem categoria —</option>
            @foreach($categorias ?? [] as $categoria)
                <option value="{{ $categoria->id }}"
                    {{ (string) old('categoria_id', $conta->categoria_id ?? '') === (string) $categoria->id ? 'selected' : '' }}>
                    {{ $categoria->nome }}
                </option>
            @endforeach
        </select>
        @error('categoria_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-1" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror"
            rows="3" style="resize:none;">{{ old('observacoes', $conta->observacoes ?? '') }}</textarea>
        @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

@push('scripts')
<script>
(function () {
    if (typeof initAutocomplete === 'function') {
        initAutocomplete('#input-fornecedor', '#hidden-fornecedor-id', '{{ route('api.fornecedores.busca') }}', function (item) {
            var doc = item.cpf || item.cnpj || '';
            return item.nome + (doc ? ' — ' + doc : '');
        });

        initAutocomplete('#input-forma-pagamento', '#hidden-forma-pagamento', '{{ route('api.formas-pagamento.busca') }}', function (item) {
            return item.nome;
        }, null, null, 'nome');
    }
})();
</script>
@endpush
