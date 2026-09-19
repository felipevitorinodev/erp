@extends('layouts.app')

@section('title', 'Orçamentos')
@section('page_title', 'Orçamentos')

@section('page_actions')
    <a href="{{ route('orcamento.create') }}" class="btn btn--primary btn--sm">+ Novo Orçamento</a>
@endsection

@section('content')

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Orçamentos</span>
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
                            <th>Total</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orcamentos as $orcamento)
                            <tr>
                                <td class="text-muted">#{{ $orcamento->numero }}</td>
                                <td>{{ $orcamento->cliente->nome ?? '— Consumidor Final —' }}</td>
                                <td>{{ $orcamento->data_orcamento->format('d/m/Y') }}</td>
                                <td>{{ $orcamento->forma_pagamento ?? '—' }}</td>
                                <td>R$ {{ number_format($orcamento->total, 2, ',', '.') }}</td>
                                <td>
                                    @if($orcamento->situacao === 'aprovado')
                                        <span class="badge badge--success">Aprovado</span>
                                    @elseif($orcamento->situacao === 'recusado')
                                        <span class="badge badge--error">Recusado</span>
                                    @elseif($orcamento->situacao === 'cancelado')
                                        <span class="badge badge--error">Cancelado</span>
                                    @else
                                        <span class="badge badge--warning">Pendente</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('orcamento.show', $orcamento) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($orcamento->situacao === 'pendente')
                                        <a href="{{ route('orcamento.edit', $orcamento) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                    <form method="POST" action="{{ route('orcamento.destroy', $orcamento) }}" style="display:inline;">
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
                                    Nenhum orçamento cadastrado.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection
