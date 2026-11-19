<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#163A5F">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="{{ asset('js/tema.js') }}?v={{ filemtime(public_path('js/tema.js')) }}"></script>
    <title>@yield('title', 'Visys') — Visys</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ilustracoes.css') }}?v={{ filemtime(public_path('css/ilustracoes.css')) }}">

    @stack('styles')
    <link rel="stylesheet" href="{{ asset('css/tema.css') }}?v={{ filemtime(public_path('css/tema.css')) }}">
</head>

<body>
    <a href="#conteudo" class="skip-link">Ir para o conteúdo</a>
    @include('partials.page-loader')

    <header class="header">
        <button type="button" class="header__menu" id="btn-sidebar-toggle" aria-label="Abrir menu"
            aria-expanded="false" aria-controls="menu-lateral">
            <span class="header__menu-icon" aria-hidden="true"><span></span></span>
        </button>

        <div class="header__brand">
            <a href="/" class="header__brand-link" title="Visys">
                <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys" class="header__logo-img">
            </a>
        </div>

        <x-busca-rapida />

        @php
            $user = Auth::user();
            $userName = $user->name ?? 'Usuário';
            $userPerfil = match ($user->perfil ?? '') {
                'admin' => 'Administrador',
                'financeiro' => 'Financeiro',
                'operador' => 'Operador',
                default => 'Usuário',
            };
            // Relação carregada uma vez por requisição e reaproveitada pelas telas (Empresa::atual()).
            $empresa = $user->empresa_id ? \App\Models\Empresas\Empresa::atual() : null;
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
            <button type="button" class="header__logout header__theme-toggle" data-theme-toggle
                aria-label="Ativar tema escuro" title="Ativar tema escuro" aria-pressed="false">
                <svg class="header__theme-icon header__theme-icon--moon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.9 13A9 9 0 0 1 11 3.1 9 9 0 1 0 20.9 13Z"></path>
                </svg>
                <svg class="header__theme-icon header__theme-icon--sun" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"></circle>
                    <path d="M12 2v2m0 16v2M4.93 4.93l1.42 1.42m11.3 11.3 1.42 1.42M2 12h2m16 0h2M4.93 19.07l1.42-1.42m11.3-11.3 1.42-1.42"></path>
                </svg>
            </button>
            <form method="POST" action="{{ route('logout') }}" class="header__logout-form">
                @csrf
                <button type="submit" class="header__logout" title="Sair do sistema" aria-label="Sair">
                    <x-icon name="log-out" class="header__logout-icon" />
                    <span class="header__logout-text">Sair</span>
                </button>
            </form>
        </div>
    </header>

    <div class="sidebar-overlay" id="sidebar-overlay" hidden></div>

    <div class="layout">

        <aside class="sidebar sidebar--iniciando" id="menu-lateral">
            <x-menu-lateral />
        </aside>

        {{-- Roda logo após a sidebar (antes dos scripts externos) para o menu já ser desenhado recolhido. --}}
        <script>
            (function () {
                var sidebar = document.querySelector('.sidebar');

                document.querySelectorAll('[data-group]').forEach(function (group) {
                    var toggle = group.querySelector('[data-toggle]');
                    var links = group.querySelector('[data-links]');
                    var arrow = group.querySelector('.sidebar__group-arrow');
                    if (!toggle || !links || !arrow) return;

                    function marcar(recolhido) {
                        links.classList.toggle('sidebar__group-links--collapsed', recolhido);
                        arrow.classList.toggle('sidebar__group-arrow--collapsed', recolhido);
                        toggle.setAttribute('aria-expanded', recolhido ? 'false' : 'true');
                    }

                    marcar(!group.querySelector('.sidebar__link--active') && !group.hasAttribute('data-keep-open'));

                    toggle.addEventListener('click', function () {
                        marcar(!links.classList.contains('sidebar__group-links--collapsed'));
                    });
                });

                if (sidebar) {
                    try {
                        var rolagem = parseInt(sessionStorage.getItem('sidebar-scroll') || '0', 10);
                        if (rolagem > 0) sidebar.scrollTop = rolagem;
                        window.addEventListener('pagehide', function () {
                            sessionStorage.setItem('sidebar-scroll', String(sidebar.scrollTop));
                        });
                    } catch (e) {}

                    requestAnimationFrame(function () {
                        requestAnimationFrame(function () {
                            sidebar.classList.remove('sidebar--iniciando');
                        });
                    });
                }
            })();
        </script>

        <main class="main" id="conteudo">

            <div class="page-bar">
                <div class="page-bar__title">
                    @hasSection('breadcrumb')
                        <nav class="breadcrumb" aria-label="Trilha de navegação">@yield('breadcrumb')</nav>
                    @endif
                    <h1 class="page-title">@yield('page_title')</h1>
                </div>
                @hasSection('page_actions')
                    <div class="page-bar__actions">
                        @yield('page_actions')
                    </div>
                @endif
            </div>

            <div class="page-content">
                @yield('content')
            </div>

        </main>
    </div>

    <x-modal-ver />

    <div class="modal-overlay" id="modal-confirm" hidden>
        <div class="modal-box">
            <div class="modal-box__header">
                <span class="card__title" id="modal-confirm-title">Confirmar</span>
                <button type="button" class="btn btn--ghost btn--sm btn--icon" data-confirm-close aria-label="Fechar"><x-icon name="x" /></button>
            </div>
            <div class="modal-box__body">
                <p id="modal-confirm-message" class="modal-confirm__texto"></p>
            </div>
            <div class="modal-box__footer">
                <button type="button" class="btn btn--ghost" data-confirm-close>Voltar</button>
                <button type="button" class="btn btn--primary" id="modal-confirm-ok">Confirmar</button>
            </div>
        </div>
    </div>

    <script src="{{ asset('js/carregamento.js') }}?v={{ filemtime(public_path('js/carregamento.js')) }}"></script>
    <script src="{{ asset('js/ilustracoes.js') }}?v={{ filemtime(public_path('js/ilustracoes.js')) }}"></script>
    <script src="{{ asset('js/jquery.min.js') }}?v={{ filemtime(public_path('js/jquery.min.js')) }}"></script>
    <script src="{{ asset('js/autocomplete.js') }}?v={{ filemtime(public_path('js/autocomplete.js')) }}"></script>
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @include('partials.toast-stack')

    {{-- Modais são colocados fora de qualquer form --}}
    @stack('modals')

    @stack('scripts')

</body>

</html>
