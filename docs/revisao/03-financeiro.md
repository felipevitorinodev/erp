# 03 — Financeiro (contas a receber/pagar, baixas, comissões, fluxo de caixa, formas de pagamento, categorias)

Arquivos revisados: controllers/services/repositories/models/requests de `ContasReceber`, `ContasPagar`, `Comissoes`, `FluxoCaixa`, `FormasPagamento`, `CategoriasFinanceiras` (+ `CategoriaPadraoService`), models de baixa, views correspondentes, integração com `VendaRepository`.

O que já estava correto: isolamento por empresa em todos os bindings (`garantirEmpresa()`), revalidação de `categoria_id`/`cliente_id`/`fornecedor_id`/`venda_id` por empresa nos requests, Blade escapado, CSRF em todos os forms, listagens paginadas, `lockForUpdate` + releitura do saldo na baixa e no pagamento de comissão.

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. Baixa e cancelamento concorrentes "ressuscitavam" conta cancelada ou cancelavam conta paga
- **Arquivos:** `ContaReceberRepository::receber()/cancelar()`, `ContaPagarRepository::pagar()/cancelar()`.
- **Causa:** a baixa travava a linha mas só rechecava o saldo, não a situação; o cancelamento fazia `update` sem trava nem condição. Resultado possível: conta `cancelada` com `valor_pago > 0` (o dinheiro some do fluxo de caixa, que só soma `paga/parcial`), ou conta cancelada (por cancelamento de venda) voltando a `parcial`.
- **Correção:** a baixa recusa conta `paga/cancelada` após o `lockForUpdate`; o cancelamento roda em transação, trava e só cancela conta `aberta` com `valor_pago = 0`. Services capturam a exceção.
- **Risco:** baixo. Para auditar dados antigos: `SELECT * FROM contas_receber WHERE situacao='cancelada' AND valor_pago>0` (idem `contas_pagar`).

### ✅ 🔴 2. Cancelamento de venda concorrente com baixa da conta a receber
- **Arquivo:** `VendaRepository::cancelar()`.
- **Causa:** verificava "existe conta paga/parcial" com SELECT simples e depois cancelava `aberta` **e `parcial`**. Uma baixa entre a checagem e o update deixava conta recebida como cancelada.
- **Correção:** trava as contas a receber e comissões da venda antes de checar, e só cancela contas `aberta` (complementa a correção 2 do módulo Vendas).

### ✅ 🔴 3. Editar conta a receber desfazia o vínculo com a venda
- **Arquivo:** `ContaReceberService::update()` / `montarDados()`; `conta-receber/_form` limpa `venda_id` ao trocar o cliente.
- **Causa:** `venda_id` vinha do formulário na edição. Uma conta gerada pela venda perdia o vínculo, podia ser excluída e não era mais cancelada junto com a venda.
- **Correção:** na edição, `venda_id` é ignorado. Na criação manual, recusa vincular venda que já tem conta a receber ativa.
- **Risco:** baixo. Dados antigos: procurar `observacoes = 'Gerada automaticamente na confirmação da venda.' AND venda_id IS NULL`.

### ✅ 🟠 4. Edição de conta sem trava, concorrente com baixa
- **Arquivos:** `ContaReceberRepository::update()`, `ContaPagarRepository::update()`.
- **Causa:** `valor_pago` e a nova situação eram calculados com o model carregado antes; uma baixa no meio gerava conta `paga` com saldo ou `valor < valor_pago`.
- **Correção:** a validação e o recálculo da situação foram para dentro de uma transação com `lockForUpdate` no repository.

### ✅ 🟠 5. Baixa aceitava data inválida/futura e forma de pagamento sem limite
- **Arquivos:** `ContaReceberService::receber()`, `ContaPagarService::pagar()`.
- **Correção:** `data_pagamento` `date|before_or_equal:today`, `forma_pagamento` `max:100` (antes: erro 500 do banco).

### ✅ 🟠 6. Tipo da categoria (receita/despesa) podia ser trocado pelo input oculto
- **Arquivo:** `CategoriaFinanceiraRepository::update()` — `tipo` não é mais alterado na edição.

### ✅ 🟡 7. Mensagem de erro SQL exibida ao usuário no pagamento de comissão
- **Arquivo:** `ComissaoService::pagar()` — `QueryException` é registrada no log e mostra mensagem genérica.

### ✅ 🟡 8. Fluxo de caixa com data inválida gerava erro 500
- **Arquivo:** `FluxoCaixaController::montarPeriodo()` — valida `data_inicio`/`data_fim`.

### ✅ 🔴 9. Geração de conta a receber dependia do **nome** da forma de pagamento
- **Arquivos:** `ContaReceberRepository::gerarDaVendaConfirmada()`, `VendaRepository::store()/update()`, `Venda`, `FluxoCaixaController::entradasDeVendasAvista()`, migration `2026_10_20_000004_add_forma_pagamento_id_to_vendas_table`.
- **Problema:** se a forma foi renomeada, desativada, excluída ou digitada diferente, a venda a prazo era confirmada **sem conta a receber** e o fluxo de caixa a contava como recebida à vista.
- **Correção (decisão do usuário: as duas opções):**
  - A venda grava `forma_pagamento_id` (nullable, FK `nullOnDelete`) além do nome, que continua gravado (NF-e e telas usam o nome; contrato AJAX/forms inalterado). O ID é resolvido no repositório ao criar/editar, inclusive na venda gerada por orçamento.
  - Na confirmação, a forma é lida pelo ID (mesmo que depois renomeada/desativada/excluída); sem ID, pelo nome. Se o nome foi informado e não há forma ativa com esse nome, **a confirmação é recusada** com mensagem.
  - Venda **sem** forma informada continua sendo confirmada sem conta a receber (comportamento anterior mantido).
  - Fluxo de caixa usa o tipo da forma vinculada quando existe.
- **Backfill:** casa o nome (sem diferenciar maiúsculas) dentro da empresa, preferindo formas ativas e não excluídas. Vendas sem correspondência ficam com `forma_pagamento_id` null. Conferir depois de migrar: `SELECT id, numero, forma_pagamento FROM vendas WHERE forma_pagamento <> '' AND forma_pagamento_id IS NULL AND situacao = 'em_andamento'` — essas vendas só confirmam após corrigir a forma.
- **Risco:** o PDV passa a receber erro ao confirmar com forma inexistente (antes confirmava silenciosamente). Não há unicidade de `(empresa_id, nome)` em `formas_pagamento`; com nomes duplicados, vale a primeira ativa.

### ⏳ 🟠 10. Pagamento de comissão identificado por texto em `observacoes`
- `ComissaoService::gerarContaPagarDaComissao()` usa `observacoes = 'comissao_id:N'` como chave de idempotência. Recomendo coluna `comissao_id` (unique) em `contas_pagar` com backfill.

### ⏳ 🟠 11. Índices ausentes
- `contas_receber`/`contas_pagar` sem índice em `(empresa_id, situacao, data_vencimento)` — usados em listagem, dashboard e relatórios. Tratado no módulo 08 (migration de índices).
- Sem unique em `comissoes.venda_id`, `formas_pagamento (empresa_id, nome)`, `categorias_financeiras (empresa_id, nome, tipo)`: exigem checar duplicados existentes antes.

### ⏳ 🟡 12. Outros
- Botão de excluir aparece na listagem para contas pagas/vinculadas (o servidor bloqueia).
- `valor` sem limite superior no request (acima de `decimal(12,2)` gera erro de banco).
- Exclusão de categoria/forma em uso não é verificada.
- `FormaPagamentoRequest` exige `empresa_id` vindo do formulário (ignorado depois; pode ser removido).
- Buscas `LIKE` não escapam `%`/`_`.
- Dinheiro manipulado como `float` com `round(…, 2)`; funciona, mas `bcmath`/centavos seria mais robusto.

### ❓ Perguntas
1. ~~Para a forma de pagamento: gravar `forma_pagamento_id` ou bloquear a confirmação?~~ **Respondida: ambas** (item 9).
2. Comissão pode ser paga antes de o cliente pagar a venda? Hoje é permitido.

## Como testar manualmente
1. Abrir uma conta a receber em duas abas; cancelar numa e registrar recebimento na outra: o recebimento deve ser recusado.
2. Editar uma conta gerada por venda e trocar o cliente: a conta continua vinculada à venda.
3. Registrar recebimento com data futura: erro no campo data.
4. Fluxo de caixa com `?data_inicio=abc`: volta com erro de validação, não 500.

Testes automatizados: `tests/Feature/FinanceiroIntegridadeTest.php` (5 testes).
