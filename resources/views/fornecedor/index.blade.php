@extends('layouts.app')

@section('title', 'Fornecedores')
@section('page_title', 'Fornecedores')

@section('page_actions')
    <a href="{{ route('fornecedor.create') }}" class="btn btn--primary btn--sm">+ Novo Fornecedor</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Fornecedores</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width:60px;">Código</th>
                            <th>Nome</th>
                            <th>Nome Fantasia</th>
                            <th>CPF / CNPJ</th>
                            <th>Telefone</th>
                            <th>Cidade / UF</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fornecedores as $fornecedor)
                            <tr>
                                <td class="text-muted">{{ $fornecedor->id }}</td>
                                <td>{{ $fornecedor->nome }}</td>
                                <td>{{ $fornecedor->nome_fantasia ?? '—' }}</td>
                                <td>{{ $fornecedor->cpf ?: ($fornecedor->cnpj ?: '—') }}</td>
                                <td>{{ $fornecedor->telefone ?? $fornecedor->celular ?? '—' }}</td>
                                <td>
                                    @if($fornecedor->cidade)
                                        {{ $fornecedor->cidade }}{{ $fornecedor->estado ? '/' . $fornecedor->estado : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('fornecedor.edit', $fornecedor) }}"
                                        class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('fornecedor.destroy', $fornecedor) }}"
                                        style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            onclick="return confirm('Excluir este fornecedor?')">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhum fornecedor cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection