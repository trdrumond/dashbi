
# Checklist – Power BI / Workspace para acesso a workspaces e relatórios

Use esta lista para garantir que o aplicativo (Service Principal) do DashBI 2.0 tenha acesso aos workspaces e relatórios no Power BI.

---

## 1. Azure AD (Portal do Azure)

| # | Item | Onde | Status |
|---|------|------|--------|
| 1.1 | App registrado (Registros de aplicativo) | Azure AD → Registros de aplicativo | ☐ |
| 1.2 | **Client ID** e **Tenant ID** anotados | Visão geral do app | ☐ |
| 1.3 | **Client Secret** criado (Certificados e segredos) | App → Certificados e segredos → Novo segredo do cliente | ☐ |
| 1.4 | **Valor** do secret copiado (não o ID do secret) | Coluna "Valor" ao criar o secret | ☐ |
| 1.5 | Permissões de API adicionadas | App → Permissões de API | ☐ |
| 1.6 | **Microsoft Power BI Service** (ou Power BI) selecionado | Permissões de API → Adicionar permissão | ☐ |
| 1.7 | Fluxo definido como **Service Principal** (sem usuário conectado) | Arquitetura do DashBI | ☐ |
| 1.8 | Se usar `.default`, garantir consentimento para a API selecionada no app | Azure AD → Permissões de API | ☐ |
| 1.9 | **Conceder consentimento de administrador** (quando aplicável) | Botão na página Permissões de API | ☐ |

---

## 2. Power BI – Portal de administração (tenant)

| # | Item | Onde | Status |
|---|------|------|--------|
| 2.1 | Acesso ao **Portal de administração do Power BI** | https://admin.powerbi.com (como administrador) | ☐ |
| 2.2 | **Configurações do locatário** abertas | Menu lateral | ☐ |
| 2.3 | **Configurações de desenvolvedor** localizadas | Na lista de configurações | ☐ |
| 2.4 | **“Permitir que os service principals usem as APIs do Power BI”** | Dentro de Configurações de desenvolvedor | ☐ |
| 2.5 | Opção **ativada** e alterações **salvas** | | ☐ |

Sem este item, a API pode retornar 401/403 mesmo com token e permissões corretas no Azure.

---

## 3. Power BI – Cada workspace (app.powerbi.com)

O aplicativo **só enxerga workspaces em que for membro**. É preciso adicionar o app em cada workspace desejado.

| # | Item | Onde | Status |
|---|------|------|--------|
| 3.1 | Acesso ao **Power BI Service** | https://app.powerbi.com | ☐ |
| 3.2 | **Workspaces** abertos | Menu lateral | ☐ |
| 3.3 | Para **cada workspace** que deve aparecer no DashBI: | | ☐ |
|     | Workspace aberto | Clicar no nome do workspace | ☐ |
|     | **Acesso** aberto | Ícone de pessoas / “Gerenciar acesso” ou “Acesso” | ☐ |
|     | **Adicionar pessoas ou grupos** | Botão na tela de acesso | ☐ |
|     | **Aplicativo** localizado | Procurar pelo **nome do app** (Azure) ou pelo **Client ID** | ☐ |
|     | App adicionado como **Membro** (ou Admin) | Selecionar função e confirmar | ☐ |
| 3.4 | Repetido para **todos** os workspaces que devem ser listados/sincronizados | | ☐ |

Dica: se não aparecer “aplicativo” na busca, use o **Client ID** do app (formato GUID).

---

## 4. DashBI 2.0 – Cadastro do tenant

| # | Item | Onde | Status |
|---|------|------|--------|
| 4.1 | **Tenant (Microsoft)** cadastrado | Menu Tenants no DashBI | ☐ |
| 4.2 | **Tenant ID** = ID do locatário do Azure | Copiar do Azure (visão geral do app ou do diretório) | ☐ |
| 4.3 | **Client ID** = ID do aplicativo do Azure | Copiar do Azure (visão geral do app) | ☐ |
| 4.4 | **Client Secret** = **Valor** do secret (não o ID do secret) | Copiar da coluna “Valor” ao criar o secret no Azure | ☐ |
| 4.5 | **Testar conexão** = OK | Botão no cadastro do tenant | ☐ |
| 4.6 | **Sincronizar** executado | Menu Sincronizar no DashBI | ☐ |

---

## 5. Resumo rápido

| Etapa | O que verificar |
|-------|------------------|
| **Azure** | Token emitido com `aud=https://analysis.windows.net/powerbi/api` usando `.default`; credenciais do app corretas. |
| **Power BI Admin** | **Permitir que os service principals usem as APIs do Power BI** = Ativado. |
| **Cada workspace** | Aplicativo adicionado em **Acesso** como **Membro** (ou Admin). |
| **DashBI** | Tenant cadastrado com Tenant ID, Client ID e **Valor** do Client Secret; Testar conexão OK; Sincronizar. |

---

## 6. Checklist técnico (401 na API)

Use este fluxo quando houver erro `HTTP 401` mesmo com token obtido.

1. **Scope/audience do token**
   - O sistema usa `scope=https://analysis.windows.net/powerbi/api/.default`.
   - O token deve ter `aud=https://analysis.windows.net/powerbi/api`.
   - Se token é emitido e `aud` está correto, OAuth está OK.

2. **Power BI Admin (ponto mais crítico)**
   - `https://admin.powerbi.com` → Configurações do locatário → Configurações de desenvolvedor.
   - Ativar **Permitir que os service principals usem as APIs do Power BI**.
   - Se estiver restrito por grupo/app específico, garantir que **este app** está na lista permitida.
   - Para teste, usar **Toda a organização** temporariamente.

3. **Acesso no workspace**
   - No `app.powerbi.com`, abrir o workspace alvo.
   - Em **Acesso**, adicionar o aplicativo como **Membro** ou **Admin**.

4. **Workspace ID correto**
   - Usar o `groupId` da URL do workspace:
     - `https://app.powerbi.com/groups/{groupId}/...`
   - Não usar `Object ID` do Entra/Azure no lugar do `groupId` do workspace.

5. **Propagação**
   - Após alterações no Azure/Power BI, aguardar 5-10 minutos e testar novamente.

6. **Script de diagnóstico**
   - Executar `php scripts/testar_conexao_sync.php`.
   - Validar especialmente os itens:
     - `2. Obter token OAuth2`
     - `3. Token com claims esperadas`
     - `4. Listar workspaces no Power BI`
     - `5. Listar relatórios do workspace_id informado`

---

## 7. Problemas comuns

| Sintoma | Conferir |
|--------|----------|
| “Invalid client secret” | Usar o **Valor** do secret no Azure (não o ID). Criar novo secret e copiar o Valor. |
| “Conexão OK” mas 0 workspaces | Adicionar o app como **membro de cada workspace** no Power BI (item 3). |
| HTTP 401 ao listar workspaces | Power BI Admin: “Permitir service principals” ativado? App na lista permitida? Workspace ID correto (`groupId`)? |
| HTTP 403 | App no workspace + configuração do tenant (itens 2 e 3). |

---

*Documento do DashBI 2.0 – uso interno.*
