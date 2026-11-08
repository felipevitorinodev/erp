@extends('layouts.app')

@section('title', 'Inutilizar numeração')
@section('page_title', 'Inutilizar numeração')

@section('breadcrumb')
    <a href="{{ route('nfe.index') }}">NF-e</a> / Inutilizar
@endsection

@section('content')

    @php
        $modeloAtual = (int) old('modelo', $modeloPadrao);
        $serieNfe = $empresa->nfe_serie ?: '001';
        $serieNfce = $empresa->nfce_serie ?: '001';
    @endphp

    <div class="alert alert--warning" style="margin-bottom:1rem;">
        Use esta tela para números que ficaram pulados na série
        {{ (int) ($empresa->nfe_ambiente ?? 2) === 1 ? 'de produção' : 'de homologação' }} e nunca foram autorizados.
        Quando a SEFAZ está fora, a emissão é bloqueada e nenhum número é consumido.
        A NF-e e a NFC-e têm numerações separadas: escolha o modelo certo.
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <div class="card__header">
            <span class="card__title">Faixa</span>
        </div>
        <form method="POST" action="{{ route('nfe.inutilizar.store') }}" id="form-inutilizar">
            @csrf
            <div class="card__body">
                <div class="form-grid form-grid--col-4">
                    <div class="form-group form-group--full">
                        <label class="form-label form-label--required" for="inutilizacao-modelo">Modelo</label>
                        <select name="modelo" id="inutilizacao-modelo" class="form-control @error('modelo') is-invalid @enderror" style="max-width:320px;"
                            data-serie-nfe="{{ $serieNfe }}" data-serie-nfce="{{ $serieNfce }}">
                            <option value="55" @selected($modeloAtual === 55)>NF-e (modelo 55)</option>
                            <option value="65" @selected($modeloAtual === 65)>NFC-e (modelo 65)</option>
                        </select>
                        @error('modelo')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required" for="inutilizacao-serie">Série</label>
                        <input type="text" name="serie" id="inutilizacao-serie" class="form-control @error('serie') is-invalid @enderror"
                            value="{{ old('serie', $modeloAtual === 65 ? $serieNfce : $serieNfe) }}" maxlength="3" inputmode="numeric">
                        @error('serie')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Ano</label>
                        <input type="text" name="ano" class="form-control @error('ano') is-invalid @enderror"
                            value="{{ old('ano', now()->format('y')) }}" maxlength="2" inputmode="numeric">
                        @error('ano')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Número inicial</label>
                        <input type="number" name="numero_inicial" class="form-control @error('numero_inicial') is-invalid @enderror"
                            value="{{ old('numero_inicial') }}" min="1">
                        @error('numero_inicial')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Número final</label>
                        <input type="number" name="numero_final" class="form-control @error('numero_final') is-invalid @enderror"
                            value="{{ old('numero_final') }}" min="1">
                        @error('numero_final')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label form-label--required">Justificativa</label>
                    <textarea name="justificativa" id="justificativa-inutilizacao" rows="3" maxlength="255" minlength="15"
                        class="form-control @error('justificativa') is-invalid @enderror">{{ old('justificativa') }}</textarea>
                    <small class="form-hint"><span id="inutilizacao-contagem">0</span>/255 (mínimo 15)</small>
                    @error('justificativa')<span class="form-error">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="card__footer">
                <a href="{{ route('nfe.index') }}" class="btn btn--ghost">Voltar</a>
                <button type="submit" class="btn btn--danger" id="btn-inutilizar"
                    data-espera="Inutilizando..."
                    data-demora="Enviando a inutilização para a SEFAZ."
                    data-confirm="Inutilizar esta faixa na SEFAZ? Os números não poderão ser usados."
                    data-confirm-title="Inutilizar numeração"
                    data-confirm-ok="Inutilizar"
                    data-confirm-variant="danger">Inutilizar</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Últimos pedidos</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Modelo</th>
                            <th>Faixa</th>
                            <th>Série</th>
                            <th>Ambiente</th>
                            <th>Situação</th>
                            <th>Protocolo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($inutilizacoes as $pedido)
                            <tr>
                                <td data-label="Modelo">{{ (int) $pedido->modelo === 65 ? 'NFC-e' : 'NF-e' }}</td>
                                <td data-label="Faixa">{{ $pedido->numero_inicial }} a {{ $pedido->numero_final }}</td>
                                <td data-label="Série">{{ $pedido->serie }}</td>
                                <td data-label="Ambiente">{{ (int) $pedido->ambiente === 1 ? 'Produção' : 'Homologação' }}</td>
                                <td data-label="Situação">
                                    @if ($pedido->situacao === 'autorizada')
                                        <span class="badge badge--success">Inutilizada</span>
                                    @else
                                        <span class="badge badge--error">{{ $pedido->motivo_status ?: 'Rejeitada' }}</span>
                                    @endif
                                </td>
                                <td data-label="Protocolo">{{ $pedido->protocolo ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted empty-state">Nenhuma inutilização registrada.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        (function () {
            var campo = document.getElementById('justificativa-inutilizacao');
            var contagem = document.getElementById('inutilizacao-contagem');
            var botao = document.getElementById('btn-inutilizar');
            function atualizar() {
                if (!campo || !contagem) return;
                contagem.textContent = String(campo.value.length);
                if (botao) botao.disabled = campo.value.trim().length < 15;
            }
            if (campo) {
                campo.addEventListener('input', atualizar);
                atualizar();
            }

            // Trocar o modelo sugere a série dele, se a série ainda não foi mexida.
            var modelo = document.getElementById('inutilizacao-modelo');
            var serie = document.getElementById('inutilizacao-serie');
            if (modelo && serie) {
                modelo.addEventListener('change', function () {
                    var sugestoes = [modelo.getAttribute('data-serie-nfe'), modelo.getAttribute('data-serie-nfce')];
                    if (serie.value === '' || sugestoes.indexOf(serie.value) !== -1) {
                        serie.value = modelo.value === '65' ? sugestoes[1] : sugestoes[0];
                    }
                });
            }
        })();
    </script>
@endpush
