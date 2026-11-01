<x-tabela-card :paginator="$produtos" titulo="Estoque">
    <thead>
        <tr>
            <th><x-ordem coluna="codigo" label="Código" :filtros="$filtros" :action="$action" /></th>
            <th><x-ordem coluna="nome" label="Produto" :filtros="$filtros" :action="$action" /></th>
            <th>Grupo</th>
            <th>Unidade</th>
            <th class="col-num"><x-ordem coluna="minimo" label="Estoque mínimo" :filtros="$filtros" :action="$action" inicial="desc" /></th>
            <th class="col-num"><x-ordem coluna="atual" label="Estoque atual" :filtros="$filtros" :action="$action" inicial="desc" /></th>
        </tr>
    </thead>
    <tbody>
        @forelse ($produtos as $produto)
            @php
                $critico = (float) $produto->estoque_atual <= (float) $produto->estoque_minimo;
            @endphp
            <tr @class(['text--danger' => $critico])>
                <td data-label="Código">{{ $produto->codigo ?: '—' }}</td>
                <td data-label="Produto">{{ $produto->nome }}</td>
                <td data-label="Grupo">{{ $produto->grupos->nome ?? '—' }}</td>
                <td data-label="Unidade">{{ $produto->unidadeMedida->sigla ?? ($produto->unidadeMedida->nome ?? '—') }}</td>
                <td data-label="Estoque mínimo" class="col-num">{{ number_format((float) $produto->estoque_minimo, 2, ',', '.') }}</td>
                <td data-label="Estoque atual" class="col-num">{{ number_format((float) $produto->estoque_atual, 2, ',', '.') }}</td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="6">Nenhum dado para os filtros selecionados.</x-tabela-vazia>
        @endforelse
    </tbody>
    @if ($produtos->total() > 0)
        <tfoot>
            <tr>
                <th colspan="5" scope="row">Valor em estoque (custo)</th>
                <td class="col-num" data-label="Valor em estoque"><x-moeda :valor="$totais['valor_custo']" /></td>
            </tr>
        </tfoot>
    @endif
</x-tabela-card>
