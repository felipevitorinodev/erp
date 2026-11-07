(function () {
    function pad(n) { return String(n).padStart(2, '0'); }

    function iso(data) {
        return data.getFullYear() + '-' + pad(data.getMonth() + 1) + '-' + pad(data.getDate());
    }

    function parseIso(valor) {
        var p = String(valor || '').split('-');
        if (p.length !== 3) return new Date();
        return new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
    }

    function somarDias(valor, dias) {
        var data = parseIso(valor);
        data.setDate(data.getDate() + dias);
        return iso(data);
    }

    function somarMeses(valor, meses) {
        var base = parseIso(valor);
        var dia = base.getDate();
        var alvo = new Date(base.getFullYear(), base.getMonth() + meses, 1);
        var ultimo = new Date(alvo.getFullYear(), alvo.getMonth() + 1, 0).getDate();
        alvo.setDate(Math.min(dia, ultimo));
        return iso(alvo);
    }

    function formatarData(valor) {
        var p = String(valor || '').split('-');
        if (p.length !== 3) return valor || '';
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function dinheiro(valor) {
        return (Number(valor) || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function reais(valor) {
        return 'R$ ' + dinheiro(valor);
    }

    function dividir(total, quantidade) {
        var centavos = Math.round((Number(total) || 0) * 100);
        var n = Math.max(1, quantidade);
        var base = Math.floor(centavos / n);
        var extra = centavos - (base * n);
        var valores = [];
        for (var i = 0; i < n; i++) {
            valores.push((base + (i < extra ? 1 : 0)) / 100);
        }
        return valores;
    }

    function tipoForma(nome) {
        var texto = String(nome || '').toLowerCase();
        if (texto.indexOf('cheque') !== -1) return 'cheque';
        if (texto.indexOf('boleto') !== -1) return 'boleto';
        return 'prazo';
    }

    function ordinal(indice) {
        return (indice + 1) + 'ª';
    }

    function ligar(root, opcoes) {
        opcoes = opcoes || {};
        var passo = 'forma';
        var quantidade = 1;
        var total = 0;
        var dataBase = opcoes.dataBase || new Date().toISOString().slice(0, 10);
        var formaPasso = root.querySelector('[data-pg-passo-forma]');
        var parcPasso = root.querySelector('[data-pg-passo-parc]');
        var dialog = root.querySelector('[data-pg-dialog]') || root.querySelector('.pg-dialog');
        var titulo = root.querySelector('[data-pg-titulo]');
        var sub = root.querySelector('[data-pg-sub]');
        var acao = root.querySelector('[data-pg-acao]');
        var detalhe = root.querySelector('[data-pg-detalhe]');
        var voltar = root.querySelector('[data-pg-voltar]');
        var grade = root.querySelector('[data-pg-parc-grid]');
        var dataInput = root.querySelector('[data-pg-parc-data]');
        var dicaData = root.querySelector('[data-pg-parc-dica]');
        var corpo = root.querySelector('[data-pg-parc-corpo]');
        var colunaDoc = root.querySelector('[data-pg-col-doc]');
        var chip = root.querySelector('[data-pg-parc-forma]');
        var ladoTotal = root.querySelector('[data-pg-side-total]');
        var ladoN = root.querySelector('[data-pg-side-n]');
        var ladoValor = root.querySelector('[data-pg-side-valor]');
        var ladoPrimeira = root.querySelector('[data-pg-side-primeira]');
        var ladoUltima = root.querySelector('[data-pg-side-ultima]');
        var nota = root.querySelector('[data-pg-parc-nota]');

        function formaAtiva() {
            return root.querySelector('.pg-opt.is-on, .pdv-forma-btn--ativa');
        }

        function dadosForma() {
            var btn = formaAtiva();
            if (!btn) return { nome: '', prazo: false, cartao: false, dias: 30 };
            return {
                nome: btn.getAttribute('data-forma') || '',
                prazo: btn.getAttribute('data-prazo') === '1',
                cartao: btn.getAttribute('data-cartao') === '1',
                dias: parseInt(btn.getAttribute('data-dias') || '30', 10) || 30,
            };
        }

        function primeiraData(forma) {
            var dias = forma.dias > 0 ? forma.dias : 30;
            return somarDias(dataBase, dias);
        }

        function lista() {
            var forma = dadosForma();
            var inicio = dataInput && dataInput.value ? dataInput.value : primeiraData(forma);
            var valores = dividir(total, quantidade);
            return valores.map(function (valor, i) {
                return {
                    vencimento: somarMeses(inicio, i),
                    valor: valor,
                    documento: '',
                };
            });
        }

        function desenharGrade() {
            if (!grade) return;
            grade.innerHTML = '';
            for (var i = 1; i <= 12; i++) {
                (function (n) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'pg-parc-opt' + (n === quantidade ? ' is-on' : '');
                    btn.setAttribute('data-n', String(n));
                    btn.innerHTML = '<b>' + n + 'x</b><small>' + reais(dividir(total, n)[0]) + '</small>';
                    btn.addEventListener('click', function () {
                        quantidade = n;
                        desenhar();
                    });
                    grade.appendChild(btn);
                })(i);
            }
        }

        function desenharTabela(parcelas) {
            if (!corpo) return;
            var cheque = tipoForma(dadosForma().nome) === 'cheque';
            if (colunaDoc) colunaDoc.hidden = !cheque;
            corpo.innerHTML = '';
            parcelas.forEach(function (parcela, i) {
                var tr = document.createElement('tr');
                if (i === 0) tr.className = 'is-first';
                var doc = cheque
                    ? '<td><input type="text" data-pg-doc maxlength="40" aria-label="Número do cheque da ' + ordinal(i) + ' parcela"></td>'
                    : '';
                tr.innerHTML = '<td>' + ordinal(i) + ' parcela</td><td>' + formatarData(parcela.vencimento) + '</td>' + doc + '<td class="r">' + reais(parcela.valor) + '</td>';
                corpo.appendChild(tr);
            });
        }

        function textoNota(forma, parcelas) {
            var soma = reais(total);
            if (tipoForma(forma.nome) === 'cheque') {
                return 'Informe o número de cada cheque. A data de cada um é o vencimento da sua parcela.';
            }
            if (tipoForma(forma.nome) === 'boleto') {
                return 'Cada parcela gera um boleto próprio. O total fecha em ' + soma + '.';
            }
            return 'Quando a divisão não é exata, as primeiras parcelas levam os centavos extras e a última fica com o menor valor, para o total fechar em ' + soma + '.';
        }

        function textoDica(forma) {
            if (tipoForma(forma.nome) === 'cheque') return 'Os demais cheques seguem mês a mês.';
            if (tipoForma(forma.nome) === 'boleto') return 'Cada boleto vence todo mês, no mesmo dia.';
            return 'As demais parcelas vencem todo mês, no mesmo dia.';
        }

        function desenhar() {
            var forma = dadosForma();
            var parcelas = lista();
            desenharGrade();
            desenharTabela(parcelas);
            if (chip) chip.textContent = forma.nome;
            if (ladoTotal) ladoTotal.textContent = reais(total);
            if (ladoN) ladoN.textContent = quantidade + 'x';
            if (ladoValor) ladoValor.textContent = reais(parcelas[0] ? parcelas[0].valor : 0);
            if (ladoPrimeira) ladoPrimeira.textContent = formatarData(parcelas[0] ? parcelas[0].vencimento : '');
            if (ladoUltima) ladoUltima.textContent = formatarData(parcelas.length ? parcelas[parcelas.length - 1].vencimento : '');
            if (nota) nota.textContent = textoNota(forma, parcelas);
            if (dicaData) dicaData.textContent = textoDica(forma);
            var cartaoAvista = forma.cartao && quantidade < 2;
            if (acao) acao.textContent = cartaoAvista ? (opcoes.rotuloConfirmar || 'Confirmar') : 'Confirmar parcelamento';
            if (detalhe) {
                detalhe.textContent = cartaoAvista
                    ? forma.nome + ' · à vista'
                    : quantidade + 'x de ' + reais(parcelas[0] ? parcelas[0].valor : 0) + ' · ' + forma.nome;
            }
            if (voltar) voltar.textContent = 'Voltar';
        }

        function mostrarForma() {
            passo = 'forma';
            if (formaPasso) formaPasso.hidden = false;
            if (parcPasso) parcPasso.hidden = true;
            if (dialog) dialog.classList.remove('pg-dialog--parc');
            if (titulo) titulo.textContent = 'Forma de pagamento';
            if (voltar) voltar.textContent = opcoes.rotuloVoltar || 'Voltar';
            atualizarAcao();
        }

        function atualizarAcao() {
            if (passo === 'parc') {
                desenhar();
                return;
            }
            var forma = dadosForma();
            if (acao) acao.textContent = forma.prazo ? 'Continuar' : (opcoes.rotuloConfirmar || 'Confirmar');
            if (detalhe) {
                detalhe.textContent = forma.nome
                    ? (forma.nome + (forma.prazo ? ' · abre o financiamento' : ' · à vista'))
                    : 'Selecione a forma';
            }
        }

        function abrirFinanciamento(novoTotal, base) {
            var forma = dadosForma();
            if (!forma.prazo) return false;
            total = Number(novoTotal) || 0;
            if (base) dataBase = base;
            quantidade = 1;
            if (dataInput) dataInput.value = primeiraData(forma);
            passo = 'parc';
            if (formaPasso) formaPasso.hidden = true;
            if (parcPasso) parcPasso.hidden = false;
            if (dialog) dialog.classList.add('pg-dialog--parc');
            if (titulo) titulo.textContent = opcoes.rotuloFinanciamento || 'Parcelar pagamento';
            desenhar();
            return true;
        }

        function atualizarTotal(novoTotal, base) {
            total = Number(novoTotal) || 0;
            if (base) dataBase = base;
            if (sub) {
                var extra = typeof opcoes.subtitulo === 'function' ? opcoes.subtitulo() : (opcoes.subtitulo || '');
                sub.textContent = (extra ? extra + ' · ' : '') + 'Total de ' + reais(total);
            }
            if (passo === 'parc') desenhar();
            else atualizarAcao();
        }

        function parcelas() {
            var itens = lista();
            if (!corpo) return itens;
            var docs = corpo.querySelectorAll('[data-pg-doc]');
            docs.forEach(function (input, i) {
                if (itens[i]) itens[i].documento = input.value.trim();
            });
            return itens;
        }

        if (dataInput) {
            dataInput.addEventListener('change', function () {
                if (passo === 'parc') desenhar();
            });
        }

        return {
            noFinanciamento: function () { return passo === 'parc'; },
            forma: dadosForma,
            abrirFinanciamento: abrirFinanciamento,
            voltar: mostrarForma,
            atualizarTotal: atualizarTotal,
            parcelas: parcelas,
            definirQuantidade: function (n) {
                quantidade = Math.min(12, Math.max(1, n));
                if (passo === 'parc') desenhar();
            },
            cicloQuantidade: function (direcao) {
                var proxima = quantidade + (direcao < 0 ? -1 : 1);
                if (proxima < 1) proxima = 12;
                if (proxima > 12) proxima = 1;
                quantidade = proxima;
                if (passo === 'parc') desenhar();
            },
            atualizarAcao: atualizarAcao,
            reset: mostrarForma,
        };
    }

    function iniciarVenda(opcoes) {
        var root = document.getElementById(opcoes.root);
        var form = document.getElementById(opcoes.form);
        if (!root || !form) return;
        var hidden = document.getElementById(opcoes.hidden);
        var resumo = document.getElementById(opcoes.resumo);
        var campos = document.getElementById(opcoes.campos);
        var ui = ligar(root, {
            dataBase: opcoes.dataBase ? opcoes.dataBase() : new Date().toISOString().slice(0, 10),
            rotuloConfirmar: 'Usar esta forma',
            rotuloVoltar: 'Voltar',
            rotuloFinanciamento: 'Parcelar venda',
            subtitulo: opcoes.subtitulo,
        });

        function total() {
            return opcoes.total ? opcoes.total() : 0;
        }

        function base() {
            return opcoes.dataBase ? opcoes.dataBase() : new Date().toISOString().slice(0, 10);
        }

        function selecionar(btn) {
            root.querySelectorAll('.pg-opt').forEach(function (item) {
                var ativo = item === btn;
                item.classList.toggle('is-on', ativo);
                item.setAttribute('aria-pressed', ativo ? 'true' : 'false');
            });
            ui.atualizarAcao();
        }

        root.querySelectorAll('.pg-opt').forEach(function (btn) {
            btn.addEventListener('click', function () { selecionar(btn); });
        });

        function marcarConfirmada() {
            if (!opcoes.confirmarSituacao) return;
            var situacao = form.querySelector('[name="situacao"]');
            if (situacao) situacao.value = 'confirmada';
        }

        function abrir() {
            if (!root.hidden) {
                confirmar();
                return;
            }
            if (opcoes.antes && opcoes.antes() === false) return;
            if (hidden && hidden.value) {
                root.querySelectorAll('.pg-opt').forEach(function (btn) {
                    if ((btn.getAttribute('data-forma') || '') === hidden.value) selecionar(btn);
                });
            }
            ui.reset();
            ui.atualizarTotal(total(), base());
            root.hidden = false;
        }

        function fechar() {
            ui.reset();
            root.hidden = true;
        }

        function parcelasEscolhidas() {
            var forma = ui.forma();
            if (!forma.prazo) return [];
            var lista = ui.parcelas();
            if (forma.cartao && lista.length < 2) return [];
            return lista;
        }

        function gravar(parcelas) {
            var forma = ui.forma();
            if (hidden) {
                hidden.value = forma.nome;
                hidden.dataset.definido = '1';
            }
            if (campos) {
                campos.innerHTML = '';
                (parcelas || []).forEach(function (parcela, i) {
                    campos.insertAdjacentHTML('beforeend',
                        '<input type="hidden" name="parcelas[' + i + '][vencimento]" value="' + parcela.vencimento + '">' +
                        '<input type="hidden" name="parcelas[' + i + '][valor]" value="' + parcela.valor.toFixed(2) + '">' +
                        '<input type="hidden" name="parcelas[' + i + '][documento]" value="' + String(parcela.documento || '').replace(/"/g, '&quot;') + '">');
                });
            }
            if (resumo) {
                resumo.textContent = parcelas && parcelas.length
                    ? forma.nome + ' · ' + parcelas.length + 'x de ' + reais(parcelas[0].valor)
                    : forma.nome + ' · à vista';
            }
        }

        function confirmar() {
            var forma = ui.forma();
            if (!forma.nome) return;
            if (forma.prazo && !ui.noFinanciamento()) {
                ui.abrirFinanciamento(total(), base());
                return;
            }
            gravar(parcelasEscolhidas());
            marcarConfirmada();
            fechar();
            if (opcoes.enviar) form.requestSubmit();
        }

        var gatilho = document.getElementById(opcoes.abrir);
        if (gatilho) gatilho.addEventListener('click', abrir);
        var confirmarBtn = root.querySelector('[data-pg-confirmar]');
        if (confirmarBtn) confirmarBtn.addEventListener('click', confirmar);
        root.querySelectorAll('[data-pg-fechar]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (ui.noFinanciamento()) ui.voltar();
                else fechar();
            });
        });
        root.addEventListener('click', function (e) {
            if (e.target === root) fechar();
        });
        root.addEventListener('keydown', function (e) {
            if (root.hidden) return;
            var digitando = e.target && /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName || '');
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                if (ui.noFinanciamento()) ui.voltar();
                else fechar();
                return;
            }
            if (e.key === 'Enter' && !digitando) {
                e.preventDefault();
                e.stopPropagation();
                confirmar();
                return;
            }
            if (!digitando && ui.noFinanciamento()) {
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); ui.cicloQuantidade(1); }
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); ui.cicloQuantidade(-1); }
                if (/^[1-9]$/.test(e.key)) { e.preventDefault(); ui.definirQuantidade(parseInt(e.key, 10)); }
                return;
            }
            if (!digitando) {
                var opcoesForma = root.querySelectorAll('.pg-opt');
                if ((e.key === 'ArrowRight' || e.key === 'ArrowDown' || e.key === 'ArrowLeft' || e.key === 'ArrowUp') && opcoesForma.length) {
                    e.preventDefault();
                    var atual = -1;
                    opcoesForma.forEach(function (item, i) { if (item.classList.contains('is-on')) atual = i; });
                    var passoTecla = (e.key === 'ArrowLeft' || e.key === 'ArrowUp') ? -1 : 1;
                    var proxima = atual + passoTecla;
                    if (proxima < 0) proxima = opcoesForma.length - 1;
                    if (proxima >= opcoesForma.length) proxima = 0;
                    selecionar(opcoesForma[proxima]);
                }
                if (/^[1-9]$/.test(e.key)) {
                    var escolhida = opcoesForma[parseInt(e.key, 10) - 1];
                    if (escolhida) { e.preventDefault(); selecionar(escolhida); }
                }
            }
        });

        if (!opcoes.enviar) {
            form.addEventListener('submit', function (e) {
                var nome = hidden ? hidden.value : '';
                if (!nome) return;
                var btn = null;
                root.querySelectorAll('.pg-opt').forEach(function (item) {
                    if ((item.getAttribute('data-forma') || '') === nome) btn = item;
                });
                var prazo = btn && btn.getAttribute('data-prazo') === '1';
                var cartaoAvista = btn && btn.getAttribute('data-cartao') === '1' && hidden && hidden.dataset.definido === '1';
                var temParcelas = campos && campos.querySelector('input');
                if (prazo && !temParcelas && !cartaoAvista) {
                    e.preventDefault();
                    selecionar(btn);
                    ui.reset();
                    ui.atualizarTotal(total(), base());
                    root.hidden = false;
                    ui.abrirFinanciamento(total(), base());
                }
            });
        }

        return { abrir: abrir, fechar: fechar };
    }

    window.PagamentoModal = {
        ligar: ligar,
        iniciarVenda: iniciarVenda,
        reais: reais,
        dinheiro: dinheiro,
    };
})();
