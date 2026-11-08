@extends('layouts.app')

@section('title', 'PDV')
@section('page_title', 'PDV — Ponto de venda')

@section('page_actions')
    <a href="{{ route('venda.index') }}" class="pdv-back">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M19 12H5"></path>
            <path d="M12 19l-7-7 7-7"></path>
        </svg>
        Vendas
    </a>
@endsection

@push('styles')
<script>document.documentElement.classList.add('pdv-page');</script>
<link rel="stylesheet" href="{{ asset('css/pdv.css') }}?v={{ filemtime(public_path('css/pdv.css')) }}">
<link rel="stylesheet" href="{{ asset('css/pagamento-modal.css') }}?v={{ filemtime(public_path('css/pagamento-modal.css')) }}">
@endpush

@section('content')
<script>document.body.classList.add('pdv-page');</script>

    <div class="pdv-shell" id="pdv-shell">
        <div class="pdv-tela-pequena" id="pdv-tela-pequena" hidden>
            <div class="pdv-tela-pequena__card">
                <div class="pdv-tela-pequena__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="3" width="20" height="14" rx="2"></rect>
                        <path d="M8 21h8"></path>
                        <path d="M12 17v4"></path>
                    </svg>
                </div>
                <h2 class="pdv-tela-pequena__titulo">Tela pequena demais</h2>
                <p class="pdv-tela-pequena__texto">
                    O caixa precisa de uma tela com pelo menos 1024 px de largura.
                    Aumente a janela ou use um monitor maior.
                </p>
                <p class="pdv-tela-pequena__meta">Largura atual: <strong id="pdv-largura-atual">—</strong></p>
                <div class="pdv-tela-pequena__acoes">
                    <a href="{{ route('venda.index') }}" class="pdv-tela-pequena__btn">Voltar para Vendas</a>
                    <a href="{{ route('venda.create') }}" class="pdv-tela-pequena__btn pdv-tela-pequena__btn--sec">Abrir venda pelo formulário</a>
                </div>
            </div>
        </div>

        <div class="pdv-wrap" id="pdv-wrap">
            <section class="pdv-esquerda" aria-label="Itens da venda">
                <div class="pdv-busca">
                    <div class="pdv-busca__linha">
                        <div class="pdv-busca__input-wrap">
                            <svg class="pdv-busca__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="11" cy="11" r="7"></circle>
                                <path d="m21 21-4.3-4.3"></path>
                            </svg>
                            <input type="search"
                                   id="pdv-input-busca"
                                   class="pdv-input-busca"
                                   placeholder="Nome, código ou código de barras"
                                   autocomplete="off"
                                   enterkeyhint="done"
                                   aria-label="Buscar produto">
                            <kbd class="pdv-kbd pdv-busca__kbd">F2</kbd>
                            <div id="pdv-sugestoes" class="pdv-sugestoes" hidden></div>
                        </div>
                        <button type="button" class="pdv-info" aria-label="Instruções da busca"
                                data-tip="F2 volta para a busca. Enter adiciona o item com a quantidade do campo Qtd. 3* grava a quantidade e libera a busca. 3*000001 adiciona direto. Esc limpa a busca.">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"></circle>
                                <path d="M12 11v6"></path>
                                <path d="M12 8h.01"></path>
                            </svg>
                        </button>
                        <div class="pdv-qtd-busca" id="pdv-qtd-busca">
                            <label class="pdv-qtd-busca__rotulo" for="pdv-input-qtd">Qtd</label>
                            <input type="text"
                                   id="pdv-input-qtd"
                                   class="pdv-qtd-busca__input"
                                   value="1"
                                   inputmode="decimal"
                                   autocomplete="off"
                                   enterkeyhint="next"
                                   aria-label="Quantidade do próximo item"
                                   data-tip="Quantidade do próximo item. Digite o número e * para voltar à busca, ou 3* na busca. Exemplo: 3* e depois o nome, ou 3*000001.">
                        </div>
                        <button type="button" class="pdv-btn-tabela" id="pdv-btn-tabela"
                                data-tip="Lista todos os produtos. Dá para alterar preço e quantidade e aplicar na venda com Enter.">
                            Tabela de preços
                            <kbd class="pdv-kbd">F10</kbd>
                        </button>
                    </div>
                    <div class="pdv-comandos" aria-label="Atalhos principais">
                        <div class="pdv-comandos__grupo">
                            <span class="pdv-comando" data-tip="Adiciona o produto destacado ou o código bipado."><kbd class="pdv-kbd">Enter</kbd> adicionar</span>
                            <span class="pdv-comando" data-tip="3* grava a quantidade no campo Qtd. Depois busque pelo nome ou continue com o código, como em 3*000001."><kbd class="pdv-kbd">3*</kbd> quantidade</span>
                            <span class="pdv-comando" data-tip="Abre o pagamento. Com ele aberto, Enter confirma a venda."><kbd class="pdv-kbd">F4</kbd> pagar</span>
                        </div>
                        <button type="button" class="pdv-comando pdv-comando--link" data-abrir-ajuda data-tip="Lista completa de atalhos do caixa.">
                            <kbd class="pdv-kbd">F1</kbd> todos os atalhos
                        </button>
                    </div>
                </div>

                <div class="pdv-itens-header">
                    <span class="pdv-itens-header__titulo">Itens da venda</span>
                    <span class="pdv-itens-header__resumo">
                        <span id="pdv-itens-resumo">Nenhum item</span>
                        <span class="pdv-itens-header__count" id="pdv-itens-count" hidden>0</span>
                    </span>
                </div>

                <div class="pdv-grade-head" id="pdv-grade-head" hidden>
                    <span>Produto</span>
                    <span>Qtd</span>
                    <span>Total</span>
                    <span></span>
                </div>

                <div id="pdv-itens" class="pdv-itens"></div>

                <div id="pdv-empty" class="pdv-empty" data-ilustracao-area>
                    <x-ilustracao nome="carrinho" tamanho="md" interativa class="pdv-empty__ilustracao" />
                    <p class="pdv-empty__titulo">Caixa livre</p>
                    <p class="pdv-empty__texto">Bipe o código, busque pelo nome ou abra a tabela de preços.</p>
                </div>
            </section>

            <aside class="pdv-direita" aria-label="Resumo da venda">
                <div class="pdv-painel">
                    <div class="pdv-total-card">
                        <div class="pdv-totais__linha">
                            <span>Subtotal</span>
                            <strong id="pdv-display-subtotal">R$ 0,00</strong>
                        </div>
                        <div class="pdv-totais__linha pdv-totais__linha--ajuste" id="pdv-linha-desconto" hidden>
                            <span>Desconto</span>
                            <strong id="pdv-display-desconto">− R$ 0,00</strong>
                        </div>
                        <div class="pdv-totais__linha pdv-totais__linha--ajuste" id="pdv-linha-acrescimo" hidden>
                            <span>Acréscimo</span>
                            <strong id="pdv-display-acrescimo">+ R$ 0,00</strong>
                        </div>
                        <div class="pdv-totais__total">
                            <span class="pdv-totais__total-label">Total</span>
                            <strong class="pdv-total-grande" id="pdv-display-total">R$ 0,00</strong>
                        </div>
                    </div>

                    <div class="pdv-cliente">
                        <div class="pdv-field-label">
                            <label for="pdv-input-cliente">Cliente</label>
                            <small>opcional · <kbd class="pdv-kbd">F3</kbd></small>
                        </div>
                        <div class="autocomplete-wrap">
                            <input type="text" id="pdv-input-cliente" class="pdv-input-soft"
                                   placeholder="Consumidor final"
                                   autocomplete="off"
                                   data-tip="Opcional na venda à vista. Na venda a prazo, identifique o cliente para gerar a conta a receber no nome dele.">
                            <input type="hidden" id="pdv-hidden-cliente-id" value="">
                        </div>
                    </div>

                    <button type="button" class="pdv-resumo-forma" id="pdv-btn-forma"
                            data-tip="F6 troca a forma sem abrir o pagamento. No pagamento, as teclas 1 a 9 escolhem direto.">
                        <span class="pdv-resumo-forma__rotulo">Pagamento <kbd class="pdv-kbd">F6</kbd></span>
                        <strong id="pdv-resumo-forma">{{ $formasPagamento->first()->nome ?? 'Nenhuma forma ativa' }}</strong>
                    </button>

                    <div class="pdv-btn-finalizar-fixed">
                        <button type="button" class="pdv-btn-finalizar" id="pdv-btn-finalizar"
                                data-tip="Abre a conferência: forma, desconto, acréscimo e troco. Enter confirma a venda.">
                            <span class="pdv-btn-finalizar__textos">
                                <span class="pdv-btn-finalizar__label">Finalizar venda</span>
                                <span class="pdv-btn-finalizar__forma">pagamento e troco</span>
                            </span>
                            <span class="pdv-btn-finalizar__lado">
                                <span class="pdv-btn-finalizar__valor" id="pdv-btn-finalizar-valor">R$ 0,00</span>
                                <kbd class="pdv-kbd pdv-kbd--lg pdv-kbd--on-dark">F4</kbd>
                            </span>
                        </button>
                    </div>

                    <button type="button" class="pdv-link-atalhos" id="pdv-btn-ajuda">
                        Atalhos do teclado <kbd class="pdv-kbd">F1</kbd>
                    </button>
                </div>
            </aside>
        </div>

        <div class="pdv-pag-overlay" id="pdv-pagamento" hidden>
            <div class="pdv-pag pg-dialog" data-pg-dialog role="dialog" aria-modal="true" aria-labelledby="pdv-pag-titulo">
                <div class="pg-head">
                    <div>
                        <h2 class="pg-title" id="pdv-pag-titulo" data-pg-titulo>Forma de pagamento</h2>
                        <div class="pg-sub" data-pg-sub>Total de R$ 0,00</div>
                        <span id="pdv-pag-total" hidden>R$ 0,00</span>
                    </div>
                    <button type="button" class="pdv-esc" data-pag-fechar>Voltar <kbd class="pdv-kbd">Esc</kbd></button>
                </div>

                <div class="pg-corpo">
                    <div data-pg-passo-forma>
                        <div class="pg-forma">
                            <div class="pg-secao">Escolha a forma</div>
                            @if($formasPagamento->isEmpty())
                                <p class="pdv-pagamento__vazio">Nenhuma forma de pagamento ativa. Peça ao administrador para cadastrar.</p>
                            @else
                                <div class="pg-grid" id="pdv-formas-grid" data-pg-formas role="radiogroup" aria-label="Forma de pagamento">
                                    @foreach($formasPagamento as $i => $forma)
                                        <button type="button"
                                                class="pg-opt pdv-forma-btn{{ $forma->permiteParcelar() ? ' is-parc pdv-forma-btn--prazo' : '' }}{{ $i === 0 ? ' is-on pdv-forma-btn--ativa' : '' }}"
                                                data-forma="{{ $forma->nome }}"
                                                data-tipo="{{ $forma->permiteParcelar() ? 'Parcelável' : 'À vista' }}"
                                                data-prazo="{{ $forma->permiteParcelar() ? '1' : '0' }}"
                                                data-cartao="{{ $forma->ehCartao() ? '1' : '0' }}"
                                                data-dias="{{ (int) $forma->dias_vencimento }}"
                                                role="radio"
                                                aria-checked="{{ $i === 0 ? 'true' : 'false' }}"
                                                aria-pressed="{{ $i === 0 ? 'true' : 'false' }}">
                                            @if ($i < 9)
                                                <kbd class="pdv-forma-btn__tecla">{{ $i + 1 }}</kbd>
                                            @endif
                                            <span class="pdv-forma-btn__nome">{{ $forma->nome }}</span>
                                            <span class="pg-tag pdv-forma-btn__tipo">{{ $forma->permiteParcelar() ? 'Parcelável' : 'À vista' }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                            <p class="pg-note">Formas com › abrem o financiamento ao confirmar. As formas à vista fecham a venda direto.</p>
                            <input type="hidden" id="pdv-hidden-forma-pagamento" value="{{ $formasPagamento->first()->nome ?? '' }}">
                            <span class="form-error" id="pdv-forma-erro" hidden>Selecione a forma de pagamento.</span>

                            <div class="pg-valores">
                                <div class="pdv-campo">
                                    <div class="pdv-field-label">
                                        <label for="pdv-desconto">Desconto <kbd class="pdv-kbd">F7</kbd></label>
                                    </div>
                                    <input type="tel" id="pdv-desconto" class="pdv-input-soft pdv-input-soft--num input-numeric"
                                           inputmode="numeric" data-decimals="2" value="0,00" aria-label="Desconto em reais">
                                </div>
                                <div class="pdv-campo">
                                    <div class="pdv-field-label">
                                        <label for="pdv-acrescimo">Acréscimo</label>
                                    </div>
                                    <input type="tel" id="pdv-acrescimo" class="pdv-input-soft pdv-input-soft--num input-numeric"
                                           inputmode="numeric" data-decimals="2" value="0,00" aria-label="Acréscimo em reais">
                                </div>
                                <div class="pdv-pag__recebido" id="pdv-pag-recebido-wrap">
                                    <div class="pdv-campo">
                                        <div class="pdv-field-label">
                                            <label for="pdv-recebido">Valor recebido <kbd class="pdv-kbd">F8</kbd></label>
                                        </div>
                                        <input type="tel" id="pdv-recebido" class="pdv-input-soft pdv-input-soft--num input-numeric"
                                               inputmode="numeric" data-decimals="2" value="0,00" aria-label="Valor recebido">
                                    </div>
                                    <div class="pdv-pag__troco">
                                        <span>Troco</span>
                                        <strong id="pdv-troco">R$ 0,00</strong>
                                    </div>
                                </div>
                                @if ($emitirNfce)
                                    <div class="pdv-campo pdv-campo--largo">
                                        <div class="pdv-field-label">
                                            <label for="pdv-nfce-documento">CPF ou CNPJ na NFC-e</label>
                                        </div>
                                        <input type="tel" id="pdv-nfce-documento" class="pdv-input-soft" inputmode="numeric"
                                               autocomplete="off" maxlength="18" placeholder="{{ ($nfceDocumentoAcima500 ?? false) ? 'Opcional abaixo de R$ 500,00' : 'Opcional' }}">
                                    </div>
                                @endif
                            </div>
                            <p class="pdv-pag__aviso" id="pdv-pag-aviso" hidden>
                                <strong>Sem cliente selecionado.</strong> A conta a receber ficará sem cliente. Informe o cliente com <kbd class="pdv-kbd">F3</kbd> antes de confirmar.
                            </p>
                            <p class="pdv-pag__cliente">Cliente <strong id="pdv-pag-cliente">Consumidor Final</strong></p>
                            <span id="pdv-pag-subtotal" hidden>R$ 0,00</span>
                            <span id="pdv-pag-qtd-itens" hidden>0</span>
                        </div>
                    </div>

                    <div data-pg-passo-parc hidden>
                        <div class="pg-parc">
                            <div class="pg-parc-main">
                                <div class="pg-parc-head">
                                    <span class="pg-secao">Forma de pagamento</span>
                                    <span class="pg-forma-chip" data-pg-parc-forma></span>
                                </div>
                                <div>
                                    <div class="pg-secao" style="margin-bottom:8px">Número de parcelas</div>
                                    <div class="pg-parc-grid" data-pg-parc-grid></div>
                                </div>
                                <div>
                                    <div class="pg-secao" style="margin-bottom:8px">Primeiro vencimento</div>
                                    <div class="pg-data">
                                        <input type="date" data-pg-parc-data aria-label="Data do primeiro vencimento">
                                        <span class="pg-note" data-pg-parc-dica></span>
                                    </div>
                                </div>
                                <div>
                                    <div class="pg-secao" style="margin-bottom:8px">Parcelas</div>
                                    <table class="pg-tabela">
                                        <thead>
                                            <tr>
                                                <th>Parcela</th>
                                                <th>Vencimento</th>
                                                <th data-pg-col-doc hidden>Nº do cheque</th>
                                                <th class="r">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody data-pg-parc-corpo></tbody>
                                    </table>
                                </div>
                            </div>
                            <aside class="pg-parc-side">
                                <div>
                                    <div class="pg-secao">Total da venda</div>
                                    <div class="pg-big" data-pg-side-total>R$ 0,00</div>
                                </div>
                                <div class="pg-kv"><span>Parcelas</span><b data-pg-side-n>1x</b></div>
                                <div class="pg-kv"><span>Valor da parcela</span><b data-pg-side-valor>R$ 0,00</b></div>
                                <div class="pg-kv"><span>1ª parcela</span><b data-pg-side-primeira></b></div>
                                <div class="pg-kv"><span>Última parcela</span><b data-pg-side-ultima></b></div>
                                <div class="pg-note" data-pg-parc-nota style="margin-top:auto"></div>
                            </aside>
                        </div>
                    </div>
                </div>

                <div class="pg-foot">
                    <button type="button" class="pg-btn" data-pg-voltar data-pag-fechar>Voltar aos itens</button>
                    <button type="button" class="pg-btn pg-primary" id="pdv-btn-confirmar">
                        <span class="pg-primary__textos">
                            <span class="pdv-btn-finalizar__label" data-pg-acao id="pdv-btn-confirmar-acao">{{ $emitirNfce ? 'Confirmar e emitir NFC-e' : 'Confirmar venda' }}</span>
                            <span class="pg-primary__detalhe" data-pg-detalhe></span>
                            <span id="pdv-btn-finalizar-forma" hidden>{{ $formasPagamento->first()->nome ?? 'Sem pagamento' }}</span>
                            <span id="pdv-btn-confirmar-valor" hidden></span>
                        </span>
                        <kbd class="pdv-kbd pdv-kbd--on-dark">Enter</kbd>
                    </button>
                </div>
            </div>
        </div>

        <div class="pdv-pag-overlay" id="pdv-ajuda" hidden>
            <div class="pdv-pag pdv-pag--ajuda" role="dialog" aria-modal="true" aria-labelledby="pdv-ajuda-titulo">
                <div class="pdv-pag__header">
                    <h2 class="pdv-pag__titulo" id="pdv-ajuda-titulo">Atalhos do teclado</h2>
                    <button type="button" class="pdv-esc" data-ajuda-fechar>Fechar <kbd class="pdv-kbd">Esc</kbd></button>
                </div>
                <div class="pdv-ajuda-grid">
                    <div>
                        <p class="pdv-ajuda-grupo">Itens</p>
                        <div class="pdv-key-row"><span>Tabela de preços</span><span class="pdv-keys"><kbd class="pdv-kbd">F10</kbd></span></div>
                        <div class="pdv-key-row"><span>Buscar</span><span class="pdv-keys"><kbd class="pdv-kbd">F2</kbd> ou <kbd class="pdv-kbd">/</kbd></span></div>
                        <div class="pdv-key-row"><span>Quantidade, depois o item</span><span class="pdv-keys"><kbd class="pdv-kbd">3*</kbd></span></div>
                        <div class="pdv-key-row"><span>Adicionar</span><span class="pdv-keys"><kbd class="pdv-kbd">Enter</kbd></span></div>
                        <div class="pdv-key-row"><span>Navegar na lista</span><span class="pdv-keys"><kbd class="pdv-kbd">↑</kbd><kbd class="pdv-kbd">↓</kbd></span></div>
                        <div class="pdv-key-row"><span>Quantidade do último item</span><span class="pdv-keys"><kbd class="pdv-kbd">F9</kbd></span></div>
                        <div class="pdv-key-row"><span>Remover o último item</span><span class="pdv-keys"><kbd class="pdv-kbd">Ctrl</kbd>+<kbd class="pdv-kbd">Del</kbd></span></div>
                    </div>
                    <div>
                        <p class="pdv-ajuda-grupo">Cliente e pagamento</p>
                        <div class="pdv-key-row"><span>Cliente</span><span class="pdv-keys"><kbd class="pdv-kbd">F3</kbd></span></div>
                        <div class="pdv-key-row"><span>Pagamento ou confirmar venda</span><span class="pdv-keys"><kbd class="pdv-kbd">F4</kbd></span></div>
                        <div class="pdv-key-row"><span>Trocar a forma de pagamento</span><span class="pdv-keys"><kbd class="pdv-kbd">F6</kbd></span></div>
                        <div class="pdv-key-row"><span>Desconto</span><span class="pdv-keys"><kbd class="pdv-kbd">F7</kbd></span></div>
                        <div class="pdv-key-row"><span>Valor recebido</span><span class="pdv-keys"><kbd class="pdv-kbd">F8</kbd></span></div>
                        <p class="pdv-ajuda-grupo pdv-ajuda-grupo--depois">Venda</p>
                        <div class="pdv-key-row"><span>Limpar ou fechar</span><span class="pdv-keys"><kbd class="pdv-kbd">Esc</kbd></span></div>
                        <div class="pdv-key-row"><span>Cancelar a venda</span><span class="pdv-keys"><kbd class="pdv-kbd">Ctrl</kbd>+<kbd class="pdv-kbd">Shift</kbd>+<kbd class="pdv-kbd">Del</kbd></span></div>
                        <div class="pdv-key-row"><span>Nova venda (depois de confirmar)</span><span class="pdv-keys"><kbd class="pdv-kbd">N</kbd> ou <kbd class="pdv-kbd">Enter</kbd></span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pdv-pag-overlay" id="pdv-tabela" hidden>
            <div class="pdv-pag pdv-tabela" role="dialog" aria-modal="true" aria-labelledby="pdv-tabela-titulo">
                <div class="pdv-pag__header">
                    <div>
                        <h2 class="pdv-pag__titulo" id="pdv-tabela-titulo">Tabela de preços</h2>
                        <p class="pdv-tabela__sub" id="pdv-tabela-resumo">Carregando produtos…</p>
                    </div>
                    <button type="button" class="pdv-esc" data-tabela-fechar>Fechar <kbd class="pdv-kbd">Esc</kbd></button>
                </div>
                <div class="pdv-tabela__busca">
                    <div class="pdv-busca__input-wrap">
                        <svg class="pdv-busca__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="11" cy="11" r="7"></circle>
                            <path d="m21 21-4.3-4.3"></path>
                        </svg>
                        <input type="search"
                               id="pdv-tabela-filtro"
                               class="pdv-input-busca pdv-input-busca--dialogo"
                               placeholder="Filtrar por nome, código, referência ou código de barras…"
                               autocomplete="off"
                               enterkeyhint="search"
                               aria-label="Filtrar produtos">
                    </div>
                </div>
                <div class="pdv-tabela__corpo" id="pdv-tabela-corpo">
                    <table class="pdv-tabela__grade">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Referência</th>
                                <th>Código de barras</th>
                                <th>Produto</th>
                                <th class="pdv-tabela__num">Estoque</th>
                                <th class="pdv-tabela__num">Preço</th>
                                <th class="pdv-tabela__qtd-celula">Qtd</th>
                                <th class="pdv-tabela__num">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="pdv-tabela-body"></tbody>
                    </table>
                </div>
                <div class="pdv-pag__footer pdv-tabela__rodape">
                    <p class="pdv-tabela__dica">
                        <kbd class="pdv-kbd">Enter</kbd> aplica a quantidade e o preço na venda.
                        <kbd class="pdv-kbd">↑↓</kbd> navegam na lista.
                    </p>
                </div>
            </div>
        </div>

        <div id="pdv-sucesso" class="pdv-sucesso" hidden>
            <div class="pdv-sucesso__card" role="status">
                <div class="pdv-sucesso__check" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.5l4.5 4.5L19 7.5"></path>
                    </svg>
                </div>
                <p class="pdv-sucesso__badge" id="pdv-sucesso-pagamento">Pagamento registrado</p>
                <h2 class="pdv-sucesso__titulo">Venda confirmada</h2>
                <p class="pdv-sucesso__valor" id="pdv-sucesso-total">R$ 0,00</p>
                <p class="pdv-sucesso__numero" id="pdv-sucesso-numero">Nº —</p>
                <p class="pdv-sucesso__troco" id="pdv-sucesso-troco" hidden></p>
                <p class="pdv-sucesso__nfce" id="pdv-sucesso-nfce" hidden></p>
                <p class="pdv-sucesso__nfce-dica" id="pdv-sucesso-nfce-dica" hidden></p>
                <div class="pdv-sucesso__acoes">
                    <a href="#" class="pdv-sucesso__nfce-link" id="pdv-link-nfce" hidden target="_blank" rel="noopener">Imprimir NFC-e</a>
                    <a href="#" class="pdv-sucesso__ver" id="pdv-link-a4" hidden target="_blank" rel="noopener">A4</a>
                    <a href="#" class="pdv-sucesso__ver" id="pdv-link-recibo" hidden target="_blank" rel="noopener">80 mm</a>
                    <button type="button" class="pdv-btn-finalizar pdv-sucesso__nova" id="pdv-btn-nova-venda">
                        <span class="pdv-btn-finalizar__label">Nova venda</span>
                        <kbd class="pdv-kbd pdv-kbd--lg pdv-kbd--on-dark">N</kbd>
                    </button>
                    <a href="#" class="pdv-sucesso__ver" id="pdv-link-ver-venda">Ver venda</a>
                </div>
            </div>
        </div>
    </div>


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
        emitirNfce: @json($emitirNfce),
    };
</script>
<script src="{{ asset('js/pagamento-modal.js') }}?v={{ filemtime(public_path('js/pagamento-modal.js')) }}"></script>
<script src="{{ asset('js/pdv.js') }}?v={{ filemtime(public_path('js/pdv.js')) }}"></script>
@endpush
