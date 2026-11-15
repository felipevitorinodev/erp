@extends('layouts.app')

@section('title', 'Grupos')
@section('page_title', 'Grupos')

@section('page_actions')
    <a href="{{ route('grupo.create') }}" class="btn btn--primary btn--sm">+ Novo grupo</a>
@endsection

@section('content')

    <x-filtros :action="route('grupo.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome do grupo">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$grupos">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Grupo pai</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($grupos as $grupo)
                            <tr>
                                <td data-label="Nome" class="col-titulo">{{ $grupo->nome }}</td>
                                <td data-label="Grupo pai">{{ $grupo->parent->nome ?? '—' }}</td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativo" :valor="(bool) ($grupo->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('grupo.edit', $grupo) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('grupo.destroy', $grupo) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este grupo?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="etiquetas"
                                :colspan="4"
                                titulo="Nenhum grupo cadastrado"
                                texto="Grupos organizam os produtos e facilitam buscas e relatórios."
                                :acao="route('grupo.create')"
                                acao-texto="+ Novo grupo"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection