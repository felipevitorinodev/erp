@extends('layouts.app')

@section('title', 'Nova entrada de estoque')
@section('page_title', 'Nova entrada de estoque')

@section('breadcrumb')
    <a href="{{ route('entrada-estoque.index') }}">Entradas de estoque</a> / Nova
@endsection

@section('content')

    @if($pedidoCompra ?? false)
        <div class="alert alert--warning mb-2">
            Recebimento do pedido <a href="{{ route('pedido-compra.show', $pedidoCompra) }}"><strong>{{ $pedidoCompra->numero }}</strong></a>.
            Ajuste as quantidades recebidas; o pedido e o estoque serão atualizados ao confirmar a entrada.
        </div>
    @endif

    {{-- Card de importação XML ou alerta de dados importados --}}
    @if(empty($dadosXml))
        <div class="card mb-2">
            <div class="card__header">
                <span class="card__title">Importar dados de XML de NF-e</span>
            </div>
            <div class="card__body">
                <form method="POST" action="{{ route('entrada-estoque.importar-xml') }}" enctype="multipart/form-data" id="form-importar-xml-create">
                    @csrf
                    @if($pedidoCompra ?? false)
                        <input type="hidden" name="pedido_compra_id" value="{{ $pedidoCompra->id }}">
                    @endif
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
                <a href="{{ route('entrada-estoque.limpar-xml', ['pedido_compra_id' => $pedidoCompra->id ?? null]) }}" class="btn btn--ghost btn--sm"
                    data-confirm="Isso limpará os dados importados do XML.">
                    Limpar
                </a>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card__header">
            <span class="card__title">Dados da entrada</span>
        </div>

        <form method="POST" action="{{ route('entrada-estoque.store') }}">
            @csrf
            <div class="card__body">
                @include('entrada-estoque._form', [
                    'dadosXml' => $dadosXml ?? null,
                    'observacoesXml' => $observacoesXml ?? null,
                    'pedidoCompra' => $pedidoCompra ?? null,
                    'itensCompra' => $itensCompra ?? collect(),
                ])
            </div>
            <div class="card__footer">
                <a href="{{ route('entrada-estoque.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary">Salvar</button>
            </div>
        </form>
    </div>

@endsection

@pushOnce('styles', 'css-entrada-estoque')
    <link rel="stylesheet" href="{{ asset('css/entrada-estoque.css') }}?v={{ filemtime(public_path('css/entrada-estoque.css')) }}">
@endPushOnce

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
