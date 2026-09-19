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
})(window.jQuery);

// Modal de confirmação global (substitui window.confirm)
(function () {
    var modal = document.getElementById('modal-confirm');
    if (!modal) return;

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
            form.submit();
        }
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.confirmApproved === '1') {
            delete form.dataset.confirmApproved;
            return;
        }

        var submitter = e.submitter;
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
