# CONTENT-QUALITY — QA: ready-copy addendum v2 + 7 kit-card SKUs

Date: 2026-08-28 | Task: `CONTENT-QUALITY` / `3D-P-CARDCONTENT` | Author: Claude (chat)
Inputs: `addendum_CONTENTQUALITY_patchfaqrecovery_READYCOPY_20260828.md`, `handoff_BOOSTERSHOP_kitcardproductcards_patch_20260828.md`
Evidence: full parse of both payloads + `backup-8.24.2026_10-35-09_boosters` (live FAQ answers, attribute registry, manufacturer table, seo_url keywords, product 134/135/136 attribute values) + `plans/3D-P_sku-naming-convention_20260807.md` (ред. 2–4)

**Addendum v2 — `Review OK; потрібні правки перед патчем`.** Mechanically flawless; two of the nine ready answers contradict the live factual record on the very SKUs the addendum's own §2 precondition check names.

**7 kit-card SKUs — `Return for changes`.** Copy quality is good, markup is correct, but the batch conflicts with the SKU/name canon on three products, repeats a mandatory legal sentence verbatim, and needs a DB decision before any patch.

---

# Part A — Addendum v2

## A1. Verified correct

| Check | Result |
|---|---|
| New FAQ item blocks | 9, all structurally valid against the CORE §9.1 contract |
| `h3.bs-faq-question` → `button.bs-faq-toggle` → `<span>`; panel `hidden=""` + `role="region"` + `<p>` | 9 / 9 |
| ARIA two-way (`button.id` ↔ `panel.aria-labelledby`, `button.aria-controls` ↔ `panel.id`) | 9 / 9 |
| New HTML ids | 18, all unique, all continue the existing per-card sequence |
| Legacy markup, second `<section>` | 0 |
| Final WP1 total | **52** items — arithmetic reproduced independently |
| Final distribution | 11 × 1, 11 × 2, 5 × 3, 1 × 4 — matches §5 exactly |
| Unique button/panel ids across 52 items | 104 — matches §6 |
| Exact duplicate FAQ answers across the merged 52 | 0 |
| New verbatim sentence repeats introduced by the addendum | 0 (the three pre-existing repeats are unchanged and already accepted) |
| Inferno X paragraph | The replacement is the accepted paragraph with the approved sentence inserted after the first sentence; the rest is byte-identical, and the sentence occurs exactly once |

Fact-checked against the pre-change live rows, and **correct**:

- `ACC-3D-PKM-110` item 2 — live: standard 63×89 mm card in a soft sleeve, Pokémon / One Piece / MTG / Yu-Gi-Oh!. The answer is consistent and drops nothing material.
- `ACC-3D-PKM-110` item 3 — live: *"середня — під топлоадер, велика — під магнітний акриловий кейс. Мала розрахована саме на м'який протектор."* The answer reproduces that mapping exactly.
- `ACC-3D-PKM-200` item 2 — live: *"Габарити підставки — 89×38×20 мм … орієнтуйтеся на 89 мм по фронту"* and *"Під корпус PSA"*. Numbers and slab format match; the CGC/BGS hedge is correctly not reintroduced.
- `ACC-3D-PKM-700` item 2 — live: *"Обертовий механізм друкується як частина конструкції, окремих підшипників чи змазки він не потребує."* Same meaning, no new performance claim.
- `ACC-007-400` item 3 — live: *"тут чотири кишеньки на сторону й до 400 карток, у меншому — 3×3 кишеньки та до 360 карток."* 50 sheets × 4 pockets × 2 sides = 400 ✓. The neighbouring product exists (`ACC-007-360`, product_id 112) and its own meta reads *"формат сторінок 3×3 та еластичний фіксатор"* — so the elastic closure claim is supported by that product's own record.
- `ACC-007-400` item 4 — consistent with the live answer and with the accepted body.
- `FIG-LUFFY-500` item 2 — `FIG-LUFFY-400` carries attribute `Колір = чорний` in the live DB, so *"плаский чорний силует"* is confirmed, not invented.

## A2. Two answers contradict the live record — must be fixed before the patch

The addendum §2 requires the executor to abort on exactly this condition for `ACC-3D-PKM-120`, `-130` and `-700`. Two of those three fail. Fixing the copy now is cheaper than having the executor stop mid-build.

### A2.1 `ACC-3D-PKM-120` — a conditional answer turned unconditional

Live: *"Залежить від товщини конкретного топлоадера. Паз розрахований під стандартний 35PT; якщо картка додатково в протекторі й топлоадер товщий, посадка буде щільнішою."*

Addendum: *"Так. Якщо картка в м'якому протекторі вже нормально поміщається в стандартний топлоадер, для підставки нічого не змінюється: вона тримає зовнішній корпус топлоадера. Нестандартні або товстіші топлоадери можуть мати інші зовнішні габарити."*

The live record says the fit gets tighter in exactly the case the new answer calls unchanged, and it names the 35PT slot spec that the new answer drops. The trailing caveat softens it but does not restore the point. As written this is a promise the product may not keep on a sleeve-plus-toploader combination — the most common configuration a buyer would ask about.

Fix: keep the live answer's conditional shape and its 35PT reference.

### A2.2 `ACC-3D-PKM-130` — an unconfirmed specification, apparently borrowed from a sibling

Live: *"Під стандартний магнітний акриловий кейс формату One Touch. Товщина кейса залежить від того, скільки карток він розрахований тримати — перед покупкою варто звірити свій формат із габаритами паза."*

Addendum: *"На стандартний магнітний акриловий кейс 35PT для однієї TCG-картки. Підставка розрахована саме на цей формат."*

Two problems. The live copy deliberately refuses to pin a PT size and tells the buyer to check their own case; the new answer pins 35PT as a confirmed fact. And 35PT is the number the live record gives for the **`ACC-3D-PKM-120` toploader slot** — the value appears to have crossed products. One Touch cases ship in several thicknesses, so a wrong number here sells a stand that will not hold the buyer's case.

Fix: either restore the One Touch wording with the check-your-format caveat, or supply an owner-measured PT value for this stand specifically.

## A3. Worth knowing before the patch runs

`ACC-3D-PKM-700` currently has a literal drafting placeholder live in its FAQ answer in the database:

```
{{потрібні дані: точна кількість карток у топлоадерах, яку вміщує ACC-3D-PKM-700}}
```

The product is `status = 0`, so no buyer has seen it, and WP1 replaces the whole description — the patch removes it. Recording it so it is understood as fixed by this wave rather than discovered later.

---

# Part B — 7 kit-card SKUs

## B1. Verified correct

- SKU formula: all seven match `PREFIX-MNEMONIC-XYZ`; `FIG- 3__` is the registered category for "модель-кіт на литнику", and the type word *Фігурка-конструктор* is the canon's own label for that category.
- FAQ markup: 23 items, 46 ids, all unique, ARIA resolves both ways, no legacy markup, no structural error.
- Self-reported counts reproduced: 7 cards, 23 FAQ items, 0 exact duplicate H2, 0 exact duplicate body sentences, 0 exact duplicate FAQ questions, 0 exact duplicate FAQ answers.
- `Покемон` appears exactly once in every body — 7 / 7.
- Meta Title 49–56 chars, Meta Description 115–120 — all inside 63 / 155.
- No official / licensed / original-merch claim anywhere; layer lines and the kit-card state are disclosed rather than hidden; 14+ everywhere; no toy framing.
- Franchise is in parentheses after the descriptive phrase, never leading.
- All seven SEO URLs are free in the live `ocp5_seo_url`; no SKU collision; manufacturer `Booster Shop` exists as manufacturer_id 17, so the native field mapping works.
- No `<a href>` in any body, so the `/product/` 404 class does not apply to this batch.

## B2. Blocking

### B2.1 Character names are in English; the canon says Ukrainian, and names three of these SKUs explicitly

`plans/3D-P_sku-naming-convention_20260807.md`, name formula: *"**{Персонаж/Тема}** — повне ім'я українською, не мнемоніка з SKU."*

The same file's worked example table gives these exact SKUs:

| SKU | Canon name | Handoff name |
|---|---|---|
| `FIG-JIGGL-300` | Фігурка-конструктор **Джигліпаф** (Pokémon) — 3D-друк | Фігурка-конструктор **Jigglypuff** (Pokémon) — 3D-друк |
| `FIG-MEW-300` | Фігурка-конструктор **Мью** (Pokémon) — 3D-друк | Фігурка-конструктор **Mew** (Pokémon) — 3D-друк |
| `FIG-UMBRE-300` | Фігурка-конструктор **Умбреон** (Pokémon) — 3D-друк | Фігурка-конструктор **Umbreon** (Pokémon) — 3D-друк |

Rule 1 of the handoff mandates English names in name, H2, body, FAQ, Meta and keywords. That is a defensible SEO position — Ukrainian buyers do search `Umbreon фігурка` — but it is a change to the canon, not an application of it, and no owner decision records it.

The concrete cost if it ships as is: every 3D product live today uses Cyrillic (Чармандер, Мью, Луффі, Сквіртл, Бульбазавр, Пікачу, Геодуд, Онікс), so category 73 would show `Фігурка Мью в покеболі` next to `Фігурка-конструктор Mew`, and `Брелок Пікачу` next to `Фігурка-конструктор Pikachu` — the same Pokémon spelled two ways one row apart.

Owner decision needed, and it is binary:
- **A** — rename the seven to Ukrainian, matching the canon and the live catalog.
- **B** — keep English, and amend the canon as ред. 5 with the rationale, plus a separate task to decide whether the existing Cyrillic names migrate. Shipping B without amending the canon leaves the naming rule quietly false, which is what produced the `BR-DITTO-200` renumbering last time.

