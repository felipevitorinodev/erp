@extends('layouts.app')

@section('title', 'Bem Vindo!')

@section('content')

    <style>
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(5, 160px);
            gap: .85rem;
            margin-bottom: 1.75rem;
        }

        .module-card {
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-top: 3px solid var(--color-primary);
            padding: 1.1rem 1rem;
            display: flex;
            flex-direction: column;
            gap: .6rem;
            cursor: pointer;
            transition: box-shadow .15s, border-color .15s;
            text-decoration: none;
        }

        .module-card:hover {
            box-shadow: 0 2px 8px rgba(26, 59, 93, .1);
            border-color: var(--color-border-mid);
            text-decoration: none;
        }

        .module-card--accent {
            border-top-color: var(--color-accent);
        }

        .module-icon {
            width: 32px;
            height: 32px;
            background: #EBF0F7;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--color-primary);
        }

        .module-card--accent .module-icon {
            background: #FBF0EB;
            color: var(--color-accent);
        }

        .module-name {
            font-size: .8rem;
            font-weight: 700;
            color: var(--color-text);
            text-transform: uppercase;
            letter-spacing: .03em;
        }

        .module-desc {
            font-size: .72rem;
            color: var(--color-text-muted);
            line-height: 1.4;
        }
    </style>

    <p class="stat-card__label" style="margin-bottom:.75rem;">Cadastros</p>
    <div class="modules-grid">

        <a href="{{ route('empresa.index') }}" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="7" width="20" height="14" rx="2" />
                    <path d="M16 3H8a2 2 0 0 0-2 2v2h12V5a2 2 0 0 0-2-2z" />
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

        <a href="#" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9h18M3 15h18M8 3v18M16 3v18" />
                </svg>
            </div>
            <div class="module-name">Fornecedores</div>
            <div class="module-desc">Gerencie seus fornecedores</div>
        </a>

        <a href="#" class="module-card">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" />
                    <path d="M8 21h8M12 17v4" />
                </svg>
            </div>
            <div class="module-name">Funcionários</div>
            <div class="module-desc">Controle o quadro de pessoal</div>
        </a>

        <a href="#" class="module-card">
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

    <p class="stat-card__label" style="margin-bottom:.75rem;">Financeiro</p>
    <div class="modules-grid" style="grid-template-columns: repeat(2, 160px);">

        <a href="#" class="module-card module-card--accent">
            <div class="module-icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
            </div>
            <div class="module-name">Contas a Receber</div>
            <div class="module-desc">Controle de recebimentos</div>
        </a>

        <a href="#" class="module-card module-card--accent">
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