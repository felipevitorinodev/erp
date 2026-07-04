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
        <label class="form-label">grupo</label>
        <select name="grupo_id" class="form-control @error('grupo_id') is-invalid @enderror">
            <option value="">Selecione</option>
            @foreach($grupos as $grupo)
                <option value="{{ $grupo->id }}" {{ old('grupo_id', $produto->grupo_id ?? '') == $grupo->id ? 'selected' : '' }}>
                    {{ $grupo->parent ? $grupo->parent->nome . ' / ' : '' }}{{ $grupo->nome }}
                </option>
            @endforeach
        </select>
        @error('grupo_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Unidade de Medida</label>
        <select name="unidade_medida_id" class="form-control @error('unidade_medida_id') is-invalid @enderror">
            <option value="">Selecione</option>
            @foreach($unidades as $unidade)
                <option value="{{ $unidade->id }}" {{ old('unidade_medida_id', $produto->unidade_medida_id ?? '') == $unidade->id ? 'selected' : '' }}>{{ $unidade->nome }} ({{ $unidade->sigla }})</option>
            @endforeach
        </select>
        @error('unidade_medida_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group form-group--span-2">
        <label class="form-label">Fornecedor Principal</label>
        <select name="fornecedor_id" class="form-control @error('fornecedor_id') is-invalid @enderror">
            <option value="">Selecione</option>
            @foreach($fornecedores as $fornecedor)
                <option value="{{ $fornecedor->id }}" {{ old('fornecedor_id', $produto->fornecedor_id ?? '') == $fornecedor->id ? 'selected' : '' }}>{{ $fornecedor->nome }}</option>
            @endforeach
        </select>
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
        <input type="number" step="0.0001" name="preco_custo"
            class="form-control @error('preco_custo') is-invalid @enderror"
            value="{{ old('preco_custo', $produto->preco_custo ?? '0.0000') }}">
        @error('preco_custo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Preço de Venda</label>
        <input type="number" step="0.0001" name="preco_venda"
            class="form-control @error('preco_venda') is-invalid @enderror"
            value="{{ old('preco_venda', $produto->preco_venda ?? '0.0000') }}">
        @error('preco_venda')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Preço Mínimo</label>
        <input type="number" step="0.0001" name="preco_minimo"
            class="form-control @error('preco_minimo') is-invalid @enderror"
            value="{{ old('preco_minimo', $produto->preco_minimo ?? '0.0000') }}">
        @error('preco_minimo')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Estoque Mínimo</label>
        <input type="number" step="0.001" name="estoque_minimo"
            class="form-control @error('estoque_minimo') is-invalid @enderror"
            value="{{ old('estoque_minimo', $produto->estoque_minimo ?? '0.000') }}">
        @error('estoque_minimo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Estoque Máximo</label>
        <input type="number" step="0.001" name="estoque_maximo"
            class="form-control @error('estoque_maximo') is-invalid @enderror"
            value="{{ old('estoque_maximo', $produto->estoque_maximo ?? '') }}">
        @error('estoque_maximo')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label">Estoque Atual</label>
        <input type="number" step="0.001" name="estoque_atual"
            class="form-control @error('estoque_atual') is-invalid @enderror"
            value="{{ old('estoque_atual', $produto->estoque_atual ?? '0.000') }}">
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