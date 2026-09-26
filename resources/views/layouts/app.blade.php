<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <!-- Prevent mobile zoom to improve usability on small screens -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Visys') — Visys</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('styles')
</head>

<body>
    @include('partials.page-loader')

    <header class="header">
        <button type="button" class="header__menu" id="btn-sidebar-toggle" aria-label="Abrir menu"
            aria-expanded="false">
            <span class="header__menu-icon" aria-hidden="true"><span></span></span>
        </button>

        <div class="header__brand">
            <a href="/" class="header__brand-link" title="Visys">
                <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys" class="header__logo-img">
            </a>
        </div>

        @php
            $user = Auth::user();
            $userName = $user->name ?? 'Usuário';
            $userPerfil = match ($user->perfil ?? '') {
                'admin' => 'Administrador',
                'financeiro' => 'Financeiro',
                'operador' => 'Operador',
                default => 'Usuário',
            };
            $empresa = $user->empresa;
            $userEmpresa = $empresa?->nome_fantasia ?: ($empresa?->razao_social ?: null);
            $userInitials = collect(preg_split('/\s+/', trim($userName)))
                ->filter()
                ->take(2)
                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                ->implode('');
        @endphp

        <div class="header__user">
            <div class="header__identity" title="{{ $userName }}{{ $userEmpresa ? ' — ' . $userEmpresa : '' }}">
                <span class="header__avatar" aria-hidden="true">{{ $userInitials ?: 'U' }}</span>
                <div class="header__identity-text">
                    <span class="header__user-name">{{ $userName }}</span>
                    <span class="header__user-meta">
                        {{ $userPerfil }}@if($userEmpresa) · {{ $userEmpresa }}@endif
                    </span>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="header__logout-form">
                @csrf
                <button type="submit" class="header__logout" title="Sair do sistema" aria-label="Sair">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="header__logout-icon" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span class="header__logout-text">Sair</span>
                </button>
            </form>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebar-overlay" hidden></div>

    <div class="layout">

        <aside class="sidebar">
            <nav class="sidebar__nav">
                @php
                    $perfil = auth()->user()->perfil ?? '';
                    $podeFinanceiro = in_array($perfil, ['admin', 'financeiro'], true);
                    $podeOperacional = in_array($perfil, ['admin', 'operador'], true);
                    $ehAdmin = $perfil === 'admin';
                @endphp

                {{-- Início --}}
                <div class="sidebar__group">
                    <a href="/" class="sidebar__link {{ request()->is('/') ? 'sidebar__link--active' : '' }}">
                        <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        INÍCIO
                    </a>
                </div>

                {{-- Vendas --}}
                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <circle cx="9" cy="21" r="1"></circle>
                            <circle cx="20" cy="21" r="1"></circle>
                            <path d="M1 1h4l2.68 13.39A2 2 0 0 0 9.64 16H19a2 2 0 0 0 2-1.61L23 6H6"></path>
                        </svg>
                        <span class="sidebar__group-label">Vendas</span>
                        <span class="sidebar__group-arrow">›</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('venda.create') }}"
                            class="sidebar__link sidebar__link--action {{ request()->routeIs('venda.create') ? 'sidebar__link--active' : '' }}">
                            + Nova Venda
                        </a>
                        <a href="{{ route('venda.index') }}"
                            class="sidebar__link {{ request()->routeIs('venda.index', 'venda.show', 'venda.edit') ? 'sidebar__link--active' : '' }}">
                            Histórico de Vendas
                        </a>
                        <a href="{{ route('orcamento.index') }}"
                            class="sidebar__link {{ request()->routeIs('orcamento.*') ? 'sidebar__link--active' : '' }}">
                            Orçamentos
                        </a>
                        @if ($podeOperacional)
                            <a href="{{ route('comissao.index') }}"
                                class="sidebar__link {{ request()->routeIs('comissao.*') ? 'sidebar__link--active' : '' }}">
                                Comissões
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Estoque --}}
                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path
                                d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z">
                            </path>
                            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                            <line x1="12" y1="22.08" x2="12" y2="12"></line>
                        </svg>
                        <span class="sidebar__group-label">Estoque</span>
                        <span class="sidebar__group-arrow">›</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('produto.index') }}"
                            class="sidebar__link {{ request()->routeIs('produto.*') ? 'sidebar__link--active' : '' }}">
                            Produtos
                        </a>
                        <a href="{{ route('entrada-estoque.index') }}"
                            class="sidebar__link {{ request()->routeIs('entrada-estoque.*') ? 'sidebar__link--active' : '' }}">
                            Entradas (Compras)
                        </a>
                        <a href="{{ route('estoque.ajuste.create') }}"
                            class="sidebar__link {{ request()->routeIs('estoque.ajuste.*') ? 'sidebar__link--active' : '' }}">
                            Ajuste Manual
                        </a>
                        <a href="{{ route('relatorio.estoque') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.estoque*') ? 'sidebar__link--active' : '' }}">
                            Posição Atual
                        </a>
                    </div>
                </div>

                {{-- Financeiro --}}
                @if ($podeFinanceiro)
                    <div class="sidebar__group" data-group>
                        <div class="sidebar__group-toggle" data-toggle>
                            <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <line x1="12" y1="1" x2="12" y2="23"></line>
                                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
                            </svg>
                            <span class="sidebar__group-label">Financeiro</span>
                            <span class="sidebar__group-arrow">›</span>
                        </div>
                        <div class="sidebar__group-links" data-links>
                            <a href="{{ route('conta-receber.index') }}"
                                class="sidebar__link {{ request()->routeIs('conta-receber.*') ? 'sidebar__link--active' : '' }}">
                                A Receber
                            </a>
                            <a href="{{ route('conta-pagar.index') }}"
                                class="sidebar__link {{ request()->routeIs('conta-pagar.*') ? 'sidebar__link--active' : '' }}">
                                A Pagar
                            </a>
                            <a href="{{ route('fluxo-de-caixa.index') }}"
                                class="sidebar__link {{ request()->routeIs('fluxo-de-caixa.*') ? 'sidebar__link--active' : '' }}">
                                Fluxo de Caixa
                            </a>
                            <a href="{{ route('categoria-financeira.index') }}"
                                class="sidebar__link {{ request()->routeIs('categoria-financeira.*') ? 'sidebar__link--active' : '' }}">
                                Categorias
                            </a>
                            @if ($ehAdmin)
                                <a href="{{ route('formaPagamento.index') }}"
                                    class="sidebar__link {{ request()->routeIs('formaPagamento.*') ? 'sidebar__link--active' : '' }}">
                                    Formas de Pagamento
                                </a>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Cadastros --}}
                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span class="sidebar__group-label">Cadastros</span>
                        <span class="sidebar__group-arrow">›</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('cliente.index') }}"
                            class="sidebar__link {{ request()->routeIs('cliente.*') ? 'sidebar__link--active' : '' }}">
                            Clientes
                        </a>
                        <a href="{{ route('fornecedor.index') }}"
                            class="sidebar__link {{ request()->routeIs('fornecedor.*') ? 'sidebar__link--active' : '' }}">
                            Fornecedores
                        </a>
                        @if ($podeOperacional)
                            <a href="{{ route('funcionario.index') }}"
                                class="sidebar__link {{ request()->routeIs('funcionario.*') ? 'sidebar__link--active' : '' }}">
                                Funcionários
                            </a>
                        @endif
                        <a href="{{ route('grupo.index') }}"
                            class="sidebar__link {{ request()->routeIs('grupo.*') ? 'sidebar__link--active' : '' }}">
                            Grupos de Produtos
                        </a>
                        <a href="{{ route('unidadeMedida.index') }}"
                            class="sidebar__link {{ request()->routeIs('unidadeMedida.*') ? 'sidebar__link--active' : '' }}">
                            Unidades de Medida
                        </a>
                    </div>
                </div>

                {{-- Relatórios --}}
                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2">
                            <line x1="18" y1="20" x2="18" y2="10"></line>
                            <line x1="12" y1="20" x2="12" y2="4"></line>
                            <line x1="6" y1="20" x2="6" y2="14"></line>
                        </svg>
                        <span class="sidebar__group-label">Relatórios</span>
                        <span class="sidebar__group-arrow">›</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('relatorio.vendas') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.vendas*') ? 'sidebar__link--active' : '' }}">
                            Vendas por Período
                        </a>
                        <a href="{{ route('relatorio.produtos-mais-vendidos') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.produtos-mais-vendidos*') ? 'sidebar__link--active' : '' }}">
                            Produtos Mais Vendidos
                        </a>
                        @if ($podeFinanceiro)
                            <a href="{{ route('relatorio.contas-receber') }}"
                                class="sidebar__link {{ request()->routeIs('relatorio.contas-receber*') ? 'sidebar__link--active' : '' }}">
                                Contas a Receber
                            </a>
                            <a href="{{ route('relatorio.contas-pagar') }}"
                                class="sidebar__link {{ request()->routeIs('relatorio.contas-pagar*') ? 'sidebar__link--active' : '' }}">
                                Contas a Pagar
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Configurações (Admin) --}}
                @if ($ehAdmin)
                    <div class="sidebar__group" data-group>
                        <div class="sidebar__group-toggle" data-toggle>
                            <svg class="sidebar__icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                stroke-width="2">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z">
                                </path>
                            </svg>
                            <span class="sidebar__group-label">Configurações</span>
                            <span class="sidebar__group-arrow">›</span>
                        </div>
                        <div class="sidebar__group-links" data-links>
                            <a href="{{ route('usuario.index') }}"
                                class="sidebar__link {{ request()->routeIs('usuario.*') ? 'sidebar__link--active' : '' }}">
                                Usuários
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('empresa.*') ? 'sidebar__link--active' : '' }}">
                                Empresa
                            </a>
                        </div>
                    </div>
                @endif

            </nav>
        </aside>

        <main class="main">

            <div class="page-bar">
                <div class="page-bar__title">
                    <h1 class="page-title">@yield('page_title')</h1>
                    @hasSection('breadcrumb')
                        <nav class="breadcrumb">@yield('breadcrumb')</nav>
                    @endif
                </div>
                <div class="page-bar__actions">
                    @yield('page_actions')
                </div>
            </div>

            @if (session('success') || session('error') || session('warning'))
                <div class="toast-stack" id="toast-stack" aria-live="polite">
                    @if (session('success'))
                        <div class="toast toast--success" role="alert">{{ session('success') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="toast toast--error" role="alert">{{ session('error') }}</div>
                    @endif
                    @if (session('warning'))
                        <div class="toast toast--warning" role="alert">{{ session('warning') }}</div>
                    @endif
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert--error">
                    <ul style="margin:0; padding-left: 1rem;">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="page-content">
                @yield('content')
            </div>

        </main>
    </div>

    <footer class="footer">
        <a href="/" class="footer__brand" title="Visys">
            <img src="{{ asset('img/logo-v-sem-fundo.png') }}" alt="Visys" class="footer__logo-img">
        </a>
        <span>&copy; {{ date('Y') }} · Visys · Gestão empresarial</span>
    </footer>

    <div class="modal-overlay" id="modal-confirm" hidden>
        <div class="modal-box">
            <div class="modal-box__header">
                <span class="card__title" id="modal-confirm-title">Confirmar</span>
                <button type="button" class="btn btn--ghost btn--sm" data-confirm-close>✕</button>
            </div>
            <div class="modal-box__body">
                <p id="modal-confirm-message" style="margin:0; font-size:13px; color:var(--color-text);"></p>
            </div>
            <div class="modal-box__footer">
                <button type="button" class="btn btn--ghost" data-confirm-close>Voltar</button>
                <button type="button" class="btn btn--primary" id="modal-confirm-ok">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/autocomplete.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>
    @include('partials.page-loader-script')

    <script>
        document.querySelectorAll('[data-group]').forEach(function(group) {
            var toggle = group.querySelector('[data-toggle]');
            var links = group.querySelector('[data-links]');
            var arrow = group.querySelector('.sidebar__group-arrow');

            if (!toggle || !links || !arrow) return;

            var hasActive = group.querySelector('.sidebar__link--active');
            var keepOpen = group.hasAttribute('data-keep-open');
            if (!hasActive && !keepOpen) {
                links.classList.add('sidebar__group-links--collapsed');
                arrow.classList.add('sidebar__group-arrow--collapsed');
            }

            toggle.addEventListener('click', function() {
                var collapsed = links.classList.toggle('sidebar__group-links--collapsed');
                arrow.classList.toggle('sidebar__group-arrow--collapsed', collapsed);
            });
        });

        document.querySelectorAll('[data-subgroup]').forEach(function(subgroup) {
            var subtoggle = subgroup.querySelector('[data-subtoggle]');
            var sublinks = subgroup.querySelector('[data-sublinks]');
            var subarrow = subgroup.querySelector('.sidebar__subgroup-arrow');

            if (!subtoggle || !sublinks || !subarrow) return;

            var hasActive = subgroup.querySelector('.sidebar__link--active');
            if (!hasActive) {
                sublinks.classList.add('sidebar__subgroup-links--collapsed');
                subarrow.classList.add('sidebar__subgroup-arrow--collapsed');
            }

            subtoggle.addEventListener('click', function() {
                var collapsed = sublinks.classList.toggle('sidebar__subgroup-links--collapsed');
                subarrow.classList.toggle('sidebar__subgroup-arrow--collapsed', collapsed);
                subtoggle.classList.toggle('sidebar__subgroup-toggle--open', !collapsed);
            });
        });
    </script>

    {{-- Modais são colocados fora de qualquer form --}}
    @stack('modals')

    @stack('scripts')

</body>

</html>
