# 08 — Relatórios, API interna (AJAX) e Dashboard

Arquivos revisados: `routes/relatorios.php`, `routes/api_interna.php`, closure do dashboard em `routes/web.php`, views `relatorios/*`, `pdf/relatorio.blade.php`, `config/app.php`.

O que já estava correto: todas as consultas filtram `empresa_id` da sessão; listagens paginadas; `LIKE` parametrizado (sem SQL injection).

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🟠 1. Relatórios de contas a receber/pagar acessíveis só com `relatorios`
- **Arquivo:** `routes/relatorios.php` (tela, CSV e PDF de contas a receber e a pagar).
- **Causa:** o menu exige `financeiro`, mas as rotas não; um perfil com só `relatorios` abria os relatórios financeiros pela URL.
- **Correção:** as 6 rotas exigem `permissao:relatorios` **e** `permissao:financeiro`. Nomes de rota inalterados.

### ✅ 🟠 2. Exportação CSV permitia injeção de fórmula
- **Arquivo:** `routes/relatorios.php` (`$csvSeguro`, aplicado às 5 exportações).
- **Causa:** nomes/descrições começando com `=`, `+`, `-`, `@` eram executados como fórmula no Excel (nomes de produto podem vir do XML de fornecedor).
- **Correção:** essas células recebem um apóstrofo no início; valores numéricos negativos não são alterados.

### ✅ 🟡 3. Data inválida no filtro gerava erro 500
- **Arquivo:** `$periodoPadrao` em `routes/relatorios.php` — `data_inicio`/`data_fim` validados.

### ✅ 🟡 4. Índices ausentes
- **Arquivo:** migration **nova** `2026_10_20_000003_add_indices_consulta.php` (somente índices não únicos; não altera dados):
  - `contas_receber`/`contas_pagar (empresa_id, situacao, data_vencimento)` — listagens, dashboard, relatórios;
  - `vendas (empresa_id, data_venda)` — relatórios e dashboard;
  - `produtos (empresa_id, codigo)` e `(empresa_id, codigo_barras)` — PDV, busca, importação de XML;
  - `movimentacoes_estoque (origem, origem_id)` — histórico/estorno.
- Em tabelas grandes, o `ALTER TABLE` pode levar alguns segundos; rodar fora do horário de uso.

### ⏳ 🟠 5. Endpoints AJAX sem middleware de permissão
- `routes/api_interna.php`: as buscas de fornecedores, funcionários (com `percentual_comissao`), produtos (com `preco_custo`) e vendas (incluindo canceladas) só exigem login. Um vendedor pode consultá-los pela URL.
- **Por que não corrigi:** cada endpoint é usado por várias telas (PDV, venda, orçamento, entrada, NF-e). Restringir sem mapear tela por tela pode quebrar o PDV de quem tem só `vendas`. Recomendo: remover `preco_custo` da resposta para quem não tem `produtos`, e filtrar vendas canceladas sem `vendas.ver_canceladas`.

### ⏳ 🟡 6. Relatórios carregam tudo em memória
- As telas de contas fazem `->get()` de todo o período só para somar; CSV/PDF fazem `->get()` sem limite. Com volume alto, risco de estouro de memória/timeout no DomPDF. Recomendo somas com `SUM ... GROUP BY`, `->lazy()` no CSV e limite de período/linhas no PDF.

### ⏳ 🟡 7. `/api/vendas/ultima` usada pelo PDV
- Retorna a última venda da empresa, não a do operador; com dois caixas pode mostrar o número da venda do outro. Tratado no módulo 09 (PDV).

### ❓ Perguntas
1. Com o filtro de situação "Todas", os totais dos relatórios de contas **somam também as canceladas**. Deve excluir as canceladas dos totais?
2. **Fuso horário:** `config/app.php` usa `UTC`. Depois das 21h (horário de Brasília) o PDV grava `data_venda` do dia seguinte, e "vencidos"/"hoje" no dashboard ficam adiantados. A correção é `'timezone' => 'America/Sao_Paulo'`, mas os `created_at` já gravados em UTC passariam a ser exibidos 3h adiantados. Posso trocar?

## Como testar manualmente
1. Perfil com só "Relatórios": menu não mostra contas e a URL `/relatorios/contas-receber` retorna 403.
2. Conta com descrição `=1+1`: no CSV aparece `'=1+1` como texto.
3. `/relatorios/vendas?data_inicio=abc`: volta com erro de validação.

**Antes do deploy:** `php artisan migrate` aplica `2026_10_20_000003`.

Testes automatizados: `tests/Feature/RelatoriosSegurancaTest.php` (3 testes).
