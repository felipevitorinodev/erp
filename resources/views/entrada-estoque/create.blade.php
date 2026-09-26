@extends('layouts.app')

@section('title', 'Nova Entrada de Estoque')
@section('page_title', 'Nova Entrada de Estoque')

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de Estoque</a> / Nova
@endsection

@section('content')

    {{-- Card de importação XML ou alerta de dados importados --}}
    @if(empty($dadosXml))
        <div class="card mb-2">
            <div class="card__header">
                <span class="card__title">Importar dados de XML de NF-e</span>
            </div>
            <div class="card__body">
                <form method="POST" action="{{ route('entrada-estoque.importar-xml') }}" enctype="multipart/form-data" id="form-importar-xml-create">
                    @csrf
                    <div class="upload-area-inline" id="upload-area-create">
                        <div class="upload-area-inline__left">
                            <div class="upload-area-inline__icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                    <line x1="12" y1="18" x2="12" y2="12"></line>
                                    <line x1="9" y1="15" x2="12" y2="12"></line>
                                    <line x1="15" y1="15" x2="12" y2="12"></line>
                                </svg>
                            </div>
                            <div class="upload-area-inline__text">
                                <span class="upload-area-inline__title" id="upload-label-create">
                                    Clique ou arraste um arquivo XML
                                </span>
                                <span class="upload-area-inline__subtitle">NF-e modelo 55 · máx. 2MB</span>
                            </div>
                        </div>
                        <input type="file" name="xml_file" id="input-xml-create" class="upload-area-inline__input" accept=".xml">
                        <button type="submit" class="btn btn--primary btn--sm" id="btn-importar-create">Importar</button>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="alert alert--success mb-2">
            <div class="xml-imported-info">
                <div class="xml-imported-info__content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--color-success);flex-shrink:0;">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>
                        <strong>NF-e nº {{ $dadosXml['nota']['numero'] ?? '' }}</strong> —
                        {{ $dadosXml['emitente']['razao_social'] ?? '' }} importada.
                        Revise os itens antes de salvar.
                    </span>
                </div>
                <a href="{{ route('entrada-estoque.limpar-xml') }}" class="btn btn--ghost btn--sm"
                    data-confirm="Isso limpará os dados importados do XML.">
                    Limpar
                </a>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da Entrada</span>
        </div>

        <form method="POST" action="{{ route('entrada-estoque.store') }}">
            @csrf
            <div class="card__body">
                @include('entrada-estoque._form', ['dadosXml' => $dadosXml ?? null, 'observacoesXml' => $observacoesXml ?? null])
            </div>
            <div class="card__footer">
                <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection

@push('styles')
<style>
.upload-area-inline {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    border: 2px dashed var(--color-border);
    border-radius: 8px;
    background: var(--color-bg);
    position: relative;
    transition: all 0.2s ease;
}
.upload-area-inline:hover,
.upload-area-inline--drag {
    border-color: var(--color-primary);
    background: rgba(var(--color-primary-rgb, 37, 99, 235), 0.04);
}
.upload-area-inline__left {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex: 1;
    min-width: 0;
}
.upload-area-inline__icon {
    color: var(--color-text-muted);
    flex-shrink: 0;
}
.upload-area-inline:hover .upload-area-inline__icon,
.upload-area-inline--drag .upload-area-inline__icon {
    color: var(--color-primary);
}
.upload-area-inline__text {
    display: flex;
    flex-direction: column;
    gap: 0.125rem;
    min-width: 0;
}
.upload-area-inline__title {
    font-weight: 500;
    font-size: 0.9rem;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.upload-area-inline__title--file {
    color: var(--color-success);
}
.upload-area-inline__subtitle {
    font-size: 0.8rem;
    color: var(--color-text-muted);
}
.upload-area-inline__input {
    position: absolute;
    left: 0;
    top: 0;
    width: calc(100% - 100px);
    height: 100%;
    opacity: 0;
    cursor: pointer;
}
.xml-imported-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
}
.xml-imported-info__content {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
@media (max-width: 600px) {
    .upload-area-inline {
        flex-direction: column;
        text-align: center;
        padding: 1.25rem 1rem;
    }
    .upload-area-inline__left {
        flex-direction: column;
        gap: 0.5rem;
    }
    .upload-area-inline__input {
        width: 100%;
        height: calc(100% - 50px);
    }
    .upload-area-inline__title {
        white-space: normal;
    }
    .xml-imported-info {
        flex-direction: column;
        text-align: center;
    }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var uploadArea = document.getElementById('upload-area-create');
    var inputFile = document.getElementById('input-xml-create');
    var labelEl = document.getElementById('upload-label-create');

    if (!uploadArea || !inputFile || !labelEl) return;

    var originalLabel = labelEl.textContent;

    ['dragenter', 'dragover'].forEach(function (evt) {
        uploadArea.addEventListener(evt, function (e) {
            e.preventDefault();
            uploadArea.classList.add('upload-area-inline--drag');
        });
    });

    ['dragleave', 'drop'].forEach(function (evt) {
        uploadArea.addEventListener(evt, function (e) {
            e.preventDefault();
            uploadArea.classList.remove('upload-area-inline--drag');
        });
    });

    uploadArea.addEventListener('drop', function (e) {
        var files = e.dataTransfer.files;
        if (files.length > 0 && files[0].name.toLowerCase().endsWith('.xml')) {
            inputFile.files = files;
            showFileName(files[0].name);
        }
    });

    inputFile.addEventListener('change', function () {
        if (inputFile.files.length > 0) {
            showFileName(inputFile.files[0].name);
        } else {
            resetLabel();
        }
    });

    function showFileName(name) {
        labelEl.textContent = name;
        labelEl.classList.add('upload-area-inline__title--file');
    }

    function resetLabel() {
        labelEl.textContent = originalLabel;
        labelEl.classList.remove('upload-area-inline__title--file');
    }
})();
</script>
@endpush
