@extends('layouts.app')

@section('title', 'Início')
@section('page_title', 'Início')

@section('content')

    @php
        $vendasHojeVal = $vendasHoje ?? 0;
        $contasReceberVencidasVal = $contasReceberVencidas ?? 0;
        $contasPagarVencidasVal = $contasPagarVencidas ?? 0;
        $estoqueCriticoVal = $estoqueCritico ?? 0;
        $podeFinanceiro = in_array(auth()->user()->perfil ?? '', ['admin', 'financeiro'], true);
        $podeAdmin = (auth()->user()->perfil ?? '') === 'admin';
        $podeOperacional = in_array(auth()->user()->perfil ?? '', ['admin', 'operador'], true);

        $faturamentoTexto = $faturamentoHoje ?? 'R$ 0,00';
        if (is_numeric($faturamentoTexto)) {
            $faturamentoTexto = 'R$ ' . number_format((float) $faturamentoTexto, 2, ',', '.');
        }

        $aReceberTexto = $aReceberProximos7Dias ?? ($aReceber7Dias ?? 'R$ 0,00');
        if (is_numeric($aReceberTexto)) {
            $aReceberTexto = 'R$ ' . number_format((float) $aReceberTexto, 2, ',', '.');
        }
    @endphp

    <div class="home-hero">
        <div>
            <div class="home-hero__eyebrow">Visys · Gestão empresarial</div>
            <div class="home-hero__title">Olá, <span>{{ Auth::user()->name }}</span></div>
            <p class="home-hero__text">
                Acesse cadastros, vendas, orçamentos e financeiro em um só lugar — interface direta, rápida e feita para o dia a dia.
            </p>
        </div>
        <div class="home-hero__chip">Painel</div>
    </div>

    <p class="home-section-label">Resumo do dia</p>
    <div class="stats-grid">

        <div class="card">
            <div class="card__body">
                <div class="text-bold">{{ $vendasHojeVal }}</div>
                <div class="text-muted">vendas hoje</div>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <div class="text-bold">{{ $faturamentoTexto }}</div>
                <div class="text-muted">faturado hoje</div>
            </div>
        </div>

        @if($podeFinanceiro)
        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge {{ $contasReceberVencidasVal > 0 ? 'badge--error' : 'badge--success' }}">
                        {{ $contasReceberVencidasVal }}
                    </span>
                </div>
                <div class="text-muted">a receber vencidas</div>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge {{ $contasPagarVencidasVal > 0 ? 'badge--error' : 'badge--success' }}">
                        {{ $contasPagarVencidasVal }}
                    </span>
                </div>
                <div class="text-muted">a pagar vencidas</div>
            </div>
        </div>

        <div class="card">
            <div class="card__body">
                <div class="text-bold">{{ $aReceberTexto }}</div>
                <div class="text-muted">a receber em 7 dias</div>
            </div>
        </div>
        @endif

        <div class="card">
            <div class="card__body">
                <div>
                    <span class="badge {{ $estoqueCriticoVal > 0 ? 'badge--error' : 'badge--success' }}">
                        {{ $estoqueCriticoVal }}
                    </span>
                </div>
                <div class="text-muted">produtos em estoque crítico</div>
            </div>
        </div>

    </div>

    <p class="home-section-label">Atalhos rápidos</p>
    <div class="stats-grid">
        <a href="{{ route('venda.create') }}" class="card card--link">
            <div class="card__body">
                <div class="text-bold">Nova Venda</div>
            </div>
        </a>
        <a href="{{ route('orcamento.create') }}" class="card card--link">
            <div class="card__body">
                <div class="text-bold">Novo Orçamento</div>
            </div>
        </a>
        <a href="{{ route('entrada-estoque.create') }}" class="card card--link">
            <div class="card__body">
                <div class="text-bold">Nova Entrada de Estoque</div>
            </div>
        </a>
    </div>

    <p class="home-section-label">Operacional</p>
    <div class="modules-grid">

        <a href="{{ route('venda.index') }}" class="module-card module-card--green">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>
            <div class="module-name">Vendas</div>
            <div class="module-desc">Registre as vendas da sua loja</div>
        </a>

        <a href="{{ route('orcamento.index') }}" class="module-card module-card--green">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 2h6"></path>
                    <path d="M9 22h6"></path>
                    <path d="M5 7h14"></path>
                    <path d="M5 17h14"></path>
                    <rect x="5" y="2" width="14" height="20" rx="0"></rect>
                    <path d="M9 12l2 2 4-4"></path>
                </svg>
            </div>
            <div class="module-name">Orçamentos</div>
            <div class="module-desc">Registre orçamentos para futuras vendas</div>
        </a>

        <a href="{{ route('entrada-estoque.index') }}" class="module-card module-card--green">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                    <line x1="12" y1="22.08" x2="12" y2="12"></line>
                </svg>
            </div>
            <div class="module-name">Entradas de Estoque</div>
            <div class="module-desc">Registre entradas de mercadoria</div>
        </a>

        @if($podeOperacional)
        <a href="{{ route('comissao.index') }}" class="module-card module-card--green">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="module-name">Comissões</div>
            <div class="module-desc">Comissões de vendedores</div>
        </a>
        @endif

    </div>

    @if($podeFinanceiro)
    <p class="home-section-label">Financeiro</p>
    <div class="modules-grid">

        <a href="{{ route('conta-receber.index') }}" class="module-card module-card--accent">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
            <div class="module-name">Contas a Receber</div>
            <div class="module-desc">Controle de recebimentos</div>
        </a>

        <a href="{{ route('conta-pagar.index') }}" class="module-card module-card--accent">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    <line x1="4" y1="4" x2="20" y2="20" />
                </svg>
            </div>
            <div class="module-name">Contas a Pagar</div>
            <div class="module-desc">Controle de pagamentos</div>
        </a>

        <a href="{{ route('fluxo-de-caixa.index') }}" class="module-card module-card--accent">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline>
                    <polyline points="16 7 22 7 22 13"></polyline>
                </svg>
            </div>
            <div class="module-name">Fluxo de Caixa</div>
            <div class="module-desc">Entradas e saídas por período</div>
        </a>

    </div>
    @endif

    <p class="home-section-label">Cadastros</p>
    <div class="modules-grid">

        <a href="{{ route('cliente.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                </svg>
            </div>
            <div class="module-name">Clientes</div>
            <div class="module-desc">Consulte e edite clientes</div>
        </a>

        <a href="{{ route('fornecedor.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9h18M3 15h18M8 3v18M16 3v18" />
                </svg>
            </div>
            <div class="module-name">Fornecedores</div>
            <div class="module-desc">Gerencie seus fornecedores</div>
        </a>

        @if($podeOperacional)
        <a href="{{ route('funcionario.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="0" />
                    <path d="M8 21h8M12 17v4" />
                </svg>
            </div>
            <div class="module-name">Funcionários</div>
            <div class="module-desc">Controle o quadro de pessoal</div>
        </a>
        @endif

        <a href="{{ route('produto.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 3h18v4H3zM3 10h18v4H3zM3 17h18v4H3z" />
                </svg>
            </div>
            <div class="module-name">Produtos</div>
            <div class="module-desc">Catálogo de produtos</div>
        </a>

    </div>

    <p class="home-section-label">Relatórios</p>
    <div class="modules-grid">

        <a href="{{ route('relatorio.vendas') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="20" x2="6" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="18" y1="20" x2="18" y2="14"></line>
                </svg>
            </div>
            <div class="module-name">Vendas por Período</div>
            <div class="module-desc">Vendas confirmadas por período</div>
        </a>

        <a href="{{ route('relatorio.produtos-mais-vendidos') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="20" x2="6" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="18" y1="20" x2="18" y2="14"></line>
                </svg>
            </div>
            <div class="module-name">Produtos Mais Vendidos</div>
            <div class="module-desc">Ranking de produtos vendidos</div>
        </a>

        @if($podeFinanceiro)
        <a href="{{ route('relatorio.contas-receber') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="20" x2="6" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="18" y1="20" x2="18" y2="14"></line>
                </svg>
            </div>
            <div class="module-name">Contas a Receber</div>
            <div class="module-desc">Posição de recebíveis</div>
        </a>

        <a href="{{ route('relatorio.contas-pagar') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="20" x2="6" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="18" y1="20" x2="18" y2="14"></line>
                </svg>
            </div>
            <div class="module-name">Contas a Pagar</div>
            <div class="module-desc">Posição de pagamentos</div>
        </a>
        @endif

        <a href="{{ route('relatorio.estoque') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="6" y1="20" x2="6" y2="10"></line>
                    <line x1="12" y1="20" x2="12" y2="4"></line>
                    <line x1="18" y1="20" x2="18" y2="14"></line>
                </svg>
            </div>
            <div class="module-name">Posição de Estoque</div>
            <div class="module-desc">Saldo atual por produto</div>
        </a>

    </div>

    <p class="home-section-label">Configuração</p>
    <div class="modules-grid">

        @if($podeAdmin)
        <a href="{{ route('formaPagamento.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
            <div class="module-name">Formas de Pagamento</div>
            <div class="module-desc">Formas de pagamento aceitas</div>
        </a>
        @endif

        <a href="{{ route('estoque.ajuste.create') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="21" x2="4" y2="14"></line>
                    <line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line>
                    <line x1="20" y1="12" x2="20" y2="3"></line>
                    <line x1="1" y1="14" x2="7" y2="14"></line>
                    <line x1="9" y1="8" x2="15" y2="8"></line>
                    <line x1="17" y1="16" x2="23" y2="16"></line>
                </svg>
            </div>
            <div class="module-name">Ajuste de Estoque</div>
            <div class="module-desc">Ajuste manual de estoque</div>
        </a>

        @if($podeAdmin)
        <a href="{{ route('usuario.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div class="module-name">Usuários</div>
            <div class="module-desc">Controle de acesso</div>
        </a>

        <a href="{{ route('empresa.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="0" />
                    <path d="M16 3H8v4h12V5a2 2 0 0 0-2-2z" />
                </svg>
            </div>
            <div class="module-name">Empresas</div>
            <div class="module-desc">Gerencie as empresas cadastradas</div>
        </a>
        @endif

    </div>

@endsection
