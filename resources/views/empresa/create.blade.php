@extends('layouts.app')

@section('title', 'Nova Empresa')
@section('page_title', 'Nova Empresa')

@section('breadcrumb')
    <a href="{{ route('empresa.index') }}">Empresas</a> / Nova Empresa
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Empresa</span>
        </div>

        <form method="POST" action="{{ route('empresa.store') }}">
            @csrf

            <div class="card__body">

                <div class="form-section">
                    <div class="form-section__title">Identificação</div>
                    <div class="form-grid form-grid--col-2">
                        <div class="form-group">
                            <label class="form-label form-label--required">Razão Social</label>
                            <input type="text" name="razao_social"
                                class="form-control @error('razao_social') is-invalid @enderror"
                                value="{{ old('razao_social') }}" maxlength="150">
                            @error('razao_social')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nome Fantasia</label>
                            <input type="text" name="nome_fantasia"
                                class="form-control @error('nome_fantasia') is-invalid @enderror"
                                value="{{ old('nome_fantasia') }}" maxlength="150">
                            @error('nome_fantasia')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Inscrição Estadual</label>
                            <input type="text" name="ie" class="form-control @error('ie') is-invalid @enderror"
                                value="{{ old('ie') }}" maxlength="20">
                            @error('ie')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Inscrição Municipal</label>
                            <input type="text" name="im" class="form-control @error('im') is-invalid @enderror"
                                value="{{ old('im') }}" maxlength="20">
                            @error('im')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    @php
                        $semCnpj = filter_var(old('sem_cnpj', '0'), FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label {{ $semCnpj ? '' : 'form-label--required' }}" id="label-cnpj" for="cnpj">CNPJ</label>
                            <input type="text" name="cnpj" id="cnpj" class="form-control @error('cnpj') is-invalid @enderror"
                                value="{{ $semCnpj ? '' : old('cnpj') }}" maxlength="18" placeholder="00.000.000/0000-00">
                            @error('cnpj')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                            <input type="hidden" name="sem_cnpj" value="0">
                            <label class="form-check" for="sem_cnpj" style="margin-top: .65rem;">
                                <input type="checkbox" name="sem_cnpj" id="sem_cnpj" value="1" @checked($semCnpj)>
                                Cadastrar sem CNPJ
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Contato</div>
                    <div class="form-grid form-grid--col-3">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror"
                                value="{{ old('telefone') }}" maxlength="20">
                            @error('telefone')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control @error('celular') is-invalid @enderror"
                                value="{{ old('celular') }}" maxlength="20">
                            @error('celular')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" maxlength="100">
                            @error('email')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Endereço</div>
                    <div class="form-grid form-grid--col-4">
                        <div class="form-group">
                            <label class="form-label">CEP</label>
                            <input type="text" name="cep" class="form-control @error('cep') is-invalid @enderror"
                                value="{{ old('cep') }}" maxlength="9" placeholder="00000-000">
                            <small class="text-muted">Ao sair do campo, o endereço e o código IBGE são preenchidos.</small>
                            @error('cep')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código IBGE</label>
                            <input type="text" name="cod_ibge" maxlength="7" inputmode="numeric"
                                class="form-control @error('cod_ibge') is-invalid @enderror"
                                value="{{ old('cod_ibge') }}">
                            <small class="text-muted">7 dígitos do município. Preenchido pelo CEP.</small>
                            @error('cod_ibge')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group form-group--span-2">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" class="form-control @error('logradouro') is-invalid @enderror"
                                value="{{ old('logradouro') }}" maxlength="150">
                            @error('logradouro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" class="form-control @error('numero') is-invalid @enderror"
                                value="{{ old('numero') }}" maxlength="20">
                            @error('numero')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">UF</label>
                            <select name="uf" class="form-control @error('uf') is-invalid @enderror">
                                <option value="">Selecione</option>
                                @foreach(['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'] as $uf)
                                    <option value="{{ $uf }}" {{ old('uf') == $uf ? 'selected' : '' }}>{{ $uf }}</option>
                                @endforeach
                            </select>
                            @error('uf')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group form-group--span-2">
                            <label class="form-label">Complemento</label>
                            <input type="text" name="complemento"
                                class="form-control @error('complemento') is-invalid @enderror" value="{{ old('complemento') }}"
                                maxlength="100">
                            @error('complemento')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" class="form-control @error('bairro') is-invalid @enderror"
                                value="{{ old('bairro') }}" maxlength="100">
                            @error('bairro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control @error('cidade') is-invalid @enderror"
                                value="{{ old('cidade') }}" maxlength="100">
                            @error('cidade')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

            </div>

            <div class="card__footer">
                <a href="{{ route('empresa.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>

        </form>
    </div>

@endsection

@push('scripts')
    <script>
        (function () {
            var checkbox = document.getElementById('sem_cnpj');
            var campo = document.getElementById('cnpj');
            var label = document.getElementById('label-cnpj');
            if (!checkbox || !campo || !label) {
                return;
            }

            var anterior = campo.value;

            function aplicar() {
                var sem = checkbox.checked;
                campo.disabled = sem;
                label.classList.toggle('form-label--required', !sem);
                if (sem) {
                    if (campo.value !== '') {
                        anterior = campo.value;
                    }
                    campo.value = '';
                    return;
                }
                if (campo.value === '' && anterior !== '') {
                    campo.value = anterior;
                }
            }

            checkbox.addEventListener('change', aplicar);
            aplicar();
        })();
    </script>
@endpush
