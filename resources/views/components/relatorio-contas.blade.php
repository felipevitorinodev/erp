@props([
    'action',
    'pessoa' => 'Cliente',
    'relacao' => 'cliente',
    'buscaPlaceholder' => 'Descrição ou cliente',
    'recebidoLabel' => 'Recebido',
    'abertoLabel' => 'A receber',
    'vazio' => 'Nenhuma conta no período.',
    'filtros',
    'categorias',
    'totais',
    'contas',
    'subtotais',
])

<x-filtros :action="$action">
    <x-filtro-periodo :inicio="$filtros['data_inicio']" :fim="$filtros['data_fim']" />
    <x-filtro-campo
        label="Situação"
        name="situacao"
        type="select"
        :value="$filtros['situacao'] ?? ''"
        :options="['em_aberto' => 'Em aberto', 'aberta' => 'Aberta', 'parcial' => 'Parcial', 'paga' => 'Paga', 'cancelada' => 'Cancelada']"
    />
    <x-filtro-campo
        label="Categoria"
        name="categoria_id"
        type="select"
        :value="$filtros['categoria_id'] ?? ''"
        :options="collect($categorias ?? [])->pluck('nome', 'id')"
    />
    <x-filtro-campo
        label="Vencimento"
        name="vencimento"
        type="select"
        :value="$filtros['vencimento'] ?? ''"
        :options="['vencidos' => 'Vencidos', 'a_vencer' => 'A vencer']"
        vazio="— Todos —"
    />
    <x-filtro-campo
        label="Busca"
        name="busca"
        :value="$filtros['busca'] ?? ''"
        :placeholder="$buscaPlaceholder"
    />
</x-filtros>

<x-relatorio-kpis>
    <x-relatorio-kpi
        label="Total no período"
        :valor="'R$ ' . number_format($totais['soma_valor'], 2, ',', '.')"
        :detalhe="$totais['qtd'] . ($totais['qtd'] === 1 ? ' título' : ' títulos')"
        icone="dollar"
    />
    <x-relatorio-kpi
        :label="$recebidoLabel"
        :valor="'R$ ' . number_format($totais['soma_pago'], 2, ',', '.')"
        :detalhe="number_format($totais['percentual_pago'], 1, ',', '.') . '% do total'"
        tom="success"
        icone="check"
        destaque
    />
    <x-relatorio-kpi
        :label="$abertoLabel"
        :valor="'R$ ' . number_format($totais['saldo_aberto'], 2, ',', '.')"
        :detalhe="$totais['qtd_aberto'] . ($totais['qtd_aberto'] === 1 ? ' título em aberto' : ' títulos em aberto')"
        tom="warning"
        icone="history"
    />
    <x-relatorio-kpi
        label="Vencido"
        :valor="'R$ ' . number_format($totais['saldo_vencido'], 2, ',', '.')"
        :detalhe="$totais['qtd_vencido'] . ($totais['qtd_vencido'] === 1 ? ' título vencido' : ' títulos vencidos')"
        tom="danger"
        icone="alert-triangle"
    />
</x-relatorio-kpis>

<x-tabela-card :paginator="$contas">
    <thead>
        <tr>
            <th>Nº</th>
            <th>Descrição</th>
            <th>{{ $pessoa }}</th>
            <th>Categoria</th>
            <th>Vencimento</th>
            <th class="col-num">Valor</th>
            <th class="col-num">Pago</th>
            <th>Situação</th>
        </tr>
    </thead>
    <tbody>
        @forelse($contas as $conta)
            @php
                $vencido = $conta->data_vencimento->lt(now()->startOfDay())
                    && ! in_array($conta->situacao, ['paga', 'cancelada'], true);
            @endphp
            <tr>
                <td data-label="Nº" class="text-muted">{{ $conta->id }}</td>
                <td data-label="Descrição">{{ $conta->descricao }}</td>
                <td data-label="{{ $pessoa }}">{{ $conta->{$relacao}->nome ?? '—' }}</td>
                <td data-label="Categoria">{{ $conta->categoria->nome ?? '—' }}</td>
                <td data-label="Vencimento" @class(['text--danger' => $vencido])>
                    {{ $conta->data_vencimento->format('d/m/Y') }}
                </td>
                <td data-label="Valor" class="col-num"><x-moeda :valor="$conta->valor" /></td>
                <td data-label="Pago" class="col-num"><x-moeda :valor="$conta->valor_pago" /></td>
                <td data-label="Situação">
                    <x-badge-situacao tipo="conta" :valor="$conta->situacao" />
                </td>
            </tr>
        @empty
            <x-tabela-vazia :colspan="8">{{ $vazio }}</x-tabela-vazia>
        @endforelse
    </tbody>
</x-tabela-card>

<x-relatorio-subtotais :itens="$subtotais" :pago-label="$recebidoLabel" />
