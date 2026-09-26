@extends('layouts.app')

@section('title', 'Empresas')
@section('page_title', 'Empresas')

@section('page_actions')
    <a href="{{ route('empresa.create') }}" class="btn btn--primary btn--sm">+ Nova Empresa</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('empresa.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Razão social, fantasia ou CNPJ">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('empresa.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Empresas</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
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
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($empresas as $empresa)
                            <tr>
                                <td data-label="Código">{{ $empresa->id }}</td>
                                <td data-label="Razão Social">{{ $empresa->razao_social }}</td>
                                <td data-label="CNPJ">{{ $empresa->cnpj }}</td>
                                <td data-label="Inscrição Estadual">{{ $empresa->ie }}</td>
                                <td data-label="Inscrição Municipal">{{ $empresa->im }}</td>
                                <td data-label="Cep">{{ $empresa->cep }}</td>
                                <td data-label="Cidade">{{ $empresa->cidade }}</td>
                                <td data-label="Logradouro">{{ $empresa->logradouro }}</td>
                                <td data-label="Uf">{{ $empresa->uf }}</td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('empresa.edit', $empresa) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                        <x-icon name="edit" />
                                    </a>
                                    <form method="POST" action="{{ route('empresa.destroy', $empresa) }}"
                                        class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                            title="Excluir" aria-label="Excluir"
                                            data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            <x-icon name="trash" />
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="empty-state">
                                    Nenhuma empresa cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $empresas->withQueryString()->links() }}
        </div>
    </div>

@endsection
