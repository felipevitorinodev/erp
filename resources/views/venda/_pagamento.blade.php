@php
    $contas = $venda->contasReceber;
    $vigentes = $contas->where('situacao', '!=', 'cancelada');
    $emAberto = $vigentes->sum(fn ($conta) => max(0, (float) $conta->valor - (float) $conta->valor_pago));
    $recebido = $vigentes->sum(fn ($conta) => (float) $conta->valor_pago);
    $forma = $venda->formaPagamento;
    $aPrazo = $forma?->isPrazo() && ! $forma->ehCartaoDebito();
    $podeFinanceiro = auth()->user()->pode('financeiro');

    if ($contas->count() > 1) {
        $condicao = $contas->count().' parcelas';
    } elseif ($contas->count() === 1) {
        $condicao = '1 parcela';
    } elseif ($aPrazo && $venda->situacao === 'em_andamento') {
        $condicao = 'A prazo';
    } elseif (filled($venda->forma_pagamento)) {
        $condicao = 'À vista';
    } else {
        $condicao = 'Não informada';
    }
@endphp

<div class="card venda-pagamento">
    <div class="card__header">
        <span class="card__title">Pagamento</span>
    </div>
    <div class="card__body">
        <dl class="ficha__grid">
            <x-ficha-campo label="Forma">{{ $venda->forma_pagamento ?: '—' }}</x-ficha-campo>
            <x-ficha-campo label="Condição">{{ $condicao }}</x-ficha-campo>
            @if ($contas->isNotEmpty())
                <x-ficha-campo label="Em aberto"><x-moeda :valor="$emAberto" /></x-ficha-campo>
                <x-ficha-campo label="Recebido"><x-moeda :valor="$recebido" /></x-ficha-campo>
            @else
                <x-ficha-campo label="Valor"><x-moeda :valor="$venda->total" /></x-ficha-campo>
            @endif
        </dl>

        @if ($contas->isEmpty() && $aPrazo && $venda->situacao === 'em_andamento')
            <p class="venda-pagamento__nota">As parcelas entram em Contas a receber quando a venda for confirmada.</p>
        @elseif ($contas->isEmpty() && filled($venda->forma_pagamento))
            <p class="venda-pagamento__nota">Recebido na venda. Esta forma não gera parcelas.</p>
        @elseif ($contas->isEmpty())
            <p class="venda-pagamento__nota">Nenhuma forma de pagamento informada.</p>
        @endif
    </div>

    @if ($contas->isNotEmpty())
        <div class="table-wrap">
            <table class="table table--cards">
                <thead>
                    <tr>
                        <th>Parcela</th>
                        <th>Vencimento</th>
                        <th class="col-num">Valor</th>
                        <th class="col-num">Pago</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($contas as $indice => $conta)
                        @php
                            $documento = null;
                            if (preg_match('/Documento (.+?)\./', (string) $conta->observacoes, $encontrado)) {
                                $documento = $encontrado[1];
                            }
                        @endphp
                        <tr>
                            <td data-label="Parcela">
                                @if ($podeFinanceiro)
                                    <a href="{{ route('conta-receber.show', $conta) }}">{{ $indice + 1 }}/{{ $contas->count() }}</a>
                                @else
                                    {{ $indice + 1 }}/{{ $contas->count() }}
                                @endif
                                @if ($documento)
                                    <span class="text-muted" style="font-size:11px;">Cheque {{ $documento }}</span>
                                @endif
                            </td>
                            <td data-label="Vencimento">{{ $conta->data_vencimento?->format('d/m/Y') ?? '—' }}</td>
                            <td data-label="Valor" class="col-num"><x-moeda :valor="$conta->valor" /></td>
                            <td data-label="Pago" class="col-num"><x-moeda :valor="$conta->valor_pago" /></td>
                            <td data-label="Situação"><x-badge-situacao tipo="conta" :valor="$conta->situacao" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
