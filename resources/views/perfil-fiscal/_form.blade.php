@php
    $perfil = $perfil ?? null;
    $csosnOpcoes = ['102' => '102 — Tributada sem crédito', '103' => '103 — Isenção no Simples', '300' => '300 — Imune', '400' => '400 — Não tributada'];
    $cstOpcoes = ['04' => '04', '05' => '05', '06' => '06', '07' => '07', '08' => '08', '09' => '09'];
@endphp

<div class="form-grid form-grid--col-2">
    <div class="form-group">
        <label class="form-label form-label--required">Nome</label>
        <input type="text" name="nome" class="form-control @error('nome') is-invalid @enderror"
            value="{{ old('nome', $perfil->nome ?? '') }}" maxlength="80" placeholder="Ex.: Revenda interna">
        @error('nome')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <div class="form-group">
        <label class="form-label">NCM</label>
        <input type="text" name="ncm" class="form-control @error('ncm') is-invalid @enderror"
            value="{{ old('ncm', $perfil->ncm ?? '') }}" maxlength="8" inputmode="numeric"
            placeholder="Opcional — só se este perfil vale para um NCM">
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
        <label class="form-label form-label--required">CFOP</label>
        <input type="text" name="cfop" class="form-control @error('cfop') is-invalid @enderror"
            value="{{ old('cfop', $perfil->cfop ?? '5102') }}" maxlength="4" inputmode="numeric">
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
</p>
