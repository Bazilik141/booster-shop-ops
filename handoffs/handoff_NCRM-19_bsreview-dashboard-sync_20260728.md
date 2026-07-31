# Codex Handoff — NCRM-19: auto-sync dashboard status from Notion inside `bsreview`

Date: 2026-07-28 | Owner: Raccoon | Planner: Claude | Executor: Codex
Related: `ROADMAP_SOP.md` §0 (2026-07-27 amendment — Claude now co-authorized to write `dashboard/booster-dashboard.html`, alongside Codex), §4a (`bsreview`/AUTO-002)

> LOW-RISK in scope (one additional, narrow, deterministic write step appended to an existing script), but the target file (`dashboard/booster-dashboard.html`) is a large hand-maintained JS array the owner reads directly — a malformed write breaks the visual dashboard for every task, not just the one being synced. Treat file-parsing correctness as the main risk, not data sensitivity.

## 1. Task ID
NCRM-19 — extend `scripts/auto_review.py` (`bsreview`, AUTO-002) so that after its existing review/comment step, it also syncs the *structured* fields (status, priority, last-updated date) of the one task it just reviewed from Notion into the dashboard mirror — automatically, triggered by the same `bsreview` run the owner/Codex already does after every Codex session. No new schedule, no new git hook.

## 2. Context
Owner is tired of manually reminding agents to keep `dashboard/booster-dashboard.html` in sync with Notion (canonical). This session's audit found concrete drift that had gone unnoticed (NCRM-07b triplicated in Notion, NCRM-11's title stale after a scope split, NCRM-17/NCRM-10 dashboard notes out of date until manually reconciled). `ROADMAP_SOP.md` §0 was amended 2026-07-27 to let Claude write the dashboard mirror directly (previously Codex-only), which unblocked manual reconciliation this session — but manual reconciliation still requires someone to notice and ask. Owner wants a trigger tied to something already done routinely, not a calendar schedule (explicitly rejected weekly automation).

`scripts/auto_review.py` already: runs after each Codex session, reads `NOTION_TOKEN` from `.env.review`, finds the diagnostic/handoff for a `TASK-ID`, calls the Claude API for review, saves a diagnostic, and (per `ROADMAP_SOP.md` §4a) "may query the roadmap by exact `Roadmap ID` through the Notion REST API and post one comment." It already has Notion read access and already resolves the exact `TASK-ID` being reviewed — the missing piece is using that same lookup to update the dashboard mirror instead of, or in addition to, only posting a comment.

This does **not** change the standing rule that `bsreview` must never write a Notion property or status (`ROADMAP_SOP.md` §4a: "No mode may update a Notion property or status") — this task is Notion → dashboard only, one direction, and only the dashboard write is new.

## 3. Goal
1. After a normal (non-`--dry-run`) `bsreview TASK-ID` run completes its existing review + diagnostic-save + Notion-comment step, the script additionally: fetches the current Notion card for that exact `Roadmap ID`, and if its `Status`, `Priority`, or `Last Updated` differ from what's in the dashboard entry for that same `id`, rewrites just those fields in place.
2. `bsreview --dry-run` behavior is unchanged: no dashboard write, no Notion read, exactly as today.
3. The sync is narrow and mechanical — it never touches `simple`/`why`/`warn` (prose fields that require human/Claude judgment to write well) and never adds or removes a task entry. If the `Roadmap ID` has no matching `id: 'TASK-ID'` block in the dashboard, log a warning and skip — do not invent one.

## 4. What to change

