(function () {
    var raiz = document.documentElement;
    var chave = 'visys-tema';

    try {
        var salvo = window.localStorage.getItem(chave);
        if (salvo === 'dark' || salvo === 'light') {
            raiz.setAttribute('data-theme', salvo);
        }
    } catch (e) {}

    function atualizarBotao(botao) {
        var escuro = raiz.getAttribute('data-theme') === 'dark';
        var rotulo = escuro ? 'Ativar tema claro' : 'Ativar tema escuro';
        botao.setAttribute('aria-label', rotulo);
        botao.setAttribute('title', rotulo);
        botao.setAttribute('aria-pressed', escuro ? 'true' : 'false');
    }

    document.addEventListener('click', function (evento) {
        var alvo = evento.target;
        if (!alvo || typeof alvo.closest !== 'function') return;

        var botao = alvo.closest('[data-theme-toggle]');
        if (!botao) return;

        var tema = raiz.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        raiz.setAttribute('data-theme', tema);

        try {
            window.localStorage.setItem(chave, tema);
        } catch (e) {}

        atualizarBotao(botao);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-theme-toggle]').forEach(atualizarBotao);
    });
})();
