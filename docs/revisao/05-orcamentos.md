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
- ✅ A venda gerada usava `data_orcamento` como `data_venda`. **Respondida:** agora usa a data da aprovação (`OrcamentoService::aprovar()`). A venda também grava `forma_pagamento_id` (módulo 03, item 9); se a forma do orçamento não existir mais, a venda é gerada "em andamento" (como antes) e só pode ser confirmada depois de corrigir a forma.
- ❓ O orçamento não guarda vendedor (`funcionario_id`), então vendas vindas de orçamento nunca geram comissão. É intencional?
- `validade_dias` aparece na tela mas não é salvo.
- A mensagem de erro da aprovação mostra `$ex->getMessage()` de qualquer exceção (pode expor erro SQL).
- Sem unique em `orcamentos (empresa_id, numero)` — exige checar duplicados existentes antes.

## Como testar manualmente
1. Criar orçamento, alterar o campo oculto `total` pelo DevTools e salvar: o total gravado deve ser o dos itens.
2. Abrir o mesmo orçamento em duas abas e aprovar nas duas: só uma venda; a segunda aba mostra "já foi processado".
3. Cadastrar produto com nome `<img src=x onerror=alert(1)>` e buscá-lo na tela de orçamento: o texto aparece literal.

Testes automatizados: `tests/Feature/EstoqueOrcamentoIntegridadeTest.php` (total recalculado, aprovação dupla).

## Orçamento → venda ou ordem de serviço (pedido de 20/10)
- O botão "Aprovar" passou a se chamar **"Gerar venda"** (mesma rota `orcamento.aprovar`, mesmo comportamento).
- Novo botão **"Gerar OS"** (só para quem tem a permissão `ordens_servico`): copia cliente, itens, totais, forma de pagamento, observações e a previsão de entrega para uma OS aberta; o orçamento fica `aprovado` com `ordem_servico_id`. Não gera venda nem mexe em estoque — isso acontece ao faturar a OS. Em duas abas, só a primeira converte (mesmo lock de `travarPendente`). Depois de converter, abre a edição da OS para preencher o equipamento.
- Rota nova: `POST orcamento/{orcamento}/gerar-os` (`orcamento.gerar-os`), middleware `permissao:orcamentos` + `permissao:ordens_servico`.
- Coluna nova `orcamentos.ordem_servico_id` (nullable, FK com `nullOnDelete`) na migration `2026_10_21_000002`.

## Ordens de serviço (módulo novo)
Assistência técnica genérica: cliente, técnico (funcionário), abertura/previsão/conclusão, equipamento, marca, modelo, nº de série, acessórios, defeito relatado, diagnóstico, serviço executado/laudo, garantia, peças e serviços (produtos `tipo` 1 ou 2), desconto/acréscimo, forma de pagamento.

- **Fluxo:** Aberta → Em execução → Aguardando peça → Concluída (troca livre entre essas quatro pela tela) → **Faturar** gera a venda e marca "Faturada". Cancelada é final.
- **Faturar** segue a mesma regra da aprovação de orçamento: venda com `data_venda` = hoje; forma à vista confirma a venda (baixa estoque só de produto com `controla_estoque`, gera recebimento); a prazo deixa a venda em andamento. Exige ao menos um item. A venda leva "OS #número – equipamento" nas observações.
- A OS pode ser aberta só com o defeito (itens opcionais até o faturamento). Totais sempre recalculados no servidor.
- OS faturada não pode ser editada, cancelada nem excluída.
- PDF da OS (comprovante com campos de assinatura do cliente e do técnico).
- Permissão nova `ordens_servico` ("Ordens de serviço", grupo Vendas). Entra no perfil padrão `financeiro`; a migration também a adiciona a todo perfil salvo que já tinha `orcamentos` (o `down()` remove).
- Rotas (prefixo `ordem-servico`, nomes `ordem-servico.*`): `index`, `create` (`/nova`), `store`, `show` (`{os}/ver`), `edit`, `update`, `situacao` (POST), `faturar` (POST), `cancelar` (POST), `destroy` (DELETE), `pdf`.
- Tabelas novas `ordens_servico` e `ordem_servico_itens` (migration `2026_10_21_000002`).

### Comissão do técnico (respondido: deve gerar e ser configurável por técnico)
- A venda gerada ao faturar leva o técnico da OS em `vendas.funcionario_id`, e a comissão nasce quando a venda é confirmada (na hora se a forma for à vista; depois, se for a prazo).
- No cadastro do funcionário há dois campos novos: **Comissão em OS (%)** e **Base da comissão em OS**. A base pode ser "Total da OS (peças + serviços)" ou "Só serviços (mão de obra)". "Só serviços" soma os itens com produto `tipo = 2` e rateia o desconto/acréscimo geral pela proporção total/subtotal.
- Vendas comuns continuam usando a "Comissão (%)" geral sobre o total. A regra é decidida em `VendaRepository::calcularComissao()` por existir OS com `venda_id` daquela venda. Edição de venda confirmada recalcula a comissão pendente pela mesma regra.
- `comissoes.base_calculo` guarda a base usada; a tela de comissões mostra a coluna "Base" e a OS de origem.
- Migration `2026_10_21_000003`: `funcionarios.comissao_os_percentual` (default 0, **preenchido com o percentual geral para quem já existe**), `funcionarios.comissao_os_base` (default `total`), `comissoes.base_calculo` (nullable). Comissões antigas ficam com base vazia ("—").

Testes: `tests/Feature/OrdemServicoTest.php` (10 testes: abertura, totais, faturar à vista/a prazo/sem itens/duas vezes, bloqueio após encerrar, situação, orçamento → OS, isolamento e permissão, telas e PDF).
