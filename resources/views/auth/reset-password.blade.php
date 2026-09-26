<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visys — Redefinir senha</title>
    <link rel="icon" href="{{ asset('icone.ico') }}" type="image/x-icon">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--color-bg);padding:1rem;">
    <div class="card" style="width:100%;max-width:420px;">
        <div class="card__header">
            <span class="card__title">Nova senha</span>
        </div>
        <div class="card__body">
            @if ($errors->any())
                <div class="alert alert--error" style="margin-bottom:1rem;">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $email) }}" required>
                </div>
                <div class="form-group" style="margin-top:0.85rem;">
                    <label class="form-label">Nova senha</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="form-group" style="margin-top:0.85rem;">
                    <label class="form-label">Confirmar senha</label>
                    <input type="password" name="password_confirmation" class="form-control" required>
                </div>
                <div class="card__footer" style="margin-top:1rem;padding:0;border:0;justify-content:flex-end;">
                    <button type="submit" class="btn btn--primary btn--sm">Salvar nova senha</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
