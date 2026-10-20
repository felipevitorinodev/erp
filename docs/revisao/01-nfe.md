# 01 — NF-e e configuração fiscal da empresa

Arquivos revisados: `NfeController`, `NfeService`, `NfeBuilderService`, `NfeSenderService`, `Nfe`, `NfeCreateRequest`, `EmpresaService`, `EmpresaController`, `EmpresaRequest`, `Empresa`, `NfeTestarCommand`, views `nfe/*`, `empresa/edit`, bloco NF-e de `venda/show`, migrations `2026_10_06_*`.
Comportamento da biblioteca conferido em `vendor/nfephp-org/sped-nfe` (`Tools::sefazEnviaLote/ConsultaChave/Cancela/setEnvironment`, `Complements::toAuthorize/cancelRegister`, `Make` / `TraitTagTotal::buildTagICMSTot`) e `sped-common` (`Certificate`, `Validator`).

Legenda de status: ✅ corrigido · ⏳ pendente (não corrigido) · ❓ pergunta

## Achados

### ✅ 🔴 1. Falha de comunicação marcava a NF-e como "rejeitada" e liberava reemissão com outro número
- **Arquivo:** `app/Services/NfeService.php` `aplicarResultadoEnvio()`; `app/Services/NfeSenderService.php` `enviar()`, `consultarRecibo()`, `resultadoProtocolo()`.
- **Problema e causa:** todo retorno diferente de `autorizada` (timeout, exceção SOAP, lote 105 "em processamento" após 3 tentativas, retorno ilegível, "autorizada mas protocolo não anexado") gravava `situacao = rejeitada`. A tela da venda então oferecia "Emitir nova NF-e", que reservava **outro número**. Se a SEFAZ tivesse autorizado a primeira, a venda ficava com **duas NF-e autorizadas** e o sistema só conhecia uma.
- **Correção:** resultados de situação desconhecida (`status = erro`) mantêm a NF-e **pendente**, gravam motivo/código/recibo e orientam o usuário a usar a nova ação **Consultar SEFAZ** (`POST nfe/{nfe}/consultar`, `sefazConsultaChave`). A consulta resolve para autorizada, denegada, rejeitada (217 = não consta) ou continua pendente. NF-e pendente bloqueia nova emissão.
- **Risco:** médio. Não altera dados existentes (notas já marcadas como rejeitadas continuam como estão — ver pendência 19).

### ✅ 🔴 2. Duplo clique em "Emitir" podia gerar duas NF-e para a mesma venda
- **Arquivo:** `NfeService::emitir()`.
- **Causa:** as verificações "já existe autorizada/pendente" eram feitas fora de transação e sem trava; duas requisições simultâneas passavam e cada uma reservava um número.
- **Correção:** verificação e reserva dentro de `DB::transaction` com `lockForUpdate()` na linha da venda.
- **Risco:** baixo.

### ✅ 🔴 3. Totais da NF-e divergiam da soma dos itens quando havia desconto por item
- **Arquivo:** `NfeBuilderService::montarItens()/montarTotais()/montarPagamento()`.
- **Causa:** cada item informa `vProd` bruto (`total + desconto`) e `vDesc` (desconto do item + rateio), mas `ICMSTot` recebia `vProd = venda.subtotal` (líquido do desconto dos itens) e `vDesc = venda.desconto`. Como o `Make` usa o valor informado em vez de somar (`TraitTagTotal::buildTagICMSTot`), a SEFAZ rejeitaria (564 / 531 / 535). `vPag` usava `venda.total`, que pode diferir 1 centavo da soma.
- **Correção:** `montarItens()` acumula `vProd`/`vDesc` dos itens já arredondados; `ICMSTot` e `vPag` usam esses totais. Coberto por teste que assina um XML real com certificado autoassinado.
- **Risco:** baixo.

### ✅ 🔴 4. Série padrão `'001'` viola o schema da NF-e (toda emissão com série padrão falhava)
- **Arquivo:** `NfeBuilderService::montarIdentificacao()`.
- **Causa:** `serie` do XML aceita o padrão `0|[1-9][0-9]{0,2}` (sem zero à esquerda); o default do banco é `'001'`. A assinatura falhava com "Este XML não é válido".
- **Correção:** envia `(int) nfe_serie` no XML. O valor gravado em `empresa.nfe_serie` e `nfes.serie` continua `'001'` (apenas exibição).
- **Risco:** baixo. Descoberto pelo teste automatizado novo.

### ✅ 🔴 5. Usuário podia editar dados fiscais/certificado de outra empresa (IDOR)
- **Arquivo:** `EmpresaService::edit()/update()`.
- **Causa:** rotas `empresa/{empresa}/editar` e `.../salvar` não verificavam se `{empresa}` é a empresa do usuário.
- **Correção:** `autorizarEmpresa()` (404 se não for a empresa do usuário), mesmo padrão dos outros services.
- **Risco:** baixo.

### ✅ 🟠 6. Cada nova tentativa após rejeição consumia um número novo (buracos na numeração)
- **Arquivo:** `NfeService::emitir()`.
- **Causa:** rejeição não consome o número na SEFAZ, mas o sistema sempre reservava o próximo.
- **Correção:** se a última NF-e da venda está rejeitada (mesmo ambiente e série e código ≠ 539), a mesma linha é reaproveitada com o mesmo número. Código 539 (número já usado com outra chave) força número novo.
- **Risco:** médio; só afeta emissões futuras.

### ✅ 🟠 7. Uso denegado (110/205/301/302/303) não era tratado
- **Causa:** virava "rejeitada", permitindo reemitir como se o número estivesse livre; o XML com o protocolo de denegação (que deve ser guardado) era perdido.
- **Correção:** nova situação `denegada` (migration), grava protocolo e XML via `Complements::toAuthorize` (a biblioteca aceita esses cStat).
- **Risco:** baixo.

### ✅ 🟠 8. Cancelamento usava o ambiente atual da empresa e descartava o XML do evento
- **Arquivo:** `NfeSenderService::cancelar()`, `NfeService::cancelar()`.
- **Causa:** se a empresa mudasse de homologação para produção, o cancelamento de uma nota de homologação iria para produção. O retorno do evento não era salvo (obrigatório guardar o `procEventoNFe`).
- **Correção:** `setEnvironment($nfe->ambiente)`; grava `xml_cancelamento` (`toAuthorize(lastRequest, response)`) e `data_cancelamento`. Download em `nfe.xml?tipo=cancelamento`.
- **Risco:** baixo.

### ✅ 🟠 9. Sem unicidade de número/série/ambiente por empresa
- **Correção:** migration `2026_10_20_000001_add_controle_sefaz_to_nfes_table` cria `unique(empresa_id, ambiente, serie, numero)`. A migration **aborta com mensagem clara** se já houver duplicados. `EmpresaService::proximoNumeronNfe()` nunca devolve número menor ou igual ao maior já usado na série/ambiente (evita colisão se a sequência ficou para trás).
- **Risco:** baixo. Não altera dados; falha explícita se houver duplicidade.

### ✅ 🟠 10. Possível "autorizada" sem protocolo
- **Arquivo:** `NfeSenderService::resultadoProtocolo()/autorizarComResposta()`.
- **Correção:** cStat 100/150 sem `nProt` vira situação desconhecida (pendente), nunca autorizada.

### ✅ 🟠 11. Certificado: sem checagem de validade, CNPJ e aviso de expiração
- **Arquivos:** `EmpresaService::aplicarCertificado()`, `NfeService::alertasConfiguracao()/create()`.
- **Correção:** upload recusa certificado vencido ou de outro CNPJ; a tela de emissão bloqueia certificado vencido/ilegível e avisa quando faltam menos de 30 dias.
- O que já estava correto: arquivo fica em `storage/app/certificados` (fora de `public`), senha criptografada com `Crypt::encryptString`, mensagens não expõem a senha.

### ✅ 🟠 12. Ambiente homologação/produção trocável por engano
- **Correção:** `EmpresaService::update()` exige o checkbox `confirmar_ambiente` quando `nfe_ambiente` muda (campo novo no form e no `EmpresaRequest`).

### ✅ 🟠 13. XML autorizado não era recuperável pela interface
- **Correção:** nova rota `GET nfe/{nfe}/xml` (`nfe.xml`) e botões "XML" / "XML cancelamento" em `nfe/show`.

### ✅ 🟡 14. Erro de schema era tratado como falha de comunicação
- **Correção:** `ValidatorException` (lançada antes do envio) vira `rejeitada` com a mensagem do schema — a nota não chegou à SEFAZ.

### ✅ 🟡 15. Certificado lido duas vezes; segunda leitura fora do try (erro 500 deixava nota pendente)
- **Correção:** `emitir()` reaproveita o `Tools` do builder; `consultar()`/`cancelar()` capturam `RuntimeException` do certificado.

### ⏳ 🟠 16. Inutilização de numeração não existe
- Números ficam sem uso quando: a venda é cancelada após uma rejeição, uma nota pendente nunca é resolvida, ou ocorre 539. A legislação exige inutilizar faixas não usadas. Exige tela/rota nova (`sefazInutiliza` + `Complements::toAuthorize` para `ProcInutNFe`). Não implementado por ser funcionalidade nova.

### ⏳ 🟠 17. Carta de correção (CC-e) e contingência (SVC/EPEC) não existem
- Sem contingência, com SEFAZ fora do ar a nota fica pendente até a consulta funcionar.

### ⏳ 🟡 18. Emissão síncrona dentro do request
- Envio + até 3 consultas de recibo (2 s cada) podem chegar perto do `max_execution_time`. Com a correção 1, um request interrompido deixa a nota pendente (seguro) e o usuário resolve com "Consultar SEFAZ". Mover para fila é recomendável depois.

### ⏳ 🟡 19. Notas antigas marcadas como "rejeitada" por falha de comunicação
- Dados anteriores à correção podem conter notas "rejeitadas" que na verdade foram autorizadas. Recomendo, para cada empresa em produção, consultar na SEFAZ as chaves das NF-e rejeitadas sem `codigo_status` (sinal de erro de comunicação).

### ⏳ 🟡 20. Cancelamento em duplo clique
- A segunda requisição recebe "573 — duplicidade de evento" e mostra erro, embora a primeira tenha cancelado. Inofensivo; não corrigido.

### ⏳ 🟡 21. Logs gravam o XML de retorno da SEFAZ
- `NfeSenderService::padronizar()/consultarRecibo()` logam a resposta completa (sem certificado ou senha, mas com CPF/nome do destinatário). Avaliar LGPD.

### ❓ Perguntas
1. **Destinatário sem documento:** o builder emite NF-e modelo 55 com `CPF 00000000000 / CONSUMIDOR FINAL`. A SEFAZ costuma rejeitar CPF zerado no modelo 55. Deseja exigir cliente identificado na NF-e?
2. **Endereço do destinatário:** quando o endereço do cliente está incompleto, `enderDest` é omitido. Confirmar com o contador/UF se isso é aceito no modelo 55.
3. **Sequência única para homologação e produção:** `nfe_sequencia` é compartilhada; ao virar para produção, a primeira nota de produção continua da numeração de homologação. Deseja sequência separada por ambiente (e por série)?
4. **Mapeamento de `tPag`:** é feito pelo nome da forma de pagamento (`Dinheiro`, `PIX`…); boleto/crediário viram `99`. Deseja um campo de código fiscal na forma de pagamento?

