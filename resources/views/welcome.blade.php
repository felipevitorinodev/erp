@extends('layouts.app')

@section('title', 'Início')
@section('page_title', 'Início')

@section('content')

    @php
        $vendasHojeVal = $vendasHoje ?? 0;
        $contasReceberVencidasVal = $contasReceberVencidas ?? 0;
        $contasPagarVencidasVal = $contasPagarVencidas ?? 0;
        $estoqueCriticoVal = $estoqueCritico ?? 0;
        $podeFinanceiro = auth()->user()->pode('financeiro');
        $podeEstoque = auth()->user()->pode('estoque');
        $podeRelatorios = auth()->user()->pode('relatorios');
        $mostraRelatorios = \App\Support\CatalogoRelatorios::temAlgum(auth()->user());
        $podeOrcamentos = auth()->user()->pode('orcamentos');
        $podeProdutos = auth()->user()->pode('produtos');
        $podeClientes = auth()->user()->pode('clientes');
        $podeFornecedores = auth()->user()->pode('fornecedores');
        $podeVendas = auth()->user()->pode('vendas');

        $faturamentoTexto = $faturamentoHoje ?? 'R$ 0,00';
        if (is_numeric($faturamentoTexto)) {
            $faturamentoTexto = 'R$ ' . number_format((float) $faturamentoTexto, 2, ',', '.');
        }

        $aReceberTexto = $aReceberProximos7Dias ?? ($aReceber7Dias ?? 'R$ 0,00');
        if (is_numeric($aReceberTexto)) {
            $aReceberTexto = 'R$ ' . number_format((float) $aReceberTexto, 2, ',', '.');
        }

        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $dataHoje = now()->day.' de '.$meses[now()->month].' de '.now()->year;
        $empresaHome = Auth::user()->empresa;
        $empresaHomeNome = trim((string) ($empresaHome->nome_fantasia ?? ''))
            ?: trim((string) ($empresaHome->razao_social ?? ''));

        $hora = (int) now()->format('G');
        // Madrugada (0h às 4h59) ainda é "boa noite".
        [$periodo, $saudacao] = match (true) {
            $hora >= 5 && $hora < 12 => ['manha', 'Bom dia'],
            $hora >= 12 && $hora < 18 => ['tarde', 'Boa tarde'],
            default => ['noite', 'Boa noite'],
        };
        $primeiroNome = \Illuminate\Support\Str::of(Auth::user()->name)->before(' ');
        $podeComandos = auth()->user()->pode('comandos');
        // Exemplo da barra do assistente: o que mais mostra o assistente em ação, entre as tarefas que o perfil pode fazer.
        $exemploComando = $podeComandos ? app(\App\Comandos\RegistroComandos::class)->exemploPrincipal(auth()->user()) : null;
        $primeirosPassos = \App\Support\PrimeirosPassos::para(auth()->user());
        $passosFeitos = count(array_filter($primeirosPassos, fn ($p) => $p['feito']));

        $mostraFaturamento = $podeRelatorios;
        $temAtencao = $podeRelatorios && ($contasReceberVencidasVal > 0 || $contasPagarVencidasVal > 0 || $estoqueCriticoVal > 0);
        $temAcoes = $podeVendas || $podeEstoque || $podeFinanceiro;
        $temConsultas = $podeVendas || $podeOrcamentos || $podeProdutos || $podeClientes || $podeFornecedores || $podeFinanceiro || $mostraRelatorios;
        $moeda = fn ($valor) => 'R$ ' . number_format((float) $valor, 2, ',', '.');
        $nomeMes = $meses[now()->month];
        $faturamentoMes = $faturamentoMes ?? null;
        $contasMes = $contasMes ?? null;
        $mostraGraficoVendas = $mostraFaturamento && is_array($faturamentoMes);
        $mostraGraficoContas = $podeFinanceiro && is_array($contasMes);
        $tetoContas = 0.0;
        if ($mostraGraficoContas) {
            $tetoContas = max(
                $contasMes['receber']['pago'],
                $contasMes['receber']['aberto'],
                $contasMes['pagar']['pago'],
                $contasMes['pagar']['aberto']
            );
        }
    @endphp

    <div class="home">
        <section class="home-resumo" data-ilustracao-area>
            <div class="home-resumo__topo">
                <div class="home-resumo__ola">
                    <p class="home-resumo__hello">{{ $saudacao }}, <strong>{{ $primeiroNome }}</strong></p>
                    <p class="home-resumo__meta">{{ $empresaHomeNome !== '' ? $empresaHomeNome.' · ' : '' }}{{ $dataHoje }}</p>
                    @if ($podeComandos && $exemploComando)
                        <button type="button" class="home-comando" data-comandos-abrir aria-controls="comandos-painel" aria-expanded="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-4.9A8 8 0 1 1 21 12z"/>
                                <path d="M8.5 12h.01M12 12h.01M15.5 12h.01" stroke-width="2.6"/>
                            </svg>
                            <span class="home-comando__texto">
                                <span class="home-comando__titulo">Peça ao assistente</span>
                                <span class="home-comando__exemplo">Ex.: “{{ $exemploComando }}”</span>
                            </span>
                            <kbd class="home-comando__atalho">Ctrl K</kbd>
                        </button>
                    @endif
                </div>
                <x-ilustracao :nome="$periodo" tamanho="md" interativa class="home-resumo__arte" />
            </div>

            @if ($mostraFaturamento || $podeFinanceiro)
                <div class="home-numeros">
                    @if ($mostraFaturamento)
                        <a href="{{ $podeVendas ? route('venda.index', ['data_inicio' => date('Y-m-d'), 'data_fim' => date('Y-m-d')]) : route('relatorio.vendas', ['data_inicio' => date('Y-m-d'), 'data_fim' => date('Y-m-d')]) }}" class="home-numero">
                            <span class="home-numero__rotulo">Vendas hoje</span>
                            <span class="home-numero__valor">{{ $vendasHojeVal }}</span>
                        </a>
                        <a href="{{ $podeRelatorios ? route('relatorio.vendas', ['data_inicio' => date('Y-m-d'), 'data_fim' => date('Y-m-d')]) : route('venda.index', ['data_inicio' => date('Y-m-d'), 'data_fim' => date('Y-m-d')]) }}" class="home-numero">
                            <span class="home-numero__rotulo">Faturado hoje</span>
                            <span class="home-numero__valor">{{ $faturamentoTexto }}</span>
                        </a>
                    @endif
                    @if ($podeFinanceiro)
                        <a href="{{ route('relatorio.contas-receber', [
                            'data_inicio' => date('Y-m-d'),
                            'data_fim' => date('Y-m-d', strtotime('+7 days')),
                            'situacao' => 'em_aberto',
                        ]) }}" class="home-numero">
                            <span class="home-numero__rotulo">A receber (7 dias)</span>
                            <span class="home-numero__valor">{{ $aReceberTexto }}</span>
                        </a>
                    @endif
                </div>
            @endif
        </section>

        @if ($primeirosPassos !== [])
            <section class="home-painel home-passos" aria-labelledby="home-passos-titulo">
                <div class="home-passos__topo">
                    <x-ilustracao nome="prancheta" tamanho="xs" />
                    <div>
                        <h2 class="home-section-label" id="home-passos-titulo">Primeiros passos</h2>
                        <p class="home-passos__resumo">{{ $passosFeitos }} de {{ count($primeirosPassos) }} concluídos</p>
                    </div>
                    <span class="home-passos__barra" aria-hidden="true"><span style="width: {{ round($passosFeitos / count($primeirosPassos) * 100) }}%"></span></span>
                </div>
                <ol class="home-passos__lista">
                    @foreach ($primeirosPassos as $passo)
                        <li class="home-passo {{ $passo['feito'] ? 'is-feito' : '' }}">
                            <span class="home-passo__marca" aria-hidden="true">
                                @if ($passo['feito'])
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-10"/></svg>
                                @else
                                    {{ $loop->iteration }}
                                @endif
                            </span>
                            <span class="home-passo__texto">
                                <span class="home-passo__titulo">{{ $passo['titulo'] }}</span>
                                <span class="home-passo__desc">{{ $passo['feito'] ? 'Feito.' : $passo['texto'] }}</span>
                            </span>
                            @unless ($passo['feito'])
                                <a href="{{ $passo['url'] }}" class="btn btn--outline btn--sm">{{ $passo['acao'] }}</a>
                            @endunless
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        @if ($podeRelatorios && ! $temAtencao)
            <section class="home-painel home-tudo-em-dia" data-ilustracao-area>
                <x-ilustracao nome="tudo-em-dia" tamanho="xs" interativa />
                <div>
                    <h2 class="home-section-label">Tudo em dia</h2>
                    <p class="home-tudo-em-dia__texto">Nenhuma conta vencida e nenhum produto com estoque baixo.</p>
                </div>
            </section>
        @endif

        @if ($temAtencao)
            <section class="home-painel">
                <h2 class="home-section-label">Pendências</h2>
                <div class="home-pendencias">
                    @if ($contasReceberVencidasVal > 0)
                        <a href="{{ route('relatorio.contas-receber', ['vencimento' => 'vencidos', 'data_inicio' => '2000-01-01', 'data_fim' => date('Y-m-d')]) }}" class="home-pendencia home-pendencia--aviso">
                            <span class="home-pendencia__n">{{ $contasReceberVencidasVal }}</span>
                            <span class="home-pendencia__txt">contas a receber vencidas</span>
                        </a>
                    @endif
                    @if ($contasPagarVencidasVal > 0)
                        <a href="{{ route('relatorio.contas-pagar', ['vencimento' => 'vencidos', 'data_inicio' => '2000-01-01', 'data_fim' => date('Y-m-d')]) }}" class="home-pendencia home-pendencia--pagar">
                            <span class="home-pendencia__n">{{ $contasPagarVencidasVal }}</span>
                            <span class="home-pendencia__txt">contas a pagar vencidas</span>
                        </a>
                    @endif
                    @if ($estoqueCriticoVal > 0)
                        <a href="{{ route('relatorio.estoque', ['apenas_critico' => 1]) }}" class="home-pendencia home-pendencia--aviso">
                            <span class="home-pendencia__n">{{ $estoqueCriticoVal }}</span>
                            <span class="home-pendencia__txt">produtos com estoque baixo</span>
                        </a>
                    @endif
                </div>
            </section>
        @endif

        @if ($temAcoes)
                    <section class="home-painel">
                        <h2 class="home-section-label">Atalhos</h2>
                        <div class="home-acoes">
                            @if ($podeVendas)
                                <a href="{{ route('pdv.index') }}" class="home-acao">
                                    <span class="home-acao__icone home-acao__icone--venda">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 9-12h-7l1-8z"></path></svg>
                                    </span>
                                    <span class="home-acao__texto">
                                        <span class="home-acao__titulo">PDV</span>
                                        <span class="home-acao__desc">Venda rápida no balcão</span>
                                    </span>
                                </a>
                                <a href="{{ route('venda.create') }}" class="home-acao">
                                    <span class="home-acao__icone home-acao__icone--venda">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                    </span>
                                    <span class="home-acao__texto">
                                        <span class="home-acao__titulo">Nova venda</span>
                                        <span class="home-acao__desc">Registrar uma venda</span>
                                    </span>
                                </a>
                            @endif

                            @if ($podeEstoque)
                                <a href="{{ route('entrada-estoque.index') }}" class="home-acao">
                                    <span class="home-acao__icone home-acao__icone--estoque">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                    </span>
                                    <span class="home-acao__texto">
                                        <span class="home-acao__titulo">Entrada de mercadoria</span>
                                        <span class="home-acao__desc">Registrar compra / NF-e</span>
                                    </span>
                                </a>
                            @endif

                            @if ($podeFinanceiro)
                                <a href="{{ route('conta-receber.create') }}" class="home-acao">
                                    <span class="home-acao__icone home-acao__icone--receber">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                                    </span>
                                    <span class="home-acao__texto">
                                        <span class="home-acao__titulo">Receber</span>
                                        <span class="home-acao__desc">Lançar conta a receber</span>
                                    </span>
                                </a>
                                <a href="{{ route('conta-pagar.create') }}" class="home-acao">
                                    <span class="home-acao__icone home-acao__icone--pagar">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                                    </span>
                                    <span class="home-acao__texto">
                                        <span class="home-acao__titulo">Pagar</span>
                                        <span class="home-acao__desc">Lançar conta a pagar</span>
                                    </span>
                                </a>
                            @endif
                        </div>
                    </section>
        @endif

        @if ($mostraGraficoVendas || $mostraGraficoContas)
            <div class="home-graficos {{ $mostraGraficoVendas && $mostraGraficoContas ? 'home-graficos--dois' : '' }}">
                @if ($mostraGraficoVendas)
                    <section class="home-painel home-grafico">
                        <div class="home-grafico__topo">
                            <h2 class="home-section-label">Faturamento de {{ $nomeMes }}</h2>
                            <a href="{{ $podeRelatorios ? route('relatorio.vendas', ['data_inicio' => $faturamentoMes['inicio'], 'data_fim' => $faturamentoMes['fim'], 'situacao' => 'confirmada']) : route('venda.index', ['data_inicio' => $faturamentoMes['inicio'], 'data_fim' => $faturamentoMes['fim']]) }}" class="home-grafico__link">Ver vendas</a>
                        </div>
                        <p class="home-grafico__resumo">
                            <strong>{{ $moeda($faturamentoMes['total']) }}</strong>
                            <span>{{ $faturamentoMes['qtd'] }} {{ $faturamentoMes['qtd'] === 1 ? 'venda confirmada' : 'vendas confirmadas' }}</span>
                            @if ($faturamentoMes['qtd'] > 0)
                                <span>ticket médio {{ $moeda($faturamentoMes['ticket']) }}</span>
                            @endif
                        </p>
                        <div class="home-barras" role="img" aria-label="Faturamento confirmado em {{ $nomeMes }}: {{ $moeda($faturamentoMes['total']) }}">
                            @foreach ($faturamentoMes['dias'] as $dia)
                                @php
                                    $dataDia = now()->copy()->day((int) $dia['n']);
                                    $semana = ['domingo', 'segunda', 'terça', 'quarta', 'quinta', 'sexta', 'sábado'][$dataDia->dayOfWeek];
                                    $quando = $dataDia->format('d/m');
                                @endphp
                                <div class="home-barra {{ $dia['hoje'] ? 'is-hoje' : '' }} {{ $dia['futuro'] ? 'is-futuro' : '' }} {{ $dia['total'] <= 0 ? 'is-vazio' : '' }}">
                                    <span class="home-barra__trilho">
                                        <span class="home-barra__col" style="height: {{ $dia['futuro'] || $dia['total'] <= 0 ? 0 : max($dia['altura'], 4) }}%">
                                            <span class="home-barra__dica">
                                                <span class="home-barra__dica-data">{{ ucfirst($semana) }}, {{ $quando }}</span>
                                                @if ($dia['hoje'])
                                                    <span class="home-barra__dica-tag">Hoje</span>
                                                @endif
                                                @if ($dia['futuro'])
                                                    <span class="home-barra__dica-nota">Este dia ainda não chegou</span>
                                                @elseif ((int) $dia['qtd'] === 0)
                                                    <span class="home-barra__dica-nota">Sem venda confirmada</span>
                                                @else
                                                    <strong class="home-barra__dica-valor">{{ $moeda($dia['total']) }}</strong>
                                                    <span class="home-barra__dica-nota">{{ $dia['qtd'] }} {{ (int) $dia['qtd'] === 1 ? 'venda confirmada' : 'vendas confirmadas' }}</span>
                                                @endif
                                            </span>
                                        </span>
                                    </span>
                                    <span class="home-barra__dia">{{ $dia['eixo'] ? $dia['n'] : '' }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="home-grafico__legenda"><span class="home-grafico__marca"></span> dia <span class="home-grafico__marca home-grafico__marca--hoje"></span> hoje</p>
                    </section>
                @endif

                @if ($mostraGraficoContas)
                    <section class="home-painel home-grafico">
                        <div class="home-grafico__topo">
                            <h2 class="home-section-label">Contas de {{ $nomeMes }}</h2>
                            <a href="{{ route('fluxo-de-caixa.index', ['data_inicio' => $contasMes['inicio'], 'data_fim' => $contasMes['fim']]) }}" class="home-grafico__link">Fluxo de caixa</a>
                        </div>
                        <p class="home-grafico__nota">Vencimento neste mês, sem contas canceladas.</p>
                        @if ($tetoContas <= 0)
                            <x-estado-vazio ilustracao="calendario" tamanho="xs"
                                titulo="Nenhuma conta com vencimento em {{ $nomeMes }}"
                                texto="Os lançamentos do mês aparecem aqui assim que forem feitos." />
                        @else
                            <div class="home-contas">
                                @foreach ([
                                    'receber' => ['titulo' => 'A receber', 'pago' => 'Recebido', 'tom' => 'receber'],
                                    'pagar' => ['titulo' => 'A pagar', 'pago' => 'Pago', 'tom' => 'pagar'],
                                ] as $chave => $rotulo)
                                    <div class="home-contas__grupo">
                                        <p class="home-contas__titulo">{{ $rotulo['titulo'] }}</p>
                                        @foreach (['aberto' => 'Em aberto', 'pago' => $rotulo['pago']] as $campo => $nome)
                                            @php $largura = round($contasMes[$chave][$campo] / $tetoContas * 100, 1); @endphp
                                            <div class="home-contas__linha">
                                                <span class="home-contas__nome">{{ $nome }}</span>
                                                <span class="home-contas__trilho">
                                                    <span class="home-contas__col home-contas__col--{{ $rotulo['tom'] }} home-contas__col--{{ $campo }}" style="width: {{ $largura }}%"></span>
                                                </span>
                                                <strong>{{ $moeda($contasMes[$chave][$campo]) }}</strong>
                                            </div>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </section>
                @endif
            </div>
        @endif

        @if ($temConsultas)
            <section class="home-painel">
                <h2 class="home-section-label">Consultas</h2>
                <div class="home-consultas">
                    @if ($podeVendas)
                        <a href="{{ route('venda.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                            Vendas
                        </a>
                    @endif
                    @if ($podeOrcamentos)
                        <a href="{{ route('orcamento.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="2" width="14" height="20"></rect><path d="M9 12l2 2 4-4"></path></svg>
                            Orçamentos
                        </a>
                    @endif
                    @if ($podeProdutos)
                        <a href="{{ route('produto.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
                            Produtos
                        </a>
                    @endif
                    @if ($podeClientes)
                        <a href="{{ route('cliente.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"></path></svg>
                            Clientes
                        </a>
                    @endif
                    @if ($podeFornecedores)
                        <a href="{{ route('fornecedor.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9h18M3 15h18M8 3v18M16 3v18"></path></svg>
                            Fornecedores
                        </a>
                    @endif
                    @if ($podeFinanceiro)
                        <a href="{{ route('conta-receber.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                            A receber
                        </a>
                        <a href="{{ route('conta-pagar.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            A pagar
                        </a>
                        <a href="{{ route('fluxo-de-caixa.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline></svg>
                            Fluxo de caixa
                        </a>
                    @endif
                    @if ($mostraRelatorios)
                        <a href="{{ route('relatorios.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="6" y1="20" x2="6" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="18" y1="20" x2="18" y2="14"></line></svg>
                            Relatórios
                        </a>
                    @endif
                </div>
            </section>
        @endif
    </div>

    <x-comandos />

@endsection

@pushOnce('styles', 'css-inicio')
    <link rel="stylesheet" href="{{ asset('css/inicio.css') }}?v={{ filemtime(public_path('css/inicio.css')) }}">
@endPushOnce
