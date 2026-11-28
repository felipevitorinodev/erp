@extends('layouts.app')

@section('title', 'Editar empresa')
@section('page_title', 'Editar empresa')

@section('breadcrumb')
    <a href="{{ route('empresa.index') }}">Empresas</a> / Editar empresa
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da empresa</span>
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
                            <label class="form-label form-label--required">Razão social</label>
                            <input type="text" name="razao_social"
                                class="form-control @error('razao_social') is-invalid @enderror"
                                value="{{ old('razao_social', $empresa->razao_social) }}" maxlength="150">
                            @error('razao_social')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Nome fantasia</label>
                            <input type="text" name="nome_fantasia"
                                class="form-control @error('nome_fantasia') is-invalid @enderror"
                                value="{{ old('nome_fantasia', $empresa->nome_fantasia) }}" maxlength="150">
                            @error('nome_fantasia')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Inscrição estadual</label>
                            <input type="text" name="ie" class="form-control @error('ie') is-invalid @enderror"
                                value="{{ old('ie', $empresa->ie) }}" maxlength="20">
                            @error('ie')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Inscrição municipal</label>
                            <input type="text" name="im" class="form-control @error('im') is-invalid @enderror"
                                value="{{ old('im', $empresa->im) }}" maxlength="20">
                            @error('im')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    @php
                        $semCnpj = filter_var(old('sem_cnpj', filled($empresa->cnpj) ? '0' : '1'), FILTER_VALIDATE_BOOLEAN);
                    @endphp
                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label {{ $semCnpj ? '' : 'form-label--required' }}" id="label-cnpj" for="cnpj">CNPJ</label>
                            <input type="text" name="cnpj" id="cnpj" class="form-control @error('cnpj') is-invalid @enderror"
                                value="{{ $semCnpj ? '' : old('cnpj', $empresa->cnpj) }}" maxlength="18"
                                placeholder="00.000.000/0000-00">
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
                                value="{{ old('telefone', $empresa->telefone) }}" maxlength="20">
                            @error('telefone')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control @error('celular') is-invalid @enderror"
                                value="{{ old('celular', $empresa->celular) }}" maxlength="20">
                            @error('celular')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">E-mail</label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email', $empresa->email) }}" maxlength="100">
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
                                value="{{ old('cep', $empresa->cep) }}" maxlength="9" placeholder="00000-000">
                            <small class="text-muted">Ao sair do campo, o endereço e o código IBGE são preenchidos.</small>
                            @error('cep')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="form-grid form-grid--col-4" style="margin-top: 1rem;">
                        <div class="form-group form-group--span-2">
                            <label class="form-label">Logradouro</label>
                            <input type="text" name="logradouro" class="form-control @error('logradouro') is-invalid @enderror"
                                value="{{ old('logradouro', $empresa->logradouro) }}" maxlength="150">
                            @error('logradouro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Número</label>
                            <input type="text" name="numero" class="form-control @error('numero') is-invalid @enderror"
                                value="{{ old('numero', $empresa->numero) }}" maxlength="20">
                            @error('numero')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">UF</label>
                            <select name="uf" class="form-control @error('uf') is-invalid @enderror">
                                <option value="">Selecione</option>
                                @foreach(['AC', 'AL', 'AM', 'AP', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MG', 'MS', 'MT', 'PA', 'PB', 'PE', 'PI', 'PR', 'RJ', 'RN', 'RO', 'RR', 'RS', 'SC', 'SE', 'SP', 'TO'] as $uf)
                                    <option value="{{ $uf }}" {{ old('uf', $empresa->uf) == $uf ? 'selected' : '' }}>{{ $uf }}</option>
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
                                value="{{ old('complemento', $empresa->complemento) }}" maxlength="100">
                            @error('complemento')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Bairro</label>
                            <input type="text" name="bairro" class="form-control @error('bairro') is-invalid @enderror"
                                value="{{ old('bairro', $empresa->bairro) }}" maxlength="100">
                            @error('bairro')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control @error('cidade') is-invalid @enderror"
                                value="{{ old('cidade', $empresa->cidade) }}" maxlength="100">
                            @error('cidade')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <div class="form-section__title" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                        Certificado digital NF-e
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
                            <label class="form-label">Senha do certificado</label>
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
                    <div class="form-section__title">Dados fiscais</div>
                    @php
                        $crtEmpresa = (int) old('crt', $empresa->crt ?? 1);
                    @endphp
                    <div id="alerta-crt-normal" class="alert alert--warning" style="margin-bottom: 1rem; {{ $crtEmpresa === 3 ? '' : 'display: none;' }}">
                        Regime Normal não emite por esta tela. Simples Nacional e MEI emitem com CSOSN, sem destaque de ICMS, PIS e COFINS.
                    </div>
                    <div class="form-grid form-grid--col-2">
                        <div class="form-group">
                            <label class="form-label form-label--required">Regime tributário (CRT)</label>
                            <select name="crt" id="crt" class="form-control @error('crt') is-invalid @enderror">
                                <option value="1" {{ $crtEmpresa === 1 ? 'selected' : '' }}>Simples Nacional</option>
                                <option value="4" {{ $crtEmpresa === 4 ? 'selected' : '' }}>Simples Nacional — MEI</option>
                                <option value="2" {{ $crtEmpresa === 2 ? 'selected' : '' }}>Simples Nacional — excesso de sublimite</option>
                                <option value="3" {{ $crtEmpresa === 3 ? 'selected' : '' }}>Regime Normal</option>
                            </select>
                            <small class="text-muted">Simples Nacional e MEI emitem com CSOSN, sem destaque de ICMS, PIS e COFINS. O excesso de sublimite, nesta versão, segue a mesma regra. Regime Normal ainda não emite por esta tela.</small>
                            @error('crt')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">Código IBGE do município</label>
                            <input type="text" name="cod_ibge" maxlength="7" inputmode="numeric"
                                class="form-control @error('cod_ibge') is-invalid @enderror"
                                value="{{ old('cod_ibge', $empresa->cod_ibge) }}">
                            <small class="text-muted">7 dígitos. Preenchido pelo CEP; confira antes de salvar.</small>
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
                                value="{{ old('nfe_csosn_padrao', $empresa->nfe_csosn_padrao ?? '102') }}">
                            @error('nfe_csosn_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">CST PIS padrão</label>
                            <input type="text" name="nfe_cst_pis_padrao" maxlength="2" inputmode="numeric"
                                class="form-control @error('nfe_cst_pis_padrao') is-invalid @enderror"
                                value="{{ old('nfe_cst_pis_padrao', $empresa->nfe_cst_pis_padrao ?? '99') }}">
                            @error('nfe_cst_pis_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required">CST COFINS padrão</label>
                            <input type="text" name="nfe_cst_cofins_padrao" maxlength="2" inputmode="numeric"
                                class="form-control @error('nfe_cst_cofins_padrao') is-invalid @enderror"
                                value="{{ old('nfe_cst_cofins_padrao', $empresa->nfe_cst_cofins_padrao ?? '99') }}">
                            @error('nfe_cst_cofins_padrao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-section" id="nfce">
                    <div class="form-section__title">Configurações NFC-e</div>
                    <p class="text-muted" style="margin-bottom: 1rem;">
                        O CSC é gravado criptografado e a tela só mostra se está preenchido ou vazio.
                        A série 001 é provisória até o contador informar a série definitiva. Ela não altera a série da NF-e.
                        O ambiente (homologação ou produção) e o certificado são os mesmos da NF-e.
                    </p>
                    <label style="display:flex; gap:.5rem; align-items:flex-start; margin-bottom: 1rem;">
                        <input type="hidden" name="nfce_emitir_pdv" value="0">
                        <input type="checkbox" name="nfce_emitir_pdv" value="1" @checked(old('nfce_emitir_pdv', $empresa->nfce_emitir_pdv))>
                        <span>Emitir NFC-e ao finalizar a venda no PDV</span>
                    </label>
                    <div class="form-grid form-grid--col-3">
                        <div class="form-group">
                            <label class="form-label">Série da NFC-e</label>
                            <input type="text" name="nfce_serie" maxlength="3" inputmode="numeric"
                                class="form-control @error('nfce_serie') is-invalid @enderror"
                                value="{{ old('nfce_serie', $empresa->nfce_serie ?? '001') }}">
                            @error('nfce_serie')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                                CSC de homologação
                                @if (filled($empresa->nfce_csc_homologacao))
                                    <span class="badge badge--success">Preenchido</span>
                                @else
                                    <span class="badge badge--neutral">Vazio</span>
                                @endif
                            </label>
                            <input type="password" name="nfce_csc_homologacao" autocomplete="new-password" maxlength="36"
                                class="form-control @error('nfce_csc_homologacao') is-invalid @enderror">
                            <small class="text-muted">Deixe em branco para manter o CSC atual. O valor gravado não volta para a tela.</small>
                            @error('nfce_csc_homologacao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">idCSC de homologação</label>
                            <input type="text" name="nfce_id_csc_homologacao" maxlength="6" inputmode="numeric" autocomplete="off"
                                class="form-control @error('nfce_id_csc_homologacao') is-invalid @enderror"
                                value="{{ old('nfce_id_csc_homologacao', $empresa->nfce_id_csc_homologacao) }}">
                            <small class="text-muted">Até 6 dígitos. Este identificador entra no QR Code. O CSC não.</small>
                            @error('nfce_id_csc_homologacao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
                        <div class="form-group">
                            <label class="form-label" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                                CSC de produção
                                @if (filled($empresa->nfce_csc_producao))
                                    <span class="badge badge--success">Preenchido</span>
                                @else
                                    <span class="badge badge--neutral">Vazio</span>
                                @endif
                            </label>
                            <input type="password" name="nfce_csc_producao" autocomplete="new-password" maxlength="36"
                                class="form-control @error('nfce_csc_producao') is-invalid @enderror">
                            <small class="text-muted">Deixe em branco para manter o CSC atual. O valor gravado não volta para a tela.</small>
                            @error('nfce_csc_producao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label">idCSC de produção</label>
                            <input type="text" name="nfce_id_csc_producao" maxlength="6" inputmode="numeric" autocomplete="off"
                                class="form-control @error('nfce_id_csc_producao') is-invalid @enderror"
                                value="{{ old('nfce_id_csc_producao', $empresa->nfce_id_csc_producao) }}">
                            @error('nfce_id_csc_producao')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    {{-- Conferência sem emitir nota. O formulário fica fora deste (não dá para aninhar forms). --}}
                    <div class="nfce-conferencia">
                        <div class="nfce-conferencia__topo">
                            <div>
                                <p class="nfce-conferencia__titulo">Conferir a NFC-e</p>
                                <p class="text-muted">Confere certificado, CSC, dados da empresa e a SEFAZ sem emitir nota. Salve as alterações antes.</p>
                            </div>
                            <button type="submit" form="form-verificar-nfce" class="btn btn--ghost btn--sm"
                                data-espera="Conferindo..."
                                data-demora="Consultando a SEFAZ.">Conferir agora</button>
                        </div>
                        @if (is_array(session('nfce_verificacao')))
                            <ul class="nfce-conferencia__lista" aria-label="Resultado da conferência">
                                @foreach (session('nfce_verificacao') as $item)
                                    @php $tom = ! $item['ok'] ? 'erro' : (! empty($item['aviso']) ? 'aviso' : 'ok'); @endphp
                                    <li class="nfce-conferencia__item nfce-conferencia__item--{{ $tom }}">
                                        <x-icon :name="$tom === 'erro' ? 'x-circle' : ($tom === 'aviso' ? 'alert-triangle' : 'check')" :size="16" />
                                        <span><strong>{{ $item['titulo'] }}:</strong> {{ $item['texto'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @php $falhas = collect(session('nfce_verificacao'))->where('ok', false)->count(); @endphp
                            <p class="nfce-conferencia__conclusao">
                                {{ $falhas === 0 ? 'Tudo certo para emitir NFC-e.' : ($falhas === 1 ? 'Corrija o item marcado antes de emitir.' : 'Corrija os '.$falhas.' itens marcados antes de emitir.') }}
                            </p>
                        @endif
                    </div>
                </div>


                <div class="form-section" id="nfse">
                    <div class="form-section__title">Configurações NFS-e (nota de serviço)</div>
                    @php
                        $ambienteNfse = (int) old('nfse_ambiente', $empresa->nfse_ambiente ?? 2);
                        $regimeEspecialNfse = (int) old('nfse_regime_especial', $empresa->nfse_regime_especial ?? 0);
                        $regimeApuracaoNfse = (string) old('nfse_regime_apuracao_sn', $empresa->nfse_regime_apuracao_sn ?? '');
                        $percentual = fn (string $campo) => old($campo, $empresa->{$campo} !== null ? number_format((float) $empresa->{$campo}, 2, ',', '') : '');
                    @endphp
                    <p class="text-muted" style="margin-bottom: 1rem;">
                        NFS-e do Sistema Nacional (Sefin Nacional), emitida pela venda com os itens do tipo Serviço.
                        Usa o mesmo certificado da NF-e. O município da empresa precisa ser conveniado ao Sistema Nacional.
                    </p>
                    <div id="alerta-nfse-producao" class="alert alert--warning" style="margin-bottom: 1rem; {{ $ambienteNfse === 1 ? '' : 'display: none;' }}">
                        Atenção: em produção, as NFS-e têm valor fiscal e só podem ser canceladas por evento.
                    </div>
                    <div class="form-grid form-grid--col-3">
                        <div class="form-group">
                            <label class="form-label form-label--required" for="nfse_ambiente">Ambiente</label>
                            <select name="nfse_ambiente" id="nfse_ambiente" class="form-control @error('nfse_ambiente') is-invalid @enderror">
                                <option value="2" @selected($ambienteNfse === 2)>Produção restrita (homologação)</option>
                                <option value="1" @selected($ambienteNfse === 1)>Produção</option>
                            </select>
                            @error('nfse_ambiente')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                            <label style="display:flex; gap:.4rem; align-items:center; margin-top:.4rem; font-size:13px;">
                                <input type="checkbox" name="confirmar_ambiente_nfse" value="1">
                                Confirmo a alteração do ambiente
                            </label>
                            <small class="text-muted">Obrigatório apenas ao trocar o ambiente.</small>
                            @error('confirmar_ambiente_nfse')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="nfse_serie">Série da DPS</label>
                            <input type="text" name="nfse_serie" id="nfse_serie" maxlength="5" inputmode="numeric" placeholder="1"
                                class="form-control @error('nfse_serie') is-invalid @enderror"
                                value="{{ old('nfse_serie', $empresa->nfse_serie) }}">
                            @error('nfse_serie')
                                <span class="form-error">{{ $message }}</span>
                            @else
                                <small class="text-muted">De 1 a 49999. Em branco, usa a série 1. A numeração da DPS é própria de cada série e ambiente.</small>
                            @enderror
                        </div>

                        <div class="form-group">
                            <label class="form-label form-label--required" for="nfse_regime_especial">Regime especial de tributação</label>
                            <select name="nfse_regime_especial" id="nfse_regime_especial" class="form-control @error('nfse_regime_especial') is-invalid @enderror">
                                @foreach (\App\Services\Nfses\NfseRegras::REGIMES_ESPECIAIS as $codigo => $rotulo)
                                    <option value="{{ $codigo }}" @selected($regimeEspecialNfse === $codigo)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('nfse_regime_especial')
                                <span class="form-error">{{ $message }}</span>
                            @else
                                <small class="text-muted">Regime do ISSQN no município. A maioria das empresas usa "Nenhum".</small>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-3" style="margin-top: 1rem;">
                        <div class="form-group" data-nfse-crt="1,2">
                            <label class="form-label" for="nfse_regime_apuracao_sn">Apuração no Simples Nacional</label>
                            <select name="nfse_regime_apuracao_sn" id="nfse_regime_apuracao_sn" class="form-control @error('nfse_regime_apuracao_sn') is-invalid @enderror">
                                <option value="" @selected($regimeApuracaoNfse === '')>Automático pelo CRT</option>
                                @foreach (\App\Services\Nfses\NfseRegras::REGIMES_APURACAO_SN as $codigo => $rotulo)
                                    <option value="{{ $codigo }}" @selected($regimeApuracaoNfse === (string) $codigo)>{{ $rotulo }}</option>
                                @endforeach
                            </select>
                            @error('nfse_regime_apuracao_sn')
                                <span class="form-error">{{ $message }}</span>
                            @else
                                <small class="text-muted">Automático: ISSQN pelo Simples; com excesso de sublimite, ISSQN fora dele.</small>
                            @enderror
                        </div>

                        <div class="form-group" data-nfse-crt="1,2,3">
                            <label class="form-label" for="nfse_aliquota_iss">Alíquota padrão do ISSQN (%)</label>
                            <input type="text" name="nfse_aliquota_iss" id="nfse_aliquota_iss" inputmode="decimal" maxlength="5" placeholder="Ex.: 2,00"
                                class="form-control @error('nfse_aliquota_iss') is-invalid @enderror" value="{{ $percentual('nfse_aliquota_iss') }}">
                            @error('nfse_aliquota_iss')
                                <span class="form-error">{{ $message }}</span>
                            @else
                                <small class="text-muted">Sugerida na emissão quando a alíquota precisa ir na nota (retenção no Simples ou município sem convênio).</small>
                            @enderror
                        </div>

                        <div class="form-group" data-nfse-crt="1,2">
                            <label class="form-label" for="nfse_ptot_trib_sn">Tributos aproximados — Simples (%)</label>
                            <input type="text" name="nfse_ptot_trib_sn" id="nfse_ptot_trib_sn" inputmode="decimal" maxlength="6" placeholder="Ex.: 6,00"
                                class="form-control @error('nfse_ptot_trib_sn') is-invalid @enderror" value="{{ $percentual('nfse_ptot_trib_sn') }}">
                            @error('nfse_ptot_trib_sn')
                                <span class="form-error">{{ $message }}</span>
                            @else
                                <small class="text-muted">Alíquota efetiva do Simples Nacional, exigida na NFS-e de ME/EPP (Lei 12.741).</small>
                            @enderror
                        </div>
                    </div>

                    <div class="form-grid form-grid--col-3" style="margin-top: 1rem;" data-nfse-crt="3">
                        <div class="form-group">
                            <label class="form-label" for="nfse_ptot_trib_fed">Tributos aproximados — federais (%)</label>
                            <input type="text" name="nfse_ptot_trib_fed" id="nfse_ptot_trib_fed" inputmode="decimal" maxlength="6"
                                class="form-control @error('nfse_ptot_trib_fed') is-invalid @enderror" value="{{ $percentual('nfse_ptot_trib_fed') }}">
                            @error('nfse_ptot_trib_fed')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="nfse_ptot_trib_est">Tributos aproximados — estaduais (%)</label>
                            <input type="text" name="nfse_ptot_trib_est" id="nfse_ptot_trib_est" inputmode="decimal" maxlength="6"
                                class="form-control @error('nfse_ptot_trib_est') is-invalid @enderror" value="{{ $percentual('nfse_ptot_trib_est') }}">
                            @error('nfse_ptot_trib_est')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="nfse_ptot_trib_mun">Tributos aproximados — municipais (%)</label>
                            <input type="text" name="nfse_ptot_trib_mun" id="nfse_ptot_trib_mun" inputmode="decimal" maxlength="6"
                                class="form-control @error('nfse_ptot_trib_mun') is-invalid @enderror" value="{{ $percentual('nfse_ptot_trib_mun') }}">
                            @error('nfse_ptot_trib_mun')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <p class="text-muted" style="margin-top: .5rem;" data-nfse-crt="4">MEI: a NFS-e sai sem alíquota, sem retenção e sem percentual de tributos.</p>

                    <p class="nfce-conferencia__titulo" style="margin-top: 1.25rem;">Códigos padrão do serviço</p>
                    <p class="text-muted" style="margin-bottom: .75rem;">
                        Usados quando o serviço vendido não tem códigos no cadastro do produto. Na emissão, os códigos escolhidos
                        podem ser gravados no produto para as próximas notas.
                    </p>
                    <x-nfse-codigos id="empresa-nfse" prefixo="nfse_" :valores="[
                        'c_trib_nac' => $empresa->nfse_c_trib_nac,
                        'c_trib_mun' => $empresa->nfse_c_trib_mun,
                        'nbs' => $empresa->nfse_nbs,
                        'class_trib' => $empresa->nfse_class_trib,
                        'ind_op' => $empresa->nfse_ind_op,
                    ]" />

                    {{-- Conferência sem emitir nota. O formulário fica fora deste (não dá para aninhar forms). --}}
                    <div class="nfce-conferencia">
                        <div class="nfce-conferencia__topo">
                            <div>
                                <p class="nfce-conferencia__titulo">Conferir a NFS-e</p>
                                <p class="text-muted">
                                    Confere certificado, dados da empresa e o convênio do município na Sefin Nacional, sem emitir nota. Salve as alterações antes.
                                    @if ($empresa->nfse_convenio_consultado_em)
                                        Última consulta: {{ $empresa->nfse_convenio_consultado_em->format('d/m/Y H:i') }}
                                        ({{ $empresa->nfse_convenio_situacao === 'inativo' ? 'convênio inativo' : 'convênio ativo' }}).
                                    @endif
                                </p>
                            </div>
                            <button type="submit" form="form-verificar-nfse" class="btn btn--ghost btn--sm"
                                data-espera="Conferindo..."
                                data-demora="Consultando a Sefin Nacional.">Conferir agora</button>
                        </div>
                        @if (is_array(session('nfse_verificacao')))
                            <ul class="nfce-conferencia__lista" aria-label="Resultado da conferência da NFS-e">
                                @foreach (session('nfse_verificacao') as $item)
                                    @php $tom = ! $item['ok'] ? 'erro' : (! empty($item['aviso']) ? 'aviso' : 'ok'); @endphp
                                    <li class="nfce-conferencia__item nfce-conferencia__item--{{ $tom }}">
                                        <x-icon :name="$tom === 'erro' ? 'x-circle' : ($tom === 'aviso' ? 'alert-triangle' : 'check')" :size="16" />
                                        <span><strong>{{ $item['titulo'] }}:</strong> {{ $item['texto'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @php $falhasNfse = collect(session('nfse_verificacao'))->where('ok', false)->count(); @endphp
                            <p class="nfce-conferencia__conclusao">
                                {{ $falhasNfse === 0 ? 'Tudo certo para emitir NFS-e.' : ($falhasNfse === 1 ? 'Corrija o item marcado antes de emitir.' : 'Corrija os '.$falhasNfse.' itens marcados antes de emitir.') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card__footer">
                <a href="{{ route('empresa.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>

        </form>
        <form method="POST" action="{{ route('empresa.nfce.verificar', $empresa) }}" id="form-verificar-nfce" hidden>
            @csrf
        </form>
        <form method="POST" action="{{ route('empresa.nfse.verificar', $empresa) }}" id="form-verificar-nfse" hidden>
            @csrf
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

        document.getElementById('nfse_ambiente')?.addEventListener('change', function () {
            const alerta = document.getElementById('alerta-nfse-producao');
            if (alerta) {
                alerta.style.display = this.value === '1' ? '' : 'none';
            }
        });

        // Campos da NFS-e que dependem do regime (CRT): MEI, ME/EPP do Simples ou não optante.
        (function () {
            const crt = document.getElementById('crt');
            const campos = document.querySelectorAll('[data-nfse-crt]');
            if (!crt || !campos.length) {
                return;
            }
            function aplicar() {
                campos.forEach(function (campo) {
                    campo.style.display = campo.getAttribute('data-nfse-crt').split(',').indexOf(crt.value) === -1 ? 'none' : '';
                });
            }
            crt.addEventListener('change', aplicar);
            aplicar();
        })();

        document.getElementById('crt')?.addEventListener('change', function () {
            const alerta = document.getElementById('alerta-crt-normal');
            if (!alerta) {
                return;
            }
            alerta.style.display = this.value === '3' ? '' : 'none';
        });

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
