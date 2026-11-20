{{-- Formulário de Entrada de Estoque --}}
@php
    $entrada = $entrada ?? null;
    $dadosXml = $dadosXml ?? null;
    $observacoesXml = $observacoesXml ?? null;
    $pedidoCompra = $pedidoCompra ?? $entrada?->pedidoCompra;
    $itensCompra = $itensCompra ?? collect();
    $decimaisQuantidade = $pedidoCompra ? 3 : 2;
@endphp

@pushOnce('styles', 'css-entrada-estoque')
    <link rel="stylesheet" href="{{ asset('css/entrada-estoque.css') }}?v={{ filemtime(public_path('css/entrada-estoque.css')) }}">
@endPushOnce
@if($dadosXml)
    <input type="hidden" name="chave_acesso_xml" value="{{ $dadosXml['nota']['chave_acesso'] ?? '' }}">
@endif
@if($pedidoCompra)
    <input type="hidden" name="pedido_compra_id" value="{{ $pedidoCompra->id }}">
@elseif($entrada?->pedido_compra_id)
    <input type="hidden" name="pedido_compra_id" value="{{ $entrada->pedido_compra_id }}">
@endif
<div class="form-section">
    <div class="form-section__title">Nota do fornecedor</div>
    <div class="form-grid form-grid--col-3">
        <div class="form-group">
            <label class="form-label">Fornecedor</label>
            <div class="autocomplete-wrap">
                <input type="text" id="input-fornecedor" class="form-control"
                    value="{{ old('fornecedor_nome', $entrada && $entrada->fornecedor ? $entrada->fornecedor->nome : ($pedidoCompra?->fornecedor?->nome ?? '')) }}"
                    placeholder="Digite para buscar..." autocomplete="off">
                <input type="hidden" name="fornecedor_id" id="hidden-fornecedor-id"
                    value="{{ old('fornecedor_id', $entrada->fornecedor_id ?? $pedidoCompra?->fornecedor_id ?? '') }}">
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
            <label class="form-label form-label--required">Data da entrada</label>
            <input type="date" name="data_entrada"
                class="form-control @error('data_entrada') is-invalid @enderror"
                value="{{ old('data_entrada', $entrada ? $entrada->data_entrada?->format('Y-m-d') : date('Y-m-d')) }}">
            @error('data_entrada')<span class="form-error">{{ $message }}</span>@enderror
        </div>
    </div>
</div>

{{-- Itens --}}
<div class="form-section">
    <div class="form-section__title">
        Itens
        @if($pedidoCompra)
            <span class="text-muted">— Pedido {{ $pedidoCompra->numero }} (recebimento parcial permitido)</span>
        @endif
    </div>
    <div class="itens-panel">
    <div class="section-toolbar">
        <button type="button" class="btn btn--primary btn--sm" id="btn-add-item">+ Adicionar item</button>
    </div>

    @error('itens')<span class="form-error">{{ $message }}</span>@enderror

    <div class="itens-list" id="itens-body">
        @php
            $itensExistentes = $entrada ? $entrada->itens : $itensCompra;
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
                            'value' => old('itens.' . $i . '.quantidade', number_format($item->quantidade, $decimaisQuantidade, ',', '.')),
                            'decimals' => $decimaisQuantidade,
                            'class' => 'text-right input-qtd'
                        ])
                    </div>
                    <div class="form-group">
                        <label class="form-label">Preço unit.</label>
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
    // Continua depois dos itens já desenhados (da entrada ou do pedido de compra), sem repetir índice.
    var indice = {{ $itensExistentes->count() }};
    var decimaisQuantidade = {{ $decimaisQuantidade }};

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

    function formatQuantidade(val) {
        return val.toFixed(decimaisQuantidade).replace('.', ',');
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
                    '<input type="hidden" name="itens[' + idx + '][produto_nome]" class="input-produto-nome-hidden" value="">' +
                    '<input type="hidden" name="itens[' + idx + '][produto_codigo]" class="input-produto-codigo-hidden" value="">' +
                '</div>' +
            '</div>' +
            '<div class="item-card__grid">' +
                '<div class="form-group">' +
                    '<label class="form-label">Qtd.</label>' +
                    '<input type="tel" name="itens[' + idx + '][quantidade]" class="form-control text-right input-qtd input-quantity input-numeric" inputmode="numeric" data-decimals="' + decimaisQuantidade + '" value="' + formatQuantidade(1) + '">' +
                '</div>' +
                '<div class="form-group">' +
                    '<label class="form-label">Preço unit.</label>' +
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

    // Expõe função para adicionar linha programaticamente (usado pelo XML)
    window.adicionarItemProgramatico = function (dados) {
        var row = criarLinha(indice++);
        document.getElementById('itens-body').appendChild(row);
        bindLinha(row);

        var inputProdutoId = row.querySelector('.input-produto-id');
        var inputProdutoNome = row.querySelector('.input-produto-nome');
        var inputProdutoNomeHidden = row.querySelector('.input-produto-nome-hidden');
        var inputProdutoCodigoHidden = row.querySelector('.input-produto-codigo-hidden');

        if (dados.produto_id) {
            inputProdutoId.value = dados.produto_id;
        }
        if (dados.produto_nome) {
            inputProdutoNome.value = dados.produto_nome;
            inputProdutoNomeHidden.value = dados.produto_nome;
        }
        if (dados.produto_codigo) {
            inputProdutoCodigoHidden.value = dados.produto_codigo;
        }
        if (dados.quantidade) {
            row.querySelector('.input-qtd').value = formatQuantidade(parseFloat(dados.quantidade));
        }
        if (dados.preco_unitario) {
            row.querySelector('.input-preco').value = formatInput(parseFloat(dados.preco_unitario));
        }
        if (dados.desconto) {
            row.querySelector('.input-desc-item').value = formatInput(parseFloat(dados.desconto));
        }

        // Marca produto não encontrado e cria bloco de vinculação
        if (!dados.produto_encontrado) {
            row.setAttribute('data-produto-nao-encontrado', '1');
            row.classList.add('item-row--pending');

            var produtoWrap = row.querySelector('.autocomplete-wrap');
            if (produtoWrap) {
                // Guarda o nome original do XML
                var nomeXml = dados.produto_nome || '';
                inputProdutoNome.setAttribute('data-nome-xml', nomeXml);

                // Cria o bloco de aviso com opção de vincular
                var avisoBox = document.createElement('div');
                avisoBox.className = 'produto-pendente-box';
                avisoBox.innerHTML =
                    '<div class="produto-pendente-box__header">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<circle cx="12" cy="12" r="10"></circle>' +
                            '<line x1="12" y1="8" x2="12" y2="12"></line>' +
                            '<line x1="12" y1="16" x2="12.01" y2="16"></line>' +
                        '</svg>' +
                        '<span class="produto-pendente-box__msg">Produto não encontrado no cadastro</span>' +
                    '</div>' +
                    '<div class="produto-pendente-box__body">' +
                        '<span class="produto-pendente-box__label">Nome na NF-e:</span>' +
                        '<span class="produto-pendente-box__value">' + escapeHtml(nomeXml) + '</span>' +
                    '</div>' +
                    '<div class="produto-pendente-box__actions">' +
                        '<button type="button" class="btn btn--ghost btn--xs btn-vincular-produto">' +
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                                '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>' +
                                '<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>' +
                            '</svg>' +
                            '<span class="btn-vincular-texto">Vincular produto do cadastro</span>' +
                        '</button>' +
                        '<button type="button" class="btn btn--ghost btn--xs btn-cancelar-vincular" style="display:none;">' +
                            'Cancelar' +
                        '</button>' +
                    '</div>';

                produtoWrap.after(avisoBox);

                // Esconde o input original e mostra o nome do XML
                inputProdutoNome.style.display = 'none';
                var displayNome = document.createElement('div');
                displayNome.className = 'produto-nome-xml';
                displayNome.textContent = nomeXml;
                produtoWrap.insertBefore(displayNome, inputProdutoNome);

                var btnVincular = avisoBox.querySelector('.btn-vincular-produto');
                var btnCancelar = avisoBox.querySelector('.btn-cancelar-vincular');
                var msgHeader = avisoBox.querySelector('.produto-pendente-box__msg');

                // Função para voltar ao estado inicial
                function voltarEstadoInicial() {
                    displayNome.style.display = '';
                    inputProdutoNome.style.display = 'none';
                    inputProdutoNome.value = nomeXml;
                    btnVincular.style.display = '';
                    btnCancelar.style.display = 'none';
                    msgHeader.textContent = 'Produto não encontrado no cadastro';
                    avisoBox.classList.remove('produto-pendente-box--linking');
                }

                // Botão vincular - mostra o autocomplete
                btnVincular.addEventListener('click', function() {
                    displayNome.style.display = 'none';
                    inputProdutoNome.style.display = '';
                    inputProdutoNome.value = '';
                    inputProdutoNome.placeholder = 'Digite para buscar produto...';
                    inputProdutoNome.focus();
                    btnVincular.style.display = 'none';
                    btnCancelar.style.display = '';
                    msgHeader.textContent = 'Buscando produto para vincular...';
                    avisoBox.classList.add('produto-pendente-box--linking');
                });

                // Botão cancelar - volta ao estado inicial
                btnCancelar.addEventListener('click', function() {
                    voltarEstadoInicial();
                });

                // Quando o input perder foco sem selecionar, volta ao estado inicial
                $(inputProdutoNome).on('blur', function() {
                    setTimeout(function() {
                        // Só volta se não tiver produto selecionado e estiver no modo de vincular
                        if (!inputProdutoId.value && avisoBox.classList.contains('produto-pendente-box--linking')) {
                            voltarEstadoInicial();
                        }
                    }, 300); // Delay para permitir clique no autocomplete
                });

                // Quando selecionar um produto do autocomplete
                $(inputProdutoNome).on('autocomplete:select', function(e, item) {
                    if (item && item.id) {
                        // Remove o aviso e marca como vinculado
                        avisoBox.remove();
                        displayNome.remove();
                        row.classList.remove('item-row--pending');
                        row.removeAttribute('data-produto-nao-encontrado');

                        // Mostra badge de vinculado
                        var badge = document.createElement('div');
                        badge.className = 'produto-vinculado-badge';
                        badge.innerHTML =
                            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                                '<polyline points="20 6 9 17 4 12"></polyline>' +
                            '</svg>' +
                            '<span>Vinculado ao produto: ' + escapeHtml(item.nome || item.text || '') + '</span>';
                        produtoWrap.after(badge);

                        // Atualiza preço se disponível
                        var preco = parseFloat(item.preco_custo || item.preco_venda || 0);
                        if (preco > 0) {
                            row.querySelector('.input-preco').value = formatInput(preco);
                        }
                        calcularLinha(row);
                        recalcularTotais();
                    }
                });
            }
        }

        calcularLinha(row);
        atualizarEmpty();
        recalcularTotais();

        return row;
    };

    // Helper para escapar HTML
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
})(jQuery);
</script>

{{-- Pre-fill XML data --}}
@if($dadosXml)
<script>
(function ($) {
    $(document).ready(function () {
        // Preenche fornecedor
        @if(!empty($dadosXml['fornecedor_id']))
            $('#hidden-fornecedor-id').val('{{ $dadosXml['fornecedor_id'] }}');
        @endif
        @if(!empty($dadosXml['fornecedor_nome']))
            $('#input-fornecedor').val({!! json_encode($dadosXml['fornecedor_nome']) !!});
        @endif

        // Preenche data de entrada
        @if(!empty($dadosXml['nota']['data_emissao']))
            $('input[name="data_entrada"]').val('{{ $dadosXml['nota']['data_emissao'] }}');
        @endif

        // Preenche número da NF
        @if(!empty($dadosXml['nota']['numero']))
            $('input[name="numero"]').val('{{ $dadosXml['nota']['numero'] }}');
        @endif

        // Preenche observações
        @if(!empty($observacoesXml))
            $('textarea[name="observacoes"]').val({!! json_encode($observacoesXml) !!});
        @endif

        // Adiciona itens
        @foreach($dadosXml['itens'] as $item)
            window.adicionarItemProgramatico({
                produto_id: {!! json_encode($item['produto_id'] ?? null) !!},
                produto_nome: {!! json_encode($item['descricao'] ?? '') !!},
                produto_codigo: {!! json_encode($item['codigo_produto_fornecedor'] ?? '') !!},
                quantidade: {{ $item['quantidade'] ?? 1 }},
                preco_unitario: {{ $item['preco_unitario'] ?? 0 }},
                desconto: {{ $item['desconto_item'] ?? 0 }},
                produto_encontrado: {{ ($item['produto_encontrado'] ?? false) ? 'true' : 'false' }}
            });
        @endforeach
    });
})(jQuery);
</script>
@endif
@endpush
