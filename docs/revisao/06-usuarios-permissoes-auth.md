# 06 — Usuários, Perfis/Permissões e Autenticação

Arquivos revisados: `AuthController`, `Usuarios/UsuarioController`, `Usuarios/PerfilController`, `PerfilService`, `UsuarioRequest`, `PerfilRequest`, models `User`/`Perfil`, middlewares `CheckPermissao`/`CheckPerfil`/`EnsureUserIsActive`, `CatalogoPermissoes`, bindings em `AppServiceProvider`, `bootstrap/app.php`, `config/session.php`, views `auth/*`, `usuario/*`, `permissao/*`.

O que já estava correto: senha com cast `hashed`; sessão regenerada no login e invalidada no logout; usuário inativo derrubado pelo middleware e sessões apagadas ao inativar; `garantirEmpresa()` no CRUD de usuários; CSRF em todos os forms.

Legenda: ✅ corrigido · ⏳ pendente · ❓ pergunta

## Achados

### ✅ 🔴 1. Login sem limite de tentativas
- **Arquivo:** `AuthController::login()`; rotas `password.email` e `password.update`.
- **Correção:** `RateLimiter` por e-mail+IP (5 tentativas, bloqueio de 60 s, limpa no sucesso); `throttle:6,1` nas rotas de recuperação de senha.

### ✅ 🔴 2. Usuário não-admin com permissão `configuracoes` podia se promover a admin ou tomar a conta de um admin
- **Arquivos:** `UsuarioRequest::withValidator()`, `UsuarioController::destroy()`, `usuario/_form.blade.php`.
- **Causa:** a regra `exists:perfis,slug` aceitava `admin`, e não havia restrição para editar/excluir um admin (inclusive trocar e-mail e senha).
- **Correção:** só administradores podem atribuir o perfil `admin` ou alterar/excluir um administrador; a opção "Administrador" não aparece para não-admins.

### ✅ 🟠 3. Empresa podia ficar sem nenhum administrador
- **Arquivo:** `UsuarioController::update()/destroy()` — recusa rebaixar, inativar ou excluir o último admin ativo.

### ✅ 🟠 4. Perfil inativo mantinha as permissões; editar o usuário selecionava "Administrador"
- **Arquivos:** `User::chavesPermissao()` (perfil inativo → sem permissões), `usuario/_form.blade.php` (opção vazia "— Selecione —"; usuários com perfil legado `operador` passam a aparecer como Vendedor em vez de cair na primeira opção da lista, que era "Administrador").
- **Atenção:** usuários vinculados a perfis inativos perdem o acesso imediatamente após o deploy. Conferir: `SELECT u.email, p.slug FROM users u JOIN perfis p ON p.empresa_id=u.empresa_id AND p.slug=u.perfil WHERE p.ativo=0`.

### ✅ 🟡 5. "Esqueci a senha" revelava se o e-mail existia / estava inativo
- **Arquivo:** `AuthController::sendResetLink()` — resposta sempre igual; o link só é enviado para usuário ativo.

### ✅ 🟡 6. Perfil customizado chamado "Operador" tinha permissões ignoradas
- **Arquivo:** `PerfilService::store()` — slug `operador` é reservado (legado mapeado para `vendedor`).

### ✅ 🟡 7. Trocar a senha de outro usuário não derrubava as sessões dele
- **Arquivo:** `UsuarioController::update()`.

### ⏳ 🔴 8. `trustProxies(at: '*')` permite envenenar o link de redefinição de senha
- **Arquivo:** `bootstrap/app.php`.
- **Problema:** qualquer cliente pode mandar `X-Forwarded-Host`; o e-mail de reset seria montado com o domínio do atacante.
- **Por que não corrigi:** depende da topologia do servidor (IP do proxy/load balancer). **Ação antes de produção:** restringir `trustProxies` aos IPs reais e adicionar `$middleware->trustHosts(...)` ou `URL::forceRootUrl(config('app.url'))`. Ver pergunta 1.

### ⏳ 🟡 9. Outros
- Senha mínima de 6 caracteres sem regra de complexidade.
- `SESSION_SECURE_COOKIE` sem padrão (definir `true` no `.env` de produção com HTTPS).
- `CheckPerfil` não é usado.
- O binding `usuario` deixa o admin resolver usuários de outras empresas (o `garantirEmpresa()` bloqueia depois; sem impacto hoje).
- Dashboard mostra KPIs da empresa inteira para qualquer perfil (a view esconde, mas os valores são calculados). Ver pergunta 2.

### ❓ Perguntas
1. A aplicação vai rodar atrás de proxy/load balancer? Qual IP/domínio? (para fechar o item 8)
2. Vendedor deve ver o faturamento do dia da empresa no dashboard?

## Como testar manualmente
1. Errar a senha 5 vezes: a 6ª tentativa (mesmo com a senha certa) mostra "Muitas tentativas…" por 1 minuto.
2. Com um perfil customizado que tenha só `configuracoes`, editar o próprio usuário: a opção "Administrador" não aparece; forçando `perfil=admin` via DevTools, erro de validação.
3. Como único admin, tentar mudar o próprio perfil para Vendedor: mensagem "É preciso manter ao menos um administrador ativo".
4. Inativar um perfil customizado: usuários desse perfil perdem acesso aos menus.

Testes automatizados: `tests/Feature/UsuariosSegurancaTest.php` (6 testes).
