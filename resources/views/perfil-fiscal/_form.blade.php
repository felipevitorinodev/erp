@php
    $perfil = $perfil ?? null;
    $csosnOpcoes = ['102' => '102 — Tributada sem crédito', '103' => '103 — Isenção no Simples', '300' => '300 — Imune', '400' => '400 — Não tributada'];
    $cstOpcoes = ['04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09'];
    $catalogoCfop = \App\Models\Fiscal\Cfop::query()->pluck('descricao', 'codigo');
    $cfopCodigo = old('cfop', $perfil->cfop ?? '5102');
    $cfopRotulo = isset($catalogoCfop[$cfopCodigo]) ? $cfopCodigo.' - '.$catalogoCfop[$cfopCodigo] : $cfopCodigo;
    $ncmCodigo = old('ncm', $perfil->ncm ?? '');
    $ncmDescricao = $ncmCodigo !== '' ? \App\Models\Fiscal\Ncm::query()->where('codigo', $ncmCodigo)->value('descricao') : null;
    $ncmRotulo = $ncmDescricao ? $ncmCodigo.' - '.$ncmDescricao : $ncmCodigo;
@endphp

<div class="form-grid form-grid--col-2">
    <div class="form-group">
        <label class="form-label form-label--required">Nome</label>
        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
            value="{{ old('nome', $perfil->nome ?? '') }}" maxlength="80" placeholder="Ex.: Revenda interna">
        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label" for="perfil-ncm-busca">NCM</label>
        <div class="nfe-busca-fiscal">
            <input type="text" id="perfil-ncm-busca" class="form-control @error('ncm') is-invalid @enderror"
                value="{{ $ncmRotulo }}" maxlength="160" autocomplete="off"
                placeholder="Opcional — código ou descrição">
            <input type="hidden" name="ncm" id="perfil-ncm" value="{{ $ncmCodigo }}">
            <div id="perfil-ncm-lista" class="autocomplete-dropdown nfe-busca-fiscal__lista" hidden></div>
        </div>
        @error('ncm')<span class="form-error">{{ $message }}</span>@enderror
        <small class="text-muted">NCM vazio não restringe. Sem NCM, operação ou UF, o perfil só vale se estiver ligado ao produto.</small>
    </div>
</div>

<div class="form-grid form-grid--col-2" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Operação</label>
        <select name="operacao" class="form-control @error('operacao') is-invalid @enderror">
            <option value="">Qualquer</option>
            <option value="venda" @selected(old('operacao', $perfil->operacao ?? '') === 'venda')>Venda</option>
            <option value="devolucao" @selected(old('operacao', $perfil->operacao ?? '') === 'devolucao')>Devolução</option>
        </select>
        @error('operacao')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">UF de destino</label>
        <input type="text" name="uf_destino" class="form-control @error('uf_destino') is-invalid @enderror"
            value="{{ old('uf_destino', $perfil->uf_destino ?? '') }}" maxlength="2" placeholder="Vazio = qualquer UF">
        @error('uf_destino')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<p class="text-muted" style="margin-top:.5rem;font-size:13px;">
    Tipo de destinatário (indIEDest 1, 2 ou 9) fica desligado. Inscrição estadual diferente de ISENTO não classifica o cliente.
</p>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label form-label--required" for="perfil-cfop-busca">CFOP</label>
        <div class="nfe-busca-fiscal">
            <input type="text" id="perfil-cfop-busca" class="form-control @error('cfop') is-invalid @enderror"
                value="{{ $cfopRotulo }}" maxlength="180" autocomplete="off" placeholder="Código ou descrição">
            <input type="hidden" name="cfop" id="perfil-cfop" value="{{ $cfopCodigo }}">
            <div id="perfil-cfop-lista" class="autocomplete-dropdown nfe-busca-fiscal__lista" hidden></div>
        </div>
        @error('cfop')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label form-label--required">CSOSN</label>
        <select name="csosn" class="form-control @error('csosn') is-invalid @enderror">
            @foreach ($csosnOpcoes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('csosn', $perfil->csosn ?? '400') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
        @error('csosn')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label form-label--required">CST PIS</label>
        <select name="cst_pis" class="form-control @error('cst_pis') is-invalid @enderror">
            @foreach ($cstOpcoes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('cst_pis', $perfil->cst_pis ?? '07') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
        @error('cst_pis')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label form-label--required">CST COFINS</label>
        <select name="cst_cofins" class="form-control @error('cst_cofins') is-invalid @enderror">
            @foreach ($cstOpcoes as $valor => $rotulo)
                <option value="{{ $valor }}" @selected(old('cst_cofins', $perfil->cst_cofins ?? '07') === $valor)>{{ $rotulo }}</option>
            @endforeach
        </select>
        @error('cst_cofins')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-group" style="margin-top:1rem;">
    <label class="form-label">Ativo</label>
    <select name="ativo" class="form-control">
        <option value="1" @selected(old('ativo', ($perfil->ativo ?? true) ? '1' : '0') == '1')>Sim</option>
        <option value="0" @selected(old('ativo', ($perfil->ativo ?? true) ? '1' : '0') == '0')>Não</option>
    </select>
</div>

<p class="text-muted" style="margin-top:1rem;font-size:13px;">
    Prioridade: valor do item, campo do produto, perfil ligado ao produto e, entre os demais,
    o que tiver mais campos preenchidos casando com a operação, a UF e o NCM.
    Perfil sem nenhum desses campos só vale se estiver ligado ao produto.
    Empate fica com o perfil mais antigo. ICMS, PIS e COFINS continuam sem valor destacado.
    Nota já autorizada não é alterada. Venda interestadual continua bloqueada nesta versão.
    Simples Nacional e MEI emitem com CSOSN, sem destaque de imposto.
</p>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof initAutocomplete !== 'function') return;

    var ncmBusca = document.getElementById('perfil-ncm-busca');
    var ncm = document.getElementById('perfil-ncm');
    var cfopBusca = document.getElementById('perfil-cfop-busca');
    var cfop = document.getElementById('perfil-cfop');

    function rotulo(item) {
        return item.rotulo || (item.codigo + ' - ' + item.descricao);
    }

    function alinhar(busca, oculto, tamanho) {
        var texto = busca.value.trim();
        if (texto === '') {
            oculto.value = '';
            return;
        }
        var inicio = (texto.match(/^(\d+)/) || [null, ''])[1];
        if (inicio.length === tamanho && (texto === inicio || texto.indexOf(inicio + ' - ') === 0)) {
            oculto.value = inicio;
            return;
        }
        if (oculto.value && texto.indexOf(oculto.value) !== 0) oculto.value = '';
    }

    initAutocomplete('#perfil-ncm-busca', '#perfil-ncm', @json(route('api.ncms.busca')), rotulo, null, null, 'codigo');
    initAutocomplete('#perfil-cfop-busca', '#perfil-cfop', @json(route('api.cfops.busca')), rotulo, null, null, 'codigo');

    var form = cfopBusca && cfopBusca.form;
    if (form) {
        form.addEventListener('submit', function () {
            alinhar(ncmBusca, ncm, 8);
            alinhar(cfopBusca, cfop, 4);
        });
    }
});
</script>
@endpush
