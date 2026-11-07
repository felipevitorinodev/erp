@php
    $usuarioId = old('usuario_id', isset($funcionario) ? $funcionario->usuario_id : null);
    $usuarioRotulo = '';
    if ($usuarioId) {
        $vinculado = isset($funcionario) ? $funcionario->usuario : null;
        $usuario = ($vinculado && (int) $vinculado->id === (int) $usuarioId)
            ? $vinculado
            : \App\Models\Usuarios\User::query()
                ->where('empresa_id', auth()->user()->empresa_id)
                ->whereKey($usuarioId)
                ->first(['id', 'name', 'email']);
        if ($usuario) {
            $usuarioRotulo = $usuario->name.($usuario->email ? ' — '.$usuario->email : '');
        }
    }
@endphp
<div class="form-group">
    <label class="form-label" for="input-usuario">Usuário do sistema</label>
    <div class="autocomplete-wrap">
        <input type="text" id="input-usuario"
            class="form-control @error('usuario_id') is-invalid @enderror"
            value="{{ $usuarioRotulo }}"
            placeholder="Buscar por nome ou e-mail"
            autocomplete="off">
        <input type="hidden" name="usuario_id" id="hidden-usuario-id" value="{{ $usuarioId }}">
    </div>
    @error('usuario_id')<span class="form-error">{{ $message }}</span>@enderror
</div>

@push('scripts')
<script>
    initAutocomplete('#input-usuario', '#hidden-usuario-id', @json(route('api.usuarios.busca')), function (item) {
        return item.name + (item.email ? ' — ' + item.email : '');
    });
</script>
@endpush
