<x-tabela-card :paginator="$debitos" titulo="Clientes com débito">
    <thead>
        <tr>
            <th>Cliente</th>
            <th class="col-num">Títulos</th>
            <th class="col-num">Saldo em aberto</th>
            <th class="col-num">Saldo vencido</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($debitos as $linha)
            <tr>
                <td data-label="Cliente">{{ $linha['nome'] }}</td>
                <td data-label="Títulos" class="col-num">{{ $linha['qtd'] }}</td>
                <td data-label="Saldo em aberto" class="col-num"><x-moeda :valor="$linha['saldo']" /></td>
                <td data-label="Saldo vencido" class="col-num"><x-moeda :valor="$linha['vencido']" /></td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="4">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($debitos->total() > 0)
        <tfoot>
            <tr>
                <th scope="row">Total</th>
                <td class="col-num" data-label="Títulos">{{ $totais['qtd'] }}</td>
                <td class="col-num" data-label="Saldo em aberto"><x-moeda :valor="$totais['saldo']" /></td>
                <td class="col-num" data-label="Saldo vencido"><x-moeda :valor="$totais['vencido']" /></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
