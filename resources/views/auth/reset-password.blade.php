@extends('layouts.auth')

@section('title', 'Nova senha')

@section('content')
    <h1 class="auth-title">Nova senha</h1>
    <p class="auth-sub">Escolha uma senha para voltar a acessar o sistema.</p>

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input id="email" type="email" name="email"
                class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $email) }}"
                autocomplete="username" required
                @error('email') aria-invalid="true" aria-describedby="erro-email" @enderror>
            @error('email')
                <span class="form-error" id="erro-email" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="form-group">
            <div class="auth-campo__topo">
                <label class="form-label" for="password">Nova senha</label>
                <button type="button" class="auth-ver-senha" data-ver-senha="password" aria-pressed="false">Mostrar</button>
            </div>
            <input id="password" type="password" name="password"
                class="form-control @error('password') is-invalid @enderror" autocomplete="new-password" required
                @error('password') aria-invalid="true" aria-describedby="erro-password" @enderror>
            @error('password')
                <span class="form-error" id="erro-password" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="form-group">
            <div class="auth-campo__topo">
                <label class="form-label" for="password_confirmation">Confirmar senha</label>
                <button type="button" class="auth-ver-senha" data-ver-senha="password_confirmation" aria-pressed="false">Mostrar</button>
            </div>
            <input id="password_confirmation" type="password" name="password_confirmation"
                class="form-control @error('password_confirmation') is-invalid @enderror"
                autocomplete="new-password" required
                @error('password_confirmation') aria-invalid="true" aria-describedby="erro-password-confirmation" @enderror>
            @error('password_confirmation')
                <span class="form-error" id="erro-password-confirmation" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="auth-actions">
            <a href="{{ route('login') }}" class="btn btn--ghost">Voltar</a>
            <button type="submit" class="btn btn--primary" data-espera="Salvando...">Salvar senha</button>
        </div>
    </form>
@endsection