# Codex Report — AUTO-003: content pipeline + SEO monitor

Date: 2026-07-20

## Scope

Implemented the merged AUTO-003/AUTO-004 handoff as local Python automation:

- content brief generation through Claude API, with structural and length gates before saving a plan;
- completed-month Google Search Console comparison, optional Notion task creation, and JSON snapshots;
- one-time Google OAuth helper using loopback OAuth + PKCE;
- local PowerShell helpers `bscontent` and `bsseo`.

No production site files, Search Console data, Notion records, or secrets were changed during this implementation.

## Files touched

```
scripts/content_pipeline.py                         — content brief generator + optional Notion task
scripts/seo_monitor.py                              — GSC monitor, snapshot writer, Notion task creation
scripts/gsc_auth.py                                 — one-time OAuth refresh-token helper
scripts/.env.example                                — GSC configuration variable template
.gitignore                                          — ignores client_secret.json
plans/seo-snapshot/.gitkeep                         — tracks the snapshot directory
diagnostics/AUTO-003_content-pipeline-seo-monitor_report_20260720.md
C:\Users\14bez\OneDrive\Документы\PowerShell\Microsoft.PowerShell_profile.ps1
                                                     — local bscontent / bsseo helpers (not a repo file)
```

## Dry-run result

```
content_pipeline.py syntax=ok
seo_monitor.py syntax=ok
gsc_auth.py syntax=ok
content_pipeline.py --help = ok
seo_monitor.py --help = ok
seo_monitor.py --dry-run without GSC config = explicit missing-config error, no network call
content field validation = test-page
position-drop test (5 -> 11) = Medium / 6 positions
bscontent, bsseo = registered PowerShell functions
git diff --check (AUTO-003 files) = clean
offline end-to-end smoke = passed: mocked Claude/GSC/Notion created a plan and one SEO task; repeat run created no duplicate task
```

Live dry-run was intentionally not executed: it requires the owner's GSC OAuth secrets and would read external GSC data. No API key or token was exposed.

## php -l result

Not applicable: AUTO-003 contains no PHP. Python source was parsed with `ast.parse` for all three scripts.

## Idempotency

- Content generator refuses to overwrite an existing same-day `plans/content-YYYY-MM-DD-slug.md`.
- SEO monitor stores URLs for which it has already created a Notion task in that month's snapshot; repeat runs do not recreate those tasks.
- `--dry-run` never creates Notion tasks, but writes/refreshes the local snapshot as specified.

## Rollback

Delete only these AUTO-003 files and remove the two helper functions from the local PowerShell profile:

```
scripts/content_pipeline.py
scripts/seo_monitor.py
scripts/gsc_auth.py
plans/seo-snapshot/
```

No live-service mutation has been made, so no production rollback is needed.

## Run command (owner)

```powershell
cd 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops'
python scripts\gsc_auth.py
# add the printed GSC_* values to .env.review, then:
bscontent "покемон бустери"
bsseo --dry-run
```

## Post-deploy QA checklist

- [ ] Add `ANTHROPIC_API_KEY` and optionally `NOTION_TOKEN` to `.env.review`; run `bscontent "тест"` and read the created plan before publication.
- [ ] Verify generated meta title is <=60 chars and description <=160 chars; fact-check every product claim manually.
- [ ] Place the Desktop OAuth `client_secret.json` in repo root, run `python scripts/gsc_auth.py`, and keep its file uncommitted.
- [ ] Run `bsseo --dry-run`; on first completed-month run confirm it writes a baseline only.
- [ ] Run `bsseo` after review; confirm each detected drop gets one Notion task with the stated priority.

## Side effects / risks

- The monitor compares the last fully completed month with the month before it, rather than partial month-to-date data. This is a deliberate safe interpretation of the handoff because Search Console data normally arrives late; it prevents false alerts.
- Search Console's page dimension is limited to top returned rows, so a high-volume property may need pagination in a later follow-up.
- Notion property names/options must match the existing Roadmap schema; a schema mismatch is reported and leaves the local content/snapshot artifact intact.
- Generated copy remains a review draft and must not be published automatically.
