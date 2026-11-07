<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <title>@yield('title', 'Acesso') — Visys</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>

<body class="auth-body">
    @include('partials.page-loader')
    <div class="auth-page">
        <aside class="auth-aside">
            <div class="auth-aside__topo">
                <a href="{{ route('login') }}" class="auth-brand" title="Visys">
                    <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys">
                </a>
                <p class="auth-aside__linha">Sistemas &amp; Gestão Empresarial</p>
            </div>

            <div class="auth-aside__body">
                <p class="auth-kicker">Gestão do dia a dia</p>
                <h2 class="auth-pitch">Cadastros, vendas, estoque e financeiro no mesmo lugar.</h2>
                <p class="auth-lead">A informação que a operação precisa para trabalhar sem sair do sistema.</p>
                <ul class="auth-points">
                    <li>
                        <span class="auth-points__mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"></path>
                                <path d="M3 6h18"></path>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                        </span>
                        <span>Vendas e orçamentos</span>
                    </li>
                    <li>
                        <span class="auth-points__mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path>
                                <path d="m3.3 7 8.7 5 8.7-5"></path>
                                <path d="M12 22V12"></path>
                            </svg>
                        </span>
                        <span>Estoque e entradas</span>
                    </li>
                    <li>
                        <span class="auth-points__mark" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="5" width="20" height="14" rx="2"></rect>
                                <path d="M2 10h20"></path>
                            </svg>
                        </span>
                        <span>Contas a pagar e receber</span>
                    </li>
                </ul>
            </div>

            <p class="auth-aside__rodape">&copy; {{ date('Y') }} · Visys · Gestão empresarial</p>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-card__corpo">
                    @if (session('warning'))
                        <p class="auth-aviso" role="alert">{{ session('warning') }}</p>
                    @endif
                    @if (session('success'))
                        <p class="auth-ok" role="status">{{ session('success') }}</p>
                    @endif
                    <p class="auth-erro" id="auth-rede" role="alert" hidden>Sem conexão. Verifique a internet e tente novamente.</p>
                    @yield('content')
                </div>
            </div>
        </main>
    </div>
    <script src="{{ asset('js/carregamento.js') }}?v={{ filemtime(public_path('js/carregamento.js')) }}"></script>
    @include('partials.toast-stack', ['ignorarErros' => true, 'ignorarSessao' => true])
    <script>
        document.querySelectorAll('[data-ver-senha]').forEach(function (botao) {
            botao.addEventListener('click', function () {
                var campo = document.getElementById(botao.getAttribute('data-ver-senha'));
                if (!campo) return;
                var mostrar = campo.type === 'password';
                campo.type = mostrar ? 'text' : 'password';
                botao.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
                botao.textContent = mostrar ? 'Ocultar' : 'Mostrar';
                campo.focus();
            });
        });

        document.querySelectorAll('.auth-card form').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var botao = form.querySelector('[type="submit"]');
                var rede = document.getElementById('auth-rede');
                if (!botao) return;

                if (botao.dataset.enviando === '1') {
                    event.preventDefault();
                    return;
                }

                if (navigator.onLine === false) {
                    event.preventDefault();
                    if (rede) rede.hidden = false;
                    return;
                }

                if (rede) rede.hidden = true;
                botao.dataset.enviando = '1';
                botao.setAttribute('aria-busy', 'true');
                botao.classList.add('is-loading');
                var espera = botao.getAttribute('data-espera');
                if (espera) botao.textContent = espera;
                window.setTimeout(function () {
                    botao.disabled = true;
                }, 0);
            });
        });
    </script>
</body>

</html>
