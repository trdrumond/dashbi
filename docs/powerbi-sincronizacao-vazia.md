# Sincronização não traz workspaces/relatórios (conexão OK)

Se o **Testar conexão** aparece **OK** mas ao **Sincronizar** não aparece nenhum workspace nem relatório, a causa quase sempre é uma destas:

---

## 1. Service Principal não está nos workspaces (mais comum)

A API do Power BI **só lista workspaces em que o aplicativo (Service Principal) é membro**. Ter token (conexão OK) não basta: o app precisa estar **dentro de cada workspace**.

### O que fazer

1. Abra o **Power BI Service** (app.powerbi.com) com um usuário admin.
2. Vá em **Workspaces** → abra o workspace desejado.
3. Clique em **Acesso** (ou no ícone de pessoas).
4. **Adicionar pessoas ou grupos** → procure pelo **nome do aplicativo** que você registrou no Azure (ou pelo **Application (client) ID**).
5. Atribua pelo menos **Membro** (ou Admin).
6. Repita para **cada workspace** que deve aparecer no DashBI 2.0.

Depois disso, execute **Sincronizar** de novo no sistema.

---

## 2. Configuração do locatário no Power BI

O tenant do Power BI precisa permitir que **service principals** usem as APIs:

1. Abra o **Portal de administração do Power BI** (admin.powerbi.com) como administrador.
2. **Configurações do locatário**.
3. Localize **Configurações de desenvolvedor** → **Permitir que os service principals usem as APIs do Power BI**.
4. Ative a opção e salve.

Se isso estiver desativado, a conexão pode até obter token, mas as chamadas de API (listar workspaces/relatórios) podem falhar ou retornar vazio.

---

## 3. Permissões da aplicação no Azure AD

No **Azure Portal** → **Azure Active Directory** → **Registros de aplicativo** → seu app → **Permissões de API**:

- **Microsoft Power BI Service** (ou Power BI):
  - **Workspace.Read.All** (ou Workspace.ReadWrite.All) – para listar workspaces.
  - **Report.Read.All** – para listar relatórios.
- Clique em **Conceder consentimento de administrador** para o tenant.

---

## Resumo rápido

| Onde              | O que verificar |
|-------------------|------------------|
| Power BI – Workspace | App adicionado em **Acesso** como Membro (ou Admin) em cada workspace que deve sincronizar. |
| Power BI – Admin  | **Permitir que os service principals usem as APIs do Power BI** = Ativado. |
| Azure AD – App    | Permissões **Workspace.Read.All** e **Report.Read.All** + consentimento de administrador. |

Depois de ajustar, use **Sincronizar** novamente. Se ainda der erro, a mensagem exibida na tela (ou no retorno da API) deve indicar o motivo (por exemplo, acesso negado 403).
