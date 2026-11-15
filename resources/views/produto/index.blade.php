@extends('layouts.app')

@section('title', 'Produtos')
@section('page_title', 'Produtos')

@section('page_actions')
    <a href="{{ route('produto.create') }}" class="btn btn--primary btn--sm">+ Novo produto</a>
@endsection

@section('content')

    <x-filtros :action="route('produto.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, código ou código de barras">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-tipo">Tipo</label>
                    <select id="campo-tipo" name="tipo" class="form-control">
                        <option value="">— Todos —</option>
                        <option value="1" {{ (string) ($filtros['tipo'] ?? '') === '1' ? 'selected' : '' }}>Produto</option>
                        <option value="2" {{ (string) ($filtros['tipo'] ?? '') === '2' ? 'selected' : '' }}>Serviço</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-grupo_id">Grupo</label>
                    <select id="campo-grupo_id" name="grupo_id" class="form-control">
                        <option value="">— Todos —</option>
                        @foreach($grupos as $grupo)
                            <option value="{{ $grupo->id }}"
                                {{ (string) ($filtros['grupo_id'] ?? '') === (string) $grupo->id ? 'selected' : '' }}>
                                {{ $grupo->nome }}
                            </option>
                        @endforeach
                    </select>
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$produtos">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nome</th>
                            <th>Grupo</th>
                            <th class="col-num">Preço de venda</th>
                            <th class="col-num">Estoque</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($produtos as $produto)
                            <tr>
                                <td data-label="Código">{{ $produto->codigo ?: '—' }}</td>
                                <td data-label="Nome" class="col-titulo">{{ $produto->nome }}</td>
                                <td data-label="Grupo">{{ $produto->grupos->nome ?? '—' }}</td>
                                <td data-label="Preço de venda" class="col-num">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                                @php($estoqueBaixo = $produto->ativo && $produto->controla_estoque && (float) $produto->estoque_atual <= (float) $produto->estoque_minimo)
                                <td data-label="Estoque" class="col-num {{ $estoqueBaixo ? 'text--danger' : '' }}">{{ number_format((float) $produto->estoque_atual, 2, ',', '.') }}
                                    {{ $produto->unidadeMedida->sigla ?? '' }}
                                    @if($estoqueBaixo)
                                        <span class="aviso-linha">estoque baixo</span>
                                    @endif
                                </td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="ativo" :valor="(bool) ($produto->ativo)" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('produto.edit', $produto) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('produto.destroy', $produto) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este produto?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="prateleira"
                                :colspan="7"
                                titulo="Nenhum produto cadastrado"
                                texto="Cadastre produtos e serviços para vender no PDV e emitir notas."
                                :acao="route('produto.create')"
                                acao-texto="+ Novo produto"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection