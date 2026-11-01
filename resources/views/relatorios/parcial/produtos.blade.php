<x-tabela-card :paginator="$produtos" titulo="Produtos">
    <thead>
        <tr>
            <th>Posição</th>
            <th>Produto</th>
            <th>Código</th>
            <th class="col-num">
                <a href="{{ $action.'?'.http_build_query(\App\Relatorios\Periodo::limpos(array_merge($filtros, ['ordenar' => 'qtd']))) }}">Qtd. vendida</a>
            </th>
            <th class="col-num">
                <a href="{{ $action.'?'.http_build_query(\App\Relatorios\Periodo::limpos(array_merge($filtros, ['ordenar' => 'valor']))) }}">Valor total</a>
            </th>
        </tr>
    </thead>
    <tbody>
        @forelse ($produtos as $produto)
            <tr>
                <td data-label="Posição">{{ $produtos->firstItem() + $loop->index }}º</td>
                <td data-label="Produto">{{ $produto->produto_nome }}</td>
                <td data-label="Código">{{ $produto->produto_codigo ?: '—' }}</td>
                <td data-label="Qtd. vendida" class="col-num">{{ number_format((float) $produto->qtd_total, 2, ',', '.') }}</td>
                <td data-label="Valor total" class="col-num"><x-moeda :valor="$produto->valor_total" /></td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="5">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($produtos->total() > 0)
        <tfoot>
            <tr>
                <th colspan="3" scope="row">Total</th>
                <td class="col-num" data-label="Quantidade">{{ number_format($totais['qtd_total'], 2, ',', '.') }}</td>
                <td class="col-num" data-label="Valor total"><x-moeda :valor="$totais['valor_total']" /></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
