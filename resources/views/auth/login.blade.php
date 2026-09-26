@extends('layouts.auth')

@section('title', 'Acesso')

@section('content')
    <h1 class="auth-title">Entrar</h1>
    <p class="auth-sub">Use o e-mail e a senha da sua conta para continuar.</p>

    @if ($errors->any())
        <div class="alert alert--error" style="margin-bottom:1.15rem;">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <div class="form-input-wrap">
                <svg class="form-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" />
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7" />
                </svg>
                <input id="email" type="email" name="email" class="form-control form-input-padded"
                    placeholder="seu@email.com" value="{{ old('email') }}" autocomplete="username" required autofocus>
            </div>
        </div>

        <div class="form-group" style="margin-top:0.85rem;">
            <label class="form-label" for="password">Senha</label>
            <div class="form-input-wrap">
                <svg class="form-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" />
                    <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                </svg>
                <input id="password" type="password" name="password" class="form-control form-input-padded"
                    placeholder="Sua senha" autocomplete="current-password" required>
            </div>
        </div>

        <div class="auth-row">
            <label for="remember">
                <input id="remember" type="checkbox" name="remember"> Lembrar neste dispositivo
            </label>
            <a href="{{ route('password.request') }}">Esqueci a senha</a>
        </div>

        <button type="submit" class="btn btn--primary auth-submit">Entrar</button>
    </form>

    <p class="auth-note">Acesso restrito a usuários autorizados.</p>
@endsection
