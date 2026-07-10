# Codex Handoff — MKT-TG-007: digest UA teaser, OpenAI as draft engine, text-paste fallback

Date: 2026-07-10
Base file: `patches/MKT-TG-006_openai-url-draft_20260704.js` (latest canonical Code.gs — includes `/digest`, `/post`, `newsDigest()`, Anthropic-based `tgDraftPost_`, OpenAI-based `openaiDraftPostFromUrl_`)

## 1. Task ID
MKT-TG-007

## 2. Context
Live pipeline (MKT-TG-005 + MKT-TG-006) today:
- `newsDigest()` (~line 2912) fetches 4 Google News RSS feeds, picks ≤5 fresh unseen items, sends ONE digest message via `newsBuildDigestMessage_()` (~line 3136) showing per item only `[GameTag] <English RSS title, linked>` + optional og:image link — no summary text — with a "✍️ Чернетка" button per item (`news_draft_<id>`).
- Tapping "Чернетка" → `news_draft_` branch in `handleTelegramCallback_` (~line 1732) → `tgDraftPost_(item)` (~line 3205) → calls **Anthropic** (`NEWS_DIGEST_ANTHROPIC_MODEL = 'claude-sonnet-4-6'`), using `fetchArticleText_(item.sourceUrl)` with fallback to the thin RSS `description` if extraction fails.
- Separately, `/post <url>` (`tgCommandPostFromUrl_`, ~line 1661) and the two-step wait-for-URL flow (`tgBeginPostFromUrl_` / `tgHandleAwaitingPostUrl_`, ~line 1689-1715) call `openaiDraftPostFromUrl_(url)` (~line 3282, **OpenAI** `gpt-5.5`), which depends on `fetchArticleText_(url)` and **throws `'Article text unavailable'`** with no fallback if extraction fails. The wait-for-URL flow also **rejects any message that isn't a URL** (regex `^https?:\/\/`).

Owner feedback (2026-07-10): digest search/delivery is fine. Anthropic's full-post writing quality is not satisfying; OpenAI's is preferred (this is already why `/post` was built on OpenAI in MKT-TG-006). `fetchArticleText_` regularly fails on JS-rendered pages, thin pages, and paywalls — today that's a hard dead end. Owner wants to be able to paste article text directly instead of only a URL, and wants the digest itself to show a short UA summary per item (not just the bare English title) before deciding whether to draft.

## 3. Goal
1. Add a short Claude-written UA teaser (2-3 sentences) to each digest item.
2. Switch the digest's "Чернетка" full-post draft engine from Anthropic to OpenAI.
3. Add a text-paste fallback (for both `/post` and the digest button) that skips `fetchArticleText_` entirely when the owner supplies raw text instead of a URL — replacing today's dead-end failure.

## 4. What to change
- **Digest teaser** — in `newsDigest()` (~line 2912), after building `selected`, add one Anthropic call per item (short summarization job — Haiku is likely sufficient, confirm with owner) turning `item.title` + `item.description` into a 2-3 sentence UA teaser. Store it on `storedItem` (e.g. `storedItem.teaser`) so it survives in `newsLoadDraftItem_`.
- **Digest message** — in `newsBuildDigestMessage_()` (~line 3136), render the new UA teaser under each item's title/link line, in addition to (not replacing) the link.
- **Draft engine switch** — in `tgDraftPost_(item)` (~line 3205) / the `news_draft_` branch (~line 1732): replace the Anthropic call with an OpenAI call. Recommended: extract a shared function from `openaiDraftPostFromUrl_` (~line 3282), e.g. `openaiDraftPostFromText_(sourceLabel, articleText)`, that both the digest path and `/post` path call — `openaiDraftPostFromUrl_` becomes a thin wrapper (`fetchArticleText_` → `openaiDraftPostFromText_`). Keep the existing Anthropic path in the file, renamed/dormant (e.g. `tgDraftPostAnthropic_`), not deleted — for rollback/comparison.
- **Text-paste fallback** — shared by both entry points:
  - When `fetchArticleText_` returns empty (inside `openaiDraftPostFromUrl_`'s call for `/post`, and inside the new OpenAI-based digest draft call), instead of throwing straight to a dead end, put the chat into an "awaiting text" wait state (reuse the `CacheService` pattern from `tgBeginPostFromUrl_` / `tgHandleAwaitingPostUrl_`, new cache key e.g. `MKT_TG_007_TEXT_WAIT_`) and prompt: "Не вдалося зчитати статтю за посиланням. Надішли текст новини одним повідомленням, і я напишу пост." The next non-`/cancel` message is passed to `openaiDraftPostFromText_` directly, no fetch attempted.
  - In `tgHandleAwaitingPostUrl_` (~line 1696): today it rejects anything that isn't a URL. Change so a non-URL message is treated as pasted article text (→ `openaiDraftPostFromText_`) instead of rejected — covers `/post` (no args) → paste text directly, not only paste-a-link.
  - For the digest "Чернетка" button specifically: if extraction fails, key the "awaiting text" state to that `news_draft_<id>` item so the eventual pasted text is matched back to the right item (not the generic `/post` flow).
  - Do **not** add Telegram document/HTML-file upload handling. Raw HTML from "view source" would not contain JS-rendered content either — it does not fix the actual failure mode — and requires new `getFile`/download code for no real benefit over the owner pasting visible text they already have open.
- No new secrets — reuse existing `ANTHROPIC_API_KEY` and `OPENAI_API_KEY` Script Properties.

## 5. Do not touch
- `/orders`, `/digest` routing itself (only the digest *message content* changes), `tgCommandOrders_`, `tgShowOrderDetails_`, `tgCommandUpdate_`, `tgUpdateOrderStatus_`, all order-update callback branches.
- `doPost`, `doGet`, `apiAddSale_`, `apiAddPurchase_`, `apiAddWriteOff_`, `apiUpdateSale_`, `apiUpdatePurchase_`, and every other CRM read/write function — this file is a single shared monolith covering both CRM business logic and the Telegram bot. Scope is strictly the `news*` / `tg*Post*` / `tgDraftPost_` / `openaiDraftPostFromUrl_` functions named above.
- `setupNewsDigestTrigger()` and the daily time trigger.
- `ANTHROPIC_API_KEY` / `OPENAI_API_KEY` values themselves (reuse, don't rotate, don't print/log).
- Standard protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical tags, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema/JSON-LD. None of these are anywhere near this task; flagging per policy.

## 6. Likely files / areas
- `patches/MKT-TG-006_openai-url-draft_20260704.js` is the last canonical export available, but per the established pattern in this project the live source may have moved on since (owner syncs a Google Sheet `Apps_Script_код` tab manually) — Codex should verify against the actual live bound Apps Script / sheet tab before editing, not assume this patch file is still 1:1 current.
- Functions in scope: `newsDigest()`, `newsBuildDigestMessage_()`, `tgDraftPost_()`, `openaiDraftPostFromUrl_()`, `tgCommandPostFromUrl_()`, `tgBeginPostFromUrl_()`, `tgHandleAwaitingPostUrl_()`, the `news_draft_` branch inside `handleTelegramCallback_()`. `fetchArticleText_()` is reused read-only, no change expected.

## 7. Acceptance criteria
- `/digest` (or the scheduled run) shows, per item: game tag, a UA 2-3 sentence teaser, source link, image link (unchanged) — not just the bare English RSS title.
- Tapping "✍️ Чернетка" on a digest item returns a full UA post generated via OpenAI (`gpt-5.5`), not Anthropic.
- If extraction fails for a tapped digest item, the bot prompts to paste text instead of failing outright; pasted text produces an OpenAI draft with no fetch attempted.
- `/post <url>` is unchanged for URLs where extraction succeeds.
- `/post <url>` where extraction fails, and `/post` (no args) followed by pasted plain text, both produce an OpenAI draft from the pasted text instead of a dead-end error.
- `/orders`, `/digest` routing, the scheduled trigger, and all order-update flows behave exactly as before.

## 8. QA / smoke test
- [ ] `/digest` → each item shows a UA teaser, not just an English title.
- [ ] Tap "Чернетка" on an item with working extraction → OpenAI-style draft returned.
- [ ] Tap "Чернетка" on an item where extraction is known to fail → paste-text prompt appears → paste → OpenAI draft from pasted text, no fetch attempted.
- [ ] `/post <url with good extraction>` → unchanged (OpenAI draft).
- [ ] `/post <url with failing extraction>` → paste-text prompt (not a dead end) → paste → draft.
- [ ] `/post` (no args) → wait prompt → paste plain text (not a URL) → draft generated directly from that text.
- [ ] `/post` → wait prompt → paste a URL (existing behavior) → still fetches and drafts as before.
- [ ] `/cancel` cancels any waiting state (URL-wait and text-wait).
- [ ] `/orders`, `/digest` schedule, main menu, CRM read/write (`doPost`/`doGet` API actions, e.g. `sku_list` or `summary`) unaffected.
- [ ] `node --check` passes on the full file.
- [ ] Cost check after a few days: Anthropic (new teaser calls, ~5/day) + OpenAI (draft calls, on-demand) both stay in the low-cost range already established for this pipeline.

## 9. Rollback note
- Keep the previous bound Apps Script version in Apps Script version history (standard practice already used for MKT-TG-006) — restore that version to fully roll back.
- Locally, restore `patches/MKT-TG-006_openai-url-draft_20260704.js` as `Code.gs` if reverting from a local copy.
- No new Script Properties introduced (reuses existing `ANTHROPIC_API_KEY` / `OPENAI_API_KEY`), so no property cleanup needed on rollback.
- No Sheet schema or trigger changes, so no data rollback needed.

## 10. Recommended status after execution
MKT-TG-007 → "In progress" until the owner runs the QA checklist above against the live bot with a real fresh digest and at least one known-thin article, and confirms both the teaser text and the OpenAI draft quality are acceptable → then "Done".

This touches only the TG content-generation path (Anthropic/OpenAI outbound text calls), not checkout/payment/schema/feed — `bs-checkout-smoke` / `bs-merchant-schema-qa` / `bs-seo-risk-gate` do not apply.
