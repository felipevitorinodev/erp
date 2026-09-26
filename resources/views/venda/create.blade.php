@extends('layouts.app')

@section('title', 'Nova Venda')
@section('page_title', 'Nova Venda')

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a> / Nova Venda
@endsection

@section('content')

<form method="POST" action="{{ route('venda.store') }}" id="form-venda">
    @csrf
    <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">

    {{-- Barra superior fixa com totais --}}
    <div class="venda-topbar">
        <div class="venda-topbar__info">
            <div class="venda-topbar__cliente">
                <span class="venda-topbar__label">Cliente:</span>
                <span id="display-cliente">Consumidor Final</span>
            </div>
        </div>
        <div class="venda-topbar__total">
            <span class="venda-topbar__total-label">TOTAL</span>
            <span class="venda-topbar__total-valor" id="display-total">R$ 0,00</span>
        </div>
    </div>

    {{-- Área de busca rápida de produto --}}
    <div class="venda-busca-rapida">
        <div class="venda-busca-rapida__input-wrap">
            <svg class="venda-busca-rapida__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <input type="text" 
                   id="busca-produto-rapida" 
                   class="venda-busca-rapida__input" 
                   placeholder="Buscar produto por nome ou código (F2)..." 
                   autocomplete="off">
        </div>
        <div class="venda-busca-rapida__hints">
            <kbd>F2</kbd> Buscar &nbsp;|&nbsp; <kbd>F4</kbd> Finalizar &nbsp;|&nbsp; <kbd>F8</kbd> Cliente
        </div>
    </div>

    {{-- Itens da venda --}}
    <div class="venda-itens-container">
        <div class="venda-itens-header">
            <span class="venda-itens-header__title">Itens da Venda</span>
            <span class="venda-itens-header__count">(<span id="count-itens">0</span> itens)</span>
        </div>

        @error('itens')<div class="alert alert--error" style="margin-bottom:1rem;">{{ $message }}</div>@enderror

        <div class="venda-itens-list" id="itens-body">
            {{-- Itens serão adicionados dinamicamente --}}
        </div>

        <div class="venda-itens-empty" id="itens-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <path d="M16 10a4 4 0 0 1-8 0"></path>
            </svg>
            <p>Nenhum item adicionado</p>
            <span>Use a busca acima para adicionar produtos</span>
        </div>
    </div>

    {{-- Painel lateral / inferior com resumo --}}
    <div class="venda-resumo">
        <div class="venda-resumo__section venda-resumo__section--dados">
            <h4>Dados da Venda</h4>
            <div class="venda-resumo__field">
                <label>Cliente</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-cliente" class="form-control"
                        placeholder="Consumidor Final" autocomplete="off">
                    <input type="hidden" name="cliente_id" id="hidden-cliente-id" value="">
                </div>
            </div>
            <div class="venda-resumo__field">
                <label>Vendedor</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-vendedor" class="form-control"
                        placeholder="Selecione..." autocomplete="off">
                    <input type="hidden" name="funcionario_id" id="hidden-funcionario-id" value="">
                </div>
            </div>
            <div class="venda-resumo__field">
                <label>Forma de Pagamento</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-forma-pagamento" class="form-control"
                        placeholder="Selecione..." autocomplete="off">
                    <input type="hidden" name="forma_pagamento" id="hidden-forma-pagamento" value="">
                </div>
            </div>
            <div class="venda-resumo__field">
                <label>Data</label>
                <input type="date" name="data_venda" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <input type="hidden" name="situacao" value="em_andamento">
        </div>

        <div class="venda-resumo__section venda-resumo__section--totais">
            <div class="venda-resumo__row">
                <span>Subtotal</span>
                <span id="label-subtotal">R$ 0,00</span>
                <input type="hidden" name="subtotal" id="input-subtotal" value="0">
            </div>
            <div class="venda-resumo__row venda-resumo__row--input">
                <label for="input-desconto">Desconto</label>
                <div class="input-prefix-wrap">
                    <span class="input-prefix">R$</span>
                    <input type="tel" name="desconto" id="input-desconto"
                        class="form-control text-right input-numeric"
                        data-decimals="2" inputmode="numeric" value="0,00">
                </div>
            </div>
            <div class="venda-resumo__row venda-resumo__row--input">
                <label for="input-acrescimo">Acréscimo</label>
                <div class="input-prefix-wrap">
                    <span class="input-prefix">R$</span>
                    <input type="tel" name="acrescimo" id="input-acrescimo"
                        class="form-control text-right input-numeric"
                        data-decimals="2" inputmode="numeric" value="0,00">
                </div>
            </div>
            <div class="venda-resumo__row venda-resumo__row--total">
                <span>TOTAL</span>
                <span id="label-total">R$ 0,00</span>
                <input type="hidden" name="total" id="input-total" value="0">
            </div>
        </div>

        <div class="venda-resumo__section">
            <div class="venda-resumo__field">
                <label>Observações</label>
                <textarea name="observacoes" class="form-control" rows="2" 
                    placeholder="Observações opcionais..." maxlength="1000"></textarea>
            </div>
        </div>

        <div class="venda-resumo__actions">
            <a href="{{ route('venda.index') }}" class="btn btn--ghost btn--block">Cancelar</a>
            <button type="submit" class="btn btn--success btn--block btn--lg" id="btn-finalizar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;margin-right:0.5rem;">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                Finalizar Venda (F4)
            </button>
        </div>
    </div>
