<x-tabela-card :paginator="$ranking" titulo="Clientes">
    <thead>
        <tr>
            <th>Cliente</th>
            <th>Classe</th>
            <th class="col-num">Vendas</th>
            <th class="col-num">Faturamento</th>
            <th class="col-num">Participação</th>
            <th class="col-num">Acumulado</th>
            <th class="col-num">Ticket médio</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($ranking as $linha)
            <tr>
                <td data-label="Cliente">{{ $linha['nome'] }}</td>
                <td data-label="Classe">{{ $linha['classe'] }}</td>
                <td data-label="Vendas" class="col-num">{{ $linha['qtd'] }}</td>
                <td data-label="Faturamento" class="col-num"><x-moeda :valor="$linha['valor']" /></td>
                <td data-label="Participação" class="col-num">{{ number_format($linha['percentual'], 1, ',', '.') }}%</td>
                <td data-label="Acumulado" class="col-num">{{ number_format($linha['acumulado'], 1, ',', '.') }}%</td>
                <td data-label="Ticket médio" class="col-num"><x-moeda :valor="$linha['ticket']" /></td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="7">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($ranking->total() > 0)
        <tfoot>
            <tr>
                <th colspan="3" scope="row">Total das confirmadas</th>
                <td class="col-num" data-label="Faturamento"><x-moeda :valor="$totais['faturamento']" /></td>
                <td colspan="3"></td>
            </tr>
            @if ($totais['faturamento_lista'] != $totais['faturamento'])
                <tr>
                    <th colspan="3" scope="row">Total da seleção</th>
                    <td class="col-num" data-label="Total da seleção"><x-moeda :valor="$totais['faturamento_lista']" /></td>
                    <td colspan="3"></td>
                </tr>
            @endif
        </tfoot>
    @endif
</x-tabela-card>
