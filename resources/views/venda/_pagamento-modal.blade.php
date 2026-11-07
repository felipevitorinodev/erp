<div class="pg-scrim" id="venda-pagamento" hidden>
    <div class="pg-dialog" data-pg-dialog role="dialog" aria-modal="true" aria-labelledby="venda-pag-titulo">
        <div class="pg-head">
            <div>
                <h2 class="pg-title" id="venda-pag-titulo" data-pg-titulo>Forma de pagamento</h2>
                <div class="pg-sub" data-pg-sub>Total de R$ 0,00</div>
            </div>
            <button type="button" class="pg-btn" data-pg-fechar>Voltar <kbd>Esc</kbd></button>
        </div>

        <div class="pg-corpo">
            <div data-pg-passo-forma>
                <div class="pg-forma">
                    <div class="pg-secao">Escolha a forma</div>
                    @if($formasPagamento->isEmpty())
                        <p class="pg-note">Nenhuma forma de pagamento ativa. Peça ao administrador para cadastrar.</p>
                    @else
                        <div class="pg-grid" data-pg-formas role="radiogroup" aria-label="Forma de pagamento">
                            @foreach($formasPagamento as $i => $forma)
                                <button type="button"
                                        class="pg-opt{{ $forma->permiteParcelar() ? ' is-parc' : '' }}{{ $i === 0 ? ' is-on' : '' }}"
                                        data-forma="{{ $forma->nome }}"
                                        data-prazo="{{ $forma->permiteParcelar() ? '1' : '0' }}"
                                        data-cartao="{{ $forma->ehCartao() ? '1' : '0' }}"
                                        data-dias="{{ (int) $forma->dias_vencimento }}"
                                        role="radio"
                                        aria-checked="{{ $i === 0 ? 'true' : 'false' }}">
                                    @if ($i < 9)
                                        <kbd>{{ $i + 1 }}</kbd>
                                    @endif
                                    <span>{{ $forma->nome }}</span>
                                    <span class="pg-tag">{{ $forma->permiteParcelar() ? 'Parcelável' : 'À vista' }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                    <p class="pg-note">Formas com › abrem o financiamento. As formas à vista seguem direto para a venda.</p>
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
            <button type="button" class="pg-btn" data-pg-voltar data-pg-fechar>Voltar</button>
            <button type="button" class="pg-btn pg-primary" data-pg-confirmar>
                <span class="pg-primary__textos">
                    <span data-pg-acao>Usar esta forma</span>
                    <span class="pg-primary__detalhe" data-pg-detalhe></span>
                </span>
                <kbd>Enter</kbd>
            </button>
        </div>
    </div>
</div>