</form>

{{-- Modal de quantidade rápida --}}
<div class="modal-overlay" id="modal-qtd" hidden>
    <div class="modal-box modal-box--compact">
        <div class="modal-box__header">
            <span class="card__title" id="modal-qtd-titulo">Adicionar Produto</span>
            <button type="button" class="btn btn--ghost btn--sm" data-modal-close>&times;</button>
        </div>
        <div class="modal-box__body">
            <div class="modal-qtd-produto">
                <span class="modal-qtd-produto__nome" id="modal-qtd-nome"></span>
                <span class="modal-qtd-produto__preco" id="modal-qtd-preco"></span>
            </div>
            <div class="form-grid form-grid--col-2" style="gap:1rem;margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Quantidade</label>
                    <input type="tel" id="modal-qtd-quantidade" class="form-control text-center input-numeric input-qtd-modal"
                        data-decimals="2" inputmode="numeric" value="1,00" style="font-size:1.5rem;font-weight:600;">
                </div>
                <div class="form-group">
                    <label class="form-label">Preço Unitário</label>
                    <input type="tel" id="modal-qtd-preco-input" class="form-control text-right input-numeric"
                        data-decimals="2" inputmode="numeric" value="0,00" style="font-size:1.5rem;font-weight:600;">
                </div>
            </div>
            <div class="modal-qtd-total">
                <span>Total do item:</span>
                <strong id="modal-qtd-total-valor">R$ 0,00</strong>
            </div>
        </div>
        <div class="modal-box__footer">
            <button type="button" class="btn btn--ghost" data-modal-close>Cancelar</button>
            <button type="button" class="btn btn--primary" id="btn-confirmar-qtd">
                Adicionar (Enter)
            </button>
        </div>
    </div>
</div>

@endsection

@push('styles')
<style>
/* Topbar fixa com total */
.venda-topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem 1rem;
    background: var(--color-primary);
    color: white;
    border-radius: 8px;
    margin-bottom: 1rem;
    position: sticky;
    top: 0;
    z-index: 50;
}
.venda-topbar__label {
    opacity: 0.8;
    font-size: 0.85rem;
}
.venda-topbar__cliente {
    font-size: 0.95rem;
}
#display-cliente {
    font-weight: 600;
    margin-left: 0.25rem;
}
.venda-topbar__total {
    text-align: right;
}
.venda-topbar__total-label {
    display: block;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    opacity: 0.8;
}
.venda-topbar__total-valor {
    font-size: 1.75rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}

/* Busca rápida */
.venda-busca-rapida {
    margin-bottom: 1rem;
}
.venda-busca-rapida__input-wrap {
    position: relative;
}
.venda-busca-rapida__icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    width: 22px;
    height: 22px;
    color: var(--color-text-muted);
    pointer-events: none;
}
.venda-busca-rapida__input {
    width: 100%;
    padding: 1rem 1rem 1rem 3rem;
    font-size: 1.1rem;
    border: 2px solid var(--color-border);
    border-radius: 8px;
    background: var(--color-surface);
    transition: border-color 0.15s, box-shadow 0.15s;
}
.venda-busca-rapida__input:focus {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px var(--color-primary-soft);
}
.venda-busca-rapida__hints {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: var(--color-text-muted);
    text-align: center;
}
.venda-busca-rapida__hints kbd {
    display: inline-block;
    padding: 0.15rem 0.4rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.75rem;
    font-weight: 600;
}

/* Container de itens */
.venda-itens-container {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    min-height: 200px;
}
.venda-itens-header {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--color-border);
}
.venda-itens-header__title {
    font-weight: 600;
    font-size: 1rem;
}
.venda-itens-header__count {
    color: var(--color-text-muted);
    font-size: 0.9rem;
}

/* Lista de itens */
.venda-itens-list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

