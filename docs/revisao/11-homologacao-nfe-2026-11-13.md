# 11 — Homologação NF-e (55) e NFC-e (65) na SEFAZ — 13/11/2026

Nenhuma nota foi emitida em produção. Sem commit, push ou deploy.

## Ambiente do teste

| Item | Valor |
|---|---|
| Emitente | empresa 1, PB, CRT 1 (Simples Nacional), CNPJ 06220266****** |
| Autorizador | SVRS (PB usa SVRS para NF-e e NFC-e). URLs `nfe-homologacao.svrs.rs.gov.br` e `nfce-homologacao.svrs.rs.gov.br` |
| tpAmb | 2 em `empresa.nfe_ambiente`, no JSON do `Tools` (modelos 55 e 65) e em todo pedido enviado |
| Biblioteca | sped-nfe 5.2.8, sped-common 5.1.17, sped-da 1.1.6; layout `PL_010_V1.30`, versão 4.00 |
| Configuração da biblioteca | não há arquivo: o JSON é montado em `NfeBuilderService::configuracaoJson()` e `NfceBuilderService::configuracaoJson()` |
| Certificado A1 | `storage/app/certificados/` (fora do git), ICP-Brasil (AC SAFEWEB RFB v5), válido até 12/08/2027, CNPJ confere. Senha criptografada com `APP_KEY`, não está no código |
| CSC/idCSC homologação | cadastrados (valores não exibidos) |
| CSC/idCSC produção | **vazios** |
| MySQL real | 8.0.46, só leitura (consulta da configuração) |
| Execução | PHP 8.4.27 (imagem `erp-teste-php84`), SQLite descartável com cópia só da linha da empresa 1 e cadastros fictícios (CPF/CNPJ de exemplo). Nenhum cliente real usado |
| Séries de teste | NF-e **98** e NFC-e **371** (a série 99 da NFC-e foi descartada: ver caso 5a). As séries do sistema (NF-e 001, NFC-e 265) não foram tocadas |
| Trava de segurança | toda chamada SOAP passou por uma classe que recusava host sem `homologacao` e pedido com `tpAmb` diferente de 2 |

Evidências (XML de cada pedido/resposta, XML gravados, PDFs, scripts): `~/backup-nfe-homolog-2026-11-13/evidencias/`.

## 1. Inventário do fluxo fiscal

| Etapa | Onde |
|---|---|
| Montagem do XML NF-e | `app/Services/Nfes/NfeBuilderService.php` — `buildAndSign()` → `montarIdentificacao/Emitente/Destinatario/Itens/Totais/Transporte/Pagamento/Informacoes()`; `Make('PL_010_V1.30')`, `Tools::signNFe()` (valida XSD) |
| Montagem do XML NFC-e | `app/Services/Nfces/NfceBuilderService.php` (estende o da NF-e): modelo 65, `tpImp` 4, destinatário opcional, troco, grupo `card` com `tpIntegra` 2 |
| CFOP/CSOSN/CST por item | `NfeVinculoFiscal` (item → produto → perfil fiscal → padrão da empresa → 5102/400/07) |
| Validação antes de reservar número | `NfePendenciasService::verificar()`, `pendenciasDeData()`, `pendenciasDeDestinatario()` (nova) |
| Datas (`dhEmi`, `dhSaiEnt`) | `NfeDatas` (fuso por UF; NFC-e usa o horário atual) |
| Envio e retorno | `NfeSenderService`: `enviar()` (lote síncrono, 1 NF-e), `consultarRecibo()`, `consultar()` (chave), `cancelar()`, `inutilizar()`, `motivoSeIndisponivel()` (status); `Standardize` + `Complements::toAuthorize` |
| Orquestração e gravação | `NfeService` (emitir, consultar, cancelar, inutilizar, danfe, xml) e `NfceService` (emitir, consultar, cancelar, danfe, verificar); `NfeRepository`, `NfceRepository` |
| Numeração | `NfeNumeracaoRepository::proximo()` (trava a linha, nunca abaixo do maior usado, pula faixa inutilizada); rejeitada reaproveita o número, exceto 539 |
| Banco | `nfes` (chave, protocolo, recibo, `xml_assinado`, `xml_autorizado`, `xml_cancelamento`, situação, cStat, motivo, datas; únicos `chave_acesso` e `empresa+modelo+ambiente+serie+numero`), `nfe_numeracoes`, `nfe_inutilizacoes`, `empresa` |
| Escolha do ambiente | `empresa.nfe_ambiente` (troca exige `confirmar_ambiente`); consulta, cancelamento e inutilização usam o ambiente gravado na nota |
| DANFE / DANFC-e | `NfeService::pdfDaNota()` (`Danfe`), `NfceService::pdf()` (`Danfce`) |
| QR code e urlChave | `Tools::signNFe()` → `addQRCode()`; URLs em `vendor/nfephp-org/sped-nfe/storage/wsnfe_4.00_mod65.xml` e `uri_consulta_nfce.json` |
| Rotas | `routes/web.php`: 333–348 (`nfe.*`), 307–308 (`venda.nfce.*`), 318–321 (`nfce.*`), 210 (`empresa.nfce.verificar`) |
| Controllers / PDV | `Nfes/NfeController`, `Nfces/NfceController`, `VendaService::emitirNfceDoPdv()` |
| Telas | `nfe/index`, `nfe/create`, `nfe/show`, `nfe/inutilizar`, `venda/_nota-fiscal*`, `venda/pdv`, `empresa/edit` |
| Teste local sem envio | `php artisan nfe:testar {empresa}` |
| Não existe | carta de correção (CC-e), contingência (SVC/EPEC), fila/job de emissão (é síncrona) |

## 2. Casos de teste

Tempos: chamada SOAP de 320 a 1010 ms; emissão completa (status + envio + gravação) de 0,8 a 1,7 s.

| Caso | Tipo | Resultado inicial | Causa | Correção aplicada | Resultado final | Status |
|---|---|---|---|---|---|---|
| 1. 1 produto, destinatário CPF | NF-e | 100 Autorizado | — | — | 100, nº 1, prot. 325260000056042 | Emitido em homologação |
| 2. 3 produtos, desconto no item + desconto geral + acréscimo, CNPJ sem IE, PIX | NF-e | **696** Operação com não contribuinte deve indicar operação com consumidor final | `indFinal` automático = 0 para qualquer CNPJ, mas CNPJ sem IE sai como não contribuinte (`indIEDest` 9) | builder: automático = 0 só para CNPJ com IE; "Não" escolhido para não contribuinte é barrado antes de reservar número | 100, nº 2 (mesmo número reaproveitado), vProd 60,85 / vDesc 4,13 / vOutro 1,10 / vNF 57,82 | Emitido (cancelado no caso 8) |
| 3. Cliente sem CPF/CNPJ | NF-e | **237** CPF do destinatário inválido; depois do CPF, **726** NF-e sem endereço do destinatário | builder enviava `CPF 00000000000` e omitia `enderDest` quando o endereço estava incompleto | barrar na entrada e no builder (sem documento válido ou sem logradouro/bairro/cidade); número em branco vira `S/N`; cadastro fictício completado | 100, nº 3 (3ª tentativa) | Emitido; clientes reais nessa situação = pendência de cadastro |
| 3b. Venda sem cliente pela tela da venda | NF-e | erro local, mas o nº 4 já tinha sido reservado | `NfeService::emitir()` reservava o número antes de checar o destinatário | checagem do destinatário antes da reserva | bloqueado sem consumir número | Ok (nº 4 da série de teste ficou sem uso) |
| 4. Substituição tributária | NF-e | — | sistema bloqueia CSOSN 201/202/500 e CRT 3 | — | — | Não testado (não suportado) |
| 4. Simples Nacional | NF-e | — | casos 1–3 são CRT 1 com CSOSN 102 e 400 | — | 100 | Emitido |
| 5a. Sem identificação, dinheiro com troco | NFC-e | **539** Duplicidade de NF-e com diferença na chave | a série 99 já tinha os nº 1 e 2 usados por este CNPJ em homologação em 05/2015 | nenhuma no código (o sistema tratou certo: pegou número novo); série de teste trocada para 371 | 100, nº 1, `vTroco` 3,65 | Emitido |
| 5b. Com CPF, cartão de crédito | NFC-e | 100 | — | — | 100, nº 2, grupo `card` com `tpIntegra` 2 | Emitido (cancelado no caso 8) |
| 6. QR code e consulta da chave | NFC-e / NF-e | 100 na consulta | — | — | QR v3 `http://www.sefaz.pb.gov.br/nfcehom?p=<chave>\|3\|2`, `urlChave` `www.sefaz.pb.gov.br/nfcehom`; `NfeConsultaProtocolo` = 100 para NFC-e e NF-e | Ok |
| 7. Status do serviço | 55 e 65 | 107 Serviço em operação | — | — | 107 | Ok |
| 8. Cancelamento | NF-e nº 2 e NFC-e nº 2 | 135 Evento registrado | — | — | `situacao` cancelada, `procEventoNFe` gravado com o protocolo do evento (…56045 e …52233) | Ok |
| 9. Inutilização | NF-e 98 nº 900–901; NFC-e 371 nº 900 | 102 Inutilização homologada | — | — | prot. 325260000056046 e 325260000052234, `procInutNFe` gravado | Ok |
| 9b. Inutilizar o nº 4 da NF-e | NF-e | recusado pela tela | regra: faixa com nota gravada (mesmo rejeitada sem envio) não pode ser inutilizada | — | — | Pendência de código |
| 10. Carta de correção | — | — | não existe no sistema | — | — | Não testado |
| 11. Lote assíncrono | NF-e | **452** Resposta assíncrona para lote com 1 NF-e | o sistema sempre envia 1 NF-e síncrona; forçado `indSinc=0` no teste | nenhuma (tratado certo: rejeição de lote, número livre) | — | Não se aplica |
| 11b. Recibo com 2 NF-e no mesmo lote | NF-e nº 6 e 7 | 103 → 104/100/100; a nº 7 recebeu chave e protocolo da nº 6 (índice único impediu gravar) | `extrairInfProt()` pegava sempre o 1º `protNFe` | escolher o `protNFe` pela chave da nota | nº 7 resolvida por "Consultar SEFAZ" (100, prot. …56048); releitura do recibo confere as duas | Emitido |
| Final | NF-e nº 8 e NFC-e nº 3 | 100 | — | rodada com todo o código corrigido | 100 | Emitido |

Pré-validação (antes de transmitir, todos os casos): XML válido no XSD `PL_010_V1.30`; chave com 44 dígitos, DV e composição corretos; `dhEmi` com fuso (`-03:00`); soma dos itens = `ICMSTot`; `vPag − vTroco = vNF`; só CSOSN (CRT 1); NCM com 8 dígitos. Após as correções: `nfeProc`, `procEventoNFe` e `procInutNFe` gravados validados nos XSD.

DANFE (NF-e nº 1) e DANFC-e (NFC-e nº 1) gerados pelos mesmos métodos da tela: layout, protocolo, marca "sem valor fiscal", QR code e URL de consulta corretos. DANFE de NF-e cancelada não mostrava "CANCELADA": corrigido.

## 3. Pendências de emissão

| Documento ou caso | Motivo | Classificação | O que é necessário para emitir | Responsável sugerido |
|---|---|---|---|---|
| NF-e para cliente sem CPF/CNPJ ou sem endereço | SEFAZ rejeita (237 / 726); agora o sistema avisa antes | Cadastro | completar CPF/CNPJ e endereço do cliente, ou emitir NFC-e | Operador / cadastro |
| Empresa com CRT 2 | builder envia CSOSN; o esperado é rejeição 591 | Fiscal | decisão do item 5.1 | Contador |
| Regime Normal (CRT 3), ST, interestadual, devolução | não implementado (bloqueado na tela) | Código + fiscal | desenvolvimento com regras definidas pelo contador | Contador + desenvolvimento |
| NFC-e e NF-e em produção | CSC e idCSC de produção vazios | Certificado / configuração | gerar o CSC de produção no portal da SEFAZ-PB e cadastrar na empresa | Responsável fiscal |
| Número com nota rejeitada que não será reemitida (ex.: venda cancelada depois da rejeição) | a tela de inutilização recusa faixa com nota gravada | Código | permitir inutilizar quando a nota da faixa for rejeitada (exceto 539), sem XML autorizado, e impedir o reaproveitamento depois | Desenvolvimento |
| CC-e e contingência (SVC/EPEC) | não existem | Código | desenvolvimento | Desenvolvimento |
| Números 4 e 5 da série de teste 98 e números 1–2 da série 99 (NFC-e) | só homologação | — | nada; série 99 não deve ser usada em homologação para este CNPJ | — |

## 4. Alterações por arquivo

Originais em `~/backup-nfe-homolog-2026-11-13/` (mesma estrutura de pastas). Patch completo para reversão: `~/backup-nfe-homolog-2026-11-13/alteracoes.patch` (`patch -R -p1 < alteracoes.patch` na raiz do projeto). Pint aplicado só nesses arquivos.

### `app/Services/Nfes/NfeBuilderService.php`
- `montarIdentificacao()` — `indFinal` automático. Original:
  ```php
  $cnpjDestino = $cliente ? $this->somenteDigitos($cliente->cnpj) : '';
  ...
  $indFinal = $venda->ind_final;
  if ($indFinal === null) {
      $indFinal = strlen($cnpjDestino) === 14 ? 0 : 1;
  }
  ```
  Agora: 0 só para CNPJ com IE (`destinatarioContribuinte()`); `ind_final = 0` com não contribuinte lança `MENSAGEM_NAO_CONTRIBUINTE`.
- `montarDestinatario()` — original enviava consumidor sem documento assim (removido; agora lança `pendenciaDocumentoDestinatario()`):
  ```php
  if ($cliente === null || $this->ehConsumidorSemDocumento($cliente)) {
      $make->tagdest($this->dados(['CPF' => '00000000000', 'xNome' => 'CONSUMIDOR FINAL', 'indIEDest' => 9]));
      if ($cliente !== null) { $this->montarEnderecoDestinatario($make, $cliente); }
      return;
  }
  ...
  } else {
      $dados['CPF'] = '00000000000';
      $dados['xNome'] = 'CONSUMIDOR FINAL';
      $dados['indIEDest'] = 9;
  }
  ```
- `montarEnderecoDestinatario()` — original omitia o endereço:
  ```php
  if ($logradouro === '' || $numero === '' || $bairro === '' || $cidade === '') {
      return;
  }
  ```
  Agora lança `pendenciaEnderecoDestinatario()`; número em branco = `S/N`. Texto "ou deixe o endereço em branco" removido da mensagem de outro município.
- Removido `ehConsumidorSemDocumento()` (sem uso). Novos: constante `MENSAGEM_NAO_CONTRIBUINTE`, `pendenciaDocumentoDestinatario()`, `pendenciaEnderecoDestinatario()`, `destinatarioContribuinte()`. Pint: `use Carbon\Carbon` sem uso removido, `new NfeDatas()` → `new NfeDatas`.

### `app/Services/Nfes/NfePendenciasService.php`
- `verificar()` — original:
  ```php
  $documento = $this->digitos($cliente->cnpj) ?: $this->digitos($cliente->cpf);
  if ($documento !== '' && ! in_array(strlen($documento), [11, 14], true)) {
      $pendencias[] = 'O CPF/CNPJ do cliente "'.$cliente->nome.'" está incompleto; a nota sairia como consumidor final.';
  }
  if (count($preenchidos) === count($endereco)) { /* CEP, UF e cidade */ }
  ```
  Agora usa `pendenciasDeDestinatario()`; CEP/UF/cidade continuam iguais, conferidos quando o endereço está completo.
- Novo `pendenciasDeDestinatario()` (237, 726, 696).

### `app/Services/Nfes/NfeService.php`
- `emitir()` — original, antes de reservar número:
  ```php
  $datas = $this->pendenciasService->pendenciasDeData($venda, $empresa);
  if ($datas !== []) {
      return $datas[0];
  }
  ```
  Agora junta `pendenciasDeData()` e `pendenciasDeDestinatario()`.
- `pdfDaNota()` — original `$pdf = (new Danfe($nfe->xml_autorizado))->render();`. Agora `setCancelFlag($nfe->situacao === 'cancelada')`.

### `app/Services/Nfes/NfeSenderService.php`
- `extrairInfProt()` — original:
  ```php
  $prot = data_get($std, 'protNFe');
  if (is_array($prot)) {
      $prot = $prot[0] ?? null;
  }
  ```
  Agora recebe a chave e escolhe o `protNFe` dela (com vários e nenhum da chave, devolve `null` = situação desconhecida, nota fica pendente). Chamadas em `consultar()`, `resultadoProtocolo()` e `autorizarComResposta()` passam a chave. Novo `chaveDoXml()`.

### Testes
- `tests/Feature/NfeEmissaoTest.php`: vendas dos testes existentes passam a ter cliente com CPF e endereço (`clienteIdentificado()`); 6 testes novos (CNPJ com/sem IE, 696, `S/N`, venda sem cliente e cliente sem endereço não reservam número, protocolo pela chave).
- `tests/Feature/AuditoriaPrimeiraLevaTest.php` (`test_nfe_cancelada_permite_nova_emissao`): venda com cliente identificado.

Fora deste trabalho: o texto do seletor "Consumidor final" em `nfe/create.blade.php` foi atualizado pela sessão de revisão visual ("Automático (CNPJ com IE = não; demais = sim)").

## 5. Decisões fiscais pendentes

As três dúvidas da primeira rodada foram resolvidas na rodada 2 (seção 8) com a regra oficial e teste na SEFAZ:

1. ~~CRT 2~~ → **bloqueado**. CSOSN só vale com CRT 1 ou 4 (rejeição 591). CRT 2 informa o ICMS por CST do regime normal, que o sistema não emite.
2. ~~IE "ISENTO"~~ → **continua `indIEDest` 9**. O MOC prevê 2 para contribuinte isento, mas a SEFAZ-PB recusou o 2 em homologação (805).
3. **CSOSN 400 e PIS/COFINS 07 da empresa 1**: a orientação da SEFAZ-PB para o Simples Nacional é **CSOSN 102** (mercadoria sem ST) ou **500** (com ST já retido, CFOP 5405), e **PIS/COFINS CST 99** com alíquota 0% e valor 0,00. O sistema agora aceita esses códigos e os usa como padrão quando nada está cadastrado. **Fica para a empresa/contador:** trocar no cadastro da empresa (Empresa → NF-e) o CSOSN padrão de 400 para 102 e os CST de PIS/COFINS de 07 para 99, e marcar com CSOSN 500 + CFOP 5405 os produtos com substituição tributária. Não alterei o cadastro real.

## 6. O que não foi testado e por quê

| Item | Motivo |
|---|---|
| NF-e regime normal, ST, interestadual, devolução | não suportados pelo sistema |
| Contingência (offline/EPEC/SVC) | não existe no sistema |
| Destinatário contribuinte com IE (`indFinal` 0) transmitido | sem IE fictícia válida no cadastro da PB; coberto só por teste automatizado |
| GTIN real (validação no cadastro CCG) | produtos de teste com `SEM GTIN` |
| MEI (CRT 4) | empresa é CRT 1 |
| Telas no navegador e PDV com `valor_recebido` | executado pelos mesmos métodos de service que os controllers chamam; telas cobertas pelos testes automatizados |
| Consulta pública do QR code no portal da SEFAZ-PB | conferido pelo webservice de consulta (100), não pelo navegador |
| SEFAZ fora do ar / timeout real | a SEFAZ respondeu 107 o tempo todo; o caminho "pendente → Consultar SEFAZ" foi exercitado no caso 11b |
| Data de emissão retroativa com mais de 30 dias | agora é barrada antes de reservar número (rodada 2); coberto por teste automatizado, não transmitido |
| NF-e com CSOSN 101 (crédito), 201/202/203/900 (ST calculado) | não suportados; o sistema recusa antes de reservar número |

Testes automatizados (`php artisan test`, PHP 8.4): **341 passaram, 1 falhou**. A falha (`NfceRotinaTest > placeholder dos 500 reais so aparece na paraiba`) já acontecia antes destas mudanças e não toca o fluxo fiscal; a suspeita levantada pela sessão de revisão visual é um cache novo de empresa feito por outra sessão. `vendor/bin/pint --test` nos arquivos alterados: ok.

## 7. Checklist para produção (NÃO EXECUTADO)

- [ ] Backup do banco antes de qualquer mudança.
- [ ] Rodar `php artisan migrate` (cria a tabela `nfe_cartas_correcao`, aditiva).
- [ ] Ajustar o padrão fiscal da empresa e dos produtos com o contador (seção 5, item 3).
- [ ] Cadastrar CSC e idCSC de **produção** da NFC-e (hoje vazios).
- [ ] Conferir o certificado A1 de produção (mesmo arquivo serve; validade 12/08/2027).
- [ ] Trocar `nfe_ambiente` para 1 na empresa (exige marcar a confirmação), com aprovação do responsável fiscal.
- [ ] Revisar as URLs de produção (SVRS) e a versão do QR code da PB em produção na sped-nfe 5.2.8 (a biblioteca usa v2 com CSC em produção e v3 em homologação); manter a biblioteca atualizada.
- [ ] Definir séries e numeração de produção (a sequência é separada por ambiente e série).
- [ ] Resolver as decisões fiscais da seção 5 com o contador.
- [ ] Rodar `php artisan optimize` depois de atualizar o código.
- [ ] Emitir uma NF-e e uma NFC-e de baixo valor, conferir DANFE/DANFC-e, consultar pela chave e, se necessário, cancelar dentro do prazo (NF-e 24 horas; NFC-e na PB 30 minutos).
- [ ] Inutilizar até o dia 10 do mês seguinte os números que ficarem pulados (inclusive de nota rejeitada que não será reemitida).

## 8. Rodada 2 — regras pesquisadas e aplicadas (14/11/2026)

### 8.1 Regras e fontes

