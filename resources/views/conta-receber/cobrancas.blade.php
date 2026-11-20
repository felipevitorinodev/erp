@extends('layouts.app')

@section('title', 'Cobranças')
@section('page_title', 'Cobranças')

@section('content')
    <x-tabela-card titulo="Contas que precisam de acompanhamento" :paginator="$contas">
        <x-slot:acoes>
            <small class="text-muted">Vencidas sem lembrete e lembretes para hoje ou antes. Nada é enviado automaticamente.</small>
        </x-slot:acoes>
        <thead>
            <tr>
                <th class="col-titulo">Conta</th>
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
                    <td data-label="Conta" class="col-titulo">#{{ $conta->id }} — {{ $conta->descricao }}</td>
                    <td data-label="Cliente">{{ $conta->cliente->nome ?? '—' }}</td>
                    <td data-label="Vencimento" @class(['text--danger' => $conta->data_vencimento->lt(today())])>{{ $conta->data_vencimento->format('d/m/Y') }}</td>
                    <td data-label="Próximo contato">{{ $conta->proxima_cobranca_em?->format('d/m/Y') ?? 'Imediato' }}</td>
                    <td data-label="Saldo" class="col-num"><x-moeda :valor="$conta->valor - $conta->valor_pago" /></td>
                    <td data-label="Ações" class="col-actions">
                        <a href="{{ route('conta-receber.show', $conta) }}#registrar-cobranca" class="btn btn--ghost btn--sm js-ver" data-no-loader>Registrar contato</a>
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="carteira" :colspan="6" titulo="Nenhuma cobrança pendente de acompanhamento" texto="Contas vencidas e lembretes vencidos serão listados aqui." />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection
