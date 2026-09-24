# Handoff — 3D-P product name and meta alignment (17 SKUs)

> ## ⛔ COMPLETED BY THE OWNER BY HAND — 2026-08-30. DO NOT BUILD A PATCH FROM THIS.
>
> The owner applied all 17 renames, the Meta Title / Meta Description / keyword
> edits and the six attribute-value corrections manually in the admin panel and
> reported `done = ok`. This file is kept as the specification of what was
> applied and why, not as work to execute. Any runner built from it now would
> abort on its own pre-check, since the live names already match the targets.

> **Amended 2026-08-28 (twice)** — `FIG-LUFFY-410` (product_id 136) gains a
> `, прямокутна` token, and `FIG-LUFFY-400` (product_id 135) becomes
> `Картина Luffy (One Piece), силует` — owner ruled both Luffy wall pieces are
> pictures, not `панно`. Row count unchanged at 17; only those two rows' target
> strings changed. See the notes under §2 and canon ред. 7 §3 / ред. 8 §1.

Date: 2026-08-28
Author: Claude (chat)
Executor: to be assigned by the owner (Codex or Claude Code)
Roadmap: `3D-P-CARDCONTENT` (derived task; no separate Roadmap ID assigned yet)
Canon: `plans/3D-P_sku-naming-convention_20260807.md`, **ред. 5 + ред. 6**

---

## 0. Why this exists

Live 3D-P product names carry three defects:

1. **Cyrillic character names** (`Чармандер`, `Мью`, `Луффі`) while every live
   `seo_url` and the whole description body already use Latin (`charmander`,
   `mew`, `luffy`). Owner decision ред. 5 aligns the name to the URL.
2. **`грейджений`** — a mangled anglicism — in one product name, one Meta Title,
   two keyword sets, one Meta Description and six attribute values. It came from
   the canon's own type table, which was a working draft nobody approved. Owner:
   "робоча назва ≠ канон". The table is corrected in ред. 6.
3. **`мала / середня / велика`** on the three card stands. Size is not the
   differentiator — the container the card sits in is. The live Meta Titles and
   SEO URLs already said so; only the name (H1) was wrong.

## 1. Scope

**In scope:** `name`, `meta_title`, `meta_description`, `meta_keyword` in
`ocp5_product_description` (language_id 4), and two attribute **values** in
`ocp5_product_attribute`.

**Out of scope — do not touch:**

- `seo_url` / `ocp5_seo_url` — **no slug changes at all.** URLs are correct and
  are the most expensive field to change after publication.
- Product **description bodies** (`description`) — the owner is rewriting those
  himself with ChatGPT. Do not edit them, not even to remove `грейджений`.
- Category 72 «Підставки та декор» description — contains 4 occurrences of
  `грейдж`, but it is a description and therefore the owner's. Reported, not
  patched.
- `ACC-3D-PKM-700` and `FIG-PKBL-600` — no changes (owner reviewed, approved
  as-is).
- `FIG-CHARM-001` (product_id 118) — a test record the owner is deleting himself.
  **Do not read, write, or reference it.**
- The seven `FIG-*-300` kit cards (product_id 154–160) — already compliant with
  ред. 5.

## 2. Renames — `name` and `meta_title`

`meta_title` format stays `<name> | Booster Shop` except where noted.

| product_id | SKU | new `name` | new `meta_title` |
|---|---|---|---|
| 125 | `BR-MEW-100` | Брелок Mew (Pokémon) — 3D-друк | Брелок Mew (Pokémon) — 3D-друк \| Booster Shop |
| 126 | `BR-CHARM-100` | Брелок Charmander (Pokémon) — 3D-друк | Брелок Charmander (Pokémon) — 3D-друк \| Booster Shop |
| 127 | `BR-SQUIR-100` | Брелок Squirtle (Pokémon) — 3D-друк | Брелок Squirtle (Pokémon) — 3D-друк \| Booster Shop |
| 128 | `BR-BULB-100` | Брелок Bulbasaur (Pokémon) — 3D-друк | Брелок Bulbasaur (Pokémon) — 3D-друк \| Booster Shop |
| 129 | `BR-PIKA-100` | Брелок Pikachu (Pokémon) — 3D-друк | Брелок Pikachu (Pokémon) — 3D-друк \| Booster Shop |
| 130 | `FIG-ONIX-500` | Фігурка Onix (Pokémon) — 3D-друк | Фігурка Onix (Pokémon) — 3D-друк \| Booster Shop |
| 131 | `FIG-GEOD-511` | Фігурка Geodude (Pokémon) — 3D-друк | Фігурка Geodude (Pokémon) — 3D-друк \| Booster Shop |
| 132 | `FIG-MEW-100` | Фігурка Mew у покеболі (Pokémon) — 3D-друк | Фігурка Mew у покеболі (Pokémon) — 3D-друк \| Booster Shop |
| 134 | `FIG-LUFFY-500` | Фігурка Luffy (One Piece) — 3D-друк | Фігурка Luffy (One Piece) — 3D-друк \| Booster Shop |
| 135 | `FIG-LUFFY-400` | Картина Luffy (One Piece), силует — 3D-друк | Картина Luffy (One Piece), силует — 3D-друк \| Booster Shop |
| 136 | `FIG-LUFFY-410` | Картина Luffy (One Piece), прямокутна — 3D-друк | Картина Luffy (One Piece), прямокутна — 3D-друк \| Booster Shop |
| 137 | `ACC-3D-PKM-110` | Підставка для картки в протекторі — 3D-друк | *unchanged* |
| 138 | `ACC-3D-PKM-120` | Підставка для картки в топлоадері — 3D-друк | *unchanged* |
| 139 | `ACC-3D-PKM-130` | Підставка для картки в акриловому кейсі — 3D-друк | Підставка для картки в акриловому кейсі \| Booster Shop |
| 140 | `ACC-3D-PKM-200` | Підставка для слаба — 3D-друк | Підставка для слаба PSA — 3D-друк \| Booster Shop |
| 141 | `ACC-3D-PKM-300` | Підставка для слаба, на ніжці — 3D-друк | *unchanged* |
| 143 | `ACC-3D-PKM-710` | Обертова підставка для слабів — 3D-друк | Обертова підставка для слабів PSA — 3D-друк \| Booster Shop |

### Notes on individual rows

- **137, 138** — Meta Title already reads `Підставка для картки в протекторі`
  / `в топлоадері`. Only the name was wrong; the title is already the target.
- **139** — owner decision: **акриловий**, not магнітний. Both magnetic and
  non-magnetic acrylic cases fit, so `магнітний` narrowed the product without
  cause. The Meta Title drops the `— 3D-друк` token to stay at 53 characters;
  with it the title is 63 and crosses the 60-character guideline.
- **140, 141, 143** — `PSA` stays out of the **name** (ред. 4: CGC and BGS return
  as compatibility options on the same page) but stays in Meta Title, Meta
  Description, keywords, the `Сумісність` attribute and the FAQ.
- **135** — owner decision 2026-08-28: this is a picture, not a `панно`. Two
  deliberate divergences, both to avoid touching a live product: the slug stays
  `panno-luffy-one-piece-3d-druk`, and the SKU stays `FIG-LUFFY-400` even though
  the `4__` subtype for a picture is `41x`. The SKU is an accounting key, not a
  description. Canon: ред. 8 §1.
- **136** — the `, прямокутна` token is **not** cosmetic. Owner decision
  2026-08-28: the round Luffy wall art is a separate work, so it becomes its own
  SKU `FIG-LUFFY-411` on its own page rather than a shape option here. Without a
  shape token on both, category 74 would show two indistinguishable «Картина
  Luffy». `FIG-LUFFY-411` is not created by this patch — it has no product_id
  yet. Slug of 136 is unchanged. Canon: ред. 7 §3.
- **143** — the old Meta Title mixed scripts (`для PSA slab`); the new one is
  consistently Cyrillic with `PSA` as the only Latin token.

## 3. Meta Description — one row

| product_id | SKU | field | change |
|---|---|---|---|
| 140 | `ACC-3D-PKM-200` | `meta_description` | replace `грейдженої картки` → `оціненої картки` |

Full target string:

```
Підставка для слаба PSA 89×38×20 мм: низька чорна основа, яка не відбирає увагу в оціненої картки. Власний 3D-друк — купити в Україні.
```

No other Meta Description contains the word.

## 4. Meta Keywords — two rows

| product_id | SKU | new `meta_keyword` |
|---|---|---|
| 140 | `ACC-3D-PKM-200` | `підставка для слаба PSA, підставка для PSA slab, підставка для слаба купити` |
| 141 | `ACC-3D-PKM-300` | `підставка PSA slab на ніжці, підставка для слаба на ніжці, настільний стенд для слаба` |

## 5. Attribute values — six rows

These are **values** in `ocp5_product_attribute`, not attribute names. Do not
create, rename or delete any attribute definition. Resolve each attribute id by
name first and verify it exists before writing.

⚠ `Матеріал` exists twice in `ocp5_attribute_description` (ids 29 and 51). That
is not one of the attributes below, but it is the reason a name→id lookup must
not be built as a plain dictionary — a duplicate silently collapses. Fail loudly
if any resolved name matches more than one id.

| product_id | SKU | attribute | old value | new value |
|---|---|---|---|---|
| 140 | `ACC-3D-PKM-200` | `Тип виробу` | підставка для грейджених карток | підставка для слаба |
| 140 | `ACC-3D-PKM-200` | `Сумісність` | грейджений слаб PSA | слаб PSA |
| 141 | `ACC-3D-PKM-300` | `Тип виробу` | підставка для грейджених карток | підставка для слаба |
| 141 | `ACC-3D-PKM-300` | `Сумісність` | грейджений слаб PSA | слаб PSA |
| 143 | `ACC-3D-PKM-710` | `Тип виробу` | обертова підставка для грейджених карток | обертова підставка для слабів |
| 143 | `ACC-3D-PKM-710` | `Сумісність` | грейджені слаби PSA | слаби PSA |

## 6. Patch conventions

Standard AGENTS.md C1–C7 apply in full. Specifically for this patch:

1. **Pre-check before any write.** For each of the 17 product_ids, verify the
   *current* `name` matches the value this handoff expects to replace. If any
   row has drifted (the owner edits cards in the admin panel), abort the whole
   transaction and report which rows differ. Do not write a partial set.
2. **PHP 8.0 target.** `never`, `readonly`, `enum` and PHP 8.1-only syntax are
   parse errors on the production host. `php -l` before shipping.
3. **Storage encoding.** `name`, `meta_title`, `meta_description` and
   `meta_keyword` are plain text columns — unlike `description`, they are **not**
   entity-encoded. Write them as-is. Verify after the write by comparing the
   stored value to the exact intended string, not by pattern-matching for tags.
4. **Backup + rollback.** Write `before.json` and a `restore.sql` covering the 17
   description rows and the 6 attribute rows to
   `_patch_backups/<patch-name>-<utc>/` before the first write.
   ⚠ Do **not** pin `product_attribute` rows by any surrogate id in `restore.sql`
   — key the restore on `(product_id, attribute_id, language_id)`. A previous
   wave shipped a `restore.sql` keyed on `product_discount_id` values that an
   admin re-save renumbered, and it is now unusable.
5. **Idempotency.** A matching rerun prints `already_applied=yes`.
6. **Self-delete** after `done=ok`.
7. **DB changes require the owner's explicit go-ahead** before the live run.

## 7. Expected output on a successful run

```
names_updated=17
meta_titles_updated=14
meta_descriptions_updated=1
meta_keywords_updated=2
attribute_values_updated=6
seo_urls_touched=0
descriptions_touched=0
done=ok
```

`seo_urls_touched=0` and `descriptions_touched=0` are **assertions**, not
counters — if either is non-zero the patch has exceeded its scope and must roll
back.

## 8. Owner-side QA after deployment

1. Open `/product/brelok-charmander-pokemon-3d-druk` — H1 reads
   `Брелок Charmander (Pokémon) — 3D-друк`, URL unchanged, page not 404.
2. Open `/product/pidstavka-dlia-kartky-v-mahnitnomu-keisi-3d-druk` — H1 reads
   `Підставка для картки в акриловому кейсі — 3D-друк`. The slug still says
   `mahnitnomu`; this is intentional (ред. 6 §2).
3. Category 73 «Фігурки та декор Pokémon» — no card in the grid says
   `грейджений`, and Latin and Cyrillic character names are not mixed.
4. Search the site for `грейдж` — the only remaining hits should be inside
   product description bodies and the category 72 description, both of which the
   owner is rewriting separately.

## 9. Left open, deliberately

- **Category 72 «Підставки та декор»** description: 4 occurrences of `грейдж`.
  Owner's, with the product bodies.
- **`ACC-3D-PKM-700`** — name says `для карток у топлоадерах`, Meta Title says
  `для топлоадерів`. A title differing from the H1 is not an error, and the owner
  approved the name as-is; left alone rather than silently widening scope.
- **Roadmap ID** for this rename is not assigned. The canon flagged the derived
  task on 2026-08-28 and it is still tracked only inside `3D-P-CARDCONTENT`.
