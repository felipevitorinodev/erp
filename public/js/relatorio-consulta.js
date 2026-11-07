(function ($) {
    'use strict';

    var moeda = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });
    var numero = new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 });
    var cores = ['#163A5F', '#E35A12', '#1F6F3B', '#7A5200', '#1F4F7A', '#8B1A1A'];
    var graficos = [];

    function formatar(valor, formato) {
        var n = Number(valor);
        if (!isFinite(n)) {
            n = 0;
        }

        return formato === 'moeda' ? moeda.format(n) : numero.format(n);
    }

    function destruir() {
        graficos.forEach(function (grafico) {
            grafico.destroy();
        });
        graficos = [];
    }

    function mostrarErro(texto) {
        $('[data-graficos-mensagem]').text(texto);
        $('#relatorio-graficos-erro').removeClass('relatorio-vazio--oculto');
    }

    function desenhar(dados) {
        destruir();
        var lista = (dados && dados.graficos) || [];

        $('#relatorio-graficos [data-grafico]').each(function (indice) {
            var grafico = lista[indice];
            var $bloco = $(this);
            var $estado = $bloco.find('.relatorio-grafico__estado');
            var canvas = $bloco.find('canvas').get(0);

            if (!grafico) {
                $bloco.addClass('relatorio-vazio--oculto');
                return;
            }

            $bloco.removeClass('relatorio-vazio--oculto');
            $bloco.find('.relatorio-grafico__titulo').text(grafico.titulo || 'Gráfico');

            if (grafico.vazio || !grafico.labels || !grafico.labels.length) {
                canvas.hidden = true;
                $estado.text('Nenhum dado para os filtros selecionados.').removeClass('relatorio-vazio--oculto');
                return;
            }

            if (!window.Chart) {
                canvas.hidden = true;
                $estado.text('Não foi possível carregar os gráficos.').removeClass('relatorio-vazio--oculto');
                return;
            }

            var series = grafico.series || [];
            var datasets = series.map(function (serie, serieIndice) {
                var cor = cores[serieIndice % cores.length];

                return {
                    label: serie.nome,
                    data: serie.dados,
                    borderColor: cor,
                    backgroundColor: grafico.tipo === 'line' ? cor : cores.slice(0, (grafico.labels || []).length),
                    tension: 0.25,
                    fill: false,
                    pointRadius: 2
                };
            });

            $estado.addClass('relatorio-vazio--oculto');
            canvas.hidden = false;

            try {
                graficos.push(new window.Chart(canvas, {
                    type: grafico.tipo === 'line' ? 'line' : 'bar',
                    data: { labels: grafico.labels, datasets: datasets },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: series.length > 1, labels: { boxWidth: 12 } },
                            tooltip: {
                                callbacks: {
                                    label: function (contexto) {
                                        var nome = contexto.dataset.label ? contexto.dataset.label + ': ' : '';
                                        var valor = contexto.parsed && contexto.parsed.y != null ? contexto.parsed.y : contexto.raw;
                                        return nome + formatar(valor, grafico.formato);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: { ticks: { maxRotation: 0, autoSkip: true } },
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function (valor) {
                                        return formatar(valor, grafico.formato);
                                    }
                                }
                            }
                        }
                    }
                }));
            } catch (erro) {
                canvas.hidden = true;
                $estado.text('Não foi possível carregar os gráficos.').removeClass('relatorio-vazio--oculto');
            }
        });
    }

    function textoFalha(xhr) {
        var texto = 'Não foi possível carregar os gráficos.';
        if (xhr && xhr.status === 419) texto = 'Sua sessão expirou. Atualize a página.';
        if (xhr && xhr.status === 403) texto = 'Você não tem permissão para ver este relatório.';
        if (xhr && xhr.status === 422) texto = 'Os filtros informados são inválidos.';
        return texto;
    }

    function carregar() {
        var raiz = document.getElementById('relatorio-graficos');
        if (!raiz) return;

        $('#relatorio-graficos-erro').addClass('relatorio-vazio--oculto');

        var pedido = function () {
            return $.ajax({
                url: raiz.getAttribute('data-url') + (window.location.search || ''),
                method: 'GET',
                dataType: 'json',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        };

        var tratar = function (dados) {
            desenhar(dados || {});
        };
        var falhar = function (xhr) {
            destruir();
            mostrarErro(textoFalha(xhr));
        };

        if (!window.ErpCarregamento) {
            pedido().done(tratar).fail(falhar);
            return;
        }

        window.ErpCarregamento.durante(pedido, {
            tipo: 'ajax',
            detalhe: 'relatorio-graficos',
            mostrar: function () {
                raiz.setAttribute('aria-busy', 'true');
                raiz.querySelectorAll('[data-grafico] .relatorio-grafico__area').forEach(function (area) {
                    window.ErpCarregamento.preencher(area, 'grafico');
                });
            },
            limpar: function () {
                raiz.removeAttribute('aria-busy');
                raiz.querySelectorAll('.sk-host').forEach(function (host) {
                    host.remove();
                });
            }
        }).then(tratar, falhar);
    }

    $(function () {
        $('[data-busca-url]').each(function () {
            var $texto = $(this);
            if (typeof window.initAutocomplete !== 'function') {
                return;
            }

            window.initAutocomplete(
                $texto,
                $('#' + $texto.attr('data-busca-alvo')),
                $texto.attr('data-busca-url'),
                function (item) {
                    var nome = item.nome || '';
                    return item.codigo ? nome + ' (' + item.codigo + ')' : nome;
                }
            );
        });

        $(document).on('click', '[data-periodo-personalizado]', function () {
            var painel = document.getElementById('relatorio-filtros');
            if (painel) {
                painel.open = true;
            }
            var campo = document.getElementById('campo-data_inicio');
            if (campo) {
                campo.focus();
            }
        });

        $(document).on('click', '[data-graficos-retry]', function () {
            carregar();
        });

        carregar();
    });
}(jQuery));
