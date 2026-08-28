# Fix list — CONTENT-QUALITY wave, round 3: attribute names that do not exist

Date: 2026-08-28 | Parent: `handoffs/handoff_CONTENT-QUALITY_wave_20260828.md`
Executor: same as rounds 1–2.

Two files change: **WP1** and **WP4**. WP2, WP3, WP5 and WP6 are unaffected — their attribute names were checked against the live database and all exist.

Cause: two attribute names in WP1 and all thirteen in WP4 were carried from the ChatGPT payload into the handoff without checking them against `ocp5_attribute_description`. The runners encode the handoff faithfully; their `attribute_missing` guard is what stopped this at a dry run. Nothing was written.

**No new attribute is created.** Both fixes map onto attributes that already exist, or drop the write where the fact already lives in the description text.

---

## 1. WP1 — `CONTENT-QUALITY_cards-update-28_20260828.php`

### 1.1 Remove two attribute writes

`Місткість дисплея` and `Внутрішнє зберігання` do not exist in `ocp5_attribute_description` (39 attributes, ids 12–55). They came from `02_FINAL_3D_20_PATCH.md` §"Meta / attributes — локальні правки" and were never real.

Remove these four writes entirely:

```
142  Місткість дисплея      = 6 топлоадерів
142  Внутрішнє зберігання   = до 38 топлоадерів
143  Місткість дисплея      = 6 слабів
143  Внутрішнє зберігання   = до 6 слабів
```

Nothing is lost for the buyer: both facts are already in the v2 body copy this same patch writes —
`ACC-3D-PKM-700`: *"Шість карток можна показувати по зовнішніх гранях, а всередині зберігати ще до 38 топлоадерів."*
`ACC-3D-PKM-710`: *"До 6 слабів можна виставити по зовнішніх гранях, а ще до 6 — зберігати всередині корпусу."*

### 1.2 Keep one attribute write

`Сумісність` exists — **attribute_id 55**, currently `грейджені слаби PSA` on product 143. Keep:

```
143  Сумісність (55) = PSA, BGS, SGC, слаби на магніті
```

Owner-confirmed 2026-08-25. Product 142 keeps its live `Сумісність` value untouched — this patch never wrote it.

### 1.3 Consequences inside the runner

- `$attrNames` drops to three entries: attribute 43's name, attribute 44's name, `Сумісність`.
- `$attrTargets` / `$expectedAttrs` drop the four capacity rows and keep `[143, 55]`.
- The `already_applied` comparison and `restore.sql` follow automatically from the reduced target list.
- **The payload changes, so `PAYLOAD_SHA256` must be regenerated.** Everything else in the payload — all 28 descriptions, 52 FAQ items, the Inferno X paragraph, the meta fields — stays byte-identical.
- Every existing assertion stays: `faq_total_invalid` at 52, per-SKU counts, `inferno_sentence_invalid`, `name_changed`, `storage_encoding_invalid`, `commercial_field_changed`, the entity-encoding check.

---

## 2. WP4 — `CONTENT-QUALITY_create-svel-set_20260828.php`

### 2.1 The problem

All thirteen attribute names in the SVEL-SET payload are invented — `TCG`, `Назва продукту`, `Код продукту`, `Формат`, `Видання`, `Мова карт`, `Карт у колоді`, `Склад колоди`, `Головна карта`, `Основний тип Енергії`, `Стан упаковки`, `Тип запечатування`, `Дата релізу`. None exists in the database. The live sealed catalogue uses a different, established vocabulary.

Reference — the closest live product, `OP-JP-ST32-STD` (product 120, a starter deck), carries eight attributes:

| id | name | value on 120 |
|---:|---|---|
| 12 | `Мова` | Японська (Japanese) |
| 13 | `Назва сету` | Стартова колода ST-32 (One Piece Card Game) |
| 14 | `Рік випуску` | 2026 |
| 17 | `Стан` | Новий, нерозпакований (Sealed) |
| 20 | `Виробник` | Bandai |
| 21 | `Тип пакування` | Starter Deck |
| 24 | `Додатковий вміст` | 10 карт DON!!, 3 картки-індекси |
| 49 | `Кількість карток у колоді` | 51 (50 карт + 1 лідер), усього 15 різних |

### 2.2 Replacement attribute set for `PKM-JP-SVEL-SET`

**Confirmed by the owner 2026-08-28.** Eight existing attributes, every value taken from the accepted payload in `01_FINAL_NON3D_10.md`:

| id | name | value |
|---:|---|---|
| 12 | `Мова` | `Японська (Japanese)` |
| 13 | `Назва сету` | `Starter Set Terastal Loudbone ex` |
| 14 | `Рік випуску` | `2023` |
| 17 | `Стан` | `Новий, нерозпакований (Sealed)` |
| 20 | `Виробник` | `The Pokémon Company` |
| 21 | `Тип пакування` | `Starter Set` |
| 24 | `Додатковий вміст` | `ігрове поле, монета Pokémon, аркуш жетонів шкоди та маркерів, посібник з правил` |
| 49 | `Кількість карток у колоді` | `60` |

Dropped, because no existing attribute holds them and the description already states each one: `Код продукту` (SVEL), `Головна карта`, `Основний тип Енергії`, `Склад колоди`, `Тип запечатування`. The body and FAQ carry all five in prose.

`Рік випуску = 2023` comes from the payload's release date 22.09.2023. `Виробник` matches `manufacturer_id = 11`, which the runner already assigns from the template.

### 2.3 Consequences inside the runner

- Replace the payload's `attributes` array with the eight rows above; regenerate `PAYLOAD_SHA256`.
- Nothing else in WP4 changes: slug `Pokemon-Starter-Set-Terastal-Loudbone-ex`, price `650.0000`, `status = 0`, `quantity = 0`, categories 59 + 64, weight 400 g, dimensions 220 × 160 × 60, the `physical_payload_missing` guard, and the thorough `already_applied` check all stay.
- The `Може трапитись у Mystery Box` post-insert assertion must go — that attribute is not in the sealed set and was never part of this product. Replace it with a check that all eight attribute rows were written.

---

## 3. Do not do

- Do not create `Місткість дисплея`, `Внутрішнє зберігання`, or any of the thirteen SVEL names. Creating an attribute needs its own owner-approved patch with a group assignment and sort order, and neither case justifies one.
- Do not map onto `Сумісність з картками` (33). Products 142 and 143 use `Сумісність` (55); 33 is a different, older attribute used elsewhere.
- Do not touch WP2, WP3, WP5, WP6.

## 4. Acceptance

- [ ] `php -l` passes on both files; no PHP 8.1+ syntax
- [ ] WP1 dry-run reaches `existing_descriptions=28`, `faq_items=52` and the seven `faq_<SKU>` lines without an `attribute_missing` error
- [ ] WP1 resolves exactly three attribute names, and its only attribute writes are attribute 43 on 19 products, attribute 44 on five, and `Сумісність` on 143
- [ ] WP1 payload hash regenerated; the 28 descriptions and 52 FAQ items are unchanged from round 2 — prove it by diffing the decoded description strings
- [ ] WP4 dry-run reaches `create_sku=PKM-JP-SVEL-SET` without an `attribute_missing` error
- [ ] WP4 writes exactly eight attribute rows, all with ids from the existing set
- [ ] neither patch inserts into `ocp5_attribute` or `ocp5_attribute_description`
- [ ] a preflight that resolves every attribute name in the payload runs before the first write in both files, as it already does

Deliver both files into `patches/` plus a short diagnostic each. Do not commit, push or deploy.
