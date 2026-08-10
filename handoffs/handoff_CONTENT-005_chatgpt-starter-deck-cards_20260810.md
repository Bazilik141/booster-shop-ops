# Brief for ChatGPT — CONTENT-005: five One Piece starter-deck product cards

Date: 2026-08-10 | Task: `CONTENT-005` | Notion: `3b86bf20-bdb4-81d6-acad-dc7d32b55500`
Addressed to: **ChatGPT**, working from this document alone. Self-contained on purpose — do not
assume access to the Booster Shop repository, CRM or site.

Owner workflow after you deliver: the owner passes your draft to Claude for review
(`bs-content-qa`), then Claude Code builds one OpenCart patch, then the owner deploys. **Nothing you
write is published directly**, so flag doubt rather than smoothing over it.

**OUTPUT LANGUAGE: Ukrainian.** This brief is in English; every deliverable string — H1, meta, body
copy, attribute values — must be natural Ukrainian. Product names, set codes and character names keep
their standard Latin/Japanese forms where that is how players actually write them.

---

## 1. Target pages

Five new OpenCart product pages on `boostershop.website`, one per starter deck. One page per deck —
never a single combined page.

| SKU | Deck | Character | Box colour |
|---|---|---|---|
| `OP-JP-ST32-STD` | ST-32 | ロロノア・ゾロ — Roronoa Zoro | green |
| `OP-JP-ST33-STD` | ST-33 | クザン — Kuzan | blue |
| `OP-JP-ST34-STD` | ST-34 | シャーロット・カタクリ — Charlotte Katakuri | purple |
| `OP-JP-ST35-STD` | ST-35 | サボ — Sabo | black |
| `OP-JP-ST36-STD` | ST-36 | ユースタス・キッド — Eustass Kid | yellow |

## 2. Intent

Transactional. The visitor already knows what a One Piece Card Game starter deck is, or is one step
away from buying their first one.

## 3. Audience

Ukrainian TCG buyers, mixed knowledge: some are experienced One Piece players choosing a colour,
some are complete beginners buying a first deck or a gift. Write so both are served — no jargon
without a short plain explanation, no talking down to players.

## 4. Tone

Booster Shop voice: clean, factual, trust-first. A curated shop, not a marketplace. No hype, no
exclamation marks, no "неймовірний шанс", no artificial urgency.

## 5. Primary keyword — one per page, no overlap

Each page gets exactly **one** primary keyword, built around its own deck code and character, so the
five pages never compete with each other:

- `стартова колода One Piece ST-32 Зоро`
- `стартова колода One Piece ST-33 Кузан`
- `стартова колода One Piece ST-34 Катакурі`
- `стартова колода One Piece ST-35 Сабо`
- `стартова колода One Piece ST-36 Кід`

Placement: exactly once in the H1, one to two times in the body. Nowhere else. If it does not fit
naturally in a sentence, leave it out of that sentence — do not bend the grammar around it.

## 6. Secondary keywords / entities — 3-7 per page, natural mentions only

Draw from: `One Piece Card Game`, `ONE PIECE カードゲーム`, `starter deck`, `японське видання`,
`Bandai`, the character's full name, the deck's colour in game terms, `ККГ`, `TCG`, `колода для
початківців`. Use only those that fit the sentence you are already writing.

## 7. Facts available — verified, use freely

From the owner's photograph of the physical lot and the printed boxes:

- Five different starter decks, codes ST-32 through ST-36, characters and colours as in §1.
- Publisher marking on the boxes: **BANDAI NAMCO**. Age marking: **対象年齢9才以上** — 9 years and up.
- Japanese edition. Product is new and sealed.
- The same lot also contains one OP-16 `決戦の刻` booster box; its box states 全126種+1種,
  1パック6枚入り, 24パック入り (126 + 1 card types, 6 cards per pack, 24 packs per box).
  **Context only — the OP-16 box already has its own live page and is out of scope. Do not write
  about it and do not link the deck pages to it without saying so explicitly.**
- Retail price: **₴700** per deck.
- Launch status: **передзамовлення** — the lot is ordered and not yet in Ukraine.

## 8. Facts missing — research them yourself, with sources

The owner has agreed that you research these. **Every one of them must arrive with a source URL, and
anything you cannot source must be returned as `ФАКТ НЕ ПІДТВЕРДЖЕНО` rather than written into the
copy.** Claude will check each claim against your sources at review; an unsourced specification is a
blocker, not a rough edge.

1. Exact card count per deck, and whether Don!! cards are included and how many.
2. The leader card of each deck — name and colour.
3. Official deck name in Japanese and in English for each of ST-32…ST-36.
4. Japanese release date, and whether all five launched simultaneously.
5. Whether any deck contains an exclusive, alternate-art or parallel card not obtainable elsewhere.
6. Whether the deck is playable out of the box without additional cards.
7. Anything printed on the box that a buyer would reasonably expect on the page.

Prefer primary sources: the official Bandai One Piece Card Game site and official product pages. A
fan wiki is acceptable as a secondary source **if labelled as such**.

## 9. Structure — deliver this block, five times, once per SKU

