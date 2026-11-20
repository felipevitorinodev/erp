// Scripts globais do ERP
(function ($) {
    if (!$) return;

    // Formata campos moeda (classe .input-moeda) no blur: 1.234,56
    $(document).on('blur', '.input-moeda', function () {
        var val = $(this).val();
        if (!val) return;

        var str = String(val).trim();
        if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
            str = str.replace(/\./g, '').replace(',', '.');
        } else if (str.indexOf(',') !== -1) {
            str = str.replace(',', '.');
        }

        var num = parseFloat(str);
        if (isNaN(num)) return;

        $(this).val(
            num.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.')
        );
    });
    
    // Comportamento padronizado para inputs numéricos (usa data-decimals no elemento)
    function attachNumericBehavior() {
        // input: permite dígitos e separadores; atualiza display sem forçar interpretação como centavos
        $(document).on('input', '.input-numeric', function (e) {
            var el = this;
            // se o input foi atualizado pelo teclado custom, ignorar para não reformatar
            if (el.dataset && el.dataset.fromKeypad === '1') {
                return;
            }
            var raw = String(el.value || '');
            var decimals = parseInt(el.getAttribute('data-decimals') || '2', 10);
            // permitir apenas dígitos, vírgula e ponto
            var cleaned = raw.replace(/[^0-9\.,]/g, '');
            // localizar o último separador (vírgula ou ponto) e tratar tudo antes como inteiro (removendo separadores)
            var lastComma = cleaned.lastIndexOf(',');
            var lastDot = cleaned.lastIndexOf('.');
            var lastSep = Math.max(lastComma, lastDot);
            if (lastSep !== -1) {
                var intPartRaw = cleaned.slice(0, lastSep);
                var decPartRaw = cleaned.slice(lastSep + 1);
                var intPart = intPartRaw.replace(/[.,]/g, '') || '0';
                var decPart = decPartRaw.replace(/[.,]/g, '').slice(0, decimals);
                cleaned = intPart + (decPart ? ',' + decPart : '');
            } else {
                // sem separador: se houver ponto usado como separador único, normaliza para vírgula
                if (cleaned.indexOf('.') !== -1 && cleaned.indexOf(',') === -1) {
                    cleaned = cleaned.replace('.', ',');
                }
            }
            el.value = cleaned;
            // move caret to end for consistent UX (a seleção ao focar cuida da substituição)
            if (typeof el.selectionStart === 'number') {
                el.selectionStart = el.selectionEnd = el.value.length;
            }
        });

        // selecionar todo o conteúdo ao focar (facilita substituição ao digitar)
        $(document).on('focus', '.input-numeric', function () {
            var el = this;
            setTimeout(function () {
                try { el.selectionStart = 0; el.selectionEnd = el.value.length; } catch (e) {}
            }, 0);
        });

        // colar -> limpar e limitar
        $(document).on('paste', '.input-numeric', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text') || '';
            text = String(text).replace(/[^0-9\.,]/g, '');
            var decimals = parseInt(this.getAttribute('data-decimals') || '2', 10);
            // usar lógica do último separador
            var lastComma = text.lastIndexOf(',');
            var lastDot = text.lastIndexOf('.');
            var lastSep = Math.max(lastComma, lastDot);
            if (lastSep !== -1) {
                var intPartRaw = text.slice(0, lastSep);
                var decPartRaw = text.slice(lastSep + 1);
                var intPart = intPartRaw.replace(/[.,]/g, '') || '0';
                var decPart = decPartRaw.replace(/[.,]/g, '').slice(0, decimals);
                this.value = intPart + (decPart ? ',' + decPart : '');
            } else {
                this.value = text.replace(/[^\d]/g, '');
            }
            $(this).trigger('input');
        });

        // blur: formata com casas decimais e separadores de milhar
        $(document).on('blur', '.input-numeric', function () {
            var el = this;
            var val = String(el.value || '').trim();
            var decimals = parseInt(el.getAttribute('data-decimals') || '2', 10);
            if (val === '') {
                if (decimals) {
                    el.value = '0,' + '0'.repeat(decimals);
                } else {
                    el.value = '0';
                }
                return;
            }
            // normalizar: se tem '.' e ',' assume que '.' é milhares e ',' decimal
            var str = val;
            if (str.indexOf(',') !== -1 && str.indexOf('.') !== -1) {
                str = str.replace(/\./g, '').replace(',', '.');
            } else if (str.indexOf(',') !== -1) {
                str = str.replace(',', '.');
            } else {
                // só ponto ou nenhum separador: manter ponto como decimal
                str = str;
            }
            var num = parseFloat(str.replace(/\s/g, '')) || 0;
            if (decimals) {
                el.value = num.toFixed(decimals).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            } else {
                el.value = String(Math.round(num));
            }
        });
    }

    // Global parseBR robusta: lida com separadores de milhar e decimal variados
    window.parseBR = function (val) {
        if (val === null || typeof val === 'undefined' || val === '') return 0;
        var s = String(val).trim();
        s = s.replace(/\s/g, '');

        var lastComma = s.lastIndexOf(',');
        var lastDot = s.lastIndexOf('.');
        if (lastComma === -1 && lastDot === -1) {
            // só dígitos
            return parseFloat(s) || 0;
        }

        var lastSep = Math.max(lastComma, lastDot);
        var intPart = s.slice(0, lastSep).replace(/[.,]/g, '');
        var decPart = s.slice(lastSep + 1).replace(/[.,]/g, '');
        var normalized = intPart + (decPart ? '.' + decPart : '');
        return parseFloat(normalized) || 0;
    };

    // aplicar comportamento padrão
    attachNumericBehavior();
    // Compatibilidade com classes antigas: mapeia para comportamento padrão sem alterar views
    // moedas / preços -> 2 casas
    $(document).on('input', '.input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo', function () {
        // delega para comportamento input-numeric simulando data-decimals=2
        if (!this.classList.contains('input-numeric')) {
            this.classList.add('input-numeric');
            this.setAttribute('data-decimals', '2');
        }
    });
    // quantidades -> 2 casas (respeita data-decimals se já definido)
    $(document).on('input', '.input-qtd', function () {
        if (!this.classList.contains('input-numeric')) {
            this.classList.add('input-numeric');
        }
        if (!this.getAttribute('data-decimals')) {
            this.setAttribute('data-decimals', '2');
        }
    });
    // também selecionar todo o conteúdo ao focar em campos legados e garantir data-decimals
    $(document).on('focus', '.input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo, .input-qtd', function () {
            if (!this.classList.contains('input-numeric')) {
            this.classList.add('input-numeric');
        }
        if (!this.getAttribute('data-decimals')) {
            if (this.classList.contains('input-qtd')) {
                this.setAttribute('data-decimals', '2');
            } else {
                this.setAttribute('data-decimals', '2');
            }
        }
        var el = this;
        setTimeout(function () {
            try { el.selectionStart = 0; el.selectionEnd = el.value.length; } catch (e) {}
        }, 0);
    });
})(window.jQuery);

// Modal de confirmação global (substitui window.confirm)
(function initModalConfirm() {
    function setup() {
        try {
            var modal = document.getElementById('modal-confirm');
            if (!modal) {
                window.modalConfirmAttached = false;
                return;
            }
            window.modalConfirmAttached = true;

            var titleEl = document.getElementById('modal-confirm-title');
            var messageEl = document.getElementById('modal-confirm-message');
            var okBtn = document.getElementById('modal-confirm-ok');
            var pendingForm = null;
            var pendingSubmitter = null;

            function fechar() {
                modal.hidden = true;
                pendingForm = null;
                pendingSubmitter = null;
                okBtn.className = 'btn btn--primary';
                okBtn.textContent = 'Confirmar';
                titleEl.textContent = 'Confirmar';
            }

            function abrir(opts) {
                titleEl.textContent = opts.title || 'Confirmar';
                messageEl.textContent = opts.message || '';
                okBtn.textContent = opts.okLabel || 'Confirmar';

                var variant = opts.variant || 'primary';
                okBtn.className = 'btn btn--' + variant;

                modal.hidden = false;
            }

            modal.querySelectorAll('[data-confirm-close]').forEach(function (el) {
                el.addEventListener('click', fechar);
            });

            modal.addEventListener('click', function (e) {
                if (e.target === modal) fechar();
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && !modal.hidden) fechar();
            });

            okBtn.addEventListener('click', function () {
                var form = pendingForm;
                var submitter = pendingSubmitter;
                if (!form) {
                    fechar();
                    return;
                }

                form.dataset.confirmApproved = '1';
                fechar();

                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter || undefined);
                } else {
                    if (window.ErpCarregamento) window.ErpCarregamento.anotarEnvio(form, submitter);
                    form.submit();
                }
            });

            // capture last clicked submitter as fallback for browsers that don't support SubmitEvent.submitter
            (function attachClickCapture() {
                document.addEventListener('click', function (ev) {
                    try {
                        var t = ev.target;
                        var btn = t.closest ? t.closest('button, input[type="submit"]') : null;
                        if (btn) {
                            window.__lastClickSubmitter = btn;
                        } else {
                            window.__lastClickSubmitter = null;
                        }
                    } catch (err) {
                        window.__lastClickSubmitter = null;
                    }
                }, true);
            })();

            // mais robusto: interceptar clicks em elementos com data-confirm
            document.addEventListener('click', function (e) {
                try {
                    var trg = e.target.closest ? e.target.closest('[data-confirm]') : null;
                    if (!trg) return;
                    // elemento com data-confirm encontrado; evitar comportamento padrão
                    e.preventDefault();
                    // determinar o formulário alvo: elemento pode ser botão dentro de form ou o próprio form
                    var formId = trg.getAttribute('form');
                    var form = formId ? document.getElementById(formId) : null;
                    if (!form && trg.closest) form = trg.closest('form');
                    if (!form && trg.tagName === 'FORM') form = trg;
                    pendingForm = form;
                    pendingSubmitter = trg.tagName === 'BUTTON' || trg.tagName === 'INPUT' ? trg : null;
                    abrir({
                        message: trg.getAttribute('data-confirm') || 'Confirmar esta ação?',
                        title: trg.getAttribute('data-confirm-title') || 'Confirmar',
                        okLabel: trg.getAttribute('data-confirm-ok') || 'Confirmar',
                        variant: trg.getAttribute('data-confirm-variant') || 'primary',
                    });
                } catch (err) {
                    // se falhar, não bloquear o clique
                    return;
                }
            }, true);

            document.addEventListener('submit', function (e) {
                var form = e.target;
                if (!(form instanceof HTMLFormElement)) return;
                if (form.dataset.confirmApproved === '1') {
                    delete form.dataset.confirmApproved;
                    return;
                }

                var submitter = e.submitter || window.__lastClickSubmitter;
                var trigger = null;

                if (submitter && submitter.hasAttribute('data-confirm')) {
                    trigger = submitter;
                } else if (form.hasAttribute('data-confirm')) {
                    trigger = form;
                }

                if (!trigger) return;

                e.preventDefault();
                pendingForm = form;
                pendingSubmitter = submitter instanceof HTMLButtonElement ? submitter : null;

                abrir({
                    message: trigger.getAttribute('data-confirm') || 'Confirmar esta ação?',
                    title: trigger.getAttribute('data-confirm-title') || 'Confirmar',
                    okLabel: trigger.getAttribute('data-confirm-ok') || 'Confirmar',
                    variant: trigger.getAttribute('data-confirm-variant') || 'primary',
                });
            });
        } catch (err) {
            console.error('modal confirm init error', err);
            window.modalConfirmAttached = false;
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();

// Sidebar mobile
(function () {
    var toggle = document.getElementById('btn-sidebar-toggle');
    var overlay = document.getElementById('sidebar-overlay');
    var sidebar = document.querySelector('.sidebar');
    if (!toggle || !overlay) return;

    function abrir() {
        document.documentElement.classList.add('sidebar-open');
        document.body.classList.add('sidebar-open');
        overlay.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Fechar menu');
        if (sidebar) sidebar.setAttribute('aria-hidden', 'false');
    }

    function fechar() {
        document.documentElement.classList.remove('sidebar-open');
        document.body.classList.remove('sidebar-open');
        overlay.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu');
        if (sidebar) sidebar.setAttribute('aria-hidden', 'true');
    }

    function alternar(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        if (document.body.classList.contains('sidebar-open')) {
            fechar();
        } else {
            abrir();
        }
    }

    toggle.addEventListener('click', alternar);
    overlay.addEventListener('click', fechar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            fechar();
        }
    });

    document.querySelectorAll('.sidebar a').forEach(function (link) {
        link.addEventListener('click', fechar);
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) fechar();
    });

    if (window.innerWidth <= 900 && sidebar) {
        sidebar.setAttribute('aria-hidden', 'true');
    }
})();

// Labels para tabelas em modo card (mobile)
(function () {
    function aplicarLabels(table) {
        var headers = Array.prototype.map.call(
            table.querySelectorAll('thead th'),
            function (th) {
                return (th.textContent || '').trim();
            }
        );

        table.querySelectorAll('tbody tr').forEach(function (tr) {
            var cells = tr.querySelectorAll('td');
            if (cells.length === 1 && cells[0].hasAttribute('colspan')) {
                return;
            }

            cells.forEach(function (td, index) {
                if (td.hasAttribute('data-label')) return;
                var label = headers[index] || '';
                if (label) {
                    td.setAttribute('data-label', label);
                }
            });
        });
    }

    function init() {
        document.querySelectorAll('table.table--cards').forEach(aplicarLabels);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.applyTableCardLabels = aplicarLabels;
})();

// Listagens: clicar na linha abre o registro (o mesmo que o botão Ver; sem Ver, o Editar).
// Cliques em links, botões e campos da própria linha seguem normais; texto selecionado não abre.
(function () {
    document.addEventListener('click', function (e) {
        if (e.defaultPrevented || e.button !== 0) return;
        var tr = e.target.closest ? e.target.closest('table.table--cards > tbody > tr') : null;
        if (!tr) return;
        if (e.target.closest('a, button, input, select, textarea, label, summary, [data-sem-clique-linha]')) return;
        var selecao = window.getSelection ? String(window.getSelection()) : '';
        if (selecao.trim() !== '') return;

        var alvo = tr.querySelector('a.js-ver') || tr.querySelector('.acoes a[aria-label="Editar"]');
        if (!alvo) return;

        if (e.ctrlKey || e.metaKey) {
            window.open(alvo.href, '_blank', 'noopener');
            return;
        }
        alvo.click();
    });
})();

// Filtros das listagens no celular: o botão "Filtros" mostra/esconde os campos além da busca.
// Sem campo de busca e com poucos campos (ex.: só o período), os campos já aparecem abertos.
(function () {
    document.querySelectorAll('[data-filtros]').forEach(function (bloco) {
        var botao = bloco.querySelector('[data-filtros-alternar]');
        var form = bloco.querySelector('form');
        if (!botao || !form) return;

        var campos = form.querySelectorAll(':scope > .form-group:not(.filtros__acoes)');
        var temPrincipal = !!form.querySelector(':scope > .filtros__principal');
        var extras = Array.prototype.filter.call(campos, function (c) {
            return !c.classList.contains('filtros__principal');
        });

        function marcar(aberto) {
            bloco.classList.toggle('filtros--aberto', aberto);
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        }

        if (extras.length === 0) {
            botao.remove();
            return;
        }
        if (!temPrincipal && extras.length <= 2) {
            marcar(true);
            botao.remove();
            return;
        }

        marcar(false);
        botao.addEventListener('click', function () {
            var aberto = !bloco.classList.contains('filtros--aberto');
            marcar(aberto);
            if (aberto) {
                var primeiro = extras[0].querySelector('input, select');
                if (primeiro) primeiro.focus({ preventScroll: true });
            }
        });
    });
})();

// Teclado numérico custom (mobile) — mostra um teclado simples com 0-9 e vírgula
(function () {
    var keypad = null;
    var activeInput = null;

    function isMobileClient() {
        return (('ontouchstart' in window) || navigator.maxTouchPoints > 0) && window.innerWidth <= 900;
    }

    function createKeypad() {
        if (keypad) return;
        keypad = document.createElement('div');
        keypad.id = 'numeric-keypad';
        keypad.className = 'numeric-keypad';
        keypad.innerHTML =
            '<div class="nk-top"><div class="nk-display" aria-hidden="true"></div><button class="nk-ok">OK</button></div>' +
            '<div class="nk-grid">' +
                '<button class="nk-btn">1</button><button class="nk-btn">2</button><button class="nk-btn">3</button>' +
                '<button class="nk-btn">4</button><button class="nk-btn">5</button><button class="nk-btn">6</button>' +
                '<button class="nk-btn">7</button><button class="nk-btn">8</button><button class="nk-btn">9</button>' +
                '<button class="nk-btn nk-comma">,</button><button class="nk-btn">0</button><button class="nk-btn nk-back">←</button>' +
            '</div>';
        document.body.appendChild(keypad);

        // Use pointerdown for better mobile responsiveness and to avoid click delays
        function handleKeypadEvent(e) {
            e.preventDefault();
            e.stopPropagation();
            var tgt = e.target;
            var btn = tgt.closest ? tgt.closest('.nk-btn, .nk-ok') : null;
            if (!btn) return;
            if (!activeInput) return;
            var display = keypad.querySelector('.nk-display');
            var decimals = parseInt(activeInput.getAttribute('data-decimals') || '2', 10);

            // helper: format buffer into display string
            function formatFromBuffer(buffer, dec) {
                buffer = buffer || '';
                // remove non-digits
                buffer = buffer.replace(/\D/g, '');
                if (buffer.length === 0) {
                    return '0,' + '0'.repeat(dec);
                }
                if (buffer.length <= dec) {
                    var decPart = buffer.padStart(dec, '0');
                    return '0,' + decPart;
                }
                var intPart = buffer.slice(0, -dec);
                var decPart = buffer.slice(-dec);
                // format intPart with thousands separator
                intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return intPart + ',' + decPart;
            }

            if (btn.classList.contains('nk-ok')) {
                hideKeypad();
                try { activeInput.focus(); } catch (e) {}
                activeInput.dispatchEvent(new Event('blur', { bubbles: true }));
                return;
            }

            // initialize buffer from dataset
            var buf = keypad.dataset.buffer || '';
            var replaceFirst = keypad.dataset.replaceNext === '1';

            if (btn.classList.contains('nk-back')) {
                if (replaceFirst) {
                    buf = '';
                    keypad.dataset.replaceNext = '0';
                } else {
                    buf = buf.slice(0, -1);
                }
                keypad.dataset.buffer = buf;
                var out = formatFromBuffer(buf, decimals);
                try { activeInput.removeAttribute && activeInput.removeAttribute('readonly'); } catch (e) {}
                try { activeInput.dataset.fromKeypad = '1'; } catch (e) {}
                activeInput.value = out;
                try { activeInput.setAttribute('value', out); } catch (e) {}
                try { activeInput.setAttribute('readonly', 'readonly'); } catch (e) {}
                display.textContent = out;
                activeInput.dispatchEvent(new Event('input', { bubbles: true }));
                setTimeout(function () { try { delete activeInput.dataset.fromKeypad; } catch (e) {} }, 50);
                return;
            }

            var key = btn.textContent.trim();
            // if comma pressed, enable explicit comma (no-op for buffer mode)
            if (key === ',') {
                // show current formatted value
                var outc = formatFromBuffer(buf, decimals);
                try { activeInput.dataset.fromKeypad = '1'; } catch (e) {}
                activeInput.value = outc;
                try { activeInput.setAttribute('value', outc); } catch (e) {}
                display.textContent = outc;
                keypad.dataset.replaceNext = '0';
                activeInput.dispatchEvent(new Event('input', { bubbles: true }));
                setTimeout(function () { try { delete activeInput.dataset.fromKeypad; } catch (e) {} }, 50);
                return;
            }

            if (!/^\d$/.test(key)) return;

            if (replaceFirst) {
                buf = key;
                keypad.dataset.replaceNext = '0';
            } else {
                buf = (buf || '') + key;
            }

            // remove leading zeros if buffer longer than decimals
            while (buf.length > (decimals + 1) && buf.charAt(0) === '0') buf = buf.slice(1);

            keypad.dataset.buffer = buf;
            var out = formatFromBuffer(buf, decimals);

            try {
                var wasReadOnly = activeInput.hasAttribute && activeInput.hasAttribute('readonly');
                if (wasReadOnly) { try { activeInput.removeAttribute('readonly'); } catch (e) { activeInput.readOnly = false; } }
                try { activeInput.dataset.fromKeypad = '1'; } catch (e) {}
                activeInput.value = out;
                try { activeInput.setAttribute('value', out); } catch (e) {}
                if (wasReadOnly) { try { activeInput.setAttribute('readonly', 'readonly'); } catch (e) { activeInput.readOnly = true; } }
                display.textContent = out;
                try { if (typeof activeInput.setSelectionRange === 'function') { var len = (out || '').length; activeInput.setSelectionRange(len, len); } } catch (e) {}
                try {
                    var ie = null;
                    try { ie = new InputEvent('input', { bubbles: true, composed: true, inputType: 'insertText', data: null }); } catch (err) { ie = null; }
                    if (ie) activeInput.dispatchEvent(ie); else activeInput.dispatchEvent(new Event('input', { bubbles: true }));
                } catch (e) { activeInput.dispatchEvent(new Event('input', { bubbles: true })); }
                try { activeInput.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) {}
                setTimeout(function () { try { delete activeInput.dataset.fromKeypad; } catch (e) {} }, 50);
            } catch (err) {
                console.error('numeric keypad write error', err);
            }
        }

        // pointerdown handles both mouse and touch/pen; do not use click fallback to avoid duplicate events
        keypad.addEventListener('pointerdown', handleKeypadEvent);

            // hide when tapping outside
        document.addEventListener('touchstart', function (e) {
            if (!keypad || !activeInput) return;
            if (e.target.closest('#numeric-keypad') || e.target === activeInput) return;
            hideKeypad();
        }, { passive: true });

        // esconder teclado quando modal abrir
        var modalObserver = new MutationObserver(function () {
            document.querySelectorAll('.modal-overlay').forEach(function (m) {
                if (!m.hidden) hideKeypad();
            });
        });
        document.querySelectorAll('.modal-overlay').forEach(function (m) {
            modalObserver.observe(m, { attributes: true, attributeFilter: ['hidden'] });
        });
    }

    function showKeypadFor(input) {
        if (!isMobileClient()) return;
        createKeypad();
        activeInput = input;
        // readonly marcado só para o gesto de toque não conta como readonly original
        var temporario = input.dataset.readonlyToque === '1';
        input._wasReadonly = temporario ? false : !!(input.readOnly || (input.hasAttribute && input.hasAttribute('readonly')));
        if (temporario) delete input.dataset.readonlyToque;
        try { input.setAttribute('readonly', 'readonly'); } catch (e) { input.readOnly = true; }
        var display = keypad.querySelector('.nk-display');
        var val = String(input.value || '');
        // initialize numeric buffer from existing value (digits only)
        var digitsOnly = (val || '').replace(/[^0-9]/g, '');
        keypad.dataset.buffer = digitsOnly || '';
        // na abertura, o primeiro dígito deve substituir o conteúdo (comportamento desejado)
        keypad.dataset.replaceNext = '1';
        display.textContent = val;
        keypad.classList.add('numeric-keypad--visible');
        // when opening, ensure comma/decimals respected
        setTimeout(function () {
            // focus for accessibility
            try { input.focus(); } catch (e) {}
        }, 0);
    }

    // Desktop keyboard behavior: apply same buffer/centavos logic when using physical keyboard
    (function attachDesktopKeyboard() {
        var selector = '.input-numeric, .input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo, .input-qtd';

        function formatFromBuffer(buffer, dec) {
            buffer = buffer || '';
            buffer = buffer.replace(/\D/g, '');
            if (buffer.length === 0) return '0,' + '0'.repeat(dec);
            if (buffer.length <= dec) {
                var decPart = buffer.padStart(dec, '0');
                return '0,' + decPart;
            }
            var intPart = buffer.slice(0, -dec);
            var decPart = buffer.slice(-dec);
            intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            return intPart + ',' + decPart;
        }

        document.addEventListener('focusin', function (e) {
            if (isMobileClient()) return;
            var el = e.target;
            if (!el || !el.matches) return;
            if (!el.matches(selector)) return;
            if (!el.classList.contains('input-numeric')) {
                el.classList.add('input-numeric');
                if (!el.getAttribute('data-decimals')) {
                    if (el.classList.contains('input-qtd')) el.setAttribute('data-decimals', '2');
                    else el.setAttribute('data-decimals', '2');
                }
            }
            // initialize buffer and set replace-first behavior
            var val = String(el.value || '');
            var digitsOnly = val.replace(/[^0-9]/g, '');
            el.dataset.buffer = digitsOnly || '';
            el.dataset.replaceNext = '1';
        });

        document.addEventListener('keydown', function (e) {
            if (isMobileClient()) return;
            var el = e.target;
            if (!el || !el.matches) return;
            if (!el.matches(selector)) return;
            // allow navigation keys, tab, enter, etc.
            if (e.key.length > 1 && !['Backspace', 'Enter', 'Tab', 'Delete'].includes(e.key)) return;
            // ignore combos
            if (e.ctrlKey || e.metaKey || e.altKey) return;

            var decimals = parseInt(el.getAttribute('data-decimals') || '2', 10);
            var buf = el.dataset.buffer || '';
            var replaceFirst = el.dataset.replaceNext === '1';

            if (e.key === 'Backspace') {
                e.preventDefault();
                if (replaceFirst) {
                    buf = '';
                    el.dataset.replaceNext = '0';
                } else {
                    buf = buf.slice(0, -1);
                }
                el.dataset.buffer = buf;
                var out = formatFromBuffer(buf, decimals);
                try { el.dataset.fromKeypad = '1'; } catch (err) {}
                el.value = out;
                try { el.setAttribute('value', out); } catch (err) {}
                el.dispatchEvent(new Event('input', { bubbles: true }));
                setTimeout(function () { try { delete el.dataset.fromKeypad; } catch (e) {} }, 50);
                return;
            }

            if (e.key === ',' || e.key === '.') {
                e.preventDefault();
                // show formatted current buffer
                var outc = formatFromBuffer(buf, decimals);
                try { el.dataset.fromKeypad = '1'; } catch (err) {}
                el.value = outc;
                try { el.setAttribute('value', outc); } catch (err) {}
                el.dataset.replaceNext = '0';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                setTimeout(function () { try { delete el.dataset.fromKeypad; } catch (e) {} }, 50);
                return;
            }

            if (/^\d$/.test(e.key)) {
                e.preventDefault();
                if (replaceFirst) {
                    buf = e.key;
                    el.dataset.replaceNext = '0';
                } else {
                    buf = (buf || '') + e.key;
                }
                // trim leading zeros
                while (buf.length > (decimals + 1) && buf.charAt(0) === '0') buf = buf.slice(1);
                el.dataset.buffer = buf;
                var out = formatFromBuffer(buf, decimals);
                try { el.dataset.fromKeypad = '1'; } catch (err) {}
                el.value = out;
                try { el.setAttribute('value', out); } catch (err) {}
                el.dispatchEvent(new Event('input', { bubbles: true }));
                setTimeout(function () { try { delete el.dataset.fromKeypad; } catch (e) {} }, 50);
                return;
            }
        });
    })();

    function hideKeypad() {
        if (!keypad) return;
        keypad.classList.remove('numeric-keypad--visible');
        if (activeInput) {
            // restore readonly state
            try {
                if (!activeInput._wasReadonly) {
                    try { activeInput.removeAttribute('readonly'); } catch (e) { activeInput.readOnly = false; }
                }
                delete activeInput._wasReadonly;
            } catch (e) {}
            activeInput = null;
        }
    }

    var seletorTeclado = '.input-numeric, .input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo, .input-qtd';
    var seletorNumerico = seletorTeclado + ', input[inputmode="numeric"], input[inputmode="decimal"], input[inputmode="tel"], input[type="tel"], input[type="number"]';
    var gesto = null;
    var rolouEm = 0;
    var LIMIAR_ROLAGEM = 12;

    function usaToque() {
        return ('ontouchstart' in window) || navigator.maxTouchPoints > 0;
    }

    function campoNumerico(el) {
        if (!el || !el.closest) return null;
        return el.closest(seletorNumerico);
    }

    function prepararTeclado(el) {
        if (!el.classList.contains('input-numeric')) {
            el.classList.add('input-numeric');
            if (!el.getAttribute('data-decimals')) el.setAttribute('data-decimals', '2');
        }
    }

    function marcarSomenteLeitura(el) {
        if (el.readOnly || (el.hasAttribute && el.hasAttribute('readonly'))) return;
        el.dataset.readonlyToque = '1';
        el.setAttribute('readonly', 'readonly');
    }

    function soltarSomenteLeitura(el) {
        if (!el || el.dataset.readonlyToque !== '1') return;
        el.removeAttribute('readonly');
        delete el.dataset.readonlyToque;
    }

    function gestoFoiRolagem() {
        return rolouEm && (Date.now() - rolouEm) < 600;
    }

    // O toque só abre o teclado se o dedo não se mover. Rolagem segue normal.
    document.addEventListener('touchstart', function (e) {
        if (!usaToque()) return;
        var el = campoNumerico(e.target);
        if (!el || el.disabled) {
            gesto = null;
            return;
        }
        gesto = {
            el: el,
            x: e.touches[0].clientX,
            y: e.touches[0].clientY,
            moved: false
        };
        marcarSomenteLeitura(el);
    }, { passive: true });

    document.addEventListener('touchmove', function (e) {
        if (!gesto || !e.touches || !e.touches.length) return;
        var dx = Math.abs(e.touches[0].clientX - gesto.x);
        var dy = Math.abs(e.touches[0].clientY - gesto.y);
        if (dx > LIMIAR_ROLAGEM || dy > LIMIAR_ROLAGEM) {
            gesto.moved = true;
            rolouEm = Date.now();
            if (document.activeElement === gesto.el) gesto.el.blur();
        }
    }, { passive: true });

    document.addEventListener('touchend', function (e) {
        if (!gesto) return;
        var g = gesto;
        gesto = null;
        if (g.moved) {
            rolouEm = Date.now();
            if (activeInput === g.el) hideKeypad();
            setTimeout(function () {
                if (document.activeElement === g.el) g.el.blur();
                soltarSomenteLeitura(g.el);
            }, 80);
            return;
        }
        if (e.cancelable) e.preventDefault();
        if (isMobileClient() && g.el.matches(seletorTeclado)) {
            prepararTeclado(g.el);
            showKeypadFor(g.el);
            return;
        }
        soltarSomenteLeitura(g.el);
        try { g.el.focus(); } catch (err) {}
    }, { passive: false });

    document.addEventListener('touchcancel', function () {
        if (gesto) soltarSomenteLeitura(gesto.el);
        gesto = null;
    }, { passive: true });

    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (!el || !el.matches || !campoNumerico(el)) return;
        if (gestoFoiRolagem()) {
            el.blur();
            soltarSomenteLeitura(el);
            return;
        }
        if (!isMobileClient() || !el.matches(seletorTeclado)) return;
        prepararTeclado(el);
        showKeypadFor(el);
    });

    // hide on resize (desktop)
    window.addEventListener('resize', function () {
        if (!isMobileClient()) hideKeypad();
    });
})();

// Normalizar inputs numéricos antes do submit para passar validação backend (ex.: "1.234,56" -> "1234.56")
document.addEventListener('submit', function (e) {
    try {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        // Não desabilitar/normalizar se o submit será interceptado pelo modal de confirmação
        var submitter = e.submitter || window.__lastClickSubmitter;
        var needsConfirm = false;
        if (submitter && submitter.hasAttribute && submitter.hasAttribute('data-confirm')) {
            needsConfirm = true;
        } else if (form.hasAttribute('data-confirm')) {
            needsConfirm = true;
        }
        if (needsConfirm && form.dataset.confirmApproved !== '1') {
            return;
        }

        // prevenir double-submit
        if (form.dataset.submitted === '1') {
            e.preventDefault();
            return;
        }

        form.querySelectorAll('.input-numeric').forEach(function (el) {
            try {
                var decimals = parseInt(el.getAttribute('data-decimals') || '2', 10);
                var raw = String(el.value || '');
                // use global parseBR to get numeric value
                var num = (typeof window.parseBR === 'function') ? window.parseBR(raw) : parseFloat(raw.toString().replace(/\./g, '').replace(',', '.')) || 0;
                // format with dot as decimal separator for backend numeric validation
                var normalized = num.toFixed(decimals);
                // set normalized value into the element so Laravel receives a numeric string like "1234.56"
                el.value = normalized;
                try { el.setAttribute('value', normalized); } catch (err) {}
            } catch (err) {
                // ignore per-field errors
            }
        });
        // marcar como enviado e desabilitar botões para evitar múltiplos envios
        form.dataset.submitted = '1';
        var btns = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        btns.forEach(function(b){ b.disabled = true; });
    } catch (err) {
        // noop
    }
}, true);

