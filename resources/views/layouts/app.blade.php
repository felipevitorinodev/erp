<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GestãoFácil')</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @stack('styles')
</head>

<body>

    <header class="header">
        <button class="menu-toggle" id="menuToggle">
            ☰
        </button>
        <div class="header__brand">
            <span class="header__logo">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                    <rect x="1" y="1" width="7" height="7" fill="white" opacity="0.9" />
                    <rect x="10" y="1" width="7" height="7" fill="white" opacity="0.5" />
                    <rect x="1" y="10" width="7" height="7" fill="white" opacity="0.5" />
                    <rect x="10" y="10" width="7" height="7" fill="white" opacity="0.9" />
                </svg>
            </span>
            <a href="/">
                <span class="header__name">GestãoFácil</span>
            </a>
        </div>

        <div class="header__user">
            <span class="header__user-name">{{ Auth::user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <button type="submit" class="header__logout">Sair</button>
            </form>
        </div>
    </header>

    <div class="layout">

        <aside class="sidebar">
            <nav class="sidebar__nav">

                <div class="sidebar__group" data-group>
                    <a style="text-decoration: none;" href="/">
                        <div class="sidebar__group-toggle" data-toggle>
                            <span class="sidebar__group-label">INÍCIO</span>
                        </div>
                    </a>
                </div>

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">CADASTROS</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>

                    <div class="sidebar__group-links" data-links>

                        <a href="{{ route('empresa.index') }}"
                            class="sidebar__link {{ request()->routeIs('empresa.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Empresas
                        </a>

                        <a href="{{ route('cliente.index') }}"
                            class="sidebar__link {{ request()->routeIs('cliente.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Clientes
                        </a>

                        <a href="{{ route('fornecedor.index') }}"
                            class="sidebar__link {{ request()->routeIs('fornecedor.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Fornecedores
                        </a>

                        <a href="{{ route('funcionario.index') }}"
                            class="sidebar__link {{ request()->routeIs('funcionario.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Funcionários
                        </a>

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
                        <span class="sidebar__group-label">FATURAMENTO</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="#" class="sidebar__link">
                            <span class="sidebar__icon">&#9632;</span> Vendas
                        </a>
                        <a href="#" class="sidebar__link">
                            <span class="sidebar__icon">&#9632;</span> Orçamentos
                        </a>
                    </div>
                </div>

                <div class="sidebar__group" data-group>
                    <div class="sidebar__group-toggle" data-toggle>
                        <span class="sidebar__group-label">FINANCEIRO</span>
                        <span class="sidebar__group-arrow">&#9660;</span>
                    </div>
                    <div class="sidebar__group-links" data-links>
                        <a href="#" class="sidebar__link">
                            <span class="sidebar__icon">&#9632;</span> Contas a Receber
                        </a>
                        <a href="#" class="sidebar__link">
                            <span class="sidebar__icon">&#9632;</span> Contas a Pagar
                        </a>
                    </div>
                </div>

            </nav>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

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

            @if(session('success'))
                <div class="alert alert--success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert--error">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert--error">
                    <ul style="margin:0; padding-left: 1rem;">
                        @foreach($errors->all() as $error)
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

    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="{{ asset('js/app.js') }}"></script>

    <script>
        document.querySelectorAll('[data-group]').forEach(function (group) {
            var toggle = group.querySelector('[data-toggle]');
            var links = group.querySelector('[data-links]');
            var arrow = group.querySelector('.sidebar__group-arrow');

            if (!toggle || !links || !arrow) return;

            var hasActive = group.querySelector('.sidebar__link--active');
            if (!hasActive) {
                links.classList.add('sidebar__group-links--collapsed');
                arrow.classList.add('sidebar__group-arrow--collapsed');
            }

            toggle.addEventListener('click', function () {
                var collapsed = links.classList.toggle('sidebar__group-links--collapsed');
                arrow.classList.toggle('sidebar__group-arrow--collapsed', collapsed);
            });
        });

        document.querySelectorAll('[data-subgroup]').forEach(function (subgroup) {
            var subtoggle = subgroup.querySelector('[data-subtoggle]');
            var sublinks = subgroup.querySelector('[data-sublinks]');
            var subarrow = subgroup.querySelector('.sidebar__subgroup-arrow');

            if (!subtoggle || !sublinks || !subarrow) return;

            var hasActive = subgroup.querySelector('.sidebar__link--active');
            if (!hasActive) {
                sublinks.classList.add('sidebar__subgroup-links--collapsed');
                subarrow.classList.add('sidebar__subgroup-arrow--collapsed');
            }

            subtoggle.addEventListener('click', function () {
                var collapsed = sublinks.classList.toggle('sidebar__subgroup-links--collapsed');
                subarrow.classList.toggle('sidebar__subgroup-arrow--collapsed', collapsed);
                subtoggle.classList.toggle('sidebar__subgroup-toggle--open', !collapsed);
            });
        });

        const menu = document.getElementById("menuToggle");
        const sidebar = document.querySelector(".sidebar");
        const overlay = document.getElementById("sidebarOverlay");

        menu.addEventListener("click", () => {

            sidebar.classList.toggle("open");
            overlay.classList.toggle("show");

        });

        overlay.addEventListener("click", () => {

            sidebar.classList.remove("open");
            overlay.classList.remove("show");

        });

        //Controle das rotas Index para resposividade
        function responsiveTables() {

            document.querySelectorAll(".table").forEach(table => {

                const headers = [...table.querySelectorAll("thead th")]
                    .map(th => th.textContent.trim());

                table.querySelectorAll("tbody tr").forEach(row => {

                    row.querySelectorAll("td").forEach((td, index) => {

                        td.dataset.label = headers[index] || "";

                    });

                });

            });

        }

        document.addEventListener("DOMContentLoaded", responsiveTables);
    </script>

    @stack('scripts')

</body>

</html>