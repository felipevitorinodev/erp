{{-- Cabeçalho da Venda --}}
<div class="form-grid form-grid--col-3">
    <div class="form-group">
        <label class="form-label">Cliente</label>
        <select name="cliente_id" class="form-control @error('cliente_id') is-invalid @enderror">
            <option value="">— Consumidor Final —</option>
            @foreach($clientes as $cliente)
                <option value="{{ $cliente->id }}"
                    {{ old('cliente_id', $venda->cliente_id ?? '') == $cliente->id ? 'selected' : '' }}>
                    {{ $cliente->nome }}
                    @if($cliente->cpf || $cliente->cnpj)
                        — {{ $cliente->cpf ?: $cliente->cnpj }}
                    @endif
                </option>
            @endforeach
        </select>
        @error('cliente_id')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Data da Venda</label>
        <input type="date" name="data_venda"
            class="form-control @error('data_venda') is-invalid @enderror"
            value="{{ old('data_venda', isset($venda) ? $venda->data_venda?->format('Y-m-d') : date('Y-m-d')) }}">
        @error('data_venda')<span class="form-error">{{ $message }}</span>@enderror
    </div>

    <div class="form-group">
        <label class="form-label form-label--required">Situação</label>
        <select name="situacao" class="form-control @error('situacao') is-invalid @enderror">
            <option value="em_andamento" {{ old('situacao', $venda->situacao ?? 'em_andamento') === 'em_andamento' ? 'selected' : '' }}>Em andamento</option>
            <option value="confirmada" {{ old('situacao', $venda->situacao ?? '') === 'confirmada' ? 'selected' : '' }}>Confirmada</option>
        </select>
        @error('situacao')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

<div class="form-grid form-grid--col-4" style="margin-top:1rem;">
    <div class="form-group form-grid--col-1">
        <label class="form-label">Forma de Pagamento</label>
        <input type="text" name="forma_pagamento"
            class="form-control @error('forma_pagamento') is-invalid @enderror"
            value="{{ old('forma_pagamento', $venda->forma_pagamento ?? '') }}"
            maxlength="100" placeholder="Ex: Dinheiro, Pix, Cartão...">
        @error('forma_pagamento')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

{{-- Itens --}}
<div style="margin-top:1.5rem;">
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.75rem;">
        <span style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--color-text-mid);">
            Itens da Venda
        </span>
        <button type="button" class="btn btn--ghost btn--sm" id="btn-add-item">+ Adicionar Item</button>
    </div>

    <div class="table-wrap">
        <table class="table" id="tabela-itens">
            <thead>
                <tr>
                    <th style="min-width:260px;">Produto</th>
                    <th style="width:100px;">Qtd.</th>
                    <th style="width:130px;">Preço Unit.</th>
                    <th style="width:110px;">Desconto</th>
                    <th style="width:120px;" class="text-right">Total</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody id="itens-body">
                @php
                    $itensExistentes = isset($venda) ? $venda->itens : collect();
                @endphp
                @forelse($itensExistentes as $i => $item)
                    <tr class="item-row">
                        <td>
                            <select name="itens[{{ $i }}][produto_id]" class="form-control select-produto">
                                <option value="">— selecione —</option>
                                @foreach($produtos as $produto)
                                    <option value="{{ $produto->id }}"
                                        data-preco="{{ $produto->preco_venda }}"
                                        data-codigo="{{ $produto->codigo }}"
                                        {{ $item->produto_id == $produto->id ? 'selected' : '' }}>
                                        {{ $produto->nome }}
                                        @if($produto->codigo) ({{ $produto->codigo }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input type="text" name="itens[{{ $i }}][quantidade]"
                                class="form-control text-right input-qtd"
                                value="{{ number_format($item->quantidade, 2, ',', '.') }}">
                        </td>
                        <td>
                            <input type="text" name="itens[{{ $i }}][preco_unitario]"
                                class="form-control text-right input-preco"
                                value="{{ number_format($item->preco_unitario, 2, ',', '.') }}">
                        </td>
                        <td>
                            <input type="text" name="itens[{{ $i }}][desconto]"
                                class="form-control text-right input-desc-item"
                                value="{{ number_format($item->desconto, 2, ',', '.') }}">
                        </td>
                        <td class="text-right">
                            <span class="span-total-item">R$ {{ number_format($item->total, 2, ',', '.') }}</span>
                        </td>
                        <td class="text-right">
                            <button type="button" class="btn btn--danger btn--sm btn-remove-item">✕</button>
                        </td>
                    </tr>
                @empty
                    {{-- linha inicial vazia --}}
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Totais --}}
<div style="margin-top:1rem; display:flex; justify-content:flex-end;">
    <div style="width:280px;">
        <div style="display:flex; justify-content:space-between; padding:0.35rem 0; border-bottom:1px solid var(--color-border);">
            <span class="text-muted" style="font-size:12px;">Subtotal</span>
            <span id="label-subtotal" style="font-size:12px;">R$ 0,00</span>
            <input type="hidden" name="subtotal" id="input-subtotal" value="{{ old('subtotal', $venda->subtotal ?? '0') }}">
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.35rem 0; border-bottom:1px solid var(--color-border);">
            <span class="text-muted" style="font-size:12px;">Desconto (R$)</span>
            <input type="text" name="desconto" id="input-desconto"
                class="form-control text-right"
                style="width:100px; padding:0.2rem 0.4rem; font-size:12px;"
                value="{{ old('desconto', number_format($venda->desconto ?? 0, 2, ',', '.')) }}">
        </div>
        <div style="display:flex; justify-content:space-between; align-items:center; padding:0.35rem 0; border-bottom:1px solid var(--color-border);">
            <span class="text-muted" style="font-size:12px;">Acréscimo (R$)</span>
            <input type="text" name="acrescimo" id="input-acrescimo"
                class="form-control text-right"
                style="width:100px; padding:0.2rem 0.4rem; font-size:12px;"
                value="{{ old('acrescimo', number_format($venda->acrescimo ?? 0, 2, ',', '.')) }}">
        </div>
        <div style="display:flex; justify-content:space-between; padding:0.5rem 0; font-weight:700;">
            <span>Total</span>
            <span id="label-total">R$ 0,00</span>
            <input type="hidden" name="total" id="input-total" value="{{ old('total', $venda->total ?? '0') }}">
        </div>
    </div>
