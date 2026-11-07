# 02 — Vendas / PDV

Arquivos revisados: `VendaController`, `VendaService`, `VendaRepository`, `Venda`, `VendaItem`, `VendaRequest`, `App\Traits\ConversorMoeda`, views `venda/*`, geração de conta a receber e comissão na confirmação.

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. Venda com NF-e autorizada podia ser cancelada
- **Arquivo:** `app/Repositories/Vendas/VendaRepository.php` `cancelar()`.
- **Problema:** cancelar a venda devolvia o estoque e cancelava a conta a receber, mas a NF-e continuava autorizada na SEFAZ (documento fiscal válido de uma venda que "não existe").
- **Correção:** bloqueia o cancelamento se houver NF-e `autorizada` (cancele a NF-e antes) ou `pendente` (consulte a SEFAZ antes).
- **Risco:** baixo; não altera dados.

### ✅ 🔴 2. Duplo clique em "Cancelar venda" devolvia o estoque duas vezes
- **Arquivo:** `VendaRepository::cancelar()`.
- **Causa:** a situação era checada no model carregado pela rota (antes da transação), sem trava. Duas requisições simultâneas viam "confirmada" e estornavam o estoque cada uma.
- **Correção:** dentro da transação, relê a venda com `lockForUpdate()` e recusa se já estiver cancelada (mesmo padrão já usado em `confirmar()`).
- **Risco:** baixo.

### ✅ 🟠 3. Criação + confirmação da venda não eram atômicas
- **Arquivo:** `VendaService::persistirVenda()`.
- **Causa:** `repository->store()` e `repository->confirmar()` rodavam em transações separadas. Se a confirmação falhasse (ex.: erro ao gerar conta a receber), a venda ficava gravada "em andamento" mas o usuário via um erro de validação e reenviava, duplicando a venda. O mesmo vale para a emissão de NF-e direta (`nfe.store`).
- **Correção:** `persistirVenda()` envolve tudo em `DB::transaction()` (o corpo antigo virou `gravarVenda()`).
- **Risco:** baixo.

### ✅ 🟠 4. Numeração da venda: concorrência, reuso de número e comparação como texto
- **Arquivo:** `VendaRepository::proximoNumero()`.
- **Causa:** `max('numero')` sem trava (duas vendas simultâneas recebiam o mesmo número), ignorava vendas excluídas (soft delete → número reaproveitado) e comparava texto (após 999999 a ordenação quebra).
- **Correção:** trava a linha da empresa (`lockForUpdate`, válida porque agora é chamado dentro da transação de `persistirVenda`), inclui excluídas (`withTrashed`) e usa `MAX(CAST(numero AS UNSIGNED))`.
- **Risco:** baixo. Não cria índice único em `vendas(empresa_id, numero)` porque dados existentes podem já ter números repetidos (ver pendência 8).

### ✅ 🟠 5. Quantidade arredondada para 2 casas e cast incorreto dos itens
- **Arquivos:** `App\Traits\ConversorMoeda::parseQuantidade()` (arredondava para 2 casas; colunas de quantidade têm 3), `VendaItem::$casts` (`quantidade` e `preco_unitario` como `decimal:2`; colunas são 3 e 4 casas).
- **Efeito:** quantidade `0,004` virava 0 e o item era descartado em silêncio; quantidades fracionadas eram lidas arredondadas para baixa de estoque e NF-e.
- **Correção:** `parseQuantidade` usa 3 casas; casts `decimal:3` / `decimal:4`. As telas continuam formatando com `number_format(…, 2)`.
- **Risco:** baixo. Afeta também entradas de estoque e orçamentos (mesmo trait), de forma correta.

### ✅ 🟡 6. Desconto maior que o subtotal era aceito
- **Arquivo:** `VendaService::calcularTotais()`.
- **Causa:** o total era "travado" em zero mas o desconto gravado ficava maior que o subtotal; a NF-e falhava no rateio.
- **Correção:** erro de validação no campo `desconto`.

### ✅ 🟡 7. Total do item não era arredondado antes de somar
- **Arquivo:** `VendaService::processarItens()`. O subtotal era a soma de valores não arredondados, mas cada item era gravado arredondado pelo banco (diferença de 1 centavo entre subtotal e soma dos itens). Agora `round(…, 2)` por item.

### ⏳ 🟡 8. Sem índice único em `vendas (empresa_id, numero)`
- Dados existentes podem ter duplicidade (por causa do bug 4). Recomendo verificar com `SELECT empresa_id, numero, COUNT(*) FROM vendas GROUP BY 1,2 HAVING COUNT(*) > 1` e, se vazio, criar o índice em migration futura.

### ⏳ 🟡 9. N+1 em `processarItens()` / `montarAvisoEstoque()`
- Um `Produto::find()` por item, duas vezes. Irrelevante para carrinhos pequenos; não alterado.

### ⏳ 🟡 10. `update()` + confirmação em transações separadas
- Se a confirmação falhar após editar, a venda fica salva "em andamento" com mensagem de erro. Não duplica dados; não alterado.

## Como testar manualmente
1. Emitir NF-e de uma venda e tentar cancelar a venda: deve aparecer "cancele a NF-e primeiro".
2. Abrir a mesma venda confirmada em duas abas e clicar "Cancelar" nas duas: o estoque volta uma vez só (ver histórico de estoque).
3. Excluir a última venda "em andamento" e criar outra: o número não se repete.
4. Lançar desconto maior que o subtotal: erro no campo desconto.

Testes automatizados: `tests/Feature/VendaIntegridadeTest.php` (4 testes).

## Layout do PDV (pedido de 20/10)
Só tela e JavaScript (`venda/pdv.blade.php`, `public/js/pdv.js`, `app.css`); o contrato do `POST` de finalização não mudou.
- O painel lateral ficou só com cliente, resumo da forma escolhida e o botão **Pagamento (F4)**. Forma de pagamento, desconto, acréscimo, valor recebido e troco foram para uma tela de pagamento própria, aberta pelo F4.
- Na tela de pagamento: formas numeradas (teclas 1–9 ou ←/→), desconto (F7), valor recebido (F8) com troco calculado na hora, aviso quando a forma é a prazo e não há cliente, Enter confirma, Esc volta. Recebido menor que o total é recusado.
- Atalhos no caixa: `3*código` lança 3 unidades, F3 cliente, F9 altera a quantidade do último item, Ctrl+Del remove o último item, Ctrl+Shift+Del cancela a venda, botão "Atalhos" lista tudo. O foco volta sempre para o campo de código.
- Tela de sucesso mostra o troco; Enter ou N inicia nova venda.
