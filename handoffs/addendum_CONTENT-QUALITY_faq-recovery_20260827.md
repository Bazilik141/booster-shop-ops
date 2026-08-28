# Addendum to Patch Handoff — CONTENT-QUALITY: FAQ recovery + Inferno X disclaimer

**Date:** 2026-08-27  
**Parent handoff:** `handoff_CONTENT-QUALITY_patch-30-cards_20260827.md`  
**Applies to:** WP1 only — `patches/CONTENT-QUALITY_cards-update-28_20260827.php`  
**Executor:** same executor as parent handoff; do not split authorship.  
**Priority:** this addendum **overrides the parent handoff only where explicitly stated below**. Everything else in the parent handoff remains unchanged.

---

## 1. Why this addendum exists

Post-handoff review found that the audited v2 content removed a set of FAQ items that were **not duplicates of the new body** and still carry useful pre-purchase information.

The issue is not the lower FAQ count by itself. The issue is information loss: on the affected cards the old FAQ answered a real buyer question, while the v2 body/FAQ no longer answers it anywhere.

The intended fix is narrow:

- restore the **function and confirmed factual answer** of the missing FAQ items on 7 existing cards;
- add one owner-approved sentence to `PKM-JP-INFX-BBX`;
- do **not** reopen the accepted body copy, Meta, names, URLs, categories or attributes beyond these edits;
- do **not** restore old FAQ mechanically in bulk.

`ACC-3D-PKM-700/710` are the reference pattern for this decision: confirmed capacity/compatibility that affects purchase choice is already present in the new prose, so those facts do not need duplicate FAQ. The only `700` FAQ recovered here is a separate use/maintenance question.

---

## 2. Source-of-truth rule for recovered FAQ

For the FAQ items listed below, **do not invent a new factual answer** and do not pull an answer from superseded draft files.

Use the product's **pre-change live row / patch backup source** as the factual source:

1. Before writing the v2 replacement, decode the current `ocp5_product_description.description`.
2. Locate the legacy FAQ item that answers the function named below.
3. Recover the existing **question/answer meaning** from that live row.
4. Rephrase only as much as needed for clean Ukrainian and the current canonical accordion markup.
5. Preserve only facts that remain consistent with:
   - the audited v2 body;
   - current attributes;
   - the explicit owner-confirmed facts in the parent handoff.
6. If the old answer conflicts with current confirmed data or contains a claim explicitly deprecated by this task, **stop and report that SKU instead of guessing**.

### Never reintroduce these deprecated claims

In particular, do not restore:
- untested stability/performance claims for `ACC-3D-PKM-200/300/700/710`;
- `зручний кут` as a tested result for `ACC-3D-PKM-200`;
- unsupported compatibility with formats not confirmed for the current variant;
- old manufacturing/engineering claims that were removed during the CONTENT-QUALITY cleanup.

The goal is to recover **lost buyer information**, not old wording.

---

## 3. FAQ items to recover

All added items must be inserted **inside the existing canonical `<section class="bs-faq-accordion">`** for that card, after the current v2 item(s), before `</section>`.

Use the existing `data-bs-faq-id`. Continue the numeric button/panel sequence; do not renumber the already-audited items.

### 3.1 `ACC-3D-PKM-110` — product_id 137

Current v2 FAQ items: **1**  
Final FAQ items after this addendum: **3**

Recover two buyer questions from the pre-change live FAQ:

1. **TCG compatibility**
   - function to preserve: whether the stand is suitable for standard cards from Pokémon / One Piece / Magic: The Gathering in the supported soft-protector format;
   - answer must use only the compatibility actually stated in the live pre-change FAQ/current confirmed data.

2. **Which stand to choose for another protection format**
   - function to preserve: if the card is in a **toploader** or a **magnetic case**, which neighbouring Booster Shop stand format should be chosen instead of `ACC-3D-PKM-110`;
   - this is a comparison between the `110 / 120 / 130` formats, not a generic buying guide.

New IDs:
- `bs-faq-prod-acc-3d-pkm-110-button-2` / `...panel-2`
- `bs-faq-prod-acc-3d-pkm-110-button-3` / `...panel-3`

Do not change the existing item 1 about package contents.

---

### 3.2 `ACC-3D-PKM-120` — product_id 138

Current v2 FAQ items: **1**  
Final: **2**

Recover the compatibility question:

- function to preserve: **whether a standard toploader containing a card already in a soft sleeve fits the stand**;
- use the pre-change live answer as factual basis;
- do not broaden the claim to arbitrary thick/oversized loaders.

New IDs:
- `bs-faq-prod-acc-3d-pkm-120-button-2`
- `bs-faq-prod-acc-3d-pkm-120-panel-2`

Existing package-contents item remains item 1.

---

### 3.3 `ACC-3D-PKM-130` — product_id 139

