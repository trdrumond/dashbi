# DashBI — Definition of Done

Uma entrega so e considerada concluida quando os itens **aplicaveis** abaixo forem atendidos.

Fonte oficial: `docs/PROJECT.md`. Este DoD descreve o **DashBI**. Nao exige artefatos de outros produtos Logos.

---

## Sempre obrigatorio

- [ ] Arquitetura da sprint definida pelo Astolfo e alinhada ao `PROJECT.md`
- [ ] Impacto em banco analisado quando houver DDL/indices (Gertrudes sob demanda)
- [ ] Prepared statements com **bind real** (sem interpolar input na string SQL), quando houver SQL
- [ ] Sem `SELECT *` em caminhos de producao alterados/criados nesta entrega (quando aplicavel)
- [ ] Validacao de entrada e escape de saida nos pontos tocados
- [ ] Controle de permissoes no **servidor** — nao so na UI
- [ ] Fluxo funcional validado (caminho feliz + erros principais)
- [ ] Sem regressao nos modulos impactados
- [ ] Revisoes de Seguranca (James) e QA (Kai) concluidas sem blockers
- [ ] Documentacao atualizada em `docs/`
- [ ] Nenhum segredo novo versionado (credenciais, API keys, dumps com PII)

## Quando a entrega criar codigo em camadas (MVC / Service / Repository)

- [ ] Acesso a dados via Repository (ou padrao equivalente do projeto)
- [ ] Regras de negocio em Service (nao em script de view)
- [ ] Contratos de dados explicitos nas fronteiras novas
- [ ] PSR-12 / estrutura alinhada ao padrao aprovado pelo Architect

## Metricas / scores

- [ ] Scores de conformidade so sobem com evidencia (grep, teste, metrica **deste** repo) — nunca sob pedido

---

## Nao fazer

- Nao versionar credenciais reais, dumps com PII ou tokens
- Nao exigir pastas/scripts de outros produtos Logos neste repo
- Nao alterar arquitetura sem justificativa registrada e aceite do Architect

---

## Referencias

- `docs/PROJECT.md`
- `docs/DEVELOPMENT_WORKFLOW.md`
- `.cursor/rules/workflow-agentes.mdc`
- `.cursor/rules/agentes-catalogo.mdc`