/* Item compacto */
.venda-item {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    align-items: center;
    gap: 1rem;
    padding: 0.75rem 1rem;
    background: #f8fafc;
    border: 1px solid var(--color-border);
    border-left: 3px solid var(--color-primary);
    border-radius: 6px;
}
.venda-item__info {
    min-width: 0;
}
.venda-item__nome {
    font-weight: 500;
    font-size: 0.95rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.venda-item__codigo {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
.venda-item__qtd {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.venda-item__qtd-input {
    width: 70px;
    text-align: center;
    font-weight: 600;
    padding: 0.4rem;
    border: 1px solid var(--color-border);
    border-radius: 4px;
    background: white;
}
.venda-item__preco {
    text-align: right;
    min-width: 90px;
}
.venda-item__preco-unit {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
.venda-item__preco-total {
    font-weight: 600;
    color: var(--color-primary);
}
.venda-item__actions {
    display: flex;
    gap: 0.25rem;
}
.venda-item__btn {
    padding: 0.4rem;
    background: transparent;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    color: var(--color-text-muted);
    transition: all 0.15s;
}
.venda-item__btn:hover {
    background: var(--color-border);
    color: var(--color-text);
}
.venda-item__btn--remove:hover {
    background: #fee2e2;
    color: var(--color-error);
}
.venda-item__btn svg {
    width: 18px;
    height: 18px;
}

/* Estado vazio */
.venda-itens-empty {
    text-align: center;
    padding: 3rem 1rem;
    color: var(--color-text-muted);
}
.venda-itens-empty svg {
    width: 48px;
    height: 48px;
    margin-bottom: 1rem;
    opacity: 0.5;
}
.venda-itens-empty p {
    font-weight: 500;
    margin-bottom: 0.25rem;
}
.venda-itens-empty span {
    font-size: 0.9rem;
}

/* Resumo / painel lateral */
.venda-resumo {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 8px;
    overflow: hidden;
}
.venda-resumo__section {
    padding: 1rem;
    border-bottom: 1px solid var(--color-border);
}
.venda-resumo__section:last-child {
    border-bottom: none;
}
.venda-resumo__section h4 {
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--color-text-muted);
    margin-bottom: 0.75rem;
}
.venda-resumo__field {
    margin-bottom: 0.75rem;
}
.venda-resumo__field:last-child {
    margin-bottom: 0;
}
.venda-resumo__field label {
    display: block;
    font-size: 0.8rem;
    font-weight: 500;
    color: var(--color-text-muted);
    margin-bottom: 0.25rem;
}
.venda-resumo__field .form-control {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

/* Totais */
.venda-resumo__section--totais {
    background: #f8fafc;
}
.venda-resumo__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    font-size: 0.95rem;
}
.venda-resumo__row--input {
    gap: 1rem;
}
.venda-resumo__row--input label {
    font-size: 0.9rem;
    color: var(--color-text-muted);
}
.input-prefix-wrap {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}
.input-prefix-wrap .form-control {
    width: 100px;
}
.input-prefix {
    font-size: 0.85rem;
    color: var(--color-text-muted);
}
.venda-resumo__row--total {
    margin-top: 0.5rem;
    padding-top: 0.75rem;
    border-top: 2px solid var(--color-border);
    font-size: 1.25rem;
    font-weight: 700;
}
.venda-resumo__row--total span:last-child {
    color: var(--color-primary);
}

/* Ações */
.venda-resumo__actions {
    padding: 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.btn--block {
    width: 100%;
    justify-content: center;
}
.btn--lg {
    padding: 1rem 1.5rem;
    font-size: 1rem;
}
.btn--success {
    background: var(--color-success);
    border-color: var(--color-success);
    color: white;
}
.btn--success:hover {
    background: #059669;
    border-color: #059669;
}

/* Modal quantidade */
.modal-box--compact {
    max-width: 400px;
}
.modal-qtd-produto {
    text-align: center;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 6px;
}
.modal-qtd-produto__nome {
    display: block;
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.25rem;
}
.modal-qtd-produto__preco {
    color: var(--color-primary);
    font-weight: 500;
}
.modal-qtd-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    background: var(--color-primary);
    color: white;
    border-radius: 6px;
    font-size: 1.1rem;
}
.modal-qtd-total strong {
    font-size: 1.3rem;
}
.input-qtd-modal {
    text-align: center !important;
}

/* Autocomplete dropdown melhorado */
.autocomplete-dropdown {
    background: #fff;
    max-height: 300px;
    overflow-y: auto;
}
.autocomplete-dropdown .autocomplete-item {
    padding: 0.75rem 1rem;
    cursor: pointer;
    border-bottom: 1px solid #eee;
    transition: background 0.1s;
}
.autocomplete-dropdown .autocomplete-item:last-child {
    border-bottom: none;
}
.autocomplete-dropdown .autocomplete-item:hover,
.autocomplete-dropdown .autocomplete-item.active {
    background: #f0f7ff;
}
.autocomplete-dropdown .autocomplete-empty {
    padding: 1rem;
    text-align: center;
    color: #888;
}

/* Desktop: layout em 2 colunas */
@media (min-width: 900px) {
    #form-venda {
        display: grid;
        grid-template-columns: 1fr 320px;
        grid-template-rows: auto auto 1fr;
        gap: 1rem;
        align-items: start;
    }
    .venda-topbar {
        grid-column: 1 / -1;
    }
    .venda-busca-rapida {
        grid-column: 1;
        margin-bottom: 0;
    }
    .venda-itens-container {
        grid-column: 1;
        grid-row: 3;
        margin-bottom: 0;
    }
    .venda-resumo {
        grid-column: 2;
        grid-row: 2 / 4;
        position: sticky;
        top: 80px;
    }
    .venda-busca-rapida__hints {
        display: block;
    }
}

/* Mobile */
@media (max-width: 899px) {
    .venda-topbar__total-valor {
        font-size: 1.4rem;
    }
    .venda-busca-rapida__hints {
        display: none;
    }
    .venda-item {
        grid-template-columns: 1fr auto;
        grid-template-rows: auto auto;
        gap: 0.5rem;
    }
    .venda-item__qtd {
        grid-column: 1;
        grid-row: 2;
    }
    .venda-item__preco {
        grid-row: 1 / 3;
        grid-column: 2;
    }
    .venda-item__actions {
        position: absolute;
        right: 0.5rem;
        top: 0.5rem;
    }
    .venda-item {
        position: relative;
        padding-right: 2.5rem;
    }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var $ = window.jQuery;
    var urlProdutos = @json(route('api.produtos.busca'));
    var urlFuncionarios = @json(route('api.funcionarios.busca'));
    var urlClientes = @json(route('api.clientes.busca'));
    var urlFormasPagamento = @json(route('api.formas-pagamento.busca'));
    var indice = 0;
    var produtoSelecionado = null;

    // Autocompletes
    if (typeof initAutocomplete === 'function') {
        initAutocomplete('#input-vendedor', '#hidden-funcionario-id', urlFuncionarios, function(item) {
            return item.nome;
        });

        initAutocomplete('#input-cliente', '#hidden-cliente-id', urlClientes, function(item) {
            var doc = item.cpf || item.cnpj || '';
            return item.nome + (doc ? ' — ' + doc : '');
        }, function(item) {
            document.getElementById('display-cliente').textContent = item ? item.nome : 'Consumidor Final';
        });

        initAutocomplete('#input-forma-pagamento', '#hidden-forma-pagamento', urlFormasPagamento, function(item) {
            return item.nome;
        }, null, null, 'nome');
    }

    // Funções auxiliares
    function parseBR(val) {
        if (typeof window.parseBR === 'function') return window.parseBR(val);
        if (!val) return 0;
        var str = String(val).trim().replace(/\./g, '').replace(',', '.');
        return parseFloat(str) || 0;
    }

    function formatBR(val) {
        var num = parseFloat(val) || 0;
        return 'R$ ' + num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatInput(val) {
        var num = parseFloat(val) || 0;
        return num.toFixed(2).replace('.', ',');
    }

    // Atualizar contadores e totais
    function atualizarTotais() {
        var subtotal = 0;
        var count = 0;

        document.querySelectorAll('#itens-body .venda-item').forEach(function(row) {
            var qtd = parseBR(row.querySelector('.venda-item__qtd-input').value);
            var preco = parseBR(row.dataset.preco);
            var total = qtd * preco;
            row.querySelector('.venda-item__preco-total').textContent = formatBR(total);
            subtotal += total;
            count++;
        });

        var desconto = parseBR(document.getElementById('input-desconto').value);
        var acrescimo = parseBR(document.getElementById('input-acrescimo').value);
        var total = Math.max(0, subtotal - desconto + acrescimo);

        document.getElementById('label-subtotal').textContent = formatBR(subtotal);
        document.getElementById('label-total').textContent = formatBR(total);
        document.getElementById('display-total').textContent = formatBR(total);
        document.getElementById('input-subtotal').value = subtotal.toFixed(2);
        document.getElementById('input-total').value = total.toFixed(2);
        document.getElementById('count-itens').textContent = count;
        document.getElementById('itens-empty').hidden = count > 0;
    }

    // Adicionar item à lista
    function adicionarItem(produto, quantidade, preco) {
        var idx = indice++;
        var total = quantidade * preco;

        var html = 
            '<div class="venda-item" data-produto-id="' + produto.id + '" data-preco="' + preco.toFixed(2) + '">' +
                '<div class="venda-item__info">' +
                    '<div class="venda-item__nome">' + produto.nome + '</div>' +
                    '<div class="venda-item__codigo">Cód: ' + (produto.codigo || '-') + '</div>' +
                '</div>' +
                '<div class="venda-item__qtd">' +
                    '<input type="tel" class="venda-item__qtd-input input-numeric" data-decimals="2" inputmode="numeric" ' +
                        'name="itens[' + idx + '][quantidade]" value="' + formatInput(quantidade) + '">' +
                '</div>' +
                '<div class="venda-item__preco">' +
                    '<div class="venda-item__preco-unit">' + formatBR(preco) + '</div>' +
                    '<div class="venda-item__preco-total">' + formatBR(total) + '</div>' +
                '</div>' +
                '<div class="venda-item__actions">' +
                    '<button type="button" class="venda-item__btn venda-item__btn--remove" title="Remover">' +
                        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                            '<path d="M3 6h18M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path>' +
                        '</svg>' +
                    '</button>' +
                '</div>' +
                '<input type="hidden" name="itens[' + idx + '][produto_id]" value="' + produto.id + '">' +
                '<input type="hidden" name="itens[' + idx + '][preco_unitario]" class="input-preco-hidden" value="' + preco.toFixed(2) + '">' +
                '<input type="hidden" name="itens[' + idx + '][desconto]" value="0">' +
            '</div>';

        document.getElementById('itens-body').insertAdjacentHTML('beforeend', html);

        // Bind eventos na nova linha
        var row = document.querySelector('#itens-body .venda-item:last-child');
        
        row.querySelector('.venda-item__qtd-input').addEventListener('input', atualizarTotais);
        row.querySelector('.venda-item__qtd-input').addEventListener('blur', atualizarTotais);
        
        row.querySelector('.venda-item__btn--remove').addEventListener('click', function() {
            row.remove();
            atualizarTotais();
        });

        atualizarTotais();
    }

    // Modal de quantidade
    var modalQtd = document.getElementById('modal-qtd');
    var modalQtdInput = document.getElementById('modal-qtd-quantidade');
    var modalPrecoInput = document.getElementById('modal-qtd-preco-input');

    function abrirModalQtd(produto) {
        produtoSelecionado = produto;
        document.getElementById('modal-qtd-nome').textContent = produto.nome;
        document.getElementById('modal-qtd-preco').textContent = formatBR(produto.preco_venda || 0);
        modalQtdInput.value = '1,00';
        modalPrecoInput.value = formatInput(produto.preco_venda || 0);
        atualizarTotalModal();
        modalQtd.hidden = false;
        setTimeout(function() { modalQtdInput.focus(); modalQtdInput.select(); }, 100);
    }

    function atualizarTotalModal() {
        var qtd = parseBR(modalQtdInput.value);
        var preco = parseBR(modalPrecoInput.value);
        document.getElementById('modal-qtd-total-valor').textContent = formatBR(qtd * preco);
    }

    modalQtdInput.addEventListener('input', atualizarTotalModal);
    modalPrecoInput.addEventListener('input', atualizarTotalModal);

    document.getElementById('btn-confirmar-qtd').addEventListener('click', function() {
        if (!produtoSelecionado) return;
        var qtd = parseBR(modalQtdInput.value);
        var preco = parseBR(modalPrecoInput.value);
        if (qtd <= 0) qtd = 1;
        adicionarItem(produtoSelecionado, qtd, preco);
        modalQtd.hidden = true;
        produtoSelecionado = null;
        document.getElementById('busca-produto-rapida').value = '';
        document.getElementById('busca-produto-rapida').focus();
    });

    // Fechar modal
    modalQtd.querySelectorAll('[data-modal-close]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalQtd.hidden = true;
            document.getElementById('busca-produto-rapida').focus();
        });
    });
    modalQtd.addEventListener('click', function(e) {
        if (e.target === modalQtd) {
            modalQtd.hidden = true;
            document.getElementById('busca-produto-rapida').focus();
        }
    });

    // Busca rápida de produto
    var buscaInput = document.getElementById('busca-produto-rapida');
    var dropdownBusca = null;
    var timeoutBusca = null;
    var resultadosBusca = [];
    var indiceSelecionado = -1;

    function criarDropdownBusca() {
        if (dropdownBusca) return;
        dropdownBusca = document.createElement('div');
        dropdownBusca.className = 'autocomplete-dropdown';
        dropdownBusca.style.cssText = 'position:absolute;left:0;right:0;top:100%;z-index:100;background:#fff;border:1px solid var(--color-border);border-top:none;border-radius:0 0 8px 8px;max-height:300px;overflow-y:auto;box-shadow:0 8px 24px rgba(28,37,48,0.15);';
        buscaInput.parentElement.style.position = 'relative';
        buscaInput.parentElement.appendChild(dropdownBusca);
    }

    function fecharDropdownBusca() {
        if (dropdownBusca) dropdownBusca.innerHTML = '';
        resultadosBusca = [];
        indiceSelecionado = -1;
    }

    function renderizarResultados(items) {
        criarDropdownBusca();
        resultadosBusca = items;
        indiceSelecionado = -1;

        if (items.length === 0) {
            dropdownBusca.innerHTML = '<div class="autocomplete-empty">Nenhum produto encontrado</div>';
            return;
        }

        var html = items.map(function(item, i) {
            var preco = formatBR(item.preco_venda || 0);
            return '<div class="autocomplete-item" data-index="' + i + '">' +
                '<div class="ac-item-main">' +
                    '<span class="ac-item-nome">' + item.nome + '</span>' +
                    '<span class="ac-item-preco">' + preco + '</span>' +
                '</div>' +
                '<div class="ac-item-meta">' +
                    '<span class="ac-item-codigo">' + (item.codigo || '-') + '</span>' +
                '</div>' +
            '</div>';
        }).join('');

        dropdownBusca.innerHTML = html;

        dropdownBusca.querySelectorAll('.autocomplete-item').forEach(function(el) {
            el.addEventListener('click', function() {
                var idx = parseInt(el.dataset.index);
                selecionarProduto(resultadosBusca[idx]);
            });
        });
    }

    function selecionarProduto(produto) {
        fecharDropdownBusca();
        abrirModalQtd(produto);
    }

    function atualizarSelecao() {
        if (!dropdownBusca) return;
        dropdownBusca.querySelectorAll('.autocomplete-item').forEach(function(el, i) {
            el.classList.toggle('active', i === indiceSelecionado);
        });
    }

    function buscarProdutos(q) {
        clearTimeout(timeoutBusca);
        var url = urlProdutos + '?q=' + encodeURIComponent(q || '');
        console.log('Buscando produtos:', url);
        
        timeoutBusca = setTimeout(function() {
            fetch(url)
                .then(function(r) { 
                    console.log('Resposta recebida:', r.status);
                    return r.json(); 
                })
                .then(function(data) {
                    console.log('Dados recebidos:', data);
                    renderizarResultados(data);
                })
                .catch(function(err) { 
                    console.error('Erro na busca:', err);
                    fecharDropdownBusca(); 
                });
        }, q ? 150 : 0);
    }

    // Verificar se o input existe
    console.log('Input de busca encontrado:', buscaInput);

    if (buscaInput) {
        buscaInput.addEventListener('focus', function() {
            console.log('Focus no input');
            buscarProdutos(this.value.trim());
        });

        buscaInput.addEventListener('click', function() {
            console.log('Click no input');
            buscarProdutos(this.value.trim());
        });

        buscaInput.addEventListener('input', function() {
            console.log('Input alterado:', this.value);
            buscarProdutos(this.value.trim());
        });
    }

    buscaInput.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (resultadosBusca.length > 0) {
                indiceSelecionado = Math.min(indiceSelecionado + 1, resultadosBusca.length - 1);
                atualizarSelecao();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (resultadosBusca.length > 0) {
                indiceSelecionado = Math.max(indiceSelecionado - 1, 0);
                atualizarSelecao();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (indiceSelecionado >= 0 && resultadosBusca[indiceSelecionado]) {
                selecionarProduto(resultadosBusca[indiceSelecionado]);
            } else if (resultadosBusca.length === 1) {
                selecionarProduto(resultadosBusca[0]);
            }
        } else if (e.key === 'Escape') {
            fecharDropdownBusca();
        }
    });

    buscaInput.addEventListener('blur', function() {
        setTimeout(fecharDropdownBusca, 200);
    });

    // Enter no modal de quantidade confirma
    modalQtd.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !modalQtd.hidden) {
            e.preventDefault();
            document.getElementById('btn-confirmar-qtd').click();
        }
    });

    // Atalhos de teclado globais
    document.addEventListener('keydown', function(e) {
        // Ignorar se estiver em input (exceto atalhos específicos)
        var tag = (e.target.tagName || '').toLowerCase();
        var isInput = tag === 'input' || tag === 'textarea' || tag === 'select';

        if (e.key === 'F2') {
            e.preventDefault();
            buscaInput.focus();
            buscaInput.select();
        } else if (e.key === 'F4') {
            e.preventDefault();
            document.getElementById('btn-finalizar').click();
        } else if (e.key === 'F8') {
            e.preventDefault();
            document.getElementById('input-cliente').focus();
        } else if (e.key === 'Escape' && !modalQtd.hidden) {
            modalQtd.hidden = true;
            buscaInput.focus();
        }
    });

    // Atualizar ao mudar desconto/acréscimo
    document.getElementById('input-desconto').addEventListener('input', atualizarTotais);
    document.getElementById('input-acrescimo').addEventListener('input', atualizarTotais);

    atualizarTotais();
});
</script>
@endpush
