# 07 — Cadastros (clientes, fornecedores, funcionários, empresa, cadastros rápidos)

Arquivos revisados: `Clientes`, `Fornecedores`, `Funcionarios`, `Empresas`, `FormasPagamento` (controllers, services, repositories, requests, models, migrations, views), cadastros rápidos em `routes/api_interna.php`, `components/modal-cadastro-rapido`.

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. CPF/CNPJ de cliente únicos no sistema inteiro (multiempresa quebrado)
- **Arquivos:** migration **nova** `2026_10_20_000002_unicidade_documento_cliente_por_empresa.php`; `ClienteRequest`; `/api/clientes/store`.
- **Causa:** `clientes.cpf`/`cnpj` com `unique()` global. A empresa B não conseguia cadastrar um cliente que já existisse na empresa A, e a mensagem "CPF já cadastrado no sistema" revelava isso.
- **Correção:** a migration troca o unique global por índices `(empresa_id, cpf)` e `(empresa_id, cnpj)`; a unicidade passa a ser validada por empresa, ignorando clientes excluídos (antes, um cliente excluído bloqueava o CPF para sempre).
- **Risco:** baixo; não altera dados. O `down()` recria o unique global e falhará se duas empresas já tiverem cadastrado o mesmo documento.
- **Limitação:** a comparação é pelo texto gravado. O formulário grava com máscara; registros antigos sem máscara não colidem com os novos. Normalizar exigiria UPDATE nos dados (pendente).

### ✅ 🔴 2. `/api/empresas/busca` listava todas as empresas do sistema
- **Arquivo:** `routes/api_interna.php` — restrito à empresa do usuário (rota não é usada pelas telas; contrato mantido).

### ✅ 🟠 3. Cadastro rápido de cliente/fornecedor sem validação de CPF/CNPJ
- **Arquivo:** `routes/api_interna.php` (`api.clientes.store`, `api.fornecedores.store`) — regras `cpf`/`cnpj` e unicidade por empresa. Documentos inválidos quebram a emissão de NF-e.

### ✅ 🟠 4. Funcionário podia ser vinculado a usuário de outra empresa
- **Arquivo:** `FuncionarioRequest` — `usuario_id` com `exists` filtrado por empresa.

### ✅ 🟠 5. Exclusão da própria empresa com um clique
- **Arquivos:** `EmpresaService::destroy()`, `empresa/index.blade.php`.
- **Causa:** o único registro visível é a empresa do usuário; excluí-la deixa todos os usuários com `empresa = null` (NF-e e certificado quebram) e não há como desfazer pela tela.
- **Correção:** o botão foi removido e a rota `empresa.destroy` (mantida) responde com mensagem de erro. **Mudança de comportamento registrada.**

### ⏳ Pendências
- `ClienteRequest`, `FornecedorRequest`, `FuncionarioRequest`, `FormaPagamentoRequest` exigem `empresa_id` no formulário (o valor é ignorado; a regra só expõe quais IDs existem).
- Fornecedores não têm unicidade de CNPJ por empresa.
- O fluxo "Nova empresa" cria empresa sem vínculo com usuário nenhum (código morto na prática). Ver pergunta.
- `certificado_pfx` sem limite de tamanho no `EmpresaRequest`.
- Normalizar CPF/CNPJ (só dígitos ou sempre com máscara) com backfill.

### ❓ Pergunta
- A tela "Nova empresa" deve existir para os clientes? Hoje cada usuário pertence a uma única empresa e a empresa criada fica inacessível.

## Como testar manualmente
1. Em duas empresas diferentes, cadastrar cliente com o mesmo CPF: as duas salvam.
2. Na mesma empresa, repetir o CPF: erro "Já existe um cliente com este CPF".
3. Cadastro rápido de cliente (modal na venda) com CPF inválido: erro de validação.
4. Tela de empresas: não há mais botão de excluir.

**Antes do deploy:** rodar `php artisan migrate` (aplica `2026_10_20_000001` e `2026_10_20_000002`).

Testes automatizados: `tests/Feature/CadastrosIsolamentoTest.php` (5 testes).
