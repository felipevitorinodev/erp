@extends('layouts.app')

@section('title', 'Entradas de estoque')
@section('page_title', 'Entradas de estoque')

@section('page_actions')
    <a href="{{ route('entrada-estoque.create') }}" class="btn btn--primary btn--sm">+ Nova entrada</a>
    <button type="button" class="btn btn--ghost btn--sm" id="btn-abrir-modal-xml">Importar XML</button>
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

    {{-- Modal de Importação XML --}}
    <div class="modal-overlay" id="modal-importar-xml" hidden>
        <div class="modal-box">
            <div class="modal-box__header">
                <span class="card__title">Importar XML de NF-e</span>
                <button type="button" class="btn btn--ghost btn--sm" data-close-modal>&times;</button>
            </div>
            <form method="POST" action="{{ route('entrada-estoque.importar-xml') }}" enctype="multipart/form-data" id="form-importar-xml">
                @csrf
                <div class="modal-box__body">
                    <div class="upload-area" id="upload-area-xml" data-ilustracao-area>
                        <x-ilustracao nome="xml" tamanho="sm" interativa class="upload-area__icon" />
                        <div class="upload-area__text">
                            <span class="upload-area__title">Arraste o arquivo XML aqui</span>
                            <span class="upload-area__subtitle">ou clique para selecionar</span>
                        </div>
                        <input type="file" name="xml_file" id="input-xml-file" class="upload-area__input" accept=".xml" required>
                        <div class="upload-area__selected" id="xml-file-name" hidden>
                            <span class="upload-area__filename"></span>
                            <button type="button" class="upload-area__remove" title="Remover">&times;</button>
                        </div>
                    </div>
                    <p class="text-muted text-center" style="font-size:0.8rem;margin-top:0.75rem;">
                        Arquivo XML da NF-e modelo 55 (máx. 2MB)
                    </p>
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

@push('scripts')
<script>
(function () {
    var btnAbrir = document.getElementById('btn-abrir-modal-xml');
    var modal = document.getElementById('modal-importar-xml');
    var uploadArea = document.getElementById('upload-area-xml');
    var inputFile = document.getElementById('input-xml-file');
    var fileNameEl = document.getElementById('xml-file-name');

    if (!btnAbrir || !modal) return;

    // Abrir/fechar modal
    btnAbrir.addEventListener('click', function () {
        modal.hidden = false;
    });

    modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
        el.addEventListener('click', function () {
            modal.hidden = true;
            resetUpload();
        });
    });

    modal.addEventListener('click', function (e) {
        if (e.target === modal) {
            modal.hidden = true;
            resetUpload();
        }
    });

    // Upload area - drag and drop
    if (uploadArea && inputFile) {
        ['dragenter', 'dragover'].forEach(function (evt) {
            uploadArea.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadArea.classList.add('upload-area--drag');
            });
        });

        ['dragleave', 'drop'].forEach(function (evt) {
            uploadArea.addEventListener(evt, function (e) {
                e.preventDefault();
                uploadArea.classList.remove('upload-area--drag');
            });
        });

        uploadArea.addEventListener('drop', function (e) {
            var files = e.dataTransfer.files;
            if (files.length > 0) {
                inputFile.files = files;
                showFileName(files[0].name);
            }
        });

        inputFile.addEventListener('change', function () {
            if (inputFile.files.length > 0) {
                showFileName(inputFile.files[0].name);
            } else {
                resetUpload();
            }
        });

        if (fileNameEl) {
            var removeBtn = fileNameEl.querySelector('.upload-area__remove');
            if (removeBtn) {
                removeBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    inputFile.value = '';
                    resetUpload();
                });
            }
        }
    }

    function showFileName(name) {
        if (!fileNameEl) return;
        fileNameEl.hidden = false;
        fileNameEl.querySelector('.upload-area__filename').textContent = name;
        uploadArea.classList.add('upload-area--has-file');
    }

    function resetUpload() {
        if (!fileNameEl) return;
        fileNameEl.hidden = true;
        uploadArea.classList.remove('upload-area--has-file');
        if (inputFile) inputFile.value = '';
    }
})();
</script>
@endpush
