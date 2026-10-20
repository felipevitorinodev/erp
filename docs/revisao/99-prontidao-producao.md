# 99 — Checklist de prontidão para produção

Legenda: **ok** · **pendente** (ação necessária) · **não verificável** (depende do servidor/`.env` real, que não foi lido por regra da revisão).

Base: `.env.example`, `config/*`, `bootstrap/app.php`, código e migrations. O `.env` real e os certificados não foram abertos.

## Ambiente e configuração

| Item | Situação | Observação |
|---|---|---|
| `APP_ENV=production` | não verificável | `.env.example` traz `local`. |
| `APP_DEBUG=false` | não verificável | `.env.example` traz `true`. Com `true` em produção, a página de erro expõe SQL, caminhos e variáveis. |
| `APP_KEY` definido e **estável** | não verificável | A senha do certificado A1 é gravada com `Crypt::encryptString` (`Empresa::certificado_senha`). **Trocar o `APP_KEY` invalida todas as senhas de certificado** — guardar a chave com backup. |
| `APP_URL` com o domínio real (https) | não verificável | Usado nos links de redefinição de senha. |
| `APP_TIMEZONE` / `config/app.php` | pendente | Está em `UTC`; vendas após 21h (Brasília) ficam com a data do dia seguinte. Ver pergunta no módulo 08. |
| `LOG_LEVEL` | pendente | `.env.example` traz `debug`; usar `warning` ou `error` em produção. |
| `SESSION_SECURE_COOKIE=true` | pendente | Sem padrão no `config/session.php`; definir no `.env` de produção (HTTPS). |
| `trustProxies(at: '*')` | pendente | Restringir aos IPs do proxy e definir hosts confiáveis (módulo 06, item 8). |
| `MAIL_MAILER` | não verificável | `.env.example` traz `log`; a recuperação de senha precisa de SMTP real. |

## Deploy

| Item | Situação | Observação |
|---|---|---|
| `php artisan config:cache` | não verificável | Nenhum `env()` fora de `config/` encontrado no código alterado. |
| `php artisan route:cache` | pendente (testar) | As rotas de `api_interna.php` e `relatorios.php` são closures; o Laravel 13 serializa closures, mas convém rodar `route:cache` em homologação antes. |
| `php artisan view:cache` | ok | Sem impedimentos encontrados. |
| Migrations pendentes | pendente | Aplicar com `php artisan migrate` (nunca `fresh`): `2026_10_20_000001` (controle SEFAZ + unique de numeração da NF-e — **aborta se já houver número duplicado**), `2026_10_20_000002` (CPF/CNPJ de cliente por empresa), `2026_10_20_000003` (índices). Todas verificadas com `--pretend`; nenhuma foi aplicada no banco local. Fazer backup antes. |
| Filas | ok | `QUEUE_CONNECTION=database`, mas não há jobs; nada precisa de worker hoje. A emissão de NF-e é síncrona (pendente no módulo 01). |
| Scheduler | ok | `routes/console.php` não agenda nada; não precisa de cron. |

## Erros, logs e dados sensíveis

| Item | Situação | Observação |
|---|---|---|
| Tratamento global de exceções | ok | `bootstrap/app.php` traduz violação de unique e de FK em mensagem amigável; o restante cai no handler padrão (depende de `APP_DEBUG=false`). |
| Mensagens de erro SQL na tela | parcial | Corrigido no pagamento de comissão; a aprovação de orçamento ainda mostra `$ex->getMessage()` de qualquer exceção (módulo 05). |
| Logs sem dados sensíveis | pendente | `NfeSenderService` registra a resposta da SEFAZ em caso de erro, que pode conter CPF/nome do destinatário (LGPD). Senha e conteúdo do certificado não são logados. |
| Armazenamento do certificado | ok | `.pfx` em `storage/app/certificados` (fora de `public/`, ignorado pelo git); senha criptografada. Recomendações: permissão de arquivo restrita ao usuário do PHP; adicionar `certificado_senha` ao `$hidden` do model `Empresa`; limite de tamanho do upload. |
| Validade do certificado | ok | Upload recusa certificado vencido ou de outro CNPJ; tela de emissão avisa com 30 dias de antecedência (módulo 01). |

