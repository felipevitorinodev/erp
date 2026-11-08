@extends('layouts.app')

@section('title', 'Empresas')
@section('page_title', 'Empresas')

@section('page_actions')
    @if (! auth()->user()->empresa_id)
        <a href="{{ route('empresa.create') }}" class="btn btn--primary btn--sm">+ Nova empresa</a>
    @endif
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
            <span class="card__title">Resultados</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Razão social</th>
                            <th>CNPJ</th>
                            <th>Inscrição estadual</th>
                            <th>Inscrição municipal</th>
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
                                <td data-label="Razão social">{{ $empresa->razao_social }}</td>
                                <td data-label="CNPJ">{{ $empresa->cnpj ?: 'Sem CNPJ' }}</td>
                                <td data-label="Inscrição estadual">{{ $empresa->ie }}</td>
                                <td data-label="Inscrição municipal">{{ $empresa->im }}</td>
                                <td data-label="Cep">{{ $empresa->cep }}</td>
                                <td data-label="Cidade">{{ $empresa->cidade }}</td>
                                <td data-label="Logradouro">{{ $empresa->logradouro }}</td>
                                <td data-label="Uf">{{ $empresa->uf }}</td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('empresa.edit', $empresa) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="predio"
                                :colspan="10"
                                titulo="Nenhuma empresa cadastrada"
                                :acao="(! auth()->user()->empresa_id) ? route('empresa.create') : null"
                                acao-texto="+ Nova empresa"
                                filtravel
                            />
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