// Ao voltar pelo histórico (bfcache) o formulário continuaria marcado como enviado, com os botões travados
window.addEventListener('pageshow', function (e) {
    if (!e.persisted) return;
    document.querySelectorAll('form[data-submitted]').forEach(function (form) {
        delete form.dataset.submitted;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) { b.disabled = false; });
    });
});

// ViaCEP + mascaras
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[name="cep"]').forEach(function (cepEl) {
        cepEl.addEventListener('blur', function () {
            var cep = String(this.value || '').replace(/\D/g, '');
            if (cep.length !== 8) return;
            var form = this.closest('form') || document;
            var liberarCep = window.ErpCarregamento ? window.ErpCarregamento.ocuparCampo(cepEl) : function () {};
            fetch('https://viacep.com.br/ws/' + cep + '/json/')
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (!d || d.erro) return;
                    var set = function (name, val) {
                        var el = form.querySelector('[name="' + name + '"]');
                        if (el) el.value = val || '';
                    };
                    set('logradouro', d.logradouro);
                    set('bairro', d.bairro);
                    set('cidade', d.localidade);
                    var uf = form.querySelector('[name="estado"]') || form.querySelector('[name="uf"]');
                    if (uf) uf.value = d.uf || '';
                    var ibge = String(d.ibge || '').replace(/\D/g, '');
                    if (ibge.length === 7) set('cod_ibge', ibge);
                })
                .catch(function () {})
                .finally(liberarCep);
        });
    });

    function onlyDigits(v) { return String(v || '').replace(/\D/g, ''); }

    function maskCpf(v) {
        v = onlyDigits(v).slice(0, 11);
        if (v.length > 9) return v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        if (v.length > 6) return v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        if (v.length > 3) return v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        return v;
    }

    function maskCep(v) {
        v = onlyDigits(v).slice(0, 8);
        if (v.length > 5) return v.replace(/(\d{5})(\d{1,3})/, '$1-$2');
        return v;
    }

    function maskPhone(v) {
        v = onlyDigits(v).slice(0, 11);
        if (v.length > 10) return v.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        if (v.length > 6) return v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
        if (v.length > 2) return v.replace(/(\d{2})(\d{0,5})/, '($1) $2');
        return v;
    }

    function maskCnpjNumeric(v) {
        v = onlyDigits(v).slice(0, 14);
        if (v.length > 12) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{1,2})/, '$1.$2.$3/$4-$5');
        if (v.length > 8) return v.replace(/(\d{2})(\d{3})(\d{3})(\d{1,4})/, '$1.$2.$3/$4');
        if (v.length > 5) return v.replace(/(\d{2})(\d{3})(\d{1,3})/, '$1.$2.$3');
        if (v.length > 2) return v.replace(/(\d{2})(\d{1,3})/, '$1.$2');
        return v;
    }

    document.querySelectorAll('[name="cpf"]').forEach(function (el) {
        el.addEventListener('input', function () { this.value = maskCpf(this.value); });
    });

    document.querySelectorAll('[name="cep"]').forEach(function (el) {
        el.addEventListener('input', function () { this.value = maskCep(this.value); });
    });

    document.querySelectorAll('[name="telefone"], [name="celular"]').forEach(function (el) {
        el.addEventListener('input', function () { this.value = maskPhone(this.value); });
    });

    document.querySelectorAll('[name="cnpj"]').forEach(function (el) {
        el.addEventListener('input', function () {
            var raw = String(this.value || '').toUpperCase();
            var alnum = raw.replace(/[^0-9A-Z]/g, '');
            if (/[A-Z]/.test(alnum)) {
                this.value = alnum.slice(0, 14);
            } else {
                this.value = maskCnpjNumeric(alnum);
            }
        });
    });
});

/* Dropdown "Exportar" (PDF / CSV) */
(function () {
    function closeAllExportDropdowns(except) {
        document.querySelectorAll('[data-export-dropdown].is-open').forEach(function (wrap) {
            if (except && wrap === except) return;
            wrap.classList.remove('is-open');
            var menu = wrap.querySelector('.export-dropdown__menu');
            var toggle = wrap.querySelector('[data-export-toggle]');
            if (menu) menu.hidden = true;
            if (toggle) toggle.setAttribute('aria-expanded', 'false');
        });
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-export-toggle]');
        if (toggle) {
            e.preventDefault();
            var wrap = toggle.closest('[data-export-dropdown]');
            if (!wrap) return;
            var isOpen = wrap.classList.contains('is-open');
            closeAllExportDropdowns();
            if (!isOpen) {
                wrap.classList.add('is-open');
                var menu = wrap.querySelector('.export-dropdown__menu');
                if (menu) menu.hidden = false;
                toggle.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        if (!e.target.closest('[data-export-dropdown]')) {
            closeAllExportDropdowns();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeAllExportDropdowns();
    });
})();

/* Telas de detalhe no celular: ações destrutivas ou raras (Recusar, Cancelar, Excluir — botões
   danger/warning) saem da barra e vão para o menu "Mais ações" (⋮). No desktop voltam ao lugar. */
(function () {
    var barra = document.querySelector('.page-bar__actions .page-actions');
    if (!barra || !window.matchMedia) return;

    var itens = Array.prototype.filter.call(barra.children, function (el) {
        return el.matches('.btn--danger, .btn--warning') || el.querySelector('.btn--danger, .btn--warning');
    });
    if (itens.length < 2) return;

    var marcas = itens.map(function (el) {
        var marca = document.createComment('acao');
        el.parentNode.insertBefore(marca, el);
        return marca;
    });

    var mais = document.createElement('div');
    mais.className = 'export-dropdown page-actions__mais';
    mais.setAttribute('data-export-dropdown', '');
    mais.innerHTML =
        '<button type="button" class="btn btn--ghost btn--sm export-dropdown__toggle page-actions__mais-botao"' +
        ' aria-expanded="false" aria-haspopup="true" data-export-toggle title="Mais ações">' +
        '<svg class="icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"' +
        ' stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="12" r="1">' +
        '</circle><circle cx="12" cy="19" r="1"></circle></svg><span>Mais</span></button>' +
        '<div class="export-dropdown__menu page-actions__menu" role="menu" hidden></div>';
    var menu = mais.querySelector('.page-actions__menu');
    var celular = window.matchMedia('(max-width: 640px)');

    function aplicar() {
        if (celular.matches) {
            itens.forEach(function (el) { menu.appendChild(el); });
            barra.appendChild(mais);
        } else {
            itens.forEach(function (el, i) { marcas[i].parentNode.insertBefore(el, marcas[i].nextSibling); });
            if (mais.parentNode) mais.parentNode.removeChild(mais);
        }
    }

    aplicar();
    if (celular.addEventListener) celular.addEventListener('change', aplicar);
})();

/* Busca rápida do cabeçalho (x-busca-rapida): telas do menu lateral (já filtradas por permissão)
   + ações de criação. Procura sem acento e por palavras soltas ("pagar conta" acha "Contas a pagar"). */
(function () {
    // app.js carrega antes do @stack('modals'): a caixa só existe depois do DOM pronto.
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciarBusca);
    else iniciarBusca();

    function iniciarBusca() {
    var caixa = document.getElementById('busca-rapida');
    var campo = document.getElementById('busca-rapida-campo');
    var lista = document.getElementById('busca-rapida-lista');
    if (!caixa || !campo || !lista) return;

    function normalizar(t) {
        return String(t || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    var itens = [];
    document.querySelectorAll('#menu-lateral a.sidebar__link').forEach(function (a) {
        var grupo = a.closest('.sidebar__group');
        var rotuloGrupo = grupo ? grupo.querySelector('.sidebar__group-toggle .sidebar__group-label') : null;
        var nome = (a.textContent || '').replace(/\s+/g, ' ').trim();
        var nomeGrupo = rotuloGrupo ? rotuloGrupo.textContent.replace(/\s+/g, ' ').trim() : '';
        if (!nome) return;
        itens.push({ rotulo: nome, url: a.href, grupo: nomeGrupo && nomeGrupo !== nome ? nomeGrupo : 'Tela', termos: '' });
    });
    try {
        JSON.parse((document.getElementById('busca-rapida-acoes') || {}).textContent || '[]').forEach(function (a) {
            itens.push(a);
        });
    } catch (e) { /* lista de ações opcional */ }
    itens.forEach(function (it) {
        it.chave = normalizar(it.rotulo);
        it.tudo = normalizar(it.rotulo + ' ' + it.grupo + ' ' + (it.termos || ''));
    });

    var resultado = [];
    var ativo = 0;
    var origemFoco = null;

    function filtrar(texto) {
        var q = normalizar(texto);
        if (!q) return itens.slice(0, 12);
        var palavras = q.split(/\s+/);
        return itens
            .filter(function (it) { return palavras.every(function (p) { return it.tudo.indexOf(p) !== -1; }); })
            .map(function (it) {
                var nota = it.chave.indexOf(q) === 0 ? 0 : (it.chave.indexOf(q) !== -1 ? 1 : 2);
                return { it: it, nota: nota };
            })
            .sort(function (a, b) { return a.nota - b.nota; })
            .slice(0, 12)
            .map(function (x) { return x.it; });
    }

    function desenhar() {
        resultado = filtrar(campo.value);
        ativo = Math.min(ativo, Math.max(resultado.length - 1, 0));
        lista.innerHTML = '';
        if (!resultado.length) {
            var vazio = document.createElement('li');
            vazio.className = 'busca-rapida__vazio';
            vazio.textContent = 'Nada encontrado para "' + campo.value.trim() + '".';
            lista.appendChild(vazio);
            campo.removeAttribute('aria-activedescendant');
            return;
        }
        resultado.forEach(function (it, i) {
            var li = document.createElement('li');
            li.setAttribute('role', 'option');
            li.id = 'busca-rapida-op-' + i;
            li.className = 'busca-rapida__item' + (i === ativo ? ' is-ativo' : '');
            li.setAttribute('aria-selected', i === ativo ? 'true' : 'false');
            var a = document.createElement('a');
            a.href = it.url;
            a.tabIndex = -1;
            var nome = document.createElement('span');
            nome.className = 'busca-rapida__nome';
            nome.textContent = it.rotulo;
            var grupo = document.createElement('span');
            grupo.className = 'busca-rapida__grupo';
            grupo.textContent = it.grupo;
            a.appendChild(nome);
            a.appendChild(grupo);
            li.appendChild(a);
            li.addEventListener('mousemove', function () {
                if (ativo !== i) { ativo = i; marcar(); }
            });
            lista.appendChild(li);
        });
        marcar();
    }

    function marcar() {
        Array.prototype.forEach.call(lista.querySelectorAll('.busca-rapida__item'), function (li, i) {
            li.classList.toggle('is-ativo', i === ativo);
            li.setAttribute('aria-selected', i === ativo ? 'true' : 'false');
            if (i === ativo) {
                campo.setAttribute('aria-activedescendant', li.id);
                if (li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
            }
        });
    }

    function abrir() {
        if (!caixa.hidden) return;
        origemFoco = document.activeElement;
        caixa.hidden = false;
        document.body.classList.add('busca-rapida-aberta');
        campo.value = '';
        ativo = 0;
        desenhar();
        campo.focus();
    }

    function fechar() {
        if (caixa.hidden) return;
        caixa.hidden = true;
        document.body.classList.remove('busca-rapida-aberta');
        if (origemFoco && origemFoco.focus) origemFoco.focus();
    }

    function ir(novaAba) {
        var it = resultado[ativo];
        if (!it) return;
        if (novaAba) { window.open(it.url, '_blank'); return; }
        window.location.href = it.url;
    }

    document.querySelectorAll('[data-busca-abrir]').forEach(function (b) { b.addEventListener('click', abrir); });
    caixa.querySelectorAll('[data-busca-fechar]').forEach(function (b) { b.addEventListener('click', fechar); });
    caixa.addEventListener('mousedown', function (e) { if (e.target === caixa) fechar(); });
    campo.addEventListener('input', function () { ativo = 0; desenhar(); });
    campo.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!resultado.length) return;
            ativo = (ativo + (e.key === 'ArrowDown' ? 1 : -1) + resultado.length) % resultado.length;
            marcar();
        } else if (e.key === 'Enter') {
            e.preventDefault();
            ir(e.ctrlKey || e.metaKey);
        } else if (e.key === 'Escape') {
            e.preventDefault();
            fechar();
        } else if (e.key === 'Tab') {
            e.preventDefault();
        }
    });

    function digitando(el) {
        if (!el) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    function outroModalAberto() {
        return !!document.querySelector('.modal-overlay:not([hidden]), #modal-ver:not([hidden]), .comandos:not([hidden])');
    }

    document.addEventListener('keydown', function (e) {
        if (!caixa.hidden || e.defaultPrevented || outroModalAberto()) return;
        if (document.getElementById('pdv-shell')) return; // PDV: "/" é a busca de produtos
        var barra = e.key === '/' && !e.ctrlKey && !e.metaKey && !e.altKey && !digitando(document.activeElement);
        var ctrlK = (e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')
            && !document.querySelector('[data-comandos-abrir]');
        if (barra || ctrlK) {
            e.preventDefault();
            abrir();
        }
    });
    }
})();

/* Faixas que rolam de lado (abas de situação, módulos dos relatórios): a aba ativa aparece
   mesmo sendo das últimas, e um esmaecido na borda avisa que há mais opções. */
(function () {
    function preparar(faixa) {
        var ativa = faixa.querySelector('.is-ativa, .is-on');
        if (ativa && faixa.scrollWidth > faixa.clientWidth) {
            faixa.scrollLeft = ativa.offsetLeft - (faixa.clientWidth - ativa.offsetWidth) / 2;
        }
        function marcar() {
            var sobra = faixa.scrollWidth - faixa.clientWidth - faixa.scrollLeft;
            faixa.classList.toggle('tem-mais-direita', sobra > 4);
            faixa.classList.toggle('tem-mais-esquerda', faixa.scrollLeft > 4);
        }
        marcar();
        faixa.addEventListener('scroll', marcar, { passive: true });
        window.addEventListener('resize', marcar);
    }

    function iniciar() {
        document.querySelectorAll('.abas-filtro, .rp-chips').forEach(preparar);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', iniciar);
    else iniciar();
})();

/* Ctrl+S (Cmd+S) salva o formulário em edição: clica no botão principal de envio, passando pelas
   mesmas validações e confirmações do clique. Fora do PDV e da NF-e (fluxo fiscal próprio). */
(function () {
    function visivel(el) {
        return !!(el && (el.offsetWidth || el.offsetHeight || el.getClientRects().length));
    }

    function botaoSalvar() {
        if (document.getElementById('pdv-shell') || document.getElementById('form-nfe')) return null;
        var candidatos = Array.prototype.filter.call(
            document.querySelectorAll('.main button[type="submit"].btn--primary'),
            function (b) {
                var form = b.form;
                return form && (form.method || '').toLowerCase() === 'post' && !b.disabled && visivel(b)
                    && !b.closest('[data-sem-atalho-salvar], .modal-overlay, #modal-ver, .filtros')
                    && !b.hasAttribute('data-acao');
            }
        );
        if (candidatos.length === 1) return candidatos[0];
        var foco = document.activeElement;
        var doFoco = candidatos.filter(function (b) { return foco && b.form && b.form.contains(foco); });
        return doFoco.length === 1 ? doFoco[0] : null;
    }

    document.addEventListener('keydown', function (e) {
        if (!(e.ctrlKey || e.metaKey) || e.altKey || e.shiftKey || (e.key !== 's' && e.key !== 'S')) return;
        if (document.querySelector('.modal-overlay:not([hidden]), #modal-ver:not([hidden])')) return;
        var botao = botaoSalvar();
        if (!botao) return;
        e.preventDefault();
        botao.click();
    });
})();

/* Ficha das rotas de ver, aberta pelo icone do olho */
(function initModalVer() {
    var modal = document.getElementById('modal-ver');
    if (!modal) return;

    var corpo = document.getElementById('modal-ver-corpo');
    var titulo = document.getElementById('modal-ver-titulo');
    var pedido = 0;

    function fechar() {
        pedido += 1;
        modal.hidden = true;
        document.body.classList.remove('modal-ver-aberto');
        corpo.removeAttribute('aria-busy');
        corpo.replaceChildren();
    }

    function abrir() {
        modal.hidden = false;
        document.body.classList.add('modal-ver-aberto');
        var botao = modal.querySelector('[data-ver-fechar]');
        if (botao) botao.focus();
    }

    function executarScripts(raiz) {
        raiz.querySelectorAll('script').forEach(function (antigo) {
            var novo = document.createElement('script');
            novo.text = antigo.text;
            antigo.replaceWith(novo);
        });
    }

    function mostrarErro(href) {
        titulo.textContent = 'Detalhe';
        corpo.replaceChildren();
        var aviso = document.createElement('p');
        aviso.className = 'text-muted';
        aviso.style.margin = '0';
        aviso.append('Não foi possível abrir este registro. ');
        var link = document.createElement('a');
        link.href = href;
        link.textContent = 'Abrir a página';
        aviso.append(link);
        corpo.append(aviso);
    }

    function carregar(href) {
        var url;
        try {
            url = new URL(href, window.location.origin);
        } catch (erro) {
            window.location.href = href;
            return;
        }
        if (url.origin !== window.location.origin) {
            window.location.href = href;
            return;
        }

        url.searchParams.set('modal', '1');
        var id = ++pedido;
        titulo.textContent = 'Detalhe';
        corpo.replaceChildren();
        corpo.setAttribute('aria-busy', 'true');
        abrir();

        var cancelarEsqueleto = window.ErpCarregamento
            ? window.ErpCarregamento.adiar(200, function () {
                if (id !== pedido) return;
                window.ErpCarregamento.preencher(corpo, 'ficha');
            })
            : function () {};

        fetch(url.toString(), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html'
            },
            credentials: 'same-origin'
        }).then(function (resposta) {
            if (!resposta.ok) throw new Error('falha');
            return resposta.text();
        }).then(function (html) {
            if (id !== pedido) return;
            cancelarEsqueleto();
            corpo.innerHTML = html;
            corpo.removeAttribute('aria-busy');
            var fragmento = corpo.querySelector('.ver-fragment');
            var nome = fragmento ? fragmento.getAttribute('data-titulo') : '';
            titulo.textContent = nome || 'Detalhe';
            executarScripts(corpo);
        }).catch(function () {
            if (id !== pedido) return;
            cancelarEsqueleto();
            corpo.removeAttribute('aria-busy');
            mostrarErro(href);
        });
    }

    modal.querySelectorAll('[data-ver-fechar]').forEach(function (el) {
        el.addEventListener('click', fechar);
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) fechar();
    });

    document.addEventListener('click', function (e) {
        var link = e.target.closest ? e.target.closest('a.js-ver') : null;
        if (!link) return;
        if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        e.preventDefault();
        carregar(link.href);
    }, true);

    document.addEventListener('click', function (e) {
        if (modal.hidden) return;
        var voltar = e.target.closest ? e.target.closest('#modal-ver [aria-label="Voltar"]') : null;
        if (!voltar) return;
        e.preventDefault();
        fechar();
    }, true);

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape' || modal.hidden) return;
        var confirmacao = document.getElementById('modal-confirm');
        if (confirmacao && !confirmacao.hidden) return;
        var interno = modal.querySelector('.modal-overlay:not([hidden])');
        if (interno) {
            interno.hidden = true;
            return;
        }
        fechar();
    });
})();

