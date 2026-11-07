(function ($) {
    'use strict';

    var modulo = '';

    function texto(valor) {
        return String(valor || '')
            .toLocaleLowerCase('pt-BR')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function rotulo(quantidade, singular, plural) {
        return quantidade + ' ' + (quantidade === 1 ? singular : plural);
    }

    function filtrar() {
        var termo = texto($('#relatorios-busca').val());
        var modulosVisiveis = 0;
        var relatoriosVisiveis = 0;

        $('[data-relatorios-categoria]').each(function () {
            var $categoria = $(this);
            var doModulo = modulo === '' || $categoria.attr('data-modulo') === modulo;
            var naCategoria = 0;

            $categoria.find('[data-relatorio]').each(function () {
                var $item = $(this);
                var combina = doModulo && texto($item.attr('data-busca')).indexOf(termo) !== -1;

                $item.toggleClass('rp-rep--oculto', !combina);

                if (combina) {
                    naCategoria += 1;
                }
            });

            $categoria.toggleClass('rp-mod--oculto', naCategoria === 0);
            $categoria.find('[data-modulo-contagem]').text(String(naCategoria));

            if (naCategoria > 0) {
                modulosVisiveis += 1;
                relatoriosVisiveis += naCategoria;
            }
        });

        $('#relatorios-resumo').text(
            rotulo(modulosVisiveis, 'módulo', 'módulos') + ' · ' + rotulo(relatoriosVisiveis, 'relatório', 'relatórios')
        );
        $('#relatorios-vazio').toggleClass('rp-vazio--oculto', relatoriosVisiveis > 0);
    }

    $(document).on('input', '#relatorios-busca', filtrar);

    $(document).on('click', '[data-filtro-modulo]', function () {
        modulo = $(this).attr('data-filtro-modulo') || '';
        $('[data-filtro-modulo]').removeClass('is-on');
        $(this).addClass('is-on');
        filtrar();
    });
})(jQuery);