</div>

<div class="form-grid form-grid--col-1" style="margin-top:1rem;">
    <div class="form-group">
        <label class="form-label">Observações</label>
        <textarea name="observacoes" class="form-control @error('observacoes') is-invalid @enderror"
            rows="2" style="resize:none;" maxlength="1000">{{ old('observacoes', $venda->observacoes ?? '') }}</textarea>
        @error('observacoes')<span class="form-error">{{ $message }}</span>@enderror
    </div>
</div>

@push('scripts')
@php
    $produtosJson = $produtos->map(function ($p) {
        return [
            'id'     => $p->id,
            'nome'   => $p->nome,
            'codigo' => $p->codigo,
            'preco'  => (float) $p->preco_venda,
        ];
    });
@endphp
<script>
(function () {
    var produtos = @json($produtosJson);

    var indice = {{ isset($venda) ? $venda->itens->count() : 0 }};

    function parseBR(val) {
        if (!val) return 0;
        return parseFloat(String(val).replace(/\./g, '').replace(',', '.')) || 0;
    }

    function formatBR(val) {
        return 'R$ ' + val.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatInput(val) {
        return val.toFixed(2).replace('.', ',');
    }

    function calcularLinha(row) {
        var qtd   = parseBR(row.querySelector('.input-qtd').value);
        var preco = parseBR(row.querySelector('.input-preco').value);
        var desc  = parseBR(row.querySelector('.input-desc-item').value);
        var total = Math.max(0, (qtd * preco) - desc);
        row.querySelector('.span-total-item').textContent = formatBR(total);
        return total;
    }

    function recalcularTotais() {
        var subtotal = 0;
        document.querySelectorAll('#itens-body .item-row').forEach(function (row) {
            subtotal += calcularLinha(row);
        });

        var desconto  = parseBR(document.getElementById('input-desconto').value);
        var acrescimo = parseBR(document.getElementById('input-acrescimo').value);
        var total     = Math.max(0, subtotal - desconto + acrescimo);

        document.getElementById('label-subtotal').textContent = formatBR(subtotal);
        document.getElementById('label-total').textContent    = formatBR(total);
        document.getElementById('input-subtotal').value       = subtotal.toFixed(2);
        document.getElementById('input-total').value          = total.toFixed(2);
    }

    function criarLinha(idx) {
        var opcoes = '<option value="">— selecione —</option>';
        produtos.forEach(function (p) {
            opcoes += '<option value="' + p.id + '" data-preco="' + p.preco + '" data-codigo="' + (p.codigo || '') + '">'
                + p.nome + (p.codigo ? ' (' + p.codigo + ')' : '') + '</option>';
        });

        var tr = document.createElement('tr');
        tr.className = 'item-row';
        tr.innerHTML =
            '<td><select name="itens[' + idx + '][produto_id]" class="form-control select-produto">' + opcoes + '</select></td>' +
            '<td><input type="text" name="itens[' + idx + '][quantidade]" class="form-control text-right input-qtd" value="1,00"></td>' +
            '<td><input type="text" name="itens[' + idx + '][preco_unitario]" class="form-control text-right input-preco" value="0,00"></td>' +
            '<td><input type="text" name="itens[' + idx + '][desconto]" class="form-control text-right input-desc-item" value="0,00"></td>' +
            '<td class="text-right"><span class="span-total-item">R$ 0,00</span></td>' +
            '<td class="text-right"><button type="button" class="btn btn--danger btn--sm btn-remove-item">✕</button></td>';

        return tr;
    }

    function bindLinha(row) {
        row.querySelector('.select-produto').addEventListener('change', function () {
            var opt = this.options[this.selectedIndex];
            if (opt.value) {
                row.querySelector('.input-preco').value = formatInput(parseFloat(opt.dataset.preco || 0));
                calcularLinha(row);
                recalcularTotais();
            }
        });

        ['input-qtd', 'input-preco', 'input-desc-item'].forEach(function (cls) {
            row.querySelector('.' + cls).addEventListener('input', function () {
                calcularLinha(row);
                recalcularTotais();
            });
        });

        row.querySelector('.btn-remove-item').addEventListener('click', function () {
            row.remove();
            recalcularTotais();
        });
    }

    // Bind nas linhas existentes
    document.querySelectorAll('#itens-body .item-row').forEach(bindLinha);

    document.getElementById('btn-add-item').addEventListener('click', function () {
        var row = criarLinha(indice++);
        document.getElementById('itens-body').appendChild(row);
        bindLinha(row);
    });

    ['input-desconto', 'input-acrescimo'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', recalcularTotais);
    });

    // Calcula ao carregar (para edição)
    recalcularTotais();
})();
</script>
@endpush