/* Envio de arquivo (x-upload-arquivo) e modal simples.
   - [data-upload-area]: arrastar e soltar, nome escolhido, remover e conferência da extensão do "accept"
     (o seletor do sistema deixa escolher "Todos os arquivos").
   - .modal-overlay[data-modal]: [data-abrir-modal="id"] abre; [data-close-modal], clique fora e Esc fecham.
     Modais sem data-modal seguem com o controle próprio de cada tela. */
(function () {
    function areaDe(el) {
        return el && el.closest ? el.closest('[data-upload-area]') : null;
    }

    function extensoes(input) {
        return (input.getAttribute('accept') || '').split(',')
            .map(function (item) { return item.trim().toLowerCase(); })
            .filter(function (item) { return item.charAt(0) === '.'; });
    }

    function aceita(input, arquivo) {
        var lista = extensoes(input);
        if (!lista.length) return true;
        var nome = arquivo.name.toLowerCase();
        return lista.some(function (ext) { return nome.slice(-ext.length) === ext; });
    }

    function avisar(area, texto) {
        var aviso = area.querySelector('.upload-area__aviso');
        if (!aviso) return;
        aviso.textContent = texto || '';
        aviso.hidden = !texto;
    }

    function recusar(area, input) {
        limpar(area, true);
        avisar(area, 'Escolha um arquivo ' + extensoes(input).join(' ou ') + '.');
    }

    function mostrar(area) {
        var input = area.querySelector('.upload-area__input');
        var escolhido = area.querySelector('.upload-area__selected');
        var arquivo = input && input.files && input.files[0];
        if (!escolhido) return;
        if (!arquivo) {
            limpar(area, true);
            return;
        }
        escolhido.querySelector('.upload-area__filename').textContent = arquivo.name;
        escolhido.hidden = false;
        area.classList.add('upload-area--has-file');
        area.classList.remove('upload-area--erro');
        avisar(area, '');
    }

    function limpar(area, manterAviso) {
        var input = area.querySelector('.upload-area__input');
        var escolhido = area.querySelector('.upload-area__selected');
        if (input) input.value = '';
        if (escolhido) escolhido.hidden = true;
        area.classList.remove('upload-area--has-file', 'upload-area--drag');
        if (!manterAviso) avisar(area, '');
    }

    document.addEventListener('change', function (e) {
        var input = e.target;
        var area = areaDe(input);
        if (!area || !input.classList.contains('upload-area__input')) return;
        var arquivo = input.files && input.files[0];
        if (arquivo && !aceita(input, arquivo)) {
            recusar(area, input);
            return;
        }
        mostrar(area);
    });

    ['dragenter', 'dragover'].forEach(function (tipo) {
        document.addEventListener(tipo, function (e) {
            var area = areaDe(e.target);
            if (!area) return;
            e.preventDefault();
            area.classList.add('upload-area--drag');
        });
    });

    document.addEventListener('dragleave', function (e) {
        var area = areaDe(e.target);
        if (!area || (e.relatedTarget && area.contains(e.relatedTarget))) return;
        area.classList.remove('upload-area--drag');
    });

    document.addEventListener('drop', function (e) {
        var area = areaDe(e.target);
        if (!area) return;
        e.preventDefault();
        area.classList.remove('upload-area--drag');
        var input = area.querySelector('.upload-area__input');
        var arquivos = e.dataTransfer && e.dataTransfer.files;
        if (!input || !arquivos || !arquivos.length) return;
        if (!aceita(input, arquivos[0])) {
            recusar(area, input);
            return;
        }
        // Só o primeiro arquivo: o campo aceita um por vez.
        try {
            var transferencia = new DataTransfer();
            transferencia.items.add(arquivos[0]);
            input.files = transferencia.files;
        } catch (erro) {
            input.files = arquivos;
        }
        mostrar(area);
    });

    document.addEventListener('click', function (e) {
        var remover = e.target.closest ? e.target.closest('.upload-area__remove') : null;
        if (!remover) return;
        var area = areaDe(remover);
        if (!area) return;
        e.preventDefault();
        e.stopPropagation();
        limpar(area);
        var input = area.querySelector('.upload-area__input');
        if (input) input.focus();
    });

    var origem = null;

    function focarPrimeiroCampo(modal) {
        var campo = modal.querySelector('[data-modal-foco], .upload-area__input, input:not([type="hidden"]), select, textarea');
        if (campo) campo.focus();
    }

    function abrirModal(modal, botao) {
        origem = botao || null;
        modal.hidden = false;
        focarPrimeiroCampo(modal);
    }

    function fecharModal(modal) {
        modal.hidden = true;
        modal.querySelectorAll('form').forEach(function (form) { form.reset(); });
        modal.querySelectorAll('[data-upload-area]').forEach(function (area) {
            limpar(area);
            area.classList.remove('upload-area--erro');
        });
        modal.querySelectorAll('[data-modal-erro]').forEach(function (el) { el.remove(); });
        if (origem && document.body.contains(origem)) origem.focus();
        origem = null;
    }

    document.addEventListener('click', function (e) {
        var botao = e.target.closest ? e.target.closest('[data-abrir-modal]') : null;
        if (botao) {
            var alvo = document.getElementById(botao.getAttribute('data-abrir-modal'));
            if (alvo && alvo.matches('[data-modal]')) {
                e.preventDefault();
                abrirModal(alvo, botao);
            }
            return;
        }

        var modal = e.target.closest ? e.target.closest('.modal-overlay[data-modal]') : null;
        if (!modal) return;
        if (e.target === modal || (e.target.closest && e.target.closest('[data-close-modal]'))) {
            fecharModal(modal);
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var confirmacao = document.getElementById('modal-confirm');
        if (confirmacao && !confirmacao.hidden) return;
        var abertos = document.querySelectorAll('.modal-overlay[data-modal]:not([hidden])');
        if (abertos.length) fecharModal(abertos[abertos.length - 1]);
    });

    // Volta com erro de validação: o modal já vem aberto pelo servidor.
    function focarAberto() {
        var aberto = document.querySelector('.modal-overlay[data-modal]:not([hidden])');
        if (aberto) focarPrimeiroCampo(aberto);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', focarAberto);
    else focarAberto();
})();
