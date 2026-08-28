# CONTENT-QUALITY — release package v2 audit (2026-08-25 v2)

Date: 2026-08-27 | Task: `CONTENT-QUALITY` | Author: Claude (chat)
Input: `BOOSTERSHOP_CONTENTQUALITY_RELEASE_20260825_v2.zip` (21 files, owner-supplied)
Supersedes as review target: `diagnostics/CONTENT-QUALITY_release-package-audit_20260825.md` (audit of the 2026-08-24 package)
Evidence base: package payload + `backup-8.24.2026_10-35-09_boosters.tar.gz` (`boosters_ocart49.sql`)

**Verdict: Review OK — patch handoff can be built. One owner decision and one mandatory patch-level fix must be carried into the handoff.**

All three blockers from the 2026-08-25 audit are closed. What remains is not a defect in the delivered markup; it is a content-depth regression against the FAQ already stored in the database, which the owner has to accept or reject knowingly.

---

## 1. Closed blockers — measured, not taken on trust

### 1.1 FAQ markup contract — PASS

Parsed all 30 FAQ blocks from `04_FAQ_ACCORDION_PAYLOAD_30.md` and validated each against the CORE §9.1 contract:

| Check | Result |
|---|---|
| Cards with a FAQ block | 30 / 30 |
| Wrapper `section.bs-faq-accordion` + `data-bs-faq-accordion=""` + `data-bs-faq-id` | 30 / 30 |
| Title exactly `<h2 class="bs-faq-title">FAQ</h2>` | 30 / 30 |
| `div.bs-faq-item` → `h3.bs-faq-question` → `button.bs-faq-toggle` → `<span>` | 48 / 48 items |
| `type="button"`, `aria-expanded="false"`, `data-bs-faq-toggle=""` | 48 / 48 |
| Panel `div.bs-faq-panel hidden="" role="region"` + `<p>` | 48 / 48 |
| `button.id` ↔ `panel.aria-labelledby` and `button.aria-controls` ↔ `panel.id` | 48 / 48 |
| Unique HTML IDs across the whole payload | 96 / 96 |
| ID scheme `bs-faq-<faq-id>-button-<n>` / `-panel-<n>` | 48 / 48 |
| Empty accordions | 0 |
| Legacy `<section class="bs-faq">` or `<div class="bs-faq-accordion">` | 0 |
| Structural errors total | **0** |

Verified against the live database, not only against the handoff: the backup contains 94 `data-bs-faq-id` values (77 product, 14 category, 3 legacy-style). Live product blocks use `<h2 class="bs-faq-title">FAQ</h2>` (85 occurrences), alphabetically ordered attributes and the same `-button-<n>` / `-panel-<n>` scheme. The payload reproduces this form exactly.

The payload also **reuses the `data-bs-faq-id` values that already exist in the database** for all 28 cards that exist there (`prod-<sku-lowercase>`). Only `BR-CHARM-200` and `PKM-JP-SVEL-SET` are new ids. No id drift, no orphaned anchors.

### 1.2 Verbatim repetition in FAQ answers — PASS for the hard rule

- exact duplicate FAQ questions: **0**
- exact duplicate FAQ answers: **0**
- IP-themed 3D mandatory legal answers rewritten product-by-product: **13 / 13**, all distinct in both wording and sentence order (verified by reading all 20 3D answers, not by counting hashes)

Sentence-level scan across all 48 answers (sentences ≥25 chars appearing on ≥2 cards) returns **3 repeats**, all outside the hard rule — see §3.1.

### 1.3 Buyer-critical facts moved into prose — PASS

- `ACC-3D-PKM-700`: `<strong>Шість карток можна показувати по зовнішніх гранях</strong>` + "до 38 топлоадерів" in body.
- `ACC-3D-PKM-710`: "До 6 слабів … ще до 6 усередині" in body; PSA / BGS / SGC / magnetic slabs rendered as a `<ul>`.
- `PKM-JP-SVEL-SET`: package contents rendered as a `<ul>` (60-card deck, playmat, coin, damage-counter sheet, rulebook).

### 1.4 Scope discipline — PASS

Diff of v2 against the accepted 2026-08-24 package, with FAQ blocks masked out:

- `01_FINAL_NON3D_10.md`: one change only — the `PKM-JP-SVEL-SET` contents list. Nothing else in any body, Meta, attribute, source or link block.
- `02_FINAL_3D_20_PATCH.md`: one change only — the `ACC-3D-PKM-710` compatibility list.
- `03_NEW_SKU_BR-CHARM-200_FULL.md`: FAQ markup conversion only.
- `05_CATEGORY_AND_GLOBAL_PATCH.md`: byte-identical to the accepted `04_CATEGORY_AND_GLOBAL_PATCH.md`.

`04_FAQ_ACCORDION_PAYLOAD_30.md` is byte-identical to the FAQ embedded in files 01–03 for all 30 cards — the convenience file cannot desynchronise the patch.

Forbidden lexicon scan (`unweighed`, weighing/sorting/guaranteed-pull vocabulary, internal terms): **0 hits**.

### 1.5 Skills v10 — PASS

- CORE §9.1 carries the accordion contract verbatim, all 9 rules, plus the explicit statement that legacy forms are not used.
- CORE §8.7 (confirmed buyer-critical fact must appear in prose) and §8.8 (three or more homogeneous elements read as a list) added as principles, no numeric thresholds — matches the owner decision of 2026-08-25.
- CORE §9 now allows `<a>` for confirmed internal links only.
- QA §7.1 adds the structural accordion check; §7.2 flags facts that exist only in attributes; §7.3 checks list formatting.
- QA §10 duplicate check now covers FAQ **questions and answers** — the gap that produced the 24.08 defect.
- The Booster Box `2–4 FAQ` numeric range is removed; "quantity is not a QA metric" is stated explicitly.
- 3D module: "Wording **must** vary between pages" is now a hard requirement covering both question and answer.

Self-reported QA in `06`/`07` (30 cards, 48 items, 96 ids, 0 structural errors, 13/13 legal rewrites) — every number independently reproduced and correct.

---

## 2. Mandatory patch-level fix

### 2.1 Internal links still lack the `/product/` prefix — **5 occurrences, not 4**

`diagnostics/CONTENT-QUALITY_link-fixes_20260825.md` documents this fix against the 24.08 package and lists four places. The v2 package moved the `FIG-LUFFY-410` comparison sentence into the FAQ, so the convenience file now carries a fifth copy:

| File | Line | Anchor |
|---|---:|---|
| `02_FINAL_3D_20_PATCH.md` | 51 | `/brelok-kliker-charmander-pokemon-3d-druk` (`BR-CHARM-100`) |
| `02_FINAL_3D_20_PATCH.md` | 159 | `/brelok-charmander-pokemon-3d-druk` (`BR-CHARM-200`) |
| `02_FINAL_3D_20_PATCH.md` | 438 | `/panno-luffy-one-piece-3d-druk` (`FIG-LUFFY-410`, inside FAQ) |
| `03_NEW_SKU_BR-CHARM-200_FULL.md` | 13 | `/brelok-charmander-pokemon-3d-druk` |
| `04_FAQ_ACCORDION_PAYLOAD_30.md` | 587 | `/panno-luffy-one-piece-3d-druk` |

All five must become `/product/<slug>`. Slug existence re-verified in `backup-8.24.2026_10-35-09_boosters`: `panno-luffy-one-piece-3d-druk` present, `brelok-charmander-pokemon-3d-druk` present, `brelok-kliker-charmander-pokemon-3d-druk` **absent** — it is created together with `BR-CHARM-200` in the same patch.

This is a patch-executor task, not a content defect. It must be stated in the handoff with all five locations, or the copy in file 04 will survive unfixed if the release operation updates FAQ separately.

### 2.2 `SHA256SUMS.txt` lists a file that is not in the archive

`booster_shop_content_skills_20260825_v10.zip` is listed but absent. 20 of 21 listed files verify OK; the skills zip cannot be verified. The `skills_v10/` directory contents do verify, so the material is present — only the redundant zip is missing. Administrative, but the manifest is broken as shipped.

---

## 3. Owner decision required before the patch

### 3.1 FAQ depth drops sharply against what is already in the database

The 2026-08-25 audit called this "FAQ згорнувся" and it is **not** resolved in v2 — the item counts are unchanged from the 24.08 package. Measured against the live `ocp5_product_description` in the 24.08 backup:

| Group | Cards | Live FAQ items | v2 FAQ items | Δ |
|---|---:|---:|---:|---:|
| non-3D sealed + accessory (existing) | 9 | 27 | 22 | −5 |
| 3D (existing) | 19 | 52 | 21 | −31 |
| **Existing cards total** | **28** | **79** | **43** | **−36 (−46%)** |
| New cards (`BR-CHARM-200`, `PKM-JP-SVEL-SET`) | 2 | — | 5 | — |

v2 distribution: 17 cards with exactly 1 item, 8 with 2, 5 with 3.

Part of the drop is the owner-approved merge of 2026-08-22 — licence disclaimer + contents + photo collapsed into one item. That legitimately turns a 2-item keychain card into a 1-item card, and all five `BR-*` keychains are correct on that basis.

The rest is different. The package rationale in `02` §"Інші глобальні правила" says "додаткові FAQ, які повторювали body, прибрані". For roughly eight cards that is not what happened — the removed question is not answered anywhere in the v2 body or FAQ:

| Card | Live question removed | Covered in v2 body/FAQ? |
|---|---|---|
| `ACC-3D-PKM-110` | "Чи підходить підставка для карток Pokémon, One Piece і Magic?" | no |
| `ACC-3D-PKM-110` | "Яку підставку взяти, якщо картка в топлоадері або магнітному кейсі?" | no |
| `ACC-3D-PKM-120` | "Чи підійде підставка для топлоадера з карткою в протекторі всередині?" | no |
| `ACC-3D-PKM-130` | "Під який розмір магнітного кейса розрахована підставка?" | no |
| `ACC-3D-PKM-200` | "Під який формат слаба розрахована підставка?" | partial (PSA named in prose) |
| `ACC-3D-PKM-200` | "Скільки місця займає одна підставка по фронту?" | no |
| `ACC-3D-PKM-700` | "Чи потрібно щось збирати або змащувати?" | no |
| `FIG-LUFFY-500` | "Чим ця фігурка відрізняється від панно й картини Луффі?" | no |
| `FIG-GEOD-511` | "Фігурка стоїть чи лежить?" | partial |
| `ACC-007-400` | "Чим цей альбом відрізняється від альбому на 360 карток?" | no |
| `ACC-007-400` | "Чи входять картки в комплект?" | no |
| `PKM-JP-INFX-BBX` | "Чи гарантується Mega Charizard X ex?" | no — see §3.2 |

Five of these are compatibility or neighbour-model questions. That is the same class the live calibrator measures at 65% of live booster cards ("відмінність від сусіда"), and it is the class CORE §8.7 and QA §7.2 were just written to protect. For `ACC-3D-PKM-130` and `ACC-3D-PKM-200` the compatibility fact appears to survive only in attributes — which their own new QA rule would flag.

`ACC-3D-PKM-700` and `ACC-3D-PKM-710` are the counter-example and show the intended pattern working: their capacity and compatibility questions were removed **and** the facts were promoted into the body. The other eight were removed without that step.

Two options, both defensible:

- **A — ship as is.** Pages become shorter and cleaner; the lost answers are pre-purchase detail on products that are all `status = 0` and can be extended in the second content wave.
- **B — one more ChatGPT pass** restoring roughly eight targeted questions (or the equivalent sentences in the body), on the named cards only, with no other change. Cost: one round trip; the markup contract is already proven, so re-review is a re-run of the same script.

This is a content-depth judgement, not a technical failure. The patch can be built either way; it should not be built without the decision, because rebuilding a 30-card content patch twice is the expensive path.

### 3.2 `PKM-JP-INFX-BBX` loses its only non-guarantee statement

