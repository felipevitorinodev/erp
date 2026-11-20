@php
    $pedido = $pedido ?? null;
    // Depois de um erro de validação, os itens voltam como foram enviados (inclusive na edição).
    // values(): índices 0..n-1, para o próximo item criado no navegador não reaproveitar um índice já usado.
    // O valor antigo volta como foi digitado ("1.234,50"); mesma leitura do PedidoCompraRequest.
    $numeroDigitado = function ($valor): float {
        $valor = str_replace(' ', '', trim((string) $valor));
        $valor = str_contains($valor, ',') && str_contains($valor, '.') ? str_replace('.', '', $valor) : $valor;

        return (float) str_replace(',', '.', $valor);
    };
    $itensAntigos = old('itens');
    $itens = is_array($itensAntigos)
        ? collect($itensAntigos)->map(function ($item, $chave) use ($numeroDigitado) {
            $item = is_array($item) ? $item : [];
            $quantidade = $numeroDigitado($item['quantidade'] ?? 0);
            $preco = $numeroDigitado($item['preco_unitario'] ?? 0);
            $desconto = $numeroDigitado($item['desconto'] ?? 0);

            return (object) [
                'chave' => $chave,
                'produto_id' => $item['produto_id'] ?? null,
                'produto_nome' => $item['produto_nome'] ?? '',
                'produto_codigo' => $item['produto_codigo'] ?? '',
                'quantidade' => $quantidade,
                'preco_unitario' => $preco,
                'desconto' => $desconto,
                'total' => max(0, $quantidade * $preco - $desconto),
            ];
        })->values()
        : collect($pedido?->itens ?? [])->values();
@endphp

@pushOnce('styles', 'css-entrada-estoque')
    <link rel="stylesheet" href="{{ asset('css/entrada-estoque.css') }}?v={{ filemtime(public_path('css/entrada-estoque.css')) }}">
@endPushOnce

<div class="form-section">
    <div class="form-section__title">Fornecedor e prazos</div>
    <div class="form-grid form-grid--col-3">
        <div class="form-group">
            <label class="form-label form-label--required">Fornecedor</label>
            <div class="autocomplete-wrap">
                <input type="text" id="input-fornecedor" name="fornecedor_nome" class="form-control"
                    value="{{ old('fornecedor_nome', $pedido?->fornecedor?->nome ?? '') }}"
                    placeholder="Digite para buscar..." autocomplete="off">
                <input type="hidden" name="fornecedor_id" id="hidden-fornecedor-id"
                    value="{{ old('fornecedor_id', $pedido?->fornecedor_id ?? '') }}">
            </div>
            @error('fornecedor_id')<span class="form-error">{{ $message }}</span>@enderror
        </div>
        <div class="form-group">
            <label class="form-label form-label--required">Data de emissão</label>
            <input type="date" name="data_emissao" class="form-control @error('data_emissao') is-invalid @enderror"
                value="{{ old('data_emissao', $pedido?->data_emissao?->format('Y-m-d') ?? date('Y-m-d')) }}">
            @error('data_emissao')<span class="form-error">{{ $message }}</span>@enderror
        </div>
        <div class="form-group">
            <label class="form-label">Previsão de entrega</label>
            <input type="date" name="data_prevista" class="form-control @error('data_prevista') is-invalid @enderror"
                value="{{ old('data_prevista', $pedido?->data_prevista?->format('Y-m-d') ?? '') }}">
            @error('data_prevista')<span class="form-error">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section__title">Itens</div>
    <div class="itens-panel">
        <div class="section-toolbar">
            <button type="button" class="btn btn--primary btn--sm" id="btn-add-item">+ Adicionar item</button>
        </div>
        @error('itens')<span class="form-error">{{ $message }}</span>@enderror
        <div class="itens-list" id="itens-body">
            @foreach($itens as $i => $item)
                <div class="item-card item-row">
                    <div class="item-card__top">
                        <span class="item-card__badge">Item <span class="item-card__num">{{ $i + 1 }}</span></span>
                        <button type="button" class="btn btn--ghost btn--sm btn-remove-item" title="Remover item">Remover</button>
                    </div>
                    <div class="form-group item-card__produto">
                        <label class="form-label form-label--required">Produto</label>
                        <div class="autocomplete-wrap">
                            <input type="text" class="form-control input-produto-nome"
                                value="{{ $item->produto_nome }}" placeholder="Buscar produto..." autocomplete="off">
                            <input type="hidden" name="itens[{{ $i }}][produto_id]" class="input-produto-id" value="{{ $item->produto_id }}">
                            <input type="hidden" name="itens[{{ $i }}][produto_nome]" class="input-produto-nome-hidden" value="{{ $item->produto_nome }}">
                            <input type="hidden" name="itens[{{ $i }}][produto_codigo]" class="input-produto-codigo-hidden" value="{{ $item->produto_codigo }}">
                        </div>
                    </div>
                    <div class="item-card__grid">
                        <div class="form-group">
                            <label class="form-label">Quantidade</label>
                            <input type="tel" name="itens[{{ $i }}][quantidade]"
                                class="form-control text-right input-qtd input-quantity input-numeric"
                                inputmode="numeric" data-decimals="3"
                                value="{{ number_format((float) $item->quantidade, 3, ',', '.') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Preço unit.</label>
                            <input type="tel" name="itens[{{ $i }}][preco_unitario]"
                                class="form-control text-right input-preco input-numeric" inputmode="numeric"
                                data-decimals="2"
                                value="{{ number_format((float) $item->preco_unitario, 2, ',', '.') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Desconto</label>
                            <input type="tel" name="itens[{{ $i }}][desconto]"
                                class="form-control text-right input-desc-item input-numeric" inputmode="numeric"
                                data-decimals="2"
                                value="{{ number_format((float) ($item->desconto ?? 0), 2, ',', '.') }}">
                        </div>
                    </div>
                    {{-- Erros vêm com o índice enviado pelo navegador; os itens foram renumerados acima. --}}
                    @if ($erroItem = $errors->first('itens.'.($item->chave ?? $i).'.*'))
                        <span class="form-error">{{ $erroItem }}</span>
                    @endif
                    <div class="item-card__footer">
                        <span class="item-card__footer-label">Total do item</span>
                        <strong class="span-total-item">R$ {{ number_format((float) $item->total, 2, ',', '.') }}</strong>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="itens-empty" id="itens-empty" @if($itens->count()) hidden @endif>
            Nenhum item adicionado. Toque em <strong>+ Adicionar item</strong> para começar.
        </div>
    </div>
