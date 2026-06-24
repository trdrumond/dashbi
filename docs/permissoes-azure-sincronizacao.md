# Permissões Azure para sincronização preencher descrição, dono e data

Para a **sincronização** preencher **descrição**, **dono** e **última atualização** dos relatórios, o aplicativo (registro no Azure) precisa das permissões abaixo.

## 1. Descrição do relatório

- **Report.Read.All** ou **Report.ReadWrite.All** (já usado para listar e embed).
- A descrição é obtida da lista de relatórios ou, quando a lista não a traz, por uma chamada **Get Report** por relatório. Nenhuma permissão extra é necessária.

## 2. Última atualização (refresh do dataset)

- **Dataset.Read.All** ou **Dataset.ReadWrite.All** (permissão de aplicativo).
- No **Azure Portal** → seu app → **Permissões de API** → **Adicionar uma permissão** → **APIs do Power BI** → **Permissões de aplicativo** → marque **Dataset.Read.All** (ou **Dataset.ReadWrite.All**).
- Depois, em **Conceder consentimento de administrador** para o tenant.
- Sem essa permissão, a API de histórico de refresh retorna 403 e o campo “Última atualização” permanece em branco.

## 3. Dono do relatório

- A **Admin API** do Power BI retorna `modifiedBy` / `createdBy` (e-mail do dono).
- Com **Service Principal**: em muitos tenants a Admin API (`/admin/groups/.../reports`) pode retornar **403** se o app não tiver permissão de administrador. Nesse caso a sincronização usa a API normal e **não** consegue preencher o dono (o campo fica em branco ou não é alterado).
- Para o dono ser preenchido, o app precisa ter acesso à Admin API (por exemplo **Tenant.Read.All** em cenários delegados, ou o tenant configurado para permitir que service principals usem as APIs do Power BI conforme a documentação Microsoft).

## Resumo

| Campo                | O que fazer |
|----------------------|-------------|
| **Descrição**        | Deve passar a preencher após a alteração (incluindo chamada Get Report quando a lista não traz description). |
| **Última atualização** | Adicionar permissão **Dataset.Read.All** (ou **Dataset.ReadWrite.All**) no app no Azure e conceder consentimento de administrador. |
| **Dono**             | Depende da Admin API; se retornar 403, o dono não será preenchido pela sincronização. |

Depois de alterar permissões no Azure, aguarde alguns minutos e rode a **Sincronização** novamente no admin.
