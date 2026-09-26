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

        $meses = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        $dataHoje = now()->day.' de '.$meses[now()->month].' de '.now()->year;
        $empresaHome = Auth::user()->empresa;
        $empresaHomeNome = trim((string) ($empresaHome->nome_fantasia ?? ''))
            ?: trim((string) ($empresaHome->razao_social ?? ''));
    @endphp

    <div class="home-bar">
        <div>
            <p class="home-bar__hello">Olá, <strong>{{ Auth::user()->name }}</strong></p>
            <p class="home-bar__meta">{{ $empresaHomeNome !== '' ? $empresaHomeNome.' · ' : '' }}{{ $dataHoje }}</p>
        </div>
    </div>

    {{-- AÇÕES PRINCIPAIS - Cards grandes e claros --}}
    <p class="home-section-label">O que você quer fazer?</p>
    <div class="main-actions-grid">
        <a href="{{ route('venda.create') }}" class="main-action-card main-action-card--primary">
            <div class="main-action-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
            </div>
            <div class="main-action-card__content">
                <span class="main-action-card__title">Nova Venda</span>
                <span class="main-action-card__desc">Registrar uma venda</span>
            </div>
        </a>

        <a href="{{ route('entrada-estoque.index') }}" class="main-action-card main-action-card--green">
            <div class="main-action-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                    <line x1="12" y1="8" x2="12" y2="16"></line>
                    <line x1="8" y1="12" x2="16" y2="12"></line>
                </svg>
            </div>
            <div class="main-action-card__content">
                <span class="main-action-card__title">Entrada de Mercadoria</span>
                <span class="main-action-card__desc">Registrar compra / NF-e</span>
            </div>
        </a>

        @if($podeFinanceiro)
        <a href="{{ route('conta-receber.create') }}" class="main-action-card main-action-card--blue">
            <div class="main-action-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="1" x2="12" y2="23"></line>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                </svg>
            </div>
            <div class="main-action-card__content">
                <span class="main-action-card__title">Receber</span>
                <span class="main-action-card__desc">Lançar conta a receber</span>
            </div>
        </a>

        <a href="{{ route('conta-pagar.create') }}" class="main-action-card main-action-card--orange">
            <div class="main-action-card__icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="1" y="4" width="22" height="16" rx="2"></rect>
                    <line x1="1" y1="10" x2="23" y2="10"></line>
                </svg>
            </div>
            <div class="main-action-card__content">
                <span class="main-action-card__title">Pagar</span>
                <span class="main-action-card__desc">Lançar conta a pagar</span>
            </div>
        </a>
        @endif
    </div>

    {{-- ALERTAS - Apenas se houver pendências --}}
    @if($contasReceberVencidasVal > 0 || $contasPagarVencidasVal > 0 || $estoqueCriticoVal > 0)
    <p class="home-section-label">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px; margin-right: 4px; color: var(--color-accent);">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
            <line x1="12" y1="9" x2="12" y2="13"></line>
            <line x1="12" y1="17" x2="12.01" y2="17"></line>
        </svg>
        Atenção
    </p>
    <div class="alerts-grid">
        @if($contasReceberVencidasVal > 0)
        <a href="{{ route('relatorio.contas-receber', ['vencimento' => 'vencidos', 'data_inicio' => '2000-01-01', 'data_fim' => date('Y-m-d')]) }}" class="alert-card alert-card--warning">
            <span class="alert-card__value">{{ $contasReceberVencidasVal }}</span>
            <span class="alert-card__label">contas a receber vencidas</span>
        </a>
        @endif
        @if($contasPagarVencidasVal > 0)
        <a href="{{ route('relatorio.contas-pagar', ['vencimento' => 'vencidos', 'data_inicio' => '2000-01-01', 'data_fim' => date('Y-m-d')]) }}" class="alert-card alert-card--danger">
            <span class="alert-card__value">{{ $contasPagarVencidasVal }}</span>
            <span class="alert-card__label">contas a pagar vencidas</span>
        </a>
        @endif
        @if($estoqueCriticoVal > 0)
        <a href="{{ route('relatorio.estoque', ['apenas_critico' => 1]) }}" class="alert-card alert-card--warning">
            <span class="alert-card__value">{{ $estoqueCriticoVal }}</span>
            <span class="alert-card__label">produtos com estoque baixo</span>
        </a>
        @endif
    </div>
    @endif

    {{-- RESUMO DO DIA --}}
    <p class="home-section-label">Resumo do dia</p>
    <div class="stats-grid">
        <a href="{{ route('venda.index') }}?data_inicio={{ date('Y-m-d') }}&data_fim={{ date('Y-m-d') }}" class="stat-card">
            <span class="stat-card__label">Vendas hoje</span>
            <span class="stat-card__value">{{ $vendasHojeVal }}</span>
        </a>

        <a href="{{ route('relatorio.vendas') }}?data_inicio={{ date('Y-m-d') }}&data_fim={{ date('Y-m-d') }}" class="stat-card">
            <span class="stat-card__label">Faturado hoje</span>
            <span class="stat-card__value">{{ $faturamentoTexto }}</span>
        </a>

        @if($podeFinanceiro)
        <a href="{{ route('relatorio.contas-receber', [
            'data_inicio' => date('Y-m-d'),
            'data_fim' => date('Y-m-d', strtotime('+7 days')),
            'situacao' => 'em_aberto',
        ]) }}" class="stat-card">
            <span class="stat-card__label">A receber (7 dias)</span>
            <span class="stat-card__value">{{ $aReceberTexto }}</span>
        </a>
        @endif
    </div>

    {{-- ACESSO RÁPIDO - Simplificado --}}
    <p class="home-section-label">Consultas</p>
    <div class="quick-links-grid">
        <a href="{{ route('venda.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
            Vendas
        </a>
        <a href="{{ route('orcamento.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="2" width="14" height="20"></rect><path d="M9 12l2 2 4-4"></path></svg>
            Orçamentos
        </a>
        <a href="{{ route('produto.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path></svg>
            Produtos
        </a>
        <a href="{{ route('cliente.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"></circle><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"></path></svg>
            Clientes
        </a>
        <a href="{{ route('fornecedor.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9h18M3 15h18M8 3v18M16 3v18"></path></svg>
            Fornecedores
        </a>
        @if($podeFinanceiro)
        <a href="{{ route('conta-receber.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
            A Receber
        </a>
        <a href="{{ route('conta-pagar.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
            A Pagar
        </a>
        <a href="{{ route('fluxo-de-caixa.index') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline></svg>
            Fluxo de Caixa
        </a>
        @endif
        <a href="{{ route('relatorio.vendas') }}" class="quick-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="6" y1="20" x2="6" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="18" y1="20" x2="18" y2="14"></line></svg>
            Relatórios
        </a>
    </div>

