<script>
    (function () {
        var loader = document.getElementById('page-loader');
        if (!loader) return;

        // Navegações rápidas não mostram o loader; o navegador mantém a página atual até a nova estar pronta.
        var ATRASO_MS = 250;
        var showTimer = null;
        var hideTimer = null;

        function show() {
            loader.hidden = false;
            document.body.setAttribute('aria-busy', 'true');
            if (hideTimer) window.clearTimeout(hideTimer);
            // Fallback: se a página não navegar (ex.: download), esconde o loader
            hideTimer = window.setTimeout(hide, 4000);
        }

        function hide() {
            if (showTimer) {
                window.clearTimeout(showTimer);
                showTimer = null;
            }
            if (hideTimer) {
                window.clearTimeout(hideTimer);
                hideTimer = null;
            }
            loader.hidden = true;
            document.body.removeAttribute('aria-busy');
        }

        // A navegação segue nativa (sem preventDefault), então o botão clicado continua indo no POST.
        function agendar(event) {
            if (showTimer) window.clearTimeout(showTimer);
            showTimer = window.setTimeout(function () {
                showTimer = null;
                if (!event.defaultPrevented) show();
            }, ATRASO_MS);
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

            agendar(event);
        });

        document.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.target && form.target !== '_self') return;

            agendar(event);
        });

        window.addEventListener('pageshow', hide);
        window.addEventListener('pagehide', hide);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') hide();
        });
    })();
</script>
