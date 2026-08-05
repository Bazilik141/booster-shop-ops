# Repo skills — executor-side only

These are **mirrors** of the owner's Cowork skills, placed here so **Claude Code**
can use them while working inside the repository. Canonical source is Cowork
(owner decision, 2026-08-05).

## Present here — the executor needs these

| Skill | Why the executor needs it |
|---|---|
| `bs-seo-risk-gate` | classify risk before touching protected assets |
| `bs-checkout-smoke` | mandatory after any change to shared header/footer or checkout |
| `bs-merchant-schema-qa` | defensive — if work comes near schema or the Merchant feed |
| `bs-crm-plan` | CRM / Apps Script / dashboard work (NCRM track) |

## Deliberately ABSENT — do not add them

| Skill | Why it must stay out |
|---|---|
| `bs-roadmap-triage` | reads and writes the Notion roadmap. Claude Code must never write Notion — that writer is Claude (chat). Adding this skill here contradicts `AGENTS.md` and `CLAUDE.md`. |
| `bs-codex-handoff` | handoff authoring belongs to Claude (chat). The executor runs a handoff; it does not write one. |
| `bs-content-brief` | content and SEO briefs belong to Claude (chat). |

If a future agent thinks one of the absent skills would be "helpful here" — it is
not an oversight. Leave them out, or ask the owner.

## Sync rules

1. Edit the **Cowork** skill first. That is the canonical copy.
2. Re-copy the file here and update the `Synced` date in the provenance header.
3. Commit the change so both agents move together.

Editing a file here without updating Cowork creates a silent fork: Claude (chat)
and Claude Code would then be following different rules for the same task. That is
the same failure mode as two competing handoffs.
