(function ($) {
    'use strict';

    var seletorBusca = '#relatorios-busca';
    var espera = null;

    function texto(valor) {
        return String(valor || '')
            .toLocaleLowerCase('pt-BR')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '');
    }

    function filtrar() {
        var termo = texto($(seletorBusca).val());
        var visiveis = 0;

        $('[data-relatorios-categoria]').each(function () {
            var $categoria = $(this);
            var naCategoria = 0;

            $categoria.find('[data-relatorio]').each(function () {
                var $card = $(this);
                var combina = texto($card.attr('data-busca')).indexOf(termo) !== -1;

                $card.toggleClass('relatorios-card--oculto', !combina);

                if (combina) {
                    naCategoria += 1;
                }
            });

            $categoria.toggleClass('relatorios-categoria--oculta', naCategoria === 0);
            visiveis += naCategoria;
        });

        $('#relatorios-vazio').toggleClass('relatorios-vazio--oculto', visiveis > 0);
    }

    $(document).on('input', seletorBusca, function () {
        window.clearTimeout(espera);
        espera = window.setTimeout(filtrar, 200);
    });
})(jQuery);
