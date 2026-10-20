(function () {
    'use strict';

    var config = window.PDV_CONFIG || {};
    var urls = config.urls || {};
    var carrinho = [];
    var sugestoes = [];
    var sugestaoAtiva = -1;
    var debounceTimer = null;
    var alertTimer = null;
    var toastTimer = null;
    var scannerTimer = null;
    var finalizando = false;
    var buscandoCodigo = false;
    var ultimoProdutoId = null;
    var lastKeyTs = 0;
    var rapidChars = 0;

    var PDV_MIN_WIDTH = 1024;

    var el = {
        shell: document.getElementById('pdv-shell'),
        wrap: document.getElementById('pdv-wrap'),
        sucesso: document.getElementById('pdv-sucesso'),
        inputBusca: document.getElementById('pdv-input-busca'),
        sugestoes: document.getElementById('pdv-sugestoes'),
        itens: document.getElementById('pdv-itens'),
        empty: document.getElementById('pdv-empty'),
        itensCount: document.getElementById('pdv-itens-count'),
        desconto: document.getElementById('pdv-desconto'),
        acrescimo: document.getElementById('pdv-acrescimo'),
        displaySubtotal: document.getElementById('pdv-display-subtotal'),
        displayTotal: document.getElementById('pdv-display-total'),
        btnFinalizar: document.getElementById('pdv-btn-finalizar'),
        btnFinalizarValor: document.getElementById('pdv-btn-finalizar-valor'),
        formaHidden: document.getElementById('pdv-hidden-forma-pagamento'),
        formaErro: document.getElementById('pdv-forma-erro'),
        formaRotulo: document.getElementById('pdv-btn-finalizar-forma'),
        formasGrid: document.getElementById('pdv-formas-grid'),
        sucessoPagamento: document.getElementById('pdv-sucesso-pagamento'),
        alert: document.getElementById('pdv-alert-erro'),
        alertMsg: document.getElementById('pdv-alert-erro-msg'),
        alertFechar: document.getElementById('pdv-alert-erro-fechar'),
        sucessoTotal: document.getElementById('pdv-sucesso-total'),
        sucessoNumero: document.getElementById('pdv-sucesso-numero'),
        btnNova: document.getElementById('pdv-btn-nova-venda'),
        linkVer: document.getElementById('pdv-link-ver-venda'),
        clienteHidden: document.getElementById('pdv-hidden-cliente-id'),
        toast: document.getElementById('pdv-toast'),
        painelExtra: document.getElementById('pdv-painel-extra'),
        telaPequena: document.getElementById('pdv-tela-pequena'),
        larguraAtual: document.getElementById('pdv-largura-atual'),
        pagamento: document.getElementById('pdv-pagamento'),
        pagErro: document.getElementById('pdv-pag-erro'),
        pagSubtotal: document.getElementById('pdv-pag-subtotal'),
        pagQtdItens: document.getElementById('pdv-pag-qtd-itens'),
        pagTotal: document.getElementById('pdv-pag-total'),
        pagCliente: document.getElementById('pdv-pag-cliente'),
        pagAviso: document.getElementById('pdv-pag-aviso'),
        recebidoWrap: document.getElementById('pdv-pag-recebido-wrap'),
        recebido: document.getElementById('pdv-recebido'),
        troco: document.getElementById('pdv-troco'),
        btnConfirmar: document.getElementById('pdv-btn-confirmar'),
        btnConfirmarValor: document.getElementById('pdv-btn-confirmar-valor'),
        resumoForma: document.getElementById('pdv-resumo-forma'),
        inputCliente: document.getElementById('pdv-input-cliente'),
        ajuda: document.getElementById('pdv-ajuda'),
        btnAjuda: document.getElementById('pdv-btn-ajuda'),
        sucessoTroco: document.getElementById('pdv-sucesso-troco'),
    };

    function pagamentoAberto() {
        return !!(el.pagamento && !el.pagamento.hidden);
    }

    function ajudaAberta() {
        return !!(el.ajuda && !el.ajuda.hidden);
    }

    function formaAtualEhPrazo() {
        var ativo = el.formasGrid ? el.formasGrid.querySelector('.pdv-forma-btn--ativa') : null;
        return !!(ativo && ativo.getAttribute('data-prazo') === '1');
    }

    function telaAdequada() {
        return window.innerWidth >= PDV_MIN_WIDTH;
    }

    function syncTelaMinima() {
        var ok = telaAdequada();
        if (el.larguraAtual) {
            el.larguraAtual.textContent = Math.round(window.innerWidth) + ' px';
        }
        if (el.telaPequena) {
            el.telaPequena.hidden = ok;
        }
        document.documentElement.classList.toggle('pdv-tela-bloqueada', !ok);
        document.body.classList.toggle('pdv-tela-bloqueada', !ok);
        return ok;
    }

    function syncPainelExtra() {
        if (!el.painelExtra) return;
        if (!telaAdequada()) return;
        el.painelExtra.open = window.innerWidth >= 900;
    }

    var pdvIniciado = false;

    function iniciarPdv() {
        if (pdvIniciado || !telaAdequada()) return false;
        pdvIniciado = true;
        bindEventos();
        restaurarFormaPagamento();
        atalhosTeclado();
        renderizarCarrinho();
        syncPainelExtra();
        focarBusca();
        return true;
    }

    syncTelaMinima();
    window.addEventListener('resize', function () {
        var ok = syncTelaMinima();
        if (ok) {
            if (!pdvIniciado) iniciarPdv();
            else {
                syncPainelExtra();
                focarBusca();
            }
        }
    });

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

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
        if (typeof window.formatBR === 'function') return window.formatBR(val);
        var n = Number(val) || 0;
        return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatQtd(val) {
        var n = Number(val) || 0;
        if (Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
        return formatBR(n);
    }

    function formatMoney(val) {
        return 'R$ ' + formatBR(val);
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function pareceCodigo(termo) {
        termo = (termo || '').trim();
        if (termo.length < 3) return false;
        if (/\s/.test(termo)) return false;
        // código / EAN / SKU: dígitos ou alfanumérico compacto
        return /^[A-Za-z0-9\-_.\/]+$/.test(termo);
    }

    function focarBusca(opcoes) {
        if (!el.inputBusca) return;
        el.inputBusca.focus();
        try {
            if (opcoes && opcoes.selecionar && el.inputBusca.value) {
                el.inputBusca.select();
                return;
            }
            var len = el.inputBusca.value.length;
            el.inputBusca.setSelectionRange(len, len);
        } catch (e) { /* ignore */ }
    }

    function mostrarErro(msg) {
        if (pagamentoAberto() && el.pagErro) {
            el.pagErro.textContent = msg || 'Ocorreu um erro. Tente novamente.';
            el.pagErro.style.display = 'flex';
            return;
        }
        if (!el.alert || !el.alertMsg) return;
        el.alertMsg.textContent = msg || 'Ocorreu um erro. Tente novamente.';
        el.alert.style.display = 'flex';
        el.alert.classList.add('pdv-alert-erro--visivel');
        clearTimeout(alertTimer);
        alertTimer = setTimeout(esconderErro, 6000);
    }

    function esconderErro() {
        if (el.pagErro) el.pagErro.style.display = 'none';
        if (!el.alert) return;
        el.alert.style.display = 'none';
        el.alert.classList.remove('pdv-alert-erro--visivel');
        clearTimeout(alertTimer);
    }

    function mostrarToast(msg) {
        if (!el.toast) return;
        // evita ficar preso em stacking context do layout (footer cobrindo)
        if (el.toast.parentNode !== document.body) {
            document.body.appendChild(el.toast);
        }
        el.toast.textContent = msg;
        el.toast.hidden = false;
        el.toast.classList.add('pdv-toast--visivel');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            el.toast.classList.remove('pdv-toast--visivel');
            setTimeout(function () { el.toast.hidden = true; }, 220);
        }, 1600);
    }

    function fecharSugestoes() {
        if (!el.sugestoes) return;
        el.sugestoes.innerHTML = '';
        el.sugestoes.hidden = true;
        sugestoes = [];
        sugestaoAtiva = -1;
    }

    function limparBusca() {
        if (el.inputBusca) el.inputBusca.value = '';
        fecharSugestoes();
        rapidChars = 0;
    }

    function destacarSugestao(index) {
        if (!el.sugestoes) return;
        var items = el.sugestoes.querySelectorAll('.pdv-sugestao-item[data-index]');
        if (!items.length) {
            sugestaoAtiva = -1;
            return;
        }

        if (index < 0) index = items.length - 1;
        if (index >= items.length) index = 0;
        sugestaoAtiva = index;

        items.forEach(function (node, i) {
            node.classList.toggle('pdv-sugestao-item--ativa', i === sugestaoAtiva);
        });

        var ativo = items[sugestaoAtiva];
        if (ativo && typeof ativo.scrollIntoView === 'function') {
            ativo.scrollIntoView({ block: 'nearest' });
        }
    }

    /**
     * "3*7891234" ou "2,5*parafuso": quantidade antes do asterisco.
     */
    function separarQuantidade(termo) {
        var m = /^\s*(\d+(?:[.,]\d{1,3})?)\s*\*\s*(.*)$/.exec(termo || '');
        if (!m) return { quantidade: 1, termo: (termo || '').trim() };
        var qtd = parseBR(m[1]);
        return { quantidade: qtd > 0 ? qtd : 1, termo: m[2].trim() };
    }

    function quantidadeDigitada() {
        return separarQuantidade(el.inputBusca ? el.inputBusca.value : '').quantidade;
    }

    function adicionarSugestao(index) {
        var produto = sugestoes[index];
        if (!produto) return false;
        adicionarAoCarrinho(normalizarProduto(produto, quantidadeDigitada()));
        limparBusca();
        focarBusca();
        return true;
    }

    function buscarProduto(termo) {
        clearTimeout(debounceTimer);
        termo = separarQuantidade(termo).termo;

        if (termo.length < 2) {
            fecharSugestoes();
            return;
        }

        // enquanto parece código puro, não abre dropdown (leitor / colar)
        if (pareceCodigo(termo) && !/[a-zA-ZÀ-ÿ]{3,}/.test(termo)) {
            fecharSugestoes();
            return;
        }

        debounceTimer = setTimeout(function () {
            fetch(urls.produtosBusca + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    sugestoes = Array.isArray(data) ? data : [];
                    renderizarSugestoes();
                })
                .catch(function () {
                    fecharSugestoes();
                });
        }, 280);
    }

    function renderizarSugestoes() {
        if (!el.sugestoes) return;

        sugestaoAtiva = -1;

        if (!sugestoes.length) {
            el.sugestoes.innerHTML = '<div class="pdv-sugestao-item pdv-sugestao-item--empty">Nenhum produto encontrado</div>';
            el.sugestoes.hidden = false;
            return;
        }

        el.sugestoes.innerHTML = sugestoes.map(function (p, i) {
            var estoque = p.estoque_atual != null ? Number(p.estoque_atual) : 0;
            return '<div class="pdv-sugestao-item" data-index="' + i + '" role="option">' +
                '<div class="pdv-sugestao-item__main">' +
                    '<div class="pdv-item-nome">' + escapeHtml(p.nome || '') + '</div>' +
                    '<div class="pdv-item-codigo">' + escapeHtml(p.codigo || '') + '</div>' +
                '</div>' +
                '<div class="pdv-sugestao-item__meta">' +
                    '<div class="pdv-item-total">' + formatMoney(p.preco_venda) + '</div>' +
                    '<span class="pdv-stock">Est. ' + formatQtd(estoque) + '</span>' +
                '</div>' +
            '</div>';
        }).join('');

        el.sugestoes.hidden = false;

        el.sugestoes.querySelectorAll('.pdv-sugestao-item[data-index]').forEach(function (node) {
            node.addEventListener('mouseenter', function () {
                var idx = parseInt(node.getAttribute('data-index'), 10);
                if (!isNaN(idx)) destacarSugestao(idx);
            });
            node.addEventListener('click', function () {
                var idx = parseInt(node.getAttribute('data-index'), 10);
                adicionarSugestao(idx);
            });
        });

        // já destaca o primeiro para Enter adicionar imediatamente
        destacarSugestao(0);
    }

    function normalizarProduto(p, quantidade) {
        return {
            produtoId: p.id || p.produtoId,
            nome: p.nome || '',
            codigo: p.codigo || '',
            unidade: p.unidade || null,
            precoUnitario: Number(p.preco_venda != null ? p.preco_venda : p.precoUnitario) || 0,
            quantidade: quantidade > 0 ? quantidade : 1,
            desconto: 0,
        };
    }

    function adicionarAoCarrinho(produto, opts) {
        opts = opts || {};
        var existente = carrinho.find(function (item) {
            return String(item.produtoId) === String(produto.produtoId);
        });

        if (existente) {
            existente.quantidade = Number(existente.quantidade) + (Number(produto.quantidade) || 1);
            ultimoProdutoId = existente.produtoId;
        } else {
            carrinho.push({
                produtoId: produto.produtoId,
                nome: produto.nome,
                codigo: produto.codigo,
                unidade: produto.unidade,
                precoUnitario: Number(produto.precoUnitario) || 0,
                quantidade: Number(produto.quantidade) || 1,
                desconto: Number(produto.desconto) || 0,
            });
            ultimoProdutoId = produto.produtoId;
        }

        renderizarCarrinho();
        if (!opts.silent) {
            mostrarToast((produto.nome || 'Produto') + ' · +' + formatQtd(Number(produto.quantidade) || 1));
        }
    }

    function buscarPorCodigo(codigo, quantidade) {
        codigo = (codigo || '').trim();
        if (!codigo) return Promise.resolve(false);
        if (buscandoCodigo) return Promise.resolve(false);

        buscandoCodigo = true;
        var base = (urls.produtosCodigo || '/api/produtos').replace(/\/$/, '');

        return fetch(base + '/' + encodeURIComponent(codigo) + '/busca-codigo', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (r.status === 404) return null;
                if (!r.ok) throw new Error('Erro na busca');
                return r.json();
            })
            .then(function (data) {
                if (!data || !data.encontrado || !data.produto) return false;
                adicionarAoCarrinho(normalizarProduto(data.produto, quantidade));
                return true;
            })
            .finally(function () {
                buscandoCodigo = false;
            });
    }

    /**
     * Enter / bipe / colar:
     * - sugestão destacada (setas) → adiciona
     * - código → busca-codigo
     * - lista aberta → adiciona item ativo/primeiro
     * - senão busca por nome
     */
    function confirmarBusca() {
        var separado = separarQuantidade(el.inputBusca ? el.inputBusca.value : '');
        var termo = separado.termo;
        var quantidade = separado.quantidade;
        if (!termo || buscandoCodigo) return;

        clearTimeout(debounceTimer);
        clearTimeout(scannerTimer);
        esconderErro();

        var finalizarOk = function () {
            limparBusca();
            focarBusca();
        };

        // 1) item destacado pelas setas
        if (sugestaoAtiva >= 0 && sugestoes[sugestaoAtiva] && el.sugestoes && !el.sugestoes.hidden) {
            adicionarSugestao(sugestaoAtiva);
            return;
        }

        // 2) lista visível com resultados → Enter adiciona o primeiro/ativo
        if (sugestoes.length > 0 && el.sugestoes && !el.sugestoes.hidden) {
            adicionarSugestao(sugestaoAtiva >= 0 ? sugestaoAtiva : 0);
            return;
        }

        var tentarPorNome = function () {
            return fetch(urls.produtosBusca + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    sugestoes = Array.isArray(data) ? data : [];
                    if (sugestoes.length === 1) {
                        adicionarAoCarrinho(normalizarProduto(sugestoes[0], quantidade));
                        finalizarOk();
                        return;
                    }
                    if (sugestoes.length > 1) {
                        renderizarSugestoes();
                        return;
                    }
                    mostrarErro('Produto não encontrado: ' + termo);
                    focarBusca();
                });
        };

        // 3) código / SKU / EAN
        if (pareceCodigo(termo)) {
            buscarPorCodigo(termo, quantidade).then(function (ok) {
                if (ok) {
                    finalizarOk();
                    return;
                }
                return tentarPorNome();
            }).catch(function () {
                mostrarErro('Não foi possível buscar o produto.');
                focarBusca();
            });
            return;
        }

        // 4) busca por nome
        tentarPorNome().catch(function () {
            mostrarErro('Não foi possível buscar o produto.');
            focarBusca();
        });
    }

    function calcularTotais() {
        var subtotal = 0;
        carrinho.forEach(function (item) {
            subtotal += (Number(item.quantidade) * Number(item.precoUnitario)) - Number(item.desconto || 0);
        });
        var desconto = parseBR(el.desconto ? el.desconto.value : 0);
        var acrescimo = parseBR(el.acrescimo ? el.acrescimo.value : 0);
        var total = Math.max(0, subtotal - desconto + acrescimo);
        return { subtotal: subtotal, desconto: desconto, acrescimo: acrescimo, total: total };
    }

    function atualizarTotais() {
        var totais = calcularTotais();
        if (el.displaySubtotal) el.displaySubtotal.textContent = formatMoney(totais.subtotal);
        if (el.displayTotal) el.displayTotal.textContent = formatMoney(totais.total);
        if (el.btnFinalizarValor) el.btnFinalizarValor.textContent = formatMoney(totais.total);
        if (el.pagSubtotal) el.pagSubtotal.textContent = formatMoney(totais.subtotal);
        if (el.pagQtdItens) el.pagQtdItens.textContent = String(carrinho.length);
        if (el.pagTotal) el.pagTotal.textContent = formatMoney(totais.total);
        if (el.btnConfirmarValor) el.btnConfirmarValor.textContent = formatMoney(totais.total);
        atualizarTroco(totais.total);
        return totais;
    }

    function calcularTroco(total) {
        if (formaAtualEhPrazo() || !el.recebido) return 0;
        var recebido = parseBR(el.recebido.value);
        return recebido > total ? recebido - total : 0;
    }

    function atualizarTroco(total) {
        if (!el.troco) return;
        var recebido = el.recebido ? parseBR(el.recebido.value) : 0;
        var troco = calcularTroco(total);
        el.troco.textContent = formatMoney(troco);
        el.troco.classList.toggle('pdv-pag__troco--falta', recebido > 0 && recebido < total);
        if (recebido > 0 && recebido < total) {
            el.troco.textContent = 'Faltam ' + formatMoney(total - recebido);
        }
    }

    function atualizarResumoPagamento() {
        var prazo = formaAtualEhPrazo();
        var semCliente = !(el.clienteHidden && el.clienteHidden.value);
        if (el.recebidoWrap) el.recebidoWrap.hidden = prazo;
        if (el.pagAviso) el.pagAviso.hidden = !(prazo && semCliente);
        if (el.pagCliente) {
            el.pagCliente.textContent = !semCliente && el.inputCliente && el.inputCliente.value
                ? el.inputCliente.value
                : 'Consumidor Final';
        }
        atualizarTotais();
    }

    function abrirPagamento() {
        if (!el.pagamento) return;
        if (el.sucesso && !el.sucesso.hidden) return;
        esconderErro();
        if (!carrinho.length) {
            mostrarErro('Adicione pelo menos um produto antes do pagamento.');
            focarBusca();
            return;
        }
        fecharSugestoes();
        el.pagamento.hidden = false;
        if (el.recebido) el.recebido.value = '0,00';
        atualizarResumoPagamento();
        setTimeout(function () {
            if (!formaAtualEhPrazo() && el.recebido) {
                el.recebido.focus();
                el.recebido.select();
            } else if (el.btnConfirmar) {
                el.btnConfirmar.focus();
            }
        }, 30);
    }

    function fecharPagamento() {
        if (!el.pagamento) return;
        el.pagamento.hidden = true;
        esconderErro();
        focarBusca();
    }

    function abrirAjuda() {
        if (el.ajuda) el.ajuda.hidden = false;
    }

    function fecharAjuda() {
        if (el.ajuda) el.ajuda.hidden = true;
        if (!pagamentoAberto()) focarBusca();
    }

    function editarQuantidadeUltimo() {
        if (!carrinho.length) return;
        var idx = carrinho.length - 1;
        if (ultimoProdutoId != null) {
            carrinho.forEach(function (item, i) {
                if (String(item.produtoId) === String(ultimoProdutoId)) idx = i;
            });
        }
        var span = el.itens ? el.itens.querySelector('.pdv-item-card[data-index="' + idx + '"] [data-acao="editar-qtd"]') : null;
        if (span) editarQuantidadeInline(span, carrinho[idx]);
    }

    function removerUltimo() {
        if (!carrinho.length) return;
        var idx = carrinho.length - 1;
        var removido = carrinho.splice(idx, 1)[0];
        ultimoProdutoId = carrinho.length ? carrinho[carrinho.length - 1].produtoId : null;
        renderizarCarrinho();
        mostrarToast('Removido: ' + (removido.nome || 'item'));
        focarBusca();
    }

    function cancelarVenda() {
        if (!carrinho.length) return;
        if (!window.confirm('Cancelar esta venda e limpar todos os itens?')) return;
        carrinho = [];
        ultimoProdutoId = null;
        if (el.desconto) el.desconto.value = '0,00';
        if (el.acrescimo) el.acrescimo.value = '0,00';
        if (el.recebido) el.recebido.value = '0,00';
        renderizarCarrinho();
        mostrarToast('Venda cancelada');
        focarBusca();
    }

    function renderizarCarrinho() {
        if (!el.itens) return;
        el.itens.innerHTML = '';

        if (el.itensCount) el.itensCount.textContent = String(carrinho.length);

        if (!carrinho.length) {
            if (el.empty) el.empty.hidden = false;
            atualizarTotais();
            return;
        }

        if (el.empty) el.empty.hidden = true;

        // mais recente no topo (fluxo de balcão)
        var ordem = carrinho.map(function (item, index) {
            return { item: item, index: index };
        }).reverse();

        ordem.forEach(function (entry) {
            var item = entry.item;
            var index = entry.index;
            var totalItem = (Number(item.quantidade) * Number(item.precoUnitario)) - Number(item.desconto || 0);
            var card = document.createElement('div');
            card.className = 'pdv-item-card';
            if (String(item.produtoId) === String(ultimoProdutoId)) {
                card.className += ' pdv-item-card--novo';
            }
            card.setAttribute('data-produto-id', item.produtoId);
            card.setAttribute('data-index', index);
            card.tabIndex = 0;

            card.innerHTML =
                '<div class="pdv-item-info">' +
                    '<div class="pdv-item-nome">' + escapeHtml(item.nome) + '</div>' +
                    '<div class="pdv-item-meta">' +
                        '<span>' + escapeHtml(item.codigo || '') + '</span>' +
                        (item.unidade ? '<span>· ' + escapeHtml(item.unidade) + '</span>' : '') +
                        '<span>· ' + formatMoney(item.precoUnitario) + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="pdv-qtd-controles">' +
                    '<button type="button" class="pdv-btn-qtd" data-acao="menos" aria-label="Diminuir">−</button>' +
                    '<span class="pdv-qtd-valor" data-acao="editar-qtd" title="Toque para editar">' + formatQtd(item.quantidade) + '</span>' +
                    '<button type="button" class="pdv-btn-qtd" data-acao="mais" aria-label="Aumentar">+</button>' +
                '</div>' +
                '<div class="pdv-item-total">' + formatMoney(totalItem) + '</div>' +
                '<button type="button" class="pdv-btn-remover" data-acao="remover" title="Remover" aria-label="Remover">×</button>';

            card.addEventListener('click', function (e) {
                var alvo = e.target.closest ? e.target.closest('[data-acao]') : e.target;
                var acao = alvo ? alvo.getAttribute('data-acao') : null;
                if (!acao) return;

                if (acao === 'mais') {
                    item.quantidade = Number(item.quantidade) + 1;
                    ultimoProdutoId = item.produtoId;
                    renderizarCarrinho();
                    focarBusca();
                } else if (acao === 'menos') {
                    if (Number(item.quantidade) <= 1) {
                        carrinho.splice(index, 1);
                    } else {
                        item.quantidade = Number(item.quantidade) - 1;
                    }
                    renderizarCarrinho();
                    focarBusca();
                } else if (acao === 'remover') {
                    carrinho.splice(index, 1);
                    renderizarCarrinho();
                    focarBusca();
                } else if (acao === 'editar-qtd') {
                    editarQuantidadeInline(alvo, item);
                }
            });

            el.itens.appendChild(card);
        });

        atualizarTotais();
    }

    function editarQuantidadeInline(span, item) {
        if (!span || span.tagName === 'INPUT') return;

        var input = document.createElement('input');
        input.type = 'tel';
        input.inputMode = 'numeric';
        input.className = 'form-control pdv-qtd-input';
        input.value = formatQtd(item.quantidade);

        function commit() {
            var v = parseBR(input.value);
            if (v > 0) item.quantidade = v;
            renderizarCarrinho();
            focarBusca();
        }

        input.addEventListener('blur', commit);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                renderizarCarrinho();
                focarBusca();
            }
        });

        span.replaceWith(input);
        input.focus();
        input.select();
    }

    function toFormBody(obj) {
        var parts = [];

        function build(value, key) {
            if (value === null || typeof value === 'undefined') {
                parts.push(encodeURIComponent(key) + '=');
                return;
            }
            if (Array.isArray(value)) {
                value.forEach(function (v, i) { build(v, key + '[' + i + ']'); });
                return;
            }
            if (typeof value === 'object') {
                Object.keys(value).forEach(function (k) { build(value[k], key + '[' + k + ']'); });
                return;
            }
            parts.push(encodeURIComponent(key) + '=' + encodeURIComponent(String(value)));
        }

        Object.keys(obj).forEach(function (k) { build(obj[k], k); });
        return parts.join('&');
    }

    function finalizarVenda() {
        if (finalizando) return;
        if (el.shell && el.shell.classList.contains('pdv-shell--sucesso')) return;
        if (el.sucesso && !el.sucesso.hidden) return;

        esconderErro();
        if (el.formaErro) el.formaErro.hidden = true;

        if (!carrinho.length) {
            mostrarErro('Adicione pelo menos um produto à venda.');
            focarBusca();
            return;
        }

        if (!pagamentoAberto()) {
            abrirPagamento();
            return;
        }

        var forma = el.formaHidden ? el.formaHidden.value : '';
        if (!forma) {
            if (el.formaErro) el.formaErro.hidden = false;
            mostrarErro('Selecione a forma de pagamento.');
            return;
        }

        var totais = calcularTotais();
        var recebido = el.recebido && !formaAtualEhPrazo() ? parseBR(el.recebido.value) : 0;
        if (recebido > 0 && recebido + 0.001 < totais.total) {
            mostrarErro('O valor recebido é menor que o total. Corrija o valor ou deixe em branco para pagamento exato.');
            if (el.recebido) { el.recebido.focus(); el.recebido.select(); }
            return;
        }
        var troco = calcularTroco(totais.total);
        var payload = {
            cliente_id: el.clienteHidden && el.clienteHidden.value ? el.clienteHidden.value : '',
            data_venda: config.dataHoje || new Date().toISOString().slice(0, 10),
            situacao: 'confirmada',
            forma_pagamento: forma,
            subtotal: formatBR(totais.subtotal),
            desconto: formatBR(totais.desconto),
            acrescimo: formatBR(totais.acrescimo),
            total: formatBR(totais.total),
            itens: carrinho.map(function (item) {
                return {
                    produto_id: item.produtoId,
                    quantidade: (Number(item.quantidade) || 0).toFixed(3).replace('.', ','),
                    preco_unitario: formatBR(item.precoUnitario),
                    desconto: formatBR(item.desconto || 0),
                };
            }),
        };

        finalizando = true;
        if (el.btnFinalizar) el.btnFinalizar.disabled = true;
        if (el.btnConfirmar) el.btnConfirmar.disabled = true;

        fetch(urls.vendaStore, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            redirect: 'manual',
            body: toFormBody(payload),
        })
            .then(function (response) {
                if (response.status === 201 || response.status === 200) {
                    return response.json()
                        .then(function (data) { abrirTelaSucesso(totais.total, data || {}, troco); })
                        .catch(function () { mostrarErro('Venda enviada, mas a resposta do servidor não foi reconhecida. Confira a lista de vendas antes de tentar novamente.'); });
                }
                if (response.type === 'opaqueredirect' || response.status === 401 || response.status === 419) {
                    return mostrarErro('Sua sessão expirou. Recarregue a página e faça login novamente; a venda não foi registrada.');
                }
                if (response.status === 422) {
                    return response.json().then(function (data) {
                        var msgs = [];
                        if (data && data.errors) {
                            Object.keys(data.errors).forEach(function (k) {
                                (data.errors[k] || []).forEach(function (m) { msgs.push(m); });
                            });
                        } else if (data && data.message) {
                            msgs.push(data.message);
                        }
                        mostrarErro(msgs.join(' ') || 'Dados inválidos. Verifique os campos.');
                    });
                }
                return response.text().then(function () {
                    mostrarErro('Não foi possível finalizar a venda. Tente novamente.');
                });
            })
            .catch(function () {
                mostrarErro('Erro de comunicação com o servidor. Tente novamente.');
            })
            .finally(function () {
                finalizando = false;
                if (el.btnFinalizar) el.btnFinalizar.disabled = false;
                if (el.btnConfirmar) el.btnConfirmar.disabled = false;
            });
    }

    function abrirTelaSucesso(total, data, troco) {
        if (el.pagamento) el.pagamento.hidden = true;
        if (el.sucessoTroco) {
            el.sucessoTroco.hidden = !(troco > 0);
            el.sucessoTroco.textContent = troco > 0 ? 'Troco: ' + formatMoney(troco) : '';
        }
        if (el.sucessoPagamento) {
            var formaNome = el.formaHidden && el.formaHidden.value ? el.formaHidden.value : '';
            el.sucessoPagamento.textContent = formaNome ? ('Pago em ' + formaNome) : 'Pagamento registrado';
        }
        if (el.sucessoTotal) el.sucessoTotal.textContent = 'Total: ' + formatMoney(total);
        if (el.sucessoNumero) {
            el.sucessoNumero.textContent = data && data.numero != null
                ? ('Nº ' + data.numero)
                : 'Nº —';
        }
        if (el.linkVer && data && data.id) {
            el.linkVer.href = (urls.vendaShowBase || '/venda') + '/' + data.id + '/ver';
        }

        if (el.wrap) el.wrap.hidden = true;
        if (el.sucesso) {
            // reinicia animação do SVG
            el.sucesso.hidden = false;
            var svg = el.sucesso.querySelector('.pdv-sucesso__icone');
            if (svg) {
                var clone = svg.cloneNode(true);
                svg.parentNode.replaceChild(clone, svg);
            }
        }
        if (el.shell) el.shell.classList.add('pdv-shell--sucesso');
        document.body.classList.add('pdv-sucesso-ativo');
        // evita scroll residual do container principal
        var main = document.querySelector('.main');
        if (main) main.scrollTop = 0;
        if (el.btnNova) setTimeout(function () { el.btnNova.focus(); }, 100);
    }

    function atalhosTeclado() {
        document.addEventListener('keydown', function (e) {
            if (!telaAdequada()) return;
            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            var isTyping = tag === 'input' || tag === 'textarea' || tag === 'select';
            var sucessoAberto = (el.shell && el.shell.classList.contains('pdv-shell--sucesso'))
                || (el.sucesso && !el.sucesso.hidden);

            if (sucessoAberto) {
                if (e.key === 'n' || e.key === 'N' || (e.key === 'Enter' && e.target !== el.linkVer)) {
                    e.preventDefault();
                    window.location.reload();
                }
                return;
            }

            if (e.key === 'F1') {
                e.preventDefault();
                if (ajudaAberta()) fecharAjuda(); else abrirAjuda();
                return;
            }

            if (ajudaAberta()) {
                if (e.key === 'Escape') {
                    e.preventDefault();
                    fecharAjuda();
                }
                return;
            }

            if (pagamentoAberto()) {
                atalhosPagamento(e, isTyping);
                return;
            }

            if (e.key === 'F3') {
                e.preventDefault();
                if (el.inputCliente) {
                    el.inputCliente.focus();
                    el.inputCliente.select();
                }
                return;
            }

            if (e.key === 'F9') {
                e.preventDefault();
                editarQuantidadeUltimo();
                return;
            }

            if (e.key === 'Delete' && e.ctrlKey) {
                e.preventDefault();
                if (e.shiftKey) cancelarVenda(); else removerUltimo();
                return;
            }

            if (e.key === 'F2' || (!isTyping && e.key === '/')) {
                e.preventDefault();
                if (e.key === 'F2') {
                    fecharSugestoes();
                    focarBusca({ selecionar: true });
                } else {
                    focarBusca();
                }
                return;
            }

            if (e.key === 'F4') {
                e.preventDefault();
                abrirPagamento();
                return;
            }

            if (e.key === 'F6') {
                e.preventDefault();
                cicloFormaPagamento();
                return;
            }

            if (e.key === 'F5') {
                e.preventDefault();
                focarBusca();
                return;
            }

            if (e.key === 'Escape') {
                limparBusca();
                focarBusca();
                return;
            }

            if (e.code === 'NumpadAdd' || e.code === 'NumpadSubtract') {
                var card = document.activeElement && document.activeElement.closest
                    ? document.activeElement.closest('.pdv-item-card')
                    : null;
                if (!card) return;
                var idx = parseInt(card.getAttribute('data-index'), 10);
                if (isNaN(idx) || !carrinho[idx]) return;
                e.preventDefault();
                if (e.code === 'NumpadAdd') {
                    carrinho[idx].quantidade = Number(carrinho[idx].quantidade) + 1;
                } else if (Number(carrinho[idx].quantidade) <= 1) {
                    carrinho.splice(idx, 1);
                } else {
                    carrinho[idx].quantidade = Number(carrinho[idx].quantidade) - 1;
                }
                renderizarCarrinho();
            }
        });
    }

    function atalhosPagamento(e, isTyping) {
        if (e.key === 'Escape') {
            e.preventDefault();
            fecharPagamento();
            return;
        }

        if (e.key === 'F4' || (e.key === 'Enter' && !e.target.closest('[data-pag-fechar]'))) {
            e.preventDefault();
            finalizarVenda();
            return;
        }

        if (e.key === 'F6' || e.key === 'ArrowDown' || (e.key === 'ArrowRight' && !isTyping)) {
            e.preventDefault();
            cicloFormaPagamento(1);
            return;
        }

        if (e.key === 'ArrowUp' || (e.key === 'ArrowLeft' && !isTyping)) {
            e.preventDefault();
            cicloFormaPagamento(-1);
            return;
        }

        if (e.key === 'F7' && el.desconto) {
            e.preventDefault();
            el.desconto.focus();
            el.desconto.select();
            return;
        }

        if (e.key === 'F8' && el.recebido && !formaAtualEhPrazo()) {
            e.preventDefault();
            el.recebido.focus();
            el.recebido.select();
            return;
        }

        if (!isTyping && /^[1-9]$/.test(e.key)) {
            var btn = botoesForma()[parseInt(e.key, 10) - 1];
            if (btn) {
                e.preventDefault();
                selecionarFormaPagamento(btn, true);
            }
        }
    }

    function bindScannerNoInput() {
        if (!el.inputBusca) return;

        el.inputBusca.addEventListener('keydown', function (e) {
            var listaAberta = el.sugestoes && !el.sugestoes.hidden && sugestoes.length > 0;

            if (e.key === 'ArrowDown') {
                if (!listaAberta) return;
                e.preventDefault();
                destacarSugestao(sugestaoAtiva < 0 ? 0 : sugestaoAtiva + 1);
                return;
            }

            if (e.key === 'ArrowUp') {
                if (!listaAberta) return;
                e.preventDefault();
                destacarSugestao(sugestaoAtiva < 0 ? sugestoes.length - 1 : sugestaoAtiva - 1);
                return;
            }

            if (e.key === 'Enter') {
                e.preventDefault();
                e.stopPropagation();
                confirmarBusca();
                return;
            }

            if (e.key === 'Escape') {
                e.preventDefault();
                limparBusca();
                return;
            }

            // teclas de digitação — marca ritmo de bipagem
            if (e.key.length === 1 || e.key === 'Backspace') {
                var now = Date.now();
                if (now - lastKeyTs < 45) {
                    rapidChars += 1;
                } else {
                    rapidChars = 1;
                }
                lastKeyTs = now;

                clearTimeout(scannerTimer);
                // leitor sem Enter: rajada rápida + pausa → confirma automaticamente
                scannerTimer = setTimeout(function () {
                    var termo = separarQuantidade(el.inputBusca.value || '').termo;
                    if (rapidChars >= 6 && pareceCodigo(termo)) {
                        confirmarBusca();
                    }
                    rapidChars = 0;
                }, 120);
            }
        });

        el.inputBusca.addEventListener('paste', function () {
            setTimeout(function () {
                var termo = separarQuantidade(el.inputBusca.value || '').termo;
                if (pareceCodigo(termo)) {
                    confirmarBusca();
                }
            }, 10);
        });

        el.inputBusca.addEventListener('input', function () {
            var termo = el.inputBusca.value || '';
            sugestaoAtiva = -1;
            if (pareceCodigo(termo.trim()) && /^\d{6,}$/.test(termo.trim())) {
                fecharSugestoes();
                return;
            }
            buscarProduto(termo);
        });
    }

    function botoesForma() {
        if (!el.formasGrid) return [];
        return Array.prototype.slice.call(el.formasGrid.querySelectorAll('.pdv-forma-btn'));
    }

    function selecionarFormaPagamento(btn, gravar) {
        if (!btn) return;
        botoesForma().forEach(function (b) {
            var ativa = b === btn;
            b.classList.toggle('pdv-forma-btn--ativa', ativa);
            b.setAttribute('aria-pressed', ativa ? 'true' : 'false');
        });
        botoesForma().forEach(function (b) {
            b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
        });
        var nome = btn.getAttribute('data-forma') || '';
        var tipo = btn.getAttribute('data-tipo') || '';
        if (el.formaHidden) el.formaHidden.value = nome;
        if (el.formaRotulo) el.formaRotulo.textContent = nome ? (nome + (tipo ? ' · ' + tipo : '')) : 'Sem pagamento';
        if (el.resumoForma) el.resumoForma.textContent = nome ? (nome + (tipo ? ' · ' + tipo : '')) : 'Sem pagamento';
        if (el.formaErro) el.formaErro.hidden = true;
        atualizarResumoPagamento();
        if (typeof btn.scrollIntoView === 'function') {
            btn.scrollIntoView({ block: 'nearest' });
        }
        if (gravar && nome) {
            try { sessionStorage.setItem('pdv-forma-pagamento', nome); } catch (e) { /* ignore */ }
        }
    }

    function restaurarFormaPagamento() {
        var botoes = botoesForma();
        if (!botoes.length) return;
        var salva = '';
        try { salva = sessionStorage.getItem('pdv-forma-pagamento') || ''; } catch (e) { /* ignore */ }
        var escolhido = botoes[0];
        if (salva) {
            botoes.forEach(function (btn) {
                if ((btn.getAttribute('data-forma') || '') === salva) escolhido = btn;
            });
        }
        selecionarFormaPagamento(escolhido, false);
    }

    function cicloFormaPagamento(direcao) {
        var botoes = botoesForma();
        if (!botoes.length) {
            mostrarToast('Nenhuma forma de pagamento ativa.');
            return;
        }
        var atual = -1;
        botoes.forEach(function (btn, i) {
            if (btn.classList.contains('pdv-forma-btn--ativa')) atual = i;
        });
        var passo = direcao === -1 ? -1 : 1;
        var proximo = botoes[(atual + passo + botoes.length) % botoes.length];
        selecionarFormaPagamento(proximo, true);
        var nome = proximo.getAttribute('data-forma') || '';
        var tipo = proximo.getAttribute('data-tipo') || '';
        mostrarToast(nome + (tipo ? ' · ' + tipo : ''));
    }

    function bindEventos() {
        bindScannerNoInput();

        document.addEventListener('click', function (e) {
            if (!el.sugestoes || el.sugestoes.hidden) return;
            if (el.sugestoes.contains(e.target) || (el.inputBusca && (e.target === el.inputBusca || el.inputBusca.contains(e.target)))) return;
            fecharSugestoes();
        });

        if (el.formasGrid) {
            el.formasGrid.querySelectorAll('.pdv-forma-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selecionarFormaPagamento(btn, true);
                    if (!formaAtualEhPrazo() && el.recebido) el.recebido.focus();
                });
            });
        }

        if (el.recebido) {
            el.recebido.addEventListener('input', function () { atualizarTroco(calcularTotais().total); });
        }
        if (el.btnConfirmar) el.btnConfirmar.addEventListener('click', finalizarVenda);
        if (el.pagamento) {
            el.pagamento.querySelectorAll('[data-pag-fechar]').forEach(function (b) {
                b.addEventListener('click', fecharPagamento);
            });
            el.pagamento.addEventListener('click', function (e) {
                if (e.target === el.pagamento) fecharPagamento();
            });
        }
        if (el.btnAjuda) el.btnAjuda.addEventListener('click', abrirAjuda);
        if (el.ajuda) {
            el.ajuda.querySelectorAll('[data-ajuda-fechar]').forEach(function (b) {
                b.addEventListener('click', fecharAjuda);
            });
            el.ajuda.addEventListener('click', function (e) {
                if (e.target === el.ajuda) fecharAjuda();
            });
        }

        if (el.desconto) {
            el.desconto.addEventListener('input', atualizarTotais);
            el.desconto.addEventListener('blur', atualizarTotais);
        }
        if (el.acrescimo) {
            el.acrescimo.addEventListener('input', atualizarTotais);
            el.acrescimo.addEventListener('blur', atualizarTotais);
        }

        if (el.btnFinalizar) el.btnFinalizar.addEventListener('click', abrirPagamento);
        if (el.alertFechar) el.alertFechar.addEventListener('click', esconderErro);
        if (el.btnNova) {
            el.btnNova.addEventListener('click', function () {
                window.location.reload();
            });
        }

        if (typeof initAutocomplete === 'function') {
            initAutocomplete(
                '#pdv-input-cliente',
                '#pdv-hidden-cliente-id',
                urls.clientesBusca,
                function (item) {
                    var doc = item.cpf || item.cnpj || '';
                    return item.nome + (doc ? ' — ' + doc : '');
                },
                function () {
                    atualizarResumoPagamento();
                    focarBusca();
                }
            );
        }

        // mantém foco no campo de bipagem (exceto ao digitar em outros inputs)
        document.addEventListener('click', function (e) {
            var t = e.target;
            if (!t) return;
            if (t.closest && (t.closest('input, textarea, select, button, a, .autocomplete-list, .pdv-qtd-controles, .pdv-ajustes, .pdv-pag-overlay'))) {
                return;
            }
            if (pagamentoAberto() || ajudaAberta()) return;
            if (el.shell && el.shell.classList.contains('pdv-shell--sucesso')) return;
            if (el.sucesso && !el.sucesso.hidden) return;
            setTimeout(focarBusca, 0);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncTelaMinima();
        iniciarPdv();
    });
})();
