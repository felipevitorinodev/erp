@extends('layouts.app')

@section('title', 'Categorias financeiras')
@section('page_title', 'Categorias financeiras')

@section('page_actions')
    <a href="{{ route('categoria-financeira.create') }}" class="btn btn--primary btn--sm">+ Nova categoria</a>
@endsection

@section('content')

    <x-filtros :action="route('categoria-financeira.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $busca ?? '' }}"
                        placeholder="Nome da categoria">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$categorias">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categorias as $categoria)
                            <tr>
                                <td data-label="Nome" class="col-titulo">{{ $categoria->nome }}</td>
                                <td data-label="Tipo">
                                    @if($categoria->tipo === 'receita')
                                        <span class="badge badge--success">Receita</span>
                                    @else
                                        <span class="badge badge--warning">Despesa</span>
                                    @endif
                                </td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativa" :valor="(bool) ($categoria->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('categoria-financeira.edit', $categoria) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('categoria-financeira.destroy', $categoria) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
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
                                titulo="Nenhuma categoria financeira cadastrada"
                                texto="Categorias agrupam as contas a pagar e a receber nos relatórios."
                                :acao="route('categoria-financeira.create')"
                                acao-texto="+ Nova categoria"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
