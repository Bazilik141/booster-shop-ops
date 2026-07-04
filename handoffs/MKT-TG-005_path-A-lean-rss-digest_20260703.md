# Handoff: MKT-TG-005 — Path A (lean RSS→Telegram news digest; replaces the Make pipeline)

**For:** a fresh Claude chat continuing this task (previous chat got very long).
**Date:** 2026-07-03 · **Owner:** Raccoon (14bezlikiy14@gmail.com) · **Lang for content:** Ukrainian.

---

## 1. Goal (the actual job, stripped of implementation)
Booster Shop = Ukrainian online TCG store (Pokémon, One Piece, MTG, Yu-Gi-Oh!).
Owner wants a **daily flow that surfaces fresh, on-topic TCG news** as candidate Telegram
posts, delivered to the owner's private bot chat, so the owner **picks 1-2/day and posts to
the group manually** (with edits). **No auto-posting to the group.**

## 2. What we built before and WHY WE'RE ABANDONING IT (MKT-TG-003/004)
Make.com scenario "Integration RSS": Google News RSS → Iterator → jina (read full article) →
Claude (write UA post) → OpenAI (turned out UNUSED) → Serper (image search) → write candidate
row to Google Sheet `Новини_кандидати` → Telegram bot (`/pick_news`) lists candidates.

**Why abandoned (owner is unhappy — justified):**
- **Cost:** it AI-rewrites ALL ~5 items/day, but owner posts 1-2 and edits anyway → paying for
  3-4 wasted drafts. Real ≈ $5-10/mo; today spiked to ~$1 (mostly test runs). Target was ~$2/mo.
- **Images off-topic:** Serper searches by article title → for anime franchises results 2-3
  drift (One Piece post pulled **Naruto** for image 2/3).
- **Freshness unreliable:** Make can't parse RSS `pubDate` (RFC822 string / array) → no working
  date filter; selection fell back to relevance/position → stale items possible.
- **Too fragile:** Make + jina + Claude + OpenAI + Serper + Sheet + bot; Make canvas painful to
  edit remotely.

**Owner decision: switch to Path A (below).**

## 3. Path A — target design (BUILD THIS)
**Core idea:** automate only the *delivery of fresh, on-topic headlines*. Do the *writing
on-demand* (owner taps a headline → 1 AI draft), NOT in batch. This kills the cost + the
image + the freshness problems at once, with the fewest parts.

**A) Daily digest job** (time trigger ~10:00 Europe/Kyiv), pure code (no Make):
1. Fetch 4 Google News RSS feeds (free `UrlFetch`):
   `https://news.google.com/rss/search?q=<Q>&hl=en&gl=US&ceid=US:en`
   - Pokémon TCG: `%22pokemon+tcg%22`
   - One Piece CG: `%22one+piece+card+game%22`
   - MTG/YGO: `%28%22magic+the+gathering%22+OR+%22yu-gi-oh%22%29`
   - TCG market/industry: `%28TCG+OR+%22trading+card+game%22%29+%28market+OR+industry%29`
2. Parse each item's `<pubDate>` with a real `new Date()` → **reliable freshness**, keep last
   ~3 days. Dedup by `<guid>`/link against a small "seen" store (a Sheet tab or Script cache).
3. Pick freshest per source: **1 Pokémon + 1 One Piece + 1 MTG/YGO + 2 TCG-market = 5** (tunable).
4. For each item, get the article's **own `og:image`** (fetch article HTML, regex
   `<meta property="og:image" content="...">`) → on-topic by definition (**fixes the Naruto
   problem**). Fallback: no image.
5. Send ONE digest message to the owner's bot chat: per item → game tag + title + link +
   og:image, each with an inline button **«✍️ Чернетка»** (draft this).

**B) On-demand draft** (owner taps «Чернетка» on an item):
- Bot calls Anthropic API **once** for that article → returns a UA post draft (reuse the proven
  system prompt in §6). Owner copies/edits/posts to the group manually.
- AI cost = only for tapped drafts (~1-2/day) → **< $1/mo** (Haiku even less).

**Cost of Path A:** digest job = free (RSS + og:image + Telegram are free UrlFetch). Only paid
part = on-demand drafts. **Near the original ~$2/mo budget.**

## 4. Where to host it (reuse the existing bot)
The Telegram bot ALREADY EXISTS inside the **CRM Google Apps Script** (single `doPost` Web App
handling both a token API and Telegram updates). Because that script **owns the Telegram
webhook**, the «Чернетка» button callbacks MUST be handled there. So the pragmatic choice:
**add the Path A functions (daily digest via time-trigger + draft-callback + Anthropic call)
into the CRM Apps Script**, reusing its Telegram helpers. Keep them lean/isolated (few funcs).
- Existing helpers to reuse: `tgSendMessage_`, `tgEditMessage_`, `tgBotApi_`,
  `tgAnswerCallback_`, `tgIsAllowedChat_`, `tgEscapeHtml_`, `tgShowMainMenu_`.
- Existing bot commands: `/start`, `/pick_news`, `/delete_news`, `setMyCommands`.
- Secrets already in Script Properties: `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALLOWED_CHAT_ID`.
  **ADD:** `ANTHROPIC_API_KEY` (owner has a key; API workspace "Telegram-BoosterShop-GroupNews").
- Note: owner said the CRM script is bloated (CRM migrating to Supabase). Keep Path A functions
  minimal and clearly namespaced (e.g., `newsDigest_`, `tgDraftPost_`), so they're easy to move
  later. A fully separate script can't own the webhook, so all-in-CRM is simplest.

Alternative if owner objects to touching CRM: a separate Apps Script/GitHub Action generates
the digest and posts it, but the button-callback + draft logic still must live in the
webhook-owning script. Confirm with owner.

## 5. Anthropic API call (on-demand draft)
- Endpoint: `POST https://api.anthropic.com/v1/messages` via `UrlFetchApp`.
- Headers: `x-api-key: <ANTHROPIC_API_KEY>`, `anthropic-version: 2023-06-01`, `content-type: application/json`.
- Model: **start with `claude-haiku-4-5-20251001`** (cheap; good enough for short TG posts).
  Upgrade to `claude-sonnet-4-6` only if quality demands. `max_tokens` ~500, `temperature` ~1.
- Context for the draft: to stay cheap, start with **RSS title + description** (short) rather
  than fetching the full article. If drafts feel thin, upgrade to fetch+strip the article body.
  (The old Make design fed the FULL article via jina — that was the main token sink; avoid it.)

## 6. Reusable Claude system prompt (proven, from the old Make module — KEEP IT)
```
Ти редактор Telegram-групи магазину колекційних карт Booster Shop. Пиши лише українською, без русизмів.

Якщо текст про одного випадкового користувача Reddit — пиши про явище або феномен, який він ілюструє, не про самого користувача.

Формат: 3-4 абзаци, 100-180 слів, 2-4 емодзі як акценти в тексті. Без markdown, без реклами магазину, без вигаданих деталей. Не починай з "На Reddit", "Хтось", "Якийсь".

Короткі речення. Максимум 2 речення на абзац.
Не пояснюй явище повністю — зачіпляй думку і залишай читача думати.
Якщо є конкретні деталі (назва карти, ціна, дата) — обов'язково використовуй їх. Не узагальнюй.
```
(There was also a longer "avoid Wikipedia style" example + a sample good post — pull the full
text from the old Make blueprint module `id:3` in `Booster Shop/Integration RSS.blueprint.v2.json`
→ `flow[].mapper.system` if you want it verbatim.)

User-message pattern (draft on tap): 
`Ось стаття з RSS:\nЗаголовок: <title>\nОпис: <rss description>\nДжерело: <link>\n\nНапиши пост для Telegram-групи українського TCG магазину.`

## 7. Decisions to CONFIRM with owner (in the new chat, before coding much)
1. Host = add to CRM Apps Script (recommended) vs separate. 
2. Draft model = Haiku (cheap) vs Sonnet (quality).
3. Draft context = RSS description only (cheap) vs fetch full article.
4. Freshness window (default: last 3 days) and per-source counts (default 1/1/1/2 = 5).
5. Digest title: keep English article title + game tag (free) — a UA short tag would cost a
   tiny AI call per item; probably skip for cost.
6. Dedup store: a `seen_guids` Sheet tab vs Script cache/properties.

## 8. Immediate action
- **Deactivate the current Make scenario** "Integration RSS"
  (`https://eu1.make.com/1992541/scenarios/6274228`) to stop token burn while migrating.
  (Owner can flip the Active toggle, or Claude via Chrome.)
- Leave the Apps Script bot running (still works); its `/pick_news` reads the old
  `Новини_кандидати` sheet and will be superseded.

## 9. Repo / files / IDs
- Repo: `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops` (GitHub `Bazilik141/booster-shop-ops`, `master`).
- This handoff: `handoffs/MKT-TG-005_path-A-lean-rss-digest_20260703.md`.
- Prior context: `handoffs/MKT-TG-004_per-game-balance_20260627.md` (old Make v2 design +
  cost analysis + the images/freshness backlog with ready fixes), `handoffs/MKT-TG-003_*`,
  `plans/tg-content-automation-phase2-plan_2026-06-27.md`.
- Old Make blueprint (SECRETS — NOT in git): `Booster Shop/Integration RSS.blueprint.v2.json`
  (contains the system prompt, the 4 queries, Serper key, AS token — reference only).
- Sheet: `Новини_кандидати` (id|created_at|game|title|post_text|source_url|image1|image2|image3|status|guid).
- CRM Apps Script source (reference export): CSV `Booster Shop CRM — облік товарів - Apps_Script_код.csv`
  in the parent "Booster Shop" folder.
- Roadmap: dashboard `dashboard/booster-dashboard.html` (mirror) + active
  `C:\Users\14bez\Downloads\Booster Shop\booster-dashboard.html`. Notion card MKT-TG-004
  page_id `38c6bf20-bdb4-8145-b9e6-d1bebf8636ef` (Booster Shop Roadmap, collection
  `5aef22c3-048d-4dde-a5b1-ad409de9301c`). Consider a new card **MKT-TG-005** for Path A, and
  mark the Make approach superseded.

## 10. Project rules / constraints
- UA content; **no invented facts/prices/specs/GTIN**; no keyword stuffing; no fake reviews.
- Secrets (`TELEGRAM_BOT_TOKEN`, `ANTHROPIC_API_KEY`) live in Apps Script **Script Properties**
  — never expose in chat/logs/commits.
- Cost-conscious: near-free target; AI only on-demand.
- git commits: repo has a recurring **stale-lock** issue from an autosync script; the sandbox
  can't remove `.git/index.lock`/`.autosync-pause` (Windows perms). Owner clears them in
  PowerShell: `Remove-Item .git\index.lock,.autosync-pause -Force -ErrorAction SilentlyContinue`
  then `git add … ; git commit -m … ; git push origin master`.

## 11. Suggested first steps in the new chat
1. Confirm §7 decisions (quick).
2. Deactivate the Make scenario (§8).
3. Write the Apps Script functions: `newsDigest_()` (time-trigger daily) + a `news_draft_<guid>`
   callback branch + `tgDraftPost_(item)` (Anthropic call) + `fetchOgImage_(url)` +
   `parseRssItems_(xml)` + dedup store. Test with one manual run; verify a digest lands in the
   bot with real og:images, then tap a draft.
4. Update roadmap (dashboard + Notion), commit.
