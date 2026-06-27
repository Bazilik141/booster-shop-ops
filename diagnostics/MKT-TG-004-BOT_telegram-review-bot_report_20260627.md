# Codex Report — MKT-TG-004-BOT: Telegram review bot

Date: 2026-06-27

## Scope

Implemented the bot-only slice from `MKT-TG-004-BOT_codex-handoff_20260627.md`:

- additive `/pick_news` and `/delete_news` routing;
- `news_list`, `news_pick_*`, `news_clean`, and `news_done_*` callbacks;
- news buttons in the existing bot main menu;
- idempotent `setupNewsSheet_()` with the exact A:K schema;
- three-day candidate reader, posted/archive status changes, and `setMyCommands`.

The existing order flow, API branch, shared Telegram helpers, CRM tabs, and cost logic were not changed.

## Files / sources touched

```text
Google Sheet: Booster Shop CRM — облік товарів
Tab: Apps_Script_код
Ranges: A1639, A1651, A2101, A2670:A2827

patches/MKT-TG-004-BOT_apps-script_20260627.js
handoffs/MKT-TG-004-BOT_codex-handoff_20260627.md
plans/tg-content-automation-phase2-plan_2026-06-27.md
```

`Apps_Script_код` remains a source copy. No Apps Script deployment was performed.

## Backup

Before editing, the source tab was duplicated to:

`Booster CRM Apps_Script_код backup 2026-06-27 MKT-TG-004-BOT`

Backup spreadsheet:
https://docs.google.com/spreadsheets/d/1Iivi9xPc0VMczKoOq54rDktXOPjqkb8rR8W16MGMoA4/edit

## Static check

```text
anchor_precheck=ok
syntax_check=ok
source_lines=2237
```

The complete CSV source was checked from `function onOpen()` after applying the same three anchored integrations and appending the news functions.

## Focused smoke

```text
smoke=ok
candidate_filter=ok
two_message_flow=ok
archive_old_only=ok
setMyCommands_payload=ok
```

The smoke used local Apps Script mocks. It did not call Telegram or modify live CRM records.

## Rollback

Restore the backup source copy, or remove:

- the news command additions from `handleTelegramUpdate_`;
- the four news callback branches from `handleTelegramCallback_`;
- the two news menu buttons from `tgShowMainMenu_`;
- the six appended news functions.

For a deployed Apps Script version, redeploy the previous version. The new candidate tab can remain unused or be deleted.

## Owner deployment / QA

- [ ] Copy the complete updated `Apps_Script_код` into the Apps Script editor.
- [ ] Save and deploy a new Web App version.
- [ ] Run `setupNewsSheet_()` once and verify `Новини_кандидати!A1:K1`.
- [ ] Run `tgSetupCommands_()` once and verify the private-chat `/` menu.
- [ ] Add two fresh test candidates and verify `/pick_news` and the two-message flow.
- [ ] Verify `news_done_*` writes `posted`.
- [ ] Verify `/delete_news` archives only `new` rows older than three days.
- [ ] Regression-check `/start` → active orders → order details → one status update.

## Side effects / risks

No database, OpenCart, checkout, payment, SEO, or existing CRM data changes. Main residual risk is the live order-bot regression until the updated Apps Script version is deployed and manually smoke-tested.

## Follow-up: source-copy syntax correction

After the owner copied `Apps_Script_код` into Code.gs, Apps Script reported:

```text
SyntaxError: Unexpected token '<' (line 2765)
```

Root cause: the Sheets `pasteData` operation treated the leading apostrophe in the source line as a spreadsheet text prefix and removed it from `Apps_Script_код!A2774`.

Corrected source-copy line:

```javascript
'<b>Джерело:</b> ' + (candidate.source_url ? tgEscapeHtml_(candidate.source_url) : '—')
```

The Code.gs line number maps exactly: sheet row 2774 minus 9 instruction rows equals Code.gs line 2765. A scan of the complete appended block confirmed this was the only code line beginning with an apostrophe. Repo artifact syntax remains `ok`.
