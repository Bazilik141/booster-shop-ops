# TG Content Automation — Phase 2 Plan (multi-source + review bot + images + schedule)

**Date:** 2026-06-27
**Builds on:** `handoffs/MKT-TG-003_make-pipeline-status_20260627.md` (pipeline fixed, chain proven).
**Goal:** 6 candidate posts/day (3 @ 10:00 + 3 @ 18:00) delivered to the owner's TG bot as **text + 2-3 image links** for manual review; owner posts 1-2/day to the group by hand. No auto-posting to the group.

---

## 1. Short conclusion
Realizable on the current stack. Split into two layers:
- **Make** = fetch + dedup + AI generation → writes candidates to a Google Sheet tab (does NOT post to group).
- **Bot** (existing CRM Apps Script) = review UI: `/pick_news`, `/delete_news`, dropdown via `setMyCommands`.
Store-backed candidates make the chat self-clean (no message accumulation), which also sidesteps Telegram's "can't delete messages older than ~48h" limit.

## 2. Task type
Mixed build: Make scenario (Claude), Google Sheet schema (Claude/Manual), Apps Script bot extension (Codex), Telegram setup (Manual), scheduling (Manual).

## 3. Owner / Claude / Codex / Manual split
- **Claude:** Make scenario rebuild, Sheet schema, image step, Codex handoff, review.
- **Codex:** Apps Script additions (`/pick_news`, `/delete_news`, `setMyCommands`, sheet reader).
- **Manual (owner):** set Script Properties (image API key if used), run `setMyCommands` once, set the two schedules, flip scenario to Active.

## 4. Status
Planning. Nothing built yet. Current Make scenario is Inactive with a temporary `Bundle order position = 1` test filter (must be replaced in Phase 1).

---

## 5. Architecture

```
[Make scenario "TG News"]                         [Google Sheet]              [CRM Apps Script bot]
 RSS (N feeds) → aggregate                         Tab: Новини_кандидати  ←──  /pick_news  → buttons
   → filter pubDate ≥ now-3d                         (one row per candidate)     → tap → post text + image links
   → exclude seen (Data Store)                     Tab/Store: seen guids       /delete_news → archive >3d rows
   → sort pubDate desc → take up to 3 accepted     (dedup)                     setMyCommands → "/" dropdown
   → per item: jina read → Claude → GPT SKIP
   → if kept: og:image + image search
   → WRITE candidate row + mark guid seen
 (no Telegram post)
```

Bridge = Google Sheet (fits existing Sheets+Apps Script stack). Dedup = Make **Data Store** keyed by `guid` (checked BEFORE jina, so duplicates cost 0 credits).

---

## 6. Phase 1 — Make: multi-source + dedup + candidate output

1. **Sources (RSS, Google News queries).** One scenario iterating a feed list. Starter set:
   - Pokémon TCG: `https://news.google.com/rss/search?q=pokemon+tcg+-pocket&hl=en&gl=US&ceid=US:en`
   - Pokémon TCG Pocket (mobile): `q=pokemon+tcg+pocket`
   - One Piece Card Game: `q=%22one+piece+card+game%22`
   - One Piece (anime/universe, curated): `q=one+piece+anime`
   - Magic: The Gathering: `q=magic+the+gathering+tcg`
   - Yu-Gi-Oh!: `q=yu-gi-oh+tcg`
   - (YGO direct feed already vetted: `https://www.ygorganization.com/feed/`)
   Store the list in an Iterator/array; tag each item with its `game/source`.
2. **Aggregate** all feeds' items into one array (Array aggregator) with fields: title, link, guid, pubDate, source_tag.
3. **Freshness filter:** keep `pubDate ≥ addDays(now;-3)`. Use `parseDate(pubDate; "ddd, DD MMM YYYY HH:mm:ss")` if the raw-string compare is unreliable (see Risks).
4. **Dedup:** Data Store `tg_news_seen` keyed by `guid` (or normalized URL). Skip items already present. Also dedup same-title cross-source.
5. **Sort** remaining by `pubDate` desc.
6. **Select up to 3 accepted:** process top items in order → jina → Claude → GPT SKIP; count accepted (non-SKIP); stop at 3 or when items run out. (If a simple "take 3 then SKIP" is easier in Make, accept 1-3/run — owner only needs 1-2.)
7. **Write candidate** row to `Новини_кандидати` + **mark guid seen** in Data Store. Do NOT post to Telegram. (Remove/disable the Telegram "Send Message" module, or branch it off.)

## 7. Phase 1b — Images (per kept candidate)
- **Primary (free, on-topic):** fetch article `og:image` — HTTP GET the source URL → regex `og:image`. 1 image.
- **Secondary (2 more):** Google Programmable Search (Custom Search JSON API), image mode, query = post subject keywords (card/set/product name). ~6 queries/day → free tier.
- Write `image1..image3` URLs into the candidate row.
- **Copyright note:** auto-found images may be copyrighted; the owner's manual pick is the safeguard. Prefer the store's own photos where possible.

## 8. Phase 2 — Bot (CRM Apps Script additions)
Grounded in existing functions (`handleTelegramUpdate_`, `handleTelegramCallback_`, `tgShowMainMenu_`, `tgSendMessage_`, `tgEditMessage_`, `tgBotApi_`, `tgIsAllowedChat_`).

