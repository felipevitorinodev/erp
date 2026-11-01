<x-tabela-card :paginator="$vendedores" titulo="Vendedores">
    <thead>
        <tr>
            <th><x-ordem coluna="nome" label="Vendedor" :filtros="$filtros" :action="$action" /></th>
            <th class="col-num"><x-ordem coluna="qtd" label="Confirmadas" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num"><x-ordem coluna="faturamento" label="Faturamento" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num"><x-ordem coluna="ticket" label="Ticket médio" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num">Em andamento</th>
            <th class="col-num">Canceladas</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($vendedores as $linha)
            <tr>
                <td data-label="Vendedor">{{ $linha['nome'] }}</td>
                <td data-label="Confirmadas" class="col-num">{{ $linha['qtd_confirmadas'] }}</td>
                <td data-label="Faturamento" class="col-num"><x-moeda :valor="$linha['faturamento']" /></td>
                <td data-label="Ticket médio" class="col-num"><x-moeda :valor="$linha['ticket']" /></td>
                <td data-label="Em andamento" class="col-num">{{ $linha['qtd_andamento'] }}</td>
                <td data-label="Canceladas" class="col-num">{{ $linha['qtd_canceladas'] }}</td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="6">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($vendedores->total() > 0)
        <tfoot>
            <tr>
                <th colspan="2" scope="row">Total das confirmadas</th>
                <td class="col-num" data-label="Faturamento"><x-moeda :valor="$totais['faturamento']" /></td>
                <td class="col-num" data-label="Ticket médio"><x-moeda :valor="$totais['ticket']" /></td>
                <td class="col-num" data-label="Em andamento">{{ $totais['qtd_andamento'] }}</td>
                <td class="col-num" data-label="Canceladas">{{ $totais['qtd_canceladas'] }}</td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