## Como testar manualmente (homologação)
1. Empresa → editar: trocar o ambiente sem marcar a confirmação deve dar erro; com a confirmação, salva.
2. Enviar um `.pfx` vencido ou de outro CNPJ: deve recusar com mensagem clara.
3. Emitir NF-e de uma venda com desconto em item e desconto geral: deve autorizar (antes era rejeitada por total).
4. Clicar duas vezes rápido em "Emitir": apenas uma NF-e é criada.
5. Desconectar a rede e emitir: a NF-e fica **Pendente** com aviso; reconectar e usar "Consultar SEFAZ".
6. Forçar uma rejeição (ex.: NCM inválido), corrigir e reemitir: o número deve ser o mesmo.
7. Cancelar uma NF-e autorizada: botão "XML cancelamento" deve aparecer e baixar o `procEventoNFe`.
8. Logar com usuário de outra empresa e acessar `/empresa/{id}/editar` e `/nfe/{id}/xml` de outra empresa: 404.

Testes automatizados: `tests/Feature/NfeEmissaoTest.php` (8 testes).

## Mudanças de contrato registradas
- Rotas novas: `POST nfe/{nfe}/consultar` (`nfe.consultar`), `GET nfe/{nfe}/xml` (`nfe.xml`). Nenhuma rota existente foi renomeada.
- `nfes.situacao` ganhou o valor `denegada`; colunas novas `recibo`, `xml_cancelamento`, `data_cancelamento`; índice único `nfes_empresa_ambiente_serie_numero_unique`.
- Campo de formulário novo `confirmar_ambiente` em `empresa/edit`.
- `NfeSenderService::cancelar()` ganhou o parâmetro `int $ambiente`.

## Rascunho de NF-e e checagem de pendências (pedido de 20/10)
- **Salvar rascunho:** a tela de emissão tem "Salvar rascunho" além de "Emitir". O rascunho é uma venda `em_andamento` com `vendas.nfe_rascunho = true`: não mexe em estoque, não gera conta a receber, não cria registro em `nfes` e **não consome número de NF-e**. Aparece no card "Rascunhos" da listagem de NF-e e na venda ("Continuar rascunho da NF-e"); pode ser editado, emitido ou excluído.
- **Rascunho valida só o formato** (ex.: NCM com 8 dígitos se informado); campos fiscais obrigatórios ficam para a emissão.
- **Emitir valida tudo de uma vez** (`NfePendenciasService`): natureza, forma de pagamento, itens, NCM/CFOP/CSOSN/CST PIS-COFINS por item (com o fallback item → produto → padrão da empresa), desconto maior que o total, CPF/CNPJ incompleto e UF/município do cliente diferentes da empresa com CFOP 5xxx. As pendências aparecem numa lista com botão "Corrigir" e a transação inteira é desfeita (sem estoque, sem número).
- **Modal de item reescrito:** busca com ↑/↓/Enter/Esc, código de barras seleciona sozinho, card do produto escolhido com "Trocar", quantidade/preço/desconto/total numa linha, dados fiscais recolhidos num bloco que abre sozinho quando falta algo, "Salvar e adicionar outro". Atalho Insert abre o modal.
- Corrigido de quebra: após erro de validação a tela perdia os itens digitados.

Contrato:
- Rotas novas: `GET nfe/rascunho/{venda}/editar` (`nfe.rascunho.edit`), `PUT nfe/rascunho/{venda}` (`nfe.rascunho.update`), `DELETE nfe/rascunho/{venda}` (`nfe.rascunho.destroy`). Campo novo `acao` (`rascunho`|`emitir`, padrão emitir) no `POST nfe/salvar`.
- Migration `2026_10_21_000001`: `vendas.nfe_rascunho` (boolean, default false) + índice `vendas_empresa_nfe_rascunho_index`.
- `/api/produtos/busca` também casa `codigo_barras` exato e devolve `tipo` e `controla_estoque` (campos adicionados; nada removido).

Testes: `tests/Feature/NfeRascunhoTest.php` (6 testes).
