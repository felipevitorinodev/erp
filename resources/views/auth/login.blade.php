<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bem Vindo!</title>
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
            width: 320px;
            flex-shrink: 0;
            background: var(--color-primary);
            border-right: 3px solid var(--color-accent);
            display: flex;
            flex-direction: column;
            padding: 2rem 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            bottom: -60px;
            right: -60px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(214, 78, 18, .12);
        }

        .login-left::after {
            content: '';
            position: absolute;
            top: -40px;
            left: -40px;
            width: 160px;
            height: 160px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .04);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background: var(--color-accent);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .brand-name {
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
            line-height: 1.2;
        }

        .brand-sub {
            color: rgba(255, 255, 255, .5);
            font-size: .65rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            margin-top: 1px;
        }

        .left-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            position: relative;
            z-index: 1;
        }

        .left-label {
            font-size: .65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: rgba(255, 255, 255, .4);
            margin-bottom: .75rem;
        }

        .feature {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-bottom: .6rem;
        }

        .feature-dot {
            width: 5px;
            height: 5px;
            background: var(--color-accent);
            flex-shrink: 0;
        }

        .feature-text {
            font-size: .75rem;
            color: rgba(255, 255, 255, .65);
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
        }

        .login-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-text);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .3rem;
        }

        .login-sub {
            font-size: .78rem;
            color: var(--color-text-muted);
            margin-bottom: 1.75rem;
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
            margin-bottom: 1.4rem;
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
        }

        .footer-note {
            margin-top: 1.25rem;
            text-align: center;
            font-size: .7rem;
            color: var(--color-text-muted);
        }
    </style>
</head>

<body>

    <div class="login-page">

        <div class="login-left">
            <div class="brand">
                <div class="brand-icon">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <rect x="1" y="1" width="7" height="7" fill="white" opacity="0.9" />
                        <rect x="10" y="1" width="7" height="7" fill="white" opacity="0.5" />
                        <rect x="1" y="10" width="7" height="7" fill="white" opacity="0.5" />
                        <rect x="10" y="10" width="7" height="7" fill="white" opacity="0.9" />
                    </svg>
                </div>
                <div>
                    <div class="brand-name">Gestão Empresarial</div>
                    <div class="brand-sub">Facilitando a gestão da sua empresa!</div>
                </div>
            </div>

            <div class="left-body">
                <div class="left-label">Seja bem vindo!</div>
                <div class="feature">
                    <span class="feature-text">Somos um projeto independente e gratuito
                        de gestão Empresarial</span>
                </div>
            </div>
        </div>

        <div class="login-right">
            <div class="login-box">
                <div class="login-title">Acesso ao sistema</div>
                <div class="login-sub">Informe suas credenciais para continuar</div>

                @if($errors->any())
                    <div class="alert alert--error" style="margin-bottom:1.25rem;">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf

                    <div class="form-group">
                        <label class="form-label">E-mail</label>
                        <div class="form-input-wrap">
                            <svg class="form-input-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2" />
                                <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                            </svg>
                            <input type="email" name="email" class="form-control form-input-padded"
                                placeholder="seu@email.com" value="{{ old('email') }}" required autofocus>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Senha</label>
                        <div class="form-input-wrap">
                            <svg class="form-input-icon" width="14" height="14" viewBox="0 0 24 24" fill="none"
                                stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" />
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
                    Acesso restrito a usuários autorizados
                </div>
            </div>
        </div>

    </div>

</body>

</html>