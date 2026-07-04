# Codex Report — MKT-TG-005: cleanup and Telegram digest trigger

Date: 2026-07-04

## Scope

Implemented the handoff 1:1:

- physically removed the dead `setupNewsSheet_`, `crmGetNewsCandidates_`, and `tgCleanNews_` stub/comment blocks;
- added the allowed-chat `/digest` route with visible no-news and concurrency feedback;
- added `/digest` to the directly runnable `tgSetupCommands()`;
- renamed the directly runnable digest function from `newsDigest_()` to `newsDigest()` and removed the temporary `runNewsDigestTest()` wrapper;
- added idempotent `setupNewsDigestTrigger()` for a daily trigger at approximately 10:00 Europe/Kiev, including duplicate and legacy-handler cleanup.

Out of scope and unchanged: CRM write actions, `/orders`, order callbacks, digest content logic, article/image fetching, draft callback/prompt/model logic, `apiAddNewsCandidate_`, and `add_news_candidate`.

## Files touched

```text
patches/MKT-TG-005_cleanup-and-trigger_20260704.js
diagnostics/MKT-TG-005_cleanup-and-trigger_report_20260704.md
Google Sheet: Booster Shop CRM — облік товарів / Apps_Script_код
```

The Sheet tab is only a source copy. The bound Apps Script project and Web App deployment were not changed by Codex.

## Live source-copy verification

- Spreadsheet timezone: `Europe/Kiev`.
- Before edit: `Apps_Script_код` had 3305 rows, including 9 instruction/header rows and 3295 source rows.
- After edit: 3252 rows.
- Searches across `Apps_Script_код!A1:A3252` return zero matches for:
  - `setupNewsSheet_`
  - `crmGetNewsCandidates_`
  - `tgCleanNews_`
  - the removed `/* retired 2026-07-03 ... dead code` markers
  - `function newsDigest_`
  - `function tgSetupCommands_`
  - `runNewsDigestTest`
- Readback confirms `/digest`, directly runnable `newsDigest()` / `tgSetupCommands()`, the Telegram command-list entry, and `setupNewsDigestTrigger()` are present.

Actual project-trigger state is not exposed through Google Sheets API. The setup function therefore checks `ScriptApp.getProjectTriggers()` at owner-run time, removes the legacy `newsDigest_` trigger, keeps one `newsDigest` trigger, removes duplicates, and creates one only when none exists.

## Dry-run result

```text
smoke=ok
digest_no_news=ok
digest_lock_conflict=ok
orders_route=ok
trigger_idempotency=ok
legacy_trigger_migration=ok
```

## Syntax check

```text
node --check MKT-TG-005_cleanup-and-trigger_20260704.js
exit=0
```

## Idempotency

Repeated `setupNewsDigestTrigger()` runs keep exactly one `newsDigest` trigger. The focused smoke test also confirmed automatic removal of the legacy `newsDigest_` trigger.

## Rollback

- Local source: restore the previous `patches/MKT-TG-005_lean-rss-digest_20260703.js`.
- Sheet source copy: use Google Sheets version history.
- Bound Apps Script: restore the previous Apps Script version and redeploy it.
- If the trigger was created, remove the `newsDigest` time-driven trigger in Apps Script → Triggers.

## Owner run steps

1. Replace `Code.gs` with the complete contents of `patches/MKT-TG-005_cleanup-and-trigger_20260704.js`.
2. Save and deploy a new Web App version.
3. Run `tgSetupCommands()` once.
4. Run `setupNewsDigestTrigger()` twice and confirm the second run reports that the trigger already exists.

## Post-deploy QA checklist

- [ ] `/digest` with fresh items sends a digest with draft buttons.
- [ ] Repeating `/digest` with no fresh items sends `Немає нових новин`.
- [ ] Concurrent `/digest` shows the friendly retry message.
- [ ] Telegram command menu contains `/digest`.
- [ ] Apps Script → Triggers shows exactly one daily `newsDigest` trigger.
- [ ] `/orders` and `Активні замовлення` still work.
- [ ] An existing `✍️ Чернетка` button still creates a draft.
- [ ] A real daily digest fires once before MKT-TG-005 is marked Done.

## Side effects / risks

Low risk. The shared Telegram message router gained one isolated branch. No CRM data, checkout, payment, fiscalization, SEO, schema, Merchant feed, or database path was changed.
