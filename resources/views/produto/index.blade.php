@extends('layouts.app')

@section('title', 'Produtos')
@section('page_title', 'Produtos')

@section('page_actions')
    <a href="{{ route('produto.create') }}" class="btn btn--primary btn--sm">+ Novo Produto</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('produto.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, código ou código de barras">
                </div>
                <div class="form-group">
                    <label class="form-label">Tipo</label>
                    <select name="tipo" class="form-control">
                        <option value="">— Todos —</option>
                        <option value="1" {{ (string) ($filtros['tipo'] ?? '') === '1' ? 'selected' : '' }}>Produto</option>
                        <option value="2" {{ (string) ($filtros['tipo'] ?? '') === '2' ? 'selected' : '' }}>Serviço</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Grupo</label>
                    <select name="grupo_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($grupos as $grupo)
                            <option value="{{ $grupo->id }}"
                                {{ (string) ($filtros['grupo_id'] ?? '') === (string) $grupo->id ? 'selected' : '' }}>
                                {{ $grupo->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('produto.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Produtos</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Grupo</th>
                            <th class="col-num">Preço Venda</th>
                            <th class="col-num">Estoque</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                            <tr>
                                <td data-label="Código">{{ $produto->id ?? '—' }}</td>
                                <td data-label="Nome">{{ $produto->nome }}</td>
                                <td data-label="Grupo">{{ $produto->grupos->nome ?? '—' }}</td>
                                <td data-label="Preço Venda" class="col-num">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                                <td data-label="Estoque" class="col-num">{{ number_format((float) $produto->estoque_atual, 2, ',', '.') }}
                                    {{ $produto->unidadeMedida->sigla ?? '' }}</td>
                                <td data-label="Situação">{{ $produto->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('produto.edit', $produto) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('produto.destroy', $produto) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Excluir este produto?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum produto cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $produtos->withQueryString()->links() }}
        </div>
    </div>

@endsection