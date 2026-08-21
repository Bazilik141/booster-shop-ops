# MKT-TG-008 — Telegram pasted-text fallback state diagnosis

Date: 2026-08-21

## Scope

Read-only diagnosis of the Telegram bot path shown in the owner screenshots:

1. `/post <URL>` cannot extract the article body.
2. The bot asks the owner to paste the original text and says it will wait for ten minutes.
3. A long non-command text is sent immediately afterwards.
4. The bot returns the main menu instead of generating a draft.

The initial diagnosis made no live changes. The local correction and later owner-reported deployment are recorded below.

## Evidence

- `crm/apps-script/Code.gs` parses successfully with Node `new Function`.
- The screenshot's fallback prompt exactly matches `tgBeginNewsTextFallback_()` in the local mirror.
- `tgBeginNewsTextFallback_()` calls `tgSetNewsInputWait_()`, which stores the wait state only in `CacheService.getScriptCache()` for 600 seconds.
- On the next webhook request, `tgHandleAwaitingNewsInput_()` calls `tgGetNewsInputWait_()`. If the cache read returns no value, it returns `false` without calling OpenAI.
- `handleTelegramUpdate_()` then immediately executes `tgShowMainMenu_(chatId)`. This is the only matching control-flow route for the pasted article text in the screenshots.
- `resetMemo_()` at the start of `doPost()` resets only the in-process `_memo` object. It does not remove the Telegram cache key.

## Finding

The failure happens **before** `openaiDraftPostFromText_()` is invoked: the ten-minute waiting state is missing at the start of the pasted-text webhook execution.

`CacheService` is unsuitable as the sole source of truth for this interaction. Google documents that cached data is not guaranteed to persist until its expiry and a read can return `null`; the code treats that `null` as an ordinary message and shows the main menu. This explains the immediate symptom without an OpenAI error message.

The current local fallback implementation was imported into the mirror in `c34d2bc` (2026-08-08). The most recent commit, `b9b35d7` (2026-08-21), modifies only `apiAddSku_()` and dashboard files; it has no Telegram-router or wait-state diff. Therefore it is not the direct code-level cause. A new Web App publication may have made the latent cache dependency visible, but that deployment-to-source identity is not locally proven.

## Local correction prepared and owner-reported deployed

The local mirror now replaces the three `tg*NewsInputWait_*` cache helpers with a durable, short-lived session record in Script Properties:

- key remains scoped by `chatId`;
- value contains the existing context plus an explicit `expires_at` timestamp;
- read deletes and rejects an expired record;
- cancel and successful consumption delete the record;
- no pasted article text is stored.

`tgHandleAwaitingNewsInput_()` now replies explicitly when a wait period expired instead of silently falling through to `tgShowMainMenu_`.

New regression test: `crm/apps-script/tests/telegram-news-input-state.test.mjs`. It proves that a 180+ character pasted article reaches `openaiDraftPostFromText_` through Script Properties while its `CacheService` mock throws on every access. It also covers cancel, state cleanup and expiry.

## Local validation

- Node VM parse of `crm/apps-script/Code.gs`: passed.
- `node crm/apps-script/tests/telegram-news-input-state.test.mjs`: passed.
- Full CRM Apps Script suite: 21/21 test files passed.
- `git diff --check` for the tracked source change: passed.

Codex made no live operation. The owner subsequently reported CRM V140 published at 11:03 Kyiv on 2026-08-21 with deployment and QA OK; a fresh post-V140 source export was not supplied.

## Required source gate before a patch

**Closed 2026-08-21.** The owner supplied a fresh complete bound-source export as `pasted-text.txt`, 8,336 physical lines / 490,660 characters. It contains no detected OpenAI, Telegram, or Google API secret-like literal. After line-ending normalization it is byte-identical to `crm/apps-script/Code.gs`: SHA-256 `da4c5173dae7c9eb39b6d97cf3e2f6bb8a63aa93969d8eeb45eea31fd393b5f2` and 8,337 normalized lines in each file. The three wait-state helpers and Telegram router are therefore current-source evidence, not a mirror-only inference.

## Owner QA after publication

Owner report: **CRM V140, 2026-08-21 11:03 Kyiv — deployment + QA OK.** The detailed per-scenario results below were not separately supplied.

- [ ] Use a URL that forces the text fallback, then paste a 180+ character article immediately; a draft arrives.
- [ ] Repeat with `/post` followed directly by a long pasted article; a draft arrives.
- [ ] Send `/cancel`; the next ordinary message does not generate a draft.
- [ ] Let the ten-minute wait expire; the next message receives an explicit expiry response rather than a silent menu fallback.

## Risk

Low-to-medium: the change is isolated to Telegram conversation state, but it touches the shared `doPost` Telegram webhook router. The pre-patch mirror was byte-verified against the owner-supplied current source; V140 deployment and QA are owner-reported, not a post-publication byte comparison.
