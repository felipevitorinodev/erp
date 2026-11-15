{{--
    Busca rápida (cabeçalho): procura telas do menu e ações de criação pelo nome, sem acento.
    Atalho "/" fora de campos de texto (e Ctrl+K quando o assistente não está na tela, pois ele usa Ctrl+K).
    Não abre pelo atalho no PDV, que usa "/" na busca de produtos. Comportamento em public/js/app.js.
    As telas vêm do próprio menu lateral (já filtrado por permissão); as ações, desta lista.
--}}
@php
    $u = auth()->user();
    $acoes = collect([
        ['Nova venda', 'venda.create', 'vendas', 'venda pedido'],
        ['Abrir o PDV (caixa)', 'pdv.index', 'vendas', 'caixa balcao frente de loja'],
        ['Novo orçamento', 'orcamento.create', 'orcamentos', 'proposta cotacao'],
        ['Nova ordem de serviço', 'ordem-servico.create', 'ordens_servico', 'os assistencia conserto'],
        ['Nova NF-e', 'nfe.create', 'nfe', 'nota fiscal emitir'],
        ['Novo produto', 'produto.create', 'produtos', 'item mercadoria servico'],
        ['Nova entrada de estoque', 'entrada-estoque.create', 'estoque', 'compra xml nota fornecedor'],
        ['Ajustar estoque', 'estoque.ajuste.create', 'estoque', 'inventario acerto'],
        ['Nova conta a receber', 'conta-receber.create', 'financeiro', 'receita boleto cobranca'],
        ['Nova conta a pagar', 'conta-pagar.create', 'financeiro', 'despesa boleto pagamento'],
        ['Novo cliente', 'cliente.create', 'clientes', 'cadastro pessoa'],
        ['Novo fornecedor', 'fornecedor.create', 'fornecedores', 'cadastro'],
        ['Novo funcionário', 'funcionario.create', 'funcionarios', 'vendedor tecnico colaborador'],
        ['Novo usuário', 'usuario.create', 'configuracoes', 'acesso login'],
    ])
        ->filter(fn ($a) => $u->pode($a[2]) && \Illuminate\Support\Facades\Route::has($a[1]))
        ->map(fn ($a) => ['rotulo' => $a[0], 'url' => route($a[1]), 'grupo' => 'Ação', 'termos' => $a[3]])
        ->values();
@endphp

<button type="button" class="header__busca" data-busca-abrir aria-haspopup="dialog" aria-controls="busca-rapida"
    title="Buscar tela ou ação (atalho /)" aria-label="Buscar tela ou ação">
    <x-icon name="search" class="header__busca-icone" />
    <span class="header__busca-texto">Buscar tela ou ação</span>
    <kbd class="header__busca-atalho" aria-hidden="true">/</kbd>
</button>

@push('modals')
    <div class="busca-rapida" id="busca-rapida" role="dialog" aria-modal="true" aria-label="Busca rápida" hidden>
        <div class="busca-rapida__caixa">
            <div class="busca-rapida__campo">
                <x-icon name="search" :size="18" />
                <input type="text" id="busca-rapida-campo" class="busca-rapida__input" autocomplete="off" spellcheck="false"
                    placeholder="Ir para… ex.: contas a pagar, novo cliente" enterkeyhint="go"
                    role="combobox" aria-expanded="true" aria-controls="busca-rapida-lista" aria-autocomplete="list">
                <button type="button" class="busca-rapida__fechar" data-busca-fechar aria-label="Fechar">Esc</button>
            </div>
            <ul class="busca-rapida__lista" id="busca-rapida-lista" role="listbox" aria-label="Resultados"></ul>
            <p class="busca-rapida__dica">
                <kbd>↑</kbd> <kbd>↓</kbd> navegar · <kbd>Enter</kbd> abrir · <kbd>Esc</kbd> fechar
            </p>
        </div>
        <script type="application/json" id="busca-rapida-acoes">@json($acoes)</script>
    </div>
@endpush
