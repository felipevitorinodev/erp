@php
    $os = $os ?? null;
    $itensIniciais = $itensIniciais ?? [];
    $fmt = fn ($v, $d = 2) => number_format((float) $v, $d, ',', '.');
@endphp

@if ($errors->any())
    <div class="alert alert--error" style="margin-bottom:1rem;">
        Corrija os campos destacados:
        <ul style="margin:0.25rem 0 0 1.25rem;">
            @foreach ($errors->all() as $erro)
                <li>{{ $erro }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card">
    <div class="card__header">
        <span class="card__title">Cliente e atendimento</span>
    </div>
    <div class="card__body">
        <div class="form-grid form-grid--col-4">
            <div class="form-group form-group--span-2">
                <label class="form-label">Cliente</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-cliente" class="form-control"
                        value="{{ old('cliente_nome', $os?->cliente?->nome ?? '') }}"
                        placeholder="Digite para buscar..." autocomplete="off">
                    <input type="hidden" name="cliente_id" id="hidden-cliente-id"
                        value="{{ old('cliente_id', $os->cliente_id ?? '') }}">
                    <input type="hidden" name="cliente_nome" id="hidden-cliente-nome"
                        value="{{ old('cliente_nome', $os?->cliente?->nome ?? '') }}">
                </div>
                @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group form-group--span-2">
                <label class="form-label">Técnico responsável</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-tecnico" class="form-control"
                        value="{{ old('tecnico_nome', $os?->tecnico?->nome ?? '') }}"
                        placeholder="Digite para buscar..." autocomplete="off">
                    <input type="hidden" name="funcionario_id" id="hidden-tecnico-id"
                        value="{{ old('funcionario_id', $os->funcionario_id ?? '') }}">
                    <input type="hidden" name="tecnico_nome" id="hidden-tecnico-nome"
                        value="{{ old('tecnico_nome', $os?->tecnico?->nome ?? '') }}">
                </div>
                @error('funcionario_id')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label form-label--required">Abertura</label>
                <input type="date" name="data_abertura"
                    class="form-control @error('data_abertura') is-invalid @enderror"
                    value="{{ old('data_abertura', $os?->data_abertura?->format('Y-m-d') ?? date('Y-m-d')) }}">
                @error('data_abertura')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Previsão de entrega</label>
                <input type="date" name="data_previsao"
                    class="form-control @error('data_previsao') is-invalid @enderror"
                    value="{{ old('data_previsao', $os?->data_previsao?->format('Y-m-d') ?? '') }}">
                @error('data_previsao')<span class="form-error">{{ $message }}</span>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <div class="card__header">
        <span class="card__title">Equipamento</span>
    </div>
    <div class="card__body">
        <div class="form-grid form-grid--col-4">
            <div class="form-group form-group--span-2">
                <label class="form-label form-label--required">Equipamento / objeto</label>
                <input type="text" name="equipamento" maxlength="150"
                    class="form-control @error('equipamento') is-invalid @enderror"
                    value="{{ old('equipamento', $os->equipamento ?? '') }}"
                    placeholder="Ex.: Notebook, celular, impressora...">
                @error('equipamento')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Marca</label>
                <input type="text" name="marca" maxlength="80" class="form-control"
                    value="{{ old('marca', $os->marca ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">Modelo</label>
                <input type="text" name="modelo" maxlength="80" class="form-control"
                    value="{{ old('modelo', $os->modelo ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">Nº de série</label>
                <input type="text" name="numero_serie" maxlength="80" class="form-control"
                    value="{{ old('numero_serie', $os->numero_serie ?? '') }}">
            </div>
            <div class="form-group form-group--span-3">
                <label class="form-label">Acessórios entregues</label>
                <input type="text" name="acessorios" maxlength="255" class="form-control"
                    value="{{ old('acessorios', $os->acessorios ?? '') }}"
                    placeholder="Ex.: carregador, capa, cabo USB">
            </div>
        </div>

        <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
            <div class="form-group">
                <label class="form-label">Defeito relatado</label>
                <textarea name="defeito_relatado" rows="4" maxlength="5000" style="resize:vertical;"
                    class="form-control @error('defeito_relatado') is-invalid @enderror"
                    placeholder="O que o cliente informou">{{ old('defeito_relatado', $os->defeito_relatado ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Diagnóstico</label>
                <textarea name="diagnostico" rows="4" maxlength="5000" style="resize:vertical;"
                    class="form-control @error('diagnostico') is-invalid @enderror"
                    placeholder="O que o técnico encontrou">{{ old('diagnostico', $os->diagnostico ?? '') }}</textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Serviço executado / laudo</label>
                <textarea name="servico_executado" rows="4" maxlength="5000" style="resize:vertical;"
                    class="form-control @error('servico_executado') is-invalid @enderror"
                    placeholder="O que foi feito">{{ old('servico_executado', $os->servico_executado ?? '') }}</textarea>
            </div>
        </div>
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <div class="card__body">
        <div class="itens-panel">
            <div class="section-toolbar">
                <span class="section-toolbar__title">Peças e serviços</span>
                <button type="button" class="btn btn--primary btn--sm" id="btn-add-item">+ Adicionar item</button>
            </div>

            @error('itens')<span class="form-error">{{ $message }}</span>@enderror

            <div class="itens-list" id="itens-body">
                @foreach ($itensIniciais as $i => $item)
                    <div class="item-card item-row">
                        <div class="item-card__top">
                            <span class="item-card__badge">Item <span class="item-card__num">{{ $i + 1 }}</span></span>
                            <button type="button" class="btn btn--ghost btn--sm btn-remove-item" title="Remover item">Remover</button>
                        </div>

                        <div class="form-group item-card__produto">
                            <label class="form-label form-label--required">Peça ou serviço</label>
                            <div class="autocomplete-wrap">
                                <input type="text" class="form-control input-produto-nome"
                                    value="{{ $item['produto_nome'] }}"
                                    placeholder="Buscar produto ou serviço..." autocomplete="off">
                                <input type="hidden" name="itens[{{ $i }}][produto_id]"
                                    class="input-produto-id" value="{{ $item['produto_id'] }}">
                            </div>
                        </div>

                        <div class="item-card__grid">
                            <div class="form-group">
                                <label class="form-label">Qtd.</label>
                                @include('components.input-quantity', [
                                    'name' => 'itens[' . $i . '][quantidade]',
                                    'value' => $fmt($item['quantidade']),
                                    'class' => 'text-right input-qtd',
                                ])
                            </div>
                            <div class="form-group">
                                <label class="form-label">Preço unit.</label>
                                <input type="tel" name="itens[{{ $i }}][preco_unitario]"
                                    class="form-control text-right input-preco input-numeric"
                                    data-decimals="2" inputmode="numeric"
                                    value="{{ $fmt($item['preco_unitario']) }}">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Desconto</label>
                                <input type="tel" name="itens[{{ $i }}][desconto]"
                                    class="form-control text-right input-desc-item input-numeric"
                                    data-decimals="2" inputmode="numeric"
                                    value="{{ $fmt($item['desconto']) }}">
                            </div>
                        </div>

                        <div class="item-card__footer">
                            <span class="item-card__footer-label">Total do item</span>
                            <strong class="span-total-item">R$ 0,00</strong>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="itens-empty" id="itens-empty" @if (count($itensIniciais)) hidden @endif>
                Nenhuma peça ou serviço ainda. Você pode abrir a OS só com o defeito e lançar os itens depois do diagnóstico.
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
                    <input type="text" name="desconto" id="input-desconto"
                        class="form-control text-right totals-box__input input-numeric"
                        data-decimals="2" inputmode="numeric"
                        value="{{ old('desconto', $fmt($os->desconto ?? 0)) }}">
                </div>
                <div class="totals-box__row">
                    <span class="text-muted">Acréscimo (R$)</span>
                    <input type="text" name="acrescimo" id="input-acrescimo"
                        class="form-control text-right totals-box__input input-numeric"
                        data-decimals="2" inputmode="numeric"
                        value="{{ old('acrescimo', $fmt($os->acrescimo ?? 0)) }}">
                </div>
                <div class="totals-box__row totals-box__row--total">
                    <span>Total da OS</span>
                    <span id="label-total">R$ 0,00</span>
                </div>
            </div>
        </div>
        @error('desconto')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="card" style="margin-top:1rem;">
    <div class="card__header">
        <span class="card__title">Pagamento e garantia</span>
    </div>
    <div class="card__body">
        <div class="form-grid form-grid--col-2">
            <div class="form-group">
                <label class="form-label">Forma de pagamento</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-forma-pagamento" class="form-control"
                        value="{{ old('forma_pagamento', $os->forma_pagamento ?? '') }}"
                        placeholder="Selecione a forma de pagamento..." autocomplete="off">
                    <input type="hidden" name="forma_pagamento" id="hidden-forma-pagamento"
                        value="{{ old('forma_pagamento', $os->forma_pagamento ?? '') }}">
                </div>
                <small class="text-muted">Ao faturar, forma à vista confirma a venda na hora; a prazo deixa a venda em andamento.</small>
            </div>
            <div class="form-group">
                <label class="form-label">Garantia</label>
                <input type="text" name="garantia" maxlength="100" class="form-control"
                    value="{{ old('garantia', $os->garantia ?? '') }}" placeholder="Ex.: 90 dias para o serviço executado">
            </div>
        </div>
        <div class="form-group" style="margin-top:1rem;">
            <label class="form-label">Observações</label>
            <textarea name="observacoes" class="form-control" rows="2" style="resize:vertical;"
                maxlength="5000">{{ old('observacoes', $os->observacoes ?? '') }}</textarea>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function ($) {
    var urlProdutos = @json(route('api.produtos.busca'));
    var indice = {{ count($itensIniciais) }};

    initAutocomplete('#input-cliente', '#hidden-cliente-id', @json(route('api.clientes.busca')), function (item) {
        var doc = item.cpf || item.cnpj || '';
        return item.nome + (doc ? ' — ' + doc : '');
    }, function (item) {
        $('#hidden-cliente-nome').val(item ? item.nome : '');
    });

    initAutocomplete('#input-tecnico', '#hidden-tecnico-id', @json(route('api.funcionarios.busca')), function (item) {
        return item.nome;
    }, function (item) {
        $('#hidden-tecnico-nome').val(item ? item.nome : '');
    });

    $('#input-cliente').on('input', function () { $('#hidden-cliente-nome').val(''); });
    $('#input-tecnico').on('input', function () { $('#hidden-tecnico-nome').val(''); });

    initAutocomplete('#input-forma-pagamento', '#hidden-forma-pagamento', @json(route('api.formas-pagamento.busca')), function (item) {
        return item.nome;
    }, null, null, 'nome');

    function parseBR(val) {
        if (typeof window.parseBR === 'function') return window.parseBR(val);
        if (val === null || typeof val === 'undefined' || val === '') return 0;
        var str = String(val).trim();
        if (str.indexOf(',') !== -1) {
            str = str.replace(/\./g, '').replace(',', '.');
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
        var linhas = document.querySelectorAll('#itens-body .item-row');
        document.getElementById('itens-empty').hidden = linhas.length > 0;
        linhas.forEach(function (row, i) {
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

        var desconto  = parseBR(document.getElementById('input-desconto').value);
        var acrescimo = parseBR(document.getElementById('input-acrescimo').value);

        document.getElementById('label-subtotal').textContent = formatBR(subtotal);
        document.getElementById('label-total').textContent = formatBR(Math.max(0, subtotal - desconto + acrescimo));
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
                '<label class="form-label form-label--required">Peça ou serviço</label>' +
                '<div class="autocomplete-wrap">' +
                    '<input type="text" class="form-control input-produto-nome" placeholder="Buscar produto ou serviço..." autocomplete="off">' +
                    '<input type="hidden" name="itens[' + idx + '][produto_id]" class="input-produto-id" value="">' +
                '</div>' +
            '</div>' +
            '<div class="item-card__grid">' +
                '<div class="form-group">' +
                    '<label class="form-label">Qtd.</label>' +
                    '<input type="tel" name="itens[' + idx + '][quantidade]" class="form-control text-right input-qtd input-quantity input-numeric" inputmode="numeric" data-decimals="2" value="1,00">' +
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
            var preco = parseFloat(item.preco_venda || item.preco || 0);
            $row.find('.input-preco').val(formatInput(preco));
            recalcularTotais();
        });

        ['input-qtd', 'input-preco', 'input-desc-item'].forEach(function (cls) {
            row.querySelector('.' + cls).addEventListener('input', recalcularTotais);
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

    ['input-desconto', 'input-acrescimo'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', recalcularTotais);
    });

    atualizarEmpty();
    recalcularTotais();
})(jQuery);
</script>
@endpush
