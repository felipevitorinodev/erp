@extends('layouts.app')

@section('title', 'Empresas')
@section('page_title', 'Empresas')

@section('page_actions')
    @if (! auth()->user()->empresa_id)
        <a href="{{ route('empresa.create') }}" class="btn btn--primary btn--sm">+ Nova empresa</a>
    @endif
@endsection

@section('content')

    <x-filtros :action="route('empresa.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Razão social, fantasia ou CNPJ">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$empresas">
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
                                <td data-label="Razão social" class="col-titulo">{{ $empresa->razao_social }}</td>
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
    </x-tabela-card>

@endsection
