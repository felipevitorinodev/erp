@extends('layouts.app')

@section('title', 'Funcionários')
@section('page_title', 'Funcionários')

@section('page_actions')
    <a href="{{ route('funcionario.create') }}" class="btn btn--primary btn--sm">+ Novo funcionário</a>
@endsection

@section('content')

    <x-filtros :action="route('funcionario.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome ou CPF">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$funcionarios">
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
                                <td data-label="Nome" class="col-titulo">{{ $funcionario->nome }}</td>
                                <td data-label="CPF">{{ $funcionario->cpf ?? '—' }}</td>
                                <td data-label="Cargo">{{ $funcionario->cargo ?? '—' }}</td>
                                <td data-label="Departamento">{{ $funcionario->departamento ?? '—' }}</td>
                                <td data-label="Admissão">{{ $funcionario->data_admissao ? $funcionario->data_admissao->format('d/m/Y') : '—' }}</td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativo" :valor="(bool) ($funcionario->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('funcionario.edit', $funcionario) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('funcionario.destroy', $funcionario) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este funcionário?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="cracha"
                                :colspan="7"
                                titulo="Nenhum funcionário cadastrado"
                                texto="Cadastre vendedores e técnicos para acompanhar vendas, ordens de serviço e comissões."
                                :acao="route('funcionario.create')"
                                acao-texto="+ Novo funcionário"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection