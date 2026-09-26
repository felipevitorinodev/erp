@extends('layouts.auth')

@section('title', 'Nova senha')

@section('content')
    <h1 class="auth-title">Nova senha</h1>
    <p class="auth-sub">Escolha uma senha para voltar a acessar o sistema.</p>

    @if ($errors->any())
        <div class="alert alert--error" style="margin-bottom:1rem;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email', $email) }}"
                autocomplete="username" required>
        </div>
        <div class="form-group" style="margin-top:0.85rem;">
            <label class="form-label" for="password">Nova senha</label>
            <input id="password" type="password" name="password" class="form-control" autocomplete="new-password" required>
        </div>
        <div class="form-group" style="margin-top:0.85rem;">
            <label class="form-label" for="password_confirmation">Confirmar senha</label>
            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control"
                autocomplete="new-password" required>
        </div>
        <div class="auth-actions">
            <a href="{{ route('login') }}" class="btn btn--ghost">Voltar</a>
            <button type="submit" class="btn btn--primary">Salvar senha</button>
        </div>
    </form>
@endsection