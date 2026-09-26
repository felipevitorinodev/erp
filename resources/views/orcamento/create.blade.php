@extends('layouts.app')

@section('title', 'Novo Orçamento')
@section('page_title', 'Novo Orçamento')

@section('breadcrumb')
    <a href="{{ route('orcamento.index') }}">Orçamentos</a> / Novo Orçamento
@endsection

@section('content')

<form method="POST" action="{{ route('orcamento.store') }}" id="form-orcamento">
    @csrf
    <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">

    {{-- Barra superior com total --}}
    <div class="venda-topbar venda-topbar--orcamento">
        <div class="venda-topbar__info">
            <div class="venda-topbar__cliente">
                <span class="venda-topbar__label">Cliente:</span>
                <span id="display-cliente">Não informado</span>
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
            <kbd>F2</kbd> Buscar &nbsp;|&nbsp; <kbd>F4</kbd> Salvar &nbsp;|&nbsp; <kbd>F8</kbd> Cliente
        </div>
    </div>

    {{-- Itens do orçamento --}}
    <div class="venda-itens-container">
        <div class="venda-itens-header">
            <span class="venda-itens-header__title">Itens do Orçamento</span>
            <span class="venda-itens-header__count">(<span id="count-itens">0</span> itens)</span>
        </div>

        @error('itens')<div class="alert alert--error" style="margin-bottom:1rem;">{{ $message }}</div>@enderror

        <div class="venda-itens-list" id="itens-body">
            {{-- Itens serão adicionados dinamicamente --}}
        </div>

        <div class="venda-itens-empty" id="itens-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
                <line x1="16" y1="13" x2="8" y2="13"></line>
                <line x1="16" y1="17" x2="8" y2="17"></line>
                <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
            <p>Nenhum item adicionado</p>
            <span>Use a busca acima para adicionar produtos</span>
        </div>
    </div>

    {{-- Painel lateral / inferior com resumo --}}
    <div class="venda-resumo">
        <div class="venda-resumo__section venda-resumo__section--dados">
            <h4>Dados do Orçamento</h4>
            <div class="venda-resumo__field">
                <label>Cliente</label>
                <div class="autocomplete-wrap">
                    <input type="text" id="input-cliente" class="form-control"
                        placeholder="Buscar cliente..." autocomplete="off">
                    <input type="hidden" name="cliente_id" id="hidden-cliente-id" value="">
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
                <input type="date" name="data_orcamento" class="form-control" value="{{ date('Y-m-d') }}">
            </div>
            <div class="venda-resumo__field">
                <label>Validade (dias)</label>
                <input type="number" name="validade_dias" class="form-control" value="30" min="1" max="365">
            </div>
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
                    placeholder="Condições, prazos, etc..." maxlength="1000"></textarea>
            </div>
        </div>

        <div class="venda-resumo__actions">
            <a href="{{ route('orcamento.index') }}" class="btn btn--ghost btn--block">Cancelar</a>
            <button type="submit" class="btn btn--primary btn--block btn--lg" id="btn-salvar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;margin-right:0.5rem;">
                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                    <polyline points="7 3 7 8 15 8"></polyline>
                </svg>
                Salvar Orçamento (F4)
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


@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    var $ = window.jQuery;
    var urlProdutos = @json(route('api.produtos.busca'));
    var urlClientes = @json(route('api.clientes.busca'));
    var urlFormasPagamento = @json(route('api.formas-pagamento.busca'));
    var indice = 0;
    var produtoSelecionado = null;

    // Autocompletes
    if (typeof initAutocomplete === 'function') {
        initAutocomplete('#input-cliente', '#hidden-cliente-id', urlClientes, function(item) {
            var doc = item.cpf || item.cnpj || '';
            return item.nome + (doc ? ' — ' + doc : '');
        }, function(item) {
            document.getElementById('display-cliente').textContent = item ? item.nome : 'Não informado';
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
        if (e.key === 'F2') {
            e.preventDefault();
            buscaInput.focus();
            buscaInput.select();
        } else if (e.key === 'F4') {
            e.preventDefault();
            document.getElementById('btn-salvar').click();
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
