<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visys — Esqueci a senha</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--color-bg);padding:1rem;">
    <div class="card" style="width:100%;max-width:420px;">
        <div class="card__header">
            <span class="card__title">Recuperar senha</span>
        </div>
        <div class="card__body">
            <p class="text-muted" style="margin-bottom:1rem;font-size:13px;">
                Informe o e-mail da sua conta. Enviaremos um link para redefinir a senha.
            </p>

            @if (session('success'))
                <div class="alert alert--success" style="margin-bottom:1rem;">{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert--error" style="margin-bottom:1rem;">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus>
                </div>
                <div class="card__footer" style="margin-top:1rem;padding:0;border:0;justify-content:space-between;">
                    <a href="{{ route('login') }}" class="btn btn--ghost btn--sm">Voltar</a>
                    <button type="submit" class="btn btn--primary btn--sm">Enviar link</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
