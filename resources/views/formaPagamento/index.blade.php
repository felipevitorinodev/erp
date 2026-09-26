@extends('layouts.app')

@section('title', 'Formas de Pagamento')
@section('page_title', 'Formas de Pagamento')

@section('page_actions')
    <a href="{{ route('formaPagamento.create') }}" class="btn btn--primary btn--sm">+ Nova Forma</a>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('formaPagamento.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Nome da forma de pagamento">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('formaPagamento.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem de Formas de Pagamento</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Gera Conta a Receber</th>
                            <th>Vencimento</th>
                            <th>Situação</th>
                            <th class="text-right" style="width:1%; white-space:nowrap;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($formasPagamento as $forma)
                            <tr>
                                <td>{{ $forma->nome }}</td>
                                <td>
                                    @if($forma->tipo === 'prazo')
                                        <span class="badge badge--warning">A prazo</span>
                                    @else
                                        <span class="badge badge--info">À vista</span>
                                    @endif
                                </td>
                                <td>{{ $forma->gera_conta_receber ? 'Sim' : 'Não' }}</td>
                                <td>
                                    @if($forma->gera_conta_receber)
                                        {{ $forma->dias_vencimento }} dia(s)
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>
                                    @if($forma->ativo)
                                        <span class="badge badge--success">Ativa</span>
                                    @else
                                        <span class="badge badge--neutral">Inativa</span>
                                    @endif
                                </td>
                                <td class="text-right" style="white-space:nowrap;">
                                    <a href="{{ route('formaPagamento.edit', $forma) }}"
                                        class="btn btn--ghost btn--sm">Editar</a>
                                    <form method="POST" action="{{ route('formaPagamento.destroy', $forma) }}"
                                        class="d-inline">
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
                                <td colspan="6" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma forma de pagamento cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $formasPagamento->withQueryString()->links() }}
        </div>
    </div>

@endsection
