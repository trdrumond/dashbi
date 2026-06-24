# DashBI 2.0

**DashBI 2.0** é um sistema corporativo para **governança, controle de acesso e distribuição** de relatórios Power BI, integrado ao Microsoft Workspace. Camada intermediária entre usuários e o Power BI.

**Stack:** PHP 7.3, MySQL 5.7+, sem framework. Arquitetura em camadas: Controller → Service → Repository → Database.

---

## Estrutura do projeto

```
/app
  /config       Config.php, Database.php
  /core         Request, Response, Router, Controller
  /helpers      HttpClient, CryptoHelper, JsonHelper
  /services     TokenService, PowerBIService, AuthService, PermissionService, etc.
  /repositories Tenant, Workspace, Report, User, Group, Permission, Log
  /modules      auth, tenants, workspaces, reports, permissions, users, groups, sync, logs
/public         index.php (front controller), .htaccess
/storage        /cache (tokens), /logs
/database       schema.sql
/cron           sync_workspaces.php, expire_permissions.php
```

---

## Requisitos

- PHP 7.3+ (extensões: pdo_mysql, json, curl, openssl)
- MySQL 5.7+ ou MariaDB
- Servidor web (Apache com mod_rewrite ou equivalente)

---

## Instalação

### 1. Banco de dados

Crie o banco e importe o schema:

```bash
mysql -u root -p < database/schema.sql
```

Ou no MySQL:

```sql
SOURCE C:/xampp/htdocs/workbi/database/schema.sql;
```

O script cria o banco `bi_portal`, as tabelas e um usuário inicial (**admin@localhost** / senha **password**). Troque a senha após o primeiro login. Para gerar outro hash:

```bash
php scripts/gerar_senha.php "admin123"
```

### 2. Configuração

Edite `app/config/Config.php`:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` para o MySQL
- `JWT_SECRET`: chave forte para criptografia de client_secret e segurança (alterar em produção)

### 3. Document root

Aponte o document root do servidor para a pasta `public` (ex.: `http://localhost/workbi/public/`).  
Se usar a raiz do projeto, ajuste o RewriteBase no `public/.htaccess` (ex.: `/workbi/public/`).

### 4. Storage

As pastas `storage/cache` e `storage/logs` são criadas automaticamente pelo bootstrap. Garanta permissão de escrita.

---

## Integração Microsoft (Power BI)

### Azure AD – App Registration

1. Azure Portal → Azure Active Directory → App registrations → New registration.
2. Anote **Application (client) ID** e **Directory (tenant) ID**.
3. Certificates & secrets → New client secret → anote o **Value** (client_secret).
4. API permissions → Add → Microsoft Power BI Service → Application permissions:
   - `Tenant.Read.All`
   - `Report.Read.All` (ou escopos necessários)
5. Grant admin consent.

### Power BI Admin

- Tenant settings → **Allow service principals to use Power BI APIs** → habilitado para o app.

### No sistema

1. Faça login como admin.
2. Cadastre um **Tenant** com: Nome, Tenant ID, Client ID, Client Secret, Workspace ID (opcional).
3. Use **Testar conexão** para validar.
4. Execute **Sincronizar** (ou agende o CRON) para trazer workspaces e relatórios.

---

## API (resumo)

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/api/auth/login` | Login (email, senha) |
| GET | `/api/auth/me` | Usuário logado |
| POST | `/api/auth/logout` | Logout |
| GET | `/api/tenants` | Listar tenants (admin) |
| POST | `/api/tenants` | Criar tenant (admin) |
| POST | `/api/tenants/{id}/test` | Testar conexão (admin) |
| GET | `/api/tenants/{tenantId}/workspaces` | Workspaces do tenant |
| GET | `/api/workspaces/{id}/reports` | Relatórios permitidos do workspace |
| GET | `/api/reports` | Relatórios que o usuário pode ver |
| GET | `/api/reports/{id}/embed` | Config para embed (embedUrl + token) |
| GET/POST | `/api/reports/{reportId}/permissions` | Permissões do relatório (admin) |
| GET/POST/PUT | `/api/users`, `/api/users/{id}` | CRUD usuários (admin) |
| GET/POST/PUT | `/api/groups`, `/api/groups/{id}` | CRUD grupos (admin) |
| POST | `/api/sync/tenant/{tenantId}` | Sincronizar tenant (admin) |
| POST | `/api/sync/all` | Sincronizar todos (admin) |
| GET | `/api/logs` | Logs de acesso (admin) |

O corpo das requisições deve ser JSON quando aplicável. Sessão via cookie (login interno).

---

## Regras de negócio

- **Permissão sempre interna:** mesmo com acesso no Power BI, o usuário só vê o relatório no sistema se houver permissão cadastrada (usuário ou grupo).
- Antes de gerar o embed token, o sistema verifica `PermissionService::canView($userId, $reportId)`.
- Permissões podem ser por **usuário** ou **grupo**; expiração por `data_inicio` / `data_fim`.
- **Pagina_restrita** (JSON): páginas permitidas; **filtro_fixo** (JSON): filtro obrigatório por grupo (pseudo-RLS).

---

## CRON

- **Sincronização:**  
  `*/15 * * * * php /path/to/workbi/cron/sync_workspaces.php`

- **Expiração de permissões:**  
  `0 2 * * * php /path/to/workbi/cron/expire_permissions.php`

Ajuste o caminho do PHP conforme o ambiente (ex.: `C:\xampp_7.3\php\php.exe` no Windows).

---

## Segurança

- Senhas com `password_hash()` (bcrypt).
- Client Secret dos tenants criptografado no banco (`CryptoHelper`).
- Prepared statements (PDO) em todos os acessos ao banco.
- Headers de segurança (X-Content-Type-Options, X-Frame-Options).
- Validação de perfil (admin/gestor) nas rotas sensíveis.

---

## Próximas fases (backlog)

- Fase 2: Permissão por página no embed, dashboard admin, logs detalhados.
- Fase 3: Simulação de usuário, solicitação de acesso, painel de governança.
- Fase 4: Multi-tenant, API própria, white label.

---

## Licença

Uso interno / conforme política da organização.
