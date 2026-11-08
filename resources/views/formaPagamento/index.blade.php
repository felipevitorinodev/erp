@extends('layouts.app')

@section('title', 'Formas de pagamento')
@section('page_title', 'Formas de pagamento')

@section('page_actions')
    <a href="{{ route('formaPagamento.create') }}" class="btn btn--primary btn--sm">+ Nova forma de pagamento</a>
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
            <span class="card__title">Resultados</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Gera conta a receber</th>
                            <th>Vencimento</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($formasPagamento as $forma)
                            <tr>
                                <td data-label="Nome">{{ $forma->nome }}</td>
                                <td data-label="Tipo">
                                    @if($forma->tipo === 'prazo')
                                        <span class="badge badge--warning">A prazo</span>
                                    @else
                                        <span class="badge badge--info">À vista</span>
                                    @endif
                                </td>
                                <td data-label="Gera conta a receber">{{ $forma->gera_conta_receber ? 'Sim' : 'Não' }}</td>
                                <td data-label="Vencimento">
                                    @if($forma->gera_conta_receber)
                                        {{ $forma->dias_vencimento }} dia(s)
                                    @else
                                        —
                                    @endif
                                </td>
                                <td data-label="Situação">
                                    @if($forma->ativo)
                                        <span class="badge badge--success">Ativa</span>
                                    @else
                                        <span class="badge badge--neutral">Inativa</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            <a href="{{ route('formaPagamento.edit', $forma) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                <x-icon name="edit" />
                                            </a>
                                        </x-slot:editar>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('formaPagamento.destroy', $forma) }}" class="d-inline">
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
                            <x-tabela-vazia ilustracao="carteira"
                                :colspan="6"
                                titulo="Nenhuma forma de pagamento cadastrada"
                                texto="Cadastre as formas aceitas, como dinheiro, Pix e cartão, para finalizar as vendas."
                                :acao="route('formaPagamento.create')"
                                acao-texto="+ Nova forma de pagamento"
                                filtravel
                            />
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
