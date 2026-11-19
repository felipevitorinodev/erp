@extends('layouts.app')

@section('title', 'Cobranças')
@section('page_title', 'Cobranças')

@section('content')
    <div class="card">
        <div class="card__body">
            Contas vencidas sem próximo lembrete e lembretes programados para hoje ou antes aparecem nesta lista. O registro de contato é manual; nenhuma mensagem é enviada automaticamente.
        </div>
    </div>

    <x-tabela-card titulo="Contas que precisam de acompanhamento" :paginator="$contas">
        <thead>
            <tr>
                <th>Conta</th>
                <th>Cliente</th>
                <th>Vencimento</th>
                <th>Próximo contato</th>
                <th class="col-num">Saldo</th>
                <th class="col-actions">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($contas as $conta)
                <tr>
                    <td data-label="Conta">#{{ $conta->id }} — {{ $conta->descricao }}</td>
                    <td data-label="Cliente">{{ $conta->cliente->nome ?? '—' }}</td>
                    <td data-label="Vencimento">{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                    <td data-label="Próximo contato">{{ $conta->proxima_cobranca_em?->format('d/m/Y') ?? 'Imediato' }}</td>
                    <td data-label="Saldo" class="col-num"><x-moeda :valor="$conta->valor - $conta->valor_pago" /></td>
                    <td data-label="Ações" class="col-actions">
                        <a href="{{ route('conta-receber.show', $conta) }}" class="btn btn--ghost btn--sm">Registrar contato</a>
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="carteira" :colspan="6" titulo="Nenhuma cobrança pendente de acompanhamento" texto="Contas vencidas e lembretes vencidos serão listados aqui." />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection
