# DashBI — Workflow Oficial de Desenvolvimento

Todo desenvolvimento do **DashBI (DBI)** seguira obrigatoriamente o fluxo abaixo.

Fonte oficial de regras: `docs/PROJECT.md`.

## Agentes Cursor (obrigatorio)

Ordem fixa em toda sprint de produto:

1. **Astolfo** (Software Architect) — plano e aceite; sem codigo de produto
2. Especialistas de implementacao (ex.: **Jamilison** Backend, **Genivaldo** Frontend, **Gertrudes** DB) conforme o plano
3. **Kai** (QA) + **James** (Security) — revisao em paralelo; blockers impedem fechamento
4. **Badauí** (Technical Writer) — documentacao em `docs/`

Sob demanda (quando o Architect indicar): Canisso (DevOps), Digão (Integracao), demais do catalogo.

Regra Cursor: `.cursor/rules/workflow-agentes.mdc` (alwaysApply).

### Excecoes

- **Hotfix trivial** ja diagnosticado e documentado: pode ir direto a implementacao minima, com registro no PR/docs e revisao James+Kai antes do fechamento.
- **Sprint so documental:** Astolfo (escopo) -> Badauí (escrita); sem implementacao se nao houver codigo.

---

## Fase 1 — Arquitetura

Responsavel: Software Architect (Astolfo)

- Entender a Sprint
- Analisar requisitos e impacto
- Definir arquitetura (AS-IS -> TO-BE incremental)
- Quebrar em subtarefas
- Produzir plano de implementacao e criterios de aceite

Nenhum codigo de produto antes desta etapa (exceto hotfix trivial acima).

---

## Fase 2 — Implementacao

Responsaveis: especialistas indicados no plano

- Implementar conforme arquitetura aprovada
- Seguir `PROJECT.md` e DoD aplicavel
- PSR-12 / Clean Code no codigo novo (quando PHP)
- Nao alterar arquitetura sem retorno ao Architect

---

## Fase 3 — Seguranca

Responsavel: Security Engineer (James)

- Revisar vulnerabilidades (SQLi, XSS, CSRF, auth, secrets)
- Revisar autenticacao e autorizacao
- Aprovar ou solicitar correcoes (blockers impedem fechamento)

---

## Fase 4 — Qualidade

Responsavel: QA Engineer (Kai)

- Validar caminho feliz + erros principais
- Validar regressao nos modulos impactados
- Aprovar ou solicitar correcoes (blockers impedem fechamento)

---

## Fase 5 — Documentacao

Responsavel: Technical Writer (Badauí)

- Atualizar `docs/`
- Registrar alteracoes de comportamento e arquitetura
- Manter alinhamento com `PROJECT.md`

---

Nenhuma Sprint de produto sera considerada concluida sem passar pelas cinco fases (QA e Security em paralelo apos a implementacao).
