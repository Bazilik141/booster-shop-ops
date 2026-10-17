# HANDOFF — CAT-005 — Bulk / Bulk Mix: product-card copy

Date: 2026-10-17
Task: CAT-005 (Notion `3fc6bf20-bdb4-81e8-ac86-fa6e70f5f529`)
Author: Claude (chat). Addressee: the ChatGPT content-drafting chat.
Role of the addressee: write client-facing copy only. No site access, no patches, no deployment, no CRM edits.
Return path: the owner brings the result back to Claude for QA; Claude polishes; the approved copy is merged into the Claude Code handoff together with the offer amendment.

Client-facing language: Ukrainian. Everything else in this file is instruction to you.

---

## 0. Owner inputs (owner: fill before sending, or leave the defaults)

| ID | Question | Default if left blank |
|---|---|---|
| O-1 | Create two new product attributes `Кількість карток у наборі` and `Склад за рідкістю` (attribute group 7)? Claude recommends YES: offer 5.6 requires the page to show these characteristics and no existing attribute fits (15 / 16 / 49 describe boosters, boxes and decks). Without them the count and rarity split live only in the name, body and FAQ. | `NO` — use existing attributes only; still return the two values in the `pending_attributes` block so nothing is lost if the owner says yes later. |
| O-2 | Damage policy sentence, only if the owner wants one stated on the cards (for example, whether visibly bent or creased cards are kept out of sets). | none — make NO claim about damage or wear beyond section 4. |

---

## 1. What is being written

Three live cards now (site launch), two later:

| Priority | SKU | Status |
|---|---|---|
| WRITE NOW | `PKM-JP-BULK-155` | launches |
| WRITE NOW | `OP-JP-BULK-155` | launches |
| WRITE NOW | `PKM-MIX-BULK-100` | launches |
| DO NOT WRITE | `PKM-JP-BULK-100`, `OP-JP-BULK-100` | deferred; exist only in CRM. Do not draft copy for them. |

Deliverable per card: H1 is fixed (below). You write body HTML, FAQ, Meta Title, Meta Description, tags and attribute values.

---

## 2. What the product is (and is not)

A **Bulk / Bulk Mix** is a set of original collectible cards that the shop assembles itself from its own stock. The cards were earlier taken out of factory packaging during pack openings done by the shop.

It is **not**: a sealed manufacturer product, a booster, a mystery box, a set with guaranteed valuable cards, an investment product.

Commercial angle: the product sells on **card count, affordability and a clearly stated composition** — never on excitement, chance or chase. Plausible buyer situations you may use: a first purchase for a newcomer, material for building a deck or filling a binder, trade stock, a low-cost gift. Do not invent facts about what a buyer can win or what the cards are worth.

---

## 3. Canonical model

### 3.1 Site model (the three launch cards)

| Field | `PKM-JP-BULK-155` | `OP-JP-BULK-155` | `PKM-MIX-BULK-100` |
|---|---|---|---|
| H1 (fixed, do not change) | `Набір Pokémon TCG: Bulk 155 — 150 C/U + 5 R (Японське видання)` | `Набір One Piece Card Game: Bulk 155 — 150 C/UC + 5 R (Японське видання)` | `Набір Pokémon TCG: Bulk Mix 100 — C/U (Мікс видань: JP / EN / KR)` |
| Total cards | 155 | 155 | 100 |
| Guaranteed composition | 150 Common/Uncommon (C/U) + 5 Rare (R) | 150 Common/Uncommon (C/UC) + 5 Rare (R) | 100 Common/Uncommon (C/U) |
| Language | Japanese only | Japanese only | Japanese, English and Korean cards may appear; the split is **not fixed** |
| Manufacturer (attribute) | The Pokémon Company | Bandai | The Pokémon Company |
| SEO URL keyword (fixed) | `Pokemon-Bulk-155-JP` | `One-Piece-Bulk-155-JP` | `Pokemon-Bulk-Mix-100` |
| Site categories | Pokémon + subcategory «Набори» | One Piece + subcategory «Набори та бокси One Piece» | Pokémon + subcategory «Набори» |
| Stock display | «В наявності» / «Немає в наявності», with the quantity | same | same |

Do not print prices, weights or dimensions in any copy (the owner enters weight and dimensions separately; prices change).

### 3.2 CRM record (for the owner's manual entry — not for you to alter or reproduce in copy)

