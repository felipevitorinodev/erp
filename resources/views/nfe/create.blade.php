@extends('layouts.app')

@php
    $editando = (bool) $rascunho;
    $titulo = $editando ? 'Rascunho de NF-e (venda #' . $rascunho->numero . ')' : 'Nova NF-e';
@endphp

@section('title', $titulo)
@section('page_title', $titulo)

@section('breadcrumb')
    <a href="{{ route('nfe.index') }}">NF-e Emitidas</a> / {{ $editando ? 'Rascunho' : 'Nova NF-e' }}
@endsection

@section('content')

<div class="nfe-wizard">
<div class="nfe-alerts">
    @if (count($alertas))
        <div class="alert alert--error">
            <strong>Pendências da empresa para emitir</strong>
            <ul class="nfe-alerts__list">
                @foreach ($alertas as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
            <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;margin-top:0.5rem;">
                <a href="{{ route('empresa.edit', $empresa) }}" class="btn btn--ghost btn--sm">Abrir cadastro da empresa</a>
                <small>Você pode preencher a nota e salvar como rascunho enquanto isso.</small>
            </div>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert--error" role="alert">
            <strong>Corrija antes de continuar</strong>
            <ul class="nfe-alerts__list">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($avisoCertificado)
        <div class="alert alert--warning">{{ $avisoCertificado }}</div>
    @endif

    @if ((int) ($empresa->nfe_ambiente ?? 2) === 2)
        <div class="alert alert--warning">Homologação — sem valor fiscal.</div>
    @else
        <div class="alert alert--warning">Produção — a nota terá valor fiscal real.</div>
    @endif

    <div class="alert alert--error" id="nfe-aviso" hidden role="alert"></div>
</div>

<nav class="nfe-steps" aria-label="Passos da NF-e">
    <button type="button" class="nfe-steps__item is-active" data-step-btn="1">
        <span class="nfe-steps__num">1</span>
        <span class="nfe-steps__label">Cabeçalho</span>
    </button>
    <button type="button" class="nfe-steps__item" data-step-btn="2">
        <span class="nfe-steps__num">2</span>
        <span class="nfe-steps__label">Itens</span>
    </button>
    <button type="button" class="nfe-steps__item" data-step-btn="3">
        <span class="nfe-steps__num">3</span>
        <span class="nfe-steps__label">Revisão</span>
    </button>
</nav>

<form method="POST" action="{{ $editando ? route('nfe.rascunho.update', $rascunho) : route('nfe.store') }}" id="form-nfe">
    @csrf
    @if ($editando)
        @method('PUT')
    @endif
    <input type="hidden" name="acao" id="input-acao" value="emitir">
    <input type="hidden" name="subtotal" id="input-subtotal" value="0">
    <input type="hidden" name="total" id="input-total" value="0">

    {{-- PASSO 1 — Cabeçalho --}}
    <section class="nfe-step-panel is-active" data-step-panel="1">
        <div class="card">
            <div class="card__header">
                <span class="card__title">Cabeçalho da nota</span>
            </div>
            <div class="card__body">
                <div class="nfe-meta-row">
                    <div class="form-group">
                        <label class="form-label">Série</label>
                        <input type="text" class="form-control" value="{{ $empresa->nfe_serie ?? '001' }}" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Próximo número</label>
                        <input type="text" class="form-control"
                            value="{{ str_pad((string) ($empresa->nfe_sequencia ?? 1), 9, '0', STR_PAD_LEFT) }}" readonly
                            title="O número só é consumido na emissão; rascunhos não usam número.">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data de emissão</label>
                        <input type="date" name="data_venda" class="form-control"
                            value="{{ old('data_venda', $rascunho?->data_venda?->format('Y-m-d') ?? date('Y-m-d')) }}">
                        @error('data_venda')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-grid form-grid--col-3">
                    <div class="form-group form-group--span-2">
                        <label class="form-label form-label--required">Natureza da operação</label>
                        <input type="text" name="natureza_operacao" class="form-control" maxlength="60"
                            value="{{ old('natureza_operacao', $rascunho?->natureza_operacao ?? 'VENDA DE MERCADORIA') }}">
                        @error('natureza_operacao')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Forma de pagamento</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-forma-pagamento" class="form-control"
                                placeholder="Buscar forma..." autocomplete="off"
                                value="{{ old('forma_pagamento', $rascunho?->forma_pagamento) }}">
                            <input type="hidden" name="forma_pagamento" id="hidden-forma-pagamento"
                                value="{{ old('forma_pagamento', $rascunho?->forma_pagamento) }}">
                        </div>
                        @error('forma_pagamento')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group form-group--span-2">
                        <label class="form-label">Destinatário</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-cliente" class="form-control"
                                placeholder="Consumidor Final" autocomplete="off"
                                value="{{ $clienteInicial?->nome }}">
                            <input type="hidden" name="cliente_id" id="hidden-cliente-id" value="{{ $clienteInicial?->id }}">
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.4rem;flex-wrap:wrap;">
                            <button type="button" class="btn btn--ghost btn--sm" id="btn-novo-cliente">+ Novo cliente</button>
                            <small class="text-muted" id="cliente-doc-hint">
                                @if ($clienteInicial)
                                    {{ $clienteInicial->cnpj ? 'CNPJ ' . $clienteInicial->cnpj : ($clienteInicial->cpf ? 'CPF ' . $clienteInicial->cpf : 'Sem documento') }}
                                @endif
                            </small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vendedor</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-vendedor" class="form-control"
                                placeholder="Opcional" autocomplete="off" value="{{ $funcionarioInicial?->nome }}">
                            <input type="hidden" name="funcionario_id" id="hidden-funcionario-id"
                                value="{{ $funcionarioInicial?->id }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consumidor final</label>
                        @php $indFinal = (string) old('ind_final', $rascunho?->ind_final); @endphp
                        <select name="ind_final" id="ind_final" class="form-control">
                            <option value="">Automático (CPF=sim / CNPJ=não)</option>
                            <option value="1" @selected($indFinal === '1')>Sim (1)</option>
                            <option value="0" @selected($indFinal === '0')>Não (0)</option>
                        </select>
                    </div>
                    <div class="form-group form-group--span-2">
                        <label class="form-label">Modalidade do frete</label>
                        <select name="mod_frete" class="form-control">
                            @foreach([
                                9 => '9 — Sem frete',
                                0 => '0 — Por conta do remetente',
                                1 => '1 — Por conta do destinatário',
                                2 => '2 — Por conta de terceiros',
                                3 => '3 — Próprio remetente',
                                4 => '4 — Próprio destinatário',
                            ] as $valor => $label)
                                <option value="{{ $valor }}" @selected((int) old('mod_frete', $rascunho?->mod_frete ?? 9) === $valor)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- PASSO 2 — Itens --}}
    <section class="nfe-step-panel" data-step-panel="2" hidden>
        <div class="card">
            <div class="card__header" style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;flex-wrap:wrap;">
                <span class="card__title">Itens e impostos</span>
                <button type="button" class="btn btn--primary btn--sm" id="btn-abrir-item">
                    + Adicionar item <kbd class="nfe-kbd">Ins</kbd>
                </button>
            </div>
            <div class="card__body" style="padding:0;">
                <div class="table-wrap">
                    <table class="table table--cards" id="tabela-itens">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Fiscal</th>
                                <th class="col-num">Qtd</th>
                                <th class="col-num">Total</th>
                                <th class="col-actions">Ações</th>
                            </tr>
                        </thead>
                        <tbody id="itens-body"></tbody>
                    </table>
                </div>
                <div id="itens-hidden"></div>
            </div>
            <div class="card__footer" style="justify-content:space-between;">
                <span class="text-muted"><span id="count-itens">0</span> item(ns)</span>
                <strong id="label-subtotal-itens">Subtotal R$ 0,00</strong>
            </div>
        </div>
    </section>

    {{-- PASSO 3 — Revisão --}}
    <section class="nfe-step-panel" data-step-panel="3" hidden>
        <div class="card">
            <div class="card__header">
                <span class="card__title">Totais e observações</span>
            </div>
            <div class="card__body">
                <div class="nfe-pendencias" id="nfe-pendencias"></div>

                <div class="form-grid form-grid--col-3">
                    <div class="form-group">
                        <label class="form-label">Subtotal</label>
                        <input type="text" class="form-control" id="label-subtotal" value="R$ 0,00" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Desconto</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">R$</span>
                            <input type="tel" name="desconto" id="input-desconto"
                                class="form-control text-right input-numeric"
                                data-decimals="2" inputmode="numeric"
                                value="{{ old('desconto', number_format((float) ($rascunho?->desconto ?? 0), 2, ',', '')) }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Acréscimo</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">R$</span>
                            <input type="tel" name="acrescimo" id="input-acrescimo"
                                class="form-control text-right input-numeric"
                                data-decimals="2" inputmode="numeric"
                                value="{{ old('acrescimo', number_format((float) ($rascunho?->acrescimo ?? 0), 2, ',', '')) }}">
                        </div>
                    </div>
                </div>

                <div class="sticky-total-bar" style="margin-top:1rem;">
                    <div>
                        <div class="sticky-total-bar__label">Total da NF-e</div>
                        <div class="sticky-total-bar__value" id="label-total">R$ 0,00</div>
                    </div>
                    <div class="text-muted" style="color:#fff;opacity:.85;font-size:12px;" id="resumo-cabecalho">
                        —
                    </div>
                </div>

                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Informações complementares (infCpl)</label>
                    <textarea name="observacoes" class="form-control" rows="3" maxlength="1000"
                        placeholder="Texto de interesse do contribuinte...">{{ old('observacoes', $rascunho?->observacoes) }}</textarea>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Informações ao fisco (infAdFisco)</label>
                    <textarea name="inf_ad_fisco" class="form-control" rows="2" maxlength="2000"
                        placeholder="Opcional">{{ old('inf_ad_fisco', $rascunho?->inf_ad_fisco) }}</textarea>
                </div>

                <div class="alert alert--warning" style="margin-top:1rem;">
                    <strong>Salvar rascunho</strong> guarda a nota sem mexer no estoque e sem enviar nada.
                    <strong>Emitir</strong> confirma a venda (estoque e financeiro) e envia a NF-e à SEFAZ.
                </div>
            </div>
        </div>
    </section>

    <div class="nfe-wizard-actions">
        <button type="button" class="btn btn--ghost" id="btn-voltar" hidden>Voltar</button>
        <div class="nfe-wizard-actions__right">
            <a href="{{ route('nfe.index') }}" class="btn btn--ghost">{{ $editando ? 'Fechar' : 'Cancelar' }}</a>
            <button type="submit" class="btn btn--outline" id="btn-rascunho" data-acao="rascunho">Salvar rascunho</button>
            <button type="button" class="btn btn--primary" id="btn-avancar">Continuar</button>
            <button type="button" class="btn btn--primary" id="btn-emitir" hidden @disabled(! $podeEmitir)
                @unless($podeEmitir) title="Resolva as pendências da empresa para emitir" @endunless>
                Emitir NF-e
            </button>
            <button type="submit" id="btn-emitir-confirmar" data-acao="emitir" hidden
                data-confirm="Confirmar emissão da NF-e para a SEFAZ? A venda será confirmada e o estoque baixado."
                data-confirm-title="Emitir NF-e"
                data-confirm-ok="Emitir agora"></button>
        </div>
    </div>
</form>

@if ($editando)
    <form method="POST" action="{{ route('nfe.rascunho.destroy', $rascunho) }}" style="margin-top:0.75rem;text-align:right;">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn btn--danger btn--sm"
            data-confirm="Excluir este rascunho? Nada foi emitido nem movimentado."
            data-confirm-title="Excluir rascunho"
            data-confirm-ok="Excluir"
            data-confirm-variant="danger">Excluir rascunho</button>
    </form>
@endif
</div>{{-- /.nfe-wizard --}}

{{-- Modal item --}}
<div class="modal-overlay" id="modal-item" hidden>
    <div class="modal-box modal-box--wide nfe-item-modal" role="dialog" aria-modal="true" aria-labelledby="modal-item-titulo">
        <div class="modal-box__header">
            <span class="card__title" id="modal-item-titulo">Adicionar item</span>
            <button type="button" class="btn btn--ghost btn--sm" data-modal-item-close aria-label="Fechar">&times;</button>
        </div>
        <div class="modal-box__body">
            <div class="alert alert--error" id="modal-item-aviso" hidden role="alert" style="margin-bottom:0.85rem;"></div>

            <div class="nfe-item-busca" id="item-busca-wrap">
                <label class="form-label form-label--required" for="busca-produto-item">Produto</label>
                <input type="text" id="busca-produto-item" class="form-control nfe-item-busca__input"
                    placeholder="Digite nome ou código, ou leia o código de barras" autocomplete="off"
                    role="combobox" aria-expanded="false" aria-controls="dropdown-produto-item">
                <div id="dropdown-produto-item" class="autocomplete-dropdown nfe-item-resultados" role="listbox" hidden></div>
                <small class="text-muted nfe-item-busca__dica">↑ ↓ escolhe · Enter seleciona · Esc fecha</small>
            </div>

            <div class="nfe-prod-card" id="item-produto-card" hidden>
                <div class="nfe-prod-card__info">
                    <strong id="item-nome-label"></strong>
                    <span class="text-muted" id="item-meta-label"></span>
                </div>
                <button type="button" class="btn btn--ghost btn--sm" id="btn-trocar-produto">Trocar produto</button>
            </div>
            <input type="hidden" id="item-produto-id">
            <input type="hidden" id="item-nome">
            <input type="hidden" id="item-codigo">

            <div class="nfe-item-valores">
                <div class="form-group">
                    <label class="form-label form-label--required" for="item-quantidade">Quantidade</label>
                    <input type="tel" id="item-quantidade" class="form-control input-numeric nfe-item-valores__grande"
                        data-decimals="2" inputmode="numeric" value="1,00">
                </div>
                <div class="form-group">
                    <label class="form-label form-label--required" for="item-preco">Preço unitário</label>
                    <input type="tel" id="item-preco" class="form-control input-numeric nfe-item-valores__grande"
                        data-decimals="2" inputmode="numeric" value="0,00">
                </div>
                <div class="form-group">
                    <label class="form-label" for="item-desconto">Desconto</label>
                    <input type="tel" id="item-desconto" class="form-control input-numeric"
                        data-decimals="2" inputmode="numeric" value="0,00">
                </div>
                <div class="nfe-item-valores__total">
                    <span>Total do item</span>
                    <strong id="item-total-label">R$ 0,00</strong>
                </div>
            </div>

            <details class="nfe-item-fiscal" id="item-fiscal">
                <summary>
                    <span class="nfe-item-fiscal__titulo">Dados fiscais</span>
                    <span class="nfe-item-fiscal-chips" id="item-fiscal-resumo"></span>
                </summary>
                <div class="nfe-item-fiscal__corpo">
                    <div class="form-grid form-grid--col-4">
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-ncm">NCM</label>
                            <input type="tel" id="item-ncm" class="form-control" maxlength="8" inputmode="numeric" placeholder="00000000">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="item-cest">CEST</label>
                            <input type="tel" id="item-cest" class="form-control" maxlength="9" inputmode="numeric">
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-unidade">Unidade</label>
                            <input type="text" id="item-unidade" class="form-control" maxlength="6" value="UN">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="item-ean">EAN / GTIN</label>
                            <input type="text" id="item-ean" class="form-control" maxlength="14" placeholder="Vazio = SEM GTIN">
                        </div>
                    </div>
                    <div class="form-grid form-grid--col-2" style="margin-top:0.75rem;">
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-cfop">CFOP</label>
                            <select id="item-cfop" class="form-control">
                                <option value="5102">5102 — Venda de mercadoria adquirida</option>
                                <option value="5101">5101 — Venda de produção do estabelecimento</option>
                                <option value="5405">5405 — Venda de mercadoria com ST</option>
                                <option value="5656">5656 — Venda de combustível</option>
                                <option value="5910">5910 — Remessa em bonificação</option>
                            </select>
                            <input type="text" id="item-cfop-livre" class="form-control" maxlength="4"
                                placeholder="Ou digite outro CFOP" style="margin-top:0.35rem;" inputmode="numeric">
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-origem">Origem</label>
                            <select id="item-origem" class="form-control">
                                <option value="0">0 — Nacional</option>
                                <option value="1">1 — Estrangeira (importação direta)</option>
                                <option value="2">2 — Estrangeira (mercado interno)</option>
                                <option value="3">3 — Nacional (conteúdo importação &gt; 40%)</option>
                                <option value="4">4 — Nacional (processos básicos)</option>
                                <option value="5">5 — Nacional (conteúdo importação ≤ 40%)</option>
                                <option value="6">6 — Estrangeira (sem similar nacional)</option>
                                <option value="7">7 — Estrangeira (mercado interno, CAMEX)</option>
                                <option value="8">8 — Nacional (conteúdo importação &gt; 70%)</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-grid form-grid--col-3" style="margin-top:0.75rem;">
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-csosn">CSOSN (ICMS)</label>
                            <select id="item-csosn" class="form-control">
                                <option value="102">102 — Tributada sem crédito</option>
                                <option value="103">103 — Isenção</option>
                                <option value="300">300 — Imune</option>
                                <option value="400">400 — Não tributada</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-cst-pis">CST PIS</label>
                            <select id="item-cst-pis" class="form-control">
                                <option value="07">07 — Operação isenta</option>
                                <option value="04">04 — Operação tributável monofásica</option>
                                <option value="05">05 — Operação tributável ST</option>
                                <option value="06">06 — Operação tributável alíquota zero</option>
                                <option value="08">08 — Sem incidência</option>
                                <option value="09">09 — Suspensão</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label form-label--required" for="item-cst-cofins">CST COFINS</label>
                            <select id="item-cst-cofins" class="form-control">
                                <option value="07">07 — Operação isenta</option>
                                <option value="04">04 — Operação tributável monofásica</option>
                                <option value="05">05 — Operação tributável ST</option>
                                <option value="06">06 — Operação tributável alíquota zero</option>
                                <option value="08">08 — Sem incidência</option>
                                <option value="09">09 — Suspensão</option>
                            </select>
                        </div>
                    </div>
                    <p class="text-muted" style="margin:0.75rem 0 0;font-size:12px;">
                        Os dados vêm do cadastro do produto. Simples Nacional: CSOSN 102/103/300/400 e PIS/COFINS isentos (CST 07) cobrem o caso mais comum.
                    </p>
                </div>
            </details>
        </div>
        <div class="modal-box__footer">
            <button type="button" class="btn btn--ghost" data-modal-item-close>Cancelar</button>
            <button type="button" class="btn btn--outline" id="btn-salvar-item-novo">Salvar e adicionar outro</button>
            <button type="button" class="btn btn--primary" id="btn-salvar-item">Salvar item <kbd class="nfe-kbd">Enter</kbd></button>
        </div>
    </div>