The body names Mega Charizard X ex as the reason to buy in the first heading and three further times. The live FAQ carried "Чи гарантується Mega Charizard X ex?"; v2 replaces it with two mechanics questions, and no sentence anywhere on the card states that a specific card is not guaranteed.

Every other sealed card in the batch keeps such a statement: `YGO-JP-BETB-BBX` and `YGO-JP-BETB-BST` explicitly ("Конкретна карта або рідкість … не гарантується"), `PKM-JP-TGTR-BBX` explicitly, `PKM-JP-STES-BBX` as "Конкретні карти залишаються випадковими", `YGO-JP-QCAC-BBX` through the FAQ answer about one box not yielding all 100 cards, the two `*-BST` packs through "5 випадкових карт".

This does not violate the brand rule as written — the rule forbids implying a guarantee, and nothing here implies one. It is a regression against the live card and the weakest point of the batch on a page built entirely around one chase card. One sentence in the body closes it, whichever way §3.1 is decided.

### 3.3 Contents sentence still repeats verbatim on the non-franchise stands

The last unfixed row of the 2026-08-25 audit table:

| Times | Sentence | Cards |
|---:|---|---|
| 5 | "У комплекті — 1 підставка." | `ACC-3D-PKM-110`, `-120`, `-130`, `-200`, `-300` |
| 2 | "У комплекті — 1 обертова підставка." | `ACC-3D-PKM-700`, `-710` |

Strictly, ChatGPT is within the rules. The hard requirement of 2026-08-25 is variation of the **legal** item, and the owner decision of 2026-08-22 exempts non-franchise `ACC-3D-PKM-*` from the licensing part, so these seven answers carry no legal sentence at all. The package's own QA claim is scoped honestly to "13/13 IP-themed" and is true.

The second half of each answer is individualised per product ("Картка, топлоадер …", "Грейджена картка, слаб …", "Магнітний кейс, картка …"), and all seven questions differ. What repeats is one short factual sentence stating the same fact about seven near-identical products.

Recommendation: leave it. Rewording "У комплекті — 1 підставка." seven ways is the kind of forced uniqueness CORE explicitly warns against. Recorded here so the row is not re-opened next wave.

### 3.4 `05_CATEGORY_AND_GLOBAL_PATCH.md` §1 is stale

The file is byte-identical to the accepted 24.08 version and instructs renaming `Може зустрічатися в Mystery Box Item` → `Може трапитись у Mystery Box`. Attribute 44 in the 24.08 backup already reads `Може трапитись у Mystery Box`, and attribute 43 `Типовий строк виготовлення при відсутності на складі` already exists. The handoff of 2026-08-25 records both.

The handoff to the executor must turn §1 and §2 into verification steps, not create/rename steps, and keep the `Так/Ні` value assignment for the six keychains, which is still outstanding.

---

## 4. Recommendation for the skills, next wave

Two things this wave proved that the modules still do not encode:

1. **URL prefix.** CORE §9 permits `<a>` for confirmed internal links but says nothing about this install's `/product/<slug>` form. The same 404 class will recur. One line in CORE §9 fixes it permanently.
2. **Regression against live content.** Both this defect class and the 24.08 one are invisible to a QA pass that reads only the payload. QA needs one instruction: when a card already exists, diff the new FAQ against the live FAQ and list every removed question together with where its answer now lives. That check would have caught §3.1 and §3.2 inside ChatGPT, before delivery.

---

## 5. Readiness

| Item | State |
|---|---|
| Markup contract | ready, verified against the live database |
| Payload internal consistency (01–04) | ready |
| Scope discipline vs accepted package | ready |
| Skills v10 | ready |
| Checksums | 20/21; one listed file absent from the archive |
| Internal link prefix | must be encoded in the handoff — 5 locations |
| `05` category patch | must be re-scoped to verification for §1–§2 |
| FAQ depth | **owner decision required** |
| `PKM-JP-INFX-BBX` non-guarantee sentence | owner decision, one sentence either way |

Once §3.1 is decided, the patch handoff can be written directly from this package: operation order, per-file targets, the five link substitutions, the category-patch re-scoping, gates and rollback. No further ChatGPT round trip is needed for the markup.
