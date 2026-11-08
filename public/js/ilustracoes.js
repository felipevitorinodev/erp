/**
 * Ilustrações interativas, contagem regressiva e botões das telas de erro.
 *
 * - [data-ilustracao-interativa]: as camadas (.il-camada) acompanham o ponteiro dentro da área
 *   ([data-ilustracao-area] ou .estado-vazio) e a ilustração reage ao clique/toque.
 * - [data-contagem]: anel regressivo; ao zerar libera o botão indicado ou recarrega a página.
 * - [data-erro-voltar] / [data-erro-recarregar]: ações das telas de erro.
 *
 * Tudo por delegação no document: funciona também em conteúdo carregado depois (relatórios, modais).
 */
(function () {
    'use strict';

    var reduzMovimento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var ilustracaoAtual = null;
    var ultimoPonteiro = null;
    var quadroPendente = 0;

    function limitar(valor) {
        return Math.max(-1, Math.min(1, valor));
    }

    function soltar(ilustracao) {
        if (!ilustracao) return;
        ilustracao.style.setProperty('--px', '0');
        ilustracao.style.setProperty('--py', '0');
    }

    function acompanharPonteiro() {
        quadroPendente = 0;
        if (!ilustracaoAtual || !ultimoPonteiro) return;
        var caixa = ilustracaoAtual.getBoundingClientRect();
        if (!caixa.width) return;
        var x = (ultimoPonteiro.clientX - caixa.left - caixa.width / 2) / caixa.width;
        var y = (ultimoPonteiro.clientY - caixa.top - caixa.height / 2) / caixa.height;
        ilustracaoAtual.style.setProperty('--px', limitar(x).toFixed(3));
        ilustracaoAtual.style.setProperty('--py', limitar(y).toFixed(3));
    }

    function aoMoverPonteiro(e) {
        if (e.pointerType === 'touch' || !e.target.closest) return;
        var area = e.target.closest('[data-ilustracao-area], .estado-vazio');
        var ilustracao = area ? area.querySelector('[data-ilustracao-interativa]') : null;
        if (ilustracao !== ilustracaoAtual) {
            soltar(ilustracaoAtual);
            ilustracaoAtual = ilustracao;
        }
        if (!ilustracao) return;
        ultimoPonteiro = e;
        if (!quadroPendente) quadroPendente = window.requestAnimationFrame(acompanharPonteiro);
    }

    function reagir(ilustracao) {
        ilustracao.classList.remove('is-reagindo');
        void ilustracao.getBoundingClientRect(); // reinicia a animação em cliques seguidos
        ilustracao.classList.add('is-reagindo');
    }

    function voltar(botao) {
        if (window.history.length > 1 && document.referrer) {
            window.history.back();
            return;
        }
        window.location.href = botao.getAttribute('data-inicio') || '/';
    }

    function aoClicar(e) {
        if (!e.target.closest) return;

        var ilustracao = e.target.closest('[data-ilustracao-interativa]');
        if (ilustracao && !reduzMovimento) reagir(ilustracao);

        var botaoVoltar = e.target.closest('[data-erro-voltar]');
        if (botaoVoltar && !botaoVoltar.disabled) {
            voltar(botaoVoltar);
            return;
        }
        if (e.target.closest('[data-erro-recarregar]')) window.location.reload();
    }

    function iniciarContagem(el) {
        if (el.dataset.contagemLigada) return;
        el.dataset.contagemLigada = '1';

        var total = parseInt(el.getAttribute('data-contagem'), 10) || 0;
        var resta = total;
        var numero = el.querySelector('[data-contagem-numero]');
        var anel = el.querySelector('[data-contagem-anel]');
        var texto = el.querySelector('[data-contagem-texto]');
        var seletorBotao = el.getAttribute('data-contagem-botao');
        var botao = seletorBotao ? document.querySelector(seletorBotao) : null;
        var recarregar = el.getAttribute('data-contagem-acao') === 'recarregar';

        function desenhar() {
            if (numero) numero.textContent = String(Math.max(resta, 0));
            if (anel && total) anel.style.strokeDashoffset = String(100 - (Math.max(resta, 0) / total) * 100);
        }

        function concluir() {
            el.classList.add('is-concluida');
            if (recarregar) {
                if (texto) texto.textContent = 'Tentando abrir de novo...';
                window.location.reload();
                return;
            }
            if (botao) {
                botao.disabled = false;
                botao.focus();
            }
            if (texto) texto.textContent = 'Pronto, já pode tentar de novo.';
        }

        desenhar();
        var relogio = window.setInterval(function () {
            resta -= 1;
            desenhar();
            if (resta <= 0) {
                window.clearInterval(relogio);
                concluir();
            }
        }, 1000);
    }

    function iniciar(raiz) {
        (raiz || document).querySelectorAll('[data-contagem]').forEach(iniciarContagem);
    }

    document.addEventListener('click', aoClicar);
    document.addEventListener('animationend', function (e) {
        if (e.animationName !== 'il-sacode' || !e.target.closest) return;
        var ilustracao = e.target.closest('.ilustracao');
        if (ilustracao) ilustracao.classList.remove('is-reagindo');
    });
    if (!reduzMovimento && window.PointerEvent) {
        document.addEventListener('pointermove', aoMoverPonteiro, { passive: true });
        document.documentElement.addEventListener('pointerleave', function () {
            soltar(ilustracaoAtual);
            ilustracaoAtual = null;
        });
    }

    window.ErpIlustracoes = { iniciar: iniciar };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { iniciar(); });
    } else {
        iniciar();
    }
})();
