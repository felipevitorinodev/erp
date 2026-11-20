/*
 * Catálogo de relatórios (resources/views/relatorios/hub.blade.php):
 * busca sem acento, filtro por módulo, contagens e "Abertos recentemente".
 * Os recentes ficam só neste navegador, separados por usuário, e são montados a partir
 * dos cartões da própria página: relatório sem permissão nunca aparece.
 */
(function ($) {
    'use strict';

    var LIMITE_RECENTES = 4;
    var modulo = '';

    function texto(valor) {
        return String(valor || '')
            .toLocaleLowerCase('pt-BR')
            .normalize('NFD')
            .replace(/[̀-ͯ]/g, '');
    }

    function rotulo(quantidade, singular, plural) {
        return quantidade + ' ' + (quantidade === 1 ? singular : plural);
    }

    function filtrar() {
        var termo = texto($('#relatorios-busca').val()).trim();
        var modulosVisiveis = 0;
        var relatoriosVisiveis = 0;
        var porModulo = {};
        var totalNaBusca = 0;

        $('[data-relatorios-categoria]').each(function () {
            var $categoria = $(this);
            var slug = $categoria.attr('data-modulo');
            var doModulo = modulo === '' || slug === modulo;
            var naBusca = 0;
            var naCategoria = 0;

            $categoria.find('[data-relatorio]').each(function () {
                var $item = $(this);
                var achou = texto($item.attr('data-busca')).indexOf(termo) !== -1;
                var combina = doModulo && achou;

                $item.toggleClass('rp-rep--oculto', !combina);

                if (achou) {
                    naBusca += 1;
                }

                if (combina) {
                    naCategoria += 1;
                }
            });

            porModulo[slug] = naBusca;
            totalNaBusca += naBusca;

            $categoria.toggleClass('rp-mod--oculto', naCategoria === 0);
            $categoria.find('[data-modulo-contagem]').text(String(naCategoria));

            if (naCategoria > 0) {
                modulosVisiveis += 1;
                relatoriosVisiveis += naCategoria;
            }
        });

        // Os chips mostram quantos relatórios do módulo combinam com o que foi digitado.
        $('[data-filtro-modulo]').each(function () {
            var slug = $(this).attr('data-filtro-modulo') || '';
            $(this).find('.rp-chip__qtd').text(String(slug === '' ? totalNaBusca : (porModulo[slug] || 0)));
        });

        $('#relatorios-resumo').text(
            rotulo(modulosVisiveis, 'módulo', 'módulos') + ' · ' + rotulo(relatoriosVisiveis, 'relatório', 'relatórios')
        );
        $('#relatorios-vazio').toggleClass('rp-vazio--oculto', relatoriosVisiveis > 0);
        $('#relatorios-recentes').prop('hidden', termo !== '' || modulo !== '' || !$('#relatorios-recentes-lista').children().length);
    }

    function escolherModulo(slug) {
        modulo = slug || '';
        $('[data-filtro-modulo]').each(function () {
            var ativo = ($(this).attr('data-filtro-modulo') || '') === modulo;
            $(this).toggleClass('is-on', ativo).attr('aria-pressed', ativo ? 'true' : 'false');
        });
        filtrar();
    }

    function limparBusca() {
        $('#relatorios-busca').val('');
        escolherModulo('');
        $('#relatorios-busca').trigger('focus');
    }

    /* ---------- Abertos recentemente ---------- */

    function chaveRecentes() {
        return 'visys-relatorios-recentes:' + ($('#relatorios-recentes').attr('data-usuario') || '');
    }

    function lerRecentes() {
        try {
            var lista = JSON.parse(window.localStorage.getItem(chaveRecentes()) || '[]');
            return Array.isArray(lista) ? lista.filter(function (slug) { return typeof slug === 'string'; }) : [];
        } catch (e) {
            return [];
        }
    }

    function guardarRecente(slug) {
        if (!slug) {
            return;
        }

        var lista = lerRecentes().filter(function (item) { return item !== slug; });
        lista.unshift(slug);

        try {
            window.localStorage.setItem(chaveRecentes(), JSON.stringify(lista.slice(0, LIMITE_RECENTES)));
        } catch (e) {
            // Navegador sem armazenamento (aba anônima, bloqueio): só não lembra.
        }
    }

    function montarRecentes() {
        var $lista = $('#relatorios-recentes-lista').empty();

        lerRecentes().forEach(function (slug) {
            var $cartao = $('.rp-modulos [data-relatorio]').filter(function () {
                return $(this).attr('data-slug') === slug;
            }).first();

            if (!$cartao.length) {
                return;
            }

            var $item = $('<a class="rp-recentes__item" data-relatorio-recente></a>')
                .attr('href', $cartao.attr('href'))
                .attr('data-slug', slug)
                .text($cartao.find('.rp-rep__nome').text());

            $lista.append($item);
        });
    }

    $(document).on('input', '#relatorios-busca', filtrar);

    $(document).on('keydown', '#relatorios-busca', function (evento) {
        if (evento.key === 'Escape' && this.value !== '') {
            evento.preventDefault();
            evento.stopPropagation();
            this.value = '';
            filtrar();
        }

        // Enter abre o primeiro relatório encontrado.
        if (evento.key === 'Enter') {
            var $primeiro = $('.rp-modulos [data-relatorio]:not(.rp-rep--oculto)').first();

            if ($primeiro.length) {
                evento.preventDefault();
                guardarRecente($primeiro.attr('data-slug'));
                window.location.href = $primeiro.attr('href');
            }
        }
    });

    $(document).on('click', '[data-filtro-modulo]', function () {
        escolherModulo($(this).attr('data-filtro-modulo'));
    });

    $(document).on('click', '[data-limpar-busca]', limparBusca);

    $(document).on('click', '[data-relatorio], [data-relatorio-recente]', function () {
        guardarRecente($(this).attr('data-slug'));
    });

    $(function () {
        montarRecentes();
        filtrar();
    });
})(jQuery);
