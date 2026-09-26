<script>
    (function () {
        var loader = document.getElementById('page-loader');
        if (!loader) return;

        function show() {
            loader.hidden = false;
            document.body.setAttribute('aria-busy', 'true');
        }

        function hide() {
            loader.hidden = true;
            document.body.removeAttribute('aria-busy');
        }

        function samePageHash(url) {
            return url.pathname === window.location.pathname
                && url.search === window.location.search
                && url.hash !== '';
        }

        function navegar(href) {
            show();
            window.setTimeout(function () {
                window.location.assign(href);
            }, 60);
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
            if (samePageHash(url)) return;

            event.preventDefault();
            navegar(link.href);
        });

        function onSubmit(event) {
            if (event.defaultPrevented) return;
            var form = event.target;
            if (!(form instanceof HTMLFormElement)) return;

            event.preventDefault();
            show();
            window.setTimeout(function () {
                HTMLFormElement.prototype.submit.call(form);
            }, 60);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                document.addEventListener('submit', onSubmit);
            });
        } else {
            document.addEventListener('submit', onSubmit);
        }

        window.addEventListener('pageshow', hide);
    })();
</script>
