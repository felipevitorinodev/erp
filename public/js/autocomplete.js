/**
 * Autocomplete genérico via jQuery AJAX.
 *
 * - Ao focar/clicar: carrega 5 itens padrão (ordem alfabética/crescente)
 * - Ao digitar: filtra via API
 * - Lista anexada ao body (evita ficar atrás de outros elementos)
 */
function initAutocomplete(inputSel, hiddenSel, url, renderItem, onSelect, extraParams, valueKey) {
    var $input = inputSel && inputSel.jquery ? inputSel : $(inputSel);
    var $hidden = hiddenSel && hiddenSel.jquery ? hiddenSel : $(hiddenSel);
    var storeKey = valueKey || 'id';

    if (!$input.length || !$hidden.length) {
        return;
    }

    if ($input.data('autocomplete-bound')) {
        return;
    }
    $input.data('autocomplete-bound', true);

    if (!$input.parent().hasClass('autocomplete-wrap')) {
        $input.wrap('<div class="autocomplete-wrap"></div>');
    }

    var debounceTimer = null;
    var requestId = 0;
    var $list = $('<ul class="autocomplete-list"></ul>').hide().appendTo('body');
    var ns = 'ac' + Math.random().toString(36).slice(2);

    function fechar() {
        $list.hide().empty();
    }

    function labelOf(item) {
        return typeof renderItem === 'function' ? renderItem(item) : (item.nome || item.razao_social || '');
    }

    function posicionar() {
        var el = $input[0];
        if (!el) return;

        var rect = el.getBoundingClientRect();
        // Use absolute positioning based on page offsets so the dropdown stays attached
        // to the input when the page is scrolled on mobile (fixed can behave inconsistently
        // with mobile keyboards/viewport changes).
        $list.css({
            position: 'absolute',
            top: (rect.bottom + window.pageYOffset + 2) + 'px',
            left: (rect.left + window.pageXOffset) + 'px',
            width: Math.max(rect.width, 240) + 'px',
            zIndex: 20000
        });
    }

    function selecionar(item) {
        $input.val(labelOf(item));
        $hidden.val(item[storeKey] != null ? item[storeKey] : item.id);
        fechar();

        if (typeof onSelect === 'function') {
            onSelect(item);
        }
    }

    function paramsFor(q) {
        var base = { q: q || '' };
        var extra = typeof extraParams === 'function' ? extraParams() : (extraParams || {});
        return $.extend({}, base, extra || {});
    }

    function renderLista(data) {
        $list.empty();

        if (!data || !data.length) {
            $list.append('<li class="autocomplete-list__empty">Nenhum resultado</li>');
            posicionar();
            $list.show();
            return;
        }

        data.forEach(function (item) {
            var $li = $('<li class="autocomplete-list__item"></li>').text(labelOf(item));
            $li.on('mousedown', function (e) {
                e.preventDefault();
                selecionar(item);
            });
            $list.append($li);
        });

        posicionar();
        $list.show();
    }

    function buscar(q) {
        var current = ++requestId;
        $input.addClass('is-loading');

        $.ajax({
            url: url,
            method: 'GET',
            dataType: 'json',
            data: paramsFor(q),
            success: function (data) {
                if (current !== requestId) return;
                renderLista(data);
            },
            complete: function () {
                if (current === requestId) {
                    $input.removeClass('is-loading');
                }
            }
        });
    }

    $input.on('focus click', function () {
        buscar($.trim($input.val()));
    });

    $input.on('input', function () {
        var q = $.trim($input.val());

        if (!q) {
            $hidden.val('');
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            buscar(q);
        }, 250);
    });

    $input.on('keydown', function (e) {
        if (e.key === 'Escape') {
            fechar();
        }
    });

    // Reposition on relevant events (include touchmove/orientationchange for mobile)
    $(window).on('scroll.' + ns + ' resize.' + ns + ' orientationchange.' + ns + ' touchmove.' + ns, function () {
        if ($list.is(':visible')) {
            posicionar();
        }
    });

    $(document).on('mousedown.' + ns, function (e) {
        if (
            !$(e.target).closest($input).length &&
            !$(e.target).closest($list).length
        ) {
            fechar();
        }
    });
}

/**
 * Inicializa autocomplete de produto em uma linha de itens (venda/orçamento).
 */
function initProdutoLinhaAutocomplete(row, url, onSelect) {
    var $row = $(row);
    var $input = $row.find('.input-produto-nome');
    var $hidden = $row.find('.input-produto-id');

    initAutocomplete(
        $input,
        $hidden,
        url,
        function (item) {
            return item.nome + (item.codigo ? ' (' + item.codigo + ')' : '');
        },
        function (item) {
            if (typeof onSelect === 'function') {
                onSelect(item, $row);
            }
        }
    );
}
