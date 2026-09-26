{{-- Formulário de Entrada de Estoque --}}
@php $entrada = $entrada ?? null; @endphp
<div class="form-grid form-grid--col-3">
    <div class="form-group">
        <label class="form-label">Fornecedor</label>
        <div class="autocomplete-wrap">
            <input type="text" id="input-fornecedor" class="form-control"
                value="{{ old('fornecedor_nome', $entrada && $entrada->fornecedor ? $entrada->fornecedor->nome : '') }}"
                placeholder="Digite para buscar..." autocomplete="off">
            <input type="hidden" name="fornecedor_id" id="hidden-fornecedor-id"
                value="{{ old('fornecedor_id', $entrada->fornecedor_id ?? '') }}">
        </div>
        @error('fornecedor_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Número da NF</label>
        <input type="text" name="numero"
            class="form-control @error('numero') is-invalid @enderror"
            value="{{ old('numero', $entrada->numero ?? '') }}"
            maxlength="20" placeholder="Ex: 12345">
        @error('numero')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Data da Entrada</label>
        <input type="date" name="data_entrada"
            class="form-control @error('data_entrada') is-invalid @enderror"
            value="{{ old('data_entrada', $entrada ? $entrada->data_entrada?->format('Y-m-d') : date('Y-m-d')) }}">
        @error('data_entrada')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

{{-- Itens --}}
<div class="itens-panel">
    <div class="section-toolbar">
        <span class="section-toolbar__title">Itens da Entrada</span>
        <button type="button" class="btn btn--primary btn--sm" id="btn-add-item">+ Adicionar Item</button>
    </div>

    @error('itens')<span class="form-error">{{ $message }}</span>@enderror

    <div class="itens-list" id="itens-body">
        @php
            $itensExistentes = $entrada ? $entrada->itens : collect();
        @endphp
        @foreach($itensExistentes as $i => $item)
            <div class="item-card item-row">
                <div class="item-card__top">
                    <span class="item-card__badge">Item <span class="item-card__num">{{ $i + 1 }}</span></span>
                    <button type="button" class="btn btn--ghost btn--sm btn-remove-item" title="Remover item">Remover</button>
                </div>

                <div class="form-group item-card__produto">
                    <label class="form-label form-label--required">Produto</label>
                    <div class="autocomplete-wrap">
                        <input type="text" class="form-control input-produto-nome"
                            value="{{ $item->produto_nome }}"
                            placeholder="Buscar produto..." autocomplete="off">
                        <input type="hidden" name="itens[{{ $i }}][produto_id]"
                            class="input-produto-id" value="{{ $item->produto_id }}">
                    </div>
                </div>

                <div class="item-card__grid">
                    <div class="form-group">
                        <label class="form-label">Qtd.</label>
                        @include('components.input-quantity', [
                            'name' => 'itens[' . $i . '][quantidade]',
                            'value' => old('itens.' . $i . '.quantidade', number_format($item->quantidade, 2, ',', '.')),
                            'class' => 'text-right input-qtd'
                        ])
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço Unit.</label>
                        <input type="tel" name="itens[{{ $i }}][preco_unitario]"
                            class="form-control text-right input-preco input-numeric"
                            inputmode="numeric"
                            data-decimals="2"
                            value="{{ number_format($item->preco_unitario, 2, ',', '.') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Desconto</label>
                        <input type="tel" name="itens[{{ $i }}][desconto]"
                            class="form-control text-right input-desc-item input-numeric"
                            inputmode="numeric"
                            data-decimals="2"
                            value="{{ number_format($item->desconto, 2, ',', '.') }}">
                    </div>
                </div>

                <div class="item-card__footer">
                    <span class="item-card__footer-label">Total do item</span>
                    <strong class="span-total-item">R$ {{ number_format($item->total, 2, ',', '.') }}</strong>
                </div>
            </div>
        @endforeach
    </div>

    <div class="itens-empty" id="itens-empty" @if($itensExistentes->count()) hidden @endif>
        Nenhum item adicionado. Toque em <strong>+ Adicionar Item</strong> para começar.
    </div>
</div>

{{-- Totais --}}
<div class="totals-wrap">
    <div class="totals-box totals-box--sale">
        <div class="totals-box__row">
            <span class="text-muted">Subtotal</span>
            <span id="label-subtotal">R$ 0,00</span>
            <input type="hidden" name="subtotal" id="input-subtotal" value="{{ old('subtotal', $entrada->subtotal ?? '0') }}">
        </div>
        <div class="totals-box__row">
            <span class="text-muted">Desconto (R$)</span>
            <input type="tel" name="desconto" id="input-desconto"
                class="form-control text-right totals-box__input input-numeric"
                data-decimals="2"
                inputmode="numeric"
                value="{{ old('desconto', number_format($entrada->desconto ?? 0, 2, ',', '.')) }}">
        </div>
        <div class="totals-box__row totals-box__row--total">
            <span>Total da entrada</span>
            <span id="label-total">R$ 0,00</span>
            <input type="hidden" name="total" id="input-total" value="{{ old('total', $entrada->total ?? '0') }}">
        </div>
    </div>
</div>

<div class="form-grid form-grid--col-1" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror"
            rows="2" style="resize:none;" maxlength="1000">{{ old('observacoes', $entrada->observacoes ?? '') }}</textarea>
        @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

@push('scripts')
<script>
(function ($) {
    var urlProdutos = '{{ route('api.produtos.busca') }}?apenas_controlados=1';
    var indice = {{ $entrada ? $entrada->itens->count() : 0 }};

    initAutocomplete('#input-fornecedor', '#hidden-fornecedor-id', '{{ route('api.fornecedores.busca') }}', function (item) {
        var doc = item.cpf || item.cnpj || '';
        return item.nome + (doc ? ' — ' + doc : '');
    });

    function parseBR(val) {
        if (typeof window.parseBR === 'function') return window.parseBR(val);
        if (val === null || typeof val === 'undefined' || val === '') return 0;
        var str = String(val).trim();
        if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
            str = str.replace(/\./g, '').replace(',', '.');
        } else if (str.indexOf(',') !== -1) {
            str = str.replace(',', '.');
        }
        return parseFloat(str) || 0;
    }

    function formatBR(val) {
        return 'R$ ' + val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatInput(val) {
        return val.toFixed(2).replace('.', ',');
    }

    function atualizarEmpty() {
        var empty = document.getElementById('itens-empty');
        var count = document.querySelectorAll('#itens-body .item-row').length;
        if (empty) empty.hidden = count > 0;

        document.querySelectorAll('#itens-body .item-row').forEach(function (row, i) {
            var num = row.querySelector('.item-card__num');
            if (num) num.textContent = String(i + 1);
        });
    }

    function calcularLinha(row) {
        var qtd   = parseBR(row.querySelector('.input-qtd').value);
        var preco = parseBR(row.querySelector('.input-preco').value);
        var desc  = parseBR(row.querySelector('.input-desc-item').value);
        var total = Math.max(0, (qtd * preco) - desc);
        row.querySelector('.span-total-item').textContent = formatBR(total);
        return total;
    }

    function recalcularTotais() {
        var subtotal = 0;
        document.querySelectorAll('#itens-body .item-row').forEach(function (row) {
            subtotal += calcularLinha(row);
        });

        var desconto = parseBR(document.getElementById('input-desconto').value);
        var total    = Math.max(0, subtotal - desconto);

        document.getElementById('label-subtotal').textContent = formatBR(subtotal);
        document.getElementById('label-total').textContent    = formatBR(total);
        document.getElementById('input-subtotal').value       = subtotal.toFixed(2);
        document.getElementById('input-total').value          = total.toFixed(2);
    }

    function criarLinha(idx) {
        var card = document.createElement('div');
        card.className = 'item-card item-row';
        card.innerHTML =
            '<div class="item-card__top">' +
                '<span class="item-card__badge">Item <span class="item-card__num"></span></span>' +
                '<button type="button" class="btn btn--ghost btn--sm btn-remove-item" title="Remover item">Remover</button>' +
            '</div>' +
            '<div class="form-group item-card__produto">' +
                '<label class="form-label form-label--required">Produto</label>' +
                '<div class="autocomplete-wrap">' +
                    '<input type="text" class="form-control input-produto-nome" placeholder="Buscar produto..." autocomplete="off">' +
                    '<input type="hidden" name="itens[' + idx + '][produto_id]" class="input-produto-id" value="">' +
                '</div>' +
            '</div>' +
            '<div class="item-card__grid">' +
                '<div class="form-group">' +
                    '<label class="form-label">Qtd.</label>' +
                    '<input type="tel" name="itens[' + idx + '][quantidade]" class="form-control text-right input-qtd input-quantity input-numeric" inputmode="numeric" data-decimals="2" value="1,00">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Preço Unit.</label>' +
                    '<input type="tel" name="itens[' + idx + '][preco_unitario]" class="form-control text-right input-preco input-numeric" inputmode="numeric" data-decimals="2" value="0,00">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Desconto</label>' +
                    '<input type="tel" name="itens[' + idx + '][desconto]" class="form-control text-right input-desc-item input-numeric" inputmode="numeric" data-decimals="2" value="0,00">' +
                '</div>' +
            '</div>' +
            '<div class="item-card__footer">' +
                '<span class="item-card__footer-label">Total do item</span>' +
                '<strong class="span-total-item">R$ 0,00</strong>' +
            '</div>';

        return card;
    }

    function bindLinha(row) {
        initProdutoLinhaAutocomplete(row, urlProdutos, function (item, $row) {
            var preco = parseFloat(item.preco_custo || item.preco_venda || item.preco || 0);
            $row.find('.input-preco').val(formatInput(preco));
            calcularLinha($row[0]);
            recalcularTotais();
        });

        ['input-qtd', 'input-preco', 'input-desc-item'].forEach(function (cls) {
            row.querySelector('.' + cls).addEventListener('input', function () {
                calcularLinha(row);
                recalcularTotais();
            });
        });

        row.querySelector('.btn-remove-item').addEventListener('click', function () {
            row.remove();
            atualizarEmpty();
            recalcularTotais();
        });
    }

    document.querySelectorAll('#itens-body .item-row').forEach(bindLinha);

    document.getElementById('btn-add-item').addEventListener('click', function () {
        var row = criarLinha(indice++);
        document.getElementById('itens-body').appendChild(row);
        bindLinha(row);
        atualizarEmpty();
        recalcularTotais();
        var produto = row.querySelector('.input-produto-nome');
        if (produto) produto.focus();
    });

    document.getElementById('input-desconto').addEventListener('input', recalcularTotais);

    atualizarEmpty();
    recalcularTotais();
})(jQuery);
</script>
@endpush
