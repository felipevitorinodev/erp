@extends('layouts.app')

@section('title', 'Histórico de Estoque')
@section('page_title', 'Histórico de Estoque')

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('estoque.ajuste.create') }}" class="btn btn--primary btn--sm">+ Novo Ajuste</a>
    </div>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('estoque.historico') }}" class="form-grid form-grid--col-3">
                <div class="form-group">
                    <label class="form-label">Produto</label>
                    <div class="autocomplete-wrap">
                        <input type="text" id="input-produto" class="form-control"
                            value="{{ $produtoSelecionado->nome ?? '' }}"
                            placeholder="Digite para buscar..." autocomplete="off">
                        <input type="hidden" name="produto_id" id="hidden-produto-id"
                            value="{{ $filtros['produto_id'] ?? '' }}">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach(['entrada' => 'Entrada', 'saida' => 'Saída', 'ajuste' => 'Ajuste'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['tipo'] ?? '') === $valor ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data início</label>
                    <input type="date" name="data_inicio" class="form-control" value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data fim</label>
                    <input type="date" name="data_fim" class="form-control" value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('estoque.historico') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Movimentações</span>
        </div>
        <div class="card__body" style="padding:0;">
            <div class="table-wrap">
                <table class="table table--cards">
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
                                <td data-label="Origem">{{ $mov->origem ?? '—' }}</td>
                                <td data-label="Usuário">{{ $mov->usuario->name ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma movimentação encontrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $movimentacoes->withQueryString()->links() }}
        </div>
    </div>

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