All five SKUs go into the CRM; the owner enters the purchase/cost side himself.

| SKU | CRM name | RRP |
|---|---|---:|
| `PKM-JP-BULK-100` | `Pokémon — Bulk 100 — JP — Bulk` | 150 грн |
| `PKM-JP-BULK-155` | `Pokémon — Bulk 155 — JP — Bulk` | 250 грн |
| `OP-JP-BULK-100` | `One Piece — Bulk 100 — JP — Bulk` | 150 грн |
| `OP-JP-BULK-155` | `One Piece — Bulk 155 — JP — Bulk` | 250 грн |
| `PKM-MIX-BULK-100` | `Pokémon — Bulk Mix 100 — MIX — Bulk` | 150 грн |

Open for the owner (not for you): the CRM product-type column currently offers Accessory, Booster, Rare Booster, Booster Box, Blister, Booster Bundle, Starter Set. `Bulk` is a new value; check the dropdown validation in `Товари` and add it there rather than reusing «Booster». SKUs follow the Mystery-box precedent (`PKM-JP-MBX-ST`: line token in the third segment, variant in the fourth).

---

## 4. Guaranteed / not guaranteed (binding on every sentence you write)

**Guaranteed (state plainly, in body and attributes):**
- the total number of cards;
- the TCG;
- the language (Bulk) or the list of possible languages (Bulk Mix);
- the rarity split: 150 C/U(C) + 5 R for Bulk 155; 100 C/U for Bulk Mix;
- that the cards are original, taken from factory packaging earlier;
- that this is not a sealed manufacturer product.

**Not guaranteed (say so, in human language):**
- any specific card, character, set or illustration;
- SR, AR, SAR, SEC, Alternate Art and any other higher or special rarity;
- any collectible, investment or market value;
- the exact language split in Bulk Mix;
- professional grading or any condition grade (PSA, BGS, CGC; also do not use «Mint», «Gem Mint», «Near Mint»);
- that the cards match the photos.

**Duplicates — decided by the owner, wording is yours but the legal meaning is fixed.** The shop builds sets without repeats on purpose, but that is NOT a guarantee. Convey, in plain and friendly Ukrainian, roughly: «ми складаємо набори без повторів; якщо один дубль випадково затесався — це не брак, вибачте». Hard constraints:
- never write «повторів немає», «без повторів гарантовано», or any number or limit of repeats;
- never write that all cards are unique;
- the text goes into the body or the FAQ, once per card, in different words on each card.

**Do NOT state, in either direction, anything about:** the five R being different from each other; Energy cards (Pokémon) or DON!! / Leader cards (One Piece) being included or excluded; a minimum number of cards per language; a language split; whether valuable cards were removed from the stock; weighing, sorting or hand selection.
(The owner deliberately left these out of the offer. An unknown fact is omitted from client copy; it is not turned into a FAQ answer saying «unknown».)

**Condition.** The condition is its own canonical characteristic. The only confirmed wording is: original cards from opened packs; no condition grade is declared. Photos show approximate filling only. If O-2 is filled in, you may add that one sentence; otherwise say nothing about damage.

**Packaging.** The actual packaging is chosen at order assembly, based on volume and the protection the cards need. Say that. Do not describe a box, a bag or any specific container, and do not create the impression of factory packaging. Do not use the word «sealed» about the product except to say it is NOT sealed.

---

## 5. Consistency with the public offer (amendment dated 18.10.2026)

The cards and the offer are published together; the card must not say more or less than the offer. Terms:
- The term is **«Bulk / Bulk Mix»** everywhere. Never «Bulk Box» and never «Mix Bulk» (the owner unified this on 2026-10-17).
- Rarity letters C, U, UC, R follow the manufacturer's classification of the specific TCG. Explain once, in plain words, what C/U (or C/UC) and R mean on that card.
- Offer 5.6 makes the guaranteed characteristics shown on the product page part of the terms of sale. So every guaranteed number on the card must be exactly right and identical in H1, body, FAQ, meta and attributes. Any number you are not sure about stays out.
- Offer 6.22: duplicates may occur; absence of repeats is a guarantee only if the product page says so — it must NOT say so.
- Offer 6.24: photos may show example cards and are not the guaranteed content. The body must say this plainly.
- Offer 6.25: no guarantee of collectible or market value.
- Offer 13.14 lists what counts as non-conformity: fewer cards than guaranteed; missing guaranteed R cards; cards of another TCG; wrong language. A FAQ answer about what happens if the set does not match may refer to this list in plain words and to the shop's return terms; do not add new rights or limits.

