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
            console.debug && console.debug('modal-confirm: init - modal found and attached');

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

                console.debug && console.debug('modal-confirm: abrir', opts);
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
                    console.debug && console.debug('modal-confirm: ok click but no pendingForm');
                    fechar();
                    return;
                }

                console.debug && console.debug('modal-confirm: ok clicked; submitting form', form, submitter);
                form.dataset.confirmApproved = '1';
                fechar();

                if (typeof form.requestSubmit === 'function') {
                    console.debug && console.debug('modal-confirm: using requestSubmit');
                    form.requestSubmit(submitter || undefined);
                } else {
                    console.debug && console.debug('modal-confirm: using form.submit() fallback');
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
                    var form = trg.closest && trg.closest('form') ? trg.closest('form') : (trg.tagName === 'FORM' ? trg : null);
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
    if (!toggle || !overlay) return;

    function abrir() {
        document.body.classList.add('sidebar-open');
        overlay.hidden = false;
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Fechar menu');
    }

    function fechar() {
        document.body.classList.remove('sidebar-open');
        overlay.hidden = true;
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', 'Abrir menu');
    }

    function alternar() {
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
        // prevent native keyboard: set readonly attribute before focusing
        input._wasReadonly = input.readOnly || false;
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

    // attach to input-numeric focus on mobile
    // intercept touchstart to prevent native keyboard from opening (run before focus)
    document.addEventListener('touchstart', function (e) {
        var selector = '.input-numeric, .input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo, .input-qtd';
        var el = e.target.closest ? e.target.closest(selector) : null;
        if (!el) return;
        if (!isMobileClient()) return;
        // prevent native keyboard
        e.preventDefault();
        // ensure data-decimals exists for legacy classes
        if (!el.classList.contains('input-numeric')) {
            el.classList.add('input-numeric');
            if (el.classList.contains('input-qtd')) el.setAttribute('data-decimals', '2');
            else el.setAttribute('data-decimals', '2');
        }
        showKeypadFor(el);
    }, { passive: false });

    // fallback: on focusin show keypad (if touchstart didn't run)
    document.addEventListener('focusin', function (e) {
        var el = e.target;
        if (!el || !el.matches) return;
        var selector = '.input-numeric, .input-preco, .input-desc-item, .input-moeda, .totals-box__input, #input-desconto, #input-acrescimo, .input-qtd';
        if (el.matches(selector) && isMobileClient()) {
            if (!el.classList.contains('input-numeric')) {
                el.classList.add('input-numeric');
                if (el.classList.contains('input-qtd')) el.setAttribute('data-decimals', '2');
                else el.setAttribute('data-decimals', '2');
            }
            showKeypadFor(el);
        }
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

// Toast auto-dismiss + ViaCEP + mascaras
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.toast').forEach(function (toast) {
        setTimeout(function () {
            toast.style.transition = 'opacity 0.3s';
            toast.style.opacity = '0';
            setTimeout(function () { toast.remove(); }, 300);
        }, 4500);
    });

    document.querySelectorAll('[name="cep"]').forEach(function (cepEl) {
        cepEl.addEventListener('blur', function () {
            var cep = String(this.value || '').replace(/\D/g, '');
            if (cep.length !== 8) return;
            var form = this.closest('form') || document;
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
                })
                .catch(function () {});
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