### B2.2 The mandatory legal sentence repeats verbatim across the batch

Hard requirement, owner decision 2026-08-25, now also written into the 3D module v10: *"Wording **must** vary between pages … both question and answer."*

| Times | Sentence | Cards |
|---:|---|---|
| 2 | `Це неофіційний 3D-виріб Booster Shop у тематиці Pokémon; Booster Shop не є ліцензіатом, партнером або афілійованою особою правовласника.` | `FIG-MAGIK-300`, `FIG-UMBRE-300` |
| 3 | `Інші предмети на фото до комплекту не входять.` | `FIG-MAGIK-300`, `FIG-MEW-300`, `FIG-SQUIR-300` |
| 2 | `Інші предмети на фотографіях до комплекту не входять.` | `FIG-GENG-300`, `FIG-JIGGL-300` |

`FIG-JIGGL-300` and `FIG-SQUIR-300` additionally share their disclaimer opening word-for-word and differ only by `або` / `чи`.

The batch QA reports *exact duplicate FAQ answers = 0*, which is true — and is exactly the trap the 2026-08-24 package fell into. The rule is about sentences inside the answers, not whole answers. None of these sentences collides with the existing 30-card wave, so the fix is local to these seven.

### B2.3 `Складання` is not an attribute that exists

The live `ocp5_attribute_description` holds 40 attributes. `Складання` is not one of them. Using it means creating a new attribute — a DB change with its own owner approval and rollback obligation under `AGENTS.md` convention 6 — while handoff §6.6 tells the executor to map onto existing IDs and §6.8 forbids creating `Формат`.

The fact is already stated in every body (*"Клей не потрібен"*) and in the Umbreon and Gengar FAQ. Cheapest resolution is to drop the attribute; creating it is an owner call, not an executor default.

## B3. Non-blocking, but fix in the same pass

| ID | What | Evidence |
|---|---|---|
| N1 | `Призначення` missing on all 7. Every live 3D product carries it (`декоративний / колекційний виріб`, attribute 54). | products 134/135/136 |
| N2 | `Рухомі елементи` missing on all 7 (attribute 41). Live siblings state it explicitly, including the negative case — 135 and 136 both read `немає`. For a kit whose assembled model is static, the honest value is a negative, not an absence. | products 135/136 |
| N3 | `Матеріал` value is `пластик PLA`; every live 3D product stores `Пластик PLA`. Different capitalisation means two distinct values in the attribute table and two entries in any future filter. | product 134 |
| N4 | `Маса` is given as a bare `29,67 г`; live convention is `орієнтовно 15,84 г`. These are slicer figures, so the hedge is the accurate form. | product 134 |
| N5 | `Розміри` embeds a clause: `112×127×2 мм, kit card до складання`. Live values are bare dimension strings. The clarification matters — but it belongs in the FAQ, where the batch already puts it three times — and an attribute value that is not a pure dimension breaks any later feed or filter use. | products 134/135/136 |
| N6 | SEO URLs are `jigglypuff-figurka-konstruktor`; the live 3D convention is `<тип>-<персонаж>-<франшиза>-3d-druk` (`brelok-charmander-pokemon-3d-druk`, `panno-luffy-one-piece-3d-druk`, `brelok-spiner-ditto-pokemon-3d-druk`). The new form reverses type and character and drops both the franchise token and the `-3d-druk` suffix. URLs are the most expensive field to change after publication. | live `ocp5_seo_url`; canon ред. 2 |
| N7 | Mnemonics `GENG` and `MAGIK` are not in the canon's registered list. Both are valid by the formula (≤5 chars, readable), but the registry is the thing that prevents a future collision — it needs the two rows. | canon §"Мнемоніки, вже використані" |
| N8 | Every H2 is `<Name> kit card — <phrase>`, 7 / 7. Not a rule violation and the phrases themselves differ, but it is the swapped-name heading pattern this project has flagged before; worth one editorial pass. | batch |
| N9 | No price, quantity or status anywhere in the handoff, and §6.13 forbids the executor from setting them. The products cannot be created without those values — same gap as `BR-CHARM-200`. Owner input needed before the patch, or they are created with the 1 UAH placeholder and that is stated. | handoff §6.13 |

## B4. Not checked here

Photos. All seven would be created without images, which is an independent Merchant Center rejection and an activation gate — already tracked for the wave, not a defect of this batch.

---

# What I need before rebuilding the handoff

1. `ACC-3D-PKM-120` and `ACC-3D-PKM-130` — corrected answers, or approval to keep the live wording verbatim.
2. Kit-card names — option A or option B from §B2.1.
3. `Складання` — create the attribute, or drop it.
4. Price / status for the seven, or confirmation to use the 1 UAH placeholder.

Items §B2.2, §B3 N1–N5 and N7 are content and registry edits that can happen in parallel; they do not block the decisions above.
