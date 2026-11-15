@extends('layouts.app')

@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')

@section('page_actions')
    <a href="{{ route('fornecedor.create') }}" class="btn btn--primary btn--sm">+ Novo fornecedor</a>
@endsection

@section('content')

    <x-filtros :action="route('fornecedor.index')">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome, CPF ou CNPJ">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$fornecedores">
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
                        @forelse($fornecedores as $fornecedor)
                            <tr>
                                <td data-label="Código" class="text-muted">{{ $fornecedor->id }}</td>
                                <td data-label="Nome" class="col-titulo">{{ $fornecedor->nome }}</td>
                                <td data-label="Nome fantasia">{{ $fornecedor->nome_fantasia ?? '—' }}</td>
                                <td data-label="CPF / CNPJ">{{ $fornecedor->cpf ?: ($fornecedor->cnpj ?: '—') }}</td>
                                <td data-label="Telefone">{{ $fornecedor->telefone ?? $fornecedor->celular ?? '—' }}</td>
                                <td data-label="Cidade / UF">
                                    @if($fornecedor->cidade)
                                        {{ $fornecedor->cidade }}{{ $fornecedor->estado ? '/' . $fornecedor->estado : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('fornecedor.edit', $fornecedor) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('fornecedor.destroy', $fornecedor) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="Excluir este fornecedor?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="caminhao"
                                :colspan="7"
                                titulo="Nenhum fornecedor cadastrado"
                                texto="Cadastre os fornecedores para lançar as entradas de estoque e as contas a pagar."
                                :acao="route('fornecedor.create')"
                                acao-texto="+ Novo fornecedor"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection