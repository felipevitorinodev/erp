# Revisão de desempenho das consultas — 14/11/2026

Revisão das consultas ao banco em todas as telas, listagens, documentos, APIs internas e relatórios, com foco nas telas pesadas e em volume grande de dados. Nenhum dado foi alterado ou apagado: as duas migrations só **acrescentam** índices e colunas virtuais (calculadas pelo MySQL, sem gravar nada nas linhas).

## Resumo

- **143 rotas medidas** antes e depois. Das 131 comparáveis, **89 ficaram pelo menos 15% mais rápidas**; as outras ficaram praticamente iguais: rotas leves (5 a 40 ms), PDFs (o tempo é do gerador de PDF, não do banco) e buscas "contém o texto" (ver "O que ainda pode melhorar").
- **Um erro corrigido e outro evitado**: o ranking de clientes com período de 1 ano dava erro 500; o relatório de clientes com débito estava a poucos milhares de devedores do mesmo erro.
- **Cache entre telas**: com cache, trocar de página, voltar à tela, abrir gráficos e exportar reaproveita contagens e totais. As listagens principais ficam entre 20 e 50 ms.

| Tela | Antes | Depois (sem cache) | Com cache |
|---|---:|---:|---:|
| Tela inicial | 434 ms | 223 ms | 20 ms |
| Lista de vendas | 2,2 s | 376 ms | 36 ms |
| Busca de vendas por cliente | 5,6 s | 1,6 s | 329 ms |
| Autocompletar de vendas | 5,9 s | 299 ms | — |
| Lista de clientes | 896 ms | 281 ms | 26 ms |
| Comissões de um ano | 2,2 s | 1,0 s | 49 ms |
| Fluxo de caixa (90 dias) | 2,7 s | 450 ms | — |
| Relatório de vendas (1 ano) | 7,1 s | 1,7 s | 31 ms |
| CSV de vendas (1 ano) | ≈ 40 min (estimado) | 76 s | — |
| Produtos mais vendidos (1 ano) | 53 s | 6,9 s | 3,4 s |
| Ranking de clientes (1 ano) | erro 500 | 3,1 s | 2,5 s |
| Clientes com débito | 1,5 s | 722 ms | 656 ms |
| CSV de inadimplência | 54 s | 16 s | — |
| CSV de estoque | 17,7 s | 8,7 s | — |

- **O que continua pesado**: PDFs de relatório com 1.000 linhas (10 a 30 s; o banco responde em milissegundos e o tempo é todo do gerador de PDF) e buscas "contém o texto". As opções estão em "O que ainda pode melhorar".

## O que fazer para ativar

1. **Backup do banco** (como sempre antes de migration).
2. `php artisan migrate` — roda as duas migrations desta revisão:
   - `2026_11_13_000001_add_sequencia_numerica_indexada`: colunas virtuais `numero_seq` (vendas, orçamentos, OS) e `codigo_seq` (produtos) + índice (empresa, coluna).
   - `2026_11_13_000002_add_indices_desempenho_listagens_relatorios`: 19 índices de listagem e relatório (tabela mais abaixo).
   - Atenção: o `migrate` também vai rodar `2026_11_14_000001_create_nfe_cartas_correcao_table`, que é de outra sessão (carta de correção), não desta revisão.
   - No MySQL 8 a criação de índice é feita sem travar a tabela para leitura e escrita. No banco de teste (1,1 milhão de vendas, 3,3 milhões de itens) a segunda migration levou 2 minutos; o tempo é proporcional ao volume. Ambas podem ser rodadas de novo sem efeito (pulam o que já existe) e têm `down` (testado: remove e recria os 19 índices).
3. `php artisan optimize` — a rota `/` (tela inicial) mudou em `routes/web.php` e o cache de rotas precisa ser refeito.
4. Nada mais: o cache entre telas usa o `CACHE_STORE=database` que já está configurado.

## Como foi medido

- Banco **sintético e isolado** (contêiner `erp_bench_mysql`, MySQL 8.0, porta 3307). O banco real (`softcomshop_mysql`) não foi usado em nenhum momento — nem para medir, nem para testar.
- Volume: 2 empresas. A empresa medida tem 1 milhão de vendas (3,3 milhões de itens no banco), 333 mil contas a receber, 180 mil contas a pagar, 200 mil clientes, 50 mil produtos, 283 mil comissões e 2 milhões de movimentações de estoque. A segunda empresa (100 mil vendas) existe para conferir que uma empresa não lê nem conta os dados da outra.
- Cada tempo é a requisição inteira passando pelo Laravel (rota, middleware, consultas, Blade, PDF/CSV), não só o SQL. Telas: mediana de 3 execuções. Relatórios: 1 execução (alguns levavam minutos).
- **Antes** = código original (cópia congelada antes de qualquer mudança). **Depois** = código atual, com cache **desligado** (`CACHE_STORE=null`), ou seja, o pior caso: primeira abertura depois de alguém gravar algo. **Com cache** = mesma rota aberta de novo com `CACHE_STORE=database` (o que acontece ao trocar de página, voltar à tela, abrir gráficos e depois exportar).
- "Mês" = mês atual; "1 ano" = últimos 12 meses (≈ 333 mil vendas).
- Rotas leves (10–40 ms) variam alguns ms entre execuções; 16 delas aparecem até 26 ms mais lentas, o que é ruído. Várias mostram uma consulta a mais (a busca da empresa, ~1 ms): no medidor o mesmo usuário é reaproveitado entre requisições e o layout antigo pulava essa busca; numa requisição real ela já acontecia em toda tela.
- Consultas = quantidade de comandos SQL da requisição, antes → depois.
- Testes automatizados: 360 de 360 passando (rodados num contêiner sem rede, com SQLite em memória).

## Técnicas aplicadas (onde e por quê)

| Técnica | Onde | Efeito |
|---|---|---|
| **Índices compostos "cobrindo" a consulta** (filtro + ordem + colunas lidas, com o `id` na posição da ordenação) | 19 índices: listagens de vendas, contas, cadastros, comissões, orçamentos, OS, entradas, NF-e, movimentações; relatórios por período | A página sai direto do índice, sem ler a tabela nem ordenar em disco. Ex.: página de vendas 261 ms → 0,16 ms no plano de execução; relatório de vendas do ano 14,7 s → 0,26 ms |
| **Dica de índice** (`USE INDEX`, classe `App\Support\IndiceBanco`) | Consultas de vendas por período (relatórios, painel, totais das comissões, fluxo de caixa), página de comissões por período, baixas do fluxo de caixa, contagem de vencidas, clientes com débito | O otimizador do MySQL escolhia índices que não filtram data e lia todas as vendas da empresa, linha a linha. A dica só é aplicada no MySQL e só se o índice existir |
| **`whereHas` (EXISTS por linha) → subconsulta `IN` ou `JOIN`** (classe `App\Support\BuscaPorNome`) | Buscas por nome de cliente/fornecedor em vendas, contas, orçamentos, OS, entradas, relatórios e na API de vendas; filtro de período das comissões | O MySQL avalia o EXISTS para cada linha da tabela principal; a subconsulta busca os poucos clientes pelo nome uma vez. Busca na lista de vendas 5–6 s → 1,6 s; API de vendas 5,9 s → 0,3 s |
| **Filtro da empresa dentro do EXISTS + partir da tabela certa** | Fluxo de caixa (baixas a receber/pagar, vendas à vista) | O MySQL partia das contas pagas (sem o filtro, as de todas as empresas: 1 milhão de leituras) e procurava as baixas de cada uma: 0,7–1,2 s por consulta em 90 dias. Agora parte das baixas do período e busca cada conta pela chave: 0,04–0,07 s |
| **Tabela derivada (equivale a CTE) + JOIN** | Ranking de clientes, clientes com débito | Agrega primeiro, depois junta só um nome por cliente. Substitui `WHERE id IN (dezenas de milhares de ids)`, que quebrava acima de 65.535 ids |
| **Funções de janela** (`COUNT(*) OVER ()`, `SUM() OVER ()`) | Produtos mais vendidos | Página, total de produtos e somas numa única consulta, em vez de agrupar 3 vezes |
| **Colunas geradas virtuais indexadas** | Próximo número de venda/orçamento/OS e próximo código de produto | `MAX(CAST(numero))` lia todas as vendas da empresa (1,5 s); `MAX(numero_seq)` é respondido pelo índice ("Select tables optimized away") |
| **Contagem da paginação no cache** (classe `App\Support\ContagemPaginada`) | Vendas, contas a receber/pagar, clientes, orçamentos, OS, entradas, histórico de estoque (comissões e inadimplência: a quantidade sai junto com os totais) | Trocar de página não refaz o `COUNT(*)` de centenas de milhares de linhas; o total é recalculado só quando alguém grava naquelas tabelas |
| **Cache entre telas com invalidação automática** (classe `App\Support\CacheDados`) | Tela inicial, totais do relatório de vendas, ranking de clientes, período anterior dos produtos mais vendidos, totais das comissões, contagens | A tela, os gráficos (`/resumo`), a paginação (`/dados`) e o PDF reaproveitam o mesmo cálculo. Toda gravação (insert/update/delete) nas tabelas do assunto troca a "versão" do cache **depois do commit**, então nunca aparece número velho. Separado por empresa. Listas com mais de 5.000 itens não são guardadas (sairia mais caro ler do cache do que do índice) |
| **Reaproveitar o que já foi buscado** | Empresa da sessão (`Empresa::atual()` uma vez por requisição, antes repetida no layout, PDV, documentos, NF-e); painel (uma consulta do mês serve "hoje" e o gráfico); inadimplência (o total da paginação é o `qtd` que os totais por faixa já contaram); relatório de vendas (mesmos totais na tela, gráfico, dados e PDF) | Menos idas ao banco por tela |
| **Exportação CSV em lotes pela chave** (classe `App\Support\LeituraEmLotes`) | CSV de vendas, contas, inadimplência, estoque; produtos mais vendidos por cursor | `lazy()` usa `LIMIT/OFFSET`: o lote 600 relê e descarta 300 mil linhas, e o CSV anual de vendas levaria ~40 min. Agora lê os ids em ordem uma vez e busca cada lote pela chave primária: 76 s |
| **`tmp_table_size` da sessão em 256 MB** | Agrupamento dos produtos mais vendidos | Com o padrão de 16 MB o `GROUP BY` de 1 milhão de itens (1 ano) ia para tabela temporária em disco. Vale só para a conexão daquela requisição, não para o servidor |
| **PDF: Blade mais leve e HTML compactado** | `resources/views/pdf/relatorio.blade.php`, `App\Relatorios\PdfRelatorio` | Montagem do HTML de 900 linhas: 627 ms → 17 ms (milhares de componentes Blade por célula viraram marcação direta, idêntica). Espaços entre tags de tabela removidos antes do dompdf (PDF idêntico, ~10% mais rápido) |

## Resultado por rota

### Tela inicial

Uma consulta do mês agrupada por dia (índice de relatório) fornece vendas e faturamento de hoje e o gráfico; contas vencidas contadas pelo índice (empresa, situação, vencimento); tudo guardado no cache até a próxima venda, conta ou movimentação de estoque.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/` | Tela inicial: vendas e faturamento de hoje, contas vencidas, estoque crítico, a receber em 7 dias, gráfico do mês e contas do mês | 434 ms | 223 ms | 20 ms | **1,9×** | 12 → 12 |

### Vendas

- Lista: índice `vendas_lista_index` (empresa, excluída, id, situação, cliente, número, data) — filtro e ordem saem do índice; total da paginação no cache.
- Busca por cliente: `cliente_id IN (clientes com o nome)` em vez de EXISTS por venda.
- API de busca (autocompletar): índice `vendas_numero_index` (empresa, excluída, número, situação, cliente).
- Nova venda/PDV/recibo/PDF: empresa carregada uma vez; próximo número pela coluna `numero_seq`.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/venda` | Lista de vendas (20 por página) com cliente e total de registros | 2,21 s | 376 ms | 36 ms | **5,9×** | 3 → 4 |
| `/venda?page=500` | Mesma lista, página 500 | 2,21 s | 405 ms | 67 ms | **5,5×** | 3 → 4 |
| `/venda?busca=Silva` | Busca por número ou nome do cliente | 5,57 s | 1,62 s | 329 ms | **3,4×** | 3 → 4 |
| `/venda?busca=0099` | Busca por número | 5,73 s | 1,94 s | 655 ms | **3,0×** | 3 → 4 |
| `/venda?situacao=em_andamento` | Filtro por situação | 455 ms | 436 ms | 41 ms | igual | 3 → 4 |
| `/venda?data_inicio…&data_fim…` | Filtro por período | 1,98 s | 405 ms | 44 ms | **4,9×** | 3 → 4 |
| `/venda/{id}` | Detalhe da venda com itens e parcelas | 22 ms | 24 ms | — | igual | 9 → 9 |
| `/venda/{id}/pdf` | PDF da venda | 398 ms | 302 ms | — | **1,3×** | 6 → 6 |
| `/venda/{id}/recibo` | Cupom/recibo | 198 ms | 182 ms | — | igual | 5 → 4 |
| `/venda/nova` | Formulário de venda | 12 ms | 16 ms | — | 1,3× mais lento | 1 → 2 |
| `/pdv` | Frente de caixa | 11 ms | 13 ms | — | 1,2× mais lento | 2 → 2 |
| `/api/vendas/busca?q=Silva` | Autocompletar de vendas | 5,90 s | 299 ms | — | **19,8×** | 2 → 2 |

### Cadastros

Índices (empresa, excluído, nome, ativo) para clientes, fornecedores e produtos: a lista em ordem alfabética sai do índice; total no cache. Próximo código de produto pela coluna `codigo_seq`. A busca de clientes "contém o texto" (`LIKE '%texto%'`) continua lendo o índice inteiro — ver "O que ainda pode melhorar".

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/cliente` | Lista de clientes por nome | 896 ms | 281 ms | 26 ms | **3,2×** | 2 → 3 |
| `/cliente?page=5000` | Página 5000 | 1,45 s | 531 ms | 283 ms | **2,7×** | 2 → 3 |
| `/cliente?busca=Karina` | Busca de clientes | 929 ms | 874 ms | 511 ms | igual | 2 → 3 |
| `/produto` | Lista de produtos por nome | 302 ms | 104 ms | — | **2,9×** | 5 → 6 |
| `/produto?busca=Cabo` | Busca de produtos | 325 ms | 148 ms | — | **2,2×** | 5 → 6 |
| `/produto?grupo_id=10` | Produtos de um grupo | 34 ms | 43 ms | — | 1,3× mais lento | 5 → 6 |
| `/produto/novo` | Formulário (próximo código) | 47 ms | 16 ms | — | **3,1×** | 3 → 4 |
| `/fornecedor` | Lista de fornecedores | 45 ms | 35 ms | — | **1,3×** | 2 → 3 |
| `/funcionario` | Lista de funcionários | 20 ms | 39 ms | — | 1,9× mais lento | 2 → 3 |
| `/grupo` | Lista de grupos | 22 ms | 32 ms | — | 1,5× mais lento | 2 → 3 |
| `/api/produtos/busca?q=cabo` | Autocompletar de produtos (PDV) | 9 ms | 9 ms | — | igual | 4 → 4 |
| `/api/produtos/busca?simples=1` | Autocompletar simples | 4 ms | 5 ms | — | 1,2× mais lento | 2 → 2 |
| `/api/clientes/busca?q=karina` | Autocompletar de clientes | 92 ms | 68 ms | — | **1,4×** | 1 → 1 |

### Financeiro

- Contas a receber/pagar: índices de lista (empresa, excluída, id, situação, pessoa, vencimento); busca por cliente/fornecedor por subconsulta; total da paginação no cache.
- Fluxo de caixa: as baixas do período são lidas pelo índice (empresa, data de pagamento) e cada conta é conferida pela chave, com o filtro da empresa dentro do EXISTS (antes o MySQL partia das contas pagas: 0,7–1,2 s por consulta); vendas à vista pelo índice de relatório.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/conta-receber` | Contas a receber (20 por página) | 484 ms | 156 ms | 44 ms | **3,1×** | 3 → 4 |
| `/conta-receber?busca=Ribeiro` | Busca por descrição ou cliente | 1,85 s | 1,01 s | 46 ms | **1,8×** | 3 → 4 |
| `/conta-receber?situacao=aberta` | Filtro por situação | 228 ms | 86 ms | 45 ms | **2,6×** | 3 → 4 |
| `/conta-receber/{id}` | Detalhe com baixas | 22 ms | 18 ms | — | **1,2×** | 4 → 5 |
| `/conta-pagar` | Contas a pagar (20 por página) | 333 ms | 114 ms | 86 ms | **2,9×** | 3 → 4 |
| `/conta-pagar?busca=Omega` | Busca por descrição ou fornecedor | 1,10 s | 516 ms | 86 ms | **2,1×** | 3 → 4 |
| `/conta-pagar/{id}` | Detalhe com baixas | 16 ms | 19 ms | — | 1,2× mais lento | 4 → 5 |
| `/fluxo-de-caixa` | Entradas e saídas por dia (mês) | 132 ms | 105 ms | 83 ms | **1,3×** | 5 → 6 |
| `/fluxo-de-caixa?(90 dias)` | Entradas e saídas por dia (90 dias) | 2,73 s | 450 ms | 447 ms | **6,1×** | 5 → 6 |

### Comissões

- Filtro de período por JOIN com a venda em vez de EXISTS por comissão (a venda excluída continua fora, como antes).
- Totais pendentes/pagas e a **quantidade** numa só consulta, partindo das vendas do período pelo índice de relatório (ano: 2,7 s → 0,9 s); a quantidade é o total da paginação, então não há `COUNT(*)` separado.
- Página pelo índice `comissoes_lista_index` (empresa, id…): percorre das comissões mais novas para trás e para nas 20 do período — ~7 ms para mês ou ano recentes (sem a dica o MySQL às vezes juntava as 330 mil vendas do ano antes de ordenar: até 2,9 s). Período de anos atrás: ~1 s.
- Totais no cache entre páginas (marcar como paga invalida).
- Conferido no banco de teste contra o código original (ano, mês + situação, período antigo + vendedor, só data final, sem filtro; páginas 1 e 50): mesmos totais, mesma quantidade e mesmas comissões em cada página.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/comissao` | Comissões + totais pendentes/pagas | 596 ms | 211 ms | 37 ms | **2,8×** | 7 → 7 |
| `/comissao?data_inicio…(1 ano)` | Comissões das vendas de um período | 2,23 s | 1,04 s | 49 ms | **2,1×** | 7 → 7 |

### Orçamentos e ordens de serviço

Índices de lista; busca por cliente por subconsulta; próximo número pela coluna `numero_seq`; total no cache.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/orcamento` | Lista de orçamentos | 351 ms | 106 ms | 35 ms | **3,3×** | 3 → 4 |
| `/orcamento?busca=Lima` | Busca por número ou cliente | 995 ms | 607 ms | 37 ms | **1,6×** | 3 → 4 |
| `/orcamento/{id}` | Detalhe | 17 ms | 25 ms | — | 1,5× mais lento | 5 → 6 |
| `/orcamento/{id}/pdf` | PDF do orçamento | 379 ms | 320 ms | — | **1,2×** | 6 → 6 |
| `/ordem-servico` | Lista de OS | 229 ms | 80 ms | 27 ms | **2,9×** | 4 → 5 |
| `/ordem-servico?busca=…` | Busca por número, equipamento ou cliente | 725 ms | 625 ms | 36 ms | **1,2×** | 4 → 5 |
| `/ordem-servico/{id}` | Detalhe | 19 ms | 29 ms | — | 1,5× mais lento | 6 → 7 |
| `/ordem-servico/{id}/pdf` | PDF da OS | 314 ms | 312 ms | — | igual | 7 → 7 |

### Estoque

Índices de lista das entradas e das movimentações (empresa, id, produto, tipo, data); total do histórico no cache.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/entrada-estoque` | Entradas de estoque | 92 ms | 52 ms | 33 ms | **1,8×** | 3 → 4 |
| `/entrada-estoque/{id}` | Detalhe | 14 ms | 20 ms | — | 1,5× mais lento | 4 → 5 |
| `/estoque/historico` | Movimentações de estoque | 462 ms | 416 ms | 24 ms | igual | 4 → 5 |
| `/estoque/historico?produto_id=…` | Movimentações de um produto | 15 ms | 22 ms | — | 1,4× mais lento | 5 → 6 |

### Notas fiscais

Índice (empresa, modelo, excluída) para a lista de NF-e/NFC-e. A lógica fiscal não foi alterada.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/nfe` | Notas fiscais | 165 ms | 102 ms | — | **1,6×** | 5 → 6 |
| `/nfe/{id}` | Detalhe da nota | 11 ms | 17 ms | — | 1,5× mais lento | 2 → 4 |

### Relatórios

Central de relatórios:

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios` | Central de relatórios | 8 ms | 15 ms | — | 1,7× mais lento | 0 → 1 |

#### Vendas

Índice `vendas_relatorio_index` (empresa, data, número, id, situação, excluída, total, cliente, vendedor, forma) cobre filtro, ordem e todas as colunas, com dica de índice; totais e comparação com o período anterior no cache (tela, gráfico, dados e PDF reaproveitam); busca por cliente por subconsulta; CSV em lotes pela chave.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/vendas` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — tela — mês atual | 175 ms | 138 ms | 32 ms | **1,3×** | 5 → 7 |
| `/relatorios/vendas?(1 ano)` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — tela — período de 1 ano | 7,11 s | 1,71 s | 31 ms | **4,2×** | 5 → 6 |
| `/relatorios/vendas/resumo` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — gráficos (JSON) — mês atual | 101 ms | 37 ms | 41 ms | **2,7×** | 2 → 2 |
| `/relatorios/vendas/resumo?(1 ano)` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — gráficos (JSON) — período de 1 ano | 4,35 s | 1,09 s | 1,03 s | **4,0×** | 2 → 2 |
| `/relatorios/vendas/dados` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — paginação (JSON) — mês atual | 84 ms | 28 ms | 14 ms | **3,0×** | 3 → 3 |
| `/relatorios/vendas/dados?(1 ano)` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — paginação (JSON) — período de 1 ano | 4,85 s | 512 ms | 9 ms | **9,5×** | 3 → 3 |
| `/relatorios/vendas/pdf` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — PDF (até 1000 linhas) — mês atual | 18,5 s | 14,8 s | — | **1,2×** | 5 → 5 |
| `/relatorios/vendas/pdf?(1 ano)` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — PDF (até 1000 linhas) — período de 1 ano | 26,3 s | 15,7 s | — | **1,7×** | 5 → 5 |
| `/relatorios/vendas/csv` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — CSV completo — mês atual | 4,03 s | 2,80 s | — | **1,4×** | 48 → 27 |
| `/relatorios/vendas/csv?(1 ano)` | Vendas do período: KPIs (confirmadas, ticket, canceladas), comparação com o período anterior e lista — CSV completo — período de 1 ano | ≈ 40 min (estimado: 667 lotes × 3,7 s) | 75,8 s | — | **≈32×** | 669 |

#### Produtos mais vendidos

Parte das vendas do período pelo índice de relatório e junta os itens pelo índice `venda_itens_relatorio_index` (venda, nome, código, produto, quantidade, total), sem ler a tabela de itens; página + totais numa consulta com funções de janela; agrupamento em memória (`tmp_table_size`); período anterior no cache; CSV por cursor.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/produtos-mais-vendidos` | Ranking de produtos por quantidade/valor e totais — tela — mês atual | 686 ms | 624 ms | 445 ms | igual | 5 → 5 |
| `/relatorios/produtos-mais-vendidos?(1 ano)` | Ranking de produtos por quantidade/valor e totais — tela — período de 1 ano | 53,1 s | 6,92 s | 3,36 s | **7,7×** | 5 → 5 |
| `/relatorios/produtos-mais-vendidos/resumo` | Ranking de produtos por quantidade/valor e totais — gráficos (JSON) — mês atual | 378 ms | 213 ms | 176 ms | **1,8×** | 2 → 3 |
| `/relatorios/produtos-mais-vendidos/resumo?(1 ano)` | Ranking de produtos por quantidade/valor e totais — gráficos (JSON) — período de 1 ano | 22,6 s | 4,71 s | 4,38 s | **4,8×** | 2 → 3 |
| `/relatorios/produtos-mais-vendidos/dados` | Ranking de produtos por quantidade/valor e totais — paginação (JSON) — mês atual | 417 ms | 422 ms | 400 ms | igual | 2 → 2 |
| `/relatorios/produtos-mais-vendidos/dados?(1 ano)` | Ranking de produtos por quantidade/valor e totais — paginação (JSON) — período de 1 ano | 29,8 s | 3,36 s | 3,22 s | **8,9×** | 2 → 2 |
| `/relatorios/produtos-mais-vendidos/pdf` | Ranking de produtos por quantidade/valor e totais — PDF (até 1000 linhas) — mês atual | 11,6 s | 10,2 s | — | igual | 3 → 3 |
| `/relatorios/produtos-mais-vendidos/pdf?(1 ano)` | Ranking de produtos por quantidade/valor e totais — PDF (até 1000 linhas) — período de 1 ano | 40,3 s | 13,7 s | — | **2,9×** | 3 → 3 |
| `/relatorios/produtos-mais-vendidos/csv` | Ranking de produtos por quantidade/valor e totais — CSV completo — mês atual | 9,24 s | 1,72 s | — | **5,4×** | 25 → 2 |
| `/relatorios/produtos-mais-vendidos/csv?(1 ano)` | Ranking de produtos por quantidade/valor e totais — CSV completo — período de 1 ano | — | 6,08 s | — | — | 2 |

#### Vendas por vendedor

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/vendas-por-vendedor` | Vendas e faturamento por vendedor — tela — mês atual | 139 ms | 90 ms | — | **1,5×** | 5 → 5 |
| `/relatorios/vendas-por-vendedor?(1 ano)` | Vendas e faturamento por vendedor — tela — período de 1 ano | 4,33 s | 1,26 s | — | **3,4×** | 5 → 5 |
| `/relatorios/vendas-por-vendedor/resumo` | Vendas e faturamento por vendedor — gráficos (JSON) — mês atual | 72 ms | 44 ms | — | **1,6×** | 3 → 3 |
| `/relatorios/vendas-por-vendedor/resumo?(1 ano)` | Vendas e faturamento por vendedor — gráficos (JSON) — período de 1 ano | 4,26 s | 1,09 s | — | **3,9×** | 3 → 3 |
| `/relatorios/vendas-por-vendedor/dados` | Vendas e faturamento por vendedor — paginação (JSON) — mês atual | 45 ms | 30 ms | — | **1,5×** | 2 → 2 |
| `/relatorios/vendas-por-vendedor/dados?(1 ano)` | Vendas e faturamento por vendedor — paginação (JSON) — período de 1 ano | 2,00 s | 626 ms | — | **3,2×** | 2 → 2 |
| `/relatorios/vendas-por-vendedor/pdf` | Vendas e faturamento por vendedor — PDF (até 1000 linhas) — mês atual | 604 ms | 552 ms | — | igual | 3 → 3 |
| `/relatorios/vendas-por-vendedor/pdf?(1 ano)` | Vendas e faturamento por vendedor — PDF (até 1000 linhas) — período de 1 ano | 2,64 s | 1,44 s | — | **1,8×** | 3 → 3 |
| `/relatorios/vendas-por-vendedor/csv` | Vendas e faturamento por vendedor — CSV completo — mês atual | 49 ms | 32 ms | — | **1,5×** | 2 → 2 |
| `/relatorios/vendas-por-vendedor/csv?(1 ano)` | Vendas e faturamento por vendedor — CSV completo — período de 1 ano | — | 654 ms | — | — | 2 |

#### Vendas por dia da semana

Os dois relatórios acima passaram a usar o índice de relatório com dica de índice.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/vendas-por-dia-semana` | Faturamento por dia da semana — tela — mês atual | 86 ms | 73 ms | — | **1,2×** | 3 → 3 |
| `/relatorios/vendas-por-dia-semana?(1 ano)` | Faturamento por dia da semana — tela — período de 1 ano | 4,08 s | 828 ms | — | **4,9×** | 3 → 3 |
| `/relatorios/vendas-por-dia-semana/resumo` | Faturamento por dia da semana — gráficos (JSON) — mês atual | 73 ms | 36 ms | — | **2,0×** | 2 → 2 |
| `/relatorios/vendas-por-dia-semana/resumo?(1 ano)` | Faturamento por dia da semana — gráficos (JSON) — período de 1 ano | 4,52 s | 876 ms | — | **5,2×** | 2 → 2 |
| `/relatorios/vendas-por-dia-semana/dados` | Faturamento por dia da semana — paginação (JSON) — mês atual | 33 ms | 19 ms | — | **1,7×** | 1 → 1 |
| `/relatorios/vendas-por-dia-semana/dados?(1 ano)` | Faturamento por dia da semana — paginação (JSON) — período de 1 ano | 2,19 s | 397 ms | — | **5,5×** | 1 → 1 |
| `/relatorios/vendas-por-dia-semana/pdf` | Faturamento por dia da semana — PDF (até 1000 linhas) — mês atual | 326 ms | 316 ms | — | igual | 2 → 2 |
| `/relatorios/vendas-por-dia-semana/pdf?(1 ano)` | Faturamento por dia da semana — PDF (até 1000 linhas) — período de 1 ano | 2,30 s | 738 ms | — | **3,1×** | 2 → 2 |
| `/relatorios/vendas-por-dia-semana/csv` | Faturamento por dia da semana — CSV completo — mês atual | 34 ms | 20 ms | — | **1,7×** | 1 → 1 |
| `/relatorios/vendas-por-dia-semana/csv?(1 ano)` | Faturamento por dia da semana — CSV completo — período de 1 ano | — | 389 ms | — | — | 1 |

#### Ranking de clientes (curva ABC)

**Estava quebrado no período de 1 ano** (erro 500 depois de 48 s): os nomes eram buscados com `WHERE id IN (161.904 ids)`, acima do limite de 65.535 parâmetros do MySQL (erro 1390). Agora a agregação por cliente é uma tabela derivada com `LEFT JOIN clientes`; o faturamento é uma soma só; a classificação vai para o cache (até 5.000 clientes). O PDF continua lento por causa do dompdf (ver adiante).

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/ranking-clientes` | Curva ABC de clientes por faturamento — tela — mês atual | 264 ms | 160 ms | 142 ms | **1,7×** | 4 → 3 |
| `/relatorios/ranking-clientes?(1 ano)` | Curva ABC de clientes por faturamento — tela — período de 1 ano | erro 500 (47,8 s) | 3,05 s | 2,46 s | **voltou a funcionar** | 1 → 3 |
| `/relatorios/ranking-clientes/resumo` | Curva ABC de clientes por faturamento — gráficos (JSON) — mês atual | 232 ms | 154 ms | 104 ms | **1,5×** | 3 → 2 |
| `/relatorios/ranking-clientes/resumo?(1 ano)` | Curva ABC de clientes por faturamento — gráficos (JSON) — período de 1 ano | erro 500 (45,0 s) | 3,60 s | 2,67 s | **voltou a funcionar** | 1 → 2 |
| `/relatorios/ranking-clientes/dados` | Curva ABC de clientes por faturamento — paginação (JSON) — mês atual | 202 ms | 124 ms | 125 ms | **1,6×** | 2 → 1 |
| `/relatorios/ranking-clientes/dados?(1 ano)` | Curva ABC de clientes por faturamento — paginação (JSON) — período de 1 ano | erro 500 (47,7 s) | 2,85 s | 2,32 s | **voltou a funcionar** | 1 → 1 |
| `/relatorios/ranking-clientes/pdf` | Curva ABC de clientes por faturamento — PDF (até 1000 linhas) — mês atual | 21,3 s | 19,0 s | — | igual | 3 → 2 |
| `/relatorios/ranking-clientes/pdf?(1 ano)` | Curva ABC de clientes por faturamento — PDF (até 1000 linhas) — período de 1 ano | erro 500 (53,6 s) | 21,7 s | — | **voltou a funcionar** | 1 → 2 |
| `/relatorios/ranking-clientes/csv` | Curva ABC de clientes por faturamento — CSV completo — mês atual | 699 ms | 639 ms | — | igual | 2 → 1 |
| `/relatorios/ranking-clientes/csv?(1 ano)` | Curva ABC de clientes por faturamento — CSV completo — período de 1 ano | — | 12,0 s | — | — | 1 |

#### Estoque

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/estoque` | Posição de estoque (mínimo, atual, críticos) — tela — mês atual | 184 ms | 187 ms | — | igual | 6 → 6 |
| `/relatorios/estoque/resumo` | Posição de estoque (mínimo, atual, críticos) — gráficos (JSON) — mês atual | 290 ms | 301 ms | — | igual | 2 → 2 |
| `/relatorios/estoque/dados` | Posição de estoque (mínimo, atual, críticos) — paginação (JSON) — mês atual | 116 ms | 142 ms | — | 1,2× mais lento | 4 → 4 |
| `/relatorios/estoque/pdf` | Posição de estoque (mínimo, atual, críticos) — PDF (até 1000 linhas) — mês atual | 12,3 s | 12,5 s | — | igual | 5 → 5 |
| `/relatorios/estoque/csv` | Posição de estoque (mínimo, atual, críticos) — CSV completo — mês atual | 17,7 s | 8,74 s | — | **2,0×** | 282 → 142 |

#### Contas a receber e contas a pagar

Índices de período (empresa, vencimento, excluída, situação, valores, categoria) e de situação; CSV em lotes pela chave.

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/contas-receber` | Contas a receber do período com totais — tela — mês atual | 145 ms | 83 ms | — | **1,7×** | 9 → 9 |
| `/relatorios/contas-receber?(1 ano)` | Contas a receber do período com totais — tela — período de 1 ano | 1,36 s | 818 ms | — | **1,7×** | 9 → 9 |
| `/relatorios/contas-receber/resumo` | Contas a receber do período com totais — gráficos (JSON) — mês atual | 50 ms | 26 ms | — | **1,9×** | 3 → 3 |
| `/relatorios/contas-receber/resumo?(1 ano)` | Contas a receber do período com totais — gráficos (JSON) — período de 1 ano | 1,09 s | 679 ms | — | **1,6×** | 3 → 3 |
| `/relatorios/contas-receber/dados` | Contas a receber do período com totais — paginação (JSON) — mês atual | 46 ms | 33 ms | — | **1,4×** | 5 → 5 |
| `/relatorios/contas-receber/dados?(1 ano)` | Contas a receber do período com totais — paginação (JSON) — período de 1 ano | 739 ms | 405 ms | — | **1,8×** | 5 → 5 |
| `/relatorios/contas-receber/pdf` | Contas a receber do período com totais — PDF (até 1000 linhas) — mês atual | 26,5 s | 23,8 s | — | igual | 6 → 6 |
| `/relatorios/contas-receber/pdf?(1 ano)` | Contas a receber do período com totais — PDF (até 1000 linhas) — período de 1 ano | 30,3 s | 24,2 s | — | **1,3×** | 6 → 6 |
| `/relatorios/contas-receber/csv` | Contas a receber do período com totais — CSV completo — mês atual | 1,10 s | 990 ms | — | igual | 27 → 16 |
| `/relatorios/contas-receber/csv?(1 ano)` | Contas a receber do período com totais — CSV completo — período de 1 ano | — | 27,1 s | — | — | 331 |

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/contas-pagar` | Contas a pagar do período com totais — tela — mês atual | 80 ms | 94 ms | — | 1,2× mais lento | 9 → 9 |
| `/relatorios/contas-pagar?(1 ano)` | Contas a pagar do período com totais — tela — período de 1 ano | 618 ms | 627 ms | — | igual | 9 → 9 |
| `/relatorios/contas-pagar/resumo` | Contas a pagar do período com totais — gráficos (JSON) — mês atual | 20 ms | 30 ms | — | 1,5× mais lento | 3 → 3 |
| `/relatorios/contas-pagar/resumo?(1 ano)` | Contas a pagar do período com totais — gráficos (JSON) — período de 1 ano | 465 ms | 454 ms | — | igual | 3 → 3 |
| `/relatorios/contas-pagar/dados` | Contas a pagar do período com totais — paginação (JSON) — mês atual | 26 ms | 23 ms | — | igual | 5 → 5 |
| `/relatorios/contas-pagar/dados?(1 ano)` | Contas a pagar do período com totais — paginação (JSON) — período de 1 ano | 285 ms | 277 ms | — | igual | 5 → 5 |
| `/relatorios/contas-pagar/pdf` | Contas a pagar do período com totais — PDF (até 1000 linhas) — mês atual | 28,4 s | 23,9 s | — | **1,2×** | 6 → 6 |
| `/relatorios/contas-pagar/pdf?(1 ano)` | Contas a pagar do período com totais — PDF (até 1000 linhas) — período de 1 ano | 28,5 s | 31,4 s | — | igual | 6 → 6 |
| `/relatorios/contas-pagar/csv` | Contas a pagar do período com totais — CSV completo — mês atual | 496 ms | 514 ms | — | igual | 15 → 10 |
| `/relatorios/contas-pagar/csv?(1 ano)` | Contas a pagar do período com totais — CSV completo — período de 1 ano | — | 13,6 s | — | — | 181 |

#### Inadimplência (receber e pagar)

CSV em lotes pela chave; total da paginação reaproveitado dos totais por faixa (um `COUNT(*)` a menos por tela).

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/inadimplencia-receber` | Títulos a receber vencidos por faixa de atraso — tela — mês atual | 308 ms | 149 ms | — | **2,1×** | 5 → 4 |
| `/relatorios/inadimplencia-receber/resumo` | Títulos a receber vencidos por faixa de atraso — gráficos (JSON) — mês atual | 307 ms | 188 ms | — | **1,6×** | 2 → 2 |
| `/relatorios/inadimplencia-receber/dados` | Títulos a receber vencidos por faixa de atraso — paginação (JSON) — mês atual | 257 ms | 106 ms | — | **2,4×** | 4 → 3 |
| `/relatorios/inadimplencia-receber/pdf` | Títulos a receber vencidos por faixa de atraso — PDF (até 1000 linhas) — mês atual | 27,0 s | 19,4 s | — | **1,4×** | 4 → 4 |
| `/relatorios/inadimplencia-receber/csv` | Títulos a receber vencidos por faixa de atraso — CSV completo — mês atual | 54,3 s | 16,0 s | — | **3,4×** | 204 → 103 |

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/inadimplencia-pagar` | Títulos a pagar vencidos por faixa de atraso — tela — mês atual | 336 ms | 235 ms | — | **1,4×** | 5 → 4 |
| `/relatorios/inadimplencia-pagar/resumo` | Títulos a pagar vencidos por faixa de atraso — gráficos (JSON) — mês atual | 357 ms | 373 ms | — | igual | 2 → 2 |
| `/relatorios/inadimplencia-pagar/dados` | Títulos a pagar vencidos por faixa de atraso — paginação (JSON) — mês atual | 304 ms | 202 ms | — | **1,5×** | 4 → 3 |
| `/relatorios/inadimplencia-pagar/pdf` | Títulos a pagar vencidos por faixa de atraso — PDF (até 1000 linhas) — mês atual | 25,5 s | 20,4 s | — | **1,2×** | 4 → 4 |
| `/relatorios/inadimplencia-pagar/csv` | Títulos a pagar vencidos por faixa de atraso — CSV completo — mês atual | 33,1 s | 16,3 s | — | **2,0×** | 208 → 105 |

#### Clientes com débito

Mesmo risco do ranking, corrigido antes de acontecer: os nomes vinham de `WHERE id IN (57.015 ids)` no banco de teste — perto do limite de 65.535; com mais clientes devedores o relatório passaria a dar erro 500. Agora é tabela derivada + `LEFT JOIN`, e a agregação usa o índice que começa pela situação (0,65 s → 0,24 s).

| Rota | O que traz | Antes | Depois | Com cache | Ganho | Consultas |
|---|---|---:|---:|---:|---|---|
| `/relatorios/clientes-com-debito` | Clientes com saldo em aberto/vencido — tela — mês atual | 1,53 s | 722 ms | 656 ms | **2,1×** | 3 → 2 |
| `/relatorios/clientes-com-debito/resumo` | Clientes com saldo em aberto/vencido — gráficos (JSON) — mês atual | 1,67 s | 829 ms | 783 ms | **2,0×** | 2 → 1 |
| `/relatorios/clientes-com-debito/dados` | Clientes com saldo em aberto/vencido — paginação (JSON) — mês atual | 1,52 s | 690 ms | 689 ms | **2,2×** | 2 → 1 |
| `/relatorios/clientes-com-debito/pdf` | Clientes com saldo em aberto/vencido — PDF (até 1000 linhas) — mês atual | 13,2 s | 9,52 s | — | **1,4×** | 3 → 2 |
| `/relatorios/clientes-com-debito/csv` | Clientes com saldo em aberto/vencido — CSV completo — mês atual | 5,01 s | 3,74 s | — | **1,3×** | 2 → 1 |

## Erros corrigidos no caminho

- **Ranking de clientes com período longo dava erro 500** (MySQL 1390, placeholders demais) — corrigido.
- **Clientes com débito** tinha o mesmo defeito, prestes a aparecer — corrigido.
- CSV anual de vendas era, na prática, impossível de baixar (~40 min) — agora 76 s.

## O que ainda pode melhorar (precisa de decisão sua)

1. **PDFs de relatório (10–30 s para 1.000 linhas).** O SQL já leva milissegundos; quase todo o tempo é do dompdf, cujo custo cresce mais que linearmente com tabelas longas (ele remede a tabela inteira a cada quebra de página). Opções:
   - dividir a tabela em blocos de 100 linhas: 18 s → 7,5 s no teste, mas o cabeçalho da tabela passa a se repetir no meio das páginas (muda o visual — por isso não apliquei);
   - gerar o PDF em segundo plano (fila) e avisar quando estiver pronto;
   - trocar o dompdf por um Chromium sem interface (Browsershot/Gotenberg), muito mais rápido em tabelas longas, mas é uma dependência pesada (não medi aqui).
2. **Buscas "contém o texto"** (`LIKE '%texto%'` em nome de cliente, descrição, número): nenhum índice comum ajuda com `%` no início. Um índice `FULLTEXT` com parser `ngram` resolveria, mas muda um pouco o comportamento da busca (tamanho mínimo do termo, acentos) — precisa ser validado com você.
3. **Produtos mais vendidos com período de 1 ano (7 s)**: agrupar 1 milhão de itens é o limite do SQL direto. O próximo passo seria uma tabela de resumo diária por produto, atualizada a cada venda.
4. **Índices antigos que ficaram redundantes** (cada um é o começo de um índice novo, que faz o mesmo trabalho). Mantidos porque a regra é só acrescentar; removê-los deixaria as gravações um pouco mais rápidas:
   - `vendas_empresa_data_index` → coberto por `vendas_relatorio_index`
   - `contas_receber_empresa_venc_index` → `contas_receber_periodo_index`; `contas_receber_empresa_situacao_venc_index` → `contas_receber_relatorio_index`
   - `contas_pagar_empresa_venc_index` → `contas_pagar_periodo_index`; `contas_pagar_empresa_situacao_venc_index` → `contas_pagar_relatorio_index`
   - `venda_itens_venda_id_foreign` → `venda_itens_relatorio_index` (é índice de chave estrangeira: só remover junto com a FK recriada)
5. **Estoque crítico** (`estoque_atual <= estoque_minimo`): não criei índice porque `estoque_atual` muda a cada venda e um índice nele pesaria em todas as vendas; a contagem leva ~0,1 s e fica no cache da tela inicial.

## Índices criados

| Tabela | Índice | Colunas |
|---|---|---|
| `vendas` | `vendas_lista_index` | empresa_id, deleted_at, id, situacao, cliente_id, numero, data_venda |
| `vendas` | `vendas_numero_index` | empresa_id, deleted_at, numero, situacao, cliente_id |
| `vendas` | `vendas_relatorio_index` | empresa_id, data_venda, numero, id, situacao, deleted_at, total, cliente_id, funcionario_id, forma_pagamento_id, forma_pagamento |
| `venda_itens` | `venda_itens_relatorio_index` | venda_id, produto_nome, produto_codigo, produto_id, quantidade, total |
| `contas_receber` | `contas_receber_lista_index` | empresa_id, deleted_at, id, situacao, cliente_id, data_vencimento |
| `contas_receber` | `contas_receber_relatorio_index` | empresa_id, situacao, data_vencimento, deleted_at, valor, valor_pago, cliente_id, categoria_id |
| `contas_receber` | `contas_receber_periodo_index` | empresa_id, data_vencimento, deleted_at, situacao, valor, valor_pago, categoria_id |
| `contas_pagar` | `contas_pagar_lista_index` | empresa_id, deleted_at, id, situacao, fornecedor_id, data_vencimento |
| `contas_pagar` | `contas_pagar_relatorio_index` | empresa_id, situacao, data_vencimento, deleted_at, valor, valor_pago, fornecedor_id, categoria_id |
| `contas_pagar` | `contas_pagar_periodo_index` | empresa_id, data_vencimento, deleted_at, situacao, valor, valor_pago, categoria_id |
| `clientes` | `clientes_lista_index` | empresa_id, deleted_at, nome, ativo |
| `fornecedores` | `fornecedores_lista_index` | empresa_id, deleted_at, nome, ativo |
| `produtos` | `produtos_lista_index` | empresa_id, deleted_at, nome, ativo |
| `comissoes` | `comissoes_lista_index` | empresa_id, id, situacao, funcionario_id, venda_id, valor |
| `orcamentos` | `orcamentos_lista_index` | empresa_id, deleted_at, id, situacao, cliente_id, numero, data_orcamento |
| `ordens_servico` | `ordens_servico_lista_index` | empresa_id, deleted_at, id, situacao, cliente_id, numero, funcionario_id, data_abertura |
| `entradas_estoque` | `entradas_estoque_lista_index` | empresa_id, deleted_at, id, situacao, fornecedor_id, numero, data_entrada |
| `nfes` | `nfes_lista_index` | empresa_id, modelo, deleted_at |
| `movimentacoes_estoque` | `movimentacoes_estoque_lista_index` | empresa_id, id, produto_id, tipo, created_at |

Mais os índices da migration de sequência: `vendas_empresa_numero_seq_index`, `orcamentos_empresa_numero_seq_index`, `ordens_servico_empresa_numero_seq_index`, `produtos_empresa_codigo_seq_index`.

## Arquivos

Novos:
- `app/Support/BuscaPorNome.php`, `CacheDados.php`, `ContagemPaginada.php`, `IndiceBanco.php`, `LeituraEmLotes.php`
- `app/Services/Painel/PainelService.php` (tela inicial, antes uma closure em `routes/web.php`)
- `database/migrations/2026_11_13_000001_add_sequencia_numerica_indexada.php`
- `database/migrations/2026_11_13_000002_add_indices_desempenho_listagens_relatorios.php`

Alterados: serviço de comissões; repositórios de vendas, clientes, produtos, contas a receber/pagar, comissões, orçamentos, OS, entradas de estoque e de todos os relatórios; serviços dos relatórios; `FluxoCaixaController`, `AjusteEstoqueController`, `VendaController` (PDV); `Empresa::atual()`; `EmpresaDocumento`; `VendaService`; rotina `RegistrarVenda`; `AppServiceProvider` (registro do cache); `routes/web.php`, `routes/api_interna.php`; `resources/views/pdf/relatorio.blade.php`; `resources/views/layouts/app.blade.php`; `App\Relatorios\PdfRelatorio`.

Cópia dos originais: `~/backup-desempenho-2026-11-13`. Nada foi commitado.

## Fontes consultadas

- Por que `whereHas` é lento e a alternativa com `whereIn`: https://dev.to/mostafalaravel/why-wherehas-is-very-slow-2mmb · https://laracasts.com/discuss/channels/eloquent/poor-wherehas-performance
- Paginação eficiente e o custo do `OFFSET`: https://aaronfrancis.com/2022/efficient-pagination-using-deferred-joins · https://planetscale.com/blog/mysql-pagination · https://oneuptime.com/blog/post/2026-03-31-mysql-optimize-limit-large-offsets/markdown
- Índices cobrindo a consulta e `COUNT(*)`: https://oneuptime.com/blog/post/2026-03-31-mysql-what-is-a-mysql-covering-index/markdown · https://oneuptime.com/blog/post/2026-03-31-mysql-optimize-count-queries/markdown
- Colunas geradas indexadas: https://dev.mysql.com/doc/refman/8.0/en/generated-column-index-optimizations.html
- Totais da paginação na mesma consulta (funções de janela): https://blog.jooq.org/calculating-pagination-metadata-without-extra-roundtrips-in-sql/ · https://dev.mysql.com/doc/refman//8.0/en/aggregate-functions.html
- Desempenho do dompdf com tabelas longas: https://www.github.com/dompdf/dompdf/discussions/3385 · https://groups.google.com/g/dompdf/c/u5tHHZBK2E0
