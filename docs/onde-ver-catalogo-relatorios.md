# Catálogo de Relatórios

## O que a sincronização atualiza (Power BI)

Na **sincronização** (Admin → Sincronizar), o sistema atualiza para cada relatório:

- **Descrição**: Copiada da descrição do relatório no Power BI (quando existir).
- **Última atualização**: Data do último refresh do dataset (API de refresh do Power BI). Requer permissão `Dataset.Read.All`.
- **Dono**: Preenchido quando a **Admin API** do Power BI está disponível (`modifiedBy` ou `createdBy`), e o e-mail corresponde a um usuário cadastrado no sistema. Caso a Admin API retorne 403, a sincronização usa a API normal e o dono não é alterado.

Esses campos aparecem no **Editar relatório** como **somente leitura** (atualizados apenas na sincronização). No Editar você pode alterar apenas **Nome** e **Imagem**.

---

## 1. Onde ver as informações (Admin – Editar relatório)

1. Faça login e abra **Configurações** (ou acesse `public/admin.html`).
2. No menu lateral, clique em **Relatórios e permissões**.
3. Selecione **Tenant** → **Workspace** → **Relatório**.
4. Clique em **Editar relatório**.
5. No modal você verá **Descrição**, **Dono** e **Última atualização** como campos desabilitados (informação vinda da sincronização). Editáveis: **Nome** e **Imagem**.

---

## 2. Onde ver as informações (Dashboard)

1. No admin, clique em **Ver relatórios** ou acesse `public/dashboard.php`.
2. Na lista "Meus relatórios", cada **card** pode mostrar:
   - Descrição (abaixo do nome)
   - Dono e data da última atualização (linha em cinza)

Essas informações são preenchidas na **sincronização** com o Power BI.

---

## 3. Migration do banco (obrigatório uma vez)

As colunas do catálogo precisam existir no banco. Execute **uma vez**:

```bash
mysql -u USUARIO -p bi_portal < database/migrations/006_report_catalog.sql
```

Ou no phpMyAdmin: abra o banco `bi_portal` e execute o conteúdo do arquivo `database/migrations/006_report_catalog.sql`.

Sem essa migration, os novos campos não existem e o "Editar relatório" pode não mostrar os campos ou a API pode falhar.
