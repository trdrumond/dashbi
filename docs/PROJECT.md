# PROJECT.md

Versao: 1.0.0

Ultima atualizacao:
12/08/2026

Responsavel:
Thiago Drumond

Status: **fonte oficial de governanca** do DashBI (DBI).

---

## Visao Geral

### Nome do Projeto

DashBI (DBI)

### Objetivo

Governanca, controle de acesso e distribuicao de relatorios Power BI (integracao Microsoft Workspace).

Stack alvo: PHP 7.3, MySQL 5.7+, camadas Controller -> Service -> Repository.

### Valor de negocio

Entregar valor operacional Logos com seguranca, rastreabilidade e alinhamento a linha de desenvolvimento Cursor (agentes + rules).

---

## Objetivos Tecnicos

- Alto desempenho
- Alta disponibilidade das rotinas criticas
- Codigo limpo e manutenivel
- Escalabilidade operacional
- Seguranca (auth, permissoes, dados sensiveis)
- Documentacao completa e alinhada ao codigo
- Evolucao incremental AS-IS -> TO-BE sem big-bang

---

## Stack Tecnologica

### Backend

- PHP 7.3
- PHP 7.3, MySQL 5.7+, camadas Controller -> Service -> Repository

### Banco de Dados

- MySQL 5.7+ (schema em database/)

### Servidor

- Producao: servidor Logos/Amanda conforme ambiente
- Desenvolvimento: XAMPP (Windows) permitido
- Extensoes tipicas: PDO MySQL, OpenSSL, mbstring, json (e demais exigidas pelo projeto)

### Frontend

- HTML5 / CSS3 / JavaScript (Bootstrap ou stack ja presente no repositorio)

### Versionamento

- Git (proibido versionar segredos)

---

## Governanca Cursor

- Agentes: `.cursor/agents/`
- Workflow: `.cursor/rules/workflow-agentes.mdc`
- Catalogo: `.cursor/rules/agentes-catalogo.mdc`
- DoD: `docs/DEFINITION_OF_DONE.md`
- Workflow docs: `docs/DEVELOPMENT_WORKFLOW.md`

## Regras de isolamento

- Este repositorio e exclusivamente **DashBI**.
- Nao misturar artefatos, namespaces ou regras de outros sistemas Logos (DEM, MIA, Portal, Celere, etc.), salvo integracoes explicitas documentadas.
