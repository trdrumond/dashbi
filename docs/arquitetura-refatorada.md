# Arquitetura refatorada - DashBI (Power BI Embed + RLS)

## Fluxo geral

```
Usuário → Login (Portal PHP) → Backend valida permissões (RBAC)
    → Backend gera Access Token (Azure AD, client_credentials)
    → Backend gera Embed Token (com EffectiveIdentity se RLS)
    → Frontend renderiza relatório (Power BI JS SDK)
```

- **Autenticação**: local (sessão PHP). Não se autentica usuário final no Power BI.
- **Service Principal**: App Registration no Azure; a aplicação gera o embed token.
- **Permissões**: tabelas `users`, `reports`, `permissions`; antes de gerar embed token o backend valida `canView(userId, reportId)`.

## Estrutura principal

- **config/azure.php** – Configuração Azure AD / Power BI (scope, RLS role default, cache).
- **app/services/AzureAuthService.php** – OAuth2 client_credentials (obtém access token).
- **app/services/TokenService.php** – Cache em arquivo do access token; usa AzureAuthService.
- **app/services/PowerBIService.php** – API Power BI: workspaces, reports, **getEmbedToken** (com opção de RLS via `identities`).
- **app/modules/reports/ReportController.php** – Lista relatórios permitidos e gera embed (valida permissão, passa EffectiveIdentity quando há dataset_id + rls_role).
- **public/dashboard.php** – Página de visualização: lista relatórios e embute com Power BI JS SDK.

## Banco de dados

- **users**: `empresa_id` (opcional) – usado como `username` no EffectiveIdentity quando houver RLS (multi-tenant ou por usuário).
- **reports**: `dataset_id`, `rls_role` – para RLS, informe o dataset e o nome da role criada no Power BI.
- **Permissões**: `permissions` (por usuário ou grupo); validar sempre antes de gerar embed.

Migração em bancos existentes:

```bash
mysql -u usuario -p bi_portal < database/migrations/001_rls_empresa.sql
```

## RLS (Row Level Security)

1. No Power BI Desktop: Modelagem → Gerenciar funções → criar role (ex.: `RLS_ROLE`) com DAX, por exemplo:
   - `[representante_email] = USERPRINCIPALNAME()` ou
   - `[empresa_id] = USERPRINCIPALNAME()` (ou `VALUE(CUSTOMDATA())` para empresa_id numérico).
2. No portal: em **Relatórios** (admin), preencher **dataset_id** e **rls_role** no relatório; em **Usuários**, preencher **Empresa ID (RLS)** quando for filtro por empresa.
3. No embed, o backend envia no GenerateToken:
   - `identities`: `username` = email do usuário ou `empresa_id`, `roles` = [rls_role], `datasets` = [dataset_id].

## Segurança

- Nunca confiar no frontend para permissões; sempre validar no backend.
- Embed token sempre sob demanda (não cachear no cliente).
- Cache de access token apenas no servidor (TokenService).
- HTTPS obrigatório em produção.

## Endpoints principais

- `POST /api/auth/login` – Login (email/senha).
- `GET /api/auth/me` – Usuário logado.
- `GET /api/reports` – Relatórios que o usuário pode ver.
- `GET /api/reports/{id}/embed` – Config para embed (embedUrl, accessToken, reportId); exige permissão e gera token com RLS quando configurado.

## Frontend

- **dashboard.php**: lista relatórios via `GET /api/reports`, ao clicar chama `GET /api/reports/{id}/embed` e usa `powerbi.embed()` com `tokenType: Embed`, `accessToken`, `embedUrl`, `id`.
- **admin.html**: gestão de tenants, usuários, grupos, relatórios e permissões; link “Ver relatórios” para dashboard.
