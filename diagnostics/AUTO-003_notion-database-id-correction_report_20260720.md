# Codex Report — AUTO-003: Notion database ID correction

Date: 2026-07-20

## Scope

Correct the local Notion database target for AUTO-002/AUTO-003 after a read-only API check showed that the legacy ID was no longer resolvable and the Roadmap URL ID was accessible with the owner's personal access token.

## Files touched

```text
auto_review.py                          — AUTO-002 root runner database target
scripts/auto_review.py                  — AUTO-002 scripts runner target and .env.review loader
scripts/content_pipeline.py             — AUTO-003 content task target
scripts/seo_monitor.py                  — AUTO-003 SEO task target
scripts/.env.example                    — PAT-first local-secret guidance
CLAUDE.md                               — Notion database reference
ROADMAP_SOP.md                          — canonical database reference
context-index.md                        — index database reference
handoffs/AUTO-003-notion-token-setup.md — corrected obsolete Private-move guidance
```

## Read-only verification

```text
GET /v1/databases/5aef22c3-048d-4dde-a5b1-ad409de9301c -> 404 object_not_found
GET /v1/databases/35c3f857-2fc5-4a78-96c8-af0efd4cf8d4 -> 35c3f857-2fc5-4a78-96c8-af0efd4cf8d4
```

## Syntax and smoke status

- Python syntax check: pending after the source update.
- Notion API smoke: passed by owner with a personal access token; no write occurred.
- `bscontent`: intentionally not run because `ANTHROPIC_API_KEY` is not configured.
- `bsseo --dry-run`: intentionally not run because GSC OAuth credentials are not configured.

## Idempotency

Source-only configuration correction. Reapplying produces the same constants and documentation.

## Rollback

Restore the prior local constants only if the owner re-runs the read-only API request and the new ID no longer resolves. No Notion data or secrets were written.

## Post-change QA checklist

- [x] Personal access token reads the live Roadmap database ID.
- [ ] `python -m py_compile auto_review.py scripts/auto_review.py scripts/content_pipeline.py scripts/seo_monitor.py`
- [ ] Add `ANTHROPIC_API_KEY` before the `bscontent` smoke test.
- [ ] Configure GSC OAuth before the `bsseo --dry-run` smoke test.

## Side effects and risks

No production or Notion writes. The full AUTO-003 smoke remains blocked solely on independent Anthropic and GSC credentials.
