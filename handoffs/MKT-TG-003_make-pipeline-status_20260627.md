# MKT-TG-003 — Make.com Telegram Pipeline — STATUS UPDATE (2026-06-27)

**Scenario:** "Integration RSS" — Make.com eu1, team 1992541, scenario id 6274228
**Status:** Pipeline FIXED + tested end-to-end (1 live post sent OK). Scenario still **Inactive** (not scheduled). Production volume/dedup/schedule = next phase.
**Prev handoff:** `MKT-TG-003_make-pipeline-handoff_20260626.md`

---

## What was broken vs what was fixed

### 1. Iterator returned empty array — FIXED
- **Root cause:** Make's XML parser wraps `<channel>` as an **array**, not an object. Mapping `{{21.rss.channel.item}}` (no array index on `channel`) resolved to empty.
- **Real structure (confirmed by inspecting XML module output):**
  `21.rss` → `channel[]` (array, 1 element) → `[1]` → `item[]` (array, ~100 items)
  Each item: `title[]`, `link[]`, `guid[]`, `pubDate[]`, `description[]`, `source[]` (all arrays).
- **Fix — Iterator (module 20) Array field:**
  `{{21.rss.channel[1].item}}`
- **Verified:** Iterator now emits ~100 item bundles ("Total number of bundles: 100").

### 2. jina.ai URL field — FIXED
- **Field "URL to Read" (module 11):** `{{20.link}}`
- `link[1]` holds the article URL directly (Google News redirect `https://news.google.com/rss/articles/CBMi...`). jina follows the redirect and returns the article text.

### 3. Claude "Create a Prompt" content mappings — FIXED (incl. a hidden bug)
- **Old (broken):**
  `Заголовок: {{20.value.title}}` · `Текст: {{11.content}}` · `Джерело: {{20.value.link}}`
- **New (correct):**
  `Заголовок: {{20.title}}` · `Текст: {{11.response}}` · `Джерело: {{20.link}}`
- **Two bugs:** (a) the bogus `.value.` prefix; (b) **jina's output field is `response`, not `content`** — so `{{11.content}}` fed Claude an EMPTY article body. Now `{{11.response}}`.
- System prompt (ToV v2), Model `claude-sonnet-4-6`, Max Tokens `500`, Temperature `1` — unchanged / preserved.

### 4. Date filter (Iterator → jina) — present, but see caveat
- Filter on the Iterator→jina route: `pubDate` **Later than** `addDays(now; -3)` (last 3 days).
- **CAVEAT discovered during test:** Google News RSS is ordered by **relevance, not date**. So the date filter alone can pass many or few items unpredictably, and "item position 1" is NOT necessarily the newest. This matters for the production "newest N" design (see below).

---

## End-to-end test result (1 post, owner-approved)

Temporary test limit added (`Bundle order position = 1`) → ran full chain once:
HTTP ✓ → XML ✓ → Iterator ✓ → filter(1) → jina ✓ (read article) → Claude ✓ → OpenAI ✓ (passed SKIP) → Telegram ✓ (**1 post sent**).

**Generated post quality (sample):** Ukrainian, clean, TCG terms kept in English (Mega Moonlit Gengar ex Tin, Chaos Rising, Perfect Order, Phantasmal Flames, TCG Live), concrete prices ($42.99 / $49.95 / $10.75), 2 emoji accents, correct 3-paragraph format. Article details from jina were used → mapping chain confirmed working.

---

## Current filter state (IMPORTANT — interim)

The route filter Iterator→jina is currently set to a **temporary test limit**: `Bundle order position = 1` (the date condition was removed for the 1-post test). This is safe for an inactive scenario (1 post per manual run, no spam) but it is **not** the production logic. Restore/redesign before activating.

---

## Production TODO (next phase — not yet built)

Target: **6 posts/day = 3 at 10:00 + 3 at 18:00** (3 posts per run, 2 scheduled runs/day).

1. **Freshness + "newest" selection.** Because Google News RSS is relevance-ordered, position-based limiting won't reliably give the newest items. Options: sort items by `pubDate` desc before limiting, and/or keep the `addDays(now;-3)` date filter as a freshness gate. Decide approach.
2. **Limit to 3 per run.** After sort/filter, cap at 3 (e.g. a counter/position condition on the sorted set, or an Array aggregator + slice).
3. **Deduplication (required for any schedule).** Without dedup, every run re-posts the same top items. Build a Make **Data Store** keyed by article `guid`/`link`; before jina, check "not already posted"; after Telegram, write the guid. This is the core build item.
4. **Two schedules.** Set scenario schedule to run at 10:00 and 18:00 (Make supports multiple times in "Advanced" scheduling, or two clones).
5. **Activate** only after 1–4 are verified with a dry-run.

## After pipeline (from prev handoff, still pending)
- Add YGO scenario (source `https://www.ygorganization.com/feed/`).
- Add One Piece TCG scenario (source TBD).
- Phase 2: photo support (og:image from jina → Router: Send Photo / Send Text).

## Files
- `plans/tg-tone-of-voice.md` — ToV v2
- `plans/tg-content-automation-plan_2026-06-18.md` — full plan
