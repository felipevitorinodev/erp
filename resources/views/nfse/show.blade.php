@extends('layouts.app')

@php
    [$rotuloSituacao, $tomSituacao] = \App\Support\Situacoes::de('nfse', $nfse->situacao);
    $titulo = $nfse->numero_nfse ? 'NFS-e #'.$nfse->numero_nfse : 'DPS '.$nfse->serie.'/'.$nfse->numero;
    $dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
@endphp

@section('title', $titulo)
@section('page_title', $titulo)

@section('breadcrumb')
    <a href="{{ route('nfse.index') }}">NFS-e emitidas</a>
    @if ($nfse->venda_id)
        / <a href="{{ route('venda.show', $nfse->venda_id) }}">Venda #{{ $nfse->venda->numero ?? $nfse->venda_id }}</a>
    @endif
    / {{ $titulo }}
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('nfse.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>

        @if ($nfse->xml_nfse)
            <a href="{{ route('nfse.danfse', $nfse) }}" target="_blank" class="btn btn--ghost btn--sm">DANFSe</a>
            <a href="{{ route('nfse.xml', $nfse) }}" class="btn btn--ghost btn--sm">XML</a>
        @elseif ($nfse->xml_assinado)
            <a href="{{ route('nfse.xml', [$nfse, 'tipo' => 'dps']) }}" class="btn btn--ghost btn--sm">XML da DPS</a>
        @endif

        @if ($nfse->xml_cancelamento)
            <a href="{{ route('nfse.xml', [$nfse, 'tipo' => 'cancelamento']) }}" class="btn btn--ghost btn--sm">XML cancelamento</a>
        @endif

        @if ($nfse->situacao === 'pendente')
            <form method="POST" action="{{ route('nfse.consultar', $nfse) }}">
                @csrf
                <button type="submit" class="btn btn--primary btn--sm" data-espera="Consultando..." data-demora="Consultando a DPS na Sefin Nacional.">Consultar Sefin</button>
            </form>
        @endif

        @if ($nfse->situacao === 'rejeitada' && $nfse->venda_id && auth()->user()->pode('nfse'))
            <a href="{{ route('nfse.create', $nfse->venda_id) }}" class="btn btn--primary btn--sm">Corrigir e emitir de novo</a>
        @endif

        @if ($nfse->podeCancelar())
            <button type="button" class="btn btn--danger btn--sm" id="btn-abrir-cancelar-nfse">Cancelar</button>
        @endif
    </div>
@endsection

@section('content')

    @if ((int) $nfse->ambiente !== 1)
        <div class="alert alert--warning">
            Esta NFS-e foi emitida na produção restrita (homologação) — sem valor fiscal.
        </div>
    @endif

    @if ($nfse->situacao === 'rejeitada')
        <div class="alert alert--error">
            <strong>A Sefin Nacional recusou a DPS.</strong> O número {{ $nfse->serie }}/{{ $nfse->numero }} é reaproveitado na próxima tentativa.
            @if ($nfse->mensagens)
                <ul class="lista-erros">
                    @foreach ($nfse->mensagens as $mensagem)
                        <li>
                            @if (filled($mensagem['codigo'] ?? null))<strong>{{ $mensagem['codigo'] }}</strong> — @endif{{ $mensagem['texto'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
            @elseif ($nfse->motivo_status)
                <br>{{ $nfse->motivo_status }}@if ($nfse->codigo_status) <span class="text-muted">({{ $nfse->codigo_status }})</span>@endif
            @endif
        </div>
    @endif

    @if ($nfse->situacao === 'pendente')
        <div class="alert alert--warning">
            A situação desta DPS na Sefin Nacional ainda não foi confirmada. Use "Consultar Sefin" antes de tentar de novo:
            se a nota já tiver sido gerada, ela é trazida para cá; se não, a venda fica liberada para nova emissão.
            @if ($nfse->motivo_status)
                <br><span class="text-muted">Último retorno: {{ $nfse->motivo_status }}@if ($nfse->codigo_status) ({{ $nfse->codigo_status }})@endif</span>
            @endif
        </div>
    @endif

    @if ($nfse->situacao === 'autorizada' && $nfse->mensagens)
        <div class="alert alert--info">
            Alertas devolvidos pela Sefin (a nota foi gerada):
            <ul class="lista-erros">
                @foreach ($nfse->mensagens as $mensagem)
                    <li>@if (filled($mensagem['codigo'] ?? null))<strong>{{ $mensagem['codigo'] }}</strong> — @endif{{ $mensagem['texto'] ?? '' }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($nfse->situacao === 'cancelada')
        <div class="alert alert--info">
            Cancelada em {{ $nfse->data_cancelamento?->format('d/m/Y H:i') ?? '—' }}.
            @if ($nfse->motivo_cancelamento)
                Motivo: {{ $nfse->motivo_cancelamento }}
            @endif
        </div>
    @endif

    <x-ficha titulo="Dados da NFS-e">
        <x-slot:status>
            <x-badge :tom="$tomSituacao">{{ $rotuloSituacao }}</x-badge>
        </x-slot:status>
        <x-ficha-campo label="Número da NFS-e">{{ $nfse->numero_nfse ? '#'.$nfse->numero_nfse : '—' }}</x-ficha-campo>
        <x-ficha-campo label="DPS (série/número)">{{ $nfse->serie }}/{{ $nfse->numero }}</x-ficha-campo>
        <x-ficha-campo label="Ambiente">
            @if ((int) $nfse->ambiente === 1)
                <x-badge tom="success">Produção</x-badge>
            @else
                <x-badge tom="warning">Produção restrita</x-badge>
            @endif
        </x-ficha-campo>
        <x-ficha-campo label="Competência">{{ $nfse->data_competencia?->format('d/m/Y') ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Emissão da DPS">{{ $nfse->data_emissao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Autorização">{{ $nfse->data_autorizacao?->format('d/m/Y H:i') ?? '—' }}</x-ficha-campo>
        <x-ficha-campo label="Venda">
            @if ($nfse->venda)
                <a href="{{ route('venda.show', $nfse->venda) }}">#{{ $nfse->venda->numero }}</a>
            @else
                —
            @endif
        </x-ficha-campo>
        <x-ficha-campo label="Tomador">{{ $nfse->venda?->cliente?->nome ?? 'Não identificado' }}</x-ficha-campo>
        @if ($nfse->chave_acesso)
            <x-ficha-campo label="Chave de acesso" largo mono>{{ $nfse->chave_acesso }}</x-ficha-campo>
        @endif
    </x-ficha>

    <x-ficha titulo="Serviço">
        <x-ficha-campo label="Código de tributação nacional" largo>{{ $servico['c_trib_nac'] ?: '—' }}</x-ficha-campo>
        @if ($nfse->c_trib_mun)
            <x-ficha-campo label="Código municipal">{{ $nfse->c_trib_mun }}</x-ficha-campo>
        @endif
        <x-ficha-campo label="NBS" largo>{{ $servico['nbs'] ?: '—' }}</x-ficha-campo>
        <x-ficha-campo label="Local da prestação">{{ $municipio ? $municipio->nome.'/'.$municipio->uf : ($nfse->municipio_prestacao ?: '—') }}</x-ficha-campo>
        <x-ficha-campo label="Classificação IBS/CBS" largo>{{ $servico['class_trib'] ?: '—' }}</x-ficha-campo>
        @if ($servico['ind_op'])
            <x-ficha-campo label="Indicador da operação" largo>{{ $servico['ind_op'] }}</x-ficha-campo>
        @endif
        <x-ficha-campo label="Descrição" largo><span class="texto-pre-linha">{{ $nfse->descricao }}</span></x-ficha-campo>
    </x-ficha>

    <x-ficha titulo="Valores">
        <x-ficha-campo label="Valor do serviço">{{ $dinheiro($nfse->valor_servico) }}</x-ficha-campo>
        <x-ficha-campo label="Desconto incondicionado">{{ $dinheiro($nfse->valor_desconto) }}</x-ficha-campo>
        <x-ficha-campo label="ISSQN retido pelo tomador">{{ $nfse->iss_retido ? 'Sim' : 'Não' }}</x-ficha-campo>
        <x-ficha-campo label="Alíquota informada">{{ $nfse->aliquota_iss !== null ? number_format((float) $nfse->aliquota_iss, 2, ',', '.').'%' : 'Definida pelo município' }}</x-ficha-campo>
        @if ($nfse->valor_iss !== null)
            <x-ficha-campo label="ISSQN apurado">{{ $dinheiro($nfse->valor_iss) }}</x-ficha-campo>
        @endif
        <x-ficha-campo label="Valor líquido"><strong>{{ $dinheiro($nfse->valor_liquido) }}</strong></x-ficha-campo>
    </x-ficha>

    @if ($nfse->podeCancelar())
        <div class="modal-overlay" id="modal-cancelar-nfse" hidden>
            <div class="modal-box">
                <div class="modal-box__header">
                    <span class="card__title">Cancelar NFS-e</span>
                    <button type="button" class="btn btn--ghost btn--sm btn--icon" id="btn-fechar-cancelar-nfse" aria-label="Fechar"><x-icon name="x" /></button>
                </div>
                <form method="POST" action="{{ route('nfse.cancelar', $nfse) }}">
                    @csrf
                    <div class="modal-box__body">
                        <p class="modal-box__intro">
                            O cancelamento é registrado como evento na Sefin Nacional. O município pode recusar fora do prazo
                            dele; nesse caso, o pedido precisa ser feito pela análise fiscal do município.
                        </p>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="codigo-motivo-cancelamento">Motivo</label>
                            <select name="codigo_motivo" id="codigo-motivo-cancelamento" required
                                class="form-control @error('codigo_motivo') is-invalid @enderror">
                                @foreach (\App\Models\Nfses\Nfse::MOTIVOS_CANCELAMENTO as $codigo => $texto)
                                    <option value="{{ $codigo }}" @selected((string) old('codigo_motivo', '1') === (string) $codigo)>{{ $texto }}</option>
                                @endforeach
                            </select>
                            @error('codigo_motivo')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="motivo-cancelamento">Justificativa</label>
                            <textarea name="motivo" id="motivo-cancelamento" rows="4" maxlength="255" required minlength="15"
                                class="form-control @error('motivo') is-invalid @enderror">{{ old('motivo') }}</textarea>
                            <small class="text-muted"><span id="motivo-cancelamento-contagem">0</span>/255 (mínimo 15)</small>
                            @error('motivo')
                                <span class="form-error">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>
                    <div class="modal-box__footer">
                        <button type="button" class="btn btn--ghost" id="btn-voltar-cancelar-nfse">Voltar</button>
                        <button type="submit" class="btn btn--danger" id="btn-confirmar-cancelamento-nfse" data-espera="Cancelando...">Confirmar cancelamento</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        (function() {
            var modal = document.getElementById('modal-cancelar-nfse');
            if (!modal) return;

            function abrir() { modal.hidden = false; }
            function fechar() { modal.hidden = true; }

            var btnAbrir = document.getElementById('btn-abrir-cancelar-nfse');
            if (btnAbrir) btnAbrir.addEventListener('click', abrir);

            ['btn-fechar-cancelar-nfse', 'btn-voltar-cancelar-nfse'].forEach(function(id) {
                var el = document.getElementById(id);
                if (el) el.addEventListener('click', fechar);
            });

            modal.addEventListener('click', function(e) {
                if (e.target === modal) fechar();
            });

            var campo = document.getElementById('motivo-cancelamento');
            var contagem = document.getElementById('motivo-cancelamento-contagem');
            var confirmar = document.getElementById('btn-confirmar-cancelamento-nfse');
            function atualizarContagem() {
                if (!campo || !contagem) return;
                contagem.textContent = String(campo.value.length);
                if (confirmar) confirmar.disabled = campo.value.trim().length < 15;
            }
            if (campo) {
                campo.addEventListener('input', atualizarContagem);
                atualizarContagem();
            }

            @if ($errors->has('motivo') || $errors->has('codigo_motivo'))
                abrir();
            @endif
        })();
    </script>
@endpush
