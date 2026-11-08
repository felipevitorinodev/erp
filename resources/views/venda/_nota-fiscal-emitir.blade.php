{{--
    Formulário "Emitir NFC-e" da venda já finalizada. Variáveis: $venda, $exigeDocumento, $temDocumentoCliente, $rotulo.
    Sem CPF/CNPJ digitado, a nota usa o do cliente da venda (quando houver).
--}}
<form method="POST" action="{{ route('venda.nfce.emitir', $venda) }}" class="nota-fiscal__emitir">
    @csrf
    <div class="form-group">
        <label class="form-label {{ $exigeDocumento && ! $temDocumentoCliente ? 'form-label--required' : '' }}" for="nfce-documento-{{ $venda->id }}">
            CPF ou CNPJ na nota
        </label>
        <input type="text" id="nfce-documento-{{ $venda->id }}" name="documento" inputmode="numeric" maxlength="18" autocomplete="off"
            class="form-control @error('documento') is-invalid @enderror" value="{{ old('documento') }}"
            placeholder="{{ $temDocumentoCliente ? 'Vazio: usa o do cliente da venda' : 'Opcional' }}">
        @if ($exigeDocumento && ! $temDocumentoCliente)
            <small class="form-hint">Na Paraíba, venda a partir de R$ 500,00 precisa do CPF ou CNPJ.</small>
        @endif
        @error('documento')<span class="form-error">{{ $message }}</span>@enderror
    </div>
    <button type="submit" class="btn btn--primary btn--sm"
        data-espera="Emitindo..."
        data-demora="Enviando a NFC-e para a SEFAZ.">{{ $rotulo }}</button>
</form>
