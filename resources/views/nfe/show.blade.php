@extends('layouts.app')

@section('title', 'NF-e #' . $nfe->numero)
@section('page_title', 'NF-e #' . $nfe->numero)

@section('breadcrumb')
    <a href="{{ route('nfe.index') }}">NF-e Emitidas</a>
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

        @if ($nfe->situacao === 'pendente')
            <form method="POST" action="{{ route('nfe.consultar', $nfe) }}" style="display:inline;">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm">Consultar SEFAZ</button>
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

    <div class="card" style="margin-top:0.75rem;">
        <div class="card__header">
            <span class="card__title">Dados da NF-e</span>
            <div>
                @if ($nfe->situacao === 'autorizada')
                    <span class="badge badge--success">Autorizada</span>
                @elseif ($nfe->situacao === 'rejeitada')
                    <span class="badge badge--error">Rejeitada</span>
                @elseif ($nfe->situacao === 'denegada')
                    <span class="badge badge--error">Denegada</span>
                @elseif ($nfe->situacao === 'cancelada')
                    <span class="badge badge--neutral">Cancelada</span>
                @else
                    <span class="badge badge--warning">Pendente</span>
                @endif
            </div>
        </div>
        <div class="card__body">
            <div class="form-grid form-grid--col-4">
                <div class="form-group">
                    <label class="form-label">Número</label>
                    <span>#{{ $nfe->numero }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Série</label>
                    <span>{{ $nfe->serie }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Ambiente</label>
                    <span>
                        @if ((int) $nfe->ambiente === 1)
                            <span class="badge badge--success">Produção</span>
                        @else
                            <span class="badge badge--warning">Homologação</span>
                        @endif
                    </span>
                </div>
                <div class="form-group">
                    <label class="form-label">Data de autorização</label>
                    <span>{{ $nfe->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</span>
                </div>
            </div>

            <div class="form-grid form-grid--col-2" style="margin-top:1rem;">
                <div class="form-group">
                    <label class="form-label">Protocolo</label>
                    <span>{{ $nfe->protocolo ?? '—' }}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Venda</label>
                    <span>
                        @if ($nfe->venda)
                            <a href="{{ route('venda.show', $nfe->venda) }}">#{{ $nfe->venda->numero }}</a>
                        @else
                            —
                        @endif
                    </span>
                </div>
            </div>

            <div class="form-group" style="margin-top:1rem;">
                <label class="form-label">Chave de acesso</label>
                <span style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; word-break: break-all; display:block;">
                    {{ $nfe->chave_acesso ?? '—' }}
                </span>
            </div>
        </div>
    </div>

    @if ($nfe->podeCancelar())
        <div class="modal-overlay" id="modal-cancelar-nfe" hidden>
            <div class="modal-box">
                <div class="modal-box__header">
                    <span class="card__title">Cancelar NF-e</span>
                    <button type="button" class="btn btn--ghost btn--sm" id="btn-fechar-cancelar-nfe">✕</button>
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
                            <textarea name="justificativa" rows="4" maxlength="255" required minlength="15"
                                class="form-control @error('justificativa') is-invalid @enderror"
                                style="resize:vertical;">{{ old('justificativa') }}</textarea>
                            @error('justificativa')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-box__footer">
                        <button type="button" class="btn btn--ghost" id="btn-cancelar-modal-nfe">Voltar</button>
                        <button type="submit" class="btn btn--danger">Confirmar cancelamento</button>
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

            @if ($errors->has('justificativa'))
                abrir();
            @endif
        })();
    </script>
@endpush
