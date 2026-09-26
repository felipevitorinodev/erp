@extends('layouts.app')

@section('title', 'Entradas de Estoque')
@section('page_title', 'Entradas de Estoque')

@section('page_actions')
    <a href="{{ route('entrada-estoque.create') }}" class="btn btn--primary btn--sm">+ Nova Entrada</a>
    <button type="button" class="btn btn--ghost btn--sm" id="btn-abrir-modal-xml">Importar XML</button>
@endsection

@section('content')

    <div class="card mb-2">
        <div class="card__body">
            <form method="GET" action="{{ route('entrada-estoque.index') }}" class="form-grid form-grid--col-3">
                <div class="form-group form-group--span-2">
                    <label class="form-label">Busca</label>
                    <input type="text" name="busca" class="form-control" value="{{ $filtros['busca'] ?? '' }}"
                        placeholder="Número ou fornecedor">
                </div>
                <div class="form-group">
                    <label class="form-label">Situação</label>
                    <select name="situacao" class="form-control">
                        <option value="">— Todas —</option>
                        @foreach(['rascunho' => 'Rascunho', 'confirmada' => 'Confirmada', 'cancelada' => 'Cancelada'] as $valor => $label)
                            <option value="{{ $valor }}" {{ ($filtros['situacao'] ?? '') === $valor ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Data (início)</label>
                    <input type="date" name="data_inicio" class="form-control"
                        value="{{ $filtros['data_inicio'] ?? '' }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Data (fim)</label>
                    <input type="date" name="data_fim" class="form-control"
                        value="{{ $filtros['data_fim'] ?? '' }}">
                </div>
                <div class="form-group">
                    <div class="filter-actions">
                        <button type="submit" class="btn btn--primary btn--sm">Filtrar</button>
                        <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost btn--sm">Limpar</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card__header">
            <span class="card__title">Listagem</span>
        </div>
        <div class="card__body" style="padding: 0;">
            <div class="table-wrap">
                <table class="table table--cards">
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
                                    @if($entrada->situacao === 'confirmada')
                                        <span class="badge badge--success">Confirmada</span>
                                    @elseif($entrada->situacao === 'cancelada')
                                        <span class="badge badge--error">Cancelada</span>
                                    @else
                                        <span class="badge badge--warning">Rascunho</span>
                                    @endif
                                </td>
                                <td class="col-actions" data-label="Ações">
                                    <a href="{{ route('entrada-estoque.show', $entrada) }}" class="btn btn--ghost btn--sm">Ver</a>
                                    @if($entrada->situacao === 'rascunho')
                                        <a href="{{ route('entrada-estoque.edit', $entrada) }}" class="btn btn--ghost btn--sm">Editar</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted" style="padding:2rem;">
                                    Nenhuma entrada de estoque cadastrada.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card__footer">
            {{ $entradas->withQueryString()->links() }}
        </div>
    </div>

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
                    <div class="upload-area" id="upload-area-xml">
                        <div class="upload-area__icon">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                <polyline points="14 2 14 8 20 8"></polyline>
                                <line x1="12" y1="18" x2="12" y2="12"></line>
                                <line x1="9" y1="15" x2="12" y2="12"></line>
                                <line x1="15" y1="15" x2="12" y2="12"></line>
                            </svg>
                        </div>
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
                    <button type="submit" class="btn btn--primary" id="btn-submit-xml">Importar</button>
                </div>
            </form>
        </div>
    </div>

@endsection

@push('styles')
<style>
.upload-area {
    position: relative;
    border: 2px dashed var(--color-border);
    border-radius: 8px;
    padding: 2rem 1rem;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s ease;
    background: var(--color-bg);
}
.upload-area:hover,
.upload-area--drag {
    border-color: var(--color-primary);
    background: rgba(var(--color-primary-rgb, 37, 99, 235), 0.04);
}
.upload-area__icon {
    color: var(--color-text-muted);
    margin-bottom: 0.75rem;
}
.upload-area--drag .upload-area__icon,
.upload-area:hover .upload-area__icon {
    color: var(--color-primary);
}
.upload-area__text {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.upload-area__title {
    font-weight: 500;
    color: var(--color-text);
    font-size: 0.95rem;
}
.upload-area__subtitle {
    font-size: 0.85rem;
    color: var(--color-text-muted);
}
.upload-area__input {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
.upload-area__selected {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding: 0.5rem 0.75rem;
    background: var(--color-success-bg, #ecfdf5);
    border-radius: 6px;
    font-size: 0.85rem;
}
.upload-area__filename {
    color: var(--color-success, #059669);
    font-weight: 500;
    word-break: break-all;
}
.upload-area__remove {
    background: none;
    border: none;
    color: var(--color-text-muted);
    font-size: 1.25rem;
    line-height: 1;
    cursor: pointer;
    padding: 0 0.25rem;
}
.upload-area__remove:hover {
    color: var(--color-danger);
}
.upload-area--has-file .upload-area__icon,
.upload-area--has-file .upload-area__text {
    display: none;
}
@media (max-width: 600px) {
    .upload-area {
        padding: 1.5rem 1rem;
    }
    .upload-area__icon svg {
        width: 40px;
        height: 40px;
    }
    .upload-area__title {
        font-size: 0.9rem;
    }
}
</style>
@endpush

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