</div>

<div class="totals-wrap">
    <div class="totals-box totals-box--sale">
        <div class="totals-box__row">
            <span class="text-muted">Subtotal</span>
            <span id="label-subtotal">R$ 0,00</span>
        </div>
        <div class="totals-box__row">
            <span class="text-muted">Desconto (R$)</span>
            <input type="tel" name="desconto" id="input-desconto"
                class="form-control text-right totals-box__input input-numeric" data-decimals="2" inputmode="numeric"
                value="{{ number_format($numeroDigitado(old('desconto', $pedido?->desconto ?? 0)), 2, ',', '.') }}">
        </div>
        <div class="totals-box__row totals-box__row--total">
            <span>Total do pedido</span>
            <span id="label-total">R$ 0,00</span>
        </div>
    </div>
</div>

<div class="form-grid form-grid--col-1" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control" rows="2" style="resize:none;" maxlength="1000">{{ old('observacoes', $pedido?->observacoes ?? '') }}</textarea>
        @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

@push('scripts')
<script>
(function () {
    var urlProdutos = '{{ route('api.produtos.busca') }}?apenas_controlados=0';
    var indice = {{ $itens->count() }};

    initAutocomplete('#input-fornecedor', '#hidden-fornecedor-id', '{{ route('api.fornecedores.busca') }}', function (item) {
        var doc = item.cpf || item.cnpj || '';
        return item.nome + (doc ? ' — ' + doc : '');
    });

    function parseBR(valor) {
        if (typeof window.parseBR === 'function') return window.parseBR(valor);
        var texto = String(valor || '').trim();
        if (texto.indexOf(',') !== -1 && texto.indexOf('.') !== -1) texto = texto.replace(/\./g, '').replace(',', '.');
        else if (texto.indexOf(',') !== -1) texto = texto.replace(',', '.');
        return parseFloat(texto) || 0;
    }

    function moeda(valor) {
        return 'R$ ' + valor.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function atualizarTotais() {
        var subtotal = 0;
        document.querySelectorAll('#itens-body .item-row').forEach(function (row, posicao) {
            var quantidade = parseBR(row.querySelector('.input-qtd').value);
            var preco = parseBR(row.querySelector('.input-preco').value);
            var desconto = parseBR(row.querySelector('.input-desc-item').value);
            var totalItem = Math.max(0, quantidade * preco - desconto);
            row.querySelector('.span-total-item').textContent = moeda(totalItem);
            row.querySelector('.item-card__num').textContent = String(posicao + 1);
            subtotal += totalItem;
        });
        var descontoPedido = parseBR(document.getElementById('input-desconto').value);
        document.getElementById('label-subtotal').textContent = moeda(subtotal);
        document.getElementById('label-total').textContent = moeda(Math.max(0, subtotal - descontoPedido));
        document.getElementById('itens-empty').hidden = document.querySelectorAll('#itens-body .item-row').length > 0;
    }

    function criarLinha(indiceLinha) {
        var row = document.createElement('div');
        row.className = 'item-card item-row';
        row.innerHTML =
            '<div class="item-card__top"><span class="item-card__badge">Item <span class="item-card__num"></span></span>' +
            '<button type="button" class="btn btn--ghost btn--sm btn-remove-item" title="Remover item">Remover</button></div>' +
            '<div class="form-group item-card__produto"><label class="form-label form-label--required">Produto</label>' +
            '<div class="autocomplete-wrap"><input type="text" class="form-control input-produto-nome" placeholder="Buscar produto..." autocomplete="off">' +
            '<input type="hidden" name="itens[' + indiceLinha + '][produto_id]" class="input-produto-id">' +
            '<input type="hidden" name="itens[' + indiceLinha + '][produto_nome]" class="input-produto-nome-hidden">' +
            '<input type="hidden" name="itens[' + indiceLinha + '][produto_codigo]" class="input-produto-codigo-hidden"></div></div>' +
            '<div class="item-card__grid"><div class="form-group"><label class="form-label">Quantidade</label>' +
            '<input type="tel" name="itens[' + indiceLinha + '][quantidade]" class="form-control text-right input-qtd input-quantity input-numeric" inputmode="numeric" data-decimals="3" value="1,000"></div>' +
            '<div class="form-group"><label class="form-label">Preço unit.</label><input type="tel" name="itens[' + indiceLinha + '][preco_unitario]" class="form-control text-right input-preco input-numeric" inputmode="numeric" data-decimals="2" value="0,00"></div>' +
            '<div class="form-group"><label class="form-label">Desconto</label><input type="tel" name="itens[' + indiceLinha + '][desconto]" class="form-control text-right input-desc-item input-numeric" inputmode="numeric" data-decimals="2" value="0,00"></div></div>' +
            '<div class="item-card__footer"><span class="item-card__footer-label">Total do item</span><strong class="span-total-item">R$ 0,00</strong></div>';
        return row;
    }

    function vincularLinha(row) {
        initProdutoLinhaAutocomplete(row, urlProdutos, function (item, $row) {
            // Nome e código acompanham o id: se o formulário voltar com erro, o item reaparece com o nome.
            $row.find('.input-produto-nome-hidden').val(item.nome || '');
            $row.find('.input-produto-codigo-hidden').val(item.codigo || '');
            var preco = parseFloat(item.preco_custo || item.preco_venda || item.preco || 0);
            $row.find('.input-preco').val(preco.toFixed(2).replace('.', ','));
            atualizarTotais();
        });
        row.querySelectorAll('.input-qtd, .input-preco, .input-desc-item').forEach(function (input) {
            input.addEventListener('input', atualizarTotais);
        });
        row.querySelector('.btn-remove-item').addEventListener('click', function () {
            row.remove();
            atualizarTotais();
        });
    }

    document.querySelectorAll('#itens-body .item-row').forEach(vincularLinha);
    document.getElementById('btn-add-item').addEventListener('click', function () {
        var row = criarLinha(indice++);
        document.getElementById('itens-body').appendChild(row);
        vincularLinha(row);
        atualizarTotais();
        row.querySelector('.input-produto-nome').focus();
    });
    document.getElementById('input-desconto').addEventListener('input', atualizarTotais);
    atualizarTotais();
})();
</script>
@endpush