</div>

@include('components.modal-cadastro-rapido', [
    'id' => 'modal-novo-cliente-nfe',
    'titulo' => 'Novo Cliente',
    'rota' => route('api.clientes.store'),
    'btnLabel' => 'Salvar cliente',
    'campos' => [
        ['name' => 'nome', 'label' => 'Nome', 'type' => 'text', 'required' => true],
        ['name' => 'cpf', 'label' => 'CPF', 'type' => 'text'],
        ['name' => 'cnpj', 'label' => 'CNPJ', 'type' => 'text'],
        ['name' => 'telefone', 'label' => 'Telefone', 'type' => 'text'],
        ['name' => 'email', 'label' => 'E-mail', 'type' => 'email'],
    ],
    'onSuccess' => 'function(data) {
        var input = document.getElementById("input-cliente");
        var hidden = document.getElementById("hidden-cliente-id");
        if (input) input.value = data.nome;
        if (hidden) hidden.value = data.id;
    }',
])

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var podeEmitir = @json($podeEmitir);
    var defaults = {
        cfop: @json($empresa->nfe_cfop_padrao ?? '5102'),
        csosn: @json($empresa->nfe_csosn_padrao ?? '400'),
        cstPis: @json($empresa->nfe_cst_pis_padrao ?? '07'),
        cstCofins: @json($empresa->nfe_cst_cofins_padrao ?? '07')
    };
    var urls = {
        produtos: @json(route('api.produtos.busca')),
        clientes: @json(route('api.clientes.busca')),
        formas: @json(route('api.formas-pagamento.busca')),
        funcionarios: @json(route('api.funcionarios.busca'))
    };
    var CFOPS_LISTA = ['5101', '5102', '5405', '5656', '5910'];
    var CSOSN_OK = ['102', '103', '300', '400'];
    var CST_OK = ['04', '05', '06', '07', '08', '09'];

    var passo = 1;
    var itens = (@json($itensIniciais) || []).map(function (it) {
        it.quantidade = parseFloat(it.quantidade) || 0;
        it.preco = parseFloat(it.preco) || 0;
        it.desconto = parseFloat(it.desconto) || 0;
        it.cfop = it.cfop || defaults.cfop;
        it.csosn = it.csosn || defaults.csosn;
        it.cst_pis = it.cst_pis || defaults.cstPis;
        it.cst_cofins = it.cst_cofins || defaults.cstCofins;
        return it;
    });
    var editIndex = null;
    var avisoEl = document.getElementById('nfe-aviso');
    var avisoModalEl = document.getElementById('modal-item-aviso');
    var avisoTimer = null;

    function parseBR(val) {
        if (typeof window.parseBR === 'function') return window.parseBR(val);
        return parseFloat(String(val || '0').replace(/\./g, '').replace(',', '.')) || 0;
    }
    function formatBR(val) {
        return 'R$ ' + (parseFloat(val) || 0).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function formatInput(val) {
        return (parseFloat(val) || 0).toFixed(2).replace('.', ',');
    }
    function formatQtd(val) {
        var n = parseFloat(val) || 0;
        return (Math.round(n) === n ? n.toFixed(0) : n.toFixed(3).replace(/0+$/, '')).replace('.', ',');
    }
    function esc(val) {
        var div = document.createElement('div');
        div.textContent = val == null ? '' : String(val);
        return div.innerHTML.replace(/"/g, '&quot;');
    }
    function digits(val, max) {
        var d = String(val || '').replace(/\D/g, '');
        return max ? d.slice(0, max) : d;
    }

    function limparInvalidos(escopo) {
        (escopo || document).querySelectorAll('.is-invalid').forEach(function (el) {
            el.classList.remove('is-invalid');
        });
    }

    function marcarInvalido(el) {
        if (!el) return;
        el.classList.add('is-invalid');
        try { el.focus(); } catch (e) {}
    }

    function mostrarAviso(msg, opts) {
        opts = opts || {};
        var target = opts.modal ? avisoModalEl : avisoEl;
        if (!target) return;
        target.textContent = msg;
        target.hidden = false;
        if (!opts.modal) {
            target.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        clearTimeout(avisoTimer);
        avisoTimer = setTimeout(function () {
            if (target) target.hidden = true;
        }, 6000);
    }

    function limparAviso(modal) {
        var target = modal ? avisoModalEl : avisoEl;
        if (target) {
            target.hidden = true;
            target.textContent = '';
        }
    }

    if (typeof initAutocomplete === 'function') {
        initAutocomplete('#input-vendedor', '#hidden-funcionario-id', urls.funcionarios, function (i) { return i.nome; });
        initAutocomplete('#input-cliente', '#hidden-cliente-id', urls.clientes, function (i) {
            return i.nome + (i.cpf || i.cnpj ? ' — ' + (i.cpf || i.cnpj) : '');
        }, function (i) {
            document.getElementById('cliente-doc-hint').textContent = i
                ? ((i.cnpj ? 'CNPJ ' + i.cnpj : (i.cpf ? 'CPF ' + i.cpf : 'Sem documento')))
                : '';
        });
        initAutocomplete('#input-forma-pagamento', '#hidden-forma-pagamento', urls.formas, function (i) {
            return i.nome;
        }, null, null, 'nome');
    }

    document.getElementById('btn-novo-cliente')?.addEventListener('click', function () {
        var m = document.getElementById('modal-novo-cliente-nfe');
        if (m) m.hidden = false;
    });

    // ---------- Passos ----------
    function irPara(n) {
        passo = n;
        limparAviso(false);
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            var ativo = parseInt(el.dataset.stepPanel, 10) === n;
            el.hidden = !ativo;
            el.classList.toggle('is-active', ativo);
        });
        document.querySelectorAll('[data-step-btn]').forEach(function (btn) {
            var num = parseInt(btn.dataset.stepBtn, 10);
            btn.classList.toggle('is-active', num === n);
            btn.classList.toggle('is-done', num < n);
        });
        document.getElementById('btn-voltar').hidden = n === 1;
        document.getElementById('btn-avancar').hidden = n === 3;
        document.getElementById('btn-emitir').hidden = n !== 3;
        if (n === 3) atualizarResumo();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.querySelectorAll('[data-step-btn]').forEach(function (btn) {
        btn.addEventListener('click', function () { irPara(parseInt(btn.dataset.stepBtn, 10)); });
    });
    document.getElementById('btn-voltar').addEventListener('click', function () {
        if (passo > 1) irPara(passo - 1);
    });
    document.getElementById('btn-avancar').addEventListener('click', function () {
        irPara(passo + 1);
    });

    // ---------- Pendências (espelham NfePendenciasService) ----------
    function pendenciasDoItem(it) {
        var p = [];
        if (digits(it.ncm).length !== 8) p.push('NCM com 8 dígitos');
        var cfop = digits(it.cfop);
        if (cfop.length !== 4 || cfop.charAt(0) !== '5') p.push('CFOP da série 5');
        if (CSOSN_OK.indexOf(digits(it.csosn)) < 0) p.push('CSOSN 102/103/300/400');
        if (CST_OK.indexOf(String(it.cst_pis || '').padStart(2, '0')) < 0) p.push('CST PIS 04 a 09');
        if (CST_OK.indexOf(String(it.cst_cofins || '').padStart(2, '0')) < 0) p.push('CST COFINS 04 a 09');
        if (!(it.quantidade > 0)) p.push('quantidade');
        return p;
    }

    function listarPendencias() {
        var lista = [];
        if (!document.querySelector('[name="natureza_operacao"]').value.trim()) {
            lista.push({ msg: 'Informe a natureza da operação.', passo: 1, campo: '[name="natureza_operacao"]' });
        }
        if (!document.getElementById('hidden-forma-pagamento').value) {
            lista.push({ msg: 'Selecione a forma de pagamento.', passo: 1, campo: '#input-forma-pagamento' });
        }
        if (!itens.length) {
            lista.push({ msg: 'Adicione pelo menos um item.', passo: 2 });
        }
        itens.forEach(function (it, idx) {
            var faltas = pendenciasDoItem(it);
            if (faltas.length) {
                lista.push({ msg: 'Item "' + it.nome + '": falta ' + faltas.join(', ') + '.', passo: 2, item: idx });
            }
        });
        var sub = itens.reduce(function (s, it) { return s + totalItem(it); }, 0);
        if (parseBR(document.getElementById('input-desconto').value) > sub + 0.001) {
            lista.push({ msg: 'O desconto é maior que o subtotal dos itens.', passo: 3, campo: '#input-desconto' });
        }
        return lista;
    }

    function renderPendencias() {
        var box = document.getElementById('nfe-pendencias');
        var lista = listarPendencias();
        if (!lista.length) {
            box.innerHTML = '<div class="alert alert--success">Tudo pronto para emitir.</div>';
            return lista;
        }
        box.innerHTML = '<div class="alert alert--warning"><strong>Falta completar para emitir</strong>' +
            '<ul class="nfe-alerts__list">' + lista.map(function (p, i) {
                return '<li>' + esc(p.msg) + ' <button type="button" class="btn btn--ghost btn--sm" data-corrigir="' + i + '">Corrigir</button></li>';
            }).join('') + '</ul><small>Você pode salvar como rascunho e completar depois.</small></div>';
        box.querySelectorAll('[data-corrigir]').forEach(function (btn) {
            btn.addEventListener('click', function () { corrigir(lista[parseInt(btn.dataset.corrigir, 10)]); });
        });
        return lista;
    }

    function corrigir(p) {
        if (typeof p.item === 'number') {
            irPara(2);
            abrirModalItem(p.item, true);
            return;
        }
        irPara(p.passo);
        if (p.campo) marcarInvalido(document.querySelector(p.campo));
    }

    // ---------- Totais e tabela ----------
    function totalItem(it) {
        return Math.max(0, (parseFloat(it.quantidade) * parseFloat(it.preco)) - parseFloat(it.desconto || 0));
    }

    function atualizarTotais() {
        var sub = itens.reduce(function (s, it) { return s + totalItem(it); }, 0);
        var desc = parseBR(document.getElementById('input-desconto').value);
        var acresc = parseBR(document.getElementById('input-acrescimo').value);
        var tot = Math.max(0, sub - desc + acresc);
        document.getElementById('input-subtotal').value = sub.toFixed(2);
        document.getElementById('input-total').value = tot.toFixed(2);
        document.getElementById('label-subtotal').value = formatBR(sub);
        document.getElementById('label-subtotal-itens').textContent = 'Subtotal ' + formatBR(sub);
        document.getElementById('label-total').textContent = formatBR(tot);
        document.getElementById('count-itens').textContent = String(itens.length);
    }

    function atualizarResumo() {
        var nat = document.querySelector('[name="natureza_operacao"]').value;
        var forma = document.getElementById('hidden-forma-pagamento').value || '—';
        var cli = document.getElementById('input-cliente').value || 'Consumidor Final';
        document.getElementById('resumo-cabecalho').textContent = cli + ' · ' + nat + ' · ' + forma;
        atualizarTotais();
        renderPendencias();
    }

    function renderItens() {
        var tbody = document.getElementById('itens-body');
        var hidden = document.getElementById('itens-hidden');
        tbody.innerHTML = '';
        hidden.innerHTML = '';

        if (!itens.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted empty-state">Nenhum item. Clique em “Adicionar item” ou tecle Insert.</td></tr>';
            atualizarTotais();
            return;
        }

        itens.forEach(function (it, idx) {
            var faltas = pendenciasDoItem(it);
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td data-label="Produto"><strong>' + esc(it.nome) + '</strong>' +
                    '<div class="text-muted" style="font-size:11px;">' + esc(it.codigo || '-') + ' · ' + esc(it.unidade) +
                    ' · ' + formatBR(it.preco) + '/un</div></td>' +
                '<td data-label="Fiscal">' +
                    (faltas.length
                        ? '<span class="badge badge--warning" title="' + esc('Falta: ' + faltas.join(', ')) + '">Incompleto</span> '
                        : '') +
                    '<span class="badge badge--neutral">NCM ' + esc(it.ncm || '—') + '</span> ' +
                    '<span class="badge badge--neutral">CFOP ' + esc(it.cfop) + '</span> ' +
                    '<span class="badge badge--neutral">CSOSN ' + esc(it.csosn) + '</span></td>' +
                '<td data-label="Qtd" class="col-num">' + formatQtd(it.quantidade) + '</td>' +
                '<td data-label="Total" class="col-num">' + formatBR(totalItem(it)) + '</td>' +
                '<td data-label="Ações" class="col-actions">' +
                    '<button type="button" class="btn btn--ghost btn--sm" data-edit="' + idx + '">Editar</button>' +
                    '<button type="button" class="btn btn--danger btn--sm btn--icon" data-del="' + idx + '" title="Remover">✕</button>' +
                '</td>';
            tbody.appendChild(tr);

            var fields = {
                produto_id: it.produto_id,
                quantidade: String(it.quantidade),
                preco_unitario: it.preco.toFixed(2),
                desconto: (it.desconto || 0).toFixed(2),
                ncm: digits(it.ncm, 8),
                cest: it.cest || '',
                cfop: it.cfop,
                unidade: it.unidade,
                origem: it.origem,
                csosn: it.csosn,
                cst_pis: it.cst_pis,
                cst_cofins: it.cst_cofins,
                ean: it.ean || ''
            };
            Object.keys(fields).forEach(function (key) {
                if (fields[key] === '' && ['ncm', 'cest', 'ean'].indexOf(key) >= 0) return;
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'itens[' + idx + '][' + key + ']';
                input.value = fields[key];
                hidden.appendChild(input);
            });
        });

        tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { abrirModalItem(parseInt(btn.dataset.edit, 10)); });
        });
        tbody.querySelectorAll('[data-del]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                itens.splice(parseInt(btn.dataset.del, 10), 1);
                renderItens();
            });
        });
        atualizarTotais();
    }

    // ---------- Modal do item ----------
    var modal = document.getElementById('modal-item');
    var buscaWrap = document.getElementById('item-busca-wrap');
    var buscaItem = document.getElementById('busca-produto-item');
    var dropItem = document.getElementById('dropdown-produto-item');
    var cardProduto = document.getElementById('item-produto-card');
    var fiscalBox = document.getElementById('item-fiscal');
    var resultados = [];
    var ativo = -1;
    var ultimaBusca = 0;
    var el = function (id) { return document.getElementById(id); };

    function modalAberto() { return !modal.hidden; }

    function fecharModal() {
        fecharDropdown();
        modal.hidden = true;
    }

    function abrirModalItem(idx, abrirFiscal) {
        editIndex = (typeof idx === 'number') ? idx : null;
        el('modal-item-titulo').textContent = editIndex === null ? 'Adicionar item' : 'Editar item';
        el('btn-salvar-item-novo').hidden = editIndex !== null;
        limparAviso(true);
        limparInvalidos(modal);
        limparModal();

        if (editIndex !== null) {
            var it = itens[editIndex];
            preencherModal(it);
            mostrarProdutoSelecionado({ id: it.produto_id, nome: it.nome, codigo: it.codigo, unidade: it.unidade });
            fiscalBox.open = !!abrirFiscal || pendenciasDoItem(it).length > 0;
        } else {
            mostrarBusca();
        }
        modal.hidden = false;
        setTimeout(function () {
            if (editIndex === null) buscaItem.focus();
            else if (fiscalBox.open && abrirFiscal) el('item-ncm').focus();
            else { el('item-quantidade').focus(); el('item-quantidade').select(); }
        }, 60);
    }

    function mostrarBusca() {
        buscaWrap.hidden = false;
        cardProduto.hidden = true;
        buscaItem.value = '';
    }

    function mostrarProdutoSelecionado(p) {
        el('item-produto-id').value = p.id;
        el('item-nome').value = p.nome;
        el('item-codigo').value = p.codigo || '';
        el('item-nome-label').textContent = p.nome;
        var meta = [p.codigo ? 'Cód. ' + p.codigo : null, p.unidade || null];
        if (p.controla_estoque && p.estoque_atual != null) meta.push('Estoque: ' + formatQtd(p.estoque_atual));
        el('item-meta-label').textContent = meta.filter(Boolean).join(' · ');
        buscaWrap.hidden = true;
        cardProduto.hidden = false;
    }

    function limparModal() {
        el('item-produto-id').value = '';
        el('item-nome').value = '';
        el('item-codigo').value = '';
        el('item-unidade').value = 'UN';
        el('item-ean').value = '';
        el('item-quantidade').value = '1,00';
        el('item-preco').value = '0,00';
        el('item-desconto').value = '0,00';
        el('item-ncm').value = '';
        el('item-cest').value = '';
        definirCfop(defaults.cfop);
        el('item-origem').value = '0';
        el('item-csosn').value = defaults.csosn;
        el('item-cst-pis').value = defaults.cstPis;
        el('item-cst-cofins').value = defaults.cstCofins;
        fiscalBox.open = false;
        atualizarTotalModalItem();
        atualizarResumoFiscal();
    }

    function definirCfop(cfop) {
        if (CFOPS_LISTA.indexOf(cfop) >= 0) {
            el('item-cfop').value = cfop;
            el('item-cfop-livre').value = '';
        } else {
            el('item-cfop-livre').value = cfop || '';
        }
    }

    function selecionarOpcao(select, valor) {
        if (Array.prototype.some.call(select.options, function (o) { return o.value === valor; })) {
            select.value = valor;
        }
    }

    function preencherModal(it) {
        el('item-unidade').value = it.unidade || 'UN';
        el('item-ean').value = it.ean || '';
        el('item-quantidade').value = formatInput(it.quantidade);
        el('item-preco').value = formatInput(it.preco);
        el('item-desconto').value = formatInput(it.desconto || 0);
        el('item-ncm').value = it.ncm || '';
        el('item-cest').value = it.cest || '';
        definirCfop(it.cfop || defaults.cfop);
        el('item-origem').value = String(it.origem ?? 0);
        selecionarOpcao(el('item-csosn'), it.csosn || defaults.csosn);
        selecionarOpcao(el('item-cst-pis'), it.cst_pis || defaults.cstPis);
        selecionarOpcao(el('item-cst-cofins'), it.cst_cofins || defaults.cstCofins);
        atualizarTotalModalItem();
        atualizarResumoFiscal();
    }

    function lerItemDoModal() {
        return {
            produto_id: parseInt(el('item-produto-id').value, 10) || null,
            nome: el('item-nome').value,
            codigo: el('item-codigo').value,
            unidade: (el('item-unidade').value || 'UN').toUpperCase().slice(0, 6),
            ean: digits(el('item-ean').value, 14),
            quantidade: parseBR(el('item-quantidade').value),
            preco: parseBR(el('item-preco').value),
            desconto: parseBR(el('item-desconto').value),
            ncm: digits(el('item-ncm').value, 8),
            cest: digits(el('item-cest').value, 9),
            cfop: digits(el('item-cfop-livre').value, 4) || el('item-cfop').value,
            origem: parseInt(el('item-origem').value, 10) || 0,
            csosn: el('item-csosn').value,
            cst_pis: el('item-cst-pis').value,
            cst_cofins: el('item-cst-cofins').value
        };
    }

    function atualizarTotalModalItem() {
        var q = parseBR(el('item-quantidade').value);
        var p = parseBR(el('item-preco').value);
        var d = parseBR(el('item-desconto').value);
        el('item-total-label').textContent = formatBR(Math.max(0, q * p - d));
    }

    function atualizarResumoFiscal() {
        var it = lerItemDoModal();
        var faltas = pendenciasDoItem(it).filter(function (f) { return f !== 'quantidade'; });
        var chips = [
            '<span class="nfe-item-fiscal-chip' + (digits(it.ncm).length === 8 ? '' : ' is-falta') + '">NCM ' + esc(it.ncm || 'falta') + '</span>',
            '<span class="nfe-item-fiscal-chip">CFOP ' + esc(it.cfop) + '</span>',
            '<span class="nfe-item-fiscal-chip">CSOSN ' + esc(it.csosn) + '</span>',
            '<span class="nfe-item-fiscal-chip">PIS/COFINS ' + esc(it.cst_pis) + '/' + esc(it.cst_cofins) + '</span>'
        ];
        if (faltas.length) {
            chips.unshift('<span class="nfe-item-fiscal-chip is-falta">Incompleto</span>');
        }
        el('item-fiscal-resumo').innerHTML = chips.join('');
    }

    ['item-quantidade', 'item-preco', 'item-desconto'].forEach(function (id) {
        el(id).addEventListener('input', atualizarTotalModalItem);
    });
    ['item-ncm', 'item-cest', 'item-cfop', 'item-cfop-livre', 'item-csosn', 'item-cst-pis', 'item-cst-cofins'].forEach(function (id) {
        el(id).addEventListener('input', atualizarResumoFiscal);
        el(id).addEventListener('change', atualizarResumoFiscal);
    });

    el('btn-abrir-item').addEventListener('click', function () { abrirModalItem(null); });
    el('btn-trocar-produto').addEventListener('click', function () {
        mostrarBusca();
        buscaItem.focus();
    });
    document.querySelectorAll('[data-modal-item-close]').forEach(function (btn) {
        btn.addEventListener('click', fecharModal);
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) fecharModal(); });

    // Busca com navegação por teclado
    function fecharDropdown() {
        dropItem.hidden = true;
        buscaItem.setAttribute('aria-expanded', 'false');
        ativo = -1;
    }

    function marcarAtivo(i) {
        var itensDom = dropItem.querySelectorAll('.autocomplete-item');
        if (!itensDom.length) return;
        ativo = (i + itensDom.length) % itensDom.length;
        itensDom.forEach(function (node, n) {
            node.classList.toggle('active', n === ativo);
            node.setAttribute('aria-selected', n === ativo ? 'true' : 'false');
        });
        itensDom[ativo].scrollIntoView({ block: 'nearest' });
    }

    function renderResultados(q) {
        dropItem.hidden = false;
        buscaItem.setAttribute('aria-expanded', 'true');
        if (!resultados.length) {
            dropItem.innerHTML = '<div class="autocomplete-empty">Nenhum produto encontrado' + (q ? ' para “' + esc(q) + '”' : '') + '.</div>';
            ativo = -1;
            return;
        }
        dropItem.innerHTML = resultados.map(function (p, i) {
            var estoque = p.controla_estoque
                ? '<span class="nfe-ac-estoque' + (parseFloat(p.estoque_atual) > 0 ? '' : ' is-zero') + '">Estoque ' + formatQtd(p.estoque_atual) + '</span>'
                : '<span class="nfe-ac-estoque">' + (parseInt(p.tipo, 10) === 2 ? 'Serviço' : 'Sem controle') + '</span>';
            return '<div class="autocomplete-item" role="option" data-i="' + i + '">' +
                '<div class="ac-item-main"><span class="ac-item-nome">' + esc(p.nome) + '</span>' +
                '<span class="ac-item-preco">' + formatBR(p.preco_venda) + '</span></div>' +
                '<div class="ac-item-meta nfe-ac-meta"><span class="ac-item-codigo">' + esc(p.codigo || 'sem código') +
                (p.ean ? ' · EAN ' + esc(p.ean) : '') + '</span>' + estoque +
                (p.ncm ? '' : '<span class="nfe-ac-falta">sem NCM</span>') + '</div></div>';
        }).join('');
        dropItem.querySelectorAll('.autocomplete-item').forEach(function (node) {
            node.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selecionarProdutoModal(resultados[parseInt(node.dataset.i, 10)]);
            });
        });
        marcarAtivo(0);
    }

    var timeoutProd = null;
    function buscarProdutosModal(q) {
        clearTimeout(timeoutProd);
        timeoutProd = setTimeout(function () {
            var id = ++ultimaBusca;
            fetch(urls.produtos + '?q=' + encodeURIComponent(q || ''), { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (data) {
                    if (id !== ultimaBusca || buscaWrap.hidden) return;
                    resultados = Array.isArray(data) ? data : [];
                    // Leitor de código de barras: código exato com um único resultado já seleciona.
                    if (q && resultados.length === 1 && (resultados[0].ean === q || resultados[0].codigo === q)) {
                        selecionarProdutoModal(resultados[0]);
                        return;
                    }
                    renderResultados(q);
                })
                .catch(function () {
                    resultados = [];
                    renderResultados(q);
                });
        }, 180);
    }

    function selecionarProdutoModal(p) {
        fecharDropdown();
        mostrarProdutoSelecionado(p);
        el('item-unidade').value = p.unidade || 'UN';
        el('item-ean').value = p.ean || '';
        el('item-preco').value = formatInput(p.preco_venda || 0);
        el('item-ncm').value = digits(p.ncm, 8);
        el('item-cest').value = digits(p.cest, 9);
        definirCfop(digits(p.cfop_padrao || defaults.cfop, 4) || defaults.cfop);
        el('item-origem').value = String(p.origem ?? 0);
        selecionarOpcao(el('item-csosn'), digits(p.cst_icms || defaults.csosn, 3) || defaults.csosn);
        selecionarOpcao(el('item-cst-pis'), (digits(p.cst_pis || defaults.cstPis, 2) || defaults.cstPis).padStart(2, '0'));
        selecionarOpcao(el('item-cst-cofins'), (digits(p.cst_cofins || defaults.cstCofins, 2) || defaults.cstCofins).padStart(2, '0'));
        atualizarTotalModalItem();
        atualizarResumoFiscal();
        fiscalBox.open = pendenciasDoItem(lerItemDoModal()).length > 0;
        el('item-quantidade').focus();
        el('item-quantidade').select();
    }

    buscaItem.addEventListener('input', function () { buscarProdutosModal(this.value.trim()); });
    buscaItem.addEventListener('focus', function () { buscarProdutosModal(this.value.trim()); });
    buscaItem.addEventListener('blur', function () { setTimeout(fecharDropdown, 150); });
    buscaItem.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); if (dropItem.hidden) buscarProdutosModal(this.value.trim()); else marcarAtivo(ativo + 1); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); marcarAtivo(ativo - 1); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            if (!dropItem.hidden && resultados[ativo]) selecionarProdutoModal(resultados[ativo]);
        } else if (e.key === 'Escape' && !dropItem.hidden) {
            e.preventDefault();
            e.stopPropagation();
            fecharDropdown();
        }
    });

    // Enter nos campos do item salva; Esc fecha o modal
    modal.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { e.preventDefault(); fecharModal(); return; }
        if (e.key === 'Enter' && e.target !== buscaItem && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'SELECT') {
            e.preventDefault();
            salvarItem(false);
        }
    });

    function salvarItem(adicionarOutro) {
        limparInvalidos(modal);
        var item = lerItemDoModal();

        if (!item.produto_id) {
            mostrarAviso('Selecione um produto.', { modal: true });
            mostrarBusca();
            marcarInvalido(buscaItem);
            return;
        }
        if (!(item.quantidade > 0)) {
            mostrarAviso('Informe uma quantidade maior que zero.', { modal: true });
            marcarInvalido(el('item-quantidade'));
            return;
        }
        if (item.desconto > item.quantidade * item.preco) {
            mostrarAviso('O desconto do item é maior que o valor do item.', { modal: true });
            marcarInvalido(el('item-desconto'));
            return;
        }
        if (item.ncm && item.ncm.length !== 8) {
            mostrarAviso('O NCM deve ter 8 dígitos (ou deixe em branco para completar depois).', { modal: true });
            fiscalBox.open = true;
            marcarInvalido(el('item-ncm'));
            return;
        }
        if (item.cfop.length !== 4) {
            mostrarAviso('Informe um CFOP com 4 dígitos.', { modal: true });
            fiscalBox.open = true;
            marcarInvalido(el('item-cfop-livre'));
            return;
        }

        if (editIndex === null) itens.push(item);
        else itens[editIndex] = item;

        limparAviso(true);
        limparAviso(false);
        renderItens();

        if (adicionarOutro) {
            abrirModalItem(null);
        } else {
            fecharModal();
        }
    }

    el('btn-salvar-item').addEventListener('click', function () { salvarItem(false); });
    el('btn-salvar-item-novo').addEventListener('click', function () { salvarItem(true); });

    // Insert abre o modal na etapa de itens
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Insert' && passo === 2 && !modalAberto()) {
            e.preventDefault();
            abrirModalItem(null);
        }
    });

    el('input-desconto').addEventListener('input', atualizarTotais);
    el('input-acrescimo').addEventListener('input', atualizarTotais);

    // ---------- Envio ----------
    var inputAcao = el('input-acao');

    el('btn-rascunho').addEventListener('click', function () { inputAcao.value = 'rascunho'; });

    el('btn-emitir').addEventListener('click', function () {
        if (!podeEmitir) return;
        irPara(3);
        var pendencias = renderPendencias();
        if (pendencias.length) {
            mostrarAviso('A nota ainda tem ' + pendencias.length + ' pendência(s). Corrija ou salve como rascunho.');
            return;
        }
        inputAcao.value = 'emitir';
        el('btn-emitir-confirmar').click();
    });

    el('form-nfe').addEventListener('submit', function (e) {
        var acao = (e.submitter && e.submitter.dataset.acao) || inputAcao.value;
        inputAcao.value = acao;

        if (!itens.length) {
            e.preventDefault();
            mostrarAviso('Adicione pelo menos um item antes de salvar.');
            irPara(2);
            return;
        }
        if (acao === 'emitir' && listarPendencias().length) {
            e.preventDefault();
            irPara(3);
            renderPendencias();
        }
    });

    renderItens();
    irPara(@json($errors->has('itens') || $errors->has('itens.*') ? 2 : ($errors->has('pendencias') || $errors->has('pendencias.*') ? 3 : 1)));
});
</script>
@endpush
