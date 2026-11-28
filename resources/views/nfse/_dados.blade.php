{{--
    Campos da NFS-e (códigos, local, competência, descrição, ISSQN). Usado na emissão pela venda (nfse/create)
    e na tela "Nova NFS-e" (nfse/nova), dentro do formulário de cada uma.
    Variáveis: $entrada, $municipio, $opSimpNac, $podeReter, $aliquotaSempre, $aliquotaComRetencao, $explicacaoAliquota.
    Opcionais: $documentoCliente (CPF/CNPJ do tomador já conhecido), $tomadorNaTela (o tomador é escolhido na mesma tela)
    e $descricaoOpcional (descrição em branco vira a lista dos serviços).
--}}
@php
    $documentoCliente = $documentoCliente ?? null;
    $tomadorNaTela = $tomadorNaTela ?? false;
    $descricaoOpcional = $descricaoOpcional ?? false;
    $retidoMarcado = (bool) old('iss_retido', $entrada['iss_retido']);
    $municipioCodigo = preg_replace('/\D/', '', (string) old('municipio_prestacao', $entrada['municipio_prestacao'])) ?? '';
    $municipioRotulo = $municipio && $municipio->codigo === $municipioCodigo
        ? $municipio->nome.'/'.$municipio->uf.' - '.$municipio->codigo
        : $municipioCodigo;
@endphp

<x-nfse-codigos id="emissao" :valores="$entrada" obrigatorio />

<div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
    <div class="form-group" data-nfse-codigos>
        <label class="form-label form-label--required" for="emissao-municipio-busca">Local da prestação</label>
        <input type="text" id="emissao-municipio-busca" data-nfse-busca="municipio" data-url="{{ route('api.municipios.busca') }}"
            class="form-control @error('municipio_prestacao') is-invalid @enderror" value="{{ $municipioRotulo }}" autocomplete="off"
            placeholder="Cidade/UF ou código IBGE">
        <input type="hidden" name="municipio_prestacao" data-nfse-codigo="municipio" value="{{ $municipioCodigo }}">
        @error('municipio_prestacao')
            <span class="form-error">{{ $message }}</span>
        @else
            <small class="text-muted">Município onde o serviço foi prestado. Sugerido: o da empresa.</small>
        @enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required" for="data-competencia">Competência</label>
        <input type="date" name="data_competencia" id="data-competencia" required max="{{ now()->toDateString() }}"
            class="form-control @error('data_competencia') is-invalid @enderror"
            value="{{ old('data_competencia', $entrada['data_competencia']) }}">
        @error('data_competencia')
            <span class="form-error">{{ $message }}</span>
        @else
            <small class="text-muted">Data em que o serviço foi prestado. Não pode ser futura.</small>
        @enderror
    </div>
</div>

<div class="form-group" style="margin-top: 1rem;">
    <label @class(['form-label', 'form-label--required' => ! $descricaoOpcional]) for="descricao-servico">Descrição do serviço</label>
    <textarea name="descricao" id="descricao-servico" rows="4" maxlength="2000" @required(! $descricaoOpcional)
        class="form-control @error('descricao') is-invalid @enderror"
        @if ($descricaoOpcional) placeholder="Ex.: 1 x Manutenção de computador" @endif>{{ old('descricao', $entrada['descricao']) }}</textarea>
    @error('descricao')
        <span class="form-error">{{ $message }}</span>
    @else
        <small class="text-muted">
            Sai no campo "Descrição do serviço" da NFS-e. Até 2000 caracteres.
            @if ($descricaoOpcional)
                Em branco, sai a lista dos serviços (quantidade x nome).
            @endif
        </small>
    @enderror
</div>

<div class="form-grid form-grid--col-2" style="margin-top: 1rem;">
    <div class="form-group">
        @if ($podeReter)
            <input type="hidden" name="iss_retido" value="0">
            <label class="form-check" for="iss-retido">
                <input type="checkbox" name="iss_retido" id="iss-retido" value="1" @checked($retidoMarcado) @disabled(! $tomadorNaTela && ! $documentoCliente)>
                <span>ISSQN retido pelo tomador</span>
            </label>
            <small class="text-muted">
                @if ($tomadorNaTela)
                    Marque quando o cliente reter o ISSQN na fonte. Exige cliente com CPF ou CNPJ; fica gravado no cadastro dele para as próximas notas.
                @elseif ($documentoCliente)
                    Marque quando o cliente reter o ISSQN na fonte. Fica gravado no cadastro do cliente para as próximas notas.
                @else
                    A retenção exige tomador identificado (CPF ou CNPJ).
                @endif
            </small>
        @else
            <input type="hidden" name="iss_retido" value="0">
            <p class="text-muted">
                {{ $opSimpNac === \App\Services\Nfses\NfseRegras::OP_MEI ? 'MEI não tem ISSQN retido.' : 'Com regime especial de tributação não há retenção nesta tela.' }}
            </p>
        @endif
    </div>

    <div class="form-group" id="grupo-aliquota-iss" @if (! $aliquotaSempre && ! ($aliquotaComRetencao && $retidoMarcado)) style="display: none;" @endif>
        <label class="form-label form-label--required" for="aliquota-iss">Alíquota do ISSQN (%)</label>
        <input type="text" name="aliquota_iss" id="aliquota-iss" inputmode="decimal" maxlength="5"
            class="form-control @error('aliquota_iss') is-invalid @enderror"
            value="{{ old('aliquota_iss', $entrada['aliquota_iss'] !== '' ? number_format((float) $entrada['aliquota_iss'], 2, ',', '') : '') }}"
            placeholder="Ex.: 2,00">
        @error('aliquota_iss')
            <span class="form-error">{{ $message }}</span>
        @else
            <small class="text-muted">{{ $explicacaoAliquota }}</small>
        @enderror
    </div>
</div>

@if (! $aliquotaSempre && ! $aliquotaComRetencao)
    <p class="text-muted" style="margin-top: .5rem;">{{ $explicacaoAliquota }}</p>
@endif

<input type="hidden" name="memorizar" value="0">
<label class="form-check" for="memorizar-codigos" style="margin-top: 1rem;">
    <input type="checkbox" name="memorizar" id="memorizar-codigos" value="1" @checked(old('memorizar', $entrada['memorizar']))>
    <span>Gravar estes códigos nos serviços da venda para as próximas notas</span>
</label>

@push('scripts')
    <script>
        (function () {
            var retido = document.getElementById('iss-retido');
            var grupo = document.getElementById('grupo-aliquota-iss');
            if (!retido || !grupo) return;

            var sempre = @json($aliquotaSempre);
            var comRetencao = @json($aliquotaComRetencao);

            retido.addEventListener('change', function () {
                grupo.style.display = (sempre || (comRetencao && retido.checked)) ? '' : 'none';
            });
        })();
    </script>
@endpush
