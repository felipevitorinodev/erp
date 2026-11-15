@extends('layouts.app')

@section('title', 'Unidades de medida')
@section('page_title', 'Unidades de medida')

@section('page_actions')
    <a href="{{ route('unidadeMedida.create') }}" class="btn btn--primary btn--sm">+ Nova unidade</a>
@endsection

@section('content')

    <x-filtros :action="route('unidadeMedida.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome ou sigla">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$unidadesMedida">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Sigla</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($unidadesMedida as $unidade)
                            <tr>
                                <td data-label="Nome" class="col-titulo">{{ $unidade->nome }}</td>
                                <td data-label="Sigla">{{ $unidade->sigla }}</td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativa" :valor="(bool) ($unidade->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('unidadeMedida.edit', $unidade) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('unidadeMedida.destroy', $unidade) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir esta unidade?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
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
                                titulo="Nenhuma unidade cadastrada"
                                texto="Unidades como UN, KG e CX aparecem no cadastro de produtos e na nota."
                                :acao="route('unidadeMedida.create')"
                                acao-texto="+ Nova unidade"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection