@php $produto = $produto ?? null; @endphp
<div class="form-grid form-grid--col-4">
    <div class="form-group form-group--span-1">
        <label class="form-label">Código</label>
        <input disabled type="text" name="codigo" class="form-control @error('codigo') is-invalid @enderror"
            value="{{ old('codigo', $produto->codigo ?? '') }}" maxlength="50">
        @error('codigo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group form-group--span-3">
        <label class="form-label form-label--required">Nome</label>
        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
            value="{{ old('nome', $produto->nome ?? '') }}" maxlength="255">
        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group form-group--span-2">
        <label class="form-label">Código de Barras</label>
        <input type="text" name="codigo_barras" class="form-control @error('codigo_barras') is-invalid @enderror"
            value="{{ old('codigo_barras', $produto->codigo_barras ?? '') }}" maxlength="50">
        @error('codigo_barras')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group form-group--span-2">
        <label class="form-label">Referência</label>
        <input type="text" name="referencia" class="form-control @error('referencia') is-invalid @enderror"
            value="{{ old('referencia', $produto->referencia ?? '') }}" maxlength="50">
        @error('referencia')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Grupo</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-grupo" class="form-control"
                value="{{ old('grupo_nome', isset($produto) && $produto->grupos ? (($produto->grupos->parent ? $produto->grupos->parent->nome . ' / ' : '') . $produto->grupos->nome) : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="grupo_id" id="hidden-grupo-id"
                value="{{ old('grupo_id', $produto->grupo_id ?? '') }}">
        </div>
        @error('grupo_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Unidade de Medida</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-unidade" class="form-control"
                value="{{ old('unidade_nome', isset($produto) && $produto->unidadeMedida ? ($produto->unidadeMedida->nome . ' (' . $produto->unidadeMedida->sigla . ')') : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="unidade_medida_id" id="hidden-unidade-id"
                value="{{ old('unidade_medida_id', $produto->unidade_medida_id ?? '') }}">
        </div>
        @error('unidade_medida_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group form-group--span-2">
        <label class="form-label">Fornecedor Principal</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-fornecedor" class="form-control"
                value="{{ old('fornecedor_nome', isset($produto) && $produto->fornecedor ? $produto->fornecedor->nome : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="fornecedor_id" id="hidden-fornecedor-id"
                value="{{ old('fornecedor_id', $produto->fornecedor_id ?? '') }}">
        </div>
        @error('fornecedor_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label form-label--required">Tipo</label>
        <select name="tipo" class="form-control @error('tipo') is-invalid @enderror">
            <option value="1" {{ old('tipo', $produto->tipo ?? 1) == 1 ? 'selected' : '' }}>Produto</option>
            <option value="2" {{ old('tipo', $produto->tipo ?? '') == 2 ? 'selected' : '' }}>Serviço</option>
        </select>
        @error('tipo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Preço de Custo</label>
        @include('components.input-numeric', [
            'name' => 'preco_custo',
            'value' => old('preco_custo', isset($produto) ? number_format($produto->preco_custo ?? 0, 2, ',', '.') : '0,00'),
            'class' => $errors->has('preco_custo') ? 'is-invalid' : '',
            'decimals' => 2
        ])
        @error('preco_custo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Preço de Venda</label>
        @include('components.input-numeric', [
            'name' => 'preco_venda',
            'value' => old('preco_venda', isset($produto) ? number_format($produto->preco_venda ?? 0, 2, ',', '.') : '0,00'),
            'class' => $errors->has('preco_venda') ? 'is-invalid' : '',
            'decimals' => 2
        ])
        @error('preco_venda')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Preço Mínimo</label>
        @include('components.input-numeric', [
            'name' => 'preco_minimo',
            'value' => old('preco_minimo', isset($produto) ? number_format($produto->preco_minimo ?? 0, 2, ',', '.') : '0,00'),
            'class' => $errors->has('preco_minimo') ? 'is-invalid' : '',
            'decimals' => 2
        ])
        @error('preco_minimo')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Estoque Mínimo</label>
        @include('components.input-quantity', [
            'name' => 'estoque_minimo',
            'value' => old('estoque_minimo', isset($produto) ? number_format($produto->estoque_minimo ?? 0, 2, ',', '.') : '0,00'),
            'class' => ($errors->has('estoque_minimo') ? 'is-invalid' : '') . ' text-right input-qtd',
            'decimals' => 2
        ])
        @error('estoque_minimo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Estoque Máximo</label>
        @include('components.input-quantity', [
            'name' => 'estoque_maximo',
            'value' => old('estoque_maximo', isset($produto) && $produto->estoque_maximo !== null ? number_format($produto->estoque_maximo ?? 0, 2, ',', '.') : ''),
            'class' => ($errors->has('estoque_maximo') ? 'is-invalid' : '') . ' text-right input-qtd',
            'decimals' => 2
        ])
        @error('estoque_maximo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Estoque Atual</label>
        @include('components.input-quantity', [
            'name' => 'estoque_atual',
            'value' => old('estoque_atual', isset($produto) ? number_format($produto->estoque_atual ?? 0, 2, ',', '.') : '0,00'),
            'class' => ($errors->has('estoque_atual') ? 'is-invalid' : '') . ' text-right input-qtd',
            'decimals' => 2
        ])
        @error('estoque_atual')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Controla Estoque?</label>
        <select name="controla_estoque" class="form-control">
            <option value="1" {{ old('controla_estoque', $produto->controla_estoque ?? 1) == 1 ? 'selected' : '' }}>Sim
            </option>
            <option value="0" {{ old('controla_estoque', $produto->controla_estoque ?? '') == 0 ? 'selected' : '' }}>Não
            </option>
        </select>
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">NCM</label>
        <input type="text" name="ncm" class="form-control @error('ncm') is-invalid @enderror"
            value="{{ old('ncm', $produto->ncm ?? '') }}" maxlength="10">
        @error('ncm')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">CEST</label>
        <input type="text" name="cest" class="form-control @error('cest') is-invalid @enderror"
            value="{{ old('cest', $produto->cest ?? '') }}" maxlength="10">
        @error('cest')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">CFOP Padrão</label>
        <input type="text" name="cfop_padrao" class="form-control @error('cfop_padrao') is-invalid @enderror"
            value="{{ old('cfop_padrao', $produto->cfop_padrao ?? '') }}" maxlength="5">
        @error('cfop_padrao')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Ativo</label>
        <select name="ativo" class="form-control">
            <option value="1" {{ old('ativo', $produto->ativo ?? 1) == 1 ? 'selected' : '' }}>Sim</option>
            <option value="0" {{ old('ativo', $produto->ativo ?? '') == 0 ? 'selected' : '' }}>Não</option>
        </select>
    </div>
</div>

<div class="form-grid" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Descrição</label>
        <textarea name="descricao" class="form-control @error('descricao') is-invalid @enderror"
            rows="3">{{ old('descricao', $produto->descricao ?? '') }}</textarea>
        @error('descricao')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>
@push('scripts')
<script>
(function () {
    initAutocomplete('#input-grupo', '#hidden-grupo-id', '{{ route('api.grupos.busca') }}', function (item) {
        return (item.parent && item.parent.nome ? item.parent.nome + ' / ' : '') + item.nome;
    });

    initAutocomplete('#input-unidade', '#hidden-unidade-id', '{{ route('api.unidades-medida.busca') }}', function (item) {
        return item.nome + (item.sigla ? ' (' + item.sigla + ')' : '');
    });

    initAutocomplete('#input-fornecedor', '#hidden-fornecedor-id', '{{ route('api.fornecedores.busca') }}', function (item) {
        var doc = item.cpf || item.cnpj || '';
        return item.nome + (doc ? ' — ' + doc : '');
    });
})();
</script>
@endpush
