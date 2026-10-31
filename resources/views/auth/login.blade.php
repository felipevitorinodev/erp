@extends('layouts.auth')

@section('title', 'Acesso')

@section('content')
    @php
        $falhaLogin = $errors->has('login');
        $focarSenha = filled(old('email'));
    @endphp

    <h1 class="auth-title">Entrar</h1>
    <p class="auth-sub">Use o e-mail e a senha da sua conta para continuar.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        @error('login')
            <p class="auth-erro" id="erro-login" role="alert">{{ $message }}</p>
        @enderror

        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <div class="form-input-wrap">
                <svg class="form-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                    <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"></path>
                </svg>
                <input id="email" type="email" name="email" inputmode="email" autocapitalize="none" spellcheck="false"
                    class="form-control form-input-padded @if($errors->has('email') || $falhaLogin) is-invalid @endif"
                    placeholder="seu@email.com" value="{{ old('email') }}" autocomplete="username" required
                    @if(! $focarSenha) autofocus @endif
                    @if($errors->has('email') || $falhaLogin) aria-invalid="true" aria-describedby="{{ $errors->has('email') ? 'erro-email' : 'erro-login' }}" @endif>
            </div>
            @error('email')
                <span class="form-error" id="erro-email" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <div class="auth-campo__topo">
                <label class="form-label" for="password">Senha</label>
                <button type="button" class="auth-ver-senha" data-ver-senha="password" aria-pressed="false">Mostrar</button>
            </div>
            <div class="form-input-wrap">
                <svg class="form-input-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                    stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="11" width="18" height="11" rx="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <input id="password" type="password" name="password"
                    class="form-control form-input-padded @if($errors->has('password') || $falhaLogin) is-invalid @endif"
                    placeholder="Sua senha" autocomplete="current-password" required
                    @if($focarSenha) autofocus @endif
                    @if($errors->has('password') || $falhaLogin) aria-invalid="true" aria-describedby="{{ $errors->has('password') ? 'erro-password' : 'erro-login' }}" @endif>
            </div>
            @error('password')
                <span class="form-error" id="erro-password" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="auth-row">
            <label for="remember">
                <input id="remember" type="checkbox" name="remember" @checked(old('remember'))> Lembrar neste dispositivo
            </label>
            <a href="{{ route('password.request') }}">Esqueci a senha</a>
        </div>

        <button type="submit" class="btn btn--primary auth-submit">Entrar</button>
    </form>

    <p class="auth-note">Acesso restrito a usuários autorizados.</p>
@endsection
