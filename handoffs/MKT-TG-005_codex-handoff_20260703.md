# Codex Handoff — MKT-TG-005: Path A lean RSS→Telegram news digest

## 1. Task ID
MKT-TG-005 (supersedes Make.com scenario from MKT-TG-003/004; keeps the existing bot's `/pick_news` flow untouched for now).

## 2. Context
Booster Shop CRM Google Apps Script already runs a single `doPost` Web App that is both a token-authenticated CRM API and the Telegram bot webhook handler (`handleTelegramUpdate_`, `handleTelegramCallback_`). Confirmed existing helpers to reuse (found in `patches/MKT-TG-004-BOT_apps-script-runtime-fix_20260629.js`, the newest available source copy in this repo — **Codex must diff against the live Apps Script project**, this file may not be 100% current):
- `tgSendMessage_(chatId, text, keyboard)`, `tgEditMessage_(chatId, messageId, text, keyboard)`, `tgAnswerCallback_(callbackId, text)`, `tgBotApi_(method, payload)`
- `tgIsAllowedChat_(chatId)`, `tgEscapeHtml_(value)`, `tgShowMainMenu_(chatId, messageId)`
- `handleTelegramUpdate_(payload)` / `handleTelegramCallback_(callbackQuery)` — command/callback routing
- `dateSortValue_(value)` — safe date coercion (handles Date, serial number, string)
- Script Properties already hold `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALLOWED_CHAT_ID`

Old Make.com scenario "Integration RSS" is being retired by the owner manually (cost, off-topic images, unreliable freshness — see `handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md` §2 for full rationale). The old `Новини_кандидати` sheet + `/pick_news` / `/delete_news` flow stays live and untouched — it is not part of this task's scope and will be deprecated separately later.

Decisions confirmed with owner (2026-07-03):
- Draft model: **Haiku** (`claude-haiku-4-5-20251001`)
- Draft context: **RSS title + description only** (no full-article fetch)
- Dedup store: **CacheService/PropertiesService**, not a new Sheet tab
- Host: add to the existing CRM Apps Script (it owns the Telegram webhook — no alternative)

## 3. Goal
Add a daily automated digest of fresh, on-topic TCG news headlines delivered to the owner's private Telegram bot chat, each with an inline **"✍️ Чернетка"** button that generates a one-off UA Telegram post draft on tap via the Anthropic API. No auto-posting to the group — owner always posts manually.

## 4. What to change
Add new, clearly namespaced functions to the CRM Apps Script (do not modify unrelated existing functions):

1. **`newsDigest_()`** — time-triggered daily (~10:00 Europe/Kyiv, owner sets the trigger in Apps Script UI, Codex should not create the trigger itself, just make the function trigger-ready).
   - Fetch 4 Google News RSS feeds via `UrlFetchApp` (URLs and queries are given verbatim in `handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md` §3A.1 — Pokémon TCG, One Piece CG, MTG/YGO, TCG market/industry).
   - Parse `<pubDate>` per item into a real `Date` (use `new Date(pubDateString)` — RFC822 parses natively in Apps Script's V8 runtime, confirm with a quick manual test since this was the exact bug in Make).
   - Keep only items from the last 3 days (tunable constant).
   - Dedup against a "seen guid" store using `CacheService.getScriptCache()` (note: max default TTL 21600s/6h — **must use `PropertiesService.getScriptProperties()`** instead for a persistent multi-day dedup window, or a custom TTL tracked manually; Codex should pick whichever is simpler and note the choice, cache alone will not survive 3 days).
   - Pick freshest per source: 1 Pokémon + 1 One Piece + 1 MTG/YGO + 2 TCG-market = 5 items (tunable constants, name them clearly e.g. `NEWS_DIGEST_PER_SOURCE_COUNT`).
   - For each picked item, fetch the article HTML and regex out `<meta property="og:image" content="...">`. Fallback: no image, just send text.
   - Send ONE digest message to `TELEGRAM_ALLOWED_CHAT_ID` via `tgSendMessage_` (or a new helper if multi-image needs `sendMediaGroup_`/`sendPhoto` — Codex should decide based on Telegram API constraints for og:image vs text-only fallback). Each item shows game tag + title + link, with inline button `«✍️ Чернетка»` → `callback_data: 'news_draft_' + <short id/guid hash>`.
   - Because Telegram `callback_data` is capped at 64 bytes, do NOT put the raw GUID/URL in callback_data. Store item data (title, description, source_url, game tag) in a short-lived `PropertiesService` or `CacheService` entry keyed by a short id, referenced from callback_data.

2. **Callback branch in `handleTelegramCallback_`**: add before the `tgAnswerCallback_(callbackId, 'Невідома дія')` fallback:
   ```
   if (data.indexOf('news_draft_') === 0) { ... }
   ```
   On tap: look up the stored item by short id, call `tgDraftPost_(item)` (new function, §5 below), then send the returned draft text back via `tgSendMessage_` (plain text, escaped, no auto-post).

3. **`tgDraftPost_(item)`** — calls `POST https://api.anthropic.com/v1/messages` via `UrlFetchApp`.
   - Headers: `x-api-key: <ANTHROPIC_API_KEY>` (new Script Property, owner adds the value manually — Codex must NOT hardcode any key), `anthropic-version: 2023-06-01`, `content-type: application/json`.
   - Model: `claude-haiku-4-5-20251001`, `max_tokens` ~500, `temperature` ~1.
   - System prompt and user-message pattern: copy verbatim from `handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md` §6 (Ukrainian system prompt, proven from the old Make module — do not alter wording).
   - Input: RSS title + description only (per owner decision) — no article body fetch for the draft step.

4. **`fetchOgImage_(url)`** — fetch article HTML via `UrlFetchApp` (short timeout / `muteHttpExceptions: true`), regex `<meta[^>]+property=["']og:image["'][^>]+content=["']([^"']+)["']` (handle attribute order variations), return the URL or `null`.

5. **`parseRssItems_(xml)`** — parse Google News RSS XML (`XmlService` or regex — Codex's choice, XmlService is safer for malformed feeds) into `{title, link, pubDate, guid, description}` objects per `<item>`.

## 5. Do not touch
- `doPost` routing for CRM API actions (`add_sale`, `add_purchase`, `add_writeoff`, `update_sale`, `update_purchase`, `add_news_candidate`) — untouched.
- Existing `/pick_news`, `/delete_news`, `tgCommandNews_`, `tgShowNewsPost_`, `tgCleanNews_`, `crmGetNewsCandidates_`, `setupNewsSheet_`, the `Новини_кандидати` sheet — untouched, stays live in parallel.
- `tgShowMainMenu_` keyboard — do not add a news-digest button here unless owner asks; digest is push-only via time trigger, not a menu item.
- Any CRM sheet write paths (`Продажі`, `Закупки`, `Списання`, `Витрати`) — completely unrelated, zero touches expected.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema — not applicable to this task but listed per standing protected-zone policy.
- `BOOSTER_CRM_TOKEN` / `TELEGRAM_BOT_TOKEN` values — never read them into logs, comments, or output. Same rule now applies to the new `ANTHROPIC_API_KEY` Script Property.

## 6. Likely files / areas
- Single Apps Script project (`Code.gs` or equivalent) — the CRM/bot Web App. Exact live file structure **must be verified by Codex against the actual Apps Script project**, not assumed from the repo's stale patch-file copies (`patches/MKT-TG-004-BOT_apps-script*.js` in this repo are historical snapshots, not guaranteed current).
- New Script Property: `ANTHROPIC_API_KEY` (owner adds manually in Apps Script → Project Settings → Script Properties; Codex should document the exact property name needed, not set the value).
- A new time-driven trigger for `newsDigest_()` — Codex can create this via Apps Script's trigger UI instructions for the owner, or via `ScriptApp.newTrigger()` in a one-time setup function (e.g. `setupNewsDigestTrigger_()`) that the owner runs once manually. Do not auto-create triggers on every deploy.

## 7. Acceptance criteria
- Running `newsDigest_()` manually (no time trigger yet) sends exactly one Telegram message to `TELEGRAM_ALLOWED_CHAT_ID` containing up to 5 items, each with a working `«✍️ Чернетка»` inline button.
- Each item's image (when present) is the article's own `og:image`, not a generic/unrelated search result — verify against at least one One Piece article to confirm no "Naruto" drift.
- Items are all from the last 3 days per their real `<pubDate>` (verify by logging parsed dates).
- Tapping «✍️ Чернетка» on one item returns a single Ukrainian draft post (100–180 words, 2–4 emoji, matches the system prompt in §6 of the source handoff) within a few seconds, sent as a new Telegram message in the same chat.
- Re-running `newsDigest_()` a second time same-day does not re-send already-seen items (dedup by guid/link works).
- No Anthropic API call happens during the digest step itself — only on button tap (verify via Apps Script execution log: exactly one `UrlFetchApp.fetch` to `api.anthropic.com` per tapped draft, zero during `newsDigest_()`).
- No existing CRM or `/pick_news` functionality regresses (spot-check `/orders`, `/pick_news`, main menu still work).

## 8. QA / smoke test
Not a checkout/payment/fiscalization task — `bs-checkout-smoke` not required. Not a schema/Merchant task — `bs-merchant-schema-qa` not required.
Manual QA steps for the owner after deploy:
1. Add `ANTHROPIC_API_KEY` to Script Properties.
2. Run `newsDigest_()` manually from the Apps Script editor (or a temporary test button) — confirm the digest message arrives in the bot chat with images and buttons.
3. Tap «✍️ Чернетка» on one item — confirm a UA draft arrives, reads naturally, no invented facts/prices, no markdown artifacts, no "На Reddit"/"Хтось" openers.
4. Check Apps Script execution log for errors and for exactly one Anthropic API call per tap.
5. Confirm `/pick_news`, `/orders`, main menu still behave as before (regression check).
6. Once trigger-ready, set up the daily time trigger (owner or Codex via `setupNewsDigestTrigger_()`), confirm it fires next day at ~10:00 Kyiv time.

## 9. Rollback note
All changes are additive new functions plus one new callback branch inside `handleTelegramCallback_`. Rollback = redeploy the previous Apps Script version (Apps Script keeps version history) or `git revert` the corresponding patch/diagnostic commit and redeploy. No sheet-data or Script Property destructive changes are made by this task (only one new property added, which is harmless to leave even if code is rolled back). If a daily trigger was created, the owner should delete it manually via Apps Script → Triggers before/after rollback to stop `newsDigest_()` from firing against reverted code.

## 10. Recommended status after execution
Move Notion card **MKT-TG-005** to `In progress` → `Review` once Codex reports back with diff + owner completes the QA checklist above. Do not mark `Done` until the owner confirms a real digest + a real draft tap both worked in production (not just in Apps Script logs).

---
**Risk note (per project SEO/technical risk policy):** this task does not touch sitemap, robots, canonical, redirects, checkout, payment, fiscalization, schema, or Merchant feed — low risk by the `bs-seo-risk-gate` criteria. Main technical risk is scope creep into the existing `/pick_news` Make-era flow or the shared `handleTelegramCallback_`/`handleTelegramUpdate_` router (one wrong edit there breaks the whole bot, as happened in the MKT-TG-004-BOT runtime-fix incident on 2026-06-29 — a duplicate/misplaced callback block caused a `ReferenceError` on every callback). Codex should add the new `news_draft_` branch cleanly inside the existing `try` block in `handleTelegramCallback_`, matching the existing style, and must not duplicate or move any existing branch.