## Segurança

| Item | Situação | Observação |
|---|---|---|
| Limite de tentativas de login | ok | Corrigido (módulo 06). |
| Escalada de privilégio | ok | Corrigido (módulo 06). |
| Isolamento entre empresas | ok | Revisado em todos os módulos; corrigidos vazamentos em empresas, produtos, grupos, orçamento, funcionários e relatórios. Endpoints AJAX ainda sem permissão por perfil (módulo 08, item 5). |
| XSS | ok | Corrigido em venda, orçamento, NF-e e modal de cadastro rápido. |
| CSV injection | ok | Corrigido (módulo 08). |

## Testes

| Item | Situação | Observação |
|---|---|---|
| Suíte passando | ok | 61 testes, 225 asserções (antes: 21/22, com o `ExampleTest` falhando). |
| Cobertura dos fluxos críticos | parcial | Cobertos: numeração/reuso/concorrência de NF-e, totais do XML, IDOR de NF-e e empresa; cancelamento de venda e estoque; baixas e cancelamento de contas; entrada de estoque; orçamento; login/permissões; isolamento de cadastros; relatórios; contrato JSON do PDV. **Não cobertos:** comunicação real com a SEFAZ (precisa de certificado e homologação), cancelamento de NF-e na SEFAZ, DANFE, importação de XML ponta a ponta. |
| Teste manual em homologação SEFAZ | pendente | Roteiro em `01-nfe.md`. Obrigatório antes do primeiro cliente emitir em produção. |

## O que não foi alterado e recomendo olhar depois

1. Inutilização de numeração, carta de correção e contingência de NF-e (módulo 01).
2. Emissão de NF-e síncrona dentro da requisição (timeout do PHP vs. SEFAZ lenta).
3. Forma de pagamento identificada pelo **nome** em vendas/contas (módulo 03, item 9).
4. Comissão identificada por texto em `observacoes` (módulo 03).
5. Conta a pagar de entrada importada sem IPI/ST/frete (módulo 04).
6. Casamento de produto do XML pelo código do fornecedor (módulo 04).
7. Permissões por perfil nos endpoints AJAX e `preco_custo` exposto ao vendedor (módulo 08).
8. Relatórios carregando tudo em memória (módulo 08).
9. Máscara de quantidade com 2 casas no front (módulos 04 e 09).
10. Normalização de CPF/CNPJ gravados com e sem máscara (módulo 07).
11. Unicidade de `orcamentos (empresa_id, numero)`, `vendas (empresa_id, numero)`, `comissoes.venda_id`, `formas_pagamento (empresa_id, nome)` — exigem verificar duplicados existentes antes.
12. Estilo (Pint) em arquivos já existentes — não reformatado para manter o diff mínimo.

## Perguntas em aberto (consolidadas)

1. **NF-e:** CPF `00000000000` para consumidor final em modelo 55, `enderDest` omitido, sequência compartilhada entre homologação/produção/séries e `tPag` mapeado pelo nome da forma — ver `01-nfe.md`.
2. **Financeiro:** gravar `forma_pagamento_id` na venda ou bloquear confirmação com forma desconhecida? Comissão pode ser paga antes de o cliente pagar?
3. **Estoque:** conta a pagar da entrada importada deve usar o `vNF`? Há importação de notas destinadas a outro CNPJ do grupo?
4. **Orçamentos:** `data_venda` deve ser a data da aprovação? Orçamento deveria ter vendedor (comissão)?
5. **Auth:** a aplicação roda atrás de proxy? Qual domínio? Vendedor deve ver o faturamento no dashboard?
6. **Cadastros:** a tela "Nova empresa" deve existir para os clientes?
7. **Relatórios:** excluir contas canceladas dos totais? Posso trocar o fuso para `America/Sao_Paulo`?
