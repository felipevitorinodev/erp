@extends('layouts.app')

@section('title', 'Conciliação bancária')
@section('page_title', 'Conciliação bancária')

@section('content')
    <div class="card">
        <div class="card__header"><span class="card__title">Importar extrato OFX</span></div>
        <div class="card__body">
        <form method="POST" action="{{ route('conciliacao-bancaria.importar') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label form-label--required" for="arquivo">Arquivo OFX</label>
                <input id="arquivo" type="file" name="arquivo" class="form-control" accept=".ofx" required>
                <span class="form-hint">O arquivo é processado para importar as transações. Nenhuma despesa ou receita é criada automaticamente.</span>
                @error('arquivo') <span class="form-error">{{ $message }}</span> @enderror
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn--primary">Importar extrato</button>
            </div>
        </form>
        </div>
    </div>

    <x-tabela-card titulo="Extratos importados" :paginator="$extratos">
        <thead>
            <tr>
                <th>Arquivo</th>
                <th>Conta bancária</th>
                <th>Período</th>
                <th class="col-num">Transações</th>
                <th>Importado em</th>
                <th class="col-actions">Ações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($extratos as $extrato)
                <tr>
                    <td data-label="Arquivo">{{ $extrato->arquivo_nome }}</td>
                    <td data-label="Conta bancária">{{ $extrato->conta_bancaria ?: '—' }}</td>
                    <td data-label="Período">
                        {{ $extrato->data_inicio?->format('d/m/Y') ?? '—' }} a {{ $extrato->data_fim?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td data-label="Transações" class="col-num">{{ $extrato->transacoes_count }}</td>
                    <td data-label="Importado em">{{ $extrato->created_at->format('d/m/Y H:i') }}</td>
                    <td class="col-actions" data-label="Ações">
                        <a href="{{ route('conciliacao-bancaria.show', $extrato) }}" class="btn btn--ghost btn--sm">Revisar</a>
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="carteira" :colspan="6" titulo="Nenhum extrato importado" texto="Importe um arquivo OFX para revisar e conciliar as transações." />
            @endforelse
        </tbody>
    </x-tabela-card>
@endsection
