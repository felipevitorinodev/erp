<script>
    (function () {
        var raiz = document.documentElement;
        var fallback = null;

        function parar() {
            raiz.classList.remove('is-loading');
            window.clearTimeout(fallback);
        }

        function iniciar(event) {
            window.setTimeout(function () {
                if (event.defaultPrevented) return;
                raiz.classList.add('is-loading');
                // Downloads não trocam de página: a barra some sozinha.
                fallback = window.setTimeout(parar, 15000);
            }, 0);
        }

        document.addEventListener('click', function (event) {
            if (event.defaultPrevented || event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            var link = event.target.closest ? event.target.closest('a[href]') : null;
            if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-loader')) return;
            if (link.target && link.target !== '_self') return;
            if (link.origin !== window.location.origin) return;
            if (link.pathname === window.location.pathname && link.search === window.location.search && link.hash) return;
            if (/\/(csv|pdf|xml|danfe)$|\.(csv|pdf|xml|xlsx?)$/i.test(link.pathname)) return;

            iniciar(event);
        });

        document.addEventListener('submit', function (event) {
            var form = event.target;
            if (form.target && form.target !== '_self') return;
            if (form.hasAttribute('data-no-loader')) return;

            iniciar(event);
        });

        window.addEventListener('pageshow', parar);
    })();
</script>
