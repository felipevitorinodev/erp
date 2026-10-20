@extends('layouts.app')

@section('title', 'Editar Empresa')
@section('page_title', 'Editar Empresa')

@section('breadcrumb')
    <a href="{{ route('empresa.index') }}">Empresas</a> / Editar Empresa
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Empresa</span>
        </div>

        <form method="POST" action="{{ route('empresa.update', $empresa) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="card__body">
                <input type="hidden" name="id" value="{{ $empresa->id }}">

                <div class="form-section">
                    <div class="form-section__title">Identificação</div>
                    <div class="form-grid form-grid--col-2">
                        <div class="form-group">
                            <label class="form-label form-label--required">Razão Social</label>
                            <input type="text" name="razao_social"
                                class="form-control @error('razao_social') is-invalid @enderror"
                                value="{{ $empresa->razao_social }}" maxlength="255">
                            @error('razao_social')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nome Fantasia</label>
                            <input type="text" name="nome_fantasia"
                                class="form-control @error('nome_fantasia') is-invalid @enderror"
                                value="{{ $empresa->nome_fantasia }}" maxlength="255">
                            @error('nome_fantasia')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Inscrição Estadual</label>
                            <input type="text" name="ie" class="form-control @error('ie') is-invalid @enderror"
                                value="{{ $empresa->ie }}" maxlength="255">
                            @error('ie')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Inscrição Municipal</label>
                            <input type="text" name="im" class="form-control @error('im') is-invalid @enderror"
                                value="{{ $empresa->im }}" maxlength="255">
                            @error('im')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label form-label--required">CNPJ</label>
                            <input type="text" name="cnpj" class="form-control @error('cnpj') is-invalid @enderror"
                                value="{{ $empresa->cnpj }}" maxlength="18" placeholder="00.000.000/0000-00">
                            @error('cnpj')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Contato</div>
                    <div class="form-grid form-grid--col-3">
                        <div class="form-group">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control @error('telefone') is-invalid @enderror"
                                value="{{ $empresa->telefone }}" maxlength="20">
                            @error('telefone')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control @error('celular') is-invalid @enderror"
                                value="{{ $empresa->celular }}" maxlength="11">
                            @error('celular')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ $empresa->email }}" maxlength="255">
                            @error('email')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Endereço</div>
                    <div class="form-grid form-grid--col-4">
                        <div class="form-group form-group--span-2">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" class="form-control @error('logradouro') is-invalid @enderror"
                                value="{{ $empresa->logradouro }}" maxlength="255">
                            @error('logradouro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" class="form-control @error('numero') is-invalid @enderror"
                                value="{{ $empresa->numero }}" maxlength="20">
                            @error('numero')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">UF</label>
                            <select name="uf" class="form-control @error('uf') is-invalid @enderror">
                                <option value="">Selecione</option>
                                @foreach(['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'] as $uf)
                                    <option value="{{ $uf }}" {{ $empresa->uf == $uf ? 'selected' : '' }}>{{ $uf }}</option>
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
                                class="form-control @error('complemento') is-invalid @enderror"
                                value="{{ $empresa->complemento }}" maxlength="255">
                            @error('complemento')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" class="form-control @error('bairro') is-invalid @enderror"
                                value="{{ $empresa->bairro }}" maxlength="255">
                            @error('bairro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control @error('cidade') is-invalid @enderror"
                                value="{{ $empresa->cidade }}" maxlength="255">
                            @error('cidade')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                        Certificado Digital NF-e
                        @if ($empresa->certificado_path && is_file(storage_path('app/'.$empresa->certificado_path)))
                            <span class="badge badge--success">Certificado configurado</span>
                        @else
                            <span class="badge badge--warning">Sem certificado</span>
                        @endif
                    </div>
                    <div class="form-grid form-grid--col-2">
                        <div class="form-group">
                            <label class="form-label">Certificado A1 (.pfx)</label>
                            <input type="file" name="certificado_pfx" accept=".pfx,.p12"
                                class="form-control @error('certificado_pfx') is-invalid @enderror">
                            @if ($empresa->certificado_path)
                                <small class="text-muted">Arquivo atual: {{ basename($empresa->certificado_path) }}</small>
                            @endif
                            @error('certificado_pfx')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Senha do Certificado</label>
                            <input type="password" name="certificado_senha" autocomplete="new-password"
                                class="form-control @error('certificado_senha') is-invalid @enderror">
                            <small class="text-muted">Deixe em branco para manter a senha atual</small>
                            @error('certificado_senha')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Dados Fiscais</div>
                    @php
                        $crtEmpresa = (int) old('crt', $empresa->crt ?? 1);
                    @endphp
                    <div id="alerta-crt-normal" class="alert alert--warning" style="margin-bottom: 1rem; {{ $crtEmpresa === 3 ? '' : 'display: none;' }}">
                        Regime Normal não calcula Lucro Presumido nem Lucro Real nesta emissão. A nota usa o CSOSN informado abaixo.
                    </div>
                    <div class="form-grid form-grid--col-2">
                        <div class="form-group">
                            <label class="form-label form-label--required">Regime tributário (CRT)</label>
                            <select name="crt" id="crt" class="form-control @error('crt') is-invalid @enderror">
                                <option value="1" {{ $crtEmpresa === 1 ? 'selected' : '' }}>Simples Nacional</option>
                                <option value="2" {{ $crtEmpresa === 2 ? 'selected' : '' }}>Simples Nacional — excesso de sublimite</option>
                                <option value="3" {{ $crtEmpresa === 3 ? 'selected' : '' }}>Regime Normal</option>
                            </select>
                            @error('crt')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Código IBGE do município</label>
                            <input type="text" name="cod_ibge" maxlength="7" inputmode="numeric"
                                class="form-control @error('cod_ibge') is-invalid @enderror"
                                value="{{ old('cod_ibge', $empresa->cod_ibge) }}">
                            <small class="text-muted">Código IBGE do município (7 dígitos). Consulte: ibge.gov.br</small>
                            @error('cod_ibge')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title">Configurações NF-e</div>
                    @php
                        $ambienteNfe = (int) old('nfe_ambiente', $empresa->nfe_ambiente ?? 2);
                        $destinoNfe = (int) old('nfe_destino', $empresa->nfe_destino ?? 1);
                    @endphp
                    <div id="alerta-nfe-producao" class="alert alert--warning" style="margin-bottom: 1rem; {{ $ambienteNfe === 1 ? '' : 'display: none;' }}">
                        Atenção: em produção, as notas têm valor fiscal real e são enviadas à SEFAZ.
                    </div>
                    <div id="alerta-nfe-interestadual" class="alert alert--warning" style="margin-bottom: 1rem; {{ $destinoNfe === 2 ? '' : 'display: none;' }}">
                        Operação interestadual ainda não é emitida. A NF-e desta versão cobre apenas operação interna, com CFOP da série 5.
                    </div>
                    <div id="alerta-nfe-exterior" class="alert alert--warning" style="margin-bottom: 1rem; {{ $destinoNfe === 3 ? '' : 'display: none;' }}">
                        Operação exterior usa CFOP da série 7, UF EX e o país do destinatário. O cadastro de cliente ainda não tem país, então a emissão exterior fica bloqueada até esse dado existir.
                    </div>
                    <div class="form-grid form-grid--col-3">
                        <div class="form-group">
                            <label class="form-label form-label--required">Ambiente</label>
                            <select name="nfe_ambiente" id="nfe_ambiente"
                                class="form-control @error('nfe_ambiente') is-invalid @enderror">
                                <option value="2" {{ $ambienteNfe === 2 ? 'selected' : '' }}>Homologação</option>
                                <option value="1" {{ $ambienteNfe === 1 ? 'selected' : '' }}>Produção</option>
                            </select>
                            @error('nfe_ambiente')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                            <label style="display:flex; gap:.4rem; align-items:center; margin-top:.4rem; font-size:13px;">
                                <input type="checkbox" name="confirmar_ambiente" value="1">
                                Confirmo a alteração do ambiente
                            </label>
                            <small class="text-muted">Obrigatório apenas ao trocar entre Homologação e Produção.</small>
                            @error('confirmar_ambiente')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">Série</label>
                            <input type="text" name="nfe_serie" maxlength="3"
                                class="form-control @error('nfe_serie') is-invalid @enderror"
                                value="{{ old('nfe_serie', $empresa->nfe_serie ?? '001') }}">
                            @error('nfe_serie')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Próximo número</label>
                            <input type="text" class="form-control" readonly
                                value="{{ str_pad((string) ($empresa->nfe_sequencia ?? 1), 9, '0', STR_PAD_LEFT) }}">
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label form-label--required">Destino da operação</label>
                            <select name="nfe_destino" id="nfe_destino"
                                class="form-control @error('nfe_destino') is-invalid @enderror">
                                <option value="1" {{ $destinoNfe === 1 ? 'selected' : '' }}>Interna</option>
                                <option value="2" {{ $destinoNfe === 2 ? 'selected' : '' }}>Interestadual</option>
                                <option value="3" {{ $destinoNfe === 3 ? 'selected' : '' }}>Exterior</option>
                            </select>
                            @error('nfe_destino')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">CFOP padrão</label>
                            <input type="text" name="nfe_cfop_padrao" maxlength="4" inputmode="numeric"
                                class="form-control @error('nfe_cfop_padrao') is-invalid @enderror"
                                value="{{ old('nfe_cfop_padrao', $empresa->nfe_cfop_padrao ?? '5102') }}">
                            <small class="text-muted">Usado quando o produto não tem CFOP. Interna: 5102. Interestadual: série 6. Exterior: série 7.</small>
                            @error('nfe_cfop_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-3" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label form-label--required">CSOSN padrão</label>
                            <input type="text" name="nfe_csosn_padrao" maxlength="3" inputmode="numeric"
                                class="form-control @error('nfe_csosn_padrao') is-invalid @enderror"
                                value="{{ old('nfe_csosn_padrao', $empresa->nfe_csosn_padrao ?? '400') }}">
                            @error('nfe_csosn_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">CST PIS padrão</label>
                            <input type="text" name="nfe_cst_pis_padrao" maxlength="2" inputmode="numeric"
                                class="form-control @error('nfe_cst_pis_padrao') is-invalid @enderror"
                                value="{{ old('nfe_cst_pis_padrao', $empresa->nfe_cst_pis_padrao ?? '07') }}">
                            @error('nfe_cst_pis_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">CST COFINS padrão</label>
                            <input type="text" name="nfe_cst_cofins_padrao" maxlength="2" inputmode="numeric"
                                class="form-control @error('nfe_cst_cofins_padrao') is-invalid @enderror"
                                value="{{ old('nfe_cst_cofins_padrao', $empresa->nfe_cst_cofins_padrao ?? '07') }}">
                            @error('nfe_cst_cofins_padrao')
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
        document.getElementById('nfe_ambiente')?.addEventListener('change', function () {
            const alerta = document.getElementById('alerta-nfe-producao');
            if (!alerta) {
                return;
            }
            alerta.style.display = this.value === '1' ? '' : 'none';
        });

        document.getElementById('nfe_destino')?.addEventListener('change', function () {
            const interestadual = document.getElementById('alerta-nfe-interestadual');
            const exterior = document.getElementById('alerta-nfe-exterior');
            if (interestadual) {
                interestadual.style.display = this.value === '2' ? '' : 'none';
            }
            if (exterior) {
                exterior.style.display = this.value === '3' ? '' : 'none';
            }
        });

        document.getElementById('crt')?.addEventListener('change', function () {
            const alerta = document.getElementById('alerta-crt-normal');
            if (!alerta) {
                return;
            }
            alerta.style.display = this.value === '3' ? '' : 'none';
        });
    </script>
@endpush
