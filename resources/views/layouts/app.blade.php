<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP')</title>

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">

    @stack('styles')
</head>

<body>

    <header class="header">
        <div class="header__brand">
            <span class="header__logo">&#9632;</span>
            <a href="/">
                <span class="header__name">Sistema de Gestão</span>
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
                    <div class="sidebar__group-toggle" data-toggle>
                        <a style="text-decoration: none;" href="/">
                            <span class="sidebar__group-label">INÍCIO</span>
                        </a>
                    </div>
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

                        <a href="#"
                            class="sidebar__link {{ request()->routeIs('clientes.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Clientes
                        </a>

                        <a href="#"
                            class="sidebar__link {{ request()->routeIs('fornecedores.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Fornecedores
                        </a>

                        <a href="#"
                            class="sidebar__link {{ request()->routeIs('funcionarios.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Funcionários
                        </a>

                        <a href="#"
                            class="sidebar__link {{ request()->routeIs('produto.*') ? 'sidebar__link--active' : '' }}">
                            <span class="sidebar__icon">&#9632;</span> Produtos
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

    <footer class="footer">
        <span>Felipe Vitorino &copy; {{ date('Y') }}</span>
    </footer>

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
    </script>

    @stack('scripts')

</body>

</html>