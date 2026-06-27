@extends('layouts.app')

@section('title', 'Empresas')
@section('page_title', 'Empresas')

@section('page_actions')
    <a href="{{ route('empresa.create') }}" class="btn btn--primary btn--sm">+ Nova Empresa</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Empresas</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Razão Social</th>
                            <th>CNPJ</th>
                            <th>Inscrição Estadual</th>
                            <th>Inscrição Municipal</th>
                            <th>Cep</th>
                            <th>Cidade</th>
                            <th>Logradouro</th>
                            <th>Uf</th>
                            <th class="text-right" style="width: 1%; white-space: nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($empresas as $empresa)
                            <tr>
                                <td>{{ $empresa->id }}</td>
                                <td>{{ $empresa->razao_social }}</td>
                                <td>{{ $empresa->cnpj }}</td>
                                <td>{{ $empresa->ie }}</td>
                                <td>{{ $empresa->im }}</td>
                                <td>{{ $empresa->cep }}</td>
                                <td>{{ $empresa->cidade }}</td>
                                <td>{{ $empresa->logradouro }}</td>
                                <td>{{ $empresa->uf }}</td>
                                <td class="text-right" style="white-space: nowrap;">
                                    <a href="{{ route('empresa.edit', $empresa) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('empresa.destroy', $empresa) }}"
                                        style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center text-muted" style="padding: 2rem;">
                                    Nenhuma empresa cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection