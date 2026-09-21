@extends('layouts.app')

@section('title', 'Grupos')
@section('page_title', 'Grupos')

@section('page_actions')
    <a href="{{ route('grupo.create') }}" class="btn btn--primary btn--sm">+ Nova grupo</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de grupos</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>grupo Pai</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr>
                                <td>{{ $grupo->nome }}</td>
                                <td>{{ $grupo->parent->nome ?? '—' }}</td>
                                <td>{{ $grupo->ativo ? 'Ativa' : 'Inativa' }}</td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('grupo.edit', $grupo) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('grupo.destroy', $grupo) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Excluir este grupo?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma grupo cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection