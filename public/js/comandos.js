/**
 * Assistente (componente x-comandos).
 *
 * - Envia a mensagem para /comandos/interpretar e mostra a resposta: uma frase, poucos detalhes e botões.
 * - Prévia: "Confirmar" (ou responder "sim") grava; "Cancelar" (ou "não") descarta. Só uma prévia vale por vez.
 * - Pergunta: a próxima mensagem é a resposta; os botões da pergunta valem só até a próxima mensagem.
 * - Texto do servidor entra sempre por textContent (nunca innerHTML).
 * - O histórico (seta para cima) fica só na memória: mensagens podem ter CPF/CNPJ.
 */
(function () {
    'use strict';

    var painel = document.getElementById('comandos-painel');
    if (!painel) return;

    var conversa = painel.querySelector('[data-comandos-conversa]');
    var formulario = painel.querySelector('[data-comandos-form]');
    var campo = formulario.querySelector('input[name="texto"]');
    var botaoEnviar = formulario.querySelector('button[type="submit"]');
    var modeloSucesso = document.getElementById('comandos-modelo-sucesso');
    var abridores = document.querySelectorAll('[data-comandos-abrir]');
    var metaCsrf = document.querySelector('meta[name="csrf-token"]');
    var celular = window.matchMedia('(max-width: 640px)');

    var URL_INTERPRETAR = painel.getAttribute('data-url-interpretar');
    var URL_CONFIRMAR = painel.getAttribute('data-url-confirmar');
    var DICA_PADRAO = campo.getAttribute('data-placeholder-padrao') || campo.placeholder;
    var DICA_PERGUNTA = 'Escreva a resposta...';
    var DICA_PREVIA = 'Responda “sim” para salvar ou “não”';

    var historico = [];
    var posicaoHistorico = 0;
    var ocupado = false;
    var focoAnterior = null;

    /* Prévia aberta (só uma vale por vez) e botões da última pergunta. */
    var previaAberta = null;
    var opcoesDaPergunta = null;

    /* ---------- Abrir e fechar ---------- */

    function aberto() {
        return !painel.hidden;
    }

    function marcarAbridores(valor) {
        abridores.forEach(function (botao) { botao.setAttribute('aria-expanded', valor ? 'true' : 'false'); });
    }

    function abrir() {
        if (aberto()) {
            campo.focus();
            return;
        }
        focoAnterior = document.activeElement;
        painel.hidden = false;
        document.body.classList.add('comandos-aberto');
        marcarAbridores(true);
        ajustarAoTeclado();
        window.requestAnimationFrame(function () { painel.classList.add('is-aberto'); });
        rolarParaFim();
        campo.focus();
    }

    function fechar() {
        if (!aberto()) return;
        painel.classList.remove('is-aberto');
        painel.hidden = true;
        painel.style.removeProperty('height');
        document.body.classList.remove('comandos-aberto');
        marcarAbridores(false);
        if (focoAnterior && typeof focoAnterior.focus === 'function') focoAnterior.focus();
    }

    /* No celular o teclado virtual encolhe a área visível: o painel acompanha para o campo não sumir. */
    function ajustarAoTeclado() {
        if (!window.visualViewport || !celular.matches || !aberto()) return;
        painel.style.height = window.visualViewport.height + 'px';
        rolarParaFim();
    }

    /* ---------- Montagem das mensagens ---------- */

    function elemento(tag, classe, texto) {
        var el = document.createElement(tag);
        if (classe) el.className = classe;
        if (texto !== undefined && texto !== null) el.textContent = String(texto);
        return el;
    }

    function rolarParaFim() {
        conversa.scrollTop = conversa.scrollHeight;
    }

    function adicionar(mensagem) {
        conversa.appendChild(mensagem);
        rolarParaFim();
        return mensagem;
    }

    function mensagemDoUsuario(texto) {
        return adicionar(elemento('div', 'comandos-msg comandos-msg--usuario', texto));
    }

    function indicadorDeEspera() {
        var bolha = elemento('div', 'comandos-msg comandos-msg--sistema comandos-msg--espera');
        bolha.setAttribute('aria-label', 'Pensando');
        for (var i = 0; i < 3; i++) bolha.appendChild(elemento('span', 'comandos-espera__ponto'));
        return adicionar(bolha);
    }

    /* Só links do próprio sistema viram botão. */
    function linkSeguro(link) {
        if (!link || !link.url) return null;
        try {
            var destino = new URL(link.url, window.location.origin);
            if (destino.origin !== window.location.origin) return null;
            var a = elemento('a', 'btn btn--ghost btn--sm comandos-msg__link', link.rotulo || 'Abrir');
            a.href = destino.href;
            return a;
        } catch (erro) {
            return null;
        }
    }

    function itens(lista) {
        var ul = elemento('ul', 'comandos-itens');
        lista.forEach(function (item) {
            var li = elemento('li');
            li.appendChild(elemento('span', 'comandos-itens__descricao', item.descricao));
            li.appendChild(elemento('strong', 'comandos-itens__valor', item.valor));
            ul.appendChild(li);
        });
        return ul;
    }

    /* Botões que respondem pelo usuário: mostram o rótulo e enviam o texto combinado ("#15", "amanhã"). */
    function opcoes(lista) {
        var caixa = elemento('div', 'comandos-opcoes');
        lista.forEach(function (opcao) {
            var botao = elemento('button', 'comandos-chip', opcao.rotulo);
            botao.type = 'button';
            botao.setAttribute('data-comandos-enviar', opcao.enviar);
            botao.setAttribute('data-comandos-rotulo', opcao.rotulo);
            caixa.appendChild(botao);
        });
        return caixa;
    }

    function respostaDoSistema(resposta) {
        var tipo = resposta.tipo || 'info';
        var bolha = elemento('div', 'comandos-msg comandos-msg--sistema comandos-msg--' + tipo);

        if (tipo === 'sucesso' && modeloSucesso) {
            bolha.appendChild(modeloSucesso.content.cloneNode(true));
        }

        bolha.appendChild(elemento('p', 'comandos-msg__texto', resposta.texto));
        if (resposta.itens) bolha.appendChild(itens(resposta.itens));
        (resposta.detalhes || []).forEach(function (detalhe) {
            bolha.appendChild(elemento('p', 'comandos-msg__detalhe', detalhe));
        });
        (resposta.avisos || []).forEach(function (aviso) {
            bolha.appendChild(elemento('p', 'comandos-msg__aviso', aviso));
        });

        if (tipo === 'confirmacao' && resposta.token) {
            bolha.appendChild(acoesDaPrevia(bolha, resposta.token));
        }

        var link = linkSeguro(resposta.link);
        if (link) bolha.appendChild(link);

        if (resposta.opcoes) {
            var caixa = opcoes(resposta.opcoes);
            bolha.appendChild(caixa);
            if (tipo === 'pergunta') opcoesDaPergunta = caixa;
        }

        return adicionar(bolha);
    }

    /* ---------- Prévia ---------- */

    function acoesDaPrevia(bolha, token) {
        var acoes = elemento('div', 'comandos-msg__acoes');
        var confirmar = elemento('button', 'btn btn--primary btn--sm', 'Confirmar');
        var cancelar = elemento('button', 'btn btn--ghost btn--sm', 'Cancelar');
        confirmar.type = 'button';
        cancelar.type = 'button';

        confirmar.addEventListener('click', function () {
            if (ocupado) return;
            confirmar.disabled = true;
            cancelar.disabled = true;
            confirmar.textContent = 'Salvando...';
            executar(URL_CONFIRMAR, { token: token });
        });

        /* Cancelar não precisa de resposta na conversa: a própria prévia mostra que nada foi salvo. */
        cancelar.addEventListener('click', function () {
            if (ocupado) return;
            encerrarPrevia('cancelada');
            postar(URL_CONFIRMAR, { token: token, acao: 'cancelar' });
            campo.focus();
        });

        acoes.appendChild(confirmar);
        acoes.appendChild(cancelar);
        bolha._acoes = acoes;
        return acoes;
    }

    /* "confirmada", "cancelada" ou "encerrada" (expirou ou foi trocada por outra). */
    function encerrarPrevia(como) {
        if (!previaAberta) return;
        var bolha = previaAberta;
        previaAberta = null;
        if (bolha._acoes) bolha._acoes.remove();
        bolha.classList.add(como === 'confirmada' ? 'is-confirmado' : 'is-encerrado');
        if (como === 'cancelada') bolha.appendChild(elemento('p', 'comandos-msg__nota', 'Cancelado. Nada foi salvo.'));
        atualizarDica();
    }

    function atualizarDica() {
        if (previaAberta) campo.placeholder = DICA_PREVIA;
        else if (opcoesDaPergunta) campo.placeholder = DICA_PERGUNTA;
        else campo.placeholder = DICA_PADRAO;
    }

    /* Os botões de uma pergunta valem só até a próxima mensagem; o escolhido fica marcado. */
    function encerrarPergunta(escolhido) {
        if (!opcoesDaPergunta) return;
        opcoesDaPergunta.classList.add('is-usada');
        opcoesDaPergunta.querySelectorAll('button').forEach(function (botao) {
            botao.disabled = true;
            if (botao === escolhido) botao.classList.add('is-escolhido');
        });
        opcoesDaPergunta = null;
    }

    function receber(resposta) {
        // Uma prévia nova ou uma pergunta substituem a prévia aberta no servidor.
        if (resposta.previa) encerrarPrevia(resposta.previa);
        else if (resposta.tipo === 'confirmacao' || resposta.tipo === 'pergunta') encerrarPrevia('encerrada');

        var bolha = respostaDoSistema(resposta);
        if (resposta.tipo === 'confirmacao' && resposta.token) previaAberta = bolha;
        atualizarDica();
    }

    /* ---------- Comunicação com o servidor ---------- */

    function erroLocal(texto) {
        return { tipo: 'erro', texto: texto };
    }

    function primeiraMensagem(corpo) {
        if (!corpo || !corpo.errors) return null;
        var chaves = Object.keys(corpo.errors);
        return chaves.length ? corpo.errors[chaves[0]][0] : null;
    }

    function postar(url, corpo) {
        return window.fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': metaCsrf ? metaCsrf.getAttribute('content') : ''
            },
            body: JSON.stringify(corpo)
        }).then(function (r) {
            if (r.ok) return r.json();
            if (r.status === 422) {
                return r.json().then(function (corpo) {
                    return erroLocal(primeiraMensagem(corpo) || 'Confira o que foi digitado.');
                }, function () { return erroLocal('Confira o que foi digitado.'); });
            }
            if (r.status === 401 || r.status === 419) return erroLocal('Sua sessão expirou. Recarregue a página e entre de novo.');
            if (r.status === 403) return erroLocal('Seu perfil não tem acesso ao assistente.');
            if (r.status === 429) return erroLocal('Muitas mensagens seguidas. Espere um minuto e tente de novo.');
            return erroLocal('Não consegui responder agora. Tente de novo em instantes.');
        }, function () {
            return erroLocal('Sem conexão com o sistema. Confira a rede e tente de novo.');
        });
    }

    function ocupar(valor) {
        ocupado = valor;
        botaoEnviar.disabled = valor;
        painel.classList.toggle('is-ocupado', valor);
    }

    /* Envia, mostra os três pontinhos enquanto espera e depois a resposta. */
    function executar(url, corpo) {
        ocupar(true);
        var espera = indicadorDeEspera();
        return postar(url, corpo).then(function (resposta) {
            espera.remove();
            receber(resposta);
            ocupar(false);
            if (window.ErpIlustracoes) window.ErpIlustracoes.iniciar(conversa);
            if (!celular.matches) campo.focus();
        });
    }

    /* rotulo: o que aparece na conversa quando um botão envia outro texto ("Maria Souza" envia "#15"). */
    function enviar(texto, rotulo, botao) {
        texto = String(texto || '').trim();
        if (texto === '' || ocupado) return;
        if (!rotulo) {
            if (historico[historico.length - 1] !== texto) historico.push(texto);
            posicaoHistorico = historico.length;
            campo.value = '';
        }
        encerrarPergunta(botao);
        mensagemDoUsuario(rotulo || texto);
        executar(URL_INTERPRETAR, { texto: texto });
    }

    /* ---------- Eventos ---------- */

    formulario.addEventListener('submit', function (e) {
        e.preventDefault();
        if (campo.value.trim() === '') {
            campo.focus();
            return;
        }
        enviar(campo.value);
    });

    painel.addEventListener('click', function (e) {
        // Exemplo vira modelo no campo (para trocar nomes e valores antes de enviar).
        var exemplo = e.target.closest('[data-comandos-exemplo]');
        if (exemplo) {
            campo.value = exemplo.getAttribute('data-comandos-exemplo');
            campo.focus();
            campo.setSelectionRange(campo.value.length, campo.value.length);
            return;
        }
        var botao = e.target.closest('[data-comandos-enviar]');
        if (botao && !botao.disabled) {
            enviar(botao.getAttribute('data-comandos-enviar'), botao.getAttribute('data-comandos-rotulo'), botao);
            return;
        }
        if (e.target.closest('[data-comandos-fechar]')) fechar();
    });

    campo.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowUp' && e.key !== 'ArrowDown') return;
        if (!historico.length) return;
        e.preventDefault();
        posicaoHistorico += e.key === 'ArrowUp' ? -1 : 1;
        posicaoHistorico = Math.max(0, Math.min(historico.length, posicaoHistorico));
        campo.value = historico[posicaoHistorico] || '';
    });

    abridores.forEach(function (botao) {
        botao.addEventListener('click', function () {
            if (aberto()) fechar();
            else abrir();
        });
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
            e.preventDefault();
            abrir();
            return;
        }
        if (e.key === 'Escape' && aberto() && painel.contains(document.activeElement)) {
            e.preventDefault();
            fechar();
        }
    });

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', ajustarAoTeclado);
    }
})();
