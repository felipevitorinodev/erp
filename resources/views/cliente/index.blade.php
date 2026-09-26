@extends('layouts.app')

@section('title', 'Clientes')
@section('page_title', 'Clientes')

@section('page_actions')
    <a href="{{ route('cliente.create') }}" class="btn btn--primary btn--sm">+ Novo Cliente</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('cliente.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, CPF ou CNPJ">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('cliente.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Clientes</span>
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
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                            <tr>
                                <td class="text-muted">{{ $cliente->id }}</td>
                                <td>{{ $cliente->nome }}</td>
                                <td>{{ $cliente->nome_fantasia ?? '—' }}</td>
                                <td>{{ $cliente->cpf ?: ($cliente->cnpj ?: '—') }}</td>
                                <td>{{ $cliente->telefone ?? $cliente->celular ?? '—' }}</td>
                                <td>
                                    @if($cliente->cidade)
                                        {{ $cliente->cidade }}{{ $cliente->estado ? '/' . $cliente->estado : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('cliente.edit', $cliente) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('cliente.destroy', $cliente) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Excluir este cliente?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum cliente cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $clientes->withQueryString()->links() }}
        </div>
    </div>

@endsection