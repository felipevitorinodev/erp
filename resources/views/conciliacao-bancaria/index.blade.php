@extends('layouts.app')

@section('title', 'Conciliação bancária')
@section('page_title', 'Conciliação bancária')

@section('page_actions')
    <button type="button" class="btn btn--primary btn--sm" data-abrir-modal="modal-importar-ofx">
        <x-icon name="upload" /> Importar OFX
    </button>
@endsection

@section('content')
    <x-tabela-card titulo="Extratos importados" :paginator="$extratos">
        <thead>
            <tr>
                <th class="col-titulo">Arquivo</th>
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
                    <td data-label="Arquivo" class="col-titulo">{{ $extrato->arquivo_nome }}</td>
                    <td data-label="Conta bancária">{{ $extrato->conta_bancaria ?: '—' }}</td>
                    <td data-label="Período">
                        {{ $extrato->data_inicio?->format('d/m/Y') ?? '—' }} a {{ $extrato->data_fim?->format('d/m/Y') ?? '—' }}
                    </td>
                    <td data-label="Transações" class="col-num">{{ $extrato->transacoes_count }}</td>
                    <td data-label="Importado em">{{ $extrato->created_at->format('d/m/Y H:i') }}</td>
                    <td class="col-actions" data-label="Ações">
                        <a href="{{ route('conciliacao-bancaria.show', $extrato) }}" class="btn btn--ghost btn--sm btn--icon js-ver"
                            data-no-loader title="Revisar transações" aria-label="Revisar transações">
                            <x-icon name="eye" />
                        </a>
                    </td>
                </tr>
            @empty
                <x-tabela-vazia ilustracao="extrato" :colspan="6" titulo="Nenhum extrato importado"
                    texto="Use “Importar OFX” para trazer o extrato do banco e conciliar as transações." />
            @endforelse
        </tbody>
    </x-tabela-card>

    {{-- Volta aberto quando o servidor recusa o arquivo, com o motivo embaixo da área. --}}
    <div class="modal-overlay" id="modal-importar-ofx" data-modal @unless ($errors->has('arquivo')) hidden @endunless>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-importar-ofx-titulo">
            <div class="modal-box__header">
                <span class="card__title" id="modal-importar-ofx-titulo">Importar extrato OFX</span>
                <button type="button" class="btn btn--ghost btn--sm btn--icon" data-close-modal title="Fechar" aria-label="Fechar">
                    <x-icon name="x" />
                </button>
            </div>
            <form method="POST" action="{{ route('conciliacao-bancaria.importar') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-box__body">
                    <x-upload-arquivo name="arquivo" accept=".ofx" ilustracao="extrato" id="arquivo"
                        titulo="Arraste o arquivo OFX aqui"
                        dica="Exporte o extrato no internet banking (OFX, até 5 MB). Nada é lançado no financeiro ao importar: você confere e concilia cada transação." />
                </div>
                <div class="modal-box__footer">
                    <button type="button" class="btn btn--ghost" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn btn--primary" data-espera="Importando..." data-demora="Lendo as transações do extrato.">Importar</button>
                </div>
            </form>
        </div>
    </div>
@endsection
