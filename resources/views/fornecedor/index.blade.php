@extends('layouts.app')

@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')

@section('page_actions')
    <a href="{{ route('fornecedor.create') }}" class="btn btn--primary btn--sm">+ Novo Fornecedor</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('fornecedor.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, CPF ou CNPJ">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('fornecedor.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Fornecedores</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:60px;">Código</th>
                            <th>Nome</th>
                            <th>Nome Fantasia</th>
                            <th>CPF / CNPJ</th>
                            <th>Telefone</th>
                            <th>Cidade / UF</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fornecedores as $fornecedor)
                            <tr>
                                <td data-label="Código" class="text-muted">{{ $fornecedor->id }}</td>
                                <td data-label="Nome">{{ $fornecedor->nome }}</td>
                                <td data-label="Nome Fantasia">{{ $fornecedor->nome_fantasia ?? '—' }}</td>
                                <td data-label="CPF / CNPJ">{{ $fornecedor->cpf ?: ($fornecedor->cnpj ?: '—') }}</td>
                                <td data-label="Telefone">{{ $fornecedor->telefone ?? $fornecedor->celular ?? '—' }}</td>
                                <td data-label="Cidade / UF">
                                    @if($fornecedor->cidade)
                                        {{ $fornecedor->cidade }}{{ $fornecedor->estado ? '/' . $fornecedor->estado : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('fornecedor.edit', $fornecedor) }}"
                                        class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                        <x-icon name="edit" />
                                    </a>
                                    <form method="POST" action="{{ route('fornecedor.destroy', $fornecedor) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                            title="Excluir" aria-label="Excluir"
                                            data-confirm="Excluir este fornecedor?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-state">
                                    Nenhum fornecedor cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $fornecedores->withQueryString()->links() }}
        </div>
    </div>

@endsection