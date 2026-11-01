<x-tabela-card :paginator="$contas" titulo="Títulos vencidos">
    <thead>
        <tr>
            <th><x-ordem coluna="vencimento" label="Vencimento" :filtros="$filtros" :action="$action" /></th>
            <th>{{ $pessoa }}</th>
            <th>Descrição</th>
            <th class="col-num"><x-ordem coluna="valor" label="Valor" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num">Pago</th>
            <th class="col-num"><x-ordem coluna="saldo" label="Saldo" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num">Dias</th>
            <th>Faixa</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($contas as $conta)
            @php
                $dias = \App\Relatorios\FaixaAtraso::dias($conta->data_vencimento);
                $saldo = round((float) $conta->valor - (float) $conta->valor_pago, 2);
            @endphp
            <tr>
                <td data-label="Vencimento">{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                <td data-label="{{ $pessoa }}">{{ $conta->{$relacao}->nome ?? '—' }}</td>
                <td data-label="Descrição">{{ $conta->descricao ?: '—' }}</td>
                <td data-label="Valor" class="col-num"><x-moeda :valor="$conta->valor" /></td>
                <td data-label="Pago" class="col-num"><x-moeda :valor="$conta->valor_pago" /></td>
                <td data-label="Saldo" class="col-num"><x-moeda :valor="$saldo" /></td>
                <td data-label="Dias" class="col-num">{{ $dias }}</td>
                <td data-label="Faixa">{{ \App\Relatorios\FaixaAtraso::rotulo(\App\Relatorios\FaixaAtraso::nome($dias)) }}</td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="8">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($contas->total() > 0)
        <tfoot>
            <tr>
                <th colspan="5" scope="row">Saldo vencido</th>
                <td class="col-num" data-label="Saldo vencido"><x-moeda :valor="$totais['saldo']" /></td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