| Regra | Fonte | O que o sistema faz agora |
|---|---|---|
| CSOSN só com CRT 1 ou 4; CRT 2 informa CST (rejeição 591) | [Orientações SEFAZ-PB Simples Nacional](https://www.sefaz.pb.gov.br/ser/images/e-docs/ORIENTACOES_SIMPLES_NACIONAL_NFE.pdf); [rejeição 591](https://buscadorncm.com.br/nfe/rejeicoes/591) | CRT 2 é bloqueado antes de montar o XML, como o CRT 3 |
| Simples Nacional: PIS/COFINS CST 99, alíquota 0%, valor 0,00; CSOSN 102 sem ST, 500 com ST retido | [Orientações SEFAZ-PB](https://www.sefaz.pb.gov.br/ser/images/e-docs/ORIENTACOES_SIMPLES_NACIONAL_NFE.pdf) | Aceita CST 49/99 (grupo `PISOutr`/`COFINSOutr` zerado) e CSOSN 500 (sem o grupo opcional de ST retido). Padrão de reserva passa de 400/07 para 102/99 |
| CSOSN 500 ↔ CFOP 5405 (rejeição 386) | Teste em homologação (NFC-e 371 nº 4 e 6) | Par trocado é recusado na emissão, nas pendências e no cadastro de perfil fiscal |
| indIEDest 2 (contribuinte isento) | MOC; **SEFAZ-PB recusou com 805** em homologação | IE "ISENTO" continua saindo como 9, sem a tag IE |
| dhEmi da NF-e: até 7 dias = 100; 7 a 30 dias = 150 "fora de prazo"; mais de 30 = 228 | [SVRS — NT 2025.001](https://receita.fazenda.rs.gov.br/01-09-2025-regras-de-validacao-da-nt-2025-001); [MOC, regra B09-20](http://moc.sped.fazenda.pr.gov.br/NFe_NFCe/RegrasDeValida%C3%A7%C3%A3o.html) | Data com 30 dias ou mais é barrada antes de reservar número. O 150 já era tratado como autorizada |
| Cancelamento: NF-e 24 h (501); NFC-e na PB 30 min | [MOC, cancelamento](http://moc.sped.fazenda.pr.gov.br/NFe_NFCe/RecepcaoEventoCancelamento.html); [Portaria GSER 17/2018, art. 6º, II](https://www.legisweb.com.br/legislacao/?id=356019) | `Nfe::podeCancelar()` usa 30 minutos para NFC-e da PB; a tela mostra o prazo certo |
| CC-e (110110): só NF-e autorizada (580), sequência 1–20 (594), texto 15–1000, não existe para NFC-e (784), sem prazo no MOC | [MOC, Carta de Correção](http://moc.sped.fazenda.pr.gov.br/NFe_NFCe/RecepcaoEventoCartaCorrecao.html) | Implementada (ver 8.2) |
| Inutilizar número pulado até o dia 10 do mês seguinte; só número nunca usado na SEFAZ | [Portaria GSER 17/2018, art. 6º, §1º](https://www.legisweb.com.br/legislacao/?id=356019) | Número de nota **rejeitada** pode ser inutilizado (antes era bloqueado); a reemissão da venda pega outro número |
| NFC-e ≥ R$ 500 na PB exige CPF/CNPJ (rejeição 750 desde 04/05/2026) | [Comunicado SEFAZ-PB](https://www.coad.com.br/home/noticias-detalhe/136883/sefaz-pb-comunica-novas-validacoes-de-emissao-da-nfc-e-sem-cpf-ou-cnpj-acima-de-r-50000) | Já existia; mantido |
| Fim da autorização assíncrona (NT 2023.002 v1.02: produção 05/04/2027) | [Inventti — NT 2023.002](https://inventti.com.br/?p=25243) | Já envia síncrono (`indSinc=1`); nada a mudar |
| Fim da inutilização só no AC, BA e SP (Ajustes SINIEF 38 e 40/2026) | [Inventti — Ajustes 30 a 40/2026](https://inventti.com.br/?p=25084) | Não afeta a PB |
| QR Code v3 (NT 2025.001) | [NT 2025.001](https://blog.tecnospeed.com.br/nota-tecnica-2025-001-nfc-e-qr-code/) | A sped-nfe usa v3 em homologação e v2 (com CSC) em produção para a PB; v2 segue aceita. Não mexi na biblioteca |

### 8.2 Alterações por arquivo

Originais em `~/backup-nfe-homolog-2026-11-13/rodada2-2026-11-14/`; diff completo em `alteracoes-rodada2.patch` (reverte com `patch -R -p1`, conferido em dry-run).

- **`app/Services/Nfes/NfeBuilderService.php`**
  - `validarEmissaoInterna()`: novo bloqueio `crt === 2` (mensagem cita 591).
  - Constantes: `CSOSN_SEM_CALCULO` ganhou `'500'`; novas `CST_OUTRAS_ZERADAS = ['49','99']`, `CST_ACEITOS`, `CFOP_SUBSTITUIDO = ['5405','5656','5667']`.
  - `montarItens()`: `tagPIS/tagCOFINS` passam por `grupoPisCofins()` (CST 49/99 com `vBC 0.00`, `p 0.0000`, `v 0.00`); checa `pendenciaCfopCsosn()`. Original: `$make->tagPIS($this->dados(['item' => $nItem, 'CST' => $cstPis]));`.
  - `cstResolvido()`: aceita `CST_ACEITOS` (original: só `CST_SEM_INCIDENCIA`, 04 a 09).
  - `montarIdentificacao()/montarDestinatario()`: usam `indIEDest()` (1 ou 9, mesma regra de antes, agora num lugar só).
  - Novos: `indIEDest()`, `pendenciaCfopCsosn()`, `grupoPisCofins()`.
- **`app/Services/Nfes/NfePendenciasService.php`**: mensagens com 500 e 49/99; par CFOP×CSOSN; data com 30 dias ou mais (228).
- **`app/Services/Nfes/NfeDatas.php`**: `ATRASO_MAXIMO_DIAS = 30` e `diasDeAtraso()`.
- **`app/Services/Nfes/NfeVinculoFiscal.php`**: reserva `'400'` → `'102'` e `'07'` → `'99'` (só vale quando item, produto, perfil e empresa estão vazios).
- **`app/Models/Nfes/Nfe.php`**: `podeCancelar()` usa `prazoCancelamentoMinutos()` (original: `subHours(24)` fixo); novos `prazoCancelamentoTexto()`, `aceitaCartaCorrecao()`, relação `cartasCorrecao()`, constantes `PRAZO_CANCELAMENTO_NFCE_MINUTOS = ['PB' => 30]` e `CCE_MAXIMO = 20`.
- **CC-e (novo)**: `NfeSenderService::cartaCorrecao()` e `cartasRegistradas()` (recupera da consulta da chave quando volta 573); `NfeService::cartaCorrecao()` (trava a nota, calcula a sequência, grava só 135/136) e `arquivoCartaCorrecao()` (XML e PDF via `Daevento`); `NfeController::cartaCorrecao/cartaCorrecaoArquivo`; rotas `nfe.carta-correcao` e `nfe.carta-correcao.arquivo`; model `NfeCartaCorrecao`; migration aditiva `2026_11_14_000001_create_nfe_cartas_correcao_table` (com `down()`); métodos no `NfeRepository`.
- **Inutilização**: `NfeRepository::faixaOcupada()` ignora nota rejeitada (exceto 539); novos `rejeitadasNaFaixa()` e `numeroInutilizado()`; `NfeService::emitir()` e `NfceService::emitir()` não reaproveitam número inutilizado. Original da mensagem: "Se a nota foi rejeitada, emita de novo pela venda: o mesmo número é usado."
- **`app/Services/Nfces/NfceBuilderService.php`**: `validarCadastroNfce()` checa o par CFOP×CSOSN antes de reservar número.
- **`app/Services/Nfces/NfceService.php`**: mensagem de prazo mostra "30 minutos"/"24 horas".
- **`app/Http/Requests/PerfisFiscais/PerfilFiscalRequest.php`**: aceita 500 e 49/99; `after()` recusa par CFOP×CSOSN trocado.
- **Telas** (combinado com a sessão de revisão visual): `nfe/show` (botão, lista e modal da CC-e; prazo de cancelamento), `nfe/create` (opções 500, 49, 99; listas vindas das constantes), `perfil-fiscal/_form` e `empresa/edit` (opções e padrões 102/99).
- **Testes**: `NfeCartaCorrecaoTest` (novo, 5 casos), 7 casos novos em `NfeEmissaoTest`, 1 em `PerfilFiscalTest`, 1 em `NfceRotinaTest`; `SefazSimulada` guarda eventos e os devolve na consulta. `PerfilFiscalTest` antigo usava CSOSN 500 como "recusado": trocado para 202.

### 8.3 Resultado na SEFAZ (homologação, SVRS, 14/11/2026)

| Caso | Documento | cStat / xMotivo | Explicação |
|---|---|---|---|
| CSOSN 102 + PIS/COFINS 99 zerado | NF-e 98 nº 9 | 100 Autorizado | CST 99 com `PISOutr` zerado aceito |
| CSOSN 500 sem ST retido | NF-e 98 nº 10 | 100 Autorizado | Grupo de ST retido é opcional |
| CNPJ com IE "ISENTO" como `indIEDest` 2 | NF-e 98 nº 11 | **805** "A SEFAZ do destinatario nao permite Contribuinte Isento" | A PB não aceita o 2 → mapeamento voltou para 9 |
| Mesma venda reemitida após inutilizar o nº 11 | NF-e 98 nº 12 | 100 Autorizado | Saiu com `indIEDest` 9 e número novo |
| NFC-e CSOSN 500 + CFOP 5102 | NFC-e 371 nº 4 | **386** "CFOP nao permitido para o CSOSN informado" | CSOSN 500 exige CFOP de ST |
| NFC-e CSOSN 500 + CFOP 5405 + CST 99 | NFC-e 371 nº 5 | 100 Autorizado | Par correto |
| NFC-e CSOSN 102 + CFOP 5405 | NFC-e 371 nº 6 | **386** | O 5405 exige CSOSN 500 |
| CC-e nº 1 e nº 2 | NF-e 98 nº 1 | 135 Evento registrado (prot. …56052, …56053) | |
| CC-e reenviada com a sequência 1 já usada | NF-e 98 nº 1 | **573** "Duplicidade de Evento" → consulta trouxe as cartas 1 e 2 com os protocolos originais | Recuperação funcionou contra a SEFAZ real |
| CC-e nº 3 | NF-e 98 nº 1 | 135 (prot. …56054) | |
| Inutilização de números rejeitados | NF-e 98 nº 4–5 e 11; NFC-e 371 nº 4 e 6 | 102 Inutilização homologada (4 pedidos) | A SEFAZ aceita inutilizar número de nota rejeitada |

XMLs gravados conferidos no XSD: `procNFe_v4.00` (notas 14, 15, 18 e 20 do banco de teste) `procCCeNFe_v1.00` (as 3 cartas, inclusive as 2 recuperadas pela consulta) e `procInutNFe_v4.00` (as 4 inutilizações). PDF da CC-e conferido visualmente. Evidências em `~/backup-nfe-homolog-2026-11-13/evidencias/rodada2/` (cópia do SQLite sem certificado, senha e CSC).

Testes automatizados: **360 passaram, 0 falharam**. `vendor/bin/pint --test` nos 20 arquivos PHP alterados: ok.
