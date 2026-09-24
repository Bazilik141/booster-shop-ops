# CAT-004 — Attribute vocabulary addendum for the ChatGPT drafting chat

Date: 2026-09-18 | Task: `CAT-004`
Supplements: `handoffs/handoff_CAT-004_starter-decks-and-rare-packs_chatgpt_20260916.md`, §9
block «Характеристики (атрибути OpenCart)» and §17 rule 1.

**Evidence:** `boosters_ocart49.sql.gz` (DB dump dated 2026-09-12, the newest available; newer
than the 2026-09-07 cPanel backup). Tables `ocp5_attribute`, `ocp5_attribute_description`,
`ocp5_attribute_group_description`, `ocp5_product_attribute`. `language_id = 4` is the only
language present in the store — 40 attribute rows exist in total, ids 12–55.

Sections 1–7 are written to be pasted into the drafting chat verbatim. The final section is for
the owner only.

---

## 1. Rule for the drafting chat

The row names below are the complete set that exists in this store. Copy a name **character for
character**, including case, spacing and the `/` in `Розмір / Формат`.

- Do not invent a row name. A name that reads perfectly but is not on this list fails the patch
  at dry run with `attribute_missing`. This has already happened twice in this project.
- Do not propose a new row. A new attribute is a separate owner-approved patch with its own
  group and sort order. If a fact has no row here, put the fact in the description prose and say
  so in «Невирішене».
- The **values** are free text — you write them. Only the row names are fixed.
- Row names are shared across the whole store, so «this row exists» does not mean «this row
  belongs on a sealed card». Section 3 lists the rows you must not touch.

## 2. The vocabulary you may use — group 7 «Характеристики» (sealed TCG products)

These 12 rows are the entire sealed-product vocabulary. Nothing else may appear in your
«Характеристики» block.

| id | Row name — copy exactly | Holds | Live values on Pokémon / One Piece cards |
|---|---|---|---|
| 12 | `Мова` | edition language | `Японська (Japanese)` (29 PKM + 23 OP), `Англійська (English)`, `Корейська (Korean)` |
| 13 | `Назва сету` | set / product name | free text, e.g. `Abyss Eye (M5)`, `The World's Strongest Warriors`, `Стартова колода ST-32 (One Piece Card Game)`, `Starter Set Terastal Loudbone ex` |
| 14 | `Рік випуску` | release **year** only | `2023`, `2024`, `2025`, `2026` |
| 15 | `Кількість карток у бустері` | cards in one pack | PKM: `5`, `7`, `10`; OP: `6`. Two styles coexist live — bare `6` (9 rows) and `6 карт` (6 rows). Use the bare number. |
| 16 | `Кількість бустерів у боксі` | packs in a box | `30` (PKM box), `24` (OP box), `20`, `8`, `6`… **Not used on a single pack and not used on a starter deck.** |
| 17 | `Стан` | condition | `Новий, нерозпакований (Sealed)` — 36 of 39 PKM and 21 of 23 OP rows. Use this string. |
| 18 | `Походження товару` | how the item was sourced | `Box / Case sourced (з оригінального боксу / кейсу)`, `Оригінальний sealed box в заводській плівці`, `Outlet — оптова закупка партіями`, `Оригінальна продукція Bandai` |
| 19 | `Зважування` | weighing disclosure | `Без зважування (Unweighed)` (28 rows), `Не застосовується — заводський sealed box` |
| 20 | `Виробник` | manufacturer | `The Pokémon Company` (all 39 PKM rows), `Bandai` (all 23 OP rows) |
| 21 | `Тип пакування` | packaging type | `Sealed Booster Pack`, `Sealed Booster Box`, `Starter Deck`, `Starter Set`, `Special Card Set`, `Sealed Booster Bundle`, `Premium Trainer Box`, `Sealed Mini Tin`, `Sealed PROMO Pack`, `Mystery Box Standard`, `Mystery Box XL`, `Checklane Blister`, `Blister / Special Card Set` |
| 24 | `Додатковий вміст` | non-card or bonus contents | e.g. `10 карт DON!!, 3 картки-індекси`, `1 Basic Energy, 1 Pokémon TCG Live code card`, `ігрове поле, монета Pokémon, аркуш жетонів шкоди та маркерів, посібник з правил` |
| 49 | `Кількість карток у колоді` | cards in a constructed deck | PKM `60` (id 155); OP `51 (50 карт + 1 лідер), усього 15 різних` (all five ST-3x decks) |

## 3. Rows that exist in the store but are forbidden on these 13 cards

