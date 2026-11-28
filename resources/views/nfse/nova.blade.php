@extends('layouts.app')

@php
    $podeEmitir = $alertas === [];
    $errosItens = collect($errors->getMessages())
        ->filter(fn ($mensagens, $campo) => str_starts_with((string) $campo, 'itens.'))
        ->flatten()
        ->unique()
        ->values();
@endphp

@section('title', 'Nova NFS-e')
@section('page_title', 'Nova NFS-e')

@section('breadcrumb')
    <a href="{{ route('nfse.index') }}">NFS-e</a> / Nova
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('nfse.index') }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
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
            Emissão na <strong>produção restrita</strong> (homologação): a NFS-e sai sem valor fiscal.
            Troque o ambiente em Empresa → Configurações NFS-e quando os testes terminarem.
        </div>
    @endif

    @if ($alertas !== [])
        <div class="alert alert--error">
            <strong>Antes de emitir, acerte o cadastro da empresa:</strong>
            <ul class="lista-erros">
                @foreach ($alertas as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
            @if (auth()->user()->pode('configuracoes'))
                <a href="{{ route('empresa.edit', $empresa) }}#nfse">Abrir configurações da empresa</a>
            @endif
        </div>
    @endif

    @if ($errors->has('nfse'))
        <div class="alert alert--error">
            <strong>A DPS seria recusada. Nada foi gravado; corrija e emita de novo:</strong>
            <ul class="lista-erros">
                @foreach ($errors->get('nfse') as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($errosItens->isNotEmpty())
        <div class="alert alert--error">
            <strong>Confira os serviços:</strong>
            <ul class="lista-erros">
                @foreach ($errosItens as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('nfse.salvar') }}" id="form-nova-nota" class="form-nota">
        @csrf

        <div class="card">
            <div class="card__header">
                <span class="card__title">Tomador e serviços</span>
                <small class="text-muted">Grava uma venda confirmada só com estes serviços e emite a NFS-e dela.</small>
            </div>
            <div class="card__body">
                @include('venda._form', [
                    'situacaoFixa' => 'A NFS-e só sai de venda confirmada.',
                    'buscaProdutos' => route('api.produtos.busca', ['tipo' => 'servico']),
                    'tituloItens' => 'Serviços',
                    'rotuloItem' => 'Serviço',
                    'itensIniciais' => $itensIniciais,
                ])
            </div>
        </div>

        <div class="card" id="dados-nfse">
            <div class="card__header">
                <span class="card__title">Dados da NFS-e</span>
                <small class="text-muted">
                    {{ match ($opSimpNac) {
                        \App\Services\Nfses\NfseRegras::OP_MEI => 'MEI',
                        \App\Services\Nfses\NfseRegras::OP_ME_EPP => 'ME/EPP do Simples Nacional',
                        default => 'Não optante do Simples Nacional',
                    } }}
                </small>
            </div>
            <div class="card__body">
                <div class="alert alert--warning" id="aviso-codigos" hidden></div>
                @include('nfse._dados', ['tomadorNaTela' => true, 'descricaoOpcional' => true])
            </div>
            <div class="card__footer">
                <a href="{{ route('nfse.index') }}" class="btn btn--ghost">Cancelar</a>
                <button type="submit" class="btn btn--primary" @disabled(! $podeEmitir)
                    data-espera="Emitindo..." data-demora="Gravando a venda e enviando a DPS para a Sefin Nacional.">Emitir NFS-e</button>
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
    if (window.PagamentoModal) {
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
                return 'Nova NFS-e · ' + (cliente && cliente.value ? cliente.value : 'Tomador não identificado');
            }
        });
    }

    // Competência acompanha a data da venda até ser mudada à mão.
    var dataVenda = document.querySelector('#form-nova-nota [name="data_venda"]');
    var competencia = document.getElementById('data-competencia');
    if (dataVenda && competencia) {
        var competenciaManual = competencia.value !== dataVenda.value;
        competencia.addEventListener('input', function () { competenciaManual = true; });
        dataVenda.addEventListener('change', function () {
            if (!competenciaManual && dataVenda.value <= competencia.max) competencia.value = dataVenda.value;
        });
    }

    // Serviço escolhido com códigos gravados: preenche os códigos da nota (o primeiro manda).
    var raiz = document.querySelector('#dados-nfse [data-nfse-codigos]');
    var aviso = document.getElementById('aviso-codigos');
    var codigoDosServicos = null;

    document.addEventListener('venda-form:produto', function (evento) {
        var item = evento.detail || {};
        var codigos = item.nfse || {};
        var servico = String(codigos.c_trib_nac || '').replace(/\D/g, '');
        if (!raiz || !window.NfseCodigos || servico.length !== 6) return;

        if (codigoDosServicos === null) {
            codigoDosServicos = servico;
            window.NfseCodigos.preencher(raiz, codigos);
            return;
        }

        if (servico !== codigoDosServicos && aviso) {
            aviso.textContent = '"' + item.nome + '" está gravado com outro código de tributação ('
                + servico.replace(/^(\d{2})(\d{2})(\d{2})$/, '$1.$2.$3') + '). A NFS-e leva um código por nota: '
                + 'emita esse serviço em outra nota ou acerte o código no cadastro.';
            aviso.hidden = false;
        }
    });
});
</script>
@endpush
