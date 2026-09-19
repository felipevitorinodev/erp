@extends('layouts.app')

@section('title', 'Início')
@section('page_title', 'Início')

@section('content')

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

    <p class="home-section-label">Cadastros</p>
    <div class="modules-grid">

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

    <p class="home-section-label">Faturamento</p>
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

    </div>

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

    </div>

@endsection
