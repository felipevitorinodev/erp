@extends('layouts.app')

@section('title', 'Contas a Receber')
@section('page_title', 'Contas a Receber')

@section('page_actions')
    <a href="{{ route('conta-receber.create') }}" class="btn btn--primary btn--sm">+ Nova Conta</a>
@endsection

@section('content')

    <x-filtros :action="route('conta-receber.index')">
        <x-filtro-campo
            label="Busca"
            name="busca"
            :value="$filtros['busca'] ?? ''"
            placeholder="Descrição ou cliente"
            :span="2"
        />
        <x-filtro-campo
            label="Situação"
            name="situacao"
            type="select"
            :value="$filtros['situacao'] ?? ''"
            :options="['aberta' => 'Aberta', 'parcial' => 'Parcial', 'paga' => 'Paga', 'cancelada' => 'Cancelada']"
        />
        <x-filtro-campo
            label="Vencimento"
            name="data_vencimento"
            type="date"
            :value="$filtros['data_vencimento'] ?? ''"
        />
    </x-filtros>

    <x-tabela-card titulo="Listagem" :paginator="$contas">
                    <thead>
                        <tr>
                            <th style="width:60px;">Nº</th>
                            <th>Descrição</th>
                            <th>Cliente</th>
                            <th>Vencimento</th>
                            <th class="col-num">Valor</th>
                            <th class="col-num">Pago</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($contas as $conta)
                            @php
                                $vencido = $conta->data_vencimento->lt(now()->startOfDay())
                                    && !in_array($conta->situacao, ['paga', 'cancelada'], true);
                            @endphp
                            <tr>
                                <td data-label="Nº" class="text-muted">{{ $conta->id }}</td>
                                <td data-label="Descrição">{{ $conta->descricao }}</td>
                                <td data-label="Cliente">{{ $conta->cliente->nome ?? '—' }}</td>
                                <td data-label="Vencimento" class="{{ $vencido ? 'text--danger' : '' }}">
                                    {{ $conta->data_vencimento->format('d/m/Y') }}
                                </td>
                                <td data-label="Valor" class="col-num"><x-moeda :valor="$conta->valor" /></td>
                                <td data-label="Pago" class="col-num"><x-moeda :valor="$conta->valor_pago" /></td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="conta" :valor="$conta->situacao" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            @if(!in_array($conta->situacao, ['paga', 'cancelada'], true))
                                                <a href="{{ route('conta-receber.edit', $conta) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                    <x-icon name="edit" />
                                                </a>
                                            @endif
                                        </x-slot:editar>
                                        <x-slot:ver>
                                            <a href="{{ route('conta-receber.show', $conta) }}" class="btn btn--ghost btn--sm btn--icon js-ver" data-no-loader title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('conta-receber.destroy', $conta) }}" class="d-inline">
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
                            <x-tabela-vazia :colspan="8">Nenhuma conta a receber cadastrada.</x-tabela-vazia>
                        @endforelse
                    </tbody>
    </x-tabela-card>

@endsection
