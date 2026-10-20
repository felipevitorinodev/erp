# 05 — Orçamentos

Arquivos revisados: `OrcamentoController`, `OrcamentoRequest`, `OrcamentoService`, `OrcamentoRepository`, models `Orcamento`/`OrcamentoItem`, views `orcamento/*`.

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. Totais do orçamento vinham do navegador
- **Arquivo:** `OrcamentoService::store()/update()`.
- **Causa:** `subtotal` e `total` eram gravados direto do POST; na aprovação esse total vira o total da venda, da conta a receber e da comissão.
- **Correção:** novo `calcularTotais()` recalcula a partir dos itens; desconto maior que o subtotal é recusado (`withErrors('desconto')`).
- **Dados existentes:** `SELECT o.id FROM orcamentos o WHERE ABS(o.total - ((SELECT SUM(total) FROM orcamento_itens i WHERE i.orcamento_id=o.id) - o.desconto + o.acrescimo)) > 0.01`.

### ✅ 🔴 2. Aprovação dupla gerava duas vendas (e baixava estoque duas vezes)
- **Arquivos:** `OrcamentoService::aprovar()`, `OrcamentoRepository::travarPendente()` (novo método público no repository existente).
- **Correção:** dentro da transação, relê o orçamento com `lockForUpdate` e exige `pendente`. `recusar`, `cancelar`, `update` e `destroy` usam a mesma trava.

### ✅ 🔴 3. XSS na tela de novo orçamento
- **Arquivo:** `orcamento/create.blade.php` — nome e código do produto eram concatenados em HTML. Produtos podem ser criados automaticamente a partir do XML de um fornecedor, então um `xProd` malicioso executaria script.
- **Correção:** função `esc()` aplicada a nome, código e id.

### ✅ 🟠 4. Cliente de outra empresa aceito no orçamento
- **Arquivo:** `OrcamentoRequest` — `withValidator` confere `empresa_id` do cliente (mesmo padrão do `VendaRequest`).

### ✅ 🟠 5. Numeração duplicada/reutilizada
- **Arquivo:** `OrcamentoRepository::proximoNumero()/store()` — número calculado dentro da transação, com trava na empresa, incluindo excluídos e com comparação numérica (igual à correção de vendas).

### ✅ 🟠 6. Salvar orçamento sem transação
- `store`/`update` agora em `DB::transaction` (antes, falha no meio deixava orçamento sem itens).

### ✅ 🟡 7. Quantidade truncada em 2 casas e preço em 2 casas
- `processarItens()` usa `parseQuantidade` (3 casas) e preço com 4 casas; casts de `OrcamentoItem` ajustados às colunas.

### ✅ 🟡 8. Orçamento aprovado podia ser excluído
- `OrcamentoRepository::destroy()` recusa `aprovado`.

### ⏳ / ❓ Pendências e perguntas
- ❓ A venda gerada usa `data_orcamento` como `data_venda`. Não deveria ser a data da aprovação?
- ❓ O orçamento não guarda vendedor (`funcionario_id`), então vendas vindas de orçamento nunca geram comissão. É intencional?
- `validade_dias` aparece na tela mas não é salvo.
- A mensagem de erro da aprovação mostra `$ex->getMessage()` de qualquer exceção (pode expor erro SQL).
- Sem unique em `orcamentos (empresa_id, numero)` — exige checar duplicados existentes antes.

## Como testar manualmente
1. Criar orçamento, alterar o campo oculto `total` pelo DevTools e salvar: o total gravado deve ser o dos itens.
2. Abrir o mesmo orçamento em duas abas e aprovar nas duas: só uma venda; a segunda aba mostra "já foi processado".
3. Cadastrar produto com nome `<img src=x onerror=alert(1)>` e buscá-lo na tela de orçamento: o texto aparece literal.

Testes automatizados: `tests/Feature/EstoqueOrcamentoIntegridadeTest.php` (total recalculado, aprovação dupla).
