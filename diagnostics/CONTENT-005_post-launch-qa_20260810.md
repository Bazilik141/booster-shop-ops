# CONTENT-005 — post-launch QA of the five live starter-deck pages

Date: 2026-08-10
Reviewer: Claude (chat) — `bs-merchant-schema-qa` + `bs-seo-risk-gate`
Trigger: owner switched the five products visible and asked for the check.

**Verdict: pages are sound and safe to leave live. Two things need the owner's attention, neither of
them a defect on the site.**

## 1 · The live descriptions are not the copy that was approved and patched

This is the headline finding and it was not expected.

The patch payload was verified **twice** — before and after the round-2 fix — as byte-identical to
`plans/CONTENT-005_starter-deck-cards_final_20260810.md`. The pages now serve **different
descriptions**. Confirmed on two of five, so it is systematic, not a one-off:

| Element | ST-32 live | ST-36 live |
|---|---|---|
| `meta_title` / `meta_description` / `meta_keyword` | matches the plan | matches the plan |
| H1 (`name`) | matches the plan | matches the plan |
| Attribute rows | match the plan, all eight | match the plan, all eight |
| **Description body** | **replaced** | **replaced** |
| FAQ questions | **replaced**, still four items | **replaced**, still four items |

Example. Plan: `<h2>Жовта колода, яка однаково добре б'є і тримає удар</h2>` followed by
`Завдяки ефекту лідера Юстасса Кіда персонаж швидко отримує Blocker`.
Live: `ST-36 Eustass Kid — жовтий midrange, що перетворює атаку на захист`, then a `Як грає ST-36`
section describing the leader effect in full mechanical detail.

Someone edited the descriptions after the patch ran. That is the owner's prerogative — the point of
recording it is that **`plans/CONTENT-005_starter-deck-cards_final_20260810.md` is no longer the
source of truth for what is on the site.** Left as is, the next person to touch these pages will work
from a file that does not describe production.

## 2 · The replacement copy is accurate — and better sourced than mine

Checked the ST-36 leader claim against the official card list
(`www.onepiece-cardgame.com/cardlist/?series=550036`), card `OP10-099 ユースタス・キッド`, LEADER:

> 【自分のターン終了時】自分のライフの上から1枚を表向きにできる：自分のコスト3から8の特徴《超新星》を持つキャラ1枚までを、アクティブにし、そのキャラは、次の相手のターン終了時まで、【ブロッカー】を得る。

Live copy: "Ефект лідера Eustass Kid наприкінці вашого ходу дозволяє перевернути верхню карту Life
лицем догори, активувати одного персонажа Supernovas вартістю від 3 до 8 і дати йому Blocker до
кінця наступного ходу суперника."

Every clause matches: end of your turn, turn the top Life card face up, one Supernova character of
cost 3 to 8, active, Blocker until the end of the opponent's next turn. The `Supernovas` trait is
also confirmed — `特徴《超新星》` appears on card after card in this deck.

**My round-2 edit was over-cautious.** I removed those specifics because the *product* page did not
carry them, and I chose not to open the card list. The card list carries them exactly. Whoever
rewrote the copy did the check I skipped, and the pages are better for it.

Not verified in this pass: the equivalent leader claims on ST-32, ST-33, ST-34 and ST-35, and the FAQ
answers on all five — the answers sit in `hidden` accordion panels, which a text read of the page
cannot reach. ST-32's live claim ("після бою з персонажем суперника знову перевести Зоро в активний
стан") is plausible and consistent with `連続攻撃`, but is **not** confirmed by me.

## 3 · Structure, pricing and status — clean

Checked on both fetched pages:

- Price **₴700.00**, status **Передзамовлення**, button **Передзамовити** — identical to the live
  OP-16 box template.
- Manufacturer **Bandai**, linked. Breadcrumb into `One Piece Card Game`. Canonical self-referential.
  `meta-robots: index,follow`.
- Three tabs present: `Опис`, `Характеристики`, `Відгуки`. The eight attribute rows render in the
  second tab, in OpenCart `sort_order`, exactly as the corrected acceptance criterion expects.
- FAQ renders as four items whose answers are not in the text layer — i.e. the `.bs-faq-panel[hidden]`
  accordion is doing its job.
- Related products show the OP-16 box plus sibling decks, one-directionally as designed.
- **Photographs are uploaded** on all five. The empty-image disapproval risk from the review is gone.

Minor hygiene, not a defect: image filenames are inconsistent — ST-36 uses
`one-piece-card-game-start-deck-yellow-eustass-kid-st-36-1.png` while the others use
`STARTER DECK ST-32 ONE PIECE CARD GAME.png` with literal spaces, which then appear URL-encoded as
`%20`. Harmless; worth a convention next time.

## 4 · Merchant / schema — what I can and cannot state

| Check | Result | Basis |
|---|---|---|
| No invented GTIN | **Pass** | The patch writes `ean`, `jan`, `isbn`, `upc`, `mpn` as empty strings and asserts the template's were empty. The theme has nothing to emit. |
| No reviews / `aggregateRating` without source | **Pass** | Products 120-124 are new with zero reviews; no rating renders on the page. |
| Price and currency on site | **Pass** | ₴700.00, UAH, consistent across page, related-product cards and category listing. |
| `availability` value | **Not verifiable from here** | The JSON-LD sits in a `<script type="application/ld+json">` that a text fetch does not return, and no browser is connected to this session. |
| Rich Results Test | **Not run** | Same reason. |
| Feed `id` unchanged | **n/a** | New products; no existing feed id was touched. |
| `shippingDetails` / return policy | **Untouched** | This task changed no policy text. |

Proportionality note: the five decks are configured **identically to the live OP-16 booster box**,
which has been in preorder on this site for some time. Whatever the theme emits for
`stock_status_id = 8`, it already emits for OP-16. So the residual `availability` risk is
pre-existing shop behaviour, not something this change introduced. That is a reason to check it
calmly, not a reason to skip it.

## 5 · SEO risk gate — LOW, sitemap regeneration approved

- Five new URLs, all human-readable, none containing a SKU. No existing URL changed, so no redirect
  is required and `TECH-012`'s legacy-slug work is untouched.
- `robots`, `canonical` and `.htaccess` are unmodified by this task.
- Sitemap regeneration is purely additive: five entries appear, nothing is removed or rewritten.
- No keyword cannibalisation: each page carries its own deck code and character in title and H1.

**Cleared to regenerate the sitemap.**

## Owner actions

1. Run Google Rich Results Test on two URLs — ST-32 and ST-36. Confirm `availability` reads
   `PreOrder`, `priceCurrency` is `UAH`, `price` is `700`, and that no `gtin` or `aggregateRating`
   field appears. Two minutes, and it closes the only open item in section 4.
2. Open one FAQ item per page and read the answers. They were rewritten with the descriptions and no
   one has proof-read them; the answers cannot be seen from outside the browser.
3. Decide what to do about section 1 — see below.
4. Regenerate the sitemap.

## Decision the owner owes the repository

`plans/CONTENT-005_starter-deck-cards_final_20260810.md` and the live pages have diverged. Options:

- pull the five live descriptions back into the plan file so it describes production again; or
- mark the plan file as superseded and record where the current copy lives.

Doing neither leaves a file labelled "source of truth for the publication patch" that no longer
matches the site — precisely the kind of quiet drift that made the OP-15 cost audit expensive.
