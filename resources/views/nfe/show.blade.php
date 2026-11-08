@extends(request()->boolean('modal') ? 'layouts.ver-modal' : 'layouts.app')

@section('title', 'NF-e #' . $nfe->numero)
@section('page_title', 'NF-e #' . $nfe->numero)

@section('breadcrumb')
    <a href="{{ route('nfe.index') }}">NF-e emitidas</a>
    @if ($nfe->venda_id)
        / <a href="{{ route('venda.show', $nfe->venda_id) }}">Venda #{{ $nfe->venda->numero ?? $nfe->venda_id }}</a>
    @endif
    / #{{ $nfe->numero }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('nfe.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        @if ($nfe->situacao === 'autorizada')
            <a href="{{ route('nfe.danfe', $nfe) }}" target="_blank" class="btn btn--ghost btn--sm">
                DANFE
            </a>
        @endif

        @if ($nfe->xml_autorizado)
            <a href="{{ route('nfe.xml', $nfe) }}" class="btn btn--ghost btn--sm">XML</a>
        @endif

        @if ($nfe->xml_cancelamento)
            <a href="{{ route('nfe.xml', [$nfe, 'tipo' => 'cancelamento']) }}" class="btn btn--ghost btn--sm">XML cancelamento</a>
        @endif

        @if ($nfe->situacao === 'rejeitada' && $nfe->venda_id)
            <form method="POST" action="{{ route('nfe.limpar-codigos', $nfe) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--ghost btn--sm"
                    data-confirm="Limpar CFOP, CSOSN e CST gravados nos itens desta venda? A próxima emissão usa o perfil atual. Esta nota rejeitada não é alterada."
                    data-confirm-title="Limpar códigos fiscais"
                    data-confirm-ok="Limpar"
                    data-confirm-variant="danger">Limpar códigos da venda</button>
            </form>
        @endif

        @if ($nfe->situacao === 'pendente')
            <form method="POST" action="{{ route('nfe.consultar', $nfe) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm" data-espera="Consultando..." data-demora="Consultando a situação na SEFAZ.">Consultar SEFAZ</button>
            </form>
        @endif

        @if ($nfe->podeCancelar())
            <button type="button" class="btn btn--danger btn--sm" id="btn-abrir-cancelar-nfe">
                Cancelar
            </button>
        @endif
    </div>
@endsection

@section('content')

    @if ((int) $nfe->ambiente === 2)
        <div class="alert alert--warning">
            Esta NF-e foi emitida em ambiente de HOMOLOGAÇÃO — sem valor fiscal.
        </div>
    @endif

    @if (in_array($nfe->situacao, ['rejeitada', 'denegada'], true) && $nfe->motivo_status)
        <div class="alert alert--error" style="margin-top:0.75rem;">
            {{ $nfe->motivo_status }}
            @if ($nfe->codigo_status)
                <span class="text-muted">(Código {{ $nfe->codigo_status }})</span>
            @endif
            @if ($explicacao = \App\Support\NfeMensagemSefaz::explicar($nfe->codigo_status))
                <br>{{ $explicacao }}
            @endif
        </div>
    @endif

    @if ($nfe->situacao === 'pendente')
        <div class="alert alert--warning" style="margin-top:0.75rem;">
            A situação desta NF-e na SEFAZ ainda não foi confirmada. Use "Consultar SEFAZ" antes de qualquer nova tentativa de emissão.
            @if ($nfe->motivo_status)
                <br><span class="text-muted">Último retorno: {{ $nfe->motivo_status }}@if ($nfe->codigo_status) (Código {{ $nfe->codigo_status }})@endif</span>
            @endif
        </div>
    @endif

    <x-ficha titulo="Dados da NF-e">
        <x-slot:status>
            @if ($nfe->situacao === 'autorizada')
                <x-badge tom="success">Autorizada</x-badge>
            @elseif ($nfe->situacao === 'rejeitada')
                <x-badge tom="error">Rejeitada</x-badge>
            @elseif ($nfe->situacao === 'denegada')
                <x-badge tom="error">Denegada</x-badge>
            @elseif ($nfe->situacao === 'cancelada')
                <x-badge>Cancelada</x-badge>
            @else
                <x-badge tom="warning">Pendente</x-badge>
            @endif
        </x-slot:status>
        <x-ficha-campo label="Número">#{{ $nfe->numero }}</x-ficha-campo>
        <x-ficha-campo label="Série">{{ $nfe->serie }}</x-ficha-campo>
        <x-ficha-campo label="Ambiente">
            @if ((int) $nfe->ambiente === 1)
                <x-badge tom="success">Produção</x-badge>
            @else
                <x-badge tom="warning">Homologação</x-badge>
            @endif
        </x-ficha-campo>
        <x-ficha-campo label="Data de autorização">{{ $nfe->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Protocolo">{{ $nfe->protocolo ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Venda">
            @if ($nfe->venda)
                <a href="{{ route('venda.show', $nfe->venda) }}">#{{ $nfe->venda->numero }}</a>
            @else
                —
            @endif
        </x-ficha-campo>
        <x-ficha-campo label="Chave de acesso" largo mono>{{ $nfe->chave_acesso ?? '—' }}</x-ficha-campo>
    </x-ficha>

    @if ($nfe->podeCancelar())
        <div class="modal-overlay" id="modal-cancelar-nfe" hidden>
            <div class="modal-box">
                <div class="modal-box__header">
                    <span class="card__title">Cancelar NF-e</span>
                    <button type="button" class="btn btn--ghost btn--sm" id="btn-fechar-cancelar-nfe" aria-label="Fechar">✕</button>
                </div>
                <form method="POST" action="{{ route('nfe.cancelar', $nfe) }}">
                    @csrf
                    <div class="modal-box__body">
                        <p class="text-muted" style="margin:0 0 0.75rem; font-size:13px;">
                            O cancelamento só é aceito pela SEFAZ em até 24 horas após a autorização.
                            A justificativa precisa ter no mínimo 15 caracteres.
                        </p>
                        <div class="form-group">
                            <label class="form-label form-label--required">Justificativa</label>
                            <textarea name="justificativa" id="justificativa-cancelamento" rows="4" maxlength="255" required minlength="15"
                                class="form-control @error('justificativa') is-invalid @enderror"
                                style="resize:vertical;">{{ old('justificativa') }}</textarea>
                            <small class="text-muted"><span id="justificativa-contagem">0</span>/255 (mínimo 15)</small>
                            @error('justificativa')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-box__footer">
                        <button type="button" class="btn btn--ghost" id="btn-cancelar-modal-nfe">Voltar</button>
                        <button type="submit" class="btn btn--danger" id="btn-confirmar-cancelamento">Confirmar cancelamento</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        (function() {
            var modal = document.getElementById('modal-cancelar-nfe');
            if (!modal) return;

            function abrir() { modal.hidden = false; }
            function fechar() { modal.hidden = true; }

            var btnAbrir = document.getElementById('btn-abrir-cancelar-nfe');
            if (btnAbrir) btnAbrir.addEventListener('click', abrir);

            ['btn-fechar-cancelar-nfe', 'btn-cancelar-modal-nfe'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', fechar);
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) fechar();
            });

            var campo = document.getElementById('justificativa-cancelamento');
            var contagem = document.getElementById('justificativa-contagem');
            var confirmar = document.getElementById('btn-confirmar-cancelamento');
            function atualizarContagem() {
                if (!campo || !contagem) return;
                contagem.textContent = String(campo.value.length);
                if (confirmar) confirmar.disabled = campo.value.trim().length < 15;
            }
            if (campo) {
                campo.addEventListener('input', atualizarContagem);
                atualizarContagem();
            }
            if (confirmar) {
                confirmar.form.addEventListener('submit', function () {
                    confirmar.disabled = true;
                });
            }

            @if ($errors->has('justificativa'))
                abrir();
            @endif
        })();
    </script>
@endpush
