(function () {
    'use strict';

    var config = window.PDV_CONFIG || {};
    var urls = config.urls || {};
    var carrinho = [];
    var sugestoes = [];
    var sugestaoAtiva = -1;
    var debounceTimer = null;
    var pedidoBusca = 0;
    var timerBusca = null;
    var toastTimer = null;
    var scannerTimer = null;
    var qtdDestaqueTimer = null;
    var finalizando = false;
    var recebidoAcompanhaTotal = true;
    var buscandoCodigo = false;
    var ultimoProdutoId = null;
    var lastKeyTs = 0;
    var rapidChars = 0;
    var catalogo = [];
    var catalogoPronto = false;
    var catalogoTruncado = false;
    var rascunhosTabela = {};
    var tabelaVisiveis = [];
    var tabelaAtiva = 0;
    var tabelaBuscaTimer = null;
    var tabelaBuscaId = 0;
    var catalogoCarregando = null;
    var TABELA_LIMITE = 2000;
    var TABELA_VISIVEIS = 80;

    var PDV_MIN_WIDTH = 1024;

    var el = {
        shell: document.getElementById('pdv-shell'),
        wrap: document.getElementById('pdv-wrap'),
        sucesso: document.getElementById('pdv-sucesso'),
        inputBusca: document.getElementById('pdv-input-busca'),
        inputQtd: document.getElementById('pdv-input-qtd'),
        qtdBusca: document.getElementById('pdv-qtd-busca'),
        sugestoes: document.getElementById('pdv-sugestoes'),
        itens: document.getElementById('pdv-itens'),
        empty: document.getElementById('pdv-empty'),
        itensCount: document.getElementById('pdv-itens-count'),
        itensResumo: document.getElementById('pdv-itens-resumo'),
        gradeHead: document.getElementById('pdv-grade-head'),
        desconto: document.getElementById('pdv-desconto'),
        acrescimo: document.getElementById('pdv-acrescimo'),
        displaySubtotal: document.getElementById('pdv-display-subtotal'),
        displayTotal: document.getElementById('pdv-display-total'),
        linhaDesconto: document.getElementById('pdv-linha-desconto'),
        displayDesconto: document.getElementById('pdv-display-desconto'),
        linhaAcrescimo: document.getElementById('pdv-linha-acrescimo'),
        displayAcrescimo: document.getElementById('pdv-display-acrescimo'),
        btnFinalizar: document.getElementById('pdv-btn-finalizar'),
        btnFinalizarValor: document.getElementById('pdv-btn-finalizar-valor'),
        formaHidden: document.getElementById('pdv-hidden-forma-pagamento'),
        formaErro: document.getElementById('pdv-forma-erro'),
        formaRotulo: document.getElementById('pdv-btn-finalizar-forma'),
        formasGrid: document.getElementById('pdv-formas-grid'),
        sucessoPagamento: document.getElementById('pdv-sucesso-pagamento'),
        sucessoTotal: document.getElementById('pdv-sucesso-total'),
        sucessoNumero: document.getElementById('pdv-sucesso-numero'),
        btnNova: document.getElementById('pdv-btn-nova-venda'),
        linkVer: document.getElementById('pdv-link-ver-venda'),
        linkA4: document.getElementById('pdv-link-a4'),
        linkRecibo: document.getElementById('pdv-link-recibo'),
        clienteHidden: document.getElementById('pdv-hidden-cliente-id'),
        toast: document.getElementById('pdv-toast'),
        painelExtra: document.getElementById('pdv-painel-extra'),
        telaPequena: document.getElementById('pdv-tela-pequena'),
        larguraAtual: document.getElementById('pdv-largura-atual'),
        pagamento: document.getElementById('pdv-pagamento'),
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
        btnForma: document.getElementById('pdv-btn-forma'),
        inputCliente: document.getElementById('pdv-input-cliente'),
        ajuda: document.getElementById('pdv-ajuda'),
        btnAjuda: document.getElementById('pdv-btn-ajuda'),
        sucessoTroco: document.getElementById('pdv-sucesso-troco'),
        sucessoNfce: document.getElementById('pdv-sucesso-nfce'),
        linkNfce: document.getElementById('pdv-link-nfce'),
        documentoNfce: document.getElementById('pdv-nfce-documento'),
        tabela: document.getElementById('pdv-tabela'),
        tabelaBody: document.getElementById('pdv-tabela-body'),
        tabelaFiltro: document.getElementById('pdv-tabela-filtro'),
        tabelaResumo: document.getElementById('pdv-tabela-resumo'),
        btnTabela: document.getElementById('pdv-btn-tabela'),
    };

    var pagUi = null;

    function pagamentoAberto() {
        return !!(el.pagamento && !el.pagamento.hidden);
    }

    function ajudaAberta() {
        return !!(el.ajuda && !el.ajuda.hidden);
    }

    function tabelaAberta() {
        return !!(el.tabela && !el.tabela.hidden);
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
        if (typeof window.mostrarToast === 'function') {
            window.mostrarToast(msg || 'Ocorreu um erro. Tente novamente.', 'error');
        }
    }

    function esconderErro() {
        document.querySelectorAll('#toast-stack .toast--error').forEach(function (t) { t.remove(); });
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

    function lerQuantidadeCampo() {
        if (!el.inputQtd) return 1;
        var n = parseBR(el.inputQtd.value);
        return n > 0 ? n : 1;
    }

    function definirQuantidade(valor, opcoes) {
        opcoes = opcoes || {};
        var n = typeof valor === 'number' ? valor : parseBR(valor);
        if (!(n > 0)) n = 1;
        if (el.inputQtd) el.inputQtd.value = formatQtdCampo(n);
        if (el.qtdBusca) {
            el.qtdBusca.classList.toggle('is-ativa', Math.abs(n - 1) > 0.0001);
            if (opcoes.destaque) {
                el.qtdBusca.classList.add('is-definida');
                clearTimeout(qtdDestaqueTimer);
                qtdDestaqueTimer = setTimeout(function () {
                    el.qtdBusca.classList.remove('is-definida');
                }, 700);
            }
        }
        atualizarDicaSugestao();
        return n;
    }

    function reiniciarQuantidade() {
        definirQuantidade(1);
    }

    function quantidadeDigitada() {
        return separarQuantidade(el.inputBusca ? el.inputBusca.value : '').quantidade;
    }

    /**
     * "3*" no começo da busca grava a quantidade e deixa o restante para o item.
     */
    function consumirPrefixoQuantidade() {
        if (!el.inputBusca) return false;
        return consumirPrefixoQuantidadeNoValor(el.inputBusca.value || '');
    }

    function consumirPrefixoQuantidadeNoValor(valor) {
        if (!el.inputBusca) return false;
        var re = /^(\s*)(\d+(?:[.,]\d{1,3})?)\s*\*(.*)$/;
        var mudou = false;
        var guard = 0;
        while (guard < 4) {
            var m = re.exec(valor);
            if (!m) break;
            definirQuantidade(m[2], { destaque: true });
            valor = String(m[3] || '').replace(/^\s+/, '');
            mudou = true;
            guard += 1;
        }
        if (mudou && el.inputBusca.value !== valor) el.inputBusca.value = valor;
        return mudou;
    }

    function adicionarSugestao(index) {
        var produto = sugestoes[index];
        if (!produto) return false;
        var qtd = lerQuantidadeCampo();
        adicionarAoCarrinho(normalizarProduto(produto, qtd));
        limparBusca();
        reiniciarQuantidade();
        focarBusca();
        return true;
    }

    function ocuparBusca(id) {
        window.clearTimeout(timerBusca);
        if (!el.inputBusca) return;
        el.inputBusca.classList.remove('is-loading');
        el.inputBusca.removeAttribute('aria-busy');
        timerBusca = window.setTimeout(function () {
            if (id !== pedidoBusca || !el.inputBusca) return;
            el.inputBusca.classList.add('is-loading');
            el.inputBusca.setAttribute('aria-busy', 'true');
        }, 200);
    }

    function liberarBusca(id) {
        if (id !== pedidoBusca) return;
        window.clearTimeout(timerBusca);
        if (!el.inputBusca) return;
        el.inputBusca.classList.remove('is-loading');
        el.inputBusca.removeAttribute('aria-busy');
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
            var id = ++pedidoBusca;
            ocuparBusca(id);
            fetch(urls.produtosBusca + '?q=' + encodeURIComponent(termo), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (id !== pedidoBusca) return;
                    sugestoes = Array.isArray(data) ? data : [];
                    renderizarSugestoes();
                })
                .catch(function () {
                    if (id !== pedidoBusca) return;
                    fecharSugestoes();
                })
                .finally(function () {
                    liberarBusca(id);
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
            var meta = 'Cód. ' + (p.codigo || '—');
            if (p.unidade) meta += ' · ' + p.unidade;
            return '<div class="pdv-sugestao-item" data-index="' + i + '" role="option">' +
                '<div class="pdv-sugestao-item__main">' +
                    '<div class="pdv-item-nome">' + escapeHtml(p.nome || '') + '</div>' +
                    '<div class="pdv-item-codigo">' + escapeHtml(meta) + '</div>' +
                '</div>' +
                '<div class="pdv-sugestao-item__meta">' +
                    '<div class="pdv-item-total">' + formatMoney(p.preco_venda) + '</div>' +
                    '<span class="pdv-stock">Est. ' + formatQtd(estoque) + '</span>' +
                '</div>' +
            '</div>';
        }).join('') +
            '<div class="pdv-sugestoes__rodape">' +
                '<span><kbd class="pdv-kbd">↑↓</kbd> navegam</span>' +
                '<span><kbd class="pdv-kbd">Enter</kbd> <span class="pdv-sugestoes__acao">adicionar</span></span>' +
                '<span><kbd class="pdv-kbd">Esc</kbd> fechar</span>' +
            '</div>';

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
        atualizarDicaSugestao();
    }

    function textoEnterSugestao() {
        var qtd = lerQuantidadeCampo();
        if (Math.abs(qtd - 1) < 0.0001) return 'adicionar';
        return 'adicionar ' + formatQtdCampo(qtd);
    }

    function atualizarDicaSugestao() {
        var acao = document.querySelector('.pdv-sugestoes__acao');
        if (acao) acao.textContent = textoEnterSugestao();
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
            estoque: p.estoque_atual != null ? Number(p.estoque_atual) : (p.estoque != null ? Number(p.estoque) : null),
            controlaEstoque: !!(p.controla_estoque || p.controlaEstoque),
        };
    }

    function adicionarAoCarrinho(produto, opts) {
        opts = opts || {};
        var existente = carrinho.find(function (item) {
            return String(item.produtoId) === String(produto.produtoId);
        });

        if (existente) {
            existente.quantidade = Number(existente.quantidade) + (Number(produto.quantidade) || 1);
            if (opts.substituirPreco) {
                existente.precoUnitario = Number(produto.precoUnitario) || 0;
            }
            if (produto.estoque != null) existente.estoque = produto.estoque;
            if (produto.controlaEstoque) existente.controlaEstoque = true;
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
                estoque: produto.estoque != null ? Number(produto.estoque) : null,
                controlaEstoque: !!produto.controlaEstoque,
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
        var id = ++pedidoBusca;
        ocuparBusca(id);
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
                if (id !== pedidoBusca) return false;
                if (!data || !data.encontrado || !data.produto) return false;
                adicionarAoCarrinho(normalizarProduto(data.produto, quantidade));
                return true;
            })
            .finally(function () {
                buscandoCodigo = false;
                liberarBusca(id);
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
        consumirPrefixoQuantidade();
        var separado = separarQuantidade(el.inputBusca ? el.inputBusca.value : '');
        var termo = separado.termo;
        var quantidade = /^\s*\d+(?:[.,]\d{1,3})?\s*\*/.test(el.inputBusca ? el.inputBusca.value : '')
            ? separado.quantidade
            : lerQuantidadeCampo();
        if (!termo || buscandoCodigo) return;

        clearTimeout(debounceTimer);
        clearTimeout(scannerTimer);
        esconderErro();

        var finalizarOk = function () {
            limparBusca();
            reiniciarQuantidade();
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
        if (el.linhaDesconto) el.linhaDesconto.hidden = !(totais.desconto > 0);
        if (el.displayDesconto) el.displayDesconto.textContent = '− ' + formatMoney(totais.desconto);
        if (el.linhaAcrescimo) el.linhaAcrescimo.hidden = !(totais.acrescimo > 0);
        if (el.displayAcrescimo) el.displayAcrescimo.textContent = '+ ' + formatMoney(totais.acrescimo);
        if (el.displayTotal) el.displayTotal.textContent = formatMoney(totais.total);
        if (el.btnFinalizarValor) el.btnFinalizarValor.textContent = formatMoney(totais.total);
        if (el.pagSubtotal) el.pagSubtotal.textContent = formatMoney(totais.subtotal);
        if (el.pagQtdItens) el.pagQtdItens.textContent = String(carrinho.length);
        if (el.pagTotal) el.pagTotal.textContent = formatMoney(totais.total);
        if (el.btnConfirmarValor) el.btnConfirmarValor.textContent = formatMoney(totais.total);
        if (pagUi && !pagUi.noFinanciamento()) pagUi.atualizarTotal(totais.total, config.dataHoje);
        if (pagamentoAberto() && recebidoAcompanhaTotal && el.recebido && !formaAtualEhPrazo()) {
            el.recebido.value = formatBR(totais.total);
        }
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
            if (!semCliente && el.inputCliente && el.inputCliente.value) {
                el.pagCliente.textContent = el.inputCliente.value;
            } else {
                el.pagCliente.textContent = prazo ? 'Não informado' : 'Consumidor Final';
            }
        }
        atualizarTotais();
    }

    function esconderDica() {
        var tip = document.querySelector('.pdv-tip');
        if (tip) tip.hidden = true;
    }

    function syncCamadaDialogo() {
        document.body.classList.toggle('pdv-dialogo-aberto', pagamentoAberto() || ajudaAberta() || tabelaAberta());
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
        esconderDica();
        recebidoAcompanhaTotal = true;
        if (pagUi) pagUi.reset();
        el.pagamento.hidden = false;
        syncCamadaDialogo();
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

    function voltarPagamento() {
        if (pagUi && pagUi.noFinanciamento()) {
            pagUi.voltar();
            return;
        }
        fecharPagamento();
    }

    function fecharPagamento() {
        if (!el.pagamento) return;
        if (pagUi) pagUi.reset();
        el.pagamento.hidden = true;
        syncCamadaDialogo();
        esconderErro();
        focarBusca();
    }

    function abrirAjuda() {
        esconderDica();
        if (el.ajuda) el.ajuda.hidden = false;
        syncCamadaDialogo();
    }

    function fecharAjuda() {
        if (el.ajuda) el.ajuda.hidden = true;
        syncCamadaDialogo();
        if (!pagamentoAberto() && !tabelaAberta()) focarBusca();
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

        var unidades = 0;
        carrinho.forEach(function (item) { unidades += Number(item.quantidade) || 0; });
        if (el.itensCount) {
            el.itensCount.textContent = String(carrinho.length);
            el.itensCount.hidden = !carrinho.length;
        }
        if (el.itensResumo) {
            el.itensResumo.textContent = carrinho.length
                ? (carrinho.length + (carrinho.length === 1 ? ' linha' : ' linhas') + ' · ' + formatQtd(unidades) + ' un')
                : 'Nenhum item';
        }
        if (el.gradeHead) el.gradeHead.hidden = !carrinho.length;

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
            var acimaEstoque = item.controlaEstoque && item.estoque != null && Number(item.quantidade) > Number(item.estoque) + 0.0001;
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
                        (item.codigo ? '<span>Cód. ' + escapeHtml(item.codigo) + '</span>' : '') +
                        (item.unidade ? '<span>' + escapeHtml(item.unidade) + '</span>' : '') +
                        '<button type="button" class="pdv-item-preco" data-acao="editar-preco" data-tip="Clique para alterar o preço unitário deste item.">' + formatMoney(item.precoUnitario) + '</button>' +
                        (acimaEstoque ? '<span class="pdv-item-alerta">Acima do estoque (' + formatQtd(item.estoque) + ')</span>' : '') +
                    '</div>' +
                '</div>' +
                '<div class="pdv-qtd-controles" data-tip="− e + ajustam uma unidade. Clique no número para digitar. F9 edita o último item.">' +
                    '<button type="button" class="pdv-btn-qtd" data-acao="menos" aria-label="Diminuir quantidade">−</button>' +
                    '<span class="pdv-qtd-valor" data-acao="editar-qtd">' + formatQtd(item.quantidade) + '</span>' +
                    '<button type="button" class="pdv-btn-qtd" data-acao="mais" aria-label="Aumentar quantidade">+</button>' +
                '</div>' +
                '<div class="pdv-item-total">' + formatMoney(totalItem) + '</div>' +
                '<button type="button" class="pdv-btn-remover" data-acao="remover" data-tip="Remove este item. Ctrl+Del remove o último lançado." aria-label="Remover item">×</button>';

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
                } else if (acao === 'editar-preco') {
                    editarPrecoInline(alvo, item);
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
        var cancelado = false;

        function commit() {
            if (cancelado) return;
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
                cancelado = true;
                renderizarCarrinho();
                focarBusca();
            }
        });

        span.replaceWith(input);
        input.focus();
        input.select();
    }

    function editarPrecoInline(span, item) {
        if (!span || span.tagName === 'INPUT') return;

        var input = document.createElement('input');
        input.type = 'tel';
        input.inputMode = 'numeric';
        input.className = 'form-control pdv-qtd-input input-numeric';
        input.setAttribute('data-decimals', '2');
        input.value = formatBR(item.precoUnitario);
        var cancelado = false;

        function commit() {
            if (cancelado) return;
            var v = parseBR(input.value);
            if (v >= 0) item.precoUnitario = v;
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
                cancelado = true;
                renderizarCarrinho();
                focarBusca();
            }
        });

        span.replaceWith(input);
        input.focus();
        input.select();
    }

    function textoBusca(valor) {
        return String(valor || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function rascunhoTabela(produto) {
        var id = String(produto.id);
        if (!rascunhosTabela[id]) {
            rascunhosTabela[id] = {
                preco: Number(produto.preco_venda) || 0,
                qtd: 1,
            };
        }
        return rascunhosTabela[id];
    }

    function prepararCatalogo(lista) {
        return (lista || []).map(function (produto) {
            produto._busca = textoBusca(
                (produto.nome || '') + ' ' + (produto.codigo || '') + ' ' + (produto.referencia || '') + ' ' + (produto.ean || '')
            );
            return produto;
        });
    }

    function carregarCatalogo() {
        if (catalogoPronto) return Promise.resolve(catalogo);
        if (catalogoCarregando) return catalogoCarregando;
        catalogoCarregando = fetch(urls.produtosBusca + '?simples=1&limit=' + TABELA_LIMITE, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) throw new Error('Falha ao carregar produtos');
                return r.json();
            })
            .then(function (data) {
                catalogo = prepararCatalogo(Array.isArray(data) ? data : []);
                catalogoTruncado = catalogo.length >= TABELA_LIMITE;
                catalogoPronto = true;
                return catalogo;
            })
            .catch(function (erro) {
                catalogoCarregando = null;
                throw erro;
            });
        return catalogoCarregando;
    }

    function mesclarCatalogo(lista) {
        var porId = {};
        catalogo.forEach(function (produto) { porId[String(produto.id)] = produto; });
        prepararCatalogo(lista).forEach(function (produto) {
            var atual = porId[String(produto.id)];
            if (atual) {
                atual.nome = produto.nome;
                atual.codigo = produto.codigo;
                atual.referencia = produto.referencia;
                atual.ean = produto.ean;
                atual.preco_venda = produto.preco_venda;
                atual.estoque_atual = produto.estoque_atual;
                atual.unidade = produto.unidade;
                atual._busca = produto._busca;
            } else {
                catalogo.push(produto);
            }
        });
    }

    function filtrarCatalogo(termo) {
        var q = textoBusca(termo).trim();
        if (!q) return catalogo.slice();
        var partes = q.split(/\s+/).filter(Boolean);
        return catalogo.filter(function (produto) {
            return partes.every(function (parte) {
                return produto._busca.indexOf(parte) !== -1;
            });
        });
    }

    function atualizarResumoTabela(totalFiltrado) {
        if (!el.tabelaResumo) return;
        if (!catalogoPronto) {
            el.tabelaResumo.textContent = 'Carregando produtos…';
            return;
        }
        var mostrando = Math.min(totalFiltrado, TABELA_VISIVEIS);
        var texto = mostrando + ' de ' + totalFiltrado + ' produto' + (totalFiltrado === 1 ? '' : 's');
        if (catalogoTruncado) {
            texto += ' · catálogo grande: a busca também consulta o servidor';
        } else if (totalFiltrado > TABELA_VISIVEIS) {
            texto += ' · refine o filtro para ver o restante';
        }
        el.tabelaResumo.textContent = texto;
    }

    function codigoExibido(produto) {
        var codigo = String((produto && produto.codigo) || '').trim();
        if (codigo) return codigo;
        return String((produto && produto.ean) || '').trim();
    }

    function renderizarTabela(termo) {
        if (!el.tabelaBody) return;
        var filtrados = filtrarCatalogo(termo);
        tabelaVisiveis = filtrados.slice(0, TABELA_VISIVEIS);
        if (tabelaAtiva >= tabelaVisiveis.length) tabelaAtiva = 0;
        atualizarResumoTabela(filtrados.length);

        if (!catalogoPronto) {
            el.tabelaBody.innerHTML = '<tr><td colspan="9" class="pdv-tabela__carregando">Carregando produtos…</td></tr>';
            return;
        }
        if (!tabelaVisiveis.length) {
            el.tabelaBody.innerHTML = '<tr><td colspan="9" class="pdv-tabela__vazio">Nenhum produto encontrado.</td></tr>';
            return;
        }

        el.tabelaBody.innerHTML = tabelaVisiveis.map(function (produto, i) {
            var rascunho = rascunhoTabela(produto);
            var estoque = produto.estoque_atual != null ? Number(produto.estoque_atual) : 0;
            var semEstoque = produto.controla_estoque && estoque <= 0;
            var total = (Number(rascunho.qtd) || 0) * (Number(rascunho.preco) || 0);
            return '<tr class="pdv-tabela__linha' + (semEstoque ? ' is-zero' : '') + (i === tabelaAtiva ? ' is-active' : '') + '" data-index="' + i + '">' +
                '<td class="pdv-tabela__codigo">' + escapeHtml(codigoExibido(produto) || '—') + '</td>' +
                '<td class="pdv-tabela__codigo">' + escapeHtml(produto.referencia || '—') + '</td>' +
                '<td class="pdv-tabela__codigo">' + escapeHtml(produto.ean || '—') + '</td>' +
                '<td class="pdv-tabela__nome">' + escapeHtml(produto.nome || '') +
                    (produto.unidade ? '<span class="pdv-tabela__unidade">' + escapeHtml(produto.unidade) + '</span>' : '') +
                '</td>' +
                '<td class="pdv-tabela__num pdv-tabela__estoque' + (semEstoque ? ' pdv-tabela__estoque--zero' : '') + '">' + formatQtd(estoque) + '</td>' +
                '<td class="pdv-tabela__num"><input type="tel" class="pdv-tabela__input input-numeric" data-campo="preco" data-decimals="2" inputmode="numeric" value="' + formatBR(rascunho.preco) + '" aria-label="Preço de ' + escapeHtml(produto.nome || 'produto') + '"></td>' +
                '<td class="pdv-tabela__qtd-celula"><div class="pdv-tabela__qtd">' +
                    '<button type="button" class="pdv-tabela__qtd-btn" data-qtd="-1" aria-label="Diminuir quantidade de ' + escapeHtml(produto.nome || 'produto') + '">−</button>' +
                    '<input type="text" inputmode="decimal" class="pdv-tabela__qtd-input" data-campo="qtd" value="' + formatQtdCampo(rascunho.qtd) + '" aria-label="Quantidade de ' + escapeHtml(produto.nome || 'produto') + '" autocomplete="off">' +
                    '<button type="button" class="pdv-tabela__qtd-btn" data-qtd="1" aria-label="Aumentar quantidade de ' + escapeHtml(produto.nome || 'produto') + '">+</button>' +
                '</div></td>' +
                '<td class="pdv-tabela__num pdv-tabela__total" data-total>' + (Number(rascunho.qtd) > 0 ? formatMoney(total) : '—') + '</td>' +
                '<td class="pdv-tabela__add"><button type="button" class="pdv-tabela__add-btn' + (produtoNoCarrinho(produto.id) ? '' : ' pdv-tabela__add-btn--novo') + '" data-adicionar>' + (produtoNoCarrinho(produto.id) ? 'Atualizar' : 'Adicionar') + '</button></td>' +
            '</tr>';
        }).join('');
    }

    function formatQtdCampo(val) {
        var n = Number(val) || 0;
        if (Math.abs(n - Math.round(n)) < 0.0001) return String(Math.round(n));
        return n.toFixed(3).replace(/0+$/, '').replace(/\.$/, '').replace('.', ',');
    }

    function ajustarQtdTabela(linha, delta) {
        var input = linha.querySelector('[data-campo="qtd"]');
        if (!input) return;
        var atual = parseBR(input.value);
        if (!(atual > 0)) atual = 1;
        var proxima = Math.round((atual + delta) * 1000) / 1000;
        if (proxima < 0.001) proxima = atual;
        input.value = formatQtdCampo(proxima);
        sincronizarRascunho(linha);
    }

    function sincronizarRascunho(linha) {
        var idx = parseInt(linha.getAttribute('data-index'), 10);
        var produto = tabelaVisiveis[idx];
        if (!produto) return null;
        var rascunho = rascunhoTabela(produto);
        var preco = linha.querySelector('[data-campo="preco"]');
        var qtd = linha.querySelector('[data-campo="qtd"]');
        if (preco) rascunho.preco = Math.max(0, parseBR(preco.value));
        if (qtd) rascunho.qtd = parseBR(qtd.value);
        var total = linha.querySelector('[data-total]');
        if (total) {
            var valorLinha = (Number(rascunho.qtd) || 0) * (Number(rascunho.preco) || 0);
            total.textContent = Number(rascunho.qtd) > 0 ? formatMoney(valorLinha) : '—';
        }
        return produto;
    }

    function destacarLinhaTabela(index) {
        if (!tabelaVisiveis.length) return;
        if (index < 0) index = tabelaVisiveis.length - 1;
        if (index >= tabelaVisiveis.length) index = 0;
        tabelaAtiva = index;
        if (!el.tabelaBody) return;
        el.tabelaBody.querySelectorAll('.pdv-tabela__linha').forEach(function (linha, i) {
            linha.classList.toggle('is-active', i === tabelaAtiva);
        });
        var ativa = el.tabelaBody.querySelector('.pdv-tabela__linha.is-active');
        if (ativa && typeof ativa.scrollIntoView === 'function') {
            ativa.scrollIntoView({ block: 'nearest' });
        }
    }

    function produtoNoCarrinho(id) {
        return carrinho.some(function (item) {
            return String(item.produtoId) === String(id);
        });
    }

    function confirmarCamposLinha(linha) {
        var qtdInput = linha.querySelector('[data-campo="qtd"]');
        var precoInput = linha.querySelector('[data-campo="preco"]');
        if (qtdInput) {
            var qtd = parseBR(qtdInput.value);
            qtdInput.value = formatQtdCampo(qtd > 0 ? qtd : 1);
        }
        if (precoInput) {
            precoInput.value = formatBR(Math.max(0, parseBR(precoInput.value)));
        }
        return sincronizarRascunho(linha);
    }

    function adicionarLinhaTabela(index) {
        var produto = tabelaVisiveis[index];
        if (!produto || !el.tabelaBody) return;
        var linha = el.tabelaBody.querySelector('.pdv-tabela__linha[data-index="' + index + '"]');
        if (linha) confirmarCamposLinha(linha);
        var rascunho = rascunhoTabela(produto);
        var qtd = Number(rascunho.qtd) > 0 ? Number(rascunho.qtd) : 1;
        var preco = Math.max(0, Number(rascunho.preco) || 0);
        var existente = carrinho.find(function (item) {
            return String(item.produtoId) === String(produto.id);
        });

        if (existente) {
            existente.quantidade = qtd;
            existente.precoUnitario = preco;
            if (!existente.codigo) existente.codigo = codigoExibido(produto);
            if (produto.estoque_atual != null) existente.estoque = Number(produto.estoque_atual);
            existente.controlaEstoque = !!produto.controla_estoque;
            ultimoProdutoId = existente.produtoId;
            renderizarCarrinho();
            mostrarToast((produto.nome || 'Produto') + ' · ' + formatQtd(qtd) + ' × ' + formatMoney(preco));
        } else {
            adicionarAoCarrinho(normalizarProduto({
                id: produto.id,
                nome: produto.nome,
                codigo: codigoExibido(produto),
                unidade: produto.unidade,
                preco_venda: preco,
                estoque_atual: produto.estoque_atual,
                controla_estoque: produto.controla_estoque,
            }, qtd), { substituirPreco: true });
        }

        if (linha) {
            var botao = linha.querySelector('[data-adicionar]');
            if (botao) botao.textContent = 'Atualizar';
        }
    }

    function buscarTabelaNoServidor(termo) {
        clearTimeout(tabelaBuscaTimer);
        if (!catalogoTruncado || textoBusca(termo).trim().length < 2) return;
        tabelaBuscaTimer = setTimeout(function () {
            var id = ++tabelaBuscaId;
            fetch(urls.produtosBusca + '?simples=1&limit=80&q=' + encodeURIComponent(termo.trim()), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (id !== tabelaBuscaId || !tabelaAberta()) return;
                    mesclarCatalogo(Array.isArray(data) ? data : []);
                    renderizarTabela(el.tabelaFiltro ? el.tabelaFiltro.value : '');
                })
                .catch(function () {});
        }, 180);
    }

    function abrirTabela() {
        if (!el.tabela || pagamentoAberto() || (el.sucesso && !el.sucesso.hidden)) return;
        fecharSugestoes();
        esconderDica();
        if (ajudaAberta()) fecharAjuda();
        el.tabela.hidden = false;
        syncCamadaDialogo();
        tabelaAtiva = 0;
        renderizarTabela(el.tabelaFiltro ? el.tabelaFiltro.value : '');
        carregarCatalogo()
            .then(function () {
                if (tabelaAberta()) renderizarTabela(el.tabelaFiltro ? el.tabelaFiltro.value : '');
            })
            .catch(function () {
                if (el.tabelaResumo) el.tabelaResumo.textContent = 'Não foi possível carregar os produtos.';
            });
        setTimeout(function () {
            if (el.tabelaFiltro) {
                el.tabelaFiltro.focus();
                el.tabelaFiltro.select();
            }
        }, 30);
    }

    function fecharTabela() {
        if (!el.tabela) return;
        el.tabela.hidden = true;
        syncCamadaDialogo();
        clearTimeout(tabelaBuscaTimer);
        focarBusca();
    }

    function atalhosTabela(e) {
        var noFiltro = e.target === el.tabelaFiltro;
        var linha = e.target && e.target.closest ? e.target.closest('.pdv-tabela__linha') : null;

        if (e.key === 'Escape') {
            e.preventDefault();
            fecharTabela();
            return;
        }

        if ((e.key === 'ArrowDown' || e.key === 'ArrowUp') && e.target && e.target.getAttribute && e.target.getAttribute('data-campo') === 'qtd') {
            e.preventDefault();
            if (linha) ajustarQtdTabela(linha, e.key === 'ArrowUp' ? 1 : -1);
            return;
        }

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            destacarLinhaTabela(tabelaAtiva + 1);
            return;
        }

        if (e.key === 'ArrowUp') {
            e.preventDefault();
            destacarLinhaTabela(tabelaAtiva - 1);
            return;
        }

        if (e.key === 'Enter') {
            e.preventDefault();
            var idx = linha ? parseInt(linha.getAttribute('data-index'), 10) : tabelaAtiva;
            if (!isNaN(idx)) adicionarLinhaTabela(idx);
            if (noFiltro && el.tabelaFiltro) el.tabelaFiltro.focus();
        }
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
        if (formaAtualEhPrazo() && pagUi && !pagUi.noFinanciamento()) {
            if (totais.desconto > totais.subtotal + 0.001) {
                mostrarErro('O desconto não pode ser maior que o subtotal dos itens.');
                if (el.desconto) { el.desconto.focus(); el.desconto.select(); }
                return;
            }
            pagUi.abrirFinanciamento(totais.total, config.dataHoje);
            return;
        }

        if (totais.desconto > totais.subtotal + 0.001) {
            mostrarErro('O desconto não pode ser maior que o subtotal dos itens.');
            if (el.desconto) { el.desconto.focus(); el.desconto.select(); }
            return;
        }
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
            valor_recebido: formatBR(recebido),
            nfce_documento: el.documentoNfce ? String(el.documentoNfce.value || '').replace(/\D/g, '') : '',
        };
        if (pagUi && pagUi.noFinanciamento()) {
            var formaPag = pagUi.forma();
            var listaPag = pagUi.parcelas();
            if (!(formaPag.cartao && listaPag.length < 2)) {
                payload.parcelas = listaPag.map(function (parcela) {
                    return {
                        vencimento: parcela.vencimento,
                        valor: parcela.valor.toFixed(2),
                        documento: parcela.documento || '',
                    };
                });
            }
        }

        finalizando = true;
        if (el.btnFinalizar) el.btnFinalizar.disabled = true;
        if (el.btnConfirmar) el.btnConfirmar.disabled = true;
        var restaurarRotulo = window.ErpCarregamento
            ? window.ErpCarregamento.marcarRotulo(
                el.btnConfirmar ? el.btnConfirmar.querySelector('.pdv-btn-finalizar__label') : null,
                config.emitirNfce ? 'Emitindo NFC-e...' : 'Finalizando...'
            )
            : function () {};
        var cancelarDemora = window.ErpCarregamento
            ? window.ErpCarregamento.agendarDemora(
                config.emitirNfce
                    ? 'Comunicando com a SEFAZ. Não feche o PDV.'
                    : 'Registrando a venda.'
            )
            : function () {};

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
                restaurarRotulo();
                cancelarDemora();
                if (el.btnFinalizar) el.btnFinalizar.disabled = false;
                if (el.btnConfirmar) el.btnConfirmar.disabled = false;
            });
    }

    function abrirTelaSucesso(total, data, troco) {
        esconderDica();
        if (el.pagamento) el.pagamento.hidden = true;
        if (el.sucessoTroco) {
            el.sucessoTroco.hidden = !(troco > 0);
            el.sucessoTroco.textContent = troco > 0 ? 'Troco: ' + formatMoney(troco) : '';
        }
        if (el.sucessoPagamento) {
            var formaNome = el.formaHidden && el.formaHidden.value ? el.formaHidden.value : '';
            el.sucessoPagamento.textContent = formaNome ? ('Pago em ' + formaNome) : 'Pagamento registrado';
        }
        if (el.sucessoTotal) el.sucessoTotal.textContent = formatMoney(total);
        if (el.sucessoNumero) {
            el.sucessoNumero.textContent = data && data.numero != null
                ? ('Nº ' + data.numero)
                : 'Nº —';
        }
        if (el.linkVer && data && data.id) {
            el.linkVer.href = (urls.vendaShowBase || '/venda') + '/' + data.id + '/ver';
        }
        var baseVenda = data && data.id ? ((urls.vendaShowBase || '/venda') + '/' + data.id) : '';
        if (el.linkA4) {
            el.linkA4.hidden = baseVenda === '';
            if (baseVenda) el.linkA4.href = baseVenda + '/pdf';
        }
        if (el.linkRecibo) {
            el.linkRecibo.hidden = baseVenda === '';
            if (baseVenda) el.linkRecibo.href = baseVenda + '/recibo';
        }
        if (el.sucessoNfce) {
            var nfce = data && data.nfce ? data.nfce : null;
            if (nfce && nfce.mensagem) {
                el.sucessoNfce.hidden = false;
                el.sucessoNfce.textContent = nfce.mensagem;
                el.sucessoNfce.classList.toggle('pdv-sucesso__nfce--erro', !nfce.ok);
            } else {
                el.sucessoNfce.hidden = true;
                el.sucessoNfce.textContent = '';
            }
        }
        if (el.linkNfce) {
            if (data && data.nfce && data.nfce.ok && data.nfce.danfe_url) {
                el.linkNfce.hidden = false;
                el.linkNfce.href = data.nfce.danfe_url;
            } else {
                el.linkNfce.hidden = true;
                el.linkNfce.removeAttribute('href');
            }
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

    function atalhoComDialogo(e) {
        return e.key === 'F2' || e.key === 'F3' || e.key === 'F4' || e.key === 'F6'
            || e.key === 'F9' || e.key === 'F10' || (e.key === 'Delete' && e.ctrlKey);
    }

    function atalhosTeclado() {
        document.addEventListener('keydown', function (e) {
            if (!telaAdequada()) return;
            var tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
            var isTyping = tag === 'input' || tag === 'textarea' || tag === 'select';
            var sucessoAberto = (el.shell && el.shell.classList.contains('pdv-shell--sucesso'))
                || (el.sucesso && !el.sucesso.hidden);

            if (e.key === 'F5') {
                e.preventDefault();
                if (!sucessoAberto && !tabelaAberta() && !pagamentoAberto() && !ajudaAberta()) focarBusca();
                return;
            }

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
                    return;
                }
                if (!atalhoComDialogo(e)) return;
                fecharAjuda();
                if (pagamentoAberto() && e.key === 'F4') return;
            }

            if (e.key === 'F10') {
                e.preventDefault();
                if (tabelaAberta()) {
                    fecharTabela();
                    return;
                }
                if (pagamentoAberto()) fecharPagamento();
                abrirTabela();
                return;
            }

            if (tabelaAberta()) {
                if (e.key === 'F2' || e.key === 'F3' || e.key === 'F4' || e.key === 'F6' || e.key === 'F9' || (e.key === 'Delete' && e.ctrlKey)) {
                    fecharTabela();
                } else {
                    atalhosTabela(e);
                    return;
                }
            }

            if (pagamentoAberto()) {
                if (e.key === 'F2' || e.key === 'F3' || e.key === 'F9' || (e.key === 'Delete' && e.ctrlKey)) {
                    fecharPagamento();
                } else {
                    atalhosPagamento(e, isTyping);
                    return;
                }
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
            voltarPagamento();
            return;
        }

        if (e.key === 'Enter') {
            if (e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || '')) return;
            e.preventDefault();
            finalizarVenda();
            return;
        }

        if (pagUi && pagUi.noFinanciamento() && !isTyping) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowRight') {
                e.preventDefault();
                pagUi.cicloQuantidade(1);
                return;
            }
            if (e.key === 'ArrowUp' || e.key === 'ArrowLeft') {
                e.preventDefault();
                pagUi.cicloQuantidade(-1);
                return;
            }
            if (/^[1-9]$/.test(e.key)) {
                e.preventDefault();
                pagUi.definirQuantidade(parseInt(e.key, 10));
                return;
            }
            if (e.key === 'F6' || e.key === 'F7' || e.key === 'F8') {
                e.preventDefault();
                return;
            }
        }

        if (e.key === 'F4') {
            e.preventDefault();
            finalizarVenda();
            return;
        }

        if (e.key === 'F6' || ((e.key === 'ArrowDown' || e.key === 'ArrowRight') && !isTyping)) {
            e.preventDefault();
            cicloFormaPagamento(1);
            return;
        }

        if ((e.key === 'ArrowUp' || e.key === 'ArrowLeft') && !isTyping) {
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

            if (e.key === '*') {
                var antes = el.inputBusca.value || '';
                if (/^(\s*)(\d+(?:[.,]\d{1,3})?)\s*$/.test(antes)) {
                    e.preventDefault();
                    consumirPrefixoQuantidadeNoValor(antes + '*');
                    sugestaoAtiva = -1;
                    buscarProduto('');
                    return;
                }
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
            if (consumirPrefixoQuantidade()) {
                sugestaoAtiva = -1;
            }
            var termo = el.inputBusca.value || '';
            sugestaoAtiva = -1;
            if (pareceCodigo(termo.trim()) && /^\d{6,}$/.test(termo.trim())) {
                fecharSugestoes();
                return;
            }
            buscarProduto(termo);
        });

        if (el.inputQtd) {
            el.inputQtd.addEventListener('keydown', function (e) {
                if (e.key === '*' || e.key === 'Enter') {
                    e.preventDefault();
                    definirQuantidade(el.inputQtd.value, { destaque: true });
                    focarBusca();
                }
            });
            el.inputQtd.addEventListener('input', function () {
                if (el.qtdBusca) {
                    var n = parseBR(el.inputQtd.value);
                    el.qtdBusca.classList.toggle('is-ativa', n > 0 && Math.abs(n - 1) > 0.0001);
                }
                atualizarDicaSugestao();
            });
            el.inputQtd.addEventListener('blur', function () {
                definirQuantidade(el.inputQtd.value);
            });
        }
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
            b.classList.toggle('is-on', ativa);
            b.setAttribute('aria-pressed', ativa ? 'true' : 'false');
        });
        botoesForma().forEach(function (b) {
            b.setAttribute('aria-checked', b === btn ? 'true' : 'false');
        });
        var nome = btn.getAttribute('data-forma') || '';
        var tipo = btn.getAttribute('data-tipo') || '';
        if (el.formaHidden) el.formaHidden.value = nome;
        if (el.formaRotulo) el.formaRotulo.textContent = nome || 'Sem pagamento';
        if (el.resumoForma) el.resumoForma.textContent = nome ? (nome + (tipo ? ' · ' + tipo : '')) : 'Sem pagamento';
        if (el.formaErro) el.formaErro.hidden = true;
        atualizarResumoPagamento();
        if (typeof btn.scrollIntoView === 'function') {
            btn.scrollIntoView({ block: 'nearest' });
        }
        if (gravar && nome) {
            try { sessionStorage.setItem('pdv-forma-pagamento', nome); } catch (e) { /* ignore */ }
        }
        if (pagUi && !pagUi.noFinanciamento()) pagUi.atualizarAcao();
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
        if (el.pagamento && window.PagamentoModal) {
            pagUi = window.PagamentoModal.ligar(el.pagamento, {
                dataBase: config.dataHoje,
                rotuloConfirmar: config.emitirNfce ? 'Confirmar e emitir NFC-e' : 'Confirmar venda',
                rotuloVoltar: 'Voltar aos itens',
            });
        }
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
            el.recebido.addEventListener('input', function () {
                recebidoAcompanhaTotal = false;
                atualizarTroco(calcularTotais().total);
            });
        }
        if (el.btnConfirmar) el.btnConfirmar.addEventListener('click', finalizarVenda);
        if (el.pagamento) {
            el.pagamento.querySelectorAll('[data-pag-fechar]').forEach(function (b) {
                b.addEventListener('click', voltarPagamento);
            });
            el.pagamento.addEventListener('click', function (e) {
                if (e.target === el.pagamento) fecharPagamento();
            });
        }
        if (el.btnTabela) el.btnTabela.addEventListener('click', abrirTabela);
        if (el.tabela) {
            el.tabela.querySelectorAll('[data-tabela-fechar]').forEach(function (b) {
                b.addEventListener('click', fecharTabela);
            });
            el.tabela.addEventListener('click', function (e) {
                if (e.target === el.tabela) fecharTabela();
            });
        }
        if (el.tabelaFiltro) {
            el.tabelaFiltro.addEventListener('input', function () {
                tabelaAtiva = 0;
                renderizarTabela(el.tabelaFiltro.value);
                buscarTabelaNoServidor(el.tabelaFiltro.value);
            });
        }
        if (el.tabelaBody) {
            el.tabelaBody.addEventListener('input', function (e) {
                var linha = e.target.closest ? e.target.closest('.pdv-tabela__linha') : null;
                if (linha) sincronizarRascunho(linha);
            });
            el.tabelaBody.addEventListener('focusin', function (e) {
                if (e.target && e.target.getAttribute && e.target.getAttribute('data-campo') === 'qtd') {
                    e.target.select();
                }
            });
            el.tabelaBody.addEventListener('focusout', function (e) {
                if (!e.target || !e.target.getAttribute || e.target.getAttribute('data-campo') !== 'qtd') return;
                var linha = e.target.closest ? e.target.closest('.pdv-tabela__linha') : null;
                var n = parseBR(e.target.value);
                e.target.value = formatQtdCampo(n > 0 ? n : 1);
                if (linha) sincronizarRascunho(linha);
            });
            el.tabelaBody.addEventListener('click', function (e) {
                var linha = e.target.closest ? e.target.closest('.pdv-tabela__linha') : null;
                if (!linha) return;
                var idx = parseInt(linha.getAttribute('data-index'), 10);
                if (!isNaN(idx)) destacarLinhaTabela(idx);
                var passo = e.target.closest ? e.target.closest('[data-qtd]') : null;
                if (passo) {
                    ajustarQtdTabela(linha, parseInt(passo.getAttribute('data-qtd'), 10) || 0);
                    return;
                }
                if (e.target.closest && e.target.closest('[data-adicionar]')) {
                    adicionarLinhaTabela(idx);
                }
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

        document.querySelectorAll('[data-abrir-ajuda]').forEach(function (b) {
            b.addEventListener('click', abrirAjuda);
        });
        bindDicas();

        if (el.btnFinalizar) el.btnFinalizar.addEventListener('click', abrirPagamento);
        if (el.btnForma) el.btnForma.addEventListener('click', abrirPagamento);
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

    function bindDicas() {
        var tip = document.createElement('div');
        tip.className = 'pdv-tip';
        tip.setAttribute('role', 'tooltip');
        tip.hidden = true;
        document.body.appendChild(tip);
        var atual = null;

        function esconder() {
            atual = null;
            tip.hidden = true;
        }

        function mostrar(alvo) {
            var texto = alvo && alvo.getAttribute ? alvo.getAttribute('data-tip') : '';
            if (!texto || (alvo.getClientRects && !alvo.getClientRects().length)) {
                esconder();
                return;
            }
            atual = alvo;
            tip.textContent = texto;
            tip.hidden = false;
            var rect = alvo.getBoundingClientRect();
            var margem = 8;
            var left = rect.left;
            var top = rect.bottom + margem;
            if (left + tip.offsetWidth > window.innerWidth - margem) {
                left = window.innerWidth - tip.offsetWidth - margem;
            }
            if (top + tip.offsetHeight > window.innerHeight - margem) {
                top = rect.top - tip.offsetHeight - margem;
            }
            tip.style.left = Math.max(margem, left) + 'px';
            tip.style.top = Math.max(margem, top) + 'px';
        }

        document.addEventListener('mouseover', function (e) {
            var alvo = e.target && e.target.closest ? e.target.closest('[data-tip]') : null;
            if (alvo) mostrar(alvo);
            else if (atual && !(atual.contains && atual.contains(e.target))) esconder();
        });
        document.addEventListener('focusin', function (e) {
            var alvo = e.target && e.target.closest ? e.target.closest('[data-tip]') : null;
            if (alvo) mostrar(alvo);
        });
        document.addEventListener('focusout', function (e) {
            var destino = e.relatedTarget && e.relatedTarget.closest ? e.relatedTarget.closest('[data-tip]') : null;
            if (!destino) esconder();
        });
        document.addEventListener('scroll', esconder, true);
        window.addEventListener('resize', esconder);
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncTelaMinima();
        iniciarPdv();
    });
})();
