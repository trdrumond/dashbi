# Agentes Cursor — DashBI

Este projeto usa agentes especializados em `.cursor/agents/`.

- Workflow: `docs/DEVELOPMENT_WORKFLOW.md`
- Rules: `.cursor/rules/workflow-agentes.mdc`, `.cursor/rules/agentes-catalogo.mdc`
- DoD: `docs/DEFINITION_OF_DONE.md`
- Fonte: `docs/PROJECT.md`

## Como acionar

No Agent chat, use a ferramenta **Task** com `subagent_type` igual ao `name:` do arquivo do agente (ex.: `Jamilison - backend-engineer`).

## Pipeline minimo

1. `Astolfo - software-architect`
2. Especialistas de implementacao (ex.: `Jamilison - backend-engineer`, `Genivaldo - frontend-engineer`, ...)
3. `Kai - QA Engineer` + `James - security-engineer`
4. `Badauí - technical-writer`

Demais agentes: ver catalogo completo em `.cursor/rules/agentes-catalogo.mdc`.
