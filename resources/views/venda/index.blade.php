@extends('layouts.app')

@section('title', 'Vendas')
@section('page_title', 'Vendas')

@section('page_actions')
    <a href="{{ route('venda.create') }}" class="btn btn--primary btn--sm">+ Nova Venda</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Vendas</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th style="width:80px;">Número</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Forma Pgto.</th>
                            <th >Total</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendas as $venda)
                            <tr>
                                <td class="text-muted">#{{ $venda->numero }}</td>
                                <td>{{ $venda->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td>{{ $venda->data_venda->format('d/m/Y') }}</td>
                                <td>{{ $venda->forma_pagamento ?? '—' }}</td>
                                <td>R$ {{ number_format($venda->total, 2, ',', '.') }}</td>
                                <td>
                                    @if($venda->situacao === 'confirmada')
                                        <span class="badge badge--success">Confirmada</span>
                                    @elseif($venda->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Em andamento</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('venda.show', $venda) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($venda->situacao !== 'cancelada')
                                        <a href="{{ route('venda.edit', $venda) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                    <form method="POST" action="{{ route('venda.destroy', $venda) }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn--danger btn--sm"
                                            data-confirm="Confirmar exclusão?" data-confirm-title="Excluir" data-confirm-ok="Excluir" data-confirm-variant="danger">
                                            Excluir
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma venda cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
