{{--
    Quadro "Nota fiscal" da venda.
    NFC-e (cupom fiscal ao consumidor) usa a permissão de vendas: o caixa emite, consulta e reimprime.
    NF-e usa a permissão "nfe" e tem a tela dela. Cancelar a NFC-e exige a mesma permissão de cancelar a venda.
    Variáveis: $venda, $empresa, $nfce (última NFC-e), $nfeDaVenda (última NF-e), $nfceDisponivel.
--}}
@php
    $usuario = auth()->user();
    $podeNfe = $usuario->pode('nfe');
    $podeCancelarNota = $usuario->pode('vendas.cancelar');
    $confirmada = $venda->situacao === 'confirmada';
    $ativas = ['autorizada', 'pendente', 'denegada'];

    // A nota que vale para a venda: a NF-e ativa; senão a NFC-e (em qualquer situação); senão a NF-e antiga.
    $modo = match (true) {
        $nfeDaVenda && in_array($nfeDaVenda->situacao, $ativas, true) => 'nfe',
        (bool) $nfce => 'nfce',
        (bool) $nfeDaVenda => 'nfe',
        default => 'emitir',
    };
    $nota = $modo === 'nfce' ? $nfce : ($modo === 'nfe' ? $nfeDaVenda : null);
    $outra = $modo === 'nfce' ? $nfeDaVenda : ($modo === 'nfe' ? $nfce : null);
    $podeEmitirNfce = $confirmada && $nfceDisponivel;
    $mostrar = $modo !== 'emitir' || $podeEmitirNfce || ($podeNfe && ($confirmada || $venda->isRascunhoNfe()));
    $exigeDocumento = strtoupper((string) $empresa->uf) === 'PB' && (float) $venda->total >= 500;
    $temDocumentoCliente = filled($venda->cliente?->cpf) || filled($venda->cliente?->cnpj);
@endphp

