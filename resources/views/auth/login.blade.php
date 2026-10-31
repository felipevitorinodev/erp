@extends('layouts.auth')

@section('title', 'Acesso')

@section('content')
    @php
        $falhaLogin = $errors->has('login');
        $focarSenha = filled(old('email'));
    @endphp

    <h1 class="auth-title">Acesso</h1>
    <p class="auth-sub">Informe o e-mail e a senha da sua conta.</p>

    <form method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        @error('login')
            <p class="auth-erro" id="erro-login" role="alert">{{ $message }}</p>
        @enderror

        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input id="email" type="email" name="email" inputmode="email" autocapitalize="none" spellcheck="false"
                class="form-control @if($errors->has('email') || $falhaLogin) is-invalid @endif"
                value="{{ old('email') }}" autocomplete="username" required
                @if(! $focarSenha) autofocus @endif
                @if($errors->has('email') || $falhaLogin) aria-invalid="true" aria-describedby="{{ $errors->has('email') ? 'erro-email' : 'erro-login' }}" @endif>
            @error('email')
                <span class="form-error" id="erro-email" role="alert">{{ $message }}</span>
            @enderror
        </div>

        <div class="form-group">
            <div class="auth-campo__topo">
                <label class="form-label" for="password">Senha</label>
                <button type="button" class="auth-ver-senha" data-ver-senha="password" aria-pressed="false">Mostrar</button>
            </div>
            <input id="password" type="password" name="password"
                class="form-control @if($errors->has('password') || $falhaLogin) is-invalid @endif"
                autocomplete="current-password" required
                @if($focarSenha) autofocus @endif
                @if($errors->has('password') || $falhaLogin) aria-invalid="true" aria-describedby="{{ $errors->has('password') ? 'erro-password' : 'erro-login' }}" @endif>
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
@endsection
