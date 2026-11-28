{{--
    Quadro "Nota de serviço" da venda (NFS-e Nacional). Aparece para quem tem a permissão "nfse"
    quando a venda tem itens de serviço ou já tem NFS-e.
    Variáveis: $venda, $nfseResumo = {temServico, nota, podeEmitir}.
--}}
@php
    $notaServico = $nfseResumo['nota'];
    $dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
@endphp

<div class="card nota-fiscal" id="nota-servico">
    <div class="ficha__head">
        <span class="card__title">Nota de serviço</span>
        @if ($notaServico)
            <span class="nota-fiscal__tipo">NFS-e {{ $notaServico->numero_nfse ? 'nº '.$notaServico->numero_nfse : '(DPS '.$notaServico->serie.'/'.$notaServico->numero.')' }}</span>
            @php [$rotuloServico, $tomServico] = \App\Support\Situacoes::de('nfse', $notaServico->situacao); @endphp
            <x-badge :tom="$tomServico">{{ $rotuloServico }}</x-badge>
            @if ((int) $notaServico->ambiente !== 1)
                <x-badge tom="warning" title="Produção restrita, sem valor fiscal">Homologação</x-badge>
            @endif
        @endif
    </div>
    <div class="card__body">
        @if (! $notaServico)
            <p class="nota-fiscal__texto">
                Esta venda tem serviços. Emita a NFS-e Nacional só com eles; os produtos vão na NF-e ou na NFC-e.
            </p>

        @elseif ($notaServico->situacao === 'autorizada')
            <dl class="ficha__grid">
                <x-ficha-campo label="Número">{{ $notaServico->numero_nfse ?? '—' }}</x-ficha-campo>
                <x-ficha-campo label="Autorizada em">{{ $notaServico->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
                <x-ficha-campo label="Valor líquido">{{ $dinheiro($notaServico->valor_liquido) }}</x-ficha-campo>
                <x-ficha-campo label="Chave de acesso" largo mono>{{ $notaServico->chave_acesso }}</x-ficha-campo>
            </dl>
            <div class="nota-fiscal__acoes">
                <a href="{{ route('nfse.danfse', $notaServico) }}" target="_blank" class="btn btn--primary btn--sm">
                    <x-icon name="pdf" /> DANFSe
                </a>
                <a href="{{ route('nfse.xml', $notaServico) }}" class="btn btn--ghost btn--sm">Baixar XML</a>
                <a href="{{ route('nfse.show', $notaServico) }}" class="btn btn--ghost btn--sm">Ver NFS-e</a>
            </div>

        @elseif ($notaServico->situacao === 'pendente')
            <div class="alert alert--warning">
                A Sefin Nacional ainda não confirmou esta NFS-e. Consulte antes de qualquer nova tentativa.
            </div>
            <div class="nota-fiscal__acoes">
                <form method="POST" action="{{ route('nfse.consultar', $notaServico) }}">
                    @csrf
                    <button type="submit" class="btn btn--primary btn--sm"
                        data-espera="Consultando..."
                        data-demora="Consultando a DPS na Sefin Nacional.">Consultar Sefin</button>
                </form>
                <a href="{{ route('nfse.show', $notaServico) }}" class="btn btn--ghost btn--sm">Ver NFS-e</a>
            </div>

        @elseif ($notaServico->situacao === 'rejeitada')
            <div class="alert alert--error">
                A Sefin Nacional recusou a DPS{{ $notaServico->codigo_status ? ' ('.$notaServico->codigo_status.')' : '' }}:
                {{ \Illuminate\Support\Str::limit((string) ($notaServico->mensagens[0]['texto'] ?? $notaServico->motivo_status), 220) }}
            </div>
            <div class="nota-fiscal__acoes">
                <a href="{{ route('nfse.show', $notaServico) }}" class="btn btn--ghost btn--sm">Ver detalhes</a>
            </div>

        @else
            <p class="nota-fiscal__texto">
                A NFS-e foi cancelada{{ $notaServico->data_cancelamento ? ' em '.$notaServico->data_cancelamento->format('d/m/Y \à\s H:i') : '' }}.
            </p>
            <div class="nota-fiscal__acoes">
                <a href="{{ route('nfse.show', $notaServico) }}" class="btn btn--ghost btn--sm">Ver NFS-e</a>
            </div>
        @endif

        @if ($nfseResumo['podeEmitir'])
            <div class="nota-fiscal__acoes">
                <a href="{{ route('nfse.create', $venda) }}" class="btn btn--primary btn--sm">
                    {{ $notaServico ? 'Emitir NFS-e de novo' : 'Emitir NFS-e' }}
                </a>
            </div>
        @elseif ($nfseResumo['temServico'] && ! $notaServico && $venda->situacao !== 'confirmada')
            <p class="text-muted">Confirme a venda para emitir a NFS-e.</p>
        @endif
    </div>
</div>
