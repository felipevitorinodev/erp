@extends('layouts.app')

@php
    $errosItens = collect($errors->getMessages())
        ->filter(fn ($mensagens, $campo) => str_starts_with((string) $campo, 'itens.'))
        ->flatten()
        ->unique()
        ->values();
    $exigeDocumentoPb = strtoupper((string) $empresa->uf) === 'PB';
@endphp

@section('title', 'Nova NFC-e')
@section('page_title', 'Nova NFC-e')

@section('breadcrumb')
    <a href="{{ route('nfce.index') }}">NFC-e</a> / Nova
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('nfce.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/pagamento-modal.css') }}?v={{ filemtime(public_path('css/pagamento-modal.css')) }}">
@endpush

@section('content')

    @if ($ambiente !== 1)
        <div class="alert alert--warning">
            Emissão em <strong>homologação</strong>: a NFC-e sai sem valor fiscal.
            O ambiente é o mesmo da NF-e: troque em Empresa → Configurações NF-e quando os testes terminarem.
        </div>
    @endif

    @unless ($configurada)
        <div class="alert alert--error">
            <strong>A NFC-e ainda não está configurada.</strong>
            Cadastre o certificado digital e o CSC do ambiente atual antes de emitir.
            @if (auth()->user()->pode('configuracoes'))
                <a href="{{ route('empresa.edit', $empresa) }}#nfce">Abrir configurações da empresa</a>
            @endif
        </div>
    @endunless

    @error('nfce')
        <div class="alert alert--error">{{ $message }}</div>
    @enderror

    @if ($errosItens->isNotEmpty())
        <div class="alert alert--error">
            <strong>Confira os itens:</strong>
            <ul class="lista-erros">
                @foreach ($errosItens as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('nfce.store') }}" id="form-nova-nota" class="form-nota">
        @csrf

        <div class="card">
            <div class="card__header">
                <span class="card__title">Consumidor e produtos</span>
                <small class="text-muted">Grava uma venda confirmada (baixa o estoque) e emite a NFC-e na hora, como no PDV.</small>
            </div>
            <div class="card__body">
                @include('venda._form', [
                    'situacaoFixa' => 'A NFC-e só sai de venda confirmada.',
                    'buscaProdutos' => route('api.produtos.busca', ['tipo' => 'produto']),
                    'tituloItens' => 'Produtos',
                    'rotuloItem' => 'Produto',
                    'itensIniciais' => $itensIniciais,
                ])
            </div>
        </div>

        <div class="card">
            <div class="card__header">
                <span class="card__title">Consumidor na nota</span>
            </div>
            <div class="card__body">
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label" for="nfce-documento">CPF ou CNPJ do consumidor</label>
                        <input type="text" name="nfce_documento" id="nfce-documento" maxlength="18" inputmode="numeric" autocomplete="off"
                            class="form-control @error('nfce_documento') is-invalid @enderror"
                            value="{{ old('nfce_documento') }}" placeholder="Opcional">
                        @error('nfce_documento')
                            <span class="form-error">{{ $message }}</span>
                        @else
                            <small class="text-muted">
                                Em branco, a nota usa o CPF/CNPJ do cliente escolhido acima, se houver.
                                @if ($exigeDocumentoPb)
                                    Na Paraíba, é obrigatório a partir de R$ 500,00.
                                @endif
                            </small>
                        @enderror
                    </div>
                </div>
            </div>
            <div class="card__footer">
                <a href="{{ route('nfce.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary" @disabled(! $configurada)
                    data-espera="Emitindo..." data-demora="Gravando a venda e enviando a NFC-e para a SEFAZ.">Emitir NFC-e</button>
            </div>
        </div>
    </form>

    @include('components.cliente-rapido')
    @include('venda._pagamento-modal')

@endsection

@push('scripts')
<script src="{{ asset('js/pagamento-modal.js') }}?v={{ filemtime(public_path('js/pagamento-modal.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.PagamentoModal) return;
    window.PagamentoModal.iniciarVenda({
        root: 'venda-pagamento',
        form: 'form-nova-nota',
        abrir: 'btn-escolher-pagamento',
        hidden: 'hidden-forma-pagamento',
        resumo: 'pagamento-resumo',
        campos: 'venda-parcelas',
        enviar: false,
        total: function () {
            return parseFloat(document.getElementById('input-total').value) || 0;
        },
        dataBase: function () {
            var campo = document.querySelector('#form-nova-nota [name="data_venda"]');
            return campo && campo.value ? campo.value : new Date().toISOString().slice(0, 10);
        },
        subtitulo: function () {
            var cliente = document.getElementById('input-cliente');
            return 'Nova NFC-e · ' + (cliente && cliente.value ? cliente.value : 'Consumidor final');
        }
    });
});
</script>
@endpush
