@extends('layouts.app')

@section('title', 'PDV')
@section('page_title', 'PDV — Ponto de Venda')

@section('page_actions')
    <a href="{{ route('venda.index') }}" class="btn btn--ghost btn--sm">← Vendas</a>
@endsection

@push('styles')
<script>document.documentElement.classList.add('pdv-page');</script>
@endpush

@section('content')
<script>document.body.classList.add('pdv-page');</script>

    <div class="pdv-shell" id="pdv-shell">
    <div class="pdv-tela-pequena" id="pdv-tela-pequena" hidden>
        <div class="pdv-tela-pequena__card">
            <div class="pdv-tela-pequena__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.6">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                    <path d="M8 21h8"></path>
                    <path d="M12 17v4"></path>
                </svg>
            </div>
            <h2 class="pdv-tela-pequena__titulo">Tela muito pequena para o PDV</h2>
            <p class="pdv-tela-pequena__texto">
                O ponto de venda precisa de um monitor de computador com pelo menos
                <strong>1024&nbsp;px</strong> de largura para funcionar corretamente.
            </p>
            <p class="pdv-tela-pequena__meta">Largura atual: <strong id="pdv-largura-atual">—</strong></p>
            <div class="pdv-tela-pequena__acoes">
                <a href="{{ route('venda.index') }}" class="btn btn--primary pdv-tela-pequena__btn">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M19 12H5"></path>
                        <polyline points="12 19 5 12 12 5"></polyline>
                    </svg>
                    Voltar para Vendas
                </a>
                <a href="{{ route('venda.create') }}" class="btn btn--ghost pdv-tela-pequena__btn pdv-tela-pequena__btn--sec">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="12" y1="18" x2="12" y2="12"></line>
                        <line x1="9" y1="15" x2="15" y2="15"></line>
                    </svg>
                    Abrir venda pelo formulário
                </a>
            </div>
        </div>
    </div>

    <div class="pdv-wrap" id="pdv-wrap">
        <div class="pdv-alert-erro alert alert--error" id="pdv-alert-erro" role="alert">
            <span id="pdv-alert-erro-msg"></span>
            <button type="button" class="btn btn--ghost btn--sm" id="pdv-alert-erro-fechar" aria-label="Fechar">✕</button>
        </div>

        <div class="pdv-esquerda">
            <div class="pdv-busca">
                <div class="pdv-busca__input-wrap">
                    <svg class="pdv-busca__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="11" cy="11" r="7"></circle>
                        <path d="m20 20-3.5-3.5"></path>
                    </svg>
                    <input type="search"
                           id="pdv-input-busca"
                           class="form-control pdv-input-busca"
                           placeholder="Nome, código ou bipar código de barras…"
                           autocomplete="off"
                           autofocus
                           enterkeyhint="done">
                    <div id="pdv-sugestoes" class="pdv-sugestoes" hidden></div>
                </div>
                <div class="pdv-atalhos" aria-label="Atalhos do teclado">
                    <span class="pdv-atalho"><kbd class="pdv-kbd">Enter</kbd> adicionar</span>
                    <span class="pdv-atalho pdv-atalho--desktop"><kbd class="pdv-kbd">↑</kbd><kbd class="pdv-kbd">↓</kbd> navegar</span>
                    <span class="pdv-atalho"><kbd class="pdv-kbd">F2</kbd> buscar</span>
                    <span class="pdv-atalho"><kbd class="pdv-kbd">3*</kbd> quantidade</span>
                    <span class="pdv-atalho"><kbd class="pdv-kbd">F4</kbd> pagamento</span>
                    <span class="pdv-atalho pdv-atalho--desktop"><kbd class="pdv-kbd">Esc</kbd> limpar</span>
                    <span class="pdv-atalho pdv-atalho--desktop"><kbd class="pdv-kbd">F1</kbd> todos</span>
                </div>
                <p class="pdv-busca__hint">Bipe ou cole o código e pressione Enter — adiciona 1 unidade automaticamente.</p>
            </div>

            <div class="pdv-itens-header">
                <span class="pdv-itens-header__titulo">Itens</span>
                <span class="pdv-itens-header__count" id="pdv-itens-count">0</span>
            </div>

            <div id="pdv-itens" class="pdv-itens"></div>
            <div id="pdv-empty" class="pdv-empty">
                <div class="pdv-empty__icon" aria-hidden="true">⌁</div>
                <p class="pdv-empty__titulo">Pronto para vender</p>
                <p class="pdv-empty__texto">Busque pelo nome ou bipe o código de barras.</p>
            </div>
        </div>

        <aside class="pdv-direita">
            <div class="pdv-painel">
                <div class="pdv-totais pdv-totais--resumo">
                    <div class="pdv-totais__linha">
                        <span>Subtotal</span>
                        <strong id="pdv-display-subtotal">R$ 0,00</strong>
                    </div>
                    <div class="pdv-totais__total">
                        <span class="pdv-totais__total-label">Total</span>
                        <strong class="pdv-total-grande" id="pdv-display-total">R$ 0,00</strong>
                    </div>
                </div>

                <div class="pdv-cliente form-group">
                    <label class="form-label" for="pdv-input-cliente">
                        Cliente <span class="pdv-label-opcional">opcional · <kbd class="pdv-kbd">F3</kbd></span>
                    </label>
                    <div class="autocomplete-wrap">
                        <input type="text" id="pdv-input-cliente" class="form-control pdv-input-soft"
                               placeholder="Consumidor Final" autocomplete="off">
                        <input type="hidden" id="pdv-hidden-cliente-id" value="">
                    </div>
                </div>

                <div class="pdv-resumo-forma">
                    <span class="text-muted">Pagamento</span>
                    <strong id="pdv-resumo-forma">{{ $formasPagamento->first()->nome ?? 'Nenhuma forma ativa' }}</strong>
                </div>

                <div class="pdv-btn-finalizar-fixed">
                    <button type="button" class="pdv-btn-finalizar" id="pdv-btn-finalizar">
                        <span class="pdv-btn-finalizar__textos">
                            <span class="pdv-btn-finalizar__label">Pagamento</span>
                            <span class="pdv-btn-finalizar__forma">revisar e finalizar</span>
                        </span>
                        <span class="pdv-btn-finalizar__valor" id="pdv-btn-finalizar-valor">R$ 0,00</span>
                        <kbd class="pdv-kbd pdv-kbd--on-dark pdv-btn-finalizar__kbd">F4</kbd>
                    </button>
                </div>

                <button type="button" class="pdv-link-atalhos" id="pdv-btn-ajuda">
                    Atalhos do teclado <kbd class="pdv-kbd">F1</kbd>
                </button>
            </div>
        </aside>
    </div>

    <div class="pdv-pag-overlay" id="pdv-pagamento" hidden>
        <div class="pdv-pag" role="dialog" aria-modal="true" aria-labelledby="pdv-pag-titulo">
            <div class="pdv-pag__header">
                <h2 class="pdv-pag__titulo" id="pdv-pag-titulo">Pagamento</h2>
                <button type="button" class="btn btn--ghost btn--sm" data-pag-fechar>Voltar <kbd class="pdv-kbd">Esc</kbd></button>
            </div>

            <div class="pdv-alert-erro alert alert--error" id="pdv-pag-erro" role="alert"></div>

            <div class="pdv-pag__corpo">
                <div class="pdv-pag__formas">
                    <div class="pdv-pagamento__topo">
                        <span class="form-label">Forma de pagamento</span>
                        <span class="pdv-pagamento__atalho"><kbd class="pdv-kbd">1</kbd>–<kbd class="pdv-kbd">9</kbd> ou <kbd class="pdv-kbd">↑</kbd><kbd class="pdv-kbd">↓</kbd></span>
                    </div>
                    @if($formasPagamento->isEmpty())
                        <p class="pdv-pagamento__vazio">Nenhuma forma de pagamento ativa. Peça ao administrador para cadastrar.</p>
                    @else
                        <div class="pdv-formas-lista" id="pdv-formas-grid" role="radiogroup" aria-label="Forma de pagamento">
                            @foreach($formasPagamento as $i => $forma)
                                <button type="button"
                                        class="pdv-forma-btn{{ $forma->isPrazo() ? ' pdv-forma-btn--prazo' : '' }}{{ $i === 0 ? ' pdv-forma-btn--ativa' : '' }}"
                                        data-forma="{{ $forma->nome }}"
                                        data-tipo="{{ $forma->isPrazo() ? 'A prazo' : 'À vista' }}"
                                        data-prazo="{{ $forma->isPrazo() ? '1' : '0' }}"
                                        role="radio"
                                        aria-checked="{{ $i === 0 ? 'true' : 'false' }}"
                                        aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">
                                    @if ($i < 9)
                                        <kbd class="pdv-kbd pdv-forma-btn__tecla">{{ $i + 1 }}</kbd>
                                    @endif
                                    <span class="pdv-forma-btn__nome">{{ $forma->nome }}</span>
                                    <span class="pdv-forma-btn__tipo">{{ $forma->isPrazo() ? 'A prazo' : 'À vista' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <input type="hidden" id="pdv-hidden-forma-pagamento"
                           value="{{ $formasPagamento->first()->nome ?? '' }}">
                    <span class="form-error" id="pdv-forma-erro" hidden>Selecione a forma de pagamento.</span>
                </div>

                <div class="pdv-pag__valores">
                    <div class="pdv-totais__linha">
                        <span>Subtotal (<span id="pdv-pag-qtd-itens">0</span> itens)</span>
                        <strong id="pdv-pag-subtotal">R$ 0,00</strong>
                    </div>
                    <div class="pdv-ajustes-grid">
                        <div class="form-group">
                            <label class="form-label" for="pdv-desconto">Desconto (R$) <kbd class="pdv-kbd">F7</kbd></label>
                            <input type="tel" id="pdv-desconto" class="form-control input-numeric text-right pdv-input-soft"
                                   inputmode="numeric" data-decimals="2" value="0,00">
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="pdv-acrescimo">Acréscimo (R$)</label>
                            <input type="tel" id="pdv-acrescimo" class="form-control input-numeric text-right pdv-input-soft"
                                   inputmode="numeric" data-decimals="2" value="0,00">
                        </div>
                    </div>
                    <div class="pdv-totais__total">
                        <span class="pdv-totais__total-label">Total a pagar</span>
                        <strong class="pdv-total-grande" id="pdv-pag-total">R$ 0,00</strong>
                    </div>

                    <div class="pdv-pag__recebido" id="pdv-pag-recebido-wrap">
                        <div class="form-group">
                            <label class="form-label" for="pdv-recebido">Valor recebido (R$) <kbd class="pdv-kbd">F8</kbd></label>
                            <input type="tel" id="pdv-recebido" class="form-control input-numeric text-right pdv-input-recebido"
                                   inputmode="numeric" data-decimals="2" value="0,00">
                        </div>
                        <div class="pdv-pag__troco">
                            <span>Troco</span>
                            <strong id="pdv-troco">R$ 0,00</strong>
                        </div>
                    </div>

                    <p class="pdv-pag__cliente">Cliente: <strong id="pdv-pag-cliente">Consumidor Final</strong></p>
                    <p class="pdv-pag__aviso" id="pdv-pag-aviso" hidden>
                        Venda a prazo sem cliente identificado: a conta a receber ficará sem cliente. Informe o cliente (F3) se possível.
                    </p>
                </div>
            </div>

            <div class="pdv-pag__footer">
                <button type="button" class="btn btn--ghost" data-pag-fechar>Voltar aos itens</button>
                <button type="button" class="pdv-btn-finalizar pdv-pag__confirmar" id="pdv-btn-confirmar">
                    <span class="pdv-btn-finalizar__textos">
                        <span class="pdv-btn-finalizar__label">Confirmar venda</span>
                        <span class="pdv-btn-finalizar__forma" id="pdv-btn-finalizar-forma">{{ $formasPagamento->first()->nome ?? 'Sem pagamento' }}</span>
                    </span>
                    <span class="pdv-btn-finalizar__valor" id="pdv-btn-confirmar-valor">R$ 0,00</span>
                    <kbd class="pdv-kbd pdv-kbd--on-dark pdv-btn-finalizar__kbd">Enter</kbd>
                </button>
            </div>
        </div>
    </div>

    <div class="pdv-pag-overlay" id="pdv-ajuda" hidden>
        <div class="pdv-pag pdv-pag--ajuda" role="dialog" aria-modal="true" aria-labelledby="pdv-ajuda-titulo">
            <div class="pdv-pag__header">
                <h2 class="pdv-pag__titulo" id="pdv-ajuda-titulo">Atalhos do teclado</h2>
                <button type="button" class="btn btn--ghost btn--sm" data-ajuda-fechar>Fechar <kbd class="pdv-kbd">Esc</kbd></button>
            </div>
            <dl class="pdv-ajuda-lista">
                <dt><kbd class="pdv-kbd">F2</kbd> ou <kbd class="pdv-kbd">/</kbd></dt><dd>Ir para a busca de produto</dd>
                <dt><kbd class="pdv-kbd">3</kbd><kbd class="pdv-kbd">*</kbd> + código</dt><dd>Adicionar com quantidade (ex.: <code>3*7891234</code>)</dd>
                <dt><kbd class="pdv-kbd">Enter</kbd></dt><dd>Adicionar o produto destacado / bipado</dd>
                <dt><kbd class="pdv-kbd">↑</kbd><kbd class="pdv-kbd">↓</kbd></dt><dd>Navegar nas sugestões</dd>
                <dt><kbd class="pdv-kbd">F3</kbd></dt><dd>Informar o cliente</dd>
                <dt><kbd class="pdv-kbd">F4</kbd></dt><dd>Abrir o pagamento / confirmar a venda</dd>
                <dt><kbd class="pdv-kbd">F6</kbd></dt><dd>Trocar a forma de pagamento</dd>
                <dt><kbd class="pdv-kbd">F7</kbd> / <kbd class="pdv-kbd">F8</kbd></dt><dd>Desconto / valor recebido (no pagamento)</dd>
                <dt><kbd class="pdv-kbd">F9</kbd></dt><dd>Alterar a quantidade do último item</dd>
                <dt><kbd class="pdv-kbd">Ctrl</kbd>+<kbd class="pdv-kbd">Del</kbd></dt><dd>Remover o último item</dd>
                <dt><kbd class="pdv-kbd">Ctrl</kbd>+<kbd class="pdv-kbd">Shift</kbd>+<kbd class="pdv-kbd">Del</kbd></dt><dd>Cancelar a venda (limpar tudo)</dd>
                <dt><kbd class="pdv-kbd">Esc</kbd></dt><dd>Limpar a busca / fechar janelas</dd>
                <dt><kbd class="pdv-kbd">N</kbd> ou <kbd class="pdv-kbd">Enter</kbd></dt><dd>Nova venda (após confirmar)</dd>
            </dl>
        </div>
    </div>

    <div id="pdv-sucesso" class="pdv-sucesso" hidden>
        <div class="pdv-sucesso__card">
            <div class="pdv-sucesso__ring">
                <svg class="pdv-sucesso__icone" viewBox="0 0 52 52" aria-hidden="true">
                    <circle class="pdv-sucesso__circle" cx="26" cy="26" r="24" fill="none"/>
                    <path class="pdv-sucesso__check" fill="none" d="M14.5 27.2l7.2 7.2 15.8-16"/>
                </svg>
            </div>
            <p class="pdv-sucesso__badge" id="pdv-sucesso-pagamento">Pagamento registrado</p>
            <h2 class="pdv-sucesso__titulo">Venda Confirmada!</h2>
            <p class="pdv-sucesso__valor" id="pdv-sucesso-total">Total: R$ 0,00</p>
            <p class="pdv-sucesso__numero" id="pdv-sucesso-numero">Nº —</p>
            <p class="pdv-sucesso__troco" id="pdv-sucesso-troco" hidden></p>
            <div class="pdv-sucesso__acoes">
                <button type="button" class="btn btn--primary pdv-sucesso__btn" id="pdv-btn-nova-venda">
                    Nova Venda <kbd class="pdv-kbd pdv-kbd--on-dark">N</kbd>
                </button>
                <a href="#" class="btn btn--ghost pdv-sucesso__btn" id="pdv-link-ver-venda">Ver Venda</a>
            </div>
        </div>
    </div>
    </div>{{-- /.pdv-shell --}}

    <div class="pdv-toast" id="pdv-toast" hidden aria-live="polite"></div>

@endsection

@push('scripts')
<script>
    window.PDV_CONFIG = {
        urls: {
            produtosBusca: @json(route('api.produtos.busca')),
            produtosCodigo: @json(url('/api/produtos')),
            clientesBusca: @json(route('api.clientes.busca')),
            vendaStore: @json(route('venda.store')),
            vendasUltima: @json(route('api.vendas.ultima')),
            vendaShowBase: @json(url('/venda')),
        },
        dataHoje: @json(date('Y-m-d')),
    };
</script>
<script src="{{ asset('js/pdv.js') }}?v=10"></script>
@endpush
