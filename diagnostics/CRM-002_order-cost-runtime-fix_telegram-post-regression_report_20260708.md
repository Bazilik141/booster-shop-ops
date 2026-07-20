# Codex Report — CRM-002: restore Telegram `/post` URL draft route

Date: 2026-07-08

## Scope
Follow-up to `patches/CRM-002_order-cost-runtime-fix_20260707.js`.

The 2026-07-07 Apps Script source-copy kept the order-cost runtime changes but regressed the MKT-TG-006 Telegram `/post <url>` flow. This follow-up restores the URL-draft route and related helper functions without changing the order-cost logic.

## Files touched
```
patches/CRM-002_order-cost-runtime-fix_telegram-post-regression_20260708.js
diagnostics/CRM-002_order-cost-runtime-fix_telegram-post-regression_report_20260708.md
```

## Cause
`handleTelegramUpdate_()` in `CRM-002_order-cost-runtime-fix_20260707.js` handled only:

- `/debug_chat`
- `/orders`
- `/digest`

Any other message fell through to `tgShowMainMenu_()`, so `/post` or a pasted URL produced the `Booster CRM` menu with `Активні замовлення`.

## Implemented fix
Built a new full Apps Script source-copy from `CRM-002_order-cost-runtime-fix_20260707.js` as the base and restored only the working MKT-TG-006 blocks from `CRM-002_stable-open-cart-cost-runtime_20260705.js`:

- `/post` route in `handleTelegramUpdate_()`;
- `tgCommandPostFromUrl_()`;
- `tgBeginPostFromUrl_()`;
- `tgHandleAwaitingPostUrl_()`;
- `digest_run`, `post_start`, `post_help` callback routes;
- expanded Telegram main menu buttons;
- `/post` command registration in `tgSetupCommands()`;
- `setupOpenAiApiKey()`;
- `NEWS_DIGEST_OPENAI_MODEL`;
- `openaiDraftPostFromUrl_()` and `openaiResponseText_()`.

## Verification
Local static checks:

```text
node --check CRM-002_order-cost-runtime-fix_telegram-post-regression_20260708.js
syntax ok
```

Presence/duplication checks:

```text
function tgCommandPostFromUrl_=1
function tgHandleAwaitingPostUrl_=1
function openaiDraftPostFromUrl_=1
function openaiResponseText_=1
function fixSaleCostForRow_=1
```

## Deployment / owner action
This is a full Apps Script source-copy. It does not deploy itself.

Owner action:

1. Copy the full contents of `patches/CRM-002_order-cost-runtime-fix_telegram-post-regression_20260708.js` into the CRM Apps Script `Code.gs`.
2. Save/deploy the Apps Script project as usual.
3. Run `tgSetupCommands()` once if the Telegram command menu needs to show `/post`.

## Post-deploy QA checklist
- [ ] `/post` with no URL starts the 10-minute URL wait flow.
- [ ] Sending a URL after `/post` returns a draft or a friendly read/API error.
- [ ] `/post https://...` directly returns a Ukrainian draft.
- [ ] `/digest` still works.
- [ ] Existing `✍️ Чернетка` buttons still work.
- [ ] `/orders` and `Активні замовлення` still work.
- [ ] A new OpenCart order still gets correct order-cost runtime handling.

## Rollback
If this follow-up causes an issue, restore the previous Apps Script content from the deployed version history or copy back `patches/CRM-002_order-cost-runtime-fix_20260707.js`.

## Side effects / risks
Medium CRM/Telegram risk because this touches the shared Telegram webhook router. No Google Sheet data, OpenCart files, database, or server files were changed locally.
