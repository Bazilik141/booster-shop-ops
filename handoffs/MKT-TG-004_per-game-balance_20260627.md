# MKT-TG-004 — Per-game balance rebuild (feed section)

Date: 2026-06-27 · Scenario: "Integration RSS" (eu1.make.com/1992541/scenarios/6274228)

## Problem
Single combined OR-query + `position ≤ 5` filter → top-5 by Google News **relevance**.
Whichever game dominates the news cycle takes all slots. Observed: 7 candidates, ~all MTG
(Marvel Super Heroes set launch + Prime Day deals wave). Structural, not test-only.

## Decision (owner)
**Per-game queries, top-2 each.** 4 games (Pokémon TCG, One Piece CG, MTG, Yu-Gi-Oh!),
2 freshest-relevant each → ~6-8 balanced candidates/run. A game with no news that day → 0 from it.

## Target architecture (feed section only; rest of chain UNCHANGED)
```
[query source: 4 game queries]
  → Iterator (per query)
    → HTTP (dynamic URL, per query)
      → XML parse
        → Iterator (items)
          → Filter  position ≤ 2     ← (was ≤ 5)
            → jina(11) → Claude(3) → OpenAI(17) → Serper(25) → Write(26)   ← unchanged
```

Key insight: with HTTP+XML+Iterator running **per query**, `Bundle order position` resets per
game, so `position ≤ 2` = top-2 **per game** = balanced. Only the existing filter value changes
(5 → 2); the new part is the query loop in front of HTTP.

## Build steps (live, ~Make UI)
1. **Query source.** Add an Iterator as the entry whose Array = the 4 queries:
   `{{split("%22pokemon+tcg%22|%22one+piece+card+game%22|%22magic+the+gathering%22|%22yu-gi-oh%22"; "|")}}`
   Output element ref = `{{<id>.value}}`.
   - NOTE: Make requires the **first** module to be trigger-capable; Iterator may be rejected as
     first. If so, keep current **HTTP(19) as trigger** and instead drive the loop by making HTTP
     fetch one query and prepending the iterator is blocked → fallback below.
   - **Fallback (definitely valid):** keep HTTP(19) first, but change scenario so HTTP is fed by a
     preceding Iterator only if Make allows a non-trigger first module (test with "Run once").
     If blocked, use a trivial first trigger (e.g. existing HTTP repurposed) → Iterator → HTTP.
2. **HTTP URL** (module 19) → dynamic:
   `https://news.google.com/rss/search?q={{<iteratorId>.value}}&hl=en&gl=US&ceid=US:en`
3. **Item filter** (Iterator 20 → jina): change `Bundle order position` from `≤ 5` to `≤ 2`.
4. (Optional, near-free here) Set Write `Game` field = current game label (since it's known per
   query) → shows game in /pick_news. Owner picked balance-only, so optional.
5. **Test:** Run once → expect ~6-8 bundles reaching Write, mix of all 4 games; verify dedup +
   images still populate (`25.data.images[N].imageUrl`).

## Revert safety
If unstable, restore the single filter to `position ≤ 5` (current working state) so the live
scheduled runs keep producing candidates (MTG-heavy but functional).

## Status
Pipeline LIVE (activated). Canvas surgery proved unreliable remotely → switched to BLUEPRINT
edit/import (`Booster Shop/Integration RSS.blueprint.v2.json`).

## v2 (final, owner-chosen) — applied via blueprint import
- **5 candidates/run, 1×/day @ 10:00** (was 2×/day top-5): 1 Pokémon + 1 One Piece +
  1 MTG/YGO + 2 TCG-market/industry. Cost cut comes from 1 run/day + fewer items (keep Sonnet).
- Query iterator (M30, split 4 queries) → per-query HTTP (M31) → XML(31.data) → items iter(20).
- Filter M11: `(query≤3 AND item≤1) OR (query=4 AND item≤2)` = 1/1/1/2.
- **Title in TG button** = `«<ККГ> · <2-3-word UA tag>»`: Claude prompt now emits
  `тег\n===\npost`; Write `title = switch(game) · split[1]`, `post_text = split[2]`.
  (No `game` column added to Data Structure — folded into title.)
- Game labels by query index: 1 Pokémon TCG / 2 One Piece / 3 MTG/YGO / 4 TCG-ринок.
- OpenAI(17) kept (unused, owner's call to keep for now).
- Schedule must be reset to **Daily 10:00 only** + re-activate after import.

## BACKUP cost option (owner-flagged, not applied)
If cost still too high: switch Claude Sonnet→Haiku (claude-haiku-4-5) AND let OpenAI(17)
polish Claude's draft (repoint `post_text` → `{{17...}}`), so OpenAI stops being dead weight.
Est. ~$2-3/mo. Quality slightly simpler. Apply only if needed.

## Token-cost finding (2026-06)
Today's ~$1 = ~8-10 of MY test runs (full article → Claude per item, ~8K in/item, Sonnet).
Prod on old config (2×/day, top-5) ≈ $10/mo. v2 (1×/day, 5 items) ≈ ~$5/mo. Images (Serper)
are NOT a token cost. Original "$2/mo" estimate was low (underweighted full-article input).

## Follow-ups — BACKLOG (owner-flagged, process later; NOT applied)
v2 imported & ran (2026-06-28) — works. Two refinements deferred:

1. **Images 2/3 off-topic.** Serper searches `q = {{20.title}}` → for anime franchises
   results 2-3 drift (One Piece post pulled Naruto for img2/img3). Image 1 usually fine.
   **READY FIX** — constrain Serper q with the game (module 25 `/images`, body field `q`):
   `{{switch(30.`__IMTINDEX__`; 1; "Pokemon TCG"; 2; "One Piece card game"; 3; "trading card game"; 4; "trading card game")}} {{20.title}}`
   (30 = query-iterator, upstream of 25, so its index is in scope.)
   Stretch option: image1 = article's own og:image (always on-topic) + Serper for 2/3.
2. **Post freshness.** Per-game selection is by relevance/position — NO date filter
   (Make pubDate filter unreliable: RFC822 string / array won't compare). Stale articles
   can surface. Options to evaluate: (a) `parseDate(pubDate)` filter, (b) tighten Google
   News query with recency, (c) aggregate+sort items by date before top-N. Decide later.
