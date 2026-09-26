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

    <header class="header">
        <button type="button" class="header__menu" id="btn-sidebar-toggle" aria-label="Abrir menu"
            aria-expanded="false">
            <span class="header__menu-icon" aria-hidden="true"></span>
        </button>

        <div class="header__brand">
            <a href="/" class="header__brand-link" title="Visys">
                <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys" class="header__logo-img">
            </a>
        </div>

        <div class="header__user">
            <span class="header__user-name">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" class="d-inline">
                @csrf
                <button type="submit" class="header__logout">Sair</button>
            </form>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebar-overlay" hidden></div>

    <div class="layout">

        <aside class="sidebar">
            <nav class="sidebar__nav">

                <div class="sidebar__group" data-group>
                    <a href="/" class="sidebar__link {{ request()->is('/') ? 'sidebar__link--active' : '' }}">
                        <span class="sidebar__icon">&#9632;</span> Início
                    </a>
                </div>

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">OPERACIONAL</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('venda.index') }}"
                            class="sidebar__link {{ request()->routeIs('venda.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Vendas
                        </a>
                        <a href="{{ route('orcamento.index') }}"
                            class="sidebar__link {{ request()->routeIs('orcamento.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Orçamentos
                        </a>
                        <a href="{{ route('entrada-estoque.index') }}"
                            class="sidebar__link {{ request()->routeIs('entrada-estoque.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Entradas de Estoque
                        </a>
                        @if(in_array(auth()->user()->perfil ?? '', ['admin', 'operador'], true))
                            <a href="{{ route('comissao.index') }}"
                                class="sidebar__link {{ request()->routeIs('comissao.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Comissões
                            </a>
                        @endif
                    </div>
                </div>

                @if(in_array(auth()->user()->perfil ?? '', ['admin', 'financeiro'], true))
                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">FINANCEIRO</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('conta-receber.index') }}"
                            class="sidebar__link {{ request()->routeIs('conta-receber.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Contas a Receber
                        </a>
                        <a href="{{ route('conta-pagar.index') }}"
                            class="sidebar__link {{ request()->routeIs('conta-pagar.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Contas a Pagar
                        </a>
                        <a href="{{ route('fluxo-de-caixa.index') }}"
                            class="sidebar__link {{ request()->routeIs('fluxo-de-caixa.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Fluxo de Caixa
                        </a>
                    </div>
                </div>
                @endif

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">CADASTROS</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>

                    <div class="sidebar__group-links" data-links>

                        <a href="{{ route('cliente.index') }}"
                            class="sidebar__link {{ request()->routeIs('cliente.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Clientes
                        </a>

                        <a href="{{ route('fornecedor.index') }}"
                            class="sidebar__link {{ request()->routeIs('fornecedor.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Fornecedores
                        </a>

                        @if(in_array(auth()->user()->perfil ?? '', ['admin', 'operador'], true))
                            <a href="{{ route('funcionario.index') }}"
                                class="sidebar__link {{ request()->routeIs('funcionario.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Funcionários
                            </a>
                        @endif

                        {{-- Sub-grupo: Produtos --}}
                        <div class="sidebar__subgroup" data-subgroup>
                            <div class="sidebar__subgroup-toggle {{ request()->routeIs('produto.*', 'grupo.*', 'unidadeMedida.*') ? 'sidebar__subgroup-toggle--open' : '' }}"
                                data-subtoggle>
                                <span class="sidebar__subgroup-name">
                                    <span class="sidebar__icon">&#9632;</span> Produtos
                                </span>
                                <span class="sidebar__subgroup-arrow">&#9660;</span>
                            </div>
                            <div class="sidebar__subgroup-links" data-sublinks>
                                <a href="{{ route('produto.index') }}"
                                    class="sidebar__link sidebar__link--sub {{ request()->routeIs('produto.*') ? 'sidebar__link--active' : '' }}">
                                    <span class="sidebar__icon">&#9632;</span> Todos os Produtos
                                </a>
                                <a href="{{ route('grupo.index') }}"
                                    class="sidebar__link sidebar__link--sub {{ request()->routeIs('grupo.*') ? 'sidebar__link--active' : '' }}">
                                    <span class="sidebar__icon">&#9632;</span> Grupos
                                </a>
                                <a href="{{ route('unidadeMedida.index') }}"
                                    class="sidebar__link sidebar__link--sub {{ request()->routeIs('unidadeMedida.*') ? 'sidebar__link--active' : '' }}">
                                    <span class="sidebar__icon">&#9632;</span> Unidades de Medida
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">RELATÓRIOS</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="{{ route('relatorio.vendas') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.vendas*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Vendas por Período
                        </a>
                        <a href="{{ route('relatorio.produtos-mais-vendidos') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.produtos-mais-vendidos*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Produtos Mais Vendidos
                        </a>
                        @if(in_array(auth()->user()->perfil ?? '', ['admin', 'financeiro'], true))
                            <a href="{{ route('relatorio.contas-receber') }}"
                                class="sidebar__link {{ request()->routeIs('relatorio.contas-receber*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Contas a Receber
                            </a>
                            <a href="{{ route('relatorio.contas-pagar') }}"
                                class="sidebar__link {{ request()->routeIs('relatorio.contas-pagar*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Contas a Pagar
                            </a>
                        @endif
                        <a href="{{ route('relatorio.estoque') }}"
                            class="sidebar__link {{ request()->routeIs('relatorio.estoque*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Posição de Estoque
                        </a>
                    </div>
                </div>

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">CONFIGURAÇÃO</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        @if((auth()->user()->perfil ?? '') === 'admin')
                            <a href="{{ route('formaPagamento.index') }}"
                                class="sidebar__link {{ request()->routeIs('formaPagamento.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Formas de Pagamento
                            </a>
                        @endif
                        @if(in_array(auth()->user()->perfil ?? '', ['admin', 'financeiro'], true))
                            <a href="{{ route('categoria-financeira.index') }}"
                                class="sidebar__link {{ request()->routeIs('categoria-financeira.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Categorias Financeiras
                            </a>
                        @endif
                        <a href="{{ route('estoque.ajuste.create') }}"
                            class="sidebar__link {{ request()->routeIs('estoque.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Ajuste de Estoque
                        </a>
                        @if((auth()->user()->perfil ?? '') === 'admin')
                            <a href="{{ route('usuario.index') }}"
                                class="sidebar__link {{ request()->routeIs('usuario.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Usuários
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('empresa.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Empresas
                            </a>
                        @endif
                    </div>
                </div>

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

    @stack('scripts')

</body>

</html>
