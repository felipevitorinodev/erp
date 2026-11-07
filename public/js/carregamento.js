// Estados de carregamento do ERP.
// Nada abaixo de 200 ms. Indicador local entre 200 e 800 ms.
// Skeleton só em troca de tela que passa de 800 ms.
// Barra com percentual apenas quando o valor recebido é real.
(function () {
    'use strict';

    var ATRASO = 200;
    var ESQUELETO = 800;
    var DEMORA = 3000;
    var ARQUIVO_MAX = 45000;

    var estado = {
        modo: null,
        timers: [],
        arquivoEm: 0
    };

    function $(id) {
        return document.getElementById(id);
    }

    function daqui(ms, fn) {
        var id = window.setTimeout(fn, ms);
        estado.timers.push(id);
        return id;
    }

    function limparTimers() {
        estado.timers.forEach(function (id) {
            window.clearTimeout(id);
        });
        estado.timers = [];
    }

    function anunciar(texto) {
        var status = $('erp-status');
        if (status) status.textContent = texto || '';
    }

    function registrar(tipo, ms, detalhe) {
        try {
            var lista = JSON.parse(sessionStorage.getItem('erp-tempos') || '[]');
            if (!Array.isArray(lista)) lista = [];
            lista.push({
                tipo: tipo,
                ms: Math.round(ms),
                detalhe: detalhe || '',
                em: Date.now()
            });
            if (lista.length > 40) lista = lista.slice(-40);
            sessionStorage.setItem('erp-tempos', JSON.stringify(lista));
        } catch (erro) {
            // Medição é auxiliar. Falha de storage não afeta a tela.
        }
    }

    function tipoTela(url) {
        var path = (url.pathname || '/').replace(/\/+$/, '') || '/';
        if (path === '/' || path === '/pdv' || /\/relatorios$/.test(path)) return 'painel';
        if (/\/(pdf|csv|xml|danfe|nfce)$/i.test(path)) return 'arquivo';
        if (/\/(novo|nova|editar|ajuste|inutilizar)$/.test(path)) return 'formulario';
        if (/\/ver$/.test(path)) return 'ficha';
        if (/\/fluxo-de-caixa$/.test(path)) return 'relatorio';
        if (/\/relatorios\//.test(path) && !/\/opcoes\//.test(path) && !/\/(resumo|dados)$/.test(path)) return 'relatorio';
        return 'tabela';
    }

    function ehArquivo(link) {
        if (!link) return false;
        if (link.hasAttribute('download')) return true;
        return tipoTela(link) === 'arquivo';
    }

    function encerrarVisual() {
        limparTimers();
        document.documentElement.classList.remove('is-nav', 'is-nav-esqueleto');
        var barra = $('nav-barra');
        if (barra) barra.hidden = true;
        var conteudo = document.querySelector('.page-content');
        if (conteudo) {
            conteudo.classList.remove('is-atualizando');
            if (conteudo.getAttribute('aria-busy') === 'true') conteudo.removeAttribute('aria-busy');
        }
        var esqueleto = $('nav-skeleton');
        if (esqueleto) {
            esqueleto.hidden = true;
            esqueleto.replaceChildren();
        }
        var processo = $('erp-processo');
        if (processo) processo.hidden = true;
        anunciar('');
        estado.modo = null;
    }

    function restaurarOcupados() {
        document.querySelectorAll('[data-erp-ocupado]').forEach(function (botao) {
            botao.disabled = false;
            botao.classList.remove('is-busy');
            botao.removeAttribute('aria-busy');
            if (Object.prototype.hasOwnProperty.call(botao.dataset, 'erpRotulo')) {
                var rotulo = botao.querySelector('.header__logout-text, .btn-text, .pdv-btn-finalizar__label');
                var nodo = botao.dataset.erpRotuloTipo === 'nodo' ? ultimoTexto(botao) : null;
                if (rotulo && botao.dataset.erpRotuloTipo === 'rotulo') rotulo.textContent = botao.dataset.erpRotulo;
                else if (nodo) nodo.textContent = botao.dataset.erpRotulo;
                else botao.textContent = botao.dataset.erpRotulo;
                delete botao.dataset.erpRotulo;
                delete botao.dataset.erpRotuloTipo;
            }
            if (Object.prototype.hasOwnProperty.call(botao.dataset, 'erpAria')) {
                if (botao.dataset.erpAria) botao.setAttribute('aria-label', botao.dataset.erpAria);
                else botao.removeAttribute('aria-label');
                delete botao.dataset.erpAria;
            }
            delete botao.dataset.erpOcupado;
        });
        document.querySelectorAll('form[data-erp-enviando]').forEach(function (form) {
            delete form.dataset.erpEnviando;
        });
        document.querySelectorAll('tr[data-erp-linha]').forEach(function (linha) {
            linha.removeAttribute('aria-busy');
            delete linha.dataset.erpLinha;
        });
    }

    function mostrarBarra() {
        document.documentElement.classList.add('is-nav');
        var barra = $('nav-barra');
        if (barra) barra.hidden = false;
        anunciar('Carregando');
    }

    function preencher(alvo, tipo) {
        if (!alvo) return;
        var tpl = $('tpl-skeleton-' + tipo) || $('tpl-skeleton-tabela');
        if (!tpl || !tpl.content) return;
        var host = document.createElement('div');
        host.className = 'sk-host' + (tipo === 'grafico' ? ' sk-host--cobertura' : '');
        host.appendChild(tpl.content.cloneNode(true));
        alvo.appendChild(host);
    }

    function mostrarEsqueleto(tipo) {
        var conteudo = document.querySelector('.page-content');
        var caixa = $('nav-skeleton');
        if (!conteudo || !caixa) return;
        if (caixa.parentNode !== conteudo) conteudo.appendChild(caixa);
        caixa.replaceChildren();
        preencher(caixa, tipo);
        caixa.hidden = false;
        conteudo.setAttribute('aria-busy', 'true');
        document.documentElement.classList.add('is-nav-esqueleto');
        document.documentElement.classList.remove('is-nav');
        var barra = $('nav-barra');
        if (barra) barra.hidden = true;
        anunciar('Carregando a tela');
    }

    function mostrarAtualizacao() {
        var conteudo = document.querySelector('.page-content');
        if (!conteudo) {
            mostrarBarra();
            return;
        }
        conteudo.classList.add('is-atualizando');
        conteudo.setAttribute('aria-busy', 'true');
        anunciar('Atualizando');
    }

    function mostrarProcesso(mensagem, percentual) {
        var caixa = $('erp-processo');
        var barra = $('erp-progresso');
        var valor = $('erp-progresso-valor');
        var msg = $('erp-progresso-msg');
        var pct = $('erp-progresso-pct');
        if (!caixa || !barra || !valor || !msg) return;

        msg.textContent = mensagem || 'Processando...';
        var definido = typeof percentual === 'number' && isFinite(percentual);
        if (definido) {
            var n = Math.max(0, Math.min(100, percentual));
            barra.classList.remove('is-indeterminado');
            barra.setAttribute('aria-valuenow', String(Math.round(n)));
            barra.removeAttribute('aria-valuetext');
            valor.style.width = n + '%';
            if (pct) {
                pct.hidden = false;
                pct.textContent = Math.round(n) + '%';
            }
        } else {
            barra.classList.add('is-indeterminado');
            barra.removeAttribute('aria-valuenow');
            barra.setAttribute('aria-valuetext', msg.textContent);
            valor.style.width = '';
            if (pct) {
                pct.hidden = true;
                pct.textContent = '';
            }
        }
        caixa.hidden = false;
        anunciar(msg.textContent);
    }

    function iniciarEspera(opcoes) {
        encerrarVisual();
        estado.modo = opcoes.modo;
        if (opcoes.modo !== 'arquivo') {
            try {
                sessionStorage.setItem('erp-nav', JSON.stringify({
                    t: Date.now(),
                    destino: opcoes.destino || '',
                    tipo: opcoes.modo
                }));
            } catch (erro) {}
        }

        daqui(ATRASO, function () {
            if (opcoes.modo === 'consulta') mostrarAtualizacao();
            else if (opcoes.modo === 'navegacao') mostrarBarra();
            else if (opcoes.modo === 'arquivo') mostrarProcesso(opcoes.mensagem || 'Gerando o arquivo...');
        });

        daqui(ESQUELETO, function () {
            if (opcoes.modo === 'navegacao') mostrarEsqueleto(opcoes.esqueleto || 'tabela');
        });

        daqui(DEMORA, function () {
            var texto = opcoes.mensagem || 'A operação continua em andamento.';
            if (opcoes.modo === 'navegacao' || opcoes.modo === 'consulta') {
                anunciar(texto);
                var nota = document.querySelector('#nav-skeleton .sk-nota, .page-content.is-atualizando .sk-nota');
                if (!nota) {
                    nota = document.createElement('p');
                    nota.className = 'sk-nota';
                    var destino = opcoes.modo === 'navegacao' ? $('nav-skeleton') : document.querySelector('.page-content');
                    if (destino) destino.prepend(nota);
                }
                nota.textContent = texto;
            } else {
                var barra = $('nav-barra');
                if (barra) barra.hidden = true;
                document.documentElement.classList.remove('is-nav');
                mostrarProcesso(texto);
            }
        });
    }

    function metodoForm(form) {
        var spoof = form.querySelector('input[name="_method"]');
        return String((spoof && spoof.value) || form.getAttribute('method') || 'get').toLowerCase();
    }

    function textoAutomatico(botao, form) {
        if (botao && botao.getAttribute('data-espera')) return botao.getAttribute('data-espera');
        var metodo = metodoForm(form);
        if (metodo === 'get') return '';
        var bruto = '';
        if (botao) bruto = (botao.getAttribute('aria-label') || botao.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (metodo === 'delete' || bruto.indexOf('exclu') !== -1) return 'Excluindo...';
        if (bruto.indexOf('inutil') !== -1) return 'Inutilizando...';
        if (bruto.indexOf('emit') !== -1) return 'Emitindo...';
        if (bruto.indexOf('import') !== -1) return 'Importando...';
        if (bruto.indexOf('consult') !== -1) return 'Consultando...';
        if (bruto.indexOf('cancel') !== -1) return 'Cancelando...';
        if (bruto.indexOf('confirm') !== -1) return 'Confirmando...';
        if (bruto.indexOf('finaliz') !== -1) return 'Finalizando...';
        if (bruto.indexOf('sair') !== -1) return 'Saindo...';
        if (bruto.indexOf('entr') !== -1) return 'Entrando...';
        if (bruto.indexOf('envi') !== -1) return 'Enviando...';
        if (bruto.indexOf('receb') !== -1 || bruto.indexOf('baix') !== -1 || bruto.indexOf('pagar') !== -1) return 'Registrando...';
        return 'Salvando...';
    }

    function mensagemDemora(texto, form) {
        var metodo = form ? metodoForm(form) : 'get';
        if (texto.indexOf('Emit') === 0) return 'Aguardando a resposta da SEFAZ. Não feche esta tela.';
        if (texto.indexOf('Inutil') === 0) return 'Enviando a inutilização para a SEFAZ.';
        if (texto.indexOf('Import') === 0) return 'Lendo o arquivo enviado.';
        if (texto.indexOf('Consult') === 0) return 'Consultando a situação na SEFAZ.';
        if (metodo === 'get') return 'A consulta está demorando mais que o normal.';
        return 'A operação continua em andamento.';
    }

    function resolverAlvo(form, submitter) {
        if (submitter) {
            var seletor = submitter.getAttribute('data-espera-para');
            if (seletor) {
                var apontado = document.querySelector(seletor);
                if (apontado) return apontado;
            }
            if (!submitter.hidden) return submitter;
        }
        return form.querySelector('button[type="submit"]:not([hidden]), input[type="submit"]:not([hidden])') || submitter;
    }

    function ultimoTexto(botao) {
        var achado = null;
        botao.childNodes.forEach(function (nodo) {
            if (nodo.nodeType === 3 && nodo.textContent.trim()) achado = nodo;
        });
        return achado;
    }

    function aplicarTexto(botao, texto) {
        var rotulo = botao.querySelector('.header__logout-text, .btn-text, .pdv-btn-finalizar__label');
        if (rotulo) {
            botao.dataset.erpRotulo = rotulo.textContent;
            botao.dataset.erpRotuloTipo = 'rotulo';
            rotulo.textContent = texto;
            return;
        }
        var nodo = ultimoTexto(botao);
        if (nodo) {
            botao.dataset.erpRotulo = nodo.textContent;
            botao.dataset.erpRotuloTipo = 'nodo';
            nodo.textContent = (botao.querySelector('svg, img') ? ' ' : '') + texto;
            return;
        }
        if (!botao.querySelector('svg')) {
            botao.dataset.erpRotulo = botao.textContent;
            botao.dataset.erpRotuloTipo = 'tudo';
            botao.textContent = texto;
            return;
        }
        botao.dataset.erpAria = botao.getAttribute('aria-label') || '';
        botao.setAttribute('aria-label', texto);
    }

    function marcarBotao(botao, texto) {
        if (!botao || botao.dataset.erpOcupado === '1') return;
        botao.dataset.erpOcupado = '1';
        botao.classList.add('is-busy');
        botao.setAttribute('aria-busy', 'true');
        if (texto) aplicarTexto(botao, texto);
        window.setTimeout(function () {
            if (botao.dataset.erpOcupado === '1') botao.disabled = true;
        }, 0);
    }

    function anotarEnvio(form, submitter) {
        if (!form || form.dataset.erpEnviando === '1') return;
        if (form.target && form.target !== '_self') return;
        form.dataset.erpEnviando = '1';

        var alvo = resolverAlvo(form, submitter);
        var metodo = metodoForm(form);
        var texto = (submitter && submitter.getAttribute('data-espera')) || textoAutomatico(alvo, form);
        var demoraExplicita = (submitter && submitter.getAttribute('data-demora'))
            || (alvo && alvo.getAttribute('data-demora'))
            || form.getAttribute('data-demora');
        var demora = demoraExplicita || mensagemDemora(texto, form);

        if (metodo !== 'get' && alvo) {
            marcarBotao(alvo, texto);
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (botao) {
                if (botao !== alvo) marcarBotao(botao, '');
            });
            var linha = alvo.closest ? alvo.closest('tr') : null;
            if (linha) {
                linha.dataset.erpLinha = '1';
                linha.setAttribute('aria-busy', 'true');
            }
        }

        var destino = '';
        try {
            destino = new URL(form.getAttribute('action') || window.location.href, window.location.href).pathname;
        } catch (erro) {
            destino = window.location.pathname;
        }

        var mesma = false;
        try {
            mesma = new URL(form.getAttribute('action') || window.location.href, window.location.href).pathname === window.location.pathname;
        } catch (erro2) {
            mesma = true;
        }

        iniciarEspera({
            modo: metodo === 'get' && mesma ? 'consulta' : (metodo === 'get' ? 'navegacao' : 'gravacao'),
            destino: destino,
            esqueleto: tipoTela(new URL(destino, window.location.href)),
            mensagem: demora
        });
    }

    function registrarNavegacao() {
        var bruto = null;
        try {
            bruto = sessionStorage.getItem('erp-nav');
            sessionStorage.removeItem('erp-nav');
        } catch (erro) {
            return;
        }
        if (!bruto) return;
        try {
            var dado = JSON.parse(bruto);
            if (!dado || !dado.t) return;
            registrar(dado.tipo || 'navegacao', Date.now() - dado.t, window.location.pathname);
        } catch (erro2) {}
    }

    function ocuparCampo(campo, atraso) {
        if (!campo) return function () {};
        var espera = window.setTimeout(function () {
            campo.classList.add('is-loading');
            campo.setAttribute('aria-busy', 'true');
        }, atraso == null ? ATRASO : atraso);
        return function liberar() {
            window.clearTimeout(espera);
            campo.classList.remove('is-loading');
            campo.removeAttribute('aria-busy');
        };
    }

    function marcarRotulo(rotulo, texto) {
        if (!rotulo) return function () {};
        var original = rotulo.textContent;
        rotulo.textContent = texto;
        var botao = rotulo.closest('button, a');
        if (botao) botao.setAttribute('aria-busy', 'true');
        return function restaurar() {
            rotulo.textContent = original;
            if (botao) botao.removeAttribute('aria-busy');
        };
    }

    function agendarDemora(mensagem, atraso) {
        var id = window.setTimeout(function () {
            mostrarProcesso(mensagem);
        }, atraso == null ? DEMORA : atraso);
        return function cancelar() {
            window.clearTimeout(id);
            var caixa = $('erp-processo');
            if (caixa && estado.modo !== 'arquivo' && estado.modo !== 'gravacao' && estado.modo !== 'navegacao') {
                caixa.hidden = true;
            }
            if (estado.modo === null) {
                if (caixa) caixa.hidden = true;
                anunciar('');
            }
        };
    }

    function durante(executor, opcoes) {
        opcoes = opcoes || {};
        var inicio = (window.performance && performance.now) ? performance.now() : Date.now();
        var atraso = opcoes.atraso == null ? ATRASO : opcoes.atraso;
        var timer = window.setTimeout(function () {
            if (typeof opcoes.mostrar === 'function') opcoes.mostrar();
        }, atraso);
        var promessa;
        try {
            promessa = executor();
        } catch (erro) {
            window.clearTimeout(timer);
            if (typeof opcoes.limpar === 'function') opcoes.limpar();
            return Promise.reject(erro);
        }
        return Promise.resolve(promessa).finally(function () {
            window.clearTimeout(timer);
            if (typeof opcoes.limpar === 'function') opcoes.limpar();
            var fim = (window.performance && performance.now) ? performance.now() : Date.now();
            registrar(opcoes.tipo || 'ajax', fim - inicio, opcoes.detalhe || '');
        });
    }

    function adiar(ms, fn) {
        var id = window.setTimeout(fn, ms);
        return function cancelar() {
            window.clearTimeout(id);
        };
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0) return;
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        var link = event.target.closest ? event.target.closest('a[href]') : null;
        if (!link || link.origin !== window.location.origin) return;

        if (ehArquivo(link)) {
            estado.arquivoEm = Date.now();
            iniciarEspera({
                modo: 'arquivo',
                destino: link.pathname,
                mensagem: 'Gerando o arquivo...'
            });
            daqui(ARQUIVO_MAX, encerrarVisual);
            return;
        }

        if (link.hasAttribute('data-no-loader')) return;
        if (link.target && link.target !== '_self') return;
        if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) return;

        var mesma = link.pathname === window.location.pathname;
        iniciarEspera({
            modo: mesma ? 'consulta' : 'navegacao',
            destino: link.pathname,
            esqueleto: tipoTela(link),
            mensagem: mesma
                ? 'A consulta está demorando mais que o normal.'
                : 'A abertura da tela está demorando mais que o normal.'
        });
    });

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.getAttribute) return;
        if (form.dataset.erpEnviando === '1') {
            event.preventDefault();
            event.stopPropagation();
        }
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || form.nodeName !== 'FORM') return;
        if (event.defaultPrevented) return;
        if (form.hasAttribute('data-no-loader')) return;
        anotarEnvio(form, event.submitter || null);
    });

    window.addEventListener('pageshow', function () {
        registrarNavegacao();
        restaurarOcupados();
        encerrarVisual();
    });

    window.addEventListener('focus', function () {
        if (estado.modo === 'arquivo' && Date.now() - estado.arquivoEm > ESQUELETO) {
            encerrarVisual();
        }
    });

    window.ErpCarregamento = {
        tipoTela: tipoTela,
        anotarEnvio: anotarEnvio,
        preencher: preencher,
        ocuparCampo: ocuparCampo,
        marcarRotulo: marcarRotulo,
        agendarDemora: agendarDemora,
        mostrarProcesso: mostrarProcesso,
        durante: durante,
        adiar: adiar,
        encerrar: encerrarVisual,
        tempos: function () {
            try {
                return JSON.parse(sessionStorage.getItem('erp-tempos') || '[]');
            } catch (erro) {
                return [];
            }
        },
        definirProgresso: function (percentual, mensagem) {
            if (typeof percentual !== 'number' || !isFinite(percentual)) {
                mostrarProcesso(mensagem || 'Processando...');
                return;
            }
            mostrarProcesso(mensagem || 'Processando...', percentual);
        }
    };
})();
