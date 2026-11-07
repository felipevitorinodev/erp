# 00 — Mapa do sistema (Fase 1)

Data do levantamento: 20/10/2026. Base: `php artisan route:list --except-vendor` (170 rotas), leitura de `app/`, `database/migrations`, `routes/`, `resources/views`, `public/js`.

## Observação sobre a arquitetura real

O enunciado descreve "controllers com lógica, Eloquent simples", mas o código **já usa** a cadeia
`Controller → Service (app/Services) → Repository (app/Repositories) → Model`. Os controllers são finos e delegam ao service.
A revisão respeita essa estrutura existente: nenhuma camada nova foi criada; as correções são feitas no service/repository onde a lógica já mora.

Exceções à cadeia: `routes/api_interna.php` (endpoints AJAX em closures), `routes/relatorios.php` (relatórios em closures) e o dashboard em `routes/web.php` (closure).

## Inventário

| Tipo | Itens |
|---|---|
| Controllers (21) | `AuthController`, `NfeController`, `Vendas\VendaController`, `Orcamentos\OrcamentoController`, `Estoque\EntradaEstoqueController`, `Estoque\AjusteEstoqueController`, `ContasReceber\ContaReceberController`, `ContasPagar\ContaPagarController`, `Comissoes\ComissaoController`, `FluxoCaixa\FluxoCaixaController`, `FormasPagamento\FormaPagamentoController`, `CategoriasFinanceiras\CategoriaFinanceiraController`, `Clientes\ClienteController`, `Fornecedores\FornecedorController`, `Funcionarios\FuncionarioController`, `Produtos\ProdutoController`, `Grupos\GrupoController`, `UnidadesMedida\UnidadeMedidaController`, `Empresas\EmpresaController`, `Usuarios\UsuarioController`, `Usuarios\PerfilController` |
| Services (22) | Um por módulo + `NfeService`, `NfeBuilderService` (monta/assina XML), `NfeSenderService` (SEFAZ), `Estoque\NfeXmlParser` (importação de XML de fornecedor), `CategoriasFinanceiras\CategoriaPadraoService`, `Usuarios\PerfilService` |
| Repositories (15) | Um por módulo de cadastro/movimento (Vendas, Orçamentos, Estoque, Contas a Pagar/Receber, Comissões etc.) |
| Models (22) | `Nfe`, `Vendas\Venda`, `Vendas\VendaItem`, `Orcamentos\Orcamento`, `Orcamentos\OrcamentoItem`, `Estoque\EntradaEstoque`, `Estoque\EntradaEstoqueItem`, `Estoque\MovimentacaoEstoque`, `ContasReceber\ContaReceber`, `ContasReceber\ContaReceberBaixa`, `ContasPagar\ContaPagar`, `ContasPagar\ContaPagarBaixa`, `Comissoes\Comissao`, `FormasPagamento\FormaPagamento`, `CategoriasFinanceiras\CategoriaFinanceira`, `Clientes\Cliente`, `Fornecedores\Fornecedor`, `Funcionarios\Funcionario`, `Produtos\Produto`, `Grupos\Grupo`, `UnidadesMedida\UnidadeMedida`, `Empresas\Empresa`, `Usuarios\User`, `Usuarios\Perfil` |
| FormRequests (19) | Um por cadastro + `Nfes\NfeCreateRequest` (estende `VendaRequest`), `Estoque\ImportarXmlRequest` |
| Middlewares | `CheckPermissao` (alias `permissao`), `CheckPerfil` (alias `perfil`), `EnsureUserIsActive` (anexado ao grupo `web`) |
| Policies | Nenhuma. Isolamento multiempresa é feito manualmente (`autorizarX()` nos services e `where('empresa_id', …)` nos repositories) |
| Jobs / Queue | Nenhum job. Tabelas `jobs`/`failed_jobs` existem, mas a emissão de NF-e é síncrona no request |
| Scheduler | Nenhuma tarefa agendada (`routes/console.php` só tem `inspire`) |
| Comandos | `nfe:testar {empresa_id}` (monta e assina XML sem enviar) |
| Views | 93 arquivos Blade (um diretório por módulo + `layouts`, `components`, `partials`, `pdf`, `relatorios`) |
| JS | `public/js/app.js` (global: máscaras, confirmação, anti duplo-submit), `public/js/autocomplete.js`, `public/js/pdv.js`, `public/js/jquery.min.js`; muitos scripts inline nas views (`nfe/create`, `venda/_form`, `venda/pdv` etc.) |
| Testes | Pest 4 em SQLite em memória: `TenantSafetyTest`, `PermissoesPerfilTest`, `PerfilEFluxosCriticosTest`, `PerfilVendedorEBaixasTest`, `AuditoriaPrimeiraLevaTest`, `ExampleTest` (22 testes; 21 passam, `ExampleTest` falha porque `/` redireciona para login) |

## Esquema real do banco (a partir das migrations)

Todas as tabelas de negócio têm `empresa_id` (FK `empresa`, cascade) e, salvo indicação, `timestamps` + `softDeletes`.

| Tabela | Colunas relevantes | Índices / FKs / observações |
|---|---|---|
| `empresa` | razao_social, cnpj (18, **unique**), cpf, ie, im, endereço (cep, logradouro, numero, complemento, bairro, cidade, uf), ativo, `certificado_path`, `certificado_senha` (text, criptografada com `Crypt`), `nfe_ambiente` (tinyint, default 2), `nfe_serie` (3, '001'), `nfe_sequencia` (bigint, 1), `nfe_csc`, `nfe_id_csc`, `cod_ibge` (7), `crt` (1), `nfe_destino` (1), `nfe_cfop_padrao`, `nfe_csosn_padrao`, `nfe_cst_pis_padrao`, `nfe_cst_cofins_padrao` | Sem `empresa_id` (é o tenant) |
| `users` | empresa_id, name, email (**unique global**), password, `perfil` (50, 'vendedor'), `ativo` | `password_reset_tokens`, `sessions` |
| `perfis` | slug, nome, `permissoes` (json), sistema, ativo | unique (empresa_id, slug); sem softDeletes |
| `clientes` | nome, cpf (**unique global**), cnpj (**unique global**), IE, endereço, estado | Unique de CPF/CNPJ não é por empresa |
| `fornecedores` | idem clientes, sem unique | |
| `funcionarios` | usuario_id (FK users), cpf, salario dec(12,2), `percentual_comissao` dec(5,2) | |
| `grupos` | parent_id (FK grupos) | |
| `unidades_medida` | nome, sigla(10) | |
| `produtos` | grupo_id, unidade_medida_id, fornecedor_id, codigo, codigo_barras, preços dec(12,4), `estoque_atual` dec(12,3), ncm, cest, cfop_padrao, origem, cst_icms, cst_pis, cst_cofins, alíquotas, controla_estoque | Sem índice em codigo/codigo_barras |
| `vendas` | cliente_id, usuario_id, funcionario_id, `numero` (string 20), data_venda, subtotal/desconto/acrescimo/total dec(12,2), forma_pagamento (texto), `situacao` (em_andamento/confirmada/cancelada), `nfe_numero` (9), natureza_operacao, mod_frete, ind_final, inf_ad_fisco | **Sem unique** em (empresa_id, numero) |
| `venda_itens` | venda_id (cascade), produto_id, snapshot de nome/código, quantidade dec(12,3), preco_unitario dec(12,4), desconto/total dec(12,2), ncm, cest, cfop, unidade, origem, csosn, cst_pis, cst_cofins, ean | Sem softDeletes |
| `nfes` | venda_id, numero (9), serie (3), `chave_acesso` (44, **unique**), `situacao` enum(pendente, autorizada, rejeitada, cancelada), ambiente, xml_assinado, xml_autorizado, protocolo, codigo_status, motivo_status, data_autorizacao | índices (empresa_id, situacao), (empresa_id, venda_id). **Sem unique** de número/série; sem coluna de recibo nem de XML do evento de cancelamento |
| `orcamentos` / `orcamento_itens` | espelho de vendas; venda_id quando aprovado; situacao (pendente/aprovado/recusado/cancelado) | |
| `entradas_estoque` / `entrada_estoque_itens` | fornecedor_id, numero, totais, situacao enum(rascunho/confirmada/cancelada), chave_acesso, xml_importado, numero_nfe, serie_nfe | unique (empresa_id, chave_acesso) |
| `movimentacoes_estoque` | produto_id, user_id, tipo enum(entrada/saida/ajuste), quantidade/antes/depois dec(10,3), motivo, origem, origem_id | Sem softDeletes |
| `contas_receber` | cliente_id, venda_id, categoria_id, descricao, valor, valor_pago, vencimento, pagamento, forma_pagamento, situacao | |
| `contas_pagar` | fornecedor_id, entrada_estoque_id, categoria_id, idem | |
| `conta_receber_baixas` / `conta_pagar_baixas` | conta_id, valor, data_pagamento, forma_pagamento | índice (empresa_id, data_pagamento); sem softDeletes |
| `comissoes` | venda_id, funcionario_id, percentual, valor, situacao enum(pendente/paga), data_pagamento | Sem softDeletes |
| `formas_pagamento` | nome, tipo (avista/prazo), gera_conta_receber, dias_vencimento | |
| `categorias_financeiras` | nome, tipo enum(receita/despesa) | |
| `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions` | padrão Laravel | |

Dinheiro: todas as colunas monetárias são `decimal` (correto). No PHP os valores são tratados como `float` com `round()` (trait `App\Traits\ConversorMoeda`).

## Módulos identificados

| # | Módulo | Rotas (prefixo) | Arquivos principais |
|---|---|---|---|
| 01 | **NF-e + configuração fiscal da empresa** | `nfe/*`, `empresa/*` | `NfeController`, `NfeService`, `NfeBuilderService`, `NfeSenderService`, `Nfe`, `NfeCreateRequest`, `EmpresaService`, `EmpresaRequest`, `Empresa`, views `nfe/*`, `empresa/edit` |
| 02 | **Vendas / PDV** | `venda/*`, `pdv` | `VendaController`, `VendaService`, `VendaRepository`, `Venda`, `VendaItem`, `VendaRequest`, views `venda/*`, `public/js/pdv.js` |
| 03 | **Financeiro** | `conta-receber/*`, `conta-pagar/*`, `comissao/*`, `fluxo-de-caixa*`, `formaPagamento/*`, `categoria-financeira/*` | services/repositories correspondentes, models de baixas |
| 04 | **Estoque** | `entrada-estoque/*`, `estoque/*` | `EntradaEstoque*`, `AjusteEstoqueController`, `NfeXmlParser`, `MovimentacaoEstoque` |
| 05 | **Orçamentos** | `orcamento/*` | `Orcamento*` |
| 06 | **Usuários / Permissões / Autenticação** | `login`, `usuario/*`, `permissoes/*` | `AuthController`, `UsuarioController`, `PerfilController`, `PerfilService`, `CatalogoPermissoes`, middlewares |
| 07 | **Cadastros** | `cliente/*`, `fornecedor/*`, `funcionario/*`, `produto/*`, `grupo/*`, `unidadeMedida/*` | controllers/services/repositories de cada cadastro |
| 08 | **Relatórios, API interna e Dashboard** | `relatorios/*`, `api/*`, `/` | `routes/relatorios.php`, `routes/api_interna.php`, closure do dashboard |
| 09 | **Frontend global** | — | `layouts/app.blade.php`, `public/js/app.js`, `autocomplete.js`, componentes Blade |

## Classificação de risco e ordem de revisão

| Ordem | Módulo | Risco | Motivo |
|---|---|---|---|
| 1 | 01 NF-e + empresa fiscal | 🔴 máximo | Documento fiscal: duplicidade de nota, nota autorizada marcada como rejeitada, certificado, ambiente |
| 2 | 02 Vendas / PDV | 🔴 alto | Dinheiro e estoque; alimenta NF-e, contas a receber e comissões |
| 3 | 03 Financeiro | 🔴 alto | Baixas, pagamentos e saldo de caixa |
| 4 | 04 Estoque | 🟠 alto | Saldo de estoque, geração de contas a pagar, importação de XML externo |
| 5 | 05 Orçamentos | 🟠 médio | Conversão em venda |
| 6 | 06 Usuários / Permissões | 🟠 médio | Escalada de privilégio, isolamento multiempresa |
| 7 | 07 Cadastros | 🟡 médio | Isolamento multiempresa, unicidade de documentos |
| 8 | 08 Relatórios / API / Dashboard | 🟡 médio | Vazamento entre empresas, desempenho |
| 9 | 09 Frontend global | 🟡 baixo | XSS, tratamento de erros AJAX |

Cada módulo tem seu relatório em `docs/revisao/NN-nome.md`; a prontidão final está em `99-prontidao-producao.md`.
