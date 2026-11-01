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

        $mostraVendas = $podeVendas || $podeRelatorios;
        $temAtencao = $podeRelatorios && ($contasReceberVencidasVal > 0 || $contasPagarVencidasVal > 0 || $estoqueCriticoVal > 0);
        $temAcoes = $podeVendas || $podeEstoque || $podeFinanceiro;
        $temConsultas = $podeVendas || $podeOrcamentos || $podeProdutos || $podeClientes || $podeFornecedores || $podeFinanceiro || $mostraRelatorios;
        $moeda = fn ($valor) => 'R$ ' . number_format((float) $valor, 2, ',', '.');
        $nomeMes = $meses[now()->month];
        $faturamentoMes = $faturamentoMes ?? null;
        $contasMes = $contasMes ?? null;
        $mostraGraficoVendas = $mostraVendas && is_array($faturamentoMes);
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
        <section class="home-resumo">
            <div class="home-resumo__ola">
                <p class="home-resumo__hello">Olá, <strong>{{ Auth::user()->name }}</strong></p>
                <p class="home-resumo__meta">{{ $empresaHomeNome !== '' ? $empresaHomeNome.' · ' : '' }}{{ $dataHoje }}</p>
            </div>

            @if ($mostraVendas || $podeFinanceiro)
                <div class="home-numeros">
                    @if ($mostraVendas)
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

        @if ($temAtencao)
            <section class="home-painel">
                <h2 class="home-section-label">Atenção</h2>
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
                        <h2 class="home-section-label">O que você quer fazer?</h2>
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
                                        <span class="home-acao__titulo">Nova Venda</span>
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
                                        <span class="home-acao__titulo">Entrada de Mercadoria</span>
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
                            <p class="home-grafico__vazio">Nenhuma conta com vencimento em {{ $nomeMes }}.</p>
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
                            A Receber
                        </a>
                        <a href="{{ route('conta-pagar.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                            A Pagar
                        </a>
                        <a href="{{ route('fluxo-de-caixa.index') }}" class="home-consulta">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline></svg>
                            Fluxo de Caixa
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

@endsection

@push('styles')
<style>
.home {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.home-resumo {
    display: grid;
    grid-template-columns: minmax(160px, 220px) minmax(0, 1fr);
    gap: 1rem 1.5rem;
    align-items: center;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 1rem 1.15rem;
}

.home-resumo__hello {
    font-size: 1.2rem;
    line-height: 1.25;
    color: var(--color-text);
}

.home-resumo__meta {
    margin-top: 0.25rem;
    font-size: 13px;
    color: var(--color-text-mid);
}

.home-numeros {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.65rem;
}

.home-numero {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
    min-width: 0;
    padding: 0.7rem 0.8rem;
    border-radius: var(--radius-md);
    background: var(--color-bg);
    color: inherit;
    text-decoration: none !important;
}

.home-numero:hover,
.home-numero:focus-visible {
    background: var(--color-primary-soft);
    text-decoration: none !important;
}

.home-numero__rotulo {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-text-muted);
}

.home-numero__valor {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.15;
    letter-spacing: -0.02em;
    color: var(--color-text);
    overflow-wrap: anywhere;
}

.home-painel {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: 0.95rem 1rem 1rem;
    min-width: 0;
}

.home-painel .home-section-label {
    margin: 0 0 0.75rem;
}

.home-acoes {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.65rem;
}

.home-acao,
.home-pendencia,
.home-consulta {
    text-decoration: none !important;
    color: inherit;
}

.home-acao {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 72px;
    padding: 0.7rem 0.8rem;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-bg);
}

.home-acao:hover,
.home-acao:focus-visible {
    background: #fff;
    border-color: var(--color-primary);
    box-shadow: var(--shadow-sm);
}

.home-acao__icone {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 12px;
    flex-shrink: 0;
}

.home-acao__icone svg {
    width: 20px;
    height: 20px;
}

.home-acao__icone--venda {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.home-acao__icone--estoque {
    background: var(--color-success-bg);
    color: var(--color-success);
}

.home-acao__icone--receber {
    background: #e8f1fb;
    color: #1d4f91;
}

.home-acao__icone--pagar {
    background: var(--color-accent-soft);
    color: var(--color-accent);
}

.home-acao__texto {
    display: flex;
    flex-direction: column;
    gap: 0.1rem;
    min-width: 0;
}

.home-acao__titulo {
    font-size: 14px;
    font-weight: 600;
    color: var(--color-text);
}

.home-acao__desc {
    font-size: 12px;
    color: var(--color-text-muted);
    line-height: 1.35;
}

.home-pendencias {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 0.5rem;
}

.home-pendencia {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 0.85rem;
    border-radius: var(--radius-md);
    border: 1px solid transparent;
}

.home-pendencia:hover,
.home-pendencia:focus-visible {
    filter: brightness(0.98);
}

.home-pendencia--aviso {
    background: #fffbeb;
    border-color: #f6e3a1;
    color: #7a5200;
}

.home-pendencia--pagar {
    background: #fef2f2;
    border-color: #f5c2c2;
    color: #8b1a1a;
}

.home-pendencia__n {
    font-size: 1.45rem;
    font-weight: 700;
    line-height: 1;
    min-width: 1.5rem;
}

.home-pendencia__txt {
    font-size: 13px;
    font-weight: 600;
    line-height: 1.3;
}

.home-consultas {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
    gap: 0.5rem;
}

.home-consulta {
    display: flex;
    align-items: center;
    gap: 0.45rem;
    min-height: 40px;
    padding: 0.45rem 0.65rem;
    border-radius: var(--radius-sm);
    background: var(--color-bg);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
}

.home-consulta svg {
    width: 16px;
    height: 16px;
    flex-shrink: 0;
    color: var(--color-text-muted);
}

.home-consulta:hover,
.home-consulta:focus-visible {
    background: var(--color-primary-soft);
    color: var(--color-primary);
}

.home-consulta:hover svg,
.home-consulta:focus-visible svg {
    color: var(--color-primary);
}

.home-acao:focus-visible,
.home-numero:focus-visible,
.home-pendencia:focus-visible,
.home-consulta:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

.home-graficos {
    display: grid;
    grid-template-columns: minmax(0, 1fr);
    gap: 1rem;
    align-items: stretch;
}

.home-graficos--dois {
    grid-template-columns: minmax(0, 1.45fr) minmax(280px, 0.8fr);
}

.home-grafico__topo {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
}

.home-grafico__topo .home-section-label {
    margin-bottom: 0;
}

.home-grafico__link {
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.home-grafico__resumo {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: 0.35rem 0.85rem;
    margin: 0.7rem 0 0.85rem;
    color: var(--color-text-mid);
    font-size: 13px;
}

.home-grafico__resumo strong {
    font-size: 1.35rem;
    color: var(--color-text);
    letter-spacing: -0.02em;
}

.home-grafico__nota,
.home-grafico__vazio,
.home-grafico__legenda {
    margin: 0.45rem 0 0;
    font-size: 12px;
    color: var(--color-text-muted);
}

.home-grafico__legenda {
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.home-grafico__marca {
    width: 10px;
    height: 10px;
    border-radius: 3px;
    background: var(--color-primary-lt);
    margin-left: 0.45rem;
}

.home-grafico__marca:first-child {
    margin-left: 0;
}

.home-grafico__marca--hoje {
    background: var(--color-accent);
}

.home-barras {
    display: flex;
    align-items: stretch;
    gap: 3px;
    height: 148px;
    margin-top: 0.25rem;
}

.home-barra {
    position: relative;
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.home-barra:hover,
.home-barra:focus-within {
    z-index: 4;
}

.home-barra__trilho {
    flex: 1;
    width: 100%;
    display: flex;
    align-items: flex-end;
    justify-content: center;
}

.home-barra__col {
    position: relative;
    width: 68%;
    max-width: 16px;
    border-radius: 3px 3px 1px 1px;
    background: var(--color-primary-lt);
}

.home-barra:hover .home-barra__col,
.home-barra:focus-within .home-barra__col {
    filter: brightness(1.08);
}

.home-barra__dica {
    position: absolute;
    left: 50%;
    bottom: calc(100% + 8px);
    z-index: 8;
    display: flex;
    flex-direction: column;
    gap: 0.12rem;
    min-width: 148px;
    padding: 0.5rem 0.65rem 0.55rem;
    border-radius: 8px;
    background: var(--color-primary);
    color: #fff;
    box-shadow: 0 8px 20px rgba(22, 58, 95, 0.28);
    opacity: 0;
    visibility: hidden;
    transform: translateX(-50%) translateY(4px);
    transition: opacity 0.12s ease, transform 0.12s ease, visibility 0.12s ease;
    pointer-events: none;
    white-space: nowrap;
}

.home-barra__dica::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: var(--color-primary);
}

.home-barra:hover .home-barra__dica,
.home-barra:focus-within .home-barra__dica {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

.home-barras .home-barra:nth-child(-n+3) .home-barra__dica {
    left: 0;
    transform: translateY(4px);
}

.home-barras .home-barra:nth-child(-n+3):hover .home-barra__dica,
.home-barras .home-barra:nth-child(-n+3):focus-within .home-barra__dica {
    transform: translateY(0);
}

.home-barras .home-barra:nth-child(-n+3) .home-barra__dica::after {
    left: 10px;
    transform: none;
}

.home-barras .home-barra:nth-last-child(-n+3) .home-barra__dica {
    left: auto;
    right: 0;
    transform: translateY(4px);
}

.home-barras .home-barra:nth-last-child(-n+3):hover .home-barra__dica,
.home-barras .home-barra:nth-last-child(-n+3):focus-within .home-barra__dica {
    transform: translateY(0);
}

.home-barras .home-barra:nth-last-child(-n+3) .home-barra__dica::after {
    left: auto;
    right: 6px;
    transform: none;
}

.home-barra__dica-data {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.01em;
}

.home-barra__dica-tag {
    align-self: flex-start;
    margin-top: 0.1rem;
    padding: 0.05rem 0.35rem;
    border-radius: 999px;
    background: var(--color-accent);
    font-size: 10px;
    font-weight: 700;
    line-height: 1.4;
}

.home-barra__dica-valor {
    margin-top: 0.15rem;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.2;
}

.home-barra__dica-nota {
    font-size: 11px;
    font-weight: 500;
    opacity: 0.88;
}

.home-barra.is-hoje .home-barra__col {
    background: var(--color-accent);
}

.home-barra.is-vazio .home-barra__col,
.home-barra.is-futuro .home-barra__col {
    height: 2px !important;
    background: var(--color-border-mid);
}

.home-barra.is-futuro .home-barra__col {
    opacity: 0.45;
}

.home-barra__dia {
    height: 14px;
    font-size: 10px;
    line-height: 14px;
    color: var(--color-text-muted);
}

.home-barra.is-hoje .home-barra__dia {
    color: var(--color-accent);
    font-weight: 700;
}

.home-contas {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    margin-top: 0.85rem;
}

.home-contas__titulo {
    margin: 0 0 0.4rem;
    font-size: 13px;
    font-weight: 700;
    color: var(--color-text);
}

.home-contas__linha {
    display: grid;
    grid-template-columns: 78px minmax(0, 1fr) auto;
    align-items: center;
    gap: 0.5rem;
    margin-top: 0.35rem;
    font-size: 12px;
    color: var(--color-text-mid);
}

.home-contas__linha strong {
    font-size: 12px;
    color: var(--color-text);
    white-space: nowrap;
}

.home-contas__trilho {
    height: 8px;
    border-radius: 99px;
    background: var(--color-bg);
    overflow: hidden;
}

.home-contas__col {
    display: block;
    height: 100%;
    border-radius: 99px;
    min-width: 0;
}

.home-contas__col--receber.home-contas__col--aberto { background: #1d4f91; }
.home-contas__col--receber.home-contas__col--pago { background: var(--color-success); }
.home-contas__col--pagar.home-contas__col--aberto { background: var(--color-accent); }
.home-contas__col--pagar.home-contas__col--pago { background: var(--color-success); }

@media (max-width: 980px) {
    .home-graficos--dois {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 820px) {
    .home-resumo,
    .home-numeros,
    .home-acoes,
    .home-pendencias {
        grid-template-columns: 1fr;
    }

    .home-contas__linha {
        grid-template-columns: 72px minmax(0, 1fr);
    }

    .home-contas__linha strong {
        grid-column: 2;
    }
}
</style>
@endpush
