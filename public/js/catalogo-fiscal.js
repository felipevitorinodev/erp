(function () {
    function posicionar(lista, input) {
        var rect = input.getBoundingClientRect();
        var abaixo = window.innerHeight - rect.bottom;
        lista.style.position = 'fixed';
        lista.style.left = Math.max(8, rect.left) + 'px';
        lista.style.width = Math.max(160, rect.width) + 'px';
        lista.style.zIndex = '80';
        lista.hidden = false;
        if (abaixo < 160 && rect.top > abaixo) {
            lista.style.top = 'auto';
            lista.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
            lista.style.maxHeight = Math.max(120, rect.top - 12) + 'px';
        } else {
            lista.style.bottom = 'auto';
            lista.style.top = (rect.bottom + 4) + 'px';
            lista.style.maxHeight = Math.max(120, Math.min(240, abaixo - 12)) + 'px';
        }
    }

    window.ligarBuscaFiscal = function (config) {
        var input = config.input;
        var hidden = config.hidden || null;
        var lista = config.lista;
        var url = config.url;
        var tamanho = config.tamanho || 0;
        var timer = null;
        var pedido = 0;

        function fechar() {
            lista.hidden = true;
            lista.innerHTML = '';
        }

        function escolher(item) {
            var rotulo = item.rotulo || (item.codigo + ' - ' + item.descricao);
            if (hidden) hidden.value = item.codigo;
            input.value = rotulo;
            fechar();
            if (config.aoEscolher) config.aoEscolher(item);
        }

        function buscar(q) {
            var id = ++pedido;
            var params = new URLSearchParams();
            if (q) params.set('q', q);
            var extra = config.params ? (config.params() || {}) : {};
            Object.keys(extra).forEach(function (chave) {
                if (extra[chave]) params.set(chave, extra[chave]);
            });
            fetch(url + (params.toString() ? '?' + params.toString() : ''), {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin'
            }).then(function (resposta) {
                if (!resposta.ok) throw new Error('falha');
                return resposta.json();
            }).then(function (data) {
                if (id !== pedido || document.activeElement !== input) return;
                var itens = Array.isArray(data) ? data : [];
                lista.innerHTML = '';
                if (!itens.length) {
                    lista.innerHTML = '<div class="autocomplete-empty">Nenhum resultado</div>';
                    posicionar(lista, input);
                    return;
                }
                itens.forEach(function (item) {
                    var node = document.createElement('div');
                    node.className = 'autocomplete-item';
                    node.textContent = item.rotulo || (item.codigo + ' - ' + item.descricao);
                    node.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        escolher(item);
                    });
                    lista.appendChild(node);
                });
                posicionar(lista, input);
            }).catch(function () {
                if (id === pedido) fechar();
            });
        }

        input.addEventListener('input', function () {
            clearTimeout(timer);
            var q = input.value.trim();
            if (hidden && tamanho && new RegExp('^\\d{' + tamanho + '}$').test(q)) {
                hidden.value = q;
            }
            if (config.aoDigitar) config.aoDigitar();
            if (q.length < 1) {
                fechar();
                return;
            }
            timer = setTimeout(function () { buscar(q); }, 180);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !lista.hidden) {
                e.preventDefault();
                e.stopPropagation();
                fechar();
            }
        });

        input.addEventListener('blur', function () {
            setTimeout(fechar, 160);
        });

        window.addEventListener('resize', fechar);
        document.addEventListener('scroll', function (e) {
            if (!lista.hidden && !lista.contains(e.target)) fechar();
        }, true);
    };
})();