- **Command routing** (in `handleTelegramUpdate_`, after the allowed-chat check):
  `if (text.indexOf('/pick_news') === 0) { tgCommandNews_(chatId); return; }`
  `if (text.indexOf('/delete_news') === 0) { tgCleanNews_(chatId); return; }`
- **Main menu** (`tgShowMainMenu_`): add buttons `{text:'Новини', callback_data:'news_list'}` and `{text:'Очистити новини', callback_data:'news_clean'}`.
- **Callbacks** (`handleTelegramCallback_`): `news_list` → `tgCommandNews_`; `news_pick_<id>` → `tgShowNewsPost_`; `news_clean` → `tgCleanNews_`.
- **New functions:**
  - `crmGetNewsCandidates_(maxDays=3)` — read `Новини_кандидати`, rows with status `new` and created within 3 days; return `{id,title,game,post_text,urls[]}`.
  - `tgCommandNews_(chatId, messageId)` — one menu message: buttons `[{text: game+' · '+shortTitle, callback_data:'news_pick_'+id}]` + "Назад". Mirrors `tgCommandOrders_` exactly.
  - `tgShowNewsPost_(chatId, id)` — send the full post as a normal message (long-press → Copy), then a second message with the 2-3 image links; optional inline button to mark `status=posted`.
  - `tgCleanNews_(chatId)` — set `status=archived` for rows older than 3 days (does NOT delete chat messages). Reports count.
- **Dropdown commands** (`setMyCommands`): one-time setup function
  `tgSetupCommands_()` → `tgBotApi_('setMyCommands', {commands:[{command:'pick_news',description:'Підібрані пости'},{command:'delete_news',description:'Очистити старі'}], scope:{type:'chat', chat_id:<ALLOWED>}})`.
  (Order command stays via menu; add `start`/`orders` here too if desired.)

### Telegram constraints baked into the design
- **No clipboard write.** `copy_text` button caps at 256 chars → can't hold a full post. Post is delivered as a normal message; native long-press Copy. (Optional: a `copy_text` button for the source URL only.)
- **No deleting >48h messages.** `/delete_news` archives sheet rows, not chat messages. Chat stays clean because candidates are buttons in one refreshed menu, not a flood of messages.

## 9. Phase 3 — Scheduling
- Make scenario schedule: two daily runs **10:00** and **18:00** (Make advanced scheduling supports multiple times; otherwise clone the scenario).
- Activate only after a dry-run confirms: dedup works, ≤3 rows/run, candidates land in the sheet, bot lists them.

---

## 10. Sheet schema — tab `Новини_кандидати`
| col | field | notes |
|-----|-------|-------|
| A | id | e.g. `NEWS-0001` |
| B | created_at | timestamp |
| C | game | Pokémon / One Piece / MTG / YGO / Other |
| D | title | short, for the button |
| E | post_text | final UA post (Claude+GPT) |
| F | source_url | original article |
| G | image1 | og:image |
| H | image2 | search |
| I | image3 | search |
| J | status | new / posted / archived |
| K | guid | dedup key (mirror of Data Store) |

## 11. Codex handoff (Apps Script part)
- Scope: add `/pick_news`, `/delete_news`, menu buttons, callbacks, the 4 new functions, `tgSetupCommands_`, and a `Новини_кандидати` reader. Do NOT touch CRM/order logic or `doPost` API branch.
- Reuse existing helpers (`tgSendMessage_`, `tgEditMessage_`, `tgBotApi_`, `tgIsAllowedChat_`, `tgEscapeHtml_`).
- Respect the existing callback-data prefix style (`news_pick_` like `order_sel_`).
- Token/secrets: keep using Script Properties; never hardcode.

## 12. QA checklist
- [ ] Make dry-run (Telegram off): ≤3 candidate rows written, correct game tags, fresh only.
- [ ] Dedup: re-run immediately → 0 new rows (all seen).
- [ ] Cross-source dup (same news in 2 feeds) → only 1 row.
- [ ] Images: each row has 1-3 working URLs.
- [ ] Bot: `/pick_news` lists candidates as buttons; tap → post text + image links arrive; chat stays a single menu.
- [ ] `/delete_news` archives >3d rows; chat not broken.
- [ ] `setMyCommands`: "/" dropdown shows the commands in the private chat.
- [ ] Order functions (`/start`, Активні замовлення, update flows) still work unchanged.
- [ ] Two schedules fire at 10:00 and 18:00.

## 13. Risks
- **pubDate parsing:** Make datetime "Later than" on the raw RFC822 array may pass 0; wrap with `parseDate(...)` and verify. (Open item from Phase 1.)
- **Google News ordering** is relevance, not date → must sort by pubDate for "newest". (Handled in Phase 1 step 5.)
- **GPT SKIP** can yield <3/run — acceptable (owner posts 1-2).
- **Image copyright** — manual pick mitigates; flag for owner awareness.
- **Image search API cost/limits** — stay within free tier; monitor.
- **Make Data Store growth** — periodically prune old guids (e.g. >30d).
- **Editing the live CRM script** — Codex change is additive; regression risk to order bot is the main thing to QA.

## 14. Open decisions for owner
1. Image search provider: Google Programmable Search (recommended) vs og:image-only (free, 1 img) vs paid (SerpAPI).
2. Dedup home: Make Data Store (recommended) vs a Sheet "seen" tab.
3. Posts per run hard cap: exactly 3, or "up to 3"?
4. Keep GPT SKIP pre-filter or rely fully on manual filter?
