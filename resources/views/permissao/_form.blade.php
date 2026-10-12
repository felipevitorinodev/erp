@php
    $perfil = $perfil ?? null;
    $admin = ($perfil->slug ?? '') === 'admin';
    $marcadas = old('permissoes', $perfil->permissoes ?? []);
    if (!is_array($marcadas)) {
        $marcadas = [];
    }
@endphp

<div class="form-section">
    <div class="form-section__title">Perfil</div>
    <div class="form-grid form-grid--col-2">
        <div class="form-group">
            <label class="form-label form-label--required">Nome</label>
            <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
                value="{{ old('nome', $perfil->nome ?? '') }}" maxlength="100" {{ $admin ? 'readonly' : '' }}>
            @error('nome')<span class="form-error">{{ $message }}</span>@enderror
        </div>
        <div class="form-group">
            <label class="form-label">Situação</label>
            <label class="form-label">
                <input type="hidden" name="ativo" value="0">
                <input type="checkbox" name="ativo" value="1" {{ $admin ? 'disabled' : '' }}
                    {{ old('ativo', $perfil->ativo ?? true) ? 'checked' : '' }}>
                Perfil ativo
            </label>
            @if($admin)
                <input type="hidden" name="ativo" value="1">
            @endif
        </div>
    </div>
    @if($admin)
        <p class="text-muted">O administrador tem todas as permissões. Essa lista não pode ser reduzida.</p>
    @endif
</div>

@error('permissoes')<div class="alert alert--error">{{ $message }}</div>@enderror

@foreach(\App\Support\CatalogoPermissoes::grupos() as $grupo => $permissoes)
    <div class="form-section">
        <div class="form-section__title">{{ $grupo }}</div>
        <div class="form-grid form-grid--col-2">
            @foreach($permissoes as $chave => $rotulo)
                <label class="form-label">
                    <input type="checkbox" name="permissoes[]" value="{{ $chave }}"
                        {{ $admin ? 'disabled' : '' }}
                        {{ $admin || in_array($chave, $marcadas, true) ? 'checked' : '' }}>
                    {{ $rotulo }}
                    @if($admin)
                        <input type="hidden" name="permissoes[]" value="{{ $chave }}">
                    @endif
                </label>
            @endforeach
        </div>
    </div>
@endforeach
