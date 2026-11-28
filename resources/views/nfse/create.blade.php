@extends('layouts.app')

@php
    $dinheiro = fn ($valor) => 'R$ '.number_format((float) $valor, 2, ',', '.');
    $cliente = $venda->cliente;
    $documentoCliente = $cliente ? (preg_replace('/\D/', '', (string) ($cliente->cnpj ?: $cliente->cpf)) ?: null) : null;
    $podeEmitir = $alertas === [] && $bloqueio === null;
@endphp

@section('title', 'Emitir NFS-e — Venda #'.$venda->numero)
@section('page_title', 'Emitir NFS-e')

@section('breadcrumb')
    <a href="{{ route('venda.index') }}">Vendas</a>
    / <a href="{{ route('venda.show', $venda) }}">Venda #{{ $venda->numero }}</a>
    / NFS-e
@endsection

@section('page_actions')
    <div class="page-actions">
        <a href="{{ route('venda.show', $venda) }}" class="btn btn--ghost btn--sm btn--icon" title="Voltar" aria-label="Voltar">
            <x-icon name="arrow-left" />
        </a>
    </div>
@endsection

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

    @if ($bloqueio)
        <div class="alert alert--error">{{ $bloqueio }}</div>
    @endif

    @if ($anterior)
        <div class="alert alert--error">
            <strong>A última tentativa (DPS {{ $anterior->serie }}/{{ $anterior->numero }}) foi recusada.</strong>
            Os dados enviados voltaram para o formulário; corrija e emita de novo com o mesmo número.
            @if ($anterior->mensagens)
                <ul class="lista-erros">
                    @foreach ($anterior->mensagens as $mensagem)
                        <li>@if (filled($mensagem['codigo'] ?? null))<strong>{{ $mensagem['codigo'] }}</strong> — @endif{{ $mensagem['texto'] ?? '' }}</li>
                    @endforeach
                </ul>
            @elseif ($anterior->motivo_status)
                <br>{{ $anterior->motivo_status }}
            @endif
        </div>
    @endif

    @if ($errors->has('nfse'))
        <div class="alert alert--error">
            <strong>A DPS seria recusada. Corrija antes de emitir:</strong>
            <ul class="lista-erros">
                @foreach ($errors->get('nfse') as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($pendencias !== [])
        <div class="alert alert--warning">
            <strong>Com os dados sugeridos, a DPS seria recusada:</strong>
            <ul class="lista-erros">
                @foreach ($pendencias as $pendencia)
                    <li>{{ $pendencia }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card__header">
            <span class="card__title">Serviços da venda #{{ $venda->numero }}</span>
            <small class="text-muted">Só os itens cadastrados como Serviço entram na NFS-e.</small>
        </div>
        <div class="card__body card__body--flush">
            <div class="table-wrap">
                <table class="table table--cards">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th class="col-num">Qtd.</th>
                            <th class="col-num">Unitário</th>
                            <th class="col-num">Desconto</th>
                            <th class="col-num">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itens as $item)
                            <tr>
                                <td data-label="Serviço">{{ $item->produto_nome }}</td>
                                <td data-label="Qtd." class="col-num">{{ rtrim(rtrim(number_format((float) $item->quantidade, 3, ',', '.'), '0'), ',') }}</td>
                                <td data-label="Unitário" class="col-num">{{ $dinheiro($item->preco_unitario) }}</td>
                                <td data-label="Desconto" class="col-num">{{ $dinheiro($item->desconto) }}</td>
                                <td data-label="Total" class="col-num">{{ $dinheiro($item->total) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-ficha titulo="Valores e tomador">
        <x-ficha-campo label="Valor dos serviços">{{ $dinheiro($valores['servico']) }}</x-ficha-campo>
        <x-ficha-campo label="Desconto incondicionado">{{ $dinheiro($valores['desconto']) }}</x-ficha-campo>
        <x-ficha-campo label="Valor líquido"><strong>{{ $dinheiro($valores['liquido']) }}</strong></x-ficha-campo>
        @if ($totalProdutos !== null)
            <x-ficha-campo label="Produtos da venda" largo>
                {{ $dinheiro($totalProdutos) }} em produtos ficam fora desta nota e vão na NF-e. O desconto e o acréscimo da venda foram divididos na proporção dos valores.
            </x-ficha-campo>
        @endif
        <x-ficha-campo label="Tomador" largo>
            @if ($cliente && $documentoCliente)
                {{ $cliente->nome }} — {{ strlen($documentoCliente) === 14 ? 'CNPJ' : 'CPF' }} {{ $documentoCliente }}
                @if (filled($cliente->cidade))
                    <span class="text-muted">({{ $cliente->cidade }}{{ $cliente->estado ? '/'.$cliente->estado : '' }})</span>
                @endif
            @elseif ($cliente)
                {{ $cliente->nome }} <span class="text-muted">— sem CPF/CNPJ: a NFS-e sai sem tomador identificado.</span>
            @else
                <span class="text-muted">Sem cliente na venda: a NFS-e sai sem tomador identificado.</span>
            @endif
        </x-ficha-campo>
    </x-ficha>

    <div class="card">
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
        <form method="POST" action="{{ route('nfse.store', $venda) }}" id="form-emitir-nfse">
            @csrf
            <div class="card__body">
                @include('nfse._dados', ['documentoCliente' => $documentoCliente])
            </div>

            <div class="card__footer">
                <a href="{{ route('venda.show', $venda) }}" class="btn btn--ghost">Voltar</a>
                <button type="submit" class="btn btn--primary" @disabled(! $podeEmitir)
                    data-espera="Emitindo..." data-demora="Enviando a DPS para a Sefin Nacional.">Emitir NFS-e</button>
            </div>
        </form>
    </div>

@endsection
