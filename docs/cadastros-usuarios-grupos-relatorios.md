# DashBI 2.0 – Cadastro de usuários, grupos e configuração de relatórios

## Visão geral do fluxo

```
1. Tenant (Microsoft)  →  2. Sincronizar  →  3. Relatórios no sistema
        ↓                        ↓
4. Usuários e Grupos  →  5. Permissões por relatório  →  Quem vê o quê
```

- **Tenant:** conexão com Azure (Client ID, Secret, Workspace ID). Sem tenant não há relatórios.
- **Sincronizar:** busca workspaces e relatórios do Power BI e grava no banco.
- **Usuários:** quem faz login no DashBI 2.0 (nome, e-mail, senha, perfil).
- **Grupos:** agrupam usuários (ex.: “Financeiro”, “Comercial”). Um usuário pode estar em vários grupos.
- **Permissões:** por relatório você define **quem** (usuário ou grupo) pode **visualizar**, **exportar**, **compartilhar**, e opcionalmente **quais páginas** e **filtro fixo**; pode ter **data início/fim**.

---

## 1. Usuários

### Quem pode cadastrar
Apenas quem tem perfil **admin** ou **master** (logado na API com sessão de administrador).

### Campos
| Campo      | Obrigatório | Descrição |
|-----------|-------------|-----------|
| nome      | Sim         | Nome completo |
| email     | Sim         | E-mail único (login) |
| senha     | Sim (na criação) | Senha em texto; o sistema grava só o hash |
| perfil    | Não         | `usuario`, `supervisor`, `gestor`, `admin`, `master` (padrão: `usuario`) |
| ativo     | Não         | 1 = ativo, 0 = inativo (padrão: 1) |

### API

**Criar usuário (POST)**  
`POST /api/users`  
Body (JSON):
```json
{
  "nome": "Maria Silva",
  "email": "maria@empresa.com",
  "senha": "senha123",
  "perfil": "usuario",
  "ativo": 1
}
```

**Atualizar (PUT)**  
`PUT /api/users/{id}`  
Body pode incluir: `nome`, `email`, `senha` (opcional), `perfil`, `ativo`.  
Se enviar `senha`, ela será trocada.

**Listar**  
`GET /api/users`  
Retorna lista (sem senha).

**Definir grupos do usuário**  
`POST /api/users/{id}/groups`  
Body:
```json
{
  "group_ids": [1, 2, 3]
}
```
Substitui a lista de grupos do usuário pelos IDs enviados.

---

## 2. Grupos

### Quem pode cadastrar
Admin ou master.

### Campos
| Campo     | Obrigatório | Descrição |
|----------|-------------|-----------|
| nome     | Sim         | Nome do grupo (ex.: "Financeiro") |
| descricao| Não         | Descrição livre |

Grupos **não** têm usuários “dentro” no cadastro do grupo. A relação é feita no **usuário**: em “Definir grupos do usuário” você informa em quais grupos ele entra.

### API

**Criar grupo (POST)**  
`POST /api/groups`  
Body:
```json
{
  "nome": "Financeiro",
  "descricao": "Equipe de finanças"
}
```

**Atualizar**  
`PUT /api/groups/{id}`  
Body: `nome`, `descricao`.

**Listar**  
`GET /api/groups`

Para **colocar um usuário em um grupo**, use `POST /api/users/{id}/groups` com `group_ids` (veja acima).

---

## 3. Configuração de relatórios (permissões)

Relatórios aparecem no sistema depois de:
1. Cadastrar um **Tenant** (conexão Microsoft).
2. **Sincronizar** (menu “Sincronizar” na admin ou `POST /api/sync/tenant/{tenantId}` ou `POST /api/sync/all`).

A “configuração” de relatórios é **só permissão**: quem pode ver cada relatório (e com que regras).

### Regra importante
O usuário **só vê** um relatório se existir uma **permissão** para ele (direta por usuário) **ou** para algum **grupo** do qual ele faça parte. Sem permissão, o relatório não aparece na lista e o embed retorna “Acesso negado”.

### Campos da permissão
| Campo           | Descrição |
|-----------------|-----------|
| report_id       | ID do relatório (obrigatório) |
| user_id **ou** group_id | Um dos dois obrigatório. Permissão é **por usuário** ou **por grupo**. |
| pode_visualizar | 1 = pode ver (padrão 1) |
| pode_exportar   | 1 = pode exportar (padrão 0) |
| pode_compartilhar | 1 = pode compartilhar (padrão 0) |
| pagina_restrita | JSON com nomes das páginas permitidas. Ex: `["Page1","Page3"]`. Vazio/null = todas. |
| filtro_fixo     | JSON com filtro obrigatório (ex. para RLS por grupo). Ex: `{"UF":["CE","MA"]}`. |
| data_inicio     | Opcional. Só vale a partir desta data. |
| data_fim        | Opcional. Só vale até esta data (depois o CRON pode remover). |

### API

**Listar permissões de um relatório**  
`GET /api/reports/{reportId}/permissions`  
Retorna quem (usuário ou grupo) tem permissão naquele relatório.

**Criar permissão (dar acesso)**  
`POST /api/reports/{reportId}/permissions`  
Body – **por usuário:**
```json
{
  "user_id": 2,
  "pode_visualizar": 1,
  "pode_exportar": 0,
  "pode_compartilhar": 0,
  "data_inicio": null,
  "data_fim": "2025-12-31"
}
```
Body – **por grupo:**
```json
{
  "group_id": 1,
  "pode_visualizar": 1,
  "pode_exportar": 1,
  "pagina_restrita": ["Page1", "Page2"],
  "filtro_fixo": {"Região": ["Norte"]}
}
```
Não envie `user_id` e `group_id` ao mesmo tempo.

**Atualizar permissão**  
`PUT /api/permissions/{id}`  
Body: quais campos quiser alterar (ex.: `pode_exportar`, `data_fim`, `pagina_restrita`).

**Remover permissão**  
`DELETE /api/permissions/{id}`

---

## Ordem prática recomendada

1. **Tenant** – Cadastrar conexão Microsoft (pela API ou tela futura).
2. **Sincronizar** – Trazer workspaces e relatórios.
3. **Grupos** – Criar grupos (ex.: Financeiro, Comercial).
4. **Usuários** – Criar usuários e, em seguida, definir `group_ids` de cada um (`POST /api/users/{id}/groups`).
5. **Permissões** – Para cada relatório que deve aparecer para alguém, criar permissão por **user_id** ou **group_id** (e opcionalmente páginas, filtro, datas).

Assim o cadastro de usuários, grupos e configuração de relatórios (permissões) fica consistente e rastreável.
