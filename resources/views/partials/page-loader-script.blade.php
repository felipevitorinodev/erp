<script>
    (function () {
        var loader = document.getElementById('page-loader');
        var progresso = document.getElementById('page-progress');
        var barra = document.getElementById('page-progress-barra');
        var texto = document.getElementById('page-loader-texto');
        if (!loader) return;

        // Navegações rápidas mostram só a barra; o conteúdo esmaece e o status aparece só se demorar.
        var ESMAECER_MS = 600;
        var STATUS_MS = 1200;
        var timers = [];
        var trickle = null;
        var valor = 0;

        function limparTimers() {
            timers.forEach(window.clearTimeout);
            timers = [];
            if (trickle) {
                window.clearInterval(trickle);
                trickle = null;
            }
        }

        function definirBarra(v) {
            valor = v;
            if (barra) barra.style.transform = 'scaleX(' + (v / 100) + ')';
        }

        function iniciarBarra() {
            if (!progresso || !barra) return;
            progresso.classList.remove('is-concluindo');
            progresso.classList.add('is-ativo');
            definirBarra(8);
            // Avança cada vez mais devagar, sem nunca chegar a 100% sozinha.
            trickle = window.setInterval(function () {
                definirBarra(valor + (92 - valor) * 0.08);
            }, 200);
        }

        function mostrar(mensagem) {
            if (texto) texto.textContent = mensagem;
            loader.hidden = false;
            document.body.setAttribute('aria-busy', 'true');

            timers.push(window.setTimeout(function () {
                if (texto) texto.textContent = 'Quase lá…';
            }, 3000));
            timers.push(window.setTimeout(function () {
                if (texto) texto.textContent = 'O servidor está demorando mais que o normal…';
            }, 8000));
            // Fallback: se nada navegar (ex.: resposta de download), some sozinho.
            timers.push(window.setTimeout(esconder, 20000));
        }

        function esconder() {
            limparTimers();
            loader.hidden = true;
            document.body.removeAttribute('aria-busy');
            document.body.classList.remove('is-navegando');
            if (progresso && progresso.classList.contains('is-ativo')) {
                definirBarra(100);
                progresso.classList.add('is-concluindo');
                window.setTimeout(function () {
                    progresso.classList.remove('is-ativo', 'is-concluindo');
                    definirBarra(0);
                }, 300);
            }
        }

        // A navegação segue nativa (sem preventDefault), então o botão clicado continua indo no POST.
        function agendar(event, mensagem) {
            limparTimers();
            timers.push(window.setTimeout(function () {
                if (event.defaultPrevented) return;
                iniciarBarra();
                timers.push(window.setTimeout(function () {
                    document.body.classList.add('is-navegando');
                }, ESMAECER_MS));
                timers.push(window.setTimeout(function () {
                    mostrar(mensagem);
                }, STATUS_MS));
            }, 0));
        }

        function mensagemDoLink(link) {
            var rotulo = (link.getAttribute('aria-label') || link.getAttribute('title') || link.textContent || '')
                .replace(/\s+/g, ' ')
                .replace(/^[+←→›\s]+/, '')
                .trim();
            if (rotulo.length < 4 || rotulo.length > 40 || /^(ver|editar|voltar|limpar|anterior|próxim[ao])\b/i.test(rotulo)) {
                return 'Carregando';
            }
            return 'Abrindo ' + rotulo;
        }

        function mensagemDoForm(form) {
            var metodo = (form.getAttribute('method') || 'GET').toUpperCase();
            var spoof = form.querySelector('input[name="_method"]');
            if (spoof && spoof.value.toUpperCase() === 'DELETE') return 'Excluindo';
            if (metodo === 'GET') return 'Buscando';
            return 'Salvando';
        }

        function samePageHash(url) {
            return url.pathname === window.location.pathname
                && url.search === window.location.search
                && url.hash !== '';
        }

        function isDownloadLink(link, url) {
            if (link.hasAttribute('download') || link.hasAttribute('data-no-loader')) return true;
            var path = (url.pathname || '').toLowerCase();
            return path.endsWith('/csv')
                || path.endsWith('.csv')
                || path.endsWith('/pdf')
                || path.endsWith('.pdf')
                || path.endsWith('/xml')
                || path.endsWith('.xlsx')
                || path.endsWith('.xls');
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download')) return;
            if (link.target && link.target !== '_self') return;

            var url;
            try {
                url = new URL(link.href, window.location.href);
            } catch (e) {
                return;
            }

            if (url.origin !== window.location.origin) return;
            if (url.protocol !== 'http:' && url.protocol !== 'https:') return;
            if (samePageHash(url)) return;
            if (isDownloadLink(link, url)) return;

            agendar(event, mensagemDoLink(link));
        });

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.target && form.target !== '_self') return;
            if (form.hasAttribute('data-no-loader')) return;

            agendar(event, mensagemDoForm(form));
        });

        window.addEventListener('pageshow', esconder);
        window.addEventListener('pagehide', esconder);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible' && !loader.hidden) esconder();
        });
    })();
</script>