Repeat exactly this shape for each deck. Deliver as plain Markdown in the chat, not as a file.

```
# <SKU>
# <deck code> — <character>

## SEO-поля

**SEO URL (проєкт, Claude підтверджує):**
One-Piece-Starter-Deck-ST-32-Roronoa-Zoro
  — human-readable, Latin, hyphenated. NEVER put the SKU in the URL.

**Meta Title (≤ 63 chars, ends with `| Booster Shop`):**

**Meta Description (≤ 158 chars):**

**Meta Keywords:**

## Назва товару (H1)

## Опис — HTML
  <h2> + 3-5 <p>, optional one <ul>. Allowed tags: h2, h3, p, strong, ul, li, br.
  No inline styles, no classes, no <script>, no <img>, no JSON-LD.

## Характеристики (атрибути OpenCart)

| Атрибут | Значення |
|---------|----------|
| Назва сету |  |
| Мова | Японська (Japanese) |
| Тип пакування | Starter Deck |
| Кількість карток у колоді |  |
| Лідер колоди |  |
| Стан | Новий, нерозпакований (Sealed) |
| Виробник | Bandai |
| Рік випуску |  |
| Вікове маркування | 9+ |
| Додатковий вміст |  |

## Джерела
  Numbered list of the URLs backing every researched fact above.

## Невирішене
  Everything you could not confirm, phrased as a question to the owner.
```

Keep the attribute rows identical across all five decks so the site tables line up. An attribute you
could not source stays empty with a note in `Невирішене` — never guessed.

## 10. CTA

One only, at the end of the description: an invitation to preorder. Plain wording, no countdown, no
scarcity claim. Do not promise a delivery date.

## 11. Internal links — 2-5 per page

Propose them as anchor text plus a description of the destination; **do not invent URLs**. Sensible
destinations: the One Piece category, another starter deck from this set, One Piece boosters. Claude
supplies the real slugs at review — the site's live URL list is not in this brief.

## 12. Image direction

The owner photographs the physical product. You provide only the alt text: Ukrainian, descriptive,
naming the deck code and character, no keyword stuffing. One alt per deck, plus one for a
back-of-box shot.

## 13. Schema

**Do not write any JSON-LD.** Product schema is generated at the patch stage and audited separately.
For your awareness, so the copy never implies something the schema cannot carry: Booster Shop does
not publish invented GTIN, reviews, `aggregateRating` or `ratingValue`.

## 14. Hard rules — a breach here sends the whole batch back

1. **A starter deck is a fixed, pre-built product.** Never describe or imply it as random, as a
   chance at a hit, or as anything resembling a booster. Every deck of a given code has identical
   contents.
2. **No invented product facts.** No card counts, release dates, leader names or exclusives without
   a source. This is the single most common failure mode in this workflow.
3. No invented GTIN, reviews, ratings, review counts, stock numbers or sales figures.
4. No guarantees of expensive cards, pull rates or investment value.
5. No `100% оригінал`, `тільки у нас`, `найкраща ціна` or similar unsupported claims.
6. Do not state availability beyond `передзамовлення`, and do not promise shipping dates.
7. No keyword stuffing. If a sentence exists only to hold a keyword, delete it.
8. Do not copy sentences from Bandai, a wiki, or a competitor shop. Facts are facts; wording must be
   your own.
9. Do not compare the decks to each other on power level or on "which is best to buy" — the shop
   sells all five.
10. Ukrainian copy must read as written by a person, not translated. Avoid literal calques of
    English marketing phrasing.

## 15. QA checklist — run it yourself before delivering

- [ ] Five complete blocks, one per SKU, identical structure
- [ ] Primary keyword: once in H1, one to two times in body, and nowhere else
- [ ] Every researched fact has a numbered source; anything unsourced is in `Невирішене`
- [ ] Meta Title ≤ 63 characters, Meta Description ≤ 158 characters, counted not estimated
- [ ] No SKU in any SEO URL
- [ ] No JSON-LD, no images, no inline styles, only the allowed HTML tags
- [ ] Nothing implies randomness, guaranteed hits or investment return
- [ ] Attribute table rows identical across all five decks
- [ ] One CTA per page
- [ ] Read each description aloud once — if a sentence exists only for SEO, it is gone

---

## Notes for Claude at review time (not for ChatGPT)

- Verify every source URL actually supports the claim it is attached to; a plausible number with an
  unrelated link is the failure to watch for.
- Confirm the five primary keywords do not cannibalise each other or existing One Piece pages —
  `bs-keyword-map`.
- Confirm SEO URLs against the live convention (`/product/One-Piece-Boosters-OP-11`,
  `/product/One-Piece-Mystery-Box`) and against `AGENTS.md`: the SKU never enters the URL, and the
  legacy SKU-slug pattern was already 301-redirected away under `TECH-012`.
- Supply the real internal-link slugs; ChatGPT was told not to invent them.
- The OP-16 box page exists and is active in preorder status (owner, 2026-08-10) — out of scope, do
  not let a draft modify it.
- Schema and feed implications go through `bs-merchant-schema-qa` at the patch stage, not here.
