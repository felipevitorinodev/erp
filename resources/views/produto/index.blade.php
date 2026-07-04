@extends('layouts.app')

@section('title', 'Produtos')
@section('page_title', 'Produtos')

@section('page_actions')
    <a href="{{ route('produto.create') }}" class="btn btn--primary btn--sm">+ Novo Produto</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Produtos</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Grupo</th>
                            <th>Preço Venda</th>
                            <th>Estoque</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                            <tr>
                                <td>{{ $produto->id ?? '—' }}</td>
                                <td>{{ $produto->nome }}</td>
                                <td>{{ $produto->grupos->nome ?? '—' }}</td>
                                <td>R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                                <td>{{ rtrim(rtrim($produto->estoque_atual, '0'), '.') }}
                                    {{ $produto->unidadeMedida->sigla ?? '' }}</td>
                                <td>{{ $produto->ativo ? 'Ativo' : 'Inativo' }}</td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('produto.edit', $produto) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('produto.destroy', $produto) }}"
                                        style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            onclick="return confirm('Excluir este produto?')">
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
    </div>

@endsection