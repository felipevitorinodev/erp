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
        <main class="auth-main">
            <div class="auth-card">
                <div class="auth-marca">
                    <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys">
                </div>
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
    @include('partials.page-loader-script')
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
                if (botao.textContent.trim() === 'Entrar') botao.textContent = 'Entrando...';
                window.setTimeout(function () {
                    botao.disabled = true;
                }, 0);
            });
        });
    </script>
</body>

</html>
