@extends('layouts.app')

@section('title', 'Nova NF-e')
@section('page_title', 'Nova NF-e')

@section('breadcrumb')
    <a href="{{ route('nfe.index') }}">NF-e Emitidas</a> / Nova NF-e
@endsection

@section('content')

<div class="nfe-wizard">
<div class="nfe-alerts">
    @if (count($alertas))
        <div class="alert alert--error">
            <strong>Pendências para emitir</strong>
            <ul class="nfe-alerts__list">
                @foreach ($alertas as $alerta)
                    <li>{{ $alerta }}</li>
                @endforeach
            </ul>
            <a href="{{ route('empresa.edit', $empresa) }}" class="btn btn--ghost btn--sm" style="margin-top:0.5rem;">
                Abrir cadastro da empresa
            </a>
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
    <button type="button" class="nfe-steps__item" data-step-btn="2" @disabled(! $podeEmitir)>
        <span class="nfe-steps__num">2</span>
        <span class="nfe-steps__label">Itens</span>
    </button>
    <button type="button" class="nfe-steps__item" data-step-btn="3" @disabled(! $podeEmitir)>
        <span class="nfe-steps__num">3</span>
        <span class="nfe-steps__label">Revisão</span>
    </button>
</nav>

<form method="POST" action="{{ route('nfe.store') }}" id="form-nfe">
    @csrf
    <input type="hidden" name="situacao" value="confirmada">
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
                            value="{{ str_pad((string) ($empresa->nfe_sequencia ?? 1), 9, '0', STR_PAD_LEFT) }}" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Data de emissão</label>
                        <input type="date" name="data_venda" class="form-control"
                            value="{{ old('data_venda', date('Y-m-d')) }}" @disabled(! $podeEmitir)>
                        @error('data_venda')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="form-grid form-grid--col-3">
                    <div class="form-group form-group--span-2">
                        <label class="form-label form-label--required">Natureza da operação</label>
                        <input type="text" name="natureza_operacao" class="form-control" maxlength="60"
                            value="{{ old('natureza_operacao', 'VENDA DE MERCADORIA') }}"
                            @disabled(! $podeEmitir)>
                        @error('natureza_operacao')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Forma de pagamento</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-forma-pagamento" class="form-control"
                                placeholder="Buscar forma..." autocomplete="off"
                                value="{{ old('forma_pagamento') }}" @disabled(! $podeEmitir)>
                            <input type="hidden" name="forma_pagamento" id="hidden-forma-pagamento"
                                value="{{ old('forma_pagamento') }}">
                        </div>
                        @error('forma_pagamento')<span class="form-error">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group form-group--span-2">
                        <label class="form-label">Destinatário</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-cliente" class="form-control"
                                placeholder="Consumidor Final" autocomplete="off" @disabled(! $podeEmitir)>
                            <input type="hidden" name="cliente_id" id="hidden-cliente-id" value="{{ old('cliente_id') }}">
                        </div>
                        <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.4rem;flex-wrap:wrap;">
                            <button type="button" class="btn btn--ghost btn--sm" id="btn-novo-cliente"
                                @disabled(! $podeEmitir)>+ Novo cliente</button>
                            <small class="text-muted" id="cliente-doc-hint"></small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vendedor</label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="input-vendedor" class="form-control"
                                placeholder="Opcional" autocomplete="off" @disabled(! $podeEmitir)>
                            <input type="hidden" name="funcionario_id" id="hidden-funcionario-id"
                                value="{{ old('funcionario_id') }}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Consumidor final</label>
                        <select name="ind_final" id="ind_final" class="form-control" @disabled(! $podeEmitir)>
                            <option value="">Automático (CPF=sim / CNPJ=não)</option>
                            <option value="1" {{ old('ind_final') === '1' ? 'selected' : '' }}>Sim (1)</option>
                            <option value="0" {{ old('ind_final') === '0' ? 'selected' : '' }}>Não (0)</option>
                        </select>
                    </div>
                    <div class="form-group form-group--span-2">
                        <label class="form-label">Modalidade do frete</label>
                        <select name="mod_frete" class="form-control" @disabled(! $podeEmitir)>
                            @foreach([
                                9 => '9 — Sem frete',
                                0 => '0 — Por conta do remetente',
                                1 => '1 — Por conta do destinatário',
                                2 => '2 — Por conta de terceiros',
                                3 => '3 — Próprio remetente',
                                4 => '4 — Próprio destinatário',
                            ] as $valor => $label)
                                <option value="{{ $valor }}" {{ (int) old('mod_frete', 9) === $valor ? 'selected' : '' }}>
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
                <button type="button" class="btn btn--primary btn--sm" id="btn-abrir-item" @disabled(! $podeEmitir)>
                    + Adicionar item
                </button>
            </div>
            <div class="card__body" style="padding:0;">
                @error('itens')
                    <div class="alert alert--error" style="margin:1rem;">{{ $message }}</div>
                @enderror
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
                        <tbody id="itens-body">
                            <tr id="itens-empty-row">
                                <td colspan="5" class="text-center text-muted empty-state">
                                    Nenhum item. Toque em “Adicionar item”.
                                </td>
                            </tr>
                        </tbody>
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
                                value="{{ old('desconto', '0,00') }}" @disabled(! $podeEmitir)>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Acréscimo</label>
                        <div class="input-prefix-wrap">
                            <span class="input-prefix">R$</span>
                            <input type="tel" name="acrescimo" id="input-acrescimo"
                                class="form-control text-right input-numeric"
                                data-decimals="2" inputmode="numeric"
                                value="{{ old('acrescimo', '0,00') }}" @disabled(! $podeEmitir)>
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
                        placeholder="Texto de interesse do contribuinte..."
                        @disabled(! $podeEmitir)>{{ old('observacoes') }}</textarea>
                </div>
                <div class="form-group" style="margin-top:1rem;">
                    <label class="form-label">Informações ao fisco (infAdFisco)</label>
                    <textarea name="inf_ad_fisco" class="form-control" rows="2" maxlength="2000"
                        placeholder="Opcional"
                        @disabled(! $podeEmitir)>{{ old('inf_ad_fisco') }}</textarea>
                </div>

                <div class="alert alert--warning" style="margin-top:1rem;">
                    Ao emitir, o sistema confirma a venda (estoque) e envia a NF-e à SEFAZ.
                </div>
            </div>
        </div>
    </section>

    <div class="nfe-wizard-actions">
        <button type="button" class="btn btn--ghost" id="btn-voltar" hidden>Voltar</button>
        <div class="nfe-wizard-actions__right">
            <a href="{{ route('nfe.index') }}" class="btn btn--ghost">Cancelar</a>
            <button type="button" class="btn btn--primary" id="btn-avancar" @disabled(! $podeEmitir)>Continuar</button>
            <button type="submit" class="btn btn--primary" id="btn-emitir" hidden @disabled(! $podeEmitir)
                data-confirm="Confirmar emissão da NF-e para a SEFAZ?"
                data-confirm-title="Emitir NF-e"
                data-confirm-ok="Emitir agora">
                Emitir NF-e
            </button>
        </div>
    </div>
</form>
</div>{{-- /.nfe-wizard --}}

{{-- Modal item + impostos --}}
<div class="modal-overlay" id="modal-item" hidden>
    <div class="modal-box modal-box--wide">
        <div class="modal-box__header">
            <span class="card__title" id="modal-item-titulo">Adicionar item</span>
            <button type="button" class="btn btn--ghost btn--sm" data-modal-item-close>&times;</button>
        </div>
        <div class="modal-box__body">
            <div class="alert alert--error" id="modal-item-aviso" hidden role="alert" style="margin-bottom:0.85rem;"></div>
            <div class="nfe-modal-tabs">
                <button type="button" class="nfe-modal-tabs__btn is-active" data-item-tab="produto">Produto</button>
                <button type="button" class="nfe-modal-tabs__btn" data-item-tab="valores">Valores</button>
                <button type="button" class="nfe-modal-tabs__btn" data-item-tab="impostos">Impostos</button>
            </div>

            <div class="nfe-modal-pane is-active" data-item-panel="produto">
                <div class="form-group">
                    <label class="form-label form-label--required">Buscar produto</label>
                    <input type="text" id="busca-produto-item" class="form-control"
                        placeholder="Nome ou código" autocomplete="off" style="font-size:16px;">
                    <div id="dropdown-produto-item" class="autocomplete-dropdown" style="display:none;"></div>
                </div>
                <input type="hidden" id="item-produto-id">
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label">Nome</label>
                        <input type="text" id="item-nome" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Código</label>
                        <input type="text" id="item-codigo" class="form-control" readonly>
                    </div>
                </div>
                <div class="form-grid form-grid--col-2" style="margin-top:0.75rem;">
                    <div class="form-group">
                        <label class="form-label form-label--required">Unidade</label>
                        <input type="text" id="item-unidade" class="form-control" maxlength="6" value="UN">
                    </div>
                    <div class="form-group">
                        <label class="form-label">EAN / GTIN</label>
                        <input type="text" id="item-ean" class="form-control" maxlength="14"
                            placeholder="Vazio = SEM GTIN">
                    </div>
                </div>
            </div>

            <div class="nfe-modal-pane" data-item-panel="valores" hidden>
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label form-label--required">Quantidade</label>
                        <input type="tel" id="item-quantidade" class="form-control input-numeric"
                            data-decimals="2" inputmode="numeric" value="1,00" style="font-size:1.25rem;">
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">Preço unitário</label>
                        <input type="tel" id="item-preco" class="form-control input-numeric"
                            data-decimals="2" inputmode="numeric" value="0,00" style="font-size:1.25rem;">
                    </div>
                </div>
                <div class="form-group" style="margin-top:0.75rem;">
                    <label class="form-label">Desconto do item</label>
                    <input type="tel" id="item-desconto" class="form-control input-numeric"
                        data-decimals="2" inputmode="numeric" value="0,00">
                </div>
                <div class="modal-qtd-total" style="margin-top:1rem;">
                    <span>Total do item</span>
                    <strong id="item-total-label">R$ 0,00</strong>
                </div>
            </div>

            <div class="nfe-modal-pane" data-item-panel="impostos" hidden>
                <div class="form-grid form-grid--col-2">
                    <div class="form-group">
                        <label class="form-label form-label--required">NCM</label>
                        <input type="tel" id="item-ncm" class="form-control" maxlength="8" inputmode="numeric"
                            placeholder="00000000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">CEST</label>
                        <input type="tel" id="item-cest" class="form-control" maxlength="9" inputmode="numeric">
                    </div>
                </div>
                <div class="form-grid form-grid--col-2" style="margin-top:0.75rem;">
                    <div class="form-group">
                        <label class="form-label form-label--required">CFOP</label>
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
                        <label class="form-label form-label--required">Origem</label>
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
                        <label class="form-label form-label--required">CSOSN (ICMS)</label>
                        <select id="item-csosn" class="form-control">
                            <option value="102">102 — Tributada sem crédito</option>
                            <option value="103">103 — Isenção</option>
                            <option value="300">300 — Imune</option>
                            <option value="400">400 — Não tributada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label form-label--required">CST PIS</label>
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
                        <label class="form-label form-label--required">CST COFINS</label>
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
                    Simples Nacional: CSOSN 102/103/300/400 e PIS/COFINS isentos (CST 07) cobrem o caso mais comum.
                </p>
            </div>
        </div>
        <div class="modal-box__footer">
            <button type="button" class="btn btn--ghost" data-modal-item-close>Cancelar</button>
            <button type="button" class="btn btn--primary" id="btn-salvar-item">Salvar item</button>
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

    var passo = 1;
    var itens = [];
    var editIndex = null;
    var produtoSelecionado = null;
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

    if (podeEmitir && typeof initAutocomplete === 'function') {
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

    function irPara(n) {
        passo = n;
        limparAviso(false);
        limparInvalidos(document.getElementById('form-nfe'));
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
        btn.addEventListener('click', function () {
            if (!podeEmitir) return;
            var dest = parseInt(btn.dataset.stepBtn, 10);
            if (dest > passo && !validarPasso(passo)) return;
            irPara(dest);
        });
    });

    document.getElementById('btn-voltar').addEventListener('click', function () {
        if (passo > 1) irPara(passo - 1);
    });
    document.getElementById('btn-avancar').addEventListener('click', function () {
        if (!validarPasso(passo)) return;
        irPara(passo + 1);
    });

    function validarPasso(n) {
        limparInvalidos(document.getElementById('form-nfe'));
        if (n === 1) {
            var natureza = document.querySelector('[name="natureza_operacao"]');
            if (!natureza.value.trim()) {
                mostrarAviso('Informe a natureza da operação.');
                marcarInvalido(natureza);
                return false;
            }
            if (!document.getElementById('hidden-forma-pagamento').value) {
                mostrarAviso('Selecione a forma de pagamento.');
                marcarInvalido(document.getElementById('input-forma-pagamento'));
                return false;
            }
            limparAviso(false);
            return true;
        }
        if (n === 2) {
            if (!itens.length) {
                mostrarAviso('Adicione pelo menos um item à nota.');
                return false;
            }
            for (var i = 0; i < itens.length; i++) {
                if (!digits(itens[i].ncm, 8) || digits(itens[i].ncm, 8).length !== 8) {
                    mostrarAviso('Item "' + itens[i].nome + '" sem NCM válido (8 dígitos).');
                    return false;
                }
            }
            limparAviso(false);
            return true;
        }
        return true;
    }

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
    }

    function renderItens() {
        var tbody = document.getElementById('itens-body');
        var hidden = document.getElementById('itens-hidden');
        tbody.innerHTML = '';
        hidden.innerHTML = '';

        if (!itens.length) {
            tbody.innerHTML = '<tr id="itens-empty-row"><td colspan="5" class="text-center text-muted empty-state">Nenhum item. Toque em “Adicionar item”.</td></tr>';
            atualizarTotais();
            return;
        }

        itens.forEach(function (it, idx) {
            var tr = document.createElement('tr');
            tr.innerHTML =
                '<td data-label="Produto"><strong>' + it.nome + '</strong>' +
                    '<div class="text-muted" style="font-size:11px;">' + (it.codigo || '-') + ' · ' + it.unidade + '</div></td>' +
                '<td data-label="Fiscal"><span class="badge badge--neutral">NCM ' + it.ncm + '</span> ' +
                    '<span class="badge badge--neutral">CFOP ' + it.cfop + '</span> ' +
                    '<span class="badge badge--neutral">CSOSN ' + it.csosn + '</span></td>' +
                '<td data-label="Qtd" class="col-num">' + formatInput(it.quantidade) + '</td>' +
                '<td data-label="Total" class="col-num">' + formatBR(totalItem(it)) + '</td>' +
                '<td data-label="Ações" class="col-actions">' +
                    '<button type="button" class="btn btn--ghost btn--sm" data-edit="' + idx + '">Editar</button>' +
                    '<button type="button" class="btn btn--danger btn--sm btn--icon" data-del="' + idx + '" title="Remover">✕</button>' +
                '</td>';
            tbody.appendChild(tr);

            var fields = {
                produto_id: it.produto_id,
                quantidade: formatInput(it.quantidade),
                preco_unitario: it.preco.toFixed(2),
                desconto: (it.desconto || 0).toFixed(2),
                ncm: it.ncm,
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

    // Modal item
    var modal = document.getElementById('modal-item');
    var buscaItem = document.getElementById('busca-produto-item');
    var dropItem = document.getElementById('dropdown-produto-item');

    function mostrarTabItem(nome) {
        document.querySelectorAll('[data-item-tab]').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.itemTab === nome);
        });
        document.querySelectorAll('[data-item-panel]').forEach(function (p) {
            var ativo = p.dataset.itemPanel === nome;
            p.hidden = !ativo;
            p.classList.toggle('is-active', ativo);
        });
    }

    document.querySelectorAll('[data-item-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () { mostrarTabItem(btn.dataset.itemTab); });
    });

    function abrirModalItem(idx) {
        editIndex = (typeof idx === 'number') ? idx : null;
        document.getElementById('modal-item-titulo').textContent = editIndex === null ? 'Adicionar item' : 'Editar item';
        limparAviso(true);
        limparInvalidos(modal);
        mostrarTabItem('produto');
        produtoSelecionado = null;

        if (editIndex !== null) {
            var it = itens[editIndex];
            preencherModal(it);
            document.getElementById('item-produto-id').value = it.produto_id;
            document.getElementById('item-nome').value = it.nome;
            document.getElementById('item-codigo').value = it.codigo || '';
            buscaItem.value = it.nome;
        } else {
            limparModal();
            buscaItem.value = '';
        }
        modal.hidden = false;
        setTimeout(function () { buscaItem.focus(); }, 80);
    }

    function limparModal() {
        document.getElementById('item-produto-id').value = '';
        document.getElementById('item-nome').value = '';
        document.getElementById('item-codigo').value = '';
        document.getElementById('item-unidade').value = 'UN';
        document.getElementById('item-ean').value = '';
        document.getElementById('item-quantidade').value = '1,00';
        document.getElementById('item-preco').value = '0,00';
        document.getElementById('item-desconto').value = '0,00';
        document.getElementById('item-ncm').value = '';
        document.getElementById('item-cest').value = '';
        document.getElementById('item-cfop').value = defaults.cfop;
        document.getElementById('item-cfop-livre').value = '';
        document.getElementById('item-origem').value = '0';
        document.getElementById('item-csosn').value = defaults.csosn;
        document.getElementById('item-cst-pis').value = defaults.cstPis;
        document.getElementById('item-cst-cofins').value = defaults.cstCofins;
        atualizarTotalModalItem();
    }

    function preencherModal(it) {
        document.getElementById('item-unidade').value = it.unidade || 'UN';
        document.getElementById('item-ean').value = it.ean || '';
        document.getElementById('item-quantidade').value = formatInput(it.quantidade);
        document.getElementById('item-preco').value = formatInput(it.preco);
        document.getElementById('item-desconto').value = formatInput(it.desconto || 0);
        document.getElementById('item-ncm').value = it.ncm || '';
        document.getElementById('item-cest').value = it.cest || '';
        document.getElementById('item-cfop').value = ['5101','5102','5405','5656','5910'].indexOf(it.cfop) >= 0 ? it.cfop : '5102';
        document.getElementById('item-cfop-livre').value = ['5101','5102','5405','5656','5910'].indexOf(it.cfop) >= 0 ? '' : it.cfop;
        document.getElementById('item-origem').value = String(it.origem ?? 0);
        document.getElementById('item-csosn').value = it.csosn || defaults.csosn;
        document.getElementById('item-cst-pis').value = it.cst_pis || defaults.cstPis;
        document.getElementById('item-cst-cofins').value = it.cst_cofins || defaults.cstCofins;
        atualizarTotalModalItem();
    }

    function atualizarTotalModalItem() {
        var q = parseBR(document.getElementById('item-quantidade').value);
        var p = parseBR(document.getElementById('item-preco').value);
        var d = parseBR(document.getElementById('item-desconto').value);
        document.getElementById('item-total-label').textContent = formatBR(Math.max(0, q * p - d));
    }

    ['item-quantidade', 'item-preco', 'item-desconto'].forEach(function (id) {
        document.getElementById(id).addEventListener('input', atualizarTotalModalItem);
    });

    document.getElementById('btn-abrir-item').addEventListener('click', function () { abrirModalItem(null); });
    document.querySelectorAll('[data-modal-item-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { modal.hidden = true; });
    });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });

    var timeoutProd = null;
    function buscarProdutosModal(q) {
        clearTimeout(timeoutProd);
        timeoutProd = setTimeout(function () {
            fetch(urls.produtos + '?q=' + encodeURIComponent(q || ''))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (!data.length) {
                        dropItem.style.display = 'block';
                        dropItem.innerHTML = '<div class="autocomplete-empty">Nenhum produto</div>';
                        return;
                    }
                    dropItem.style.display = 'block';
                    dropItem.innerHTML = data.map(function (p, i) {
                        return '<div class="autocomplete-item" data-i="' + i + '">' +
                            '<div class="ac-item-main"><span class="ac-item-nome">' + p.nome + '</span>' +
                            '<span class="ac-item-preco">' + formatBR(p.preco_venda) + '</span></div>' +
                            '<div class="ac-item-meta"><span class="ac-item-codigo">' + (p.codigo || '-') +
                            (p.ncm ? ' · NCM ' + p.ncm : ' · Sem NCM') + '</span></div></div>';
                    }).join('');
                    dropItem.querySelectorAll('.autocomplete-item').forEach(function (el) {
                        el.addEventListener('click', function () {
                            selecionarProdutoModal(data[parseInt(el.dataset.i, 10)]);
                        });
                    });
                });
        }, 150);
    }

    function selecionarProdutoModal(p) {
        produtoSelecionado = p;
        dropItem.style.display = 'none';
        buscaItem.value = p.nome;
        document.getElementById('item-produto-id').value = p.id;
        document.getElementById('item-nome').value = p.nome;
        document.getElementById('item-codigo').value = p.codigo || '';
        document.getElementById('item-unidade').value = p.unidade || 'UN';
        document.getElementById('item-ean').value = p.ean || '';
        document.getElementById('item-preco').value = formatInput(p.preco_venda || 0);
        document.getElementById('item-ncm').value = digits(p.ncm, 8);
        document.getElementById('item-cest').value = digits(p.cest, 9);
        var cfop = digits(p.cfop_padrao || defaults.cfop, 4) || defaults.cfop;
        if (['5101','5102','5405','5656','5910'].indexOf(cfop) >= 0) {
            document.getElementById('item-cfop').value = cfop;
            document.getElementById('item-cfop-livre').value = '';
        } else {
            document.getElementById('item-cfop-livre').value = cfop;
        }
        document.getElementById('item-origem').value = String(p.origem ?? 0);
        var csosn = digits(p.cst_icms || defaults.csosn, 3) || defaults.csosn;
        if (['102','103','300','400'].indexOf(csosn) >= 0) {
            document.getElementById('item-csosn').value = csosn;
        }
        var pis = (digits(p.cst_pis || defaults.cstPis, 2) || defaults.cstPis).padStart(2, '0');
        var cof = (digits(p.cst_cofins || defaults.cstCofins, 2) || defaults.cstCofins).padStart(2, '0');
        document.getElementById('item-cst-pis').value = pis;
        document.getElementById('item-cst-cofins').value = cof;
        atualizarTotalModalItem();
        mostrarTabItem('valores');
    }

    buscaItem.addEventListener('input', function () { buscarProdutosModal(this.value.trim()); });
    buscaItem.addEventListener('focus', function () { buscarProdutosModal(this.value.trim()); });
    buscaItem.addEventListener('blur', function () { setTimeout(function () { dropItem.style.display = 'none'; }, 200); });

    document.getElementById('btn-salvar-item').addEventListener('click', function () {
        limparInvalidos(modal);
        var produtoId = document.getElementById('item-produto-id').value;
        if (!produtoId) {
            mostrarAviso('Selecione um produto.', { modal: true });
            mostrarTabItem('produto');
            marcarInvalido(document.getElementById('busca-produto-item'));
            return;
        }
        var ncm = digits(document.getElementById('item-ncm').value, 8);
        if (ncm.length !== 8) {
            mostrarAviso('Informe o NCM com 8 dígitos.', { modal: true });
            mostrarTabItem('impostos');
            marcarInvalido(document.getElementById('item-ncm'));
            return;
        }
        var cfopLivre = digits(document.getElementById('item-cfop-livre').value, 4);
        var cfop = cfopLivre || document.getElementById('item-cfop').value;
        if (cfop.length !== 4) {
            mostrarAviso('Informe um CFOP válido.', { modal: true });
            mostrarTabItem('impostos');
            marcarInvalido(cfopLivre ? document.getElementById('item-cfop-livre') : document.getElementById('item-cfop'));
            return;
        }
        var qtd = parseBR(document.getElementById('item-quantidade').value);
        var preco = parseBR(document.getElementById('item-preco').value);
        if (qtd <= 0) {
            mostrarAviso('Informe uma quantidade válida.', { modal: true });
            mostrarTabItem('valores');
            marcarInvalido(document.getElementById('item-quantidade'));
            return;
        }

        var item = {
            produto_id: parseInt(produtoId, 10),
            nome: document.getElementById('item-nome').value,
            codigo: document.getElementById('item-codigo').value,
            unidade: (document.getElementById('item-unidade').value || 'UN').toUpperCase().slice(0, 6),
            ean: digits(document.getElementById('item-ean').value, 14),
            quantidade: qtd,
            preco: preco,
            desconto: parseBR(document.getElementById('item-desconto').value),
            ncm: ncm,
            cest: digits(document.getElementById('item-cest').value, 9),
            cfop: cfop,
            origem: parseInt(document.getElementById('item-origem').value, 10) || 0,
            csosn: document.getElementById('item-csosn').value,
            cst_pis: document.getElementById('item-cst-pis').value,
            cst_cofins: document.getElementById('item-cst-cofins').value
        };

        if (editIndex === null) itens.push(item);
        else itens[editIndex] = item;

        limparAviso(true);
        limparAviso(false);
        modal.hidden = true;
        renderItens();
    });

    document.getElementById('input-desconto').addEventListener('input', atualizarTotais);
    document.getElementById('input-acrescimo').addEventListener('input', atualizarTotais);

    document.getElementById('form-nfe').addEventListener('submit', function (e) {
        if (!validarPasso(1) || !validarPasso(2)) {
            e.preventDefault();
            if (!validarPasso(1)) irPara(1);
            else irPara(2);
        }
    });

    renderItens();
    irPara(1);
});
</script>
@endpush