@endsection

@push('styles')
<style>
/* Ações principais grandes */
.main-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.main-action-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem 1.5rem;
    background: var(--color-surface);
    border: 2px solid var(--color-border);
    border-radius: 8px;
    text-decoration: none !important;
    transition: all 0.2s ease;
}
.main-action-card:hover,
.main-action-card:focus,
.main-action-card:active {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    text-decoration: none !important;
}
.main-action-card--primary {
    background: var(--color-primary);
    border-color: var(--color-primary);
    color: white;
}
.main-action-card--primary:hover {
    background: var(--color-primary-lt);
}
.main-action-card--green {
    border-color: #059669;
}
.main-action-card--green:hover {
    background: #ecfdf5;
}
.main-action-card--green .main-action-card__icon {
    color: #059669;
}
.main-action-card--blue {
    border-color: #2563eb;
}
.main-action-card--blue:hover {
    background: #eff6ff;
}
.main-action-card--blue .main-action-card__icon {
    color: #2563eb;
}
.main-action-card--orange {
    border-color: var(--color-accent);
}
.main-action-card--orange:hover {
    background: var(--color-accent-soft);
}
.main-action-card--orange .main-action-card__icon {
    color: var(--color-accent);
}
.main-action-card__icon {
    flex-shrink: 0;
}
.main-action-card__icon svg {
    width: 32px;
    height: 32px;
}
.main-action-card--primary .main-action-card__icon svg {
    stroke: white;
}
.main-action-card__content {
    display: flex;
    flex-direction: column;
}
.main-action-card__title {
    font-weight: 600;
    font-size: 1rem;
    color: var(--color-text);
}
.main-action-card--primary .main-action-card__title {
    color: white;
}
.main-action-card__desc {
    font-size: 0.8rem;
    color: var(--color-text-muted);
    margin-top: 0.125rem;
}
.main-action-card--primary .main-action-card__desc {
    color: rgba(255,255,255,0.8);
}

/* Alertas */
.alerts-grid {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1.5rem;
}
.alert-card {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    text-decoration: none !important;
    font-size: 0.9rem;
}
.alert-card:hover,
.alert-card:focus,
.alert-card:active {
    text-decoration: none !important;
}
.alert-card--warning {
    background: #fef3c7;
    border: 1px solid #f59e0b;
    color: #92400e;
}
.alert-card--danger {
    background: #fef2f2;
    border: 1px solid #ef4444;
    color: #991b1b;
}
.alert-card__value {
    font-weight: 700;
    font-size: 1.25rem;
}
.alert-card__label {
    font-size: 0.85rem;
}

/* Links rápidos */
.quick-links-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.quick-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1rem;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    color: var(--color-text);
    text-decoration: none !important;
    transition: all 0.15s ease;
}
.quick-link:hover,
.quick-link:focus,
.quick-link:active {
    background: var(--color-primary-soft);
    border-color: var(--color-primary);
    color: var(--color-primary);
    text-decoration: none !important;
}
.quick-link svg {
    width: 18px;
    height: 18px;
    opacity: 0.7;
}
.quick-link:hover svg {
    opacity: 1;
}

@media (max-width: 600px) {
    .main-actions-grid {
        grid-template-columns: 1fr;
    }
    .main-action-card {
        padding: 1rem;
    }
    .main-action-card__icon svg {
        width: 28px;
        height: 28px;
    }
    .alerts-grid {
        flex-direction: column;
    }
    .quick-links-grid {
        gap: 0.5rem;
    }
    .quick-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }
}
</style>
@endpush
