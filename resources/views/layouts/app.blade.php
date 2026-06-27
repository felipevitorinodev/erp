<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ERP') — {{ config('app.name') }}</title>

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
            <span class="header__user-name">Nome do usuário</span>

            <form method="POST" action="" style="display:inline;">
                @csrf
                <button type="submit" class="header__logout">Sair</button>
            </form>
        </div>
    </header>

    <div class="layout">

        <aside class="sidebar">
            <nav class="sidebar__nav">

                <div class="sidebar__group">
                    <a style="text-decoration: none;" href="/" class="sidebar__group-header">
                        <span class="sidebar__group-label">INÍCIO</span>
                    </a>
                </div>

                <div class="sidebar__group is-expanded">
                    <button type="button" class="sidebar__group-header" onclick="toggleSidebarGroup(this)">
                        <span class="sidebar__group-label">CADASTROS</span>
                        <span class="sidebar__group-toggle">&#9660;</span>
                    </button>

                    <div class="sidebar__group-content">
                        <div class="sidebar__group-inner">
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('empresa.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Empresas
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('cliente.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Clientes
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('fornecedore.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Fornecedores
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('funcionario.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Funcionarios
                            </a>
                            <a href="{{ route('empresa.index') }}"
                                class="sidebar__link {{ request()->routeIs('produto.*') ? 'sidebar__link--active' : '' }}">
                                <span class="sidebar__icon">&#9632;</span> Produtos
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar__group">
                    <button type="button" class="sidebar__group-header" onclick="toggleSidebarGroup(this)">
                        <span class="sidebar__group-label">FINANCEIRO</span>
                        <span class="sidebar__group-toggle">&#9660;</span>
                    </button>

                    <div class="sidebar__group-content">
                        <div class="sidebar__group-inner">
                            <a href="#" class="sidebar__link">
                                <span class="sidebar__icon">&#9632;</span> Contas a Pagar
                            </a>
                            <a href="#" class="sidebar__link">
                                <span class="sidebar__icon">&#9632;</span> Contas a Receber
                            </a>
                        </div>
                    </div>
                </div>

                <div class="sidebar__group">
                    <button type="button" class="sidebar__group-header" onclick="toggleSidebarGroup(this)">
                        <span class="sidebar__group-label">FISCAL</span>
                        <span class="sidebar__group-toggle">&#9660;</span>
                    </button>

                    <div class="sidebar__group-content">
                        <div class="sidebar__group-inner">
                            <a href="#" class="sidebar__link">
                                <span class="sidebar__icon">&#9632;</span> NF-e
                            </a>
                        </div>
                    </div>
                </div>

            </nav>
        </aside>

        <main class="main">
            <div class="page-bar">
                <div class="page-bar__title">
                    <h1 class="page-title">@yield('page_title')</h1>
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

    @stack('scripts')

</body>
<script>
    function toggleSidebarGroup(button) {
        const group = button.closest('.sidebar__group');

        group.classList.toggle('is-expanded');
    }
</script>

</html>