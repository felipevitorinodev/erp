@extends('layouts.app')

@section('title', 'Editar Cliente')
@section('page_title', 'Editar Cliente')

@section('breadcrumb')
    <a href="{{ route('cliente.index') }}">Clientes</a> / Editar Cliente
@endsection

@section('content')

<div class="card">
    <div class="card__header">
        <span class="card__title">Dados do Cliente</span>
    </div>

    <form method="POST" action="{{ route('cliente.update', $cliente) }}">
        @csrf
        @method('PUT')
        <input type="hidden" name="empresa_id" value="{{ Auth()->user()->empresa_id }}">
        <div class="card__body">

            <div class="form-grid form-grid--col-2">
                <div class="form-group">
                    <label class="form-label form-label--required">Nome / Razão Social</label>
                    <input type="text" name="nome"
                        class="form-control @error('nome') is-invalid @enderror"
                        value="{{ old('nome', $cliente->nome) }}" maxlength="255">
                    @error('nome')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Nome Fantasia</label>
                    <input type="text" name="nome_fantasia"
                        class="form-control @error('nome_fantasia') is-invalid @enderror"
                        value="{{ old('nome_fantasia', $cliente->nome_fantasia) }}" maxlength="255">
                    @error('nome_fantasia')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select name="tipo_pessoa" id="tipo_pessoa"
                        class="form-control @error('tipo_pessoa') is-invalid @enderror"
                        onchange="toggleDocumento(this.value, true)">
                        <option value="PF" {{ old('tipo_pessoa', $cliente->cpf ? 'PF' : 'PJ') == 'PF' ? 'selected' : '' }}>Pessoa Física</option>
                        <option value="PJ" {{ old('tipo_pessoa', $cliente->cpf ? 'PF' : 'PJ') == 'PJ' ? 'selected' : '' }}>Pessoa Jurídica</option>
                    </select>
                    @error('tipo_pessoa')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group" id="campo_cpf">
                    <label class="form-label">CPF</label>
                    <input type="text" name="cpf"
                        class="form-control @error('cpf') is-invalid @enderror"
                        value="{{ old('cpf', $cliente->cpf ?? '') }}" maxlength="14" placeholder="000.000.000-00">
                    @error('cpf')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group" id="campo_cnpj">
                    <label class="form-label">CNPJ</label>
                    <input type="text" name="cnpj"
                        class="form-control @error('cnpj') is-invalid @enderror"
                        value="{{ old('cnpj', $cliente->cnpj ?? '') }}" maxlength="18" placeholder="00.000.000/0000-00">
                    @error('cnpj')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Inscrição Estadual</label>
                    <input type="text" name="inscricao_estadual"
                        class="form-control @error('inscricao_estadual') is-invalid @enderror"
                        value="{{ old('inscricao_estadual', $cliente->inscricao_estadual) }}" maxlength="255">
                    @error('inscricao_estadual')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Inscrição Municipal</label>
                    <input type="text" name="inscricao_municipal"
                        class="form-control @error('inscricao_municipal') is-invalid @enderror"
                        value="{{ old('inscricao_municipal', $cliente->inscricao_municipal) }}" maxlength="255">
                    @error('inscricao_municipal')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-3" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Telefone</label>
                    <input type="text" name="telefone"
                        class="form-control @error('telefone') is-invalid @enderror"
                        value="{{ old('telefone', $cliente->telefone) }}" maxlength="20" placeholder="(00) 0000-0000">
                    @error('telefone')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Celular</label>
                    <input type="text" name="celular"
                        class="form-control @error('celular') is-invalid @enderror"
                        value="{{ old('celular', $cliente->celular) }}" maxlength="20" placeholder="(00) 00000-0000">
                    @error('celular')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">E-mail</label>
                    <input type="email" name="email"
                        class="form-control @error('email') is-invalid @enderror"
                        value="{{ old('email', $cliente->email) }}" maxlength="255">
                    @error('email')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            {{-- ENDEREÇO --}}
            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">CEP</label>
                    <input type="text" name="cep"
                        class="form-control @error('cep') is-invalid @enderror"
                        value="{{ old('cep', $cliente->cep) }}" maxlength="9" placeholder="00000-000">
                    @error('cep')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group form-group--span-2">
                    <label class="form-label">Logradouro</label>
                    <input type="text" name="logradouro"
                        class="form-control @error('logradouro') is-invalid @enderror"
                        value="{{ old('logradouro', $cliente->logradouro) }}" maxlength="255">
                    @error('logradouro')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Número</label>
                    <input type="text" name="numero"
                        class="form-control @error('numero') is-invalid @enderror"
                        value="{{ old('numero', $cliente->numero) }}" maxlength="20">
                    @error('numero')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Bairro</label>
                    <input type="text" name="bairro"
                        class="form-control @error('bairro') is-invalid @enderror"
                        value="{{ old('bairro', $cliente->bairro) }}" maxlength="255">
                    @error('bairro')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group form-group--span-2">
                    <label class="form-label">Complemento</label>
                    <input type="text" name="complemento"
                        class="form-control @error('complemento') is-invalid @enderror"
                        value="{{ old('complemento', $cliente->complemento) }}" maxlength="255">
                    @error('complemento')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group">
                    <label class="form-label">Cidade</label>
                    <input type="text" name="cidade"
                        class="form-control @error('cidade') is-invalid @enderror"
                        value="{{ old('cidade', $cliente->cidade) }}" maxlength="255">
                    @error('cidade')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

            <div class="form-grid form-grid--col-4" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-control @error('estado') is-invalid @enderror">
                        <option value="">Selecione</option>
                        @foreach(['AC','AL','AM','AP','BA','CE','DF','ES','GO','MA','MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN','RO','RR','RS','SC','SE','SP','TO'] as $uf)
                            <option value="{{ $uf }}" {{ old('estado', $cliente->estado) == $uf ? 'selected' : '' }}>{{ $uf }}</option>
                        @endforeach
                    </select>
                    @error('estado')<span class="form-error">{{ $message }}</span>@enderror
                </div>

                <div class="form-group form-group--span-3">
                    <label class="form-label">Observações</label>
                    <textarea style="resize:none;" name="observacoes"
                        class="form-control @error('observacoes') is-invalid @enderror"
                        rows="1" maxlength="1000">{{ old('observacoes', $cliente->observacoes) }}</textarea>
                    @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>

        </div>

        <div class="card__footer">
            <a href="{{ route('cliente.index') }}" class="btn btn--ghost">Cancelar</a>
            <button type="submit" class="btn btn--primary">Salvar</button>
        </div>

    </form>
</div>

@push('scripts')
<script>
    function toggleDocumento(tipo, limpar) {
        var cpf  = document.getElementById('campo_cpf');
        var cnpj = document.getElementById('campo_cnpj');

        if (tipo === 'PF') {
            cpf.style.display  = 'flex';
            cnpj.style.display = 'none';
            if (limpar) cnpj.querySelector('input').value = '';
        } else {
            cpf.style.display  = 'none';
            cnpj.style.display = 'flex';
            if (limpar) cpf.querySelector('input').value = '';
        }
    }

    toggleDocumento(document.getElementById('tipo_pessoa').value, false);
</script>
@endpush

@endsection