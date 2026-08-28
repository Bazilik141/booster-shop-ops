# Addendum v2 to Patch Handoff — CONTENT-QUALITY: ready-copy FAQ recovery + Inferno X correction

**Date:** 2026-08-28  
**Parent handoff:** `handoff_CONTENT-QUALITY_patch-30-cards_20260827.md`  
**Applies to:** WP1 only — `patches/CONTENT-QUALITY_cards-update-28_20260827.php`  
**Executor:** same executor as parent handoff  
**Priority:** this file **fully supersedes** `addendum_CONTENT-QUALITY_patch-faq-recovery_20260827.md`.

> **Do not use the previous addendum.** It incorrectly delegated copy recovery/rephrasing to the executor.  
> In this v2 addendum all client-facing copy is final. The executor performs only mechanical insertion, encoding, assertions and QA.

---

## 0. Corrections applied 2026-08-28 (Claude, on owner instruction)

Two of the nine ready answers contradicted the pre-change live record. Both were rewritten before this file reached the executor; nothing else in the addendum changed.

### `ACC-3D-PKM-120`

Live row: *"Залежить від товщини конкретного топлоадера. Паз розрахований під стандартний 35PT; якщо картка додатково в протекторі й топлоадер товщий, посадка буде щільнішою."*

The previous draft answered "Так … для підставки нічого не змінюється", turning the one case the live card calls a tighter fit into a non-issue, and dropped the 35PT slot spec. The answer now keeps the conditional shape and restores 35PT.

### `ACC-3D-PKM-130`

Live row: *"Під стандартний магнітний акриловий кейс формату One Touch. Товщина кейса залежить від того, скільки карток він розрахований тримати — перед покупкою варто звірити свій формат із габаритами паза."*

The previous draft pinned "35PT" as a confirmed format. That number is the live spec for the **`ACC-3D-PKM-120` toploader slot**, not for this stand, and One Touch cases ship in several thicknesses — the claim would sell a stand that may not fit the buyer's case. The answer now restores the One Touch format and the check-your-own-case caveat, with no PT figure.

### Verified unchanged and correct

The other seven answers, the Inferno X paragraph, all ids, ARIA wiring, the 52-item total and the 11/11/5/1 distribution were re-checked after the edit and are unaffected. `ACC-3D-PKM-110`, `-200`, `-700`, `FIG-LUFFY-500` and `ACC-007-400` were fact-checked against the same backup and match the live record; `FIG-LUFFY-400` carries `Колір = чорний`, so the "плаский чорний силует" wording is confirmed, and `ACC-007-360` (product_id 112) states "формат сторінок 3×3 та еластичний фіксатор" in its own meta, which supports the comparison answer.

---

## 1. Scope

Apply only these changes:

1. append **9 exact FAQ items** to 7 existing product descriptions;
2. replace **one exact paragraph** in `PKM-JP-INFX-BBX` with the ready paragraph below;
3. keep every other accepted v2 field unchanged.

Do **not** reopen:
- accepted body copy outside the one Inferno X paragraph;
- existing FAQ items;
- Meta;
- product names;
- SEO URLs;
- categories;
- attributes;
- commercial fields.

The executor must **not write, recover, paraphrase, shorten, translate or improve any customer-facing text** in this addendum.

---

## 2. Mechanical implementation contract

For each affected product:

1. Start from the **audited v2 final description payload** used by the parent handoff.
2. Keep the existing canonical `<section class="bs-faq-accordion">`.
3. Insert the exact new `<div class="bs-faq-item">...</div>` block(s) below **immediately before the existing FAQ `</section>`**.
4. Do not renumber or edit existing FAQ items.
5. Do not create a second FAQ section.
6. Concatenate body + final FAQ exactly as in the parent handoff.
7. HTML-entity-encode the **whole final description** before DB write, using the same storage convention as existing `ocp5_product_description.description`.
8. Verify all `id`, `aria-controls` and `aria-labelledby` pairs.
9. If any expected target product, current FAQ section or expected existing item is missing, **abort and report**. Do not improvise a replacement.

### Limited factual precondition check

The executor may inspect the pre-change live/backup text **only as an assertion**, never as a source for new wording.

For `ACC-3D-PKM-120`, `ACC-3D-PKM-130` and `ACC-3D-PKM-700`, confirm that the pre-change factual record does not contradict the exact supplied answer below. If it does, **stop and report that SKU**. Do not rewrite the answer.

This check was run by Claude against `backup-8.24.2026_10-35-09_boosters` on 2026-08-28 and all three now pass — see §0. The executor still runs it, as a second pair of eyes, not as a formality.

---

# 3. Exact FAQ blocks to append

## 3.1 `ACC-3D-PKM-110` — product_id 137

Existing v2 FAQ item 1 remains unchanged. Append items 2 and 3 exactly.

### Item 2

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-110-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-110-button-2" type="button"><span>Чи підійде ця підставка для карт Pokémon, One Piece та Magic: The Gathering?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-110-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-110-panel-2" role="region">
<p>Так, якщо це стандартна TCG-картка у звичайному м’якому протекторі. Підставка розрахована саме на такий формат; для стандартних карт Pokémon, One Piece та Magic: The Gathering принцип сумісності однаковий.</p>
</div>
</div>
```

### Item 3

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-110-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-110-button-3" type="button"><span>Яку підставку вибрати для картки в топлоадері або магнітному кейсі?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-110-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-110-panel-3" role="region">
<p>Для картки в м’якому протекторі підійде ця мала підставка. Для картки в топлоадері обирайте середню підставку, а для магнітного акрилового кейса — велику.</p>
</div>
</div>
```

**Final FAQ count:** 3.

---

## 3.2 `ACC-3D-PKM-120` — product_id 138

Existing v2 FAQ item 1 remains unchanged. Append item 2 exactly.

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-120-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-120-button-2" type="button"><span>Чи поміститься топлоадер, якщо картка всередині вже в м’якому протекторі?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-120-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-120-panel-2" role="region">
<p>Паз розрахований під стандартний топлоадер 35PT. Якщо картка в м’якому протекторі нормально заходить у такий топлоадер, підставка тримає його зовнішній корпус як звичайно. З товщими або нестандартними топлоадерами посадка буде щільнішою.</p>
</div>
</div>
```

**Final FAQ count:** 2.

---

## 3.3 `ACC-3D-PKM-130` — product_id 139

Existing v2 FAQ item 1 remains unchanged. Append item 2 exactly.

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-130-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-130-button-2" type="button"><span>На який магнітний кейс розрахована велика підставка?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-130-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-130-panel-2" role="region">
<p>На стандартний магнітний акриловий кейс формату One Touch для однієї картки. Такі кейси бувають різної товщини — вона залежить від того, на скільки карток розрахований кейс, — тож перед покупкою варто звірити свій кейс із габаритами паза.</p>
</div>
</div>
```

**Final FAQ count:** 2.

---

## 3.4 `ACC-3D-PKM-200` — product_id 140

Existing v2 FAQ item 1 remains unchanged. Append item 2 exactly.

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-200-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-200-button-2" type="button"><span>Під який слаб розрахована підставка і скільки місця вона займає?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-200-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-200-panel-2" role="region">
<p>Ця версія розрахована на слаб PSA. Ширина основи по фронту — 89 мм, глибина — 38 мм, висота підставки — 20 мм.</p>
</div>
</div>
```

**Final FAQ count:** 2.

Do not add claims about tested stability, a «зручний кут» or unconfirmed CGC/BGS compatibility.

---

## 3.5 `ACC-3D-PKM-700` — product_id 142

Existing v2 FAQ item 1 remains unchanged. Append item 2 exactly.

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-3d-pkm-700-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-3d-pkm-700-button-2" type="button"><span>Чи потрібно збирати або змащувати обертову підставку перед використанням?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-3d-pkm-700-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-3d-pkm-700-panel-2" role="region">
<p>Ні. Підставка постачається готовою до використання: окреме складання та змащування обертового вузла перед звичайним використанням не потрібні.</p>
</div>
</div>
```

**Final FAQ count:** 2.

Do not duplicate capacity facts already present in body/attributes. Do not add claims about smoothness, stability, durability or service life.

---

## 3.6 `FIG-LUFFY-500` — product_id 134

Existing mandatory legal + contents FAQ item 1 remains unchanged. Append item 2 exactly.

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-fig-luffy-500-panel-2" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-fig-luffy-500-button-2" type="button"><span>Чим рухома фігурка Луффі відрізняється від панно та картини?</span></button></h3>

<div aria-labelledby="bs-faq-prod-fig-luffy-500-button-2" class="bs-faq-panel" hidden="" id="bs-faq-prod-fig-luffy-500-panel-2" role="region">
<p>Це об’ємна рухома фігурка із сегментованим корпусом, положення якого можна змінювати. Панно Луффі — плаский чорний силует персонажа в повний зріст на власній основі, а картина Луффі — лінійний портрет обличчя в прямокутній рамці.</p>
</div>
</div>
```

**Final FAQ count:** 2.

No internal link is required by this addendum.

---

## 3.7 `ACC-007-400` — product_id 144

Existing v2 FAQ items 1–2 remain unchanged. Append items 3 and 4 exactly.

### Item 3

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-007-400-panel-3" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-007-400-button-3" type="button"><span>Чим альбом на 400 карток відрізняється від жовтого альбому на 360?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-007-400-button-3" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-007-400-panel-3" role="region">
<p>У версії на 400 карток — 50 двосторонніх аркушів по 4 кишеньки з кожного боку, кільцевий механізм і застібка-блискавка. Альбом на 360 карток має сторінки 3×3 і закривається еластичною стрічкою. Тобто 400-версія вміщує більше карток і дозволяє виймати та переставляти комплектні аркуші.</p>
</div>
</div>
```

### Item 4

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="bs-faq-prod-acc-007-400-panel-4" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="bs-faq-prod-acc-007-400-button-4" type="button"><span>Чи входять картки до комплекту альбому?</span></button></h3>

<div aria-labelledby="bs-faq-prod-acc-007-400-button-4" class="bs-faq-panel" hidden="" id="bs-faq-prod-acc-007-400-panel-4" role="region">
<p>Ні. У комплекті — 1 жовтий альбом із 50 аркушами. Картки, протектори та інші предмети на фото показані для демонстрації й до комплекту не входять.</p>
</div>
</div>
```

**Final FAQ count:** 4.

Do not restore superseded uncertainty about soft sleeves or toploaders. Current confirmed compatibility remains unchanged.

---

# 4. `PKM-JP-INFX-BBX` — product_id 148

No new FAQ item.

Replace only the current Booster Box paragraph beginning:

> `У заводськи запечатаній коробці <strong>30 бустерів по 5 карт</strong>.`

with this exact final paragraph:

```html
<p>У заводськи запечатаній коробці <strong>30 бустерів по 5 карт</strong>. 30 бустерів дають значно більше знайомства із сетом, але конкретний Mega Charizard X ex чи інша визначена карта в коробці не гарантуються виробником. Це варіант для колекціонера, який уже знає, що хоче копати Inferno X глибше: від основної лінії Mega Charizard X ex до інших Mega Evolution і спеціальних ілюстрацій, а не просто перевірити один випадковий пак.</p>
```

The sentence:

> `30 бустерів дають значно більше знайомства із сетом, але конкретний Mega Charizard X ex чи інша визначена карта в коробці не гарантуються виробником.`

is owner-approved and must remain **byte-for-byte unchanged in visible text**.

Do not alter Inferno X FAQ, Meta, set mapping or any other paragraph.

---

# 5. Acceptance criteria override

This addendum overrides only the FAQ-count expectation from the parent handoff.

## WP1 — 28 existing cards

After applying this addendum:

- FAQ items total: **52**
- cards with 1 FAQ: **11**
- cards with 2 FAQ: **11**
- cards with 3 FAQ: **5**
- cards with 4 FAQ: **1**

Arithmetic check:

`11×1 + 11×2 + 5×3 + 1×4 = 52`

## Full 30-card content definition

For reference only, including `BR-CHARM-200` and the out-of-scope `PKM-JP-SVEL-SET`:

- FAQ items total: **57**
- cards with 1 FAQ: **11**
- cards with 2 FAQ: **12**
- cards with 3 FAQ: **6**
- cards with 4 FAQ: **1**

`PKM-JP-SVEL-SET` remains outside this patch unless separately re-scoped by the owner.

---

# 6. Mandatory post-build QA

The executor must report all of the following before execution:

- 28 existing target descriptions resolved;
- **52 FAQ items** across WP1 final payload;
- **104 unique button/panel IDs** across those 52 FAQ items;
- duplicate FAQ IDs: 0;
- broken `aria-controls` / `aria-labelledby` pairs: 0;
- second FAQ sections on affected cards: 0;
- legacy `<section class="bs-faq">`: 0;
- legacy `<div class="bs-faq-accordion">`: 0;
- raw `<h2>`, `<p>`, `<section>` stored in DB description after encoding: 0;
- entity-encoded `&lt;...&gt;` storage form confirmed;
- Inferno X owner-approved guarantee sentence: exactly 1 occurrence;
- existing FAQ items on the 7 affected cards unchanged;
- Meta/name/URL/category/attribute diffs caused by this addendum: 0.

After dry run, show the exact per-product FAQ count for:
- `ACC-3D-PKM-110` = 3
- `ACC-3D-PKM-120` = 2
- `ACC-3D-PKM-130` = 2
- `ACC-3D-PKM-200` = 2
- `ACC-3D-PKM-700` = 2
- `FIG-LUFFY-500` = 2
- `ACC-007-400` = 4

Do not execute production write if any assertion fails.

---

# 7. Source files used to prepare this ready-copy addendum

These are **review references only**. They are not instructions for the executor to author copy.

- `handoff_CONTENT-QUALITY_patch-30-cards_20260827.md` — parent patch scope and DB contract.
- `04_FAQ_ACCORDION_PAYLOAD_30.md` — current audited canonical accordion and existing item numbering.
- `BOOSTER-SHOP_20-3D_cleanup_v1_20260824.md` — accepted 3D body/FAQ state.
- `booster_shop_3d_card_stands_draft.md` — confirmed physical compatibility/dimensions used in the targeted answers.
- `booster_shop_accessories_ACC-007-009_preview.md` — confirmed neighbouring 360-card album construction used in the comparison.
- `CONTENT-QUALITY_10-non3d_FINAL_20260824.md` / final cleanup — confirmed 400-card album facts.
- owner-approved Inferno X sentence from the CONTENT-QUALITY review.

**Important:** these files are not fallback copy sources. The exact customer-facing text to insert is already present in §§3–4 above.
