@php $usuario = $usuario ?? null; @endphp

<div class="form-section">
    <div class="form-section__title">Dados do usuário</div>
    <div class="form-grid form-grid--col-2">
        <div class="form-group">
            <label class="form-label form-label--required">Nome</label>
            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                value="{{ old('name', $usuario->name ?? '') }}" maxlength="255">
            @error('name')<span class="form-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label form-label--required">E-mail</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                value="{{ old('email', $usuario->email ?? '') }}" maxlength="255">
            @error('email')<span class="form-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label {{ $usuario ? '' : 'form-label--required' }}">Senha</label>
            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror"
                autocomplete="new-password">
            @if($usuario)
                <small class="text-muted">Deixe em branco para manter a senha atual.</small>
            @endif
            @error('password')<span class="form-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label {{ $usuario ? '' : 'form-label--required' }}">Confirmar senha</label>
            <input type="password" name="password_confirmation" class="form-control"
                autocomplete="new-password">
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section__title">Acesso</div>
    <div class="form-grid form-grid--col-2">
        <div class="form-group">
            <label class="form-label form-label--required">Perfil</label>
            <select name="perfil" class="form-control @error('perfil') is-invalid @enderror">
                @foreach(\App\Models\Usuarios\Perfil::where('empresa_id', auth()->user()->empresa_id)->where('ativo', true)->orderBy('nome')->get() as $perfilOpcao)
                    <option value="{{ $perfilOpcao->slug }}" {{ old('perfil', $usuario->perfil ?? 'vendedor') === $perfilOpcao->slug ? 'selected' : '' }}>
                        {{ $perfilOpcao->nome }}
                    </option>
                @endforeach
            </select>
            @error('perfil')<span class="form-error">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label">Situação</label>
            <label class="form-label">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1"
                    {{ old('ativo', $usuario->ativo ?? true) ? 'checked' : '' }}>
                Usuário ativo
            </label>
        </div>
    </div>
</div>
