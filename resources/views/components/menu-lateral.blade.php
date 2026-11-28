{{--
    Menu lateral do sistema. Cada grupo lista só os links que o perfil do usuário pode abrir;
    grupo sem nenhum link não aparece. O grupo da tela atual abre expandido (script no layout).
    "ativo" recebe padrões de nome de rota (routeIs) ou, com "url:", de caminho (request()->is).
--}}
@php
    $u = auth()->user();
    $pode = fn (string $permissao) => $u->pode($permissao);
    $podeRelatorios = $pode('relatorios');

    $menu = [
        ['rotulo' => 'Início', 'icone' => 'home', 'url' => url('/'), 'ativo' => ['url:/']],
        ['rotulo' => 'Vendas', 'icone' => 'cart', 'links' => [
            ['rotulo' => 'PDV', 'rota' => 'pdv.index', 'ativo' => ['pdv.*'], 'ver' => $pode('vendas')],
            ['rotulo' => 'Vendas', 'rota' => 'venda.index', 'ativo' => ['venda.*'], 'ver' => $pode('vendas')],
            ['rotulo' => 'Orçamentos', 'rota' => 'orcamento.index', 'ativo' => ['orcamento.*'], 'ver' => $pode('orcamentos')],
            ['rotulo' => 'Ordens de serviço', 'rota' => 'ordem-servico.index', 'ativo' => ['ordem-servico.*'], 'ver' => $pode('ordens_servico')],
            ['rotulo' => 'Comissões', 'rota' => 'comissao.index', 'ativo' => ['comissao.*'], 'ver' => $pode('comissoes')],
        ]],
        ['rotulo' => 'Nota fiscal', 'icone' => 'file-text', 'links' => [
            ['rotulo' => 'NF-e', 'rota' => 'nfe.index', 'ativo' => ['nfe.*'], 'ver' => $pode('nfe')],
            ['rotulo' => 'NFC-e', 'rota' => 'nfce.index', 'ativo' => ['nfce.*'], 'ver' => $pode('vendas')],
            ['rotulo' => 'NFS-e', 'rota' => 'nfse.index', 'ativo' => ['nfse.*'], 'ver' => $pode('nfse')],
            ['rotulo' => 'Perfis fiscais', 'rota' => 'perfilFiscal.index', 'ativo' => ['perfilFiscal.*'], 'ver' => $pode('nfe')],
        ]],
        ['rotulo' => 'Produtos', 'icone' => 'tag', 'links' => [
            ['rotulo' => 'Produtos', 'rota' => 'produto.index', 'ativo' => ['produto.*'], 'ver' => $pode('produtos')],
            ['rotulo' => 'Unidades', 'rota' => 'unidadeMedida.index', 'ativo' => ['unidadeMedida.*'], 'ver' => $pode('produtos')],
            ['rotulo' => 'Grupos', 'rota' => 'grupo.index', 'ativo' => ['grupo.*'], 'ver' => $pode('produtos')],
        ]],
        ['rotulo' => 'Estoque', 'icone' => 'package', 'links' => [
            ['rotulo' => 'Pedidos de compra', 'rota' => 'pedido-compra.index', 'ativo' => ['pedido-compra.*'], 'ver' => $pode('estoque')],
            ['rotulo' => 'Entradas', 'rota' => 'entrada-estoque.index', 'ativo' => ['entrada-estoque.*'], 'ver' => $pode('estoque')],
            ['rotulo' => 'Ajuste manual', 'rota' => 'estoque.ajuste.create', 'ativo' => ['estoque.ajuste.*'], 'ver' => $pode('estoque')],
            ['rotulo' => 'Posição atual', 'rota' => 'relatorio.estoque', 'ativo' => ['relatorio.estoque*'], 'ver' => $pode('estoque') && $podeRelatorios],
        ]],
        ['rotulo' => 'Financeiro', 'icone' => 'dollar', 'links' => [
            ['rotulo' => 'A receber', 'rota' => 'conta-receber.index', 'ativo' => ['conta-receber.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'Cobranças', 'rota' => 'cobranca.index', 'ativo' => ['cobranca.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'A pagar', 'rota' => 'conta-pagar.index', 'ativo' => ['conta-pagar.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'Conciliação bancária', 'rota' => 'conciliacao-bancaria.index', 'ativo' => ['conciliacao-bancaria.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'Fluxo de caixa', 'rota' => 'fluxo-de-caixa.index', 'ativo' => ['fluxo-de-caixa.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'Categorias', 'rota' => 'categoria-financeira.index', 'ativo' => ['categoria-financeira.*'], 'ver' => $pode('financeiro')],
            ['rotulo' => 'Formas de pagamento', 'rota' => 'formaPagamento.index', 'ativo' => ['formaPagamento.*'], 'ver' => $pode('formas_pagamento')],
        ]],
        ['rotulo' => 'Cadastros', 'icone' => 'users', 'links' => [
            ['rotulo' => 'Clientes', 'rota' => 'cliente.index', 'ativo' => ['cliente.*'], 'ver' => $pode('clientes')],
            ['rotulo' => 'Fornecedores', 'rota' => 'fornecedor.index', 'ativo' => ['fornecedor.*'], 'ver' => $pode('fornecedores')],
            ['rotulo' => 'Funcionários', 'rota' => 'funcionario.index', 'ativo' => ['funcionario.*'], 'ver' => $pode('funcionarios')],
        ]],
        ['rotulo' => 'Relatórios', 'icone' => 'bar-chart', 'rota' => 'relatorios.index', 'ativo' => ['relatorios.*', 'relatorio.*'],
            'ver' => \App\Support\CatalogoRelatorios::temAlgum($u)],
        ['rotulo' => 'Configurações', 'icone' => 'settings', 'links' => [
            ['rotulo' => 'Usuários', 'rota' => 'usuario.index', 'ativo' => ['usuario.*'], 'ver' => $pode('configuracoes')],
            ['rotulo' => 'Empresa', 'rota' => 'empresa.index', 'ativo' => ['empresa.*'], 'ver' => $pode('configuracoes')],
            ['rotulo' => 'Permissões', 'rota' => 'permissao.index', 'ativo' => ['permissao.*'], 'ver' => $pode('configuracoes')],
        ]],
    ];

    $ativo = function (array $padroes): bool {
        foreach ($padroes as $padrao) {
            $casou = str_starts_with($padrao, 'url:')
                ? request()->is(ltrim(substr($padrao, 4), '/') ?: '/')
                : request()->routeIs($padrao);
            if ($casou) {
                return true;
            }
        }

        return false;
    };
@endphp

<nav class="sidebar__nav" aria-label="Menu principal">
    @foreach ($menu as $i => $item)
        @if (isset($item['links']))
            @php
                $links = array_values(array_filter($item['links'], fn ($l) => $l['ver']));
                $grupoAtivo = collect($links)->contains(fn ($l) => $ativo($l['ativo']));
            @endphp
            @continue($links === [])
            <div class="sidebar__group" data-group>
                <button type="button" class="sidebar__group-toggle" data-toggle
                    aria-expanded="{{ $grupoAtivo ? 'true' : 'false' }}" aria-controls="menu-grupo-{{ $i }}">
                    <x-icon :name="$item['icone']" :size="18" class="sidebar__icon-svg" />
                    <span class="sidebar__group-label">{{ $item['rotulo'] }}</span>
                    <span class="sidebar__group-arrow" aria-hidden="true">›</span>
                </button>
                <div class="sidebar__group-links" id="menu-grupo-{{ $i }}" data-links>
                    @foreach ($links as $link)
                        @php($linkAtivo = $ativo($link['ativo']))
                        <a href="{{ route($link['rota']) }}" @class(['sidebar__link', 'sidebar__link--active' => $linkAtivo])
                            @if ($linkAtivo) aria-current="page" @endif>{{ $link['rotulo'] }}</a>
                    @endforeach
                </div>
            </div>
        @else
            @continue(isset($item['ver']) && ! $item['ver'])
            @php($linkAtivo = $ativo($item['ativo']))
            <div class="sidebar__group">
                <a href="{{ $item['url'] ?? route($item['rota']) }}" @class(['sidebar__link', 'sidebar__link--active' => $linkAtivo])
                    @if ($linkAtivo) aria-current="page" @endif>
                    <x-icon :name="$item['icone']" :size="18" class="sidebar__icon-svg" />
                    {{ $item['rotulo'] }}
                </a>
            </div>
        @endif
    @endforeach
</nav>
