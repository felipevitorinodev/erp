@extends('layouts.auth')

@section('title', 'Esqueci a senha')

@section('content')
    <h1 class="auth-title">Recuperar senha</h1>
    <p class="auth-sub">Informe o e-mail da conta. Enviaremos um link para criar uma senha nova.</p>

    @if (session('success'))
        <div class="alert alert--success" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert--error" style="margin-bottom:1rem;">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="form-group">
            <label class="form-label" for="email">E-mail</label>
            <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}"
                autocomplete="username" required autofocus>
        </div>
        <div class="auth-actions">
            <a href="{{ route('login') }}" class="btn btn--ghost">Voltar</a>
            <button type="submit" class="btn btn--primary">Enviar link</button>
        </div>
    </form>
@endsection
