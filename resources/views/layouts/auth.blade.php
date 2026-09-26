<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Acesso') — Visys</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>

<body class="auth-body">
    @include('partials.page-loader')
    <div class="auth-page">
        <aside class="auth-aside">
            <a href="{{ route('login') }}" class="auth-brand" title="Visys">
                <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys">
            </a>
            <div class="auth-aside__body">
                <p class="auth-kicker">Gestão do dia a dia</p>
                <p class="auth-pitch">Cadastros, vendas, estoque e financeiro no mesmo lugar, com a informação que a operação precisa.</p>
                <ul class="auth-points">
                    <li>Vendas e orçamentos</li>
                    <li>Estoque e entradas</li>
                    <li>Contas a pagar e receber</li>
                </ul>
            </div>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                @yield('content')
            </div>
        </main>
    </div>
    @include('partials.page-loader-script')
</body>

</html>
