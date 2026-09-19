<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visys — Acesso</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
        }

        .login-page {
            min-height: 100vh;
            display: flex;
        }

        .login-left {
            width: 340px;
            flex-shrink: 0;
            background: var(--color-primary);
            border-right: 3px solid var(--color-accent);
            display: flex;
            flex-direction: column;
            padding: 2rem 1.6rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            bottom: -40px;
            right: -40px;
            width: 180px;
            height: 180px;
            background: rgba(227, 90, 18, .14);
        }

        .login-left::after {
            content: '';
            position: absolute;
            top: -30px;
            left: -30px;
            width: 140px;
            height: 140px;
            background: rgba(255, 255, 255, .05);
        }

        .brand {
            display: flex;
            align-items: center;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .brand-logo {
            display: block;
            height: 72px;
            width: auto;
            max-width: 100%;
            object-fit: contain;
        }

        .left-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            z-index: 1;
            gap: .85rem;
        }

        .left-label {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255, 255, 255, .4);
        }

        .feature-text {
            font-size: .82rem;
            color: rgba(255, 255, 255, .72);
            line-height: 1.5;
            max-width: 16rem;
        }

        .feature-list {
            display: flex;
            flex-direction: column;
            gap: .45rem;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-size: .75rem;
            color: rgba(255, 255, 255, .65);
        }

        .feature-item::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--color-accent);
            flex-shrink: 0;
        }

        .login-right {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 2rem;
            background: var(--color-bg);
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-top: 3px solid var(--color-primary);
            padding: 1.75rem 1.5rem 1.5rem;
            box-shadow: var(--shadow-sm);
        }

        .login-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--color-primary);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .3rem;
        }

        .login-sub {
            font-size: .8rem;
            color: var(--color-text-muted);
            margin-bottom: 1.5rem;
        }

        .form-input-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .form-input-icon {
            position: absolute;
            left: .65rem;
            color: var(--color-text-muted);
        }

        .form-input-padded {
            padding-left: 2.1rem !important;
        }

        .remember-row {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 1rem 0 1.35rem;
        }

        .remember-row label {
            font-size: .75rem;
            color: var(--color-text-mid);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: .4rem;
        }

        .btn-login-full {
            width: 100%;
            justify-content: center;
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            min-height: 42px;
        }

        .footer-note {
            margin-top: 1.25rem;
            text-align: center;
            font-size: .7rem;
            color: var(--color-text-muted);
        }

        @media (max-width: 768px) {
            .login-page {
                flex-direction: column;
            }

            .login-left {
                width: 100%;
                min-height: auto;
                padding: 1.35rem 1.25rem 1.1rem;
            }

            .brand {
                margin-bottom: 0.85rem;
            }

            .left-body {
                display: none;
            }

            .login-right {
                padding: 1.35rem 1.1rem 2rem;
                align-items: flex-start;
            }

            .login-box {
                box-shadow: none;
            }
        }

        @media (max-width: 420px) {
            .brand-logo {
                height: 56px;
            }

            .login-title {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>

    <div class="login-page">

        <div class="login-left">
            <div class="brand">
                <img src="{{ asset('img/logo-sem-fundo.png') }}" alt="Visys" class="brand-logo">
            </div>

            <div class="left-body">
                <div class="left-label">Bem-vindo</div>
                <div class="feature-text">
                    Sistema de gestão feito para o dia a dia: cadastros, vendas, orçamentos e financeiro com foco em
                    clareza e agilidade.
                </div>
                <div class="feature-list">
                    <div class="feature-item">Cadastros centralizados</div>
                    <div class="feature-item">Vendas e orçamentos</div>
                    <div class="feature-item">Contas a pagar e receber</div>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-box">
                <div class="login-title">Acesso</div>
                <div class="login-sub">Informe suas credenciais para continuar</div>

                @if ($errors->any())
                    <div class="alert alert--error" style="margin-bottom:1.25rem;">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <div class="form-input-wrap">
                            <svg class="form-input-icon" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="0" />
                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                            </svg>
                            <input type="email" name="email" class="form-control form-input-padded"
                                placeholder="seu@email.com" value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top:0.85rem;">
                        <label class="form-label">Senha</label>
                        <div class="form-input-wrap">
                            <svg class="form-input-icon" width="14" height="14" viewBox="0 0 24 24"
                                fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="0" />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input type="password" name="password" class="form-control form-input-padded"
                                placeholder="••••••••" required>
                        </div>
                    </div>

                    <div class="remember-row">
                        <label>
                            <input type="checkbox" name="remember"> Lembrar-me neste dispositivo
                        </label>
                    </div>

                    <button type="submit" class="btn btn--primary btn-login-full">
                        Entrar
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </button>
                </form>

                <div class="footer-note">
                    Visys · Acesso restrito a usuários autorizados
                </div>
            </div>
        </div>

    </div>

</body>

</html>