Current v2 FAQ items: **1**  
Final: **2**

Recover the magnetic-case compatibility question:

- function to preserve: **which magnetic-case size / format this stand is designed for**;
- use the exact size/format confirmed in the pre-change live FAQ;
- do not generalize to every magnetic acrylic case.

New IDs:
- `bs-faq-prod-acc-3d-pkm-130-button-2`
- `bs-faq-prod-acc-3d-pkm-130-panel-2`

Existing package-contents item remains item 1.

---

### 3.4 `ACC-3D-PKM-200` — product_id 140

Current v2 FAQ items: **1**  
Final: **2**

Recover one FAQ that combines the useful purchase facts from the pre-change live card:

- supported slab format for the current variant;
- physical footprint / space required **по фронту**, where that value is explicitly present in the pre-change confirmed data.

The current variant is the PSA-oriented `ACC-3D-PKM-200`. Do not add a disclaimer about unconfirmed CGC/BGS fit and do not claim tested stability or a `зручний кут`.

New IDs:
- `bs-faq-prod-acc-3d-pkm-200-button-2`
- `bs-faq-prod-acc-3d-pkm-200-panel-2`

Existing package-contents item remains item 1.

---

### 3.5 `ACC-3D-PKM-700` — product_id 142

Current v2 FAQ items: **1**  
Final: **2**

Recover the use/maintenance question:

- function to preserve: **whether the rotating display needs assembly and/or lubrication before normal use**;
- source the answer from the pre-change live FAQ;
- do not turn this into a performance claim about smoothness, durability or stability;
- do not duplicate the already-restored capacity facts (`6 on display + up to 38 inside`) from the body/attributes.

New IDs:
- `bs-faq-prod-acc-3d-pkm-700-button-2`
- `bs-faq-prod-acc-3d-pkm-700-panel-2`

Existing package-contents item remains item 1.

---

### 3.6 `FIG-LUFFY-500` — product_id 134

Current v2 FAQ items: **1**  
Final: **2**

Recover the product-comparison FAQ:

- function to preserve: **how the movable Luffy figure differs from the Luffy panel and Luffy picture/card-art decor products**;
- keep the distinction practical:
  - `FIG-LUFFY-500` = movable segmented figure;
  - `FIG-LUFFY-400` = silhouette panel;
  - `FIG-LUFFY-410` = line-art picture/decor;
- if the pre-change answer contains valid internal links, retain them only with the current `/product/` prefix and only if the destination slug already exists in the repository payload/current DB.

New IDs:
- `bs-faq-prod-fig-luffy-500-button-2`
- `bs-faq-prod-fig-luffy-500-panel-2`

Do not alter the mandatory legal+contents FAQ at item 1.

---

### 3.7 `ACC-007-400` — product_id 144

Current v2 FAQ items: **2**  
Final: **4**

Recover two pre-purchase questions from the live card:

1. **Difference from the 360-card album**
   - preserve the actual confirmed differences stated by the live FAQ;
   - compare only to the real neighbouring 360-card album, not to an abstract class of binders.

2. **Whether cards are included**
   - answer clearly that the product is the album/package specified by the card and that demonstration cards are not included, according to the pre-change confirmed FAQ/live body.

New IDs:
- `bs-faq-prod-acc-007-400-button-3` / `...panel-3`
- `bs-faq-prod-acc-007-400-button-4` / `...panel-4`

Do **not** restore superseded uncertainty from old drafts about soft sleeves/toploaders. Current owner-confirmed facts already establish:
- standard Pokémon / MTG-size cards fit;
- ordinary soft sleeves fit;
- toploaders do not fit.

Existing v2 items 1–2 remain unchanged.

---

## 4. `PKM-JP-INFX-BBX` — product_id 148: body correction

No additional FAQ is required by this addendum.

The page is heavily centered on Mega Charizard X ex, so the body must explicitly state that opening a full box does not guarantee that specific card.

Insert the following **owner-approved sentence exactly as written** in the Booster Box paragraph, immediately after the sentence that establishes the 30-pack box format / before the paragraph moves on to the next idea:

> 30 бустерів дають значно більше знайомства із сетом, але конкретний Mega Charizard X ex чи інша визначена карта в коробці не гарантуються виробником.

Do not paraphrase this sentence.

Do not alter the current Inferno X FAQ, Meta or set-mapping text.

---

## 5. Canonical FAQ markup still applies

The parent handoff's accordion contract remains mandatory.

For every recovered item:

```html
<div class="bs-faq-item">
<h3 class="bs-faq-question"><button aria-controls="...panel-N" aria-expanded="false" class="bs-faq-toggle" data-bs-faq-toggle="" id="...button-N" type="button"><span>Питання</span></button></h3>

<div aria-labelledby="...button-N" class="bs-faq-panel" hidden="" id="...panel-N" role="region">
<p>Відповідь.</p>
</div>
</div>
```

Requirements:
- no second FAQ `<section>`;
- no legacy `<div class="bs-faq-accordion">`;
- no legacy `<section class="bs-faq">`;
- `button.id ↔ panel.aria-labelledby`;
- `button.aria-controls ↔ panel.id`;
- IDs unique within the description;
- preserve `hidden=""`;
- the whole final description is still **HTML-entity-encoded before DB write**, exactly as required by the parent handoff.

---

## 6. Acceptance-criteria overrides

This addendum changes the FAQ count expectations in parent §5.

### Replace the old FAQ-count criterion

**Old:**
> 43 items across the 28 updated cards; full 30-card distribution = 17 cards with 1 item, 8 with 2, 5 with 3.

**New:**
- the 28 existing cards updated by WP1 contain **52 FAQ items** after this addendum;
- across those 28:
  - **11 cards** have 1 FAQ item;
  - **11 cards** have 2;
  - **5 cards** have 3;
  - **1 card** has 4.
- across the full 30-card content set (including `BR-CHARM-200` and the still-out-of-scope `PKM-JP-SVEL-SET`) the content definition becomes **57 FAQ items**:
  - **11 cards** with 1;
  - **12 cards** with 2;
  - **6 cards** with 3;
  - **1 card** with 4.

The patch's run output must print the **52-item count for the 28 rows it actually updates**.

### Add these assertions

- [ ] `ACC-3D-PKM-110` decoded FAQ item count = 3.
- [ ] `ACC-3D-PKM-120` = 2.
- [ ] `ACC-3D-PKM-130` = 2.
- [ ] `ACC-3D-PKM-200` = 2.
- [ ] `ACC-3D-PKM-700` = 2.
- [ ] `FIG-LUFFY-500` = 2.
- [ ] `ACC-007-400` = 4.
- [ ] `PKM-JP-INFX-BBX` still has the v2 FAQ count, and its decoded body contains the exact approved `Mega Charizard X ex ... не гарантуються виробником` sentence once.
- [ ] No recovered FAQ creates a duplicate `id`, `aria-controls` or `aria-labelledby` value.
- [ ] No recovered FAQ reintroduces a claim that conflicts with the v2 body/current attributes.

All other acceptance criteria from the parent handoff remain unchanged.

---

## 7. Patch/report implications

WP1 must treat this addendum as an overlay on top of the repository v2 release package.

Recommended implementation order inside the patch generator:

1. load the audited v2 body + FAQ payload from the repository release archive;
2. read and back up the current pre-change DB rows;
3. recover the explicitly listed legacy FAQ meaning from those pre-change rows;
4. merge only those items into the canonical v2 FAQ sections;
5. insert the approved Inferno X sentence;
6. run all old parent assertions plus the updated FAQ-count assertions above;
7. entity-encode the final HTML;
8. write the same 28 target rows and no others.

The diagnostic report for WP1 must contain a small table:

| SKU | old live FAQ count | v2 FAQ count | final count | action |
|---|---:|---:|---:|---|
| ACC-3D-PKM-110 | ... | 1 | 3 | recovered 2 |
| ACC-3D-PKM-120 | ... | 1 | 2 | recovered 1 |
| ACC-3D-PKM-130 | ... | 1 | 2 | recovered 1 |
| ACC-3D-PKM-200 | ... | 1 | 2 | recovered 1 |
| ACC-3D-PKM-700 | ... | 1 | 2 | recovered 1 |
| FIG-LUFFY-500 | ... | 1 | 2 | recovered 1 |
| ACC-007-400 | ... | 2 | 4 | recovered 2 |
| PKM-JP-INFX-BBX | ... | unchanged | unchanged | body sentence added |

Populate `old live FAQ count` from the actual pre-change DB row; do not hard-code a guessed value.

---

## 8. No scope expansion

This addendum does **not** change:

- WP2 category patch;
- WP3 `BR-CHARM-200` creation;
- live names of the 28 existing products;
- existing SEO URLs;
- Meta rules from the parent handoff;
- price/status/quantity/image rules;
- category mappings;
- 3D attributes already specified in the parent handoff;
- checkout/payment/schema/Merchant scope.

### `PKM-JP-SVEL-SET`

Keep it out of WP1/WP2/WP3.

The owner has since discussed its slug/RRP separately, but creation of that product still requires its **own explicit create-patch handoff**. Do not silently fold it into this wave.

---

## 9. Final instruction to executor

Use:

1. `handoff_CONTENT-QUALITY_patch-30-cards_20260827.md` as the parent execution contract;
2. this file as the **higher-priority addendum** for the eight affected cards / FAQ-count assertions.

If the two files conflict on FAQ counts or the eight edits above, **this addendum wins**.

Do not commit, push or deploy. Author/revise the patch file and diagnostic only; owner performs the production run after review.