---

## 6. Voice and language rules

- The shop speaks as «ми»; the buyer is «ви». Never «ти».
- No internal vocabulary: `SKU`, `артикул`, `позиція`, `номенклатура`, `хвиля`, `CRM`.
- Natural Ukrainian. English only for official names (Pokémon TCG, One Piece Card Game, the rarity letters). Avoid `opening`, `chase`, `hit`, `pull`, `hype`, `card pool`, `binder collection` in prose.
- No trust block: no bulleted «чому купують у Booster Shop», no heading named after the shop, no «безкоштовна доставка від 2000 грн» boilerplate. Facts about origin and honesty enter inside the body text, calmly, without justifying.
- No exaggeration, no urgency, no «вигідно/суперціна» claims, no comparison to other shops, no «топ». No «лімітований», «ексклюзивний», «гарантований» attached to anything except the guaranteed items in section 4.
- Never invent: GTIN, reviews, ratings, stock numbers, weights, dimensions, release dates, card lists, set names.
- The opening sentence must be a hook specific to this product, not a restatement of H1 and not generic praise. The H1 itself is not repeated as an H2 at the top.
- Apply the ChatGPT Core/Product content skills you are configured with. Where this handoff is stricter, this handoff wins.
- The three cards must read as three different cards: no paragraph, FAQ question or FAQ answer is shared verbatim between them. The same legal meaning may be reworded.

---

## 7. Deliverable per card

One file for all three, Markdown, named `CAT-005_bulk-card-copy_<YYYYMMDD>.md`, one section per SKU, each containing a fenced block with exactly these fields:

```
sku:
h1:                      # copy of the fixed H1
seo_keyword:             # copy of the fixed keyword
meta_title:              # <= 63 characters INCLUDING the suffix " | Booster Shop"; must differ from h1
meta_description:        # <= 155 characters; product + strongest differentiator; no filler
tags:                    # 5-8 comma-separated Ukrainian/English tags; no repeats of the same word form
description_html:        # see markup below
attributes:              # list of  <attribute_id> | <attribute name> | <value>   (section 8)
pending_attributes:      # only if O-1 is not YES: the two new-attribute values, marked PENDING
self_qa:                 # the checklist in section 10, each item PASS/FAIL with a quote
```

Do NOT produce `meta_keywords` (the field is retired).

### Body shape
- 3–4 `<h2>` sections, 5–7 `<p>`, at most one `<ul>` (the guaranteed composition may be the one list), `<strong>` only on guaranteed facts.
- Must cover, each in your own words per card: what the set is and where the cards come from; the exact guaranteed composition; the languages; what is not guaranteed (specific cards and sets, special rarities, value); duplicates (section 4 wording); that the set is not a sealed manufacturer product; packaging chosen at assembly; photos are illustrative.
- Do not start every card with the same structure.

### FAQ — mandatory markup (copy exactly; replace the id and texts)
The `data-bs-faq-id` is `prod-` + the model in lowercase with hyphens, e.g. `prod-pkm-jp-bulk-155`.

```html
<section class="bs-faq-accordion" data-bs-faq-accordion="" data-bs-faq-id="prod-pkm-jp-bulk-155">
<h2 class="bs-faq-title">FAQ</h2>

<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="prod-pkm-jp-bulk-155-panel-1" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="prod-pkm-jp-bulk-155-button-1" type="button"><span>QUESTION</span></button></h3>

<div aria-labelledby="prod-pkm-jp-bulk-155-button-1" class="bs-faq-panel" hidden="" id="prod-pkm-jp-bulk-155-panel-1" role="region">
<p>ANSWER</p>
</div>
</div>

</section>
```

### FAQ — content
- 3–5 content questions per card, plus the mandatory disclosure item (this set is formed by the shop from cards taken out of factory packaging and is not a sealed manufacturer product) — the disclosure does not count toward the 3–5.
- Roughly half search-shaped, half product-shaped. A search-shaped question must make sense to someone searching the category who has never seen this product, and must survive being asked about another shop's set. Good directions: what «bulk» means for Pokémon / One Piece cards; what C, U, UC, R mean; what a bulk set is useful for; what the rarity letters do and do not promise. Product-shaped directions: duplicates; condition; languages (Bulk Mix); what to do if the set does not match the stated composition; packaging.
- An answer must add information; do not merely repeat a `<strong>` value from the body.

