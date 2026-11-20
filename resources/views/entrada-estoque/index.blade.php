@extends('layouts.app')

@section('title', 'Entradas de estoque')
@section('page_title', 'Entradas de estoque')

@section('page_actions')
    <a href="{{ route('entrada-estoque.create') }}" class="btn btn--primary btn--sm">+ Nova entrada</a>
    <button type="button" class="btn btn--ghost btn--sm" data-abrir-modal="modal-importar-xml">
        <x-icon name="upload" /> Importar XML
    </button>
@endsection

@section('content')

    <x-filtros :action="route('entrada-estoque.index')" :manter="['situacao']">
                <div class="form-group filtros__principal form-group--span-2">
                    <label class="form-label" for="campo-busca">Busca</label>
                    <input id="campo-busca" type="search" enterkeyhint="search" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou fornecedor">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_inicio">Data (início)</label>
                    <input id="campo-data_inicio" type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label" for="campo-data_fim">Data (fim)</label>
                    <input id="campo-data_fim" type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
    </x-filtros>

    <x-tabela-card titulo="Resultados" :paginator="$entradas">
        <x-slot:abas>
            <x-filtro-abas :opcoes="['rascunho' => 'Rascunhos', 'confirmada' => 'Confirmadas', 'cancelada' => 'Canceladas']" />
        </x-slot:abas>
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Fornecedor</th>
                            <th>Data</th>
                            <th class="col-num">Total</th>
                            <th>Situação</th>
                            <th class="col-actions">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entradas as $entrada)
                            <tr>
                                <td data-label="Número" class="text-muted">#{{ $entrada->numero }}</td>
                                <td data-label="Fornecedor">{{ $entrada->fornecedor->nome ?? '—' }}</td>
                                <td data-label="Data">{{ $entrada->data_entrada->format('d/m/Y') }}</td>
                                <td data-label="Total" class="col-num">R$ {{ number_format($entrada->total, 2, ',', '.') }}</td>
                                <td data-label="Situação">
                                    <x-badge-situacao tipo="entrada" :valor="$entrada->situacao" />
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <x-acoes>
                                        <x-slot:editar>
                                            @if($entrada->situacao === 'rascunho')
                                                <a href="{{ route('entrada-estoque.edit', $entrada) }}" class="btn btn--ghost btn--sm btn--icon" title="Editar" aria-label="Editar">
                                                    <x-icon name="edit" />
                                                </a>
                                            @endif
                                        </x-slot:editar>
                                        <x-slot:ver>
                                            <a href="{{ route('entrada-estoque.show', $entrada) }}" class="btn btn--ghost btn--sm btn--icon js-ver" data-no-loader title="Ver" aria-label="Ver">
                                                <x-icon name="eye" />
                                            </a>
                                        </x-slot:ver>
                                        <x-slot:excluir>
                                            <form method="POST" action="{{ route('entrada-estoque.destroy', $entrada) }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn--danger btn--sm btn--icon"
                                                    title="Excluir" aria-label="Excluir"
                                                    data-confirm="{{ $entrada->situacao === 'confirmada'
                                                        ? 'Excluir a compra #' . $entrada->numero . '? O estoque e a conta a pagar serão revertidos.'
                                                        : 'Confirmar exclusão da compra #' . $entrada->numero . '?' }}"
                                                    data-confirm-title="Excluir compra"
                                                    data-confirm-ok="Excluir"
                                                    data-confirm-variant="danger">
                                                    <x-icon name="trash" />
                                                </button>
                                            </form>
                                        </x-slot:excluir>
                                    </x-acoes>
                                </td>
                            </tr>
                        @empty
                            <x-tabela-vazia ilustracao="estoque"
                                :colspan="6"
                                titulo="Nenhuma entrada de estoque lançada"
                                texto="Lance as compras para dar entrada dos produtos no estoque."
                                :acao="route('entrada-estoque.create')"
                                acao-texto="+ Nova entrada"
                                filtravel
                            />
                        @endforelse
                    </tbody>
    </x-tabela-card>

    {{-- Modal de importação do XML. Volta aberto quando o servidor recusa o arquivo. --}}
    <div class="modal-overlay" id="modal-importar-xml" data-modal @unless ($errors->has('xml_file')) hidden @endunless>
        <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="modal-importar-xml-titulo">
            <div class="modal-box__header">
                <span class="card__title" id="modal-importar-xml-titulo">Importar XML de NF-e</span>
                <button type="button" class="btn btn--ghost btn--sm btn--icon" data-close-modal title="Fechar" aria-label="Fechar">
                    <x-icon name="x" />
                </button>
            </div>
            <form method="POST" action="{{ route('entrada-estoque.importar-xml') }}" enctype="multipart/form-data" id="form-importar-xml">
                @csrf
                <div class="modal-box__body">
                    <x-upload-arquivo name="xml_file" accept=".xml" ilustracao="xml" id="input-xml-file"
                        titulo="Arraste o arquivo XML aqui"
                        dica="XML da NF-e modelo 55 (até 2 MB). Os itens vêm para conferência antes de salvar a entrada." />
                </div>
                <div class="modal-box__footer">
                    <button type="button" class="btn btn--ghost" data-close-modal>Cancelar</button>
                    <button type="submit" class="btn btn--primary" id="btn-submit-xml" data-espera="Importando..." data-demora="Lendo o XML da nota.">Importar</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@pushOnce('styles', 'css-entrada-estoque')
    <link rel="stylesheet" href="{{ asset('css/entrada-estoque.css') }}?v={{ filemtime(public_path('css/entrada-estoque.css')) }}">
@endPushOnce
