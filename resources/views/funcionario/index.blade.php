@extends('layouts.app')

@section('title', 'Funcionários')
@section('page_title', 'Funcionários')

@section('page_actions')
    <a href="{{ route('funcionario.create') }}" class="btn btn--primary btn--sm">+ Novo Funcionário</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Funcionários</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>CPF</th>
                            <th>Cargo</th>
                            <th>Departamento</th>
                            <th>Admissão</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($funcionarios as $funcionario)
                            <tr>
                                <td>{{ $funcionario->nome }}</td>
                                <td>{{ $funcionario->cpf ?? '—' }}</td>
                                <td>{{ $funcionario->cargo ?? '—' }}</td>
                                <td>{{ $funcionario->departamento ?? '—' }}</td>
                                <td>{{ $funcionario->data_admissao ? $funcionario->data_admissao->format('d/m/Y') : '—' }}</td>
                                <td>
                                    {{ $funcionario->ativo ? 'Ativo' : 'Inativo' }}
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('funcionario.edit', $funcionario) }}"
                                        class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('funcionario.destroy', $funcionario) }}"
                                        style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            onclick="return confirm('Excluir este funcionário?')">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum funcionário cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection