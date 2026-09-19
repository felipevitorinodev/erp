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
        <label class="form-label">Cliente</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-cliente" class="form-control"
                value="{{ old('cliente_nome', $conta && $conta->cliente ? $conta->cliente->nome : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="cliente_id" id="hidden-cliente-id"
                value="{{ old('cliente_id', $conta->cliente_id ?? '') }}">
        </div>
        @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-3" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Venda vinculada</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-venda" class="form-control"
                value="{{ old('venda_nome', $conta && $conta->venda ? ('#' . $conta->venda->numero) : '') }}"
                placeholder="Digite número ou cliente..." autocomplete="off">
            <input type="hidden" name="venda_id" id="hidden-venda-id"
                value="{{ old('venda_id', $conta->venda_id ?? '') }}">
        </div>
        @error('venda_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Valor</label>
        <input type="text" name="valor"
            class="form-control input-moeda @error('valor') is-invalid @enderror"
            value="{{ old('valor', isset($conta) && $conta ? number_format($conta->valor, 2, ',', '.') : '') }}"
            placeholder="0,00" required>
        @error('valor')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Vencimento</label>
        <input type="date" name="data_vencimento"
            class="form-control @error('data_vencimento') is-invalid @enderror"
            value="{{ old('data_vencimento', $conta ? $conta->data_vencimento?->format('Y-m-d') : date('Y-m-d')) }}"
            required>
        @error('data_vencimento')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-2" style="margin-top:1rem;">
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
(function ($) {
    function formatVenda(item) {
        var total = parseFloat(item.total || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        var cliente = item.cliente && item.cliente.nome ? ' — ' + item.cliente.nome : '';
        return '#' + item.numero + cliente + ' — R$ ' + total;
    }

    initAutocomplete('#input-cliente', '#hidden-cliente-id', '{{ route('api.clientes.busca') }}', function (item) {
        var doc = item.cpf || item.cnpj || '';
        return item.nome + (doc ? ' — ' + doc : '');
    }, function () {
        $('#input-venda').val('');
        $('#hidden-venda-id').val('');
    });

    initAutocomplete('#input-venda', '#hidden-venda-id', '{{ route('api.vendas.busca') }}', formatVenda, null, function () {
        var clienteId = $('#hidden-cliente-id').val();
        return clienteId ? { cliente_id: clienteId } : {};
    });

    initAutocomplete('#input-forma-pagamento', '#hidden-forma-pagamento', '{{ route('api.formas-pagamento.busca') }}', function (item) {
        return item.nome;
    }, null, null, 'nome');
})(jQuery);
</script>
@endpush
