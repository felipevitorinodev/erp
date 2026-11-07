<x-tabela-card :paginator="$contas" titulo="Contas">
    <thead>
        <tr>
            <th>Nº</th>
            <th><x-ordem coluna="descricao" label="Descrição" :filtros="$filtros" :action="$action" /></th>
            <th>{{ $pessoa }}</th>
            <th>Categoria</th>
            <th><x-ordem coluna="vencimento" label="Vencimento" :filtros="$filtros" :action="$action" /></th>
            <th class="col-num"><x-ordem coluna="valor" label="Valor" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num"><x-ordem coluna="pago" label="Pago" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th><x-ordem coluna="situacao" label="Situação" :filtros="$filtros" :action="$action" /></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($contas as $conta)
            @php
                $vencido = $conta->data_vencimento->lt(now()->startOfDay())
                    && ! in_array($conta->situacao, ['paga', 'cancelada'], true);
            @endphp
            <tr>
                <td data-label="Nº">{{ $conta->id }}</td>
                <td data-label="Descrição">{{ $conta->descricao }}</td>
                <td data-label="{{ $pessoa }}">{{ $conta->{$relacao}->nome ?? '—' }}</td>
                <td data-label="Categoria">{{ $conta->categoria->nome ?? '—' }}</td>
                <td data-label="Vencimento" @class(['text--danger' => $vencido])>{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                <td data-label="Valor" class="col-num"><x-moeda :valor="$conta->valor" /></td>
                <td data-label="Pago" class="col-num"><x-moeda :valor="$conta->valor_pago" /></td>
                <td data-label="Situação"><x-badge-situacao tipo="conta" :valor="$conta->situacao" /></td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="8">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($contas->total() > 0)
        <tfoot>
            <tr>
                <th colspan="5" scope="row">Total no período</th>
                <td class="col-num" data-label="Valor"><x-moeda :valor="$totais['soma_valor']" /></td>
                <td class="col-num" data-label="Pago"><x-moeda :valor="$totais['soma_pago']" /></td>
                <td></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>

<x-relatorio-subtotais :itens="$subtotais_categoria" :pago-label="$rotuloPago" />
