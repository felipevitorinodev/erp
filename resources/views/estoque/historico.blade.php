@extends('layouts.app')

@section('title', 'Histórico de estoque')
@section('page_title', 'Histórico de estoque')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('estoque.ajuste.create') }}" class="btn btn--primary btn--sm">+ Novo ajuste</a>
    </div>
@endsection

@section('content')

    <x-filtros :action="route('estoque.historico')" principal="produto_id">
                <div class="form-group filtros__principal">
                    <label class="form-label" for="input-produto">Produto</label>
                    <div class="autocomplete-wrap">
                        <input type="text" id="input-produto" class="form-control"
                            value="{{ $produtoSelecionado->nome ?? '' }}"
                            placeholder="Digite para buscar..." autocomplete="off">
                        <input type="hidden" name="produto_id" id="hidden-produto-id"
                            value="{{ $filtros['produto_id'] ?? '' }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-tipo">Tipo</label>
                    <select id="campo-tipo" name="tipo" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach(['entrada' => 'Entrada', 'saida' => 'Saída', 'ajuste' => 'Ajuste'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['tipo'] ?? '') === $valor ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_inicio">Data início</label>
                    <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_fim">Data fim</label>
                    <input id="campo-data_fim" type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Movimentações" :paginator="$movimentacoes">
                    <thead>
                        <tr>
                            <th>Data</th>
                            <th>Produto</th>
                            <th>Tipo</th>
                            <th class="col-num">Quantidade</th>
                            <th class="col-num">Antes</th>
                            <th class="col-num">Depois</th>
                            <th>Motivo</th>
                            <th>Origem</th>
                            <th>Usuário</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movimentacoes as $mov)
                            <tr>
                                <td data-label="Data">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                                <td data-label="Produto">{{ $mov->produto->nome ?? '—' }}</td>
                                <td data-label="Tipo">
                                    @if($mov->tipo === 'entrada')
                                        <span class="badge badge--success">Entrada</span>
                                    @elseif($mov->tipo === 'saida')
                                        <span class="badge badge--error">Saída</span>
                                    @else
                                        <span class="badge badge--info">Ajuste</span>
                                    @endif
                                </td>
                                <td data-label="Quantidade" class="col-num">{{ number_format($mov->quantidade, 2, ',', '.') }}</td>
                                <td data-label="Antes" class="col-num">{{ number_format($mov->estoque_antes, 2, ',', '.') }}</td>
                                <td data-label="Depois" class="col-num">{{ number_format($mov->estoque_depois, 2, ',', '.') }}</td>
                                <td data-label="Motivo">{{ $mov->motivo }}</td>
                                <td data-label="Origem">{{ ['venda' => 'Venda', 'entrada_estoque' => 'Entrada de estoque', 'ajuste_manual' => 'Ajuste manual'][$mov->origem] ?? ($mov->origem ? ucfirst(str_replace('_', ' ', $mov->origem)) : '—') }}</td>
                                <td data-label="Usuário">{{ $mov->usuario->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="estoque"
                                :colspan="9"
                                titulo="Nenhuma movimentação encontrada"
                                texto="Vendas, entradas e ajustes deste produto aparecem aqui."
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection

@push('scripts')
<script>
(function ($) {
    initAutocomplete('#input-produto', '#hidden-produto-id', '{{ route('api.produtos.busca') }}', function (item) {
        return (item.codigo ? item.codigo + ' — ' : '') + item.nome;
    });
})(jQuery);
</script>
@endpush
