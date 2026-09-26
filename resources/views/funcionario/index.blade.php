@extends('layouts.app')

@section('title', 'Funcionários')
@section('page_title', 'Funcionários')

@section('page_actions')
    <a href="{{ route('funcionario.create') }}" class="btn btn--primary btn--sm">+ Novo Funcionário</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('funcionario.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome ou CPF">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('funcionario.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Funcionários</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Cargo</th>
                            <th>Departamento</th>
                            <th>Admissão</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($funcionarios as $funcionario)
                            <tr>
                                <td data-label="Nome">{{ $funcionario->nome }}</td>
                                <td data-label="CPF">{{ $funcionario->cpf ?? '—' }}</td>
                                <td data-label="Cargo">{{ $funcionario->cargo ?? '—' }}</td>
                                <td data-label="Departamento">{{ $funcionario->departamento ?? '—' }}</td>
                                <td data-label="Admissão">{{ $funcionario->data_admissao ? $funcionario->data_admissao->format('d/m/Y') : '—' }}</td>
                                <td data-label="Situação">
                                    @if($funcionario->ativo)
                                        <span class="badge badge--success">Ativo</span>
                                    @else
                                        <span class="badge badge--neutral">Inativo</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('funcionario.edit', $funcionario) }}"
                                        class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                        <x-icon name="edit" />
                                    </a>
                                    <form method="POST" action="{{ route('funcionario.destroy', $funcionario) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                            title="Excluir" aria-label="Excluir"
                                            data-confirm="Excluir este funcionário?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty-state">
                                    Nenhum funcionário cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $funcionarios->withQueryString()->links() }}
        </div>
    </div>

@endsection