<x-tabela-card :paginator="$vendas" titulo="Vendas">
    <thead>
        <tr>
            <th><x-ordem coluna="numero" label="Número" :filtros="$filtros" :action="$action" /></th>
            <th>Cliente</th>
            <th><x-ordem coluna="data" label="Data" :filtros="$filtros" :action="$action" /></th>
            <th>Forma de pagamento</th>
            <th class="col-num"><x-ordem coluna="total" label="Total" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th><x-ordem coluna="situacao" label="Situação" :filtros="$filtros" :action="$action" /></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($vendas as $venda)
            <tr>
                <td data-label="Número">#{{ $venda->numero }}</td>
                <td data-label="Cliente">{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</td>
                <td data-label="Data">{{ $venda->data_venda->format('d/m/Y') }}</td>
                <td data-label="Forma de pagamento">{{ $venda->forma_pagamento ?: '—' }}</td>
                <td data-label="Total" class="col-num"><x-moeda :valor="$venda->total" /></td>
                <td data-label="Situação"><x-badge-situacao tipo="venda" :valor="$venda->situacao" /></td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="6">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($vendas->total() > 0)
        <tfoot>
            <tr>
                <th colspan="4" scope="row">Total das confirmadas</th>
                <td class="col-num" data-label="Total das confirmadas"><x-moeda :valor="$totais['valor_confirmadas']" /></td>
                <td></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
