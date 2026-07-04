# Codex Handoff — MKT-TG-006: OpenAI URL-to-draft Telegram command

Date: 2026-07-04
Base file: `patches/MKT-TG-005_cleanup-and-trigger_20260704.js` (latest canonical Code.gs — includes `/digest`, `setupNewsDigestTrigger()`, Sonnet-based `tgDraftPost_`)

## 1. Task ID
MKT-TG-006

## 2. Context
MKT-TG-005 (RSS → Telegram digest, on-demand Claude draft) is live. Owner reports the RSS pipeline sometimes finds a genuinely interesting story but `fetchArticleText_` can't pull enough real content from the source site (thin page, JS-rendered content, paywall, etc.), so the draft comes out weak even after four rounds of prompt tuning.

New request: a separate Telegram command where the owner pastes a raw article URL directly (bypassing RSS/Google News entirely) and gets a Ukrainian Telegram post draft back. Owner explicitly asked for this to use **OpenAI**, not Anthropic/Sonnet, because they prefer OpenAI's text quality for this kind of copy.

Two design decisions were defaulted to the recommended option below because the confirmation prompt to the owner failed to send in this session. **Owner should confirm or override before Codex runs**, but the handoff is written assuming these defaults:

- Command shape: `/post <url>` as one message (not a two-step "bot waits for your next message" flow). Reason: stateless, matches the existing `text.indexOf('/xxx') === 0` routing pattern in `handleTelegramUpdate_`, no new PropertiesService "waiting for reply" state machine needed.
- Model: `gpt-5.5` (OpenAI's current flagship, confirmed via OpenAI's own docs as of 2026-07-04: $5 / $30 per MTok in/out, 128K max output, Responses API). Usage is on-demand and low-volume, so cost is negligible even at flagship pricing.

## 3. Goal
Add a `/post <url>` Telegram command that: takes the pasted URL, extracts article text (reuse `fetchArticleText_`), sends it to OpenAI with a Ukrainian system prompt equivalent in spirit to the one already proven in `tgDraftPost_`, and returns the draft in the same visual format the owner already sees from RSS drafts (bold tag line + post text).

## 4. What to change

- Add a new Script Property `OPENAI_API_KEY` (owner will paste the key manually in Apps Script — Codex should not invent or request the value).
- Add a new constant, e.g. `NEWS_DIGEST_OPENAI_MODEL = 'gpt-5.5'`.
- Add a new function, e.g. `openaiDraftPostFromUrl_(url)`:
  - Validates `url` is `http(s)://...`.
  - Calls existing `fetchArticleText_(url)` to get article text. If it returns `''` (too short / fetch failed), throw a clear error — do not fall back to guessing content.
  - Builds a system prompt **reusing the same rules already proven in `tgDraftPost_`'s `systemPrompt` array** (lines ~3112-3131 of the base file): no russianisms, 2-4 paragraphs / 60-180 words, no invented details, no generic truisms, no "Wikipedia spec-sheet" style, no meta-commentary about missing info, etc. Adapt only the framing line since there's no RSS title here — e.g. derive/allow a working title from the page (`<title>` tag or first heading) if easily available from the already-fetched HTML, otherwise ask for just the URL context without inventing a headline.
  - Calls OpenAI's Responses API (`https://api.openai.com/v1/responses`, `Authorization: Bearer <OPENAI_API_KEY>`, `model: NEWS_DIGEST_OPENAI_MODEL`) via `UrlFetchApp.fetch`. Codex should verify current exact request/response shape for the Responses API against OpenAI's own docs before wiring the parser — do not assume the Chat Completions schema.
  - Parses the response text using the same `tag\n===\npost` convention as `tgDraftPost_`, returning `{ tag, text }`.
  - On any error (missing key, fetch failure, empty article, malformed API response), throw with a clear message; caller handles the user-facing Ukrainian error text.
- Add a new command handler, e.g. `tgCommandPostFromUrl_(chatId, rawText)`:
  - Extracts the URL portion after `/post` (trim, take first whitespace-delimited token).
  - If no URL or invalid format: send a friendly Ukrainian message explaining usage (`/post <посилання>`), no exception.
  - Otherwise call `openaiDraftPostFromUrl_`, then send the result in the same format as the existing `news_draft_` callback: bold tag (if present) + escaped post text via `tgSendMessage_`/`tgEscapeHtml_`.
  - On error, log via `Logger.log` (same pattern as `tgCommandDigest_`/the `news_draft_` callback) and send a friendly Ukrainian failure message — never leak raw API errors to the chat.
- Route `/post` in `handleTelegramUpdate_` (same block as `/orders` and `/digest`, ~line 1631-1632): `if (text.indexOf('/post') === 0) { tgCommandPostFromUrl_(chatId, text); return; }`.
- Add `/post` to the Telegram command list in `tgSetupCommands_` (alongside `/start`, `/orders`, `/digest`) with a short description, e.g. "Чернетка за посиланням".

## 5. Do not touch

- `tgDraftPost_`, `NEWS_DIGEST_ANTHROPIC_MODEL`, `ANTHROPIC_API_KEY` usage, the RSS digest pipeline (`newsDigest`, `parseRssItems_`, `newsBuildDigestMessage_`, `resolveGoogleNewsArticleUrl_`), and the `news_draft_` callback branch. This is a parallel, independent path — the existing Sonnet-based RSS draft flow must keep working exactly as-is.
- `/orders`, `/digest`, `tgCommandOrders_`, `tgCommandDigest_`, `tgShowMainMenu_`, all order-update callback branches.
- `setupNewsDigestTrigger()` and the daily trigger.
- CRM read/write paths, `apiAddNewsCandidate_`, `add_news_candidate` in `doPost`.
- Standard protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema/JSON-LD. None of these should be anywhere near this change, but flagging per policy.

## 6. Likely files / areas

- `patches/MKT-TG-005_cleanup-and-trigger_20260704.js` is the canonical base (or the live `Code.gs` / the CRM sheet's `Apps_Script_код` tab, which the owner keeps in sync manually — Codex should verify against actual current project files rather than assume this patch file is still 1:1 with the live script).
- `handleTelegramUpdate_` (~line 1621) — add routing.
- `tgSetupCommands_` — add command entry.
- New functions can go near `tgDraftPost_` (~line 3107) since they share `fetchArticleText_`.

## 7. Acceptance criteria

- Sending `/post https://<valid article url>` to the bot returns a Ukrainian draft in the chat, formatted like existing RSS drafts (bold tag + post text), generated via OpenAI (not Anthropic).
- Sending `/post` with no URL, or a malformed URL, returns a friendly Ukrainian usage message — no thrown error reaches Telegram, no 500 in Apps Script executions.
- If `OPENAI_API_KEY` is missing or the OpenAI call fails, the bot sends a friendly Ukrainian failure message and logs the real error via `Logger.log` — same pattern as the existing Anthropic draft error handling.
- If `fetchArticleText_` can't extract enough text from the pasted URL, the bot says so plainly (e.g. "Не вдалося зчитати статтю за цим посиланням") rather than generating a draft from almost nothing.
- `/orders`, `/digest`, existing `✍️ Чернетка` buttons, and the daily digest trigger all continue to work unchanged.
- `node --check` passes on the full file.

## 8. QA / smoke test

- [ ] `/post` (no URL) → usage message, no crash.
- [ ] `/post not-a-url` → usage/validation message, no crash.
- [ ] `/post <real article URL with substantial text>` → draft returned, tag + post visible, reads like OpenAI output (not identical boilerplate to Sonnet drafts).
- [ ] `/post <URL that returns very little text, e.g. a thin listing page>` → friendly "couldn't read this article" message, not a hallucinated draft.
- [ ] Temporarily remove/blank `OPENAI_API_KEY` → friendly failure message, Apps Script Executions log shows the real error.
- [ ] `/digest`, `/orders`, `✍️ Чернетка` on an existing digest item, main menu — all still work exactly as before.
- [ ] Telegram command menu (after re-running `tgSetupCommands()` once) shows `/post` alongside `/start`, `/orders`, `/digest`.

This touches an outbound AI text-generation path with a new external vendor, not checkout/payment/schema/feed, so `bs-checkout-smoke` / `bs-merchant-schema-qa` / `bs-seo-risk-gate` do not apply here.

## 9. Rollback note

- Local source: restore `patches/MKT-TG-005_cleanup-and-trigger_20260704.js` (pre-MKT-TG-006 state) as `Code.gs`.
- Remove the `/post` entry from `tgSetupCommands_` and re-run `tgSetupCommands()` to drop it from the Telegram menu.
- Optionally delete the `OPENAI_API_KEY` Script Property if fully rolling back (no other function should depend on it).
- No trigger or CRM data changes involved, so no Sheet/trigger rollback needed.

## 10. Recommended status after execution

MKT-TG-006 → "In progress" until the owner completes the QA checklist above with a real pasted URL and confirms draft quality is acceptable, then → "Done".
