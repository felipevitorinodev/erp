# 04 — Estoque e Produtos (entradas, importação de XML, ajuste, produtos, grupos, unidades)

Arquivos revisados: `EntradaEstoque*` (controller, requests, service, repository, models), `NfeXmlParser`, `AjusteEstoque*`, `MovimentacaoEstoque`, `Produto*`, `Grupo*`, `UnidadeMedida*`, views `entrada-estoque/*`, `estoque/*`, `produto/*`, `grupo/*`.

O que já estava correto: a confirmação de entrada trava a linha e rechecа a situação; importação duplicada bloqueada por unique `(empresa_id, chave_acesso)`; isolamento por empresa em todos os bindings; ajuste manual com `lockForUpdate`; autocomplete renderiza com `.text()`; XXE não explorável (`LIBXML_NOENT` não é usado).

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. Cancelamento de entrada podia estornar o estoque duas vezes
- **Arquivo:** `EntradaEstoqueRepository::cancelar()` / `destroy()`.
- **Causa:** a situação era checada no service com o model carregado antes; o cancelamento não travava a entrada. Duplo clique, ou cancelar + excluir ao mesmo tempo, gerava duas saídas.
- **Correção:** trava a entrada e rechecа `confirmada` dentro da transação; trava também as contas a pagar antes de checar pagamento e só cancela contas `aberta`.
- **Dados existentes:** `SELECT origem_id, COUNT(*) FROM movimentacoes_estoque WHERE origem='entrada_estoque' AND tipo='saida' GROUP BY origem_id HAVING COUNT(*) > 1`.

### ✅ 🔴 2. Desconto da NF-e importada aplicado duas vezes
- **Arquivo:** `entrada-estoque/_form.blade.php` (pré-preenchimento do XML).
- **Causa:** o formulário preenchia o desconto geral com `ICMSTot/vDesc`, que é a soma dos `vDesc` dos itens — que também eram preenchidos item a item.
- **Correção:** o desconto geral não é mais pré-preenchido; o desconto vem só dos itens.
- **Dados existentes:** entradas importadas com desconto têm `total` e conta a pagar menores. Conferir `entradas_estoque.desconto > 0 AND chave_acesso IS NOT NULL`.

### ✅ 🔴 3. Quantidades truncadas em 2 casas (colunas têm 3)
- **Arquivos:** casts de `Produto` (`estoque_*`), `MovimentacaoEstoque`, `EntradaEstoqueItem` (`quantidade` 3 casas, `preco_unitario` 4 casas); `EntradaEstoqueService::processarItens()` lê o preço com 4 casas e arredonda o total do item.
- **Causa:** `confirmar()` fazia `increment('estoque_atual', $item->quantidade)` com o valor já arredondado pelo cast. Ex.: 0,125 kg virava 0,13.
- **Observação:** os campos de quantidade na tela continuam com máscara de 2 casas (`data-decimals="2"`); mudar a máscara afeta o JS global e ficou pendente (item 9).

### ✅ 🔴 4. NF-e emitida para outro CNPJ podia ser importada
- **Arquivo:** `EntradaEstoqueService::processarXml()`.
- **Correção:** recusa o XML quando o CNPJ do destinatário existe e é diferente do CNPJ da empresa (apenas quando a empresa tem CNPJ de 14 dígitos). Ver pergunta 2.

### ✅ 🟠 5. XML "esquecido" na sessão era gravado em entrada manual
- **Arquivos:** `EntradaEstoqueService::store()`, `_form.blade.php`.
- **Causa:** qualquer entrada salva enquanto `dados_xml` estivesse na sessão recebia a chave e o XML; a NF-e verdadeira ficava bloqueada como "já importada".
- **Correção:** o formulário aberto a partir do XML envia `chave_acesso_xml` (campo oculto novo); o service só usa o XML da sessão se a chave bater.

### ✅ 🟠 6. Edição concorrente com a confirmação voltava a entrada para rascunho
- **Arquivo:** `EntradaEstoqueRepository::update()`, `EntradaEstoqueService::update()`.
- **Correção:** trava e rechecа `rascunho` dentro da transação; `situacao` não é mais regravada na edição.

### ✅ 🟠 7. Cadastro de subgrupo dava erro 500
- **Arquivo:** `GrupoRequest` — `parent_id` validava contra a tabela inexistente `categorias`.
- **Correção:** valida contra `grupos` da mesma empresa, grupo pai precisa ser de primeiro nível (evita ciclos) e diferente do próprio grupo.

### ✅ 🟠 8. Produto aceitava grupo/unidade/fornecedor de outra empresa
- **Arquivo:** `ProdutoRequest` — `exists` agora filtra por `empresa_id` e ignora excluídos.
- **Dados existentes:** `SELECT p.id FROM produtos p JOIN grupos g ON g.id=p.grupo_id WHERE g.empresa_id <> p.empresa_id` (idem unidade/fornecedor).

### ⏳ 🟠 9. Pendências
- Conta a pagar da entrada usa a soma dos itens, não o `vNF` (sem IPI, ST, frete, seguro, outras despesas). Ver pergunta 1.
- Itens do XML são casados pelo **código do fornecedor** (`cProd`) contra o código interno do produto; pode dar entrada no produto errado. Recomendo casar primeiro por EAN e criar vínculo fornecedor+cProd → produto (exige tabela nova).
- Editar entrada em rascunho perde nome/código de itens ainda não vinculados (o formulário de edição não reenvia esses campos ocultos).
- O XML fica duas vezes na sessão (`_xml_raw` e `xml_content`) e a pré-visualização já cria o fornecedor, mesmo que a importação seja abandonada.
- Máscara de quantidade com 2 casas no front.
- Índices: `produtos (empresa_id, codigo)`, `produtos (empresa_id, codigo_barras)`, `movimentacoes_estoque (origem, origem_id)` — incluídos na migration de índices do módulo 08.
- `permite_venda_sem_estoque` é validado e tem cast, mas a coluna não existe (o valor é descartado em silêncio).
- O campo "estoque atual" aparece editável na edição do produto, mas é ignorado (correto não alterar; recomendo deixá-lo somente leitura).
- `preco_custo` é gravado com o preço bruto, sem rateio do desconto.

### ❓ Perguntas
1. A conta a pagar de uma entrada importada deve ser o `vNF` da nota (com IPI/ST/frete) ou o total dos produtos?
2. Há clientes que importam notas emitidas para outro CNPJ do mesmo grupo (filial, CPF do sócio)? Se sim, o bloqueio do item 4 precisa de uma exceção.

## Como testar manualmente
1. Importar um XML com desconto por item: o desconto geral deve vir zerado e o total deve bater com `vProd - vDesc`.
2. Importar um XML cujo destinatário é outro CNPJ: mensagem de erro.
3. Importar um XML, abandonar, criar uma entrada manual: a entrada não deve receber chave de acesso.
4. Cadastrar subgrupo: deve salvar (antes: erro 500).
5. Entrada com quantidade 0,125 (via XML): estoque do produto deve somar 0,125.

Testes automatizados: `tests/Feature/EstoqueOrcamentoIntegridadeTest.php` (cancelamento duplo, 3 casas, subgrupo, grupo de outra empresa).
