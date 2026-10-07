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
                    <span class="pdv-atalho"><kbd class="pdv-kbd">F2</kbd> busca</span>
                    <span class="pdv-atalho"><kbd class="pdv-kbd">F4</kbd> finalizar</span>
                    <span class="pdv-atalho pdv-atalho--desktop"><kbd class="pdv-kbd">Esc</kbd> limpar</span>
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
                <div class="pdv-cliente form-group">
                    <label class="form-label" for="pdv-input-cliente">Cliente <span class="pdv-label-opcional">opcional</span></label>
                    <div class="autocomplete-wrap">
                        <input type="text" id="pdv-input-cliente" class="form-control pdv-input-soft"
                               placeholder="Consumidor Final" autocomplete="off">
                        <input type="hidden" id="pdv-hidden-cliente-id" value="">
                    </div>
                </div>

                <div class="pdv-totais">
                    <div class="pdv-totais__linha">
                        <span>Subtotal</span>
                        <strong id="pdv-display-subtotal">R$ 0,00</strong>
                    </div>

                    <details class="pdv-ajustes">
                        <summary class="pdv-ajustes__summary">Desconto / Acréscimo</summary>
                        <div class="pdv-ajustes__body">
                            <div class="form-group">
                                <label class="form-label" for="pdv-desconto">Desconto (R$)</label>
                                <input type="tel" id="pdv-desconto" class="form-control input-numeric text-right pdv-input-soft"
                                       inputmode="numeric" data-decimals="2" value="0,00">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="pdv-acrescimo">Acréscimo (R$)</label>
                                <input type="tel" id="pdv-acrescimo" class="form-control input-numeric text-right pdv-input-soft"
                                       inputmode="numeric" data-decimals="2" value="0,00">
                            </div>
                        </div>
                    </details>

                    <div class="pdv-totais__total">
                        <span class="pdv-totais__total-label">Total</span>
                        <strong class="pdv-total-grande" id="pdv-display-total">R$ 0,00</strong>
                    </div>
                </div>

                <div class="pdv-formas">
                    <label class="form-label">Pagamento</label>
                    <div class="pdv-formas-grid" id="pdv-formas-grid">
                        @foreach($formasPagamento as $i => $forma)
                            <button type="button"
                                    class="pdv-forma-btn{{ $i === 0 ? ' pdv-forma-btn--ativa' : '' }}"
                                    data-forma="{{ $forma->nome }}">
                                {{ $forma->nome }}
                            </button>
                        @endforeach
                    </div>
                    <input type="hidden" id="pdv-hidden-forma-pagamento"
                           value="{{ $formasPagamento->first()->nome ?? '' }}">
                    <span class="form-error" id="pdv-forma-erro" hidden>Selecione a forma de pagamento.</span>
                </div>

                <div class="pdv-btn-finalizar-fixed">
                    <button type="button" class="pdv-btn-finalizar" id="pdv-btn-finalizar">
                        <span class="pdv-btn-finalizar__label">Finalizar Venda</span>
                        <span class="pdv-btn-finalizar__valor" id="pdv-btn-finalizar-valor">R$ 0,00</span>
                        <kbd class="pdv-kbd pdv-kbd--on-dark pdv-btn-finalizar__kbd">F4</kbd>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <div id="pdv-sucesso" class="pdv-sucesso" hidden>
        <div class="pdv-sucesso__card">
            <div class="pdv-sucesso__ring">
                <svg class="pdv-sucesso__icone" viewBox="0 0 52 52" aria-hidden="true">
                    <circle class="pdv-sucesso__circle" cx="26" cy="26" r="24" fill="none"/>
                    <path class="pdv-sucesso__check" fill="none" d="M14.5 27.2l7.2 7.2 15.8-16"/>
                </svg>
            </div>
            <p class="pdv-sucesso__badge">Pagamento registrado</p>
            <h2 class="pdv-sucesso__titulo">Venda Confirmada!</h2>
            <p class="pdv-sucesso__valor" id="pdv-sucesso-total">Total: R$ 0,00</p>
            <p class="pdv-sucesso__numero" id="pdv-sucesso-numero">Nº —</p>
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
<script src="{{ asset('js/pdv.js') }}?v=5"></script>
@endpush
