@extends('layouts.auth')

@section('title', 'Esqueci a senha')

@section('content')
    <h1 class="auth-title">Recuperar senha</h1>
    <p class="auth-sub">Informe o e-mail da conta. Enviaremos um link para criar uma senha nova.</p>

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input id="email" type="email" name="email"
                class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}"
                autocomplete="username" required autofocus
                @error('email') aria-invalid="true" aria-describedby="erro-email" @enderror>
            @error('email')
                <span class="form-error" id="erro-email" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="auth-actions">
            <a href="{{ route('login') }}" class="btn btn--ghost">Voltar</a>
            <button type="submit" class="btn btn--primary" data-espera="Enviando...">Enviar link</button>
        </div>
    </form>
@endsection