**Target file precedence** (per `ROADMAP_SOP.md` §8 — this script runs locally on the owner's machine, so unlike Claude it can reach both paths):
1. Write `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html` (active dashboard) first.
2. Copy the same result to `dashboard/booster-dashboard.html` (repo mirror) — matches the documented "active → repo mirror" direction, reversing back to the original flow now that a local script does it instead of Claude alone.
3. If the active dashboard path doesn't exist on the machine running the script (e.g. CI, or a machine without that exact folder layout), write only the repo mirror and log a clear warning instead of failing.

**In `scripts/auto_review.py`**:
- Add a function, e.g. `sync_dashboard_status(task_id: str, notion_card: dict) -> None`, called only in the normal (non-dry-run) path, only when `NOTION_TOKEN` is available (same gate as the existing comment-post step) and only after the existing Notion fetch for that `Roadmap ID` (reuse the fetch, don't add a second API call).
- Map fields using the existing vocabulary in `ROADMAP_SOP.md` §2 exactly: Notion `Status` "Not started"/"In progress"/"Done" → dashboard `status` `'todo'`/`'active'`/`'done'`. Do not invent a fourth state.
- `Priority`: Notion's `High`/`Medium`/`Low` maps 1:1 to the dashboard `priority` string — no translation needed (confirm against a few live entries before assuming).
- `Last Updated`: Notion's property is free-text, not a real date field — for ST/NCRM/PAY/MKT-TG entries it's consistently `YYYY-MM-DD` in practice, but do not assume: validate with a regex before writing; if it doesn't match `YYYY-MM-DD`, skip updating `lastUpdated` for that run and log why (this property is known to sometimes hold narrative text on unrelated, non-`ROADMAP_SOP`-governed series — see §5).
- Locate the target object by exact `id: 'TASK-ID'` (single-quoted, matches existing dashboard file convention — confirm exact quoting/spacing against the real file, don't assume from this handoff). If zero or more than one match is found, log a warning and make no change — do not guess which one is canonical.
- Log every sync action (task id, old → new values, which file(s) written) to stdout so it's visible in the same terminal output as the review itself — no separate log file needed unless Codex finds one already exists for `bsreview` runs.

## 5. What NOT to touch
- Do not extend this to the `AUTO-*` series (pricing/competitor/SEO/Telegram track) — confirmed this session to be a separate, `ROADMAP_SOP.md`-ungoverned track with its own ID collisions (two colliding batches per AUTO-001 through AUTO-006). `bsreview` only ever resolves a `Roadmap ID` from a diagnostic/handoff filename in this repo, which naturally excludes `AUTO-*` (no diagnostics/handoffs exist for that track here) — but do not add any code path that would let it match one.
- Do not touch `simple`, `why`, or `warn` fields in the dashboard — those stay manually written (by Claude or Codex, per existing practice) and are out of scope for automated sync.
- Do not add or remove a task's `id:` block — new-task creation stays a manual step (`ROADMAP_SOP.md` §3, stage 1 "Create").
- Do not change anything about the existing Notion-comment-posting behavior, `--dry-run` semantics, or diagnostic-saving logic.
- Do not give `bsreview` any Notion *write* capability — this task is strictly a local-file write driven by a Notion *read*.
- Standard protected zones (not touched by this task, listed for completeness per handoff convention): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, storefront schema.org markup — n/a, this task is entirely inside `scripts/auto_review.py` and the two dashboard file paths.

## 6. Likely files / areas
- `scripts/auto_review.py` (extend).
- `dashboard/booster-dashboard.html` (repo mirror — written by the script, not hand-edited by Codex as part of this task).
- `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html` (active dashboard, outside the repo — written by the script when present on the running machine).
- Codex should verify the exact `ROADMAP_FLOW` array structure/quoting in the current dashboard file before writing a parser/regex — do not assume the shape described here is exact.

## 7. Acceptance criteria
- [ ] Running `bsreview NCRM-11` (or any task with an existing diagnostic) after this change performs the existing review + comment, then logs a dashboard-sync line showing old vs. new `status`/`priority`/`lastUpdated` (or "no change" if already in sync).
- [ ] The dashboard entry for that one task, and only that task, changes in `git diff` — every other task's entry is byte-identical.
- [ ] `bsreview --dry-run` produces zero changes to either dashboard file and makes zero Notion requests, same as before this change.
- [ ] A task whose `Roadmap ID` has no matching `id:` block in the dashboard logs a clear warning and makes no file change (test with a made-up ID or a known-absent one).
- [ ] A Notion "Last Updated" value that isn't `YYYY-MM-DD` does not corrupt the dashboard's `lastUpdated` field — confirm by testing against an `AUTO-*`-style narrative value if convenient, or a synthetic one.
- [ ] After a sync run, `dashboard/booster-dashboard.html` still parses as valid JS (e.g. load it in a browser or run it through a JS parser) — no trailing comma, quote-escaping, or bracket-matching breakage.
- [ ] `git diff` for this task touches only `scripts/auto_review.py` and (as a run-time side effect, not a source change) `dashboard/booster-dashboard.html`.

## 8. QA / smoke test (owner)
- [ ] Run `bsreview NCRM-11` (a task with a real, current diagnostic) and confirm the console log accurately describes what changed.
- [ ] Open both dashboard files (active + repo mirror) in a browser afterward and confirm the page still renders normally — no blank page, no console error.
- [ ] Confirm `git diff -- dashboard/booster-dashboard.html` shows only the expected task's fields changed, nothing else moved or reformatted.
- [ ] Deliberately run `bsreview` on a task ID that doesn't exist in the dashboard and confirm it warns instead of crashing or writing garbage.
- [ ] Run `bsreview --dry-run` once and confirm neither dashboard file's mtime changes.

## 9. Rollback note
- Pure script change — revert `scripts/auto_review.py` to the prior version to fully disable the sync step; no schema, no Notion, no server-side state involved.
- If a bad sync run corrupts a dashboard file, `git checkout -- dashboard/booster-dashboard.html` restores the repo mirror from the last commit; the active dashboard outside git has no automatic backup — Codex should have the script write a `.bak` copy of the active dashboard before each overwrite (e.g. `booster-dashboard.html.bak`) as cheap insurance, gitignored, not part of the diff.

## 10. Recommended status after execution
NCRM-19 → stays `In progress` until owner QA (§8) passes on at least one real `bsreview` run against a live task; only then move toward `Done`. This is one piece of NCRM-19, not the whole task — the AUTO-* annotation and dashboard-writer amendment already shipped this session are the other parts already done.