Group 9 «Характеристики аксесуарів» (ids 27–35) and group 10 «Характеристики 3D-виробу»
(ids 36–48, 50–55) belong to sleeves, storage and 3D-printed goods. They render under a
different group heading and must not appear on a sealed card.

Two of them read as if they fit a sealed product — they do not:

- `Вікове позиціонування` (id 42) is a 3D-print row. The manufacturer age marking you research
  for §8 of the brief goes into the **description prose**, not into an attribute.
- `Матеріал` (id 51) and `Сумісність` (id 55) are 3D-print rows; `Сумісність з картками` (id 33)
  is an accessories row.

## 4. Family A — Pokémon ex Start Deck, 9 cards

Fill exactly these rows:

| Row | Value |
|---|---|
| `Мова` | `Японська (Japanese)` |
| `Назва сету` | from your research — the official series name of the ex Start Deck line |
| `Рік випуску` | from your research, year only |
| `Стан` | `Новий, нерозпакований (Sealed)` |
| `Виробник` | `The Pokémon Company` |
| `Тип пакування` | `Starter Deck` — see the owner-decision note below if you see `Starter Set` used elsewhere |
| `Кількість карток у колоді` | from your research (precedent id 155 writes a bare `60`) |
| `Додатковий вміст` | from your research — coin, damage counters, playmat, rulebook, promo, energy; omit the row entirely if the official source lists nothing |

Do **not** write `Кількість карток у бустері` or `Кількість бустерів у боксі` on a starter deck —
the only Pokémon starter precedent (id 155, `PKM-JP-SVEL-SET`) omits both.

`Походження товару` and `Зважування` are owner facts for this family, not research output. Leave
both out and note in «Невирішене» that the owner sets them.

For `…-RND`: the same rows, with `Назва сету` and `Додатковий вміст` describing the random-deck
product itself. No type-specific content.

## 5. Family B — One Piece Rare Pack, 4 cards

| Row | Value |
|---|---|
| `Мова` | `Японська (Japanese)` |
| `Назва сету` | from your research — official set name. The live OP-13 row (id 178) reads `Carrying On His Will (受け継がれる意志)`: official English name plus the Japanese name in brackets |
| `Рік випуску` | from your research, year only |
| `Кількість карток у бустері` | from your research; every live OP booster row is `6` |
| `Стан` | `Новий, нерозпакований (Sealed)` |
| `Виробник` | `Bandai` |
| `Тип пакування` | `Sealed Booster Pack` |
| `Зважування` | `Без зважування (Unweighed)` |
| `Походження товару` | propose wording in «Невирішене» only — see below |

`Кількість бустерів у боксі` is not written on a single pack.

**`Походження товару` is the sensitive one.** The brief's §11 origin paragraph mixes our own
statement with a supplier claim. No live value covers «bought individually at retail». Do not
invent one: propose your wording in «Невирішене» and let the owner approve it. The nearest live
values are `Box / Case sourced (з оригінального боксу / кейсу)` and `Оригінальна продукція Bandai`
— neither describes this batch.

**Do not copy product id 108 (`OP-JP-OP16-BST`).** It is a single booster carrying
`Тип пакування = Sealed Booster Box`. That is a known live defect, not the convention.

## 6. Facts that have no attribute row — put them in the prose, not in a table

- rarity, print status, whether Bandai still prints the set;
- manufacturer age marking;
- exact release date (row 14 holds the year only);
- deck play style, energy type, the Pokémon on the box;
- the fact that the `…-RND` deck type is not chosen by the buyer;
- the equal-odds statement required by §11 — it is a sentence in the description, never an
  attribute value.

## 7. Delivery format

In the «Характеристики (атрибути OpenCart)» block write one line per row:

```
Мова: Японська (Japanese)
Назва сету: <…>
Рік випуску: <…>
```

Order the lines as they appear in section 2 (ascending id). Omit any row you have no sourced
value for and list it in «Невирішене» — an empty or `—` value is worse than an absent row.

Write `&` as a plain ampersand. The live database stores `&amp;` inside some values; that is the
storage encoding, not the text.

---

## Open decision for the owner (not for the drafting chat)

`Тип пакування` for the nine Pokémon ex Start Decks — `Starter Deck` or `Starter Set`?

- `Starter Deck` is what all five One Piece `ST-3x` decks carry (ids 120–124), and it matches the
  `STD` format code in the approved SKUs.
- `Starter Set` is what the only Pokémon starter precedent carries — id 155
  `PKM-JP-SVEL-SET`, whose SKU format code is `SET`, not `STD`.

This addendum tells the drafting chat `Starter Deck`. If the owner decides otherwise, one string
changes in nine cards.
