@extends('layouts.app')

@section('title', 'Clientes')
@section('page_title', 'Clientes')

@section('page_actions')
    <a href="{{ route('cliente.create') }}" class="btn btn--primary btn--sm">+ Novo cliente</a>
@endsection

@section('content')

    <x-filtros :action="route('cliente.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, CPF ou CNPJ">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$clientes">
                    <thead>
                        <tr>
                            <th style="width:60px;">Código</th>
                            <th>Nome</th>
                            <th>Nome fantasia</th>
                            <th>CPF / CNPJ</th>
                            <th>Telefone</th>
                            <th>Cidade / UF</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clientes as $cliente)
                            <tr>
                                <td data-label="Código" class="text-muted">{{ $cliente->id }}</td>
                                <td data-label="Nome" class="col-titulo">{{ $cliente->nome }}</td>
                                <td data-label="Nome fantasia">{{ $cliente->nome_fantasia ?? '—' }}</td>
                                <td data-label="CPF / CNPJ">{{ $cliente->cpf ?: ($cliente->cnpj ?: '—') }}</td>
                                <td data-label="Telefone">{{ $cliente->telefone ?? $cliente->celular ?? '—' }}</td>
                                <td data-label="Cidade / UF">
                                    @if($cliente->cidade)
                                        {{ $cliente->cidade }}{{ $cliente->estado ? '/' . $cliente->estado : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('cliente.edit', $cliente) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('cliente.destroy', $cliente) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este cliente?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="pessoas"
                                :colspan="7"
                                titulo="Nenhum cliente cadastrado"
                                texto="Cadastre os clientes para usar em vendas, orçamentos e notas fiscais."
                                :acao="route('cliente.create')"
                                acao-texto="+ Novo cliente"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection