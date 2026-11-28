/*
 * Autocompletar dos códigos da NFS-e (x-nfse-codigos e o município de prestação).
 * Cada grupo [data-nfse-codigos] tem pares de campo visível [data-nfse-busca] e oculto [data-nfse-codigo].
 * Ao escolher o serviço, NBS, classificação e indicador em branco recebem a sugestão da correlação oficial.
 */
(function (window, document, $) {
    'use strict';

    if (!$ || typeof window.initAutocomplete !== 'function') {
        return;
    }

    var TAMANHOS = { servico: 6, nbs: 9, classificacao: 6, indicador: 6, municipio: 7 };

    function rotulo(item) {
        return item.rotulo || (item.codigo + ' - ' + item.descricao);
    }

    function digitos(texto) {
        return String(texto || '').replace(/\D/g, '');
    }

    // Texto digitado sem escolher na lista: aproveita o código quando o texto é ou começa pelo código.
    function alinhar(busca, oculto, tipo) {
        var texto = busca.value.trim();
        if (texto === '') {
            oculto.value = '';
            return;
        }

        var tamanho = TAMANHOS[tipo];
        var codigo = '';
        if (tipo === 'municipio') {
            var fim = texto.match(/(\d{7})\s*$/) || texto.match(/^(\d{7})/);
            codigo = fim ? fim[1] : '';
        } else {
            var inicio = texto.split(' - ')[0];
            codigo = /^[\d.\s]+$/.test(inicio) ? digitos(inicio) : '';
        }

        if (codigo.length === tamanho) {
            oculto.value = codigo;
        } else if (oculto.value && rotuloDoOculto(busca) !== texto) {
            oculto.value = '';
        }
    }

    function rotuloDoOculto(busca) {
        return busca.getAttribute('data-rotulo') || busca.defaultValue;
    }

    function iniciar(raiz) {
        if (raiz.getAttribute('data-nfse-iniciado')) {
            return;
        }
        raiz.setAttribute('data-nfse-iniciado', '1');

        var pares = {};
        raiz.querySelectorAll('[data-nfse-busca]').forEach(function (busca) {
            var tipo = busca.getAttribute('data-nfse-busca');
            var oculto = raiz.querySelector('[data-nfse-codigo="' + tipo + '"]');
            if (oculto) {
                pares[tipo] = { busca: busca, oculto: oculto };
            }
        });

        function valor(tipo) {
            return pares[tipo] ? pares[tipo].oculto.value : '';
        }

        function contexto(comNbs) {
            var params = {};
            if (valor('servico').length === 6) params.servico = valor('servico');
            if (comNbs && valor('nbs').length === 9) params.nbs = valor('nbs');
            return params;
        }

        // Preenche o campo em branco com a primeira sugestão da correlação (o servidor já ordena as sugeridas antes).
        function sugerir(tipo, comNbs, depois) {
            var par = pares[tipo];
            if (!par || par.oculto.value) {
                if (depois) depois();
                return;
            }
            $.getJSON(par.busca.getAttribute('data-url'), contexto(comNbs)).done(function (lista) {
                if (!par.oculto.value && lista && lista.length && lista[0].sugerido) {
                    par.oculto.value = lista[0].codigo;
                    par.busca.value = rotulo(lista[0]);
                    par.busca.setAttribute('data-rotulo', par.busca.value);
                    par.busca.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }).always(function () {
                if (depois) depois();
            });
        }

        Object.keys(pares).forEach(function (tipo) {
            var par = pares[tipo];
            var extras = null;
            if (tipo === 'nbs') extras = function () { return contexto(false); };
            if (tipo === 'classificacao' || tipo === 'indicador') extras = function () { return contexto(true); };

            var aoEscolher = function (item) {
                par.busca.setAttribute('data-rotulo', rotulo(item));
                par.oculto.dispatchEvent(new Event('change', { bubbles: true }));
                if (tipo === 'servico') {
                    sugerir('nbs', false, function () {
                        sugerir('classificacao', true);
                        sugerir('indicador', true);
                    });
                } else if (tipo === 'nbs') {
                    sugerir('classificacao', true);
                    sugerir('indicador', true);
                }
            };

            window.initAutocomplete(par.busca, par.oculto, par.busca.getAttribute('data-url'), rotulo, aoEscolher, extras, 'codigo');

            var form = par.busca.form;
            if (form) {
                form.addEventListener('submit', function () {
                    alinhar(par.busca, par.oculto, tipo);
                });
            }
        });
    }

    function iniciarTodos() {
        document.querySelectorAll('[data-nfse-codigos]').forEach(iniciar);
    }

    // Preenche o grupo com códigos já conhecidos (ex.: os gravados no serviço escolhido na tela "Nova NFS-e").
    // codigos: { c_trib_nac, c_trib_mun, nbs, class_trib, ind_op }; os vazios não mexem no campo.
    var CAMPOS = { servico: 'c_trib_nac', nbs: 'nbs', classificacao: 'class_trib', indicador: 'ind_op' };

    function preencher(raiz, codigos) {
        if (!raiz || !codigos) {
            return;
        }

        Object.keys(CAMPOS).forEach(function (tipo) {
            var codigo = digitos(codigos[CAMPOS[tipo]]);
            var busca = raiz.querySelector('[data-nfse-busca="' + tipo + '"]');
            var oculto = raiz.querySelector('[data-nfse-codigo="' + tipo + '"]');
            if (!busca || !oculto || codigo.length !== TAMANHOS[tipo]) {
                return;
            }

            oculto.value = codigo;
            busca.value = codigo;
            busca.setAttribute('data-rotulo', codigo);
            $.getJSON(busca.getAttribute('data-url'), { q: codigo }).done(function (lista) {
                var item = (lista || []).filter(function (i) { return i.codigo === codigo; })[0];
                if (item && oculto.value === codigo) {
                    busca.value = rotulo(item);
                    busca.setAttribute('data-rotulo', busca.value);
                }
            });
        });

        var municipal = raiz.querySelector('[name$="c_trib_mun"]');
        if (municipal && digitos(codigos.c_trib_mun).length === 3) {
            municipal.value = digitos(codigos.c_trib_mun);
        }
    }

    window.NfseCodigos = { iniciar: iniciar, iniciarTodos: iniciarTodos, preencher: preencher };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarTodos);
    } else {
        iniciarTodos();
    }
})(window, document, window.jQuery);
