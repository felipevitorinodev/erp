<x-tabela-card :paginator="$dias" titulo="Dias da semana">
    <thead>
        <tr>
            <th>Dia</th>
            <th class="col-num">Vendas</th>
            <th class="col-num">Faturamento</th>
            <th class="col-num">Ticket médio</th>
            <th class="col-num">Participação</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($dias as $linha)
            <tr>
                <td data-label="Dia">{{ $linha['nome'] }}</td>
                <td data-label="Vendas" class="col-num">{{ $linha['qtd'] }}</td>
                <td data-label="Faturamento" class="col-num"><x-moeda :valor="$linha['valor']" /></td>
                <td data-label="Ticket médio" class="col-num"><x-moeda :valor="$linha['ticket']" /></td>
                <td data-label="Participação" class="col-num">{{ number_format($linha['percentual'], 1, ',', '.') }}%</td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="5">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($dias->total() > 0)
        <tfoot>
            <tr>
                <th scope="row">Total das confirmadas</th>
                <td class="col-num" data-label="Vendas">{{ $totais['qtd'] }}</td>
                <td class="col-num" data-label="Faturamento"><x-moeda :valor="$totais['faturamento']" /></td>
                <td class="col-num" data-label="Ticket médio"><x-moeda :valor="$totais['ticket']" /></td>
                <td></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
