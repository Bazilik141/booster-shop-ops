# Codex Report — MKT-TG-004-BOT: Apps Script runtime fix

Date: 2026-06-29

## Scope

Reviewed the owner-provided active `Code.gs` source, compared the Telegram/news integration with the live CRM source-copy tab `Apps_Script_код`, and fixed the `news_done_*` callback flow.

No Google Sheet cells, CRM records, Script Properties, Telegram messages, or deployments were changed during this review.

## Files touched

- `patches/MKT-TG-004-BOT_apps-script-runtime-fix_20260629.js`
- `diagnostics/MKT-TG-004-BOT_apps-script-runtime-fix_report_20260629.md`

## Finding and fix

The active source had a duplicate `news_done_*` block inserted inside `handleTelegramUpdate_()`. That block referenced `data`, `callbackId`, `chatId`, and `messageId` outside their scope, so every Telegram callback could finish with a `ReferenceError`.

The corrected source:

- removes the misplaced duplicate block;
- keeps status update logic in `handleTelegramCallback_()`;
- after status becomes `posted`, calls `tgShowMainMenu_(chatId, messageId)`;
- preserves the CRM/order flow and all active news-candidate additions.

## Comparison with `Apps_Script_код`

The source-copy tab already contains the original MKT-TG-004-BOT implementation:

- `/pick_news` and `/delete_news` routing;
- `news_list`, `news_pick_*`, `news_clean`, `news_done_*`;
- news buttons in the main menu;
- `setupNewsSheet_`, `crmGetNewsCandidates_`, `tgCommandNews_`, `tgShowNewsPost_`, `tgCleanNews_`, `tgSetupCommands_`.

The owner-provided active source is newer. The source-copy tab does not contain:

- `add_news_candidate` routing in `doPost`;
- `apiAddNewsCandidate_`;
- public wrappers `setupNewsSheet()` and `tgSetupCommands()`;
- `seedNewsTest()` and `seedNewsTestRows_()`;
- `/start` in `tgSetupCommands_()`.

No recently delivered MKT-TG-004-BOT function is missing from the active source.

## Verification

```text
syntax_check=ok
duplicate_functions=0
function_count=186
news_done_smoke=ok
main_menu_return=ok
```

The smoke test used local Apps Script mocks. It verified the sequence:

1. candidate status cell receives `posted`;
2. callback receives `Позначено`;
3. the same Telegram message is replaced with the main menu.

No live Telegram API or CRM mutation was used.

## Idempotency

Repeated clicks are naturally limited because the button message is replaced by the main menu after the first successful click. The candidate lookup only accepts rows still having status `new`.

## Rollback

Redeploy the previous Apps Script version. No sheet-data rollback is required for the code review itself.

## Deployment

Replace the current `Code.gs` with the corrected full source, save it, and deploy a new Web App version. The `Apps_Script_код` tab remains a stale source copy until it is separately synchronized.

## Post-deploy QA

- Open `/start` and confirm the main menu.
- Open `Новини`, select one candidate, and press `✓ Опубліковано`.
- Confirm the candidate status becomes `posted`.
- Confirm the source/image message changes to the main menu.
- Open `Активні замовлення` and confirm the order flow still works.
- Check Apps Script executions for absence of `ReferenceError: data is not defined`.