---

## 8. Attributes (already resolved against the live attribute dictionary; do not invent names)

Use only these existing names (attribute group 7 `Характеристики`). Values below are the canonical wording; adapt the game name only.

| ID | Name | Value |
|---:|---|---|
| 12 | Мова | Bulk: `Японська (Japanese)` · Bulk Mix: `Японська, англійська, корейська (JP / EN / KR)` |
| 13 | Назва сету | `Bulk / різні японські сети Pokémon TCG` · `Bulk / різні японські сети One Piece Card Game` · `Bulk Mix / різні сети Pokémon TCG (JP / EN / KR)` |
| 17 | Стан | `Оригінальні картки з розкритих бустерів; ступінь стану не заявляється` |
| 18 | Походження товару | `Картки з оригінальних бустерів, розкритих магазином` |
| 20 | Виробник | `The Pokémon Company` / `Bandai` |
| 21 | Тип пакування | `Сформований набір карток; паковання підбирається при комплектації замовлення` |
| 24 | Додатковий вміст | `Немає; спеціальні рідкості не гарантуються` |

Do not return attributes 15, 16, 19, 49 — they describe boosters, boxes, weighing or decks and would mislabel this product. Do not return `Рік випуску`.

Pending (only used if the owner answers YES to O-1; otherwise returned under `pending_attributes`):

| Name | Pokémon Bulk 155 | One Piece Bulk 155 | Pokémon Bulk Mix 100 |
|---|---|---|---|
| Кількість карток у наборі | 155 | 155 | 100 |
| Склад за рідкістю | 150 C/U + 5 R | 150 C/UC + 5 R | 100 C/U |

---

## 9. Photo requirements (context for alt text and body; the owner supplies the images)

- At least 3 real photographs per card (canon): the whole set as it is shipped or a stack of the right size; a spread of example cards; a close view of the cards.
- Photos show approximate filling only. The body states this (offer 6.24).
- No imagery that suggests a sealed factory product (no boxes, shrink wrap, booster packs presented as the contents).
- Do not feature a valuable card as the visual hook; no AI-generated imagery on a product card.
- Do not describe cards or photos you cannot see.

---

## 10. Self-QA before returning (each item PASS/FAIL, with the quoted sentence)

1. Every number (155 / 150 / 5 / 100) is identical across H1, body, FAQ, meta, attributes.
2. No sentence promises any specific card, special rarity, value or grade.
3. No «повторів немає», no repeat limit, no «усі картки унікальні».
4. Bulk is Japanese only; Bulk Mix lists JP/EN/KR as possible languages, with no minimum and no split.
5. No Energy / DON!! / Leader statement; no claim about the five R being distinct.
6. «Sealed» appears only in the sentence that says the product is not sealed.
7. The terms «Bulk Box» and «Mix Bulk» do not appear.
8. No `SKU` / `артикул` / price / weight / dimensions in client text.
9. No trust block; no shop-named heading.
10. Voice: ми/ви; no anglicism from the ban list.
11. Meta title ≤ 63 characters including ` | Booster Shop` and differs from H1; meta description ≤ 155.
12. FAQ markup identical to section 7; ids unique per card.
13. The three cards share no verbatim paragraph or FAQ.
14. Photos-are-illustrative statement present on each card.
15. Each card states, in its own words, that the set is not a sealed manufacturer product.

Report the character counts for meta title and meta description as numbers. If you cannot measure, say so; do not estimate.

---

## 11. Do not

- Do not change H1, SEO keywords, SKUs, attribute names, categories.
- Do not write copy for the two deferred SKUs.
- Do not add provisions that the owner removed from the offer: guaranteed absence of repeats; a cap of four repeats; five different R; extra language-split requirements; extra limits on return rights; Pokémon Energy or DON!! provisions.
- Do not claim a return or refund rule beyond referring to the shop's return terms in general words.
- Do not return a self-reported «QA PASS» without the quotes in section 10; the claim will be re-verified.

---

## 12. What happens next (for orientation)

1. Owner returns your file to Claude → independent content QA against this handoff and the offer text.
2. Claude polishes → merges the approved copy with the offer amendment into one handoff for Claude Code (cards and offer, one work package per patch).
3. Claude reviews the patch → the owner deploys.
