<table class="grid">
    <thead>
        <tr>
            <th style="width: 42%;">Produto / serviço</th>
            <th class="r" style="width: 12%;">Qtd.</th>
            <th class="r" style="width: 16%;">Preço unit.</th>
            <th class="r" style="width: 14%;">Desconto</th>
            <th class="r" style="width: 16%;">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($itens as $item)
            <tr>
                <td>
                    <span class="item-nome">{{ $item->produto_nome }}</span>
                    @if($item->produto_codigo)
                        <span class="item-codigo">Cód. {{ $item->produto_codigo }}</span>
                    @endif
                </td>
                <td class="r num">{{ number_format($item->quantidade, 2, ',', '.') }}</td>
                <td class="r num">{{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                <td class="r num">{{ number_format($item->desconto, 2, ',', '.') }}</td>
                <td class="r num">{{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">Nenhum item neste documento.</td>
            </tr>
        @endforelse
    </tbody>
</table>
