# Codex Handoff — MKT-TG-005 (part 2): full cleanup + on-demand digest trigger + daily trigger setup

## 1. Task ID
MKT-TG-005 (continuation — Path A is live and working: daily-digest logic, on-demand Anthropic drafts with full-article-text fetch, and a first pass of old-pipeline cleanup are already deployed and confirmed working by the owner).

## 2. Context
The owner has already manually pasted the current `patches/MKT-TG-005_lean-rss-digest_20260703.js` into the live Apps Script project (the CRM spreadsheet's Apps Script source-copy tab, `Apps_Script_код`, has also been updated to match — **treat that tab and the repo patch file as the current live source, not the older `MKT-TG-004-BOT_apps-script-runtime-fix_20260629.js` snapshot**).

Confirmed working in production by the owner:
- `newsDigest_()` sends a daily digest with per-item "✍️ Чернетка" buttons.
- Tapping a draft button now fetches the full article text (`fetchArticleText_`) and calls Anthropic (model `claude-sonnet-4-6`, prompt tuned with owner-supplied examples) — draft quality confirmed acceptable by the owner after several rounds of live feedback.
- A prior cleanup pass removed `/pick_news`, `/delete_news` command routing, their callback branches (`news_list`, `news_pick_`, `news_clean`, `news_done_`), the "Новини" / "Очистити новини (>3д)" buttons from `tgShowMainMenu_`, and `seedNewsTest`/`seedNewsTestRows_`. Confirmed in the live bot: new `tgShowMainMenu_` invocations correctly show only "Активні замовлення".

**Known loose ends from that cleanup pass (this is what this handoff is for):**

1. **Stale Telegram command list.** Removing `pick_news`/`delete_news` from `tgSetupCommands_()`'s `commands` array does not retroactively update Telegram — the owner still sees `/pick_news`, `/delete_news` in Telegram's own command menu because `setMyCommands` was never re-called after the code change. **The owner can fix this immediately by running the existing `tgSetupCommands()` wrapper once from the Apps Script editor — this does not need Codex.** Codex does not need to touch this, just be aware it's already handled operationally.

2. **Three functions were not cleanly deleted, only neutered.** Due to a text-editing tool limitation on the Claude side (an exact-match issue with a `` unit-separator character used in a header-validation check), `setupNewsSheet_()`, `crmGetNewsCandidates_()`, and `tgCleanNews_()` were **not physically removed**. Instead each function body was replaced with a harmless one-line stub (e.g. `function crmGetNewsCandidates_(maxDays) { return []; }`) and the original dead body was wrapped in a `/* ... */` block comment directly below it, left in place. Functionally this is equivalent to deletion (nothing calls these three functions anymore — confirmed by grep across the whole file), but it is not a clean removal. **Codex should fully delete these three stub+comment blocks** (search for the inline comments starting `/* retired 2026-07-03, MKT-TG-005 cleanup`).

3. **No on-demand way to trigger a fresh digest without opening the Apps Script editor.** Right now `newsDigest_()` only runs via a daily time-driven trigger (if one has even been created — **Codex should verify whether a trigger for `newsDigest_` already exists in the project; if not, this needs to be set up too**, see §4.3) or by manually selecting a wrapper function in the Apps Script "Run" dropdown. The owner explicitly asked for a way to get a fresh digest from Telegram itself, without touching the script editor.

4. **Optional, not yet decided by owner:** `apiAddNewsCandidate_` and the `add_news_candidate` branch in `doPost` are leftover API surface from the old Make pipeline (Make is being deactivated). Nothing calls this endpoint now. **Do not remove this without an explicit owner go-ahead** — flag it back to the owner/Claude as available for a future cleanup pass, don't take initiative on it here.

## 3. Goal
1. Fully remove the three neutered dead functions (physical deletion, not comment-wrapping).
2. Add a Telegram-side way to trigger `newsDigest_()` on demand (new command, e.g. `/digest`, routed the same way `/orders` is routed today) so the owner never has to open the Apps Script editor for routine use.
3. Verify a daily time-driven trigger for `newsDigest_()` exists; if not, add a one-time setup function (no-underscore-suffixed wrapper, so it's visible in the Apps Script "Run" dropdown) that the owner can run once to create it — do not create the trigger automatically on every script save/deploy.

## 4. What to change

### 4.1 Delete dead code cleanly
Remove the three `/* retired 2026-07-03, MKT-TG-005 cleanup ... */` comment blocks and their preceding one-line stub functions entirely:
- `setupNewsSheet_()`
- `crmGetNewsCandidates_()`
- `tgCleanNews_()`

Confirm via search that no remaining code references these three function names before deleting (Claude already confirmed zero callers as of 2026-07-03, but Codex should re-verify against the current live source before deleting, since the owner may have made further manual edits).

### 4.2 On-demand digest command
- Add `/digest` (or similar short command — Codex's naming choice, keep it short and consistent with `/orders`) to `handleTelegramUpdate_`'s routing, calling `newsDigest_()` directly, guarded by the same `tgIsAllowedChat_` check already in place for other commands.
- Add the same command to `tgSetupCommands_()`'s `commands` array (currently only `{ command: 'start', description: 'Головне меню' }`) so it appears in Telegram's command menu, e.g. `{ command: 'digest', description: 'Свіжий дайджест новин' }`.
- `newsDigest_()` currently returns silently (`{ ok: true, sent: 0, skipped: 'no_fresh_items' }`, no chat message) when there are no fresh unseen items. For a manually-triggered command this is a bad UX — the owner taps `/digest` and sees nothing happen. Codex should make `newsDigest_()` (or a thin wrapper around it used only for the manual-trigger path) send a short chat reply like "Немає нових новин" when nothing is sent, without changing behavior for the automatic daily-trigger case (or decide it's fine to always send a short status reply either way — Codex's call, just make sure a manual tap always produces visible feedback in the chat).
- `newsDigest_()` uses `LockService.getScriptLock()` with a 5-second wait and throws if it can't acquire the lock. If `/digest` is tapped while the daily trigger happens to be running concurrently, the current code would throw an uncaught error with no user-facing message. Codex should wrap the manual-trigger call path in a try/catch that sends a friendly "Спробуй за хвилину, дайджест уже формується" (or similar) message instead of a silent failure or a raw error bubbling up through `doPost`.

### 4.3 Daily trigger verification / setup
- Codex should check the Apps Script project's existing triggers (via the Triggers UI or `ScriptApp.getProjectTriggers()`) to confirm whether a time-driven trigger for `newsDigest_` already exists.
- If none exists, add a one-time setup function, e.g. `setupNewsDigestTrigger()` (no trailing underscore, so it shows up directly in the Apps Script "Run" dropdown — same reason `tgSetupCommands()` exists as a wrapper over `tgSetupCommands_()`), that creates a single daily time-driven trigger for `newsDigest_` at approximately 10:00 (project timezone is already `Europe/Kiev`, confirmed in the prior Codex report for this task). Guard against creating duplicate triggers on repeated runs (check `ScriptApp.getProjectTriggers()` for an existing `newsDigest_` trigger before adding another, mirroring the existing dedup pattern already used elsewhere in the script for `updateLotStatuses`, per `patches/MKT-TG-004-BOT_apps-script-runtime-fix_20260629.js` line ~2191).
- The owner runs this setup function once, manually, the same way they'd run any other wrapper — Codex should not create the trigger automatically as a side effect of anything else.

## 5. Do not touch
- CRM write paths (`Продажі`, `Закупки`, `Списання`, `Витрати`) and their API actions (`add_sale`, `add_purchase`, `add_writeoff`, `update_sale`, `update_purchase`) in `doPost`.
- `/orders` command, `tgCommandOrders_`, `tgShowOrderDetails_`, `tgCommandUpdate_`, `tgUpdateOrderStatus_` — the active orders-review flow.
- `newsDigest_`, `fetchArticleText_`, `fetchOgImage_`, `resolveGoogleNewsArticleUrl_`, `parseRssItems_`, `tgDraftPost_`, the `news_draft_` callback branch, and the whole Anthropic-call/prompt logic — this is freshly tuned based on several rounds of live owner feedback and is working well; do not alter the system prompt, model, or draft logic as part of this task.
- `apiAddNewsCandidate_` and the `add_news_candidate` branch in `doPost` — leave in place, not in scope for this task (see §2.4).
- The `Новини_кандидати` Google Sheet tab itself — this is data, not code; do not delete the sheet tab. If it's truly unused going forward that's a separate manual owner decision, not a code change.
- Standard protected zones (not touched by this task, listed per project policy): `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas
Single Apps Script project (CRM `Code.gs` / live source-copy tab `Apps_Script_код` in the CRM spreadsheet). Reference copy in this repo: `patches/MKT-TG-005_lean-rss-digest_20260703.js` — this is the most current committed snapshot, but Codex should verify against the actual live Apps Script project / the spreadsheet's source-copy tab, since the owner may have made small manual edits directly in the editor since this file was last committed.

## 7. Acceptance criteria
- Grepping the live source for `setupNewsSheet_`, `crmGetNewsCandidates_`, `tgCleanNews_`, and the `/* retired 2026-07-03` comment markers returns zero matches.
- `node --check` (or Apps Script's own syntax validation) passes on the full file.
- Typing `/digest` in the bot chat triggers a fresh digest attempt immediately; if fresh items exist, a digest message with draft buttons appears; if none exist, a visible chat message says so (not silence).
- `/digest` appears in Telegram's own command menu after `tgSetupCommands()` is re-run.
- A time-driven trigger for `newsDigest_` exists in the project (either pre-existing and confirmed, or newly created via the one-time setup function) and does not get duplicated on repeated manual runs of the setup function.
- `/orders`, the "Активні замовлення" flow, and existing draft-button behavior on already-sent digest messages all continue to work unchanged.

## 8. QA / smoke test
Not a checkout/payment/fiscalization/schema/Merchant task — `bs-checkout-smoke` and `bs-merchant-schema-qa` are not required. Manual QA for the owner after deploy:
1. Confirm the three dead functions are gone (search the script for their names).
2. Tap `/digest` when there are known-fresh unseen items — confirm a digest message with buttons arrives.
3. Tap `/digest` again immediately after — confirm either nothing new is silently missed (dedup already covers this) and confirm a clear "no news" message appears instead of silence.
4. Confirm `/digest` shows up in Telegram's command menu (may need `tgSetupCommands()` re-run once, same as the owner already needs to do for the current cleanup).
5. Check Apps Script → Triggers — confirm exactly one time-driven trigger exists for `newsDigest_` after running the setup function (run it twice to confirm no duplicate is created the second time).
6. Regression: `/orders`, "Активні замовлення", and tapping "✍️ Чернетка" on an existing digest message all still work.

## 9. Rollback note
All changes are: (a) pure deletions of already-dead, already-neutered code (zero behavior change even if rollback is needed — reverting just brings back inert comments, no functional risk either way), (b) one new additive command branch + one new command-list entry, (c) one new additive one-time trigger-setup function. Rollback = redeploy the previous Apps Script version (version history) or revert the corresponding commit and re-paste. If a new trigger was created via the setup function, the owner should delete it manually via Apps Script → Triggers if rolling back, to stop `newsDigest_` firing against reverted code.

## 10. Recommended status after execution
Keep Notion card **MKT-TG-005** at `In progress`. Move to `Review` once Codex reports back with the diff, and the owner has completed the QA checklist in §8 against the live bot (not just Apps Script logs). Do not mark `Done` until `/digest` has been tapped for real in production and a real daily-trigger digest has fired at least once.

---
**Risk note (per project SEO/technical risk policy):** no sitemap, robots, canonical, redirects, checkout, payment, fiscalization, schema, or Merchant feed involved — low risk by `bs-seo-risk-gate` criteria. Main technical risk is the same shared `handleTelegramUpdate_`/`handleTelegramCallback_` router that had a `ReferenceError` incident on 2026-06-29 (a misplaced/duplicated callback block) — the new `/digest` branch should be added the same clean way the `news_draft_` branch was added previously (one clearly-scoped `if` block, no duplication, no moving of existing branches).