@if ($mostrar)
<div class="card nota-fiscal" id="nota-fiscal">
    <div class="ficha__head">
        <span class="card__title">Nota fiscal</span>
        @if ($nota)
            <span class="nota-fiscal__tipo">{{ $modo === 'nfce' ? 'NFC-e' : 'NF-e' }} nº {{ (int) $nota->numero }}</span>
            @php [$rotuloSituacao, $tomSituacao] = \App\Support\Situacoes::de('nfe', $nota->situacao); @endphp
            <x-badge :tom="$tomSituacao">{{ $rotuloSituacao }}</x-badge>
            @if ((int) $nota->ambiente !== 1)
                <x-badge tom="warning" title="Nota de teste, sem valor fiscal">Homologação</x-badge>
            @endif
        @endif
    </div>
    <div class="card__body">

        {{-- ========== NFC-e ========== --}}
        @if ($modo === 'nfce')
            @if ($nota->situacao === 'autorizada')
                <dl class="ficha__grid">
                    <x-ficha-campo label="Número e série">{{ (int) $nota->numero }} / {{ $nota->serie }}</x-ficha-campo>
                    <x-ficha-campo label="Autorizada em">{{ $nota->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Protocolo">{{ $nota->protocolo ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Chave de acesso" largo mono>{{ $nota->chave_acesso }}</x-ficha-campo>
                </dl>
                <div class="nota-fiscal__acoes">
                    <a href="{{ route('venda.nfce.danfe', $venda) }}" target="_blank" class="btn btn--primary btn--sm">
                        <x-icon name="pdf" /> Imprimir NFC-e
                    </a>
                    <a href="{{ route('nfce.xml', $nota) }}" class="btn btn--ghost btn--sm">Baixar XML</a>
                    @if ($podeCancelarNota && $nota->podeCancelar())
                        <details class="nota-fiscal__cancelar">
                            <summary class="btn btn--ghost btn--sm nota-fiscal__cancelar-botao">Cancelar NFC-e</summary>
                            <form method="POST" action="{{ route('nfce.cancelar', $nota) }}" class="nota-fiscal__cancelar-form">
                                @csrf
                                <label class="form-label form-label--required" for="nfce-justificativa">Motivo do cancelamento</label>
                                <textarea id="nfce-justificativa" name="justificativa" rows="2" minlength="15" maxlength="255" required
                                    class="form-control @error('justificativa') is-invalid @enderror"
                                    placeholder="Ex.: venda registrada em duplicidade pelo caixa">{{ old('justificativa') }}</textarea>
                                <small class="form-hint">
                                    Mínimo de 15 caracteres.
                                    @if (strtoupper((string) $empresa->uf) === 'PB' && $nota->data_autorizacao)
                                        Na Paraíba, a SEFAZ aceita o cancelamento até 30 minutos depois da autorização (até {{ $nota->data_autorizacao->copy()->addMinutes(30)->format('H:i') }}).
                                    @else
                                        A SEFAZ só aceita o cancelamento logo depois da autorização; o prazo depende do estado.
                                    @endif
                                </small>
                                @error('justificativa')<span class="form-error">{{ $message }}</span>@enderror
                                <div>
                                    <button type="submit" class="btn btn--danger btn--sm"
                                        data-espera="Cancelando..."
                                        data-demora="Enviando o cancelamento para a SEFAZ."
                                        data-confirm="Cancelar a NFC-e nº {{ (int) $nota->numero }} na SEFAZ? Isso não pode ser desfeito. A venda continua registrada."
                                        data-confirm-title="Cancelar NFC-e"
                                        data-confirm-ok="Cancelar NFC-e"
                                        data-confirm-variant="danger">Confirmar cancelamento</button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>

            @elseif ($nota->situacao === 'pendente')
                <div class="alert alert--warning">
                    A SEFAZ ainda não confirmou esta NFC-e. Consulte para saber se ela foi autorizada.
                    Enquanto ela estiver pendente, nenhuma outra NFC-e é emitida.
                </div>
                <div class="nota-fiscal__acoes">
                    <form method="POST" action="{{ route('nfce.consultar', $nota) }}">
                        @csrf
                        <button type="submit" class="btn btn--primary btn--sm"
                            data-espera="Consultando..."
                            data-demora="Perguntando à SEFAZ pela NFC-e.">Consultar SEFAZ</button>
                    </form>
                </div>

            @elseif ($nota->situacao === 'denegada')
                <div class="alert alert--error">
                    A SEFAZ negou esta NFC-e: {{ $nota->motivoParaTela() }}
                    @if ($nota->codigo_status)<span class="text-muted">(código {{ $nota->codigo_status }})</span>@endif.
                    O número foi consumido. Fale com o contador.
                </div>

            @else
                @if ($nota->situacao === 'cancelada')
                    <p class="nota-fiscal__texto">
                        A NFC-e nº {{ (int) $nota->numero }} foi cancelada na SEFAZ{{ $nota->data_cancelamento ? ' em '.$nota->data_cancelamento->format('d/m/Y \à\s H:i') : '' }}.
                        @if ($confirmada)
                            A venda continua confirmada: para desfazê-la, use <strong>Cancelar</strong> no topo da página.
                        @endif
                    </p>
                    <div class="nota-fiscal__acoes">
                        <a href="{{ route('nfce.xml', ['nfe' => $nota, 'cancelamento' => 1]) }}" class="btn btn--ghost btn--sm">XML do cancelamento</a>
                        <a href="{{ route('nfce.xml', $nota) }}" class="btn btn--ghost btn--sm">XML da NFC-e</a>
                    </div>
                @elseif ($nota->codigo_status === '217')
                    <div class="alert alert--warning">
                        A SEFAZ não recebeu esta NFC-e. Emita de novo: o mesmo número será usado.
                    </div>
                @elseif (blank($nota->codigo_status))
                    {{-- Barrada antes do envio (cadastro, certificado): a SEFAZ não chegou a ver a nota. --}}
                    <div class="alert alert--error">
                        A NFC-e não foi emitida: {{ $nota->motivoParaTela() ?: 'motivo não informado.' }}
                        <span class="nota-fiscal__motivo">Corrija e emita de novo. O mesmo número será usado.</span>
                    </div>
                @else
                    <div class="alert alert--error">
                        A SEFAZ rejeitou a NFC-e: {{ $nota->motivoParaTela() ?: 'motivo não informado.' }}
                        <span class="text-muted">(código {{ $nota->codigo_status }})</span>
                        <span class="nota-fiscal__motivo">Corrija o que a SEFAZ apontou e emita de novo.@if ($nota->codigo_status !== '539') O mesmo número será usado.@endif</span>
                    </div>
                @endif

                @if ($podeEmitirNfce)
                    @include('venda._nota-fiscal-emitir', ['rotulo' => $nota->situacao === 'cancelada' ? 'Emitir nova NFC-e' : 'Emitir de novo'])
                @endif
                @if ($podeNfe && $confirmada && ! $nfeDaVenda)
                    <form method="POST" action="{{ route('nfe.emitir', $venda) }}" class="nota-fiscal__outra">
                        @csrf
                        Precisa de NF-e (para empresa ou entrega)?
                        <button type="submit" class="btn btn--ghost btn--sm"
                            data-confirm="Emitir NF-e da venda #{{ $venda->numero }} agora?"
                            data-confirm-title="Emitir NF-e"
                            data-confirm-ok="Emitir">Emitir NF-e</button>
                    </form>
                @endif
            @endif

        {{-- ========== NF-e ========== --}}
        @elseif ($modo === 'nfe')
            @if (! $podeNfe)
                <p class="nota-fiscal__texto">Esta venda tem NF-e. Os detalhes ficam com quem cuida das notas fiscais.</p>
            @elseif ($nota->situacao === 'autorizada')
                <dl class="ficha__grid">
                    <x-ficha-campo label="Protocolo">{{ $nota->protocolo ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Autorizada em">{{ $nota->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
                    <x-ficha-campo label="Chave de acesso" largo mono>{{ $nota->chave_acesso }}</x-ficha-campo>
                </dl>
                <div class="nota-fiscal__acoes">
                    <a href="{{ route('nfe.danfe', $nota) }}" target="_blank" class="btn btn--primary btn--sm">DANFE</a>
                    <a href="{{ route('nfe.show', $nota) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
                </div>
            @else
                @if ($nota->situacao === 'cancelada')
                    <p class="nota-fiscal__texto">A NF-e anterior foi cancelada. Você pode emitir uma nova nota para esta venda.</p>
                @elseif ($nota->motivo_status)
                    <div class="alert {{ $nota->situacao === 'pendente' ? 'alert--warning' : 'alert--error' }}">
                        {{ $nota->motivo_status }}
                        @if ($nota->codigo_status)<span class="text-muted">(código {{ $nota->codigo_status }})</span>@endif
                    </div>
                @endif
                <div class="nota-fiscal__acoes">
                    <a href="{{ route('nfe.show', $nota) }}" class="btn btn--ghost btn--sm">Ver NF-e</a>
                    @if (in_array($nota->situacao, ['rejeitada', 'cancelada'], true) && $confirmada)
                        <form method="POST" action="{{ route('nfe.emitir', $venda) }}">
                            @csrf
                            <button type="submit" class="btn btn--primary btn--sm"
                                data-confirm="Emitir uma nova NF-e para a venda #{{ $venda->numero }}?"
                                data-confirm-title="Emitir NF-e"
                                data-confirm-ok="Emitir">
                                {{ $nota->situacao === 'cancelada' ? 'Emitir nova NF-e' : 'Tentar novamente' }}
                            </button>
                        </form>
                    @endif
                </div>
            @endif

        {{-- ========== Sem nota ========== --}}
        @elseif ($confirmada)
            <div class="nota-fiscal__opcoes">
                @if ($podeEmitirNfce)
                    <div class="nota-fiscal__opcao">
                        <p class="nota-fiscal__opcao-titulo">NFC-e</p>
                        <p class="nota-fiscal__texto">Cupom fiscal para o consumidor final, como no PDV.</p>
                        @include('venda._nota-fiscal-emitir', ['rotulo' => 'Emitir NFC-e'])
                    </div>
                @endif
                @if ($podeNfe)
                    <div class="nota-fiscal__opcao">
                        <p class="nota-fiscal__opcao-titulo">NF-e</p>
                        <p class="nota-fiscal__texto">Nota para empresa, entrega ou venda fora do balcão.</p>
                        <form method="POST" action="{{ route('nfe.emitir', $venda) }}">
                            @csrf
                            <button type="submit" class="btn {{ $podeEmitirNfce ? 'btn--ghost' : 'btn--primary' }} btn--sm"
                                data-confirm="Emitir NF-e da venda #{{ $venda->numero }} agora?"
                                data-confirm-title="Emitir NF-e"
                                data-confirm-ok="Emitir">
                                Emitir NF-e
                            </button>
                        </form>
                    </div>
                @endif
            </div>
            @if (! $nfceDisponivel && $usuario->pode('configuracoes'))
                <p class="nota-fiscal__dica">
                    Para emitir NFC-e, cadastre o certificado digital e o CSC em
                    <a href="{{ route('empresa.edit', $empresa) }}#nfce">Empresa</a>.
                </p>
            @endif

        @elseif ($venda->isRascunhoNfe())
            <p class="nota-fiscal__texto">Esta venda é um rascunho de NF-e: o estoque só será baixado na emissão.</p>
            <a href="{{ route('nfe.rascunho.edit', $venda) }}" class="btn btn--primary btn--sm">Continuar rascunho da NF-e</a>
        @else
            <p class="nota-fiscal__texto">Confirme a venda antes de emitir a nota fiscal.</p>
        @endif

        {{-- A outra nota da venda (ex.: NFC-e rejeitada e depois NF-e), só como referência. --}}
        @if ($outra && ($modo === 'nfce' ? $podeNfe : true))
            <p class="nota-fiscal__outra">
                {{ $modo === 'nfce' ? 'NF-e' : 'NFC-e' }} nº {{ (int) $outra->numero }}:
                {{ \App\Support\Situacoes::de('nfe', $outra->situacao)[0] }}
                @if ($modo === 'nfce')
                    · <a href="{{ route('nfe.show', $outra) }}">Ver NF-e</a>
                @endif
            </p>
        @endif
    </div>
</div>
@endif
