# Codex Report — NCRM-19: bsreview dashboard sync

Date: 2026-07-27 (amended 2026-07-29)

## Scope

Extended the canonical `scripts/auto_review.py` only. In normal mode, its one
existing Notion roadmap query now returns the page object used for both the
existing comment post and a subsequent one-task dashboard sync. No Notion
property or status write was added.

The sync maps Notion `Status` to dashboard `status`, copies the supported
priority vocabulary, and copies `Last Updated` only when it matches
`YYYY-MM-DD`. It does not change task creation, deletion, `simple`, `why`, or
`warn`.

## Files touched

```
scripts/auto_review.py                                      — implementation
diagnostics/NCRM-19_bsreview_dashboard_sync_report_20260727.md — this report
```

## Local validation

```
python -m py_compile scripts\auto_review.py
NCRM-19 isolated sync tests: PASS
git diff --check
```

The isolated test uses a temporary copy of the one canonical dashboard and
synthetic Notion cards. It verifies a three-field update, absent-ID no-write
behavior, narrative `Last Updated` rejection, AUTO-series exclusion, and
`--dry-run` making no Notion lookup or file write.

## Runtime behavior

- The only target is `dashboard/booster-dashboard.html` in this repository.
- Zero or multiple matching `id: 'TASK-ID'` blocks, invalid status/priority, or
  a malformed target block result in a warning and no dashboard write.
- A no-change sync does not rewrite the file.

## Not performed

No live `bsreview` run, Claude API call, Notion request, dashboard write, or
Notion mutation was performed during this implementation. Existing dashboard
worktree changes were preserved. The former standalone dashboard is retired;
this implementation now uses only the canonical repository file.

## Rollback

Revert `scripts/auto_review.py` to disable the feature. Restore a bad
dashboard write from git.

## Owner QA checklist

- [ ] Run `bsreview NCRM-11` with real configured credentials.
- [ ] Confirm the terminal shows the comment result followed by
  `[dashboard-sync]` old-to-new fields or `no change`.
- [ ] Open `dashboard/booster-dashboard.html` and confirm the page renders
  without console errors.
- [ ] Review `git diff -- dashboard/booster-dashboard.html` and confirm only
  NCRM-11's `status`, `priority`, and/or `lastUpdated` changed.
- [ ] Run an absent dashboard ID and `bsreview --dry-run`; confirm warnings and
  no dashboard mtime changes respectively.

## Risk

Low, with dashboard parsing guarded by exact ID and field-count checks. The
remaining proof is owner-run integration QA against the real Notion card and
canonical dashboard file.
