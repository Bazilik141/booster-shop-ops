# LEGAL-002b-3DP — Claude Code implementation report

Date: 2026-08-07 | Task: `LEGAL-002b-3DP` (parent `LEGAL-002`)
Handoff: `handoffs/handoff_LEGAL-002b-3DP_claude-code-implementation_20260806.md`
Executor: Claude Code · Opus · high
Live source: `backup-8.5.2026_10-49-27_boosters.tar.gz` (targeted extraction only — the
archive was never unpacked into the repository)

## Result

Four independent patches, one per work package, none bundled. All four are
authored, syntax-checked, and **executed end-to-end against a throwaway MySQL 8.4
instance seeded from the live dump** — not just written and eyeballed.

| # | Patch | Status |
|---|---|---|
| 1 | `patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php` | Ready — 1 open item reported below, not implemented |
| 2 | `patches/LEGAL-002b-3DP_return-page-revision_20260806.php` | Ready |
| 3 | `patches/LEGAL-002b-3DP_3d-attribute-schema_20260806.php` | Ready — built to the owner's 2026-08-07 decision |
| 4 | `patches/LEGAL-002b-3DP_mysterybox-airpack-faq_20260806.php` | Ready — scope corrected from 6 products to the 4 that exist |

Nothing was committed, pushed or deployed. No Notion property or status was touched.

## Verification performed

Beyond `php -l`, each patch was run against a scratch database seeded with the real
`ocp5_information*`, `ocp5_seo_url`, `ocp5_attribute*`, `ocp5_product_description`
and `ocp5_product_attribute` tables from the 2026-08-05 dump. Seed fidelity was
confirmed by SHA-256: the seeded id=2/3/6 bodies hash to the same values as the live
dump.

Exercised and passing:

- **Fresh apply** — all four patches, correct end state verified by direct SQL.
- **Idempotent re-run** — all four report `already_applied` and change nothing.
- **Precondition failure** (patch 1, live offer body altered) — patch refuses, writes
  nothing, exits 1, and does **not** self-delete so it can be re-run.
- **Partial-drift handling** (patch 4, one product's description altered) — that
  product is skipped with a named reason, the other three still apply, and the file
  is retained.
- **Blob integrity** — every embedded gzip+base64 payload was decoded straight out of
  the finished patch files and compared byte-for-byte against its source file; every
  guard constant matches its blob's real hash.

One defect was found and fixed by this testing: patch 1's original content gate
asserted the archive's back-link URL appeared once, but the bare URL also
prefix-matches the `…-arhiv-2026-05-26` link in §19. The gate now matches the exact
`href="…"` attribute. A second, cosmetic fix: patch 2 logged a misleading
"page was edited since the backup" line on an already-applied re-run.

## Work package 1 — offer + archive

Live facts confirmed by direct extraction, not assumed:

- id=3 body hashes to `4324d3f4…`, **byte-identical to `handoffs/offer_html_20260724.html`**
  — the same constant the deployed LEGAL-002 v4 patch used. The archive body embedded
  in patch 1 is therefore provably "banner + what is actually live", and the patch
  hard-gates on that hash before archiving.
- New offer: 21 `<h2>`, `Редакція від: <strong>07.08.2026</strong>`, section 7
  `Товари 3D-друку, декоративні та електричні вироби` present, both archive links present.

**Handoff correction — archive meta fields.** The handoff specified
`meta_title`/`meta_description`/`meta_keyword` = empty string for the new archive,
flagged "verify against actual id=6 row". Verified: id=6 actually stores
`meta_title` = `Публічна оферта — архів 26.05.2026` (i.e. **equal to its title**), with
only `meta_description` and `meta_keyword` empty. The patch mirrors the live pattern,
not the handoff text, and logs id=6's live pattern at run time so review can confirm
it still holds.

### Open item — archive `noindex`: reported, deliberately not implemented

The handoff asked whether the archive should be `noindex`. Answer, from the live files:

- `ocp5_information_description` has **no `meta_robots` column** — there is no per-page
  field to write.
- `catalog/controller/information/information.php` sends `X-Robots-Tag: noindex` only
  from its `info()` popup route (line 91). The SEO-slug route that serves
  `/information/publichna-oferta-arhiv-…` does not.

So noindex is not achievable by data change at all; it needs a controller or template
edit, which is a High-risk SEO change per `bs-seo-risk-gate` and outside this patch's
scope. The existing 26.05.2026 archive is indexable today, and the patch leaves the new
archive consistent with it. **This remains an owner decision** — if you want the archives
de-indexed, that is a separate scoped task, and it must not be done via
`robots.txt`/`sitemap.xml`.

### Note — dead theme code touching the offer page

`information.twig` carries two hardcoded `|replace` hacks that fire only when
`heading_title == 'Публічна оферта'`: one injecting a "Призначення платежу" line after a
`Банк: <strong>НоваПей</strong>` string, one rewriting an old clause `10.10.`. Neither
search string exists in the 07.08.2026 offer (or the 24.07.2026 one), so both are
inert no-ops — no behaviour change from this patch. Flagging as cleanup worth its own
ticket, not fixed here.

Also worth knowing: that same template keys the legal-page styling
(`bs-cp-article--legal`) on the exact title `Публічна оферта`, so archive pages render
without it. Pre-existing behaviour, identical for the 26.05 archive; not changed.

## Work package 2 — return page

`title`, `meta_title`, `meta_description`, `meta_keyword` are all left untouched, and the
patch asserts each is unchanged after the write.

**Storage-form change — needs owner awareness.** The current id=2 body is stored
**HTML-entity-encoded** (`&lt;p&gt;…`), unlike id=3 and id=6 which store raw HTML. The
handoff's shared context described the mechanism as raw HTML, which is true for the
offer pages but not for this row. This patch writes **raw HTML**, matching the offer
and archive pages. Both render identically because the controller runs
`html_entity_decode(..., ENT_QUOTES, 'UTF-8')` (line 51) and OpenCart's Twig runs with
`autoescape => false` — both verified in the live files. The full previous row is backed
up as JSON, so it is reversible.

The patch does not hard-fail if the live body has drifted from the 2026-08-05 snapshot
(you may have edited the page since); it records the actual pre-change hash instead. The
hard gate is the post-write SHA-256. Telegram button verified present before and after,
URL and label unchanged.

## Work package 3 — 3D attribute schema

Built to your 2026-08-07 decision. Definitions only — `ocp5_product_attribute` is never
written, and the patch asserts its row count is identical before and after (492 → 492 in
the test run).

**Reused as-is, verified, never modified:** id=20 `Виробник`, id=27 `Тип товару`,
id=29 `Матеріал`, id=33 `Сумісність з картками`. The patch fails closed if any of these
has been renamed, since the reuse decision was made against those labels.

**id=30 `Розмір / Формат` — not reused.** Your rule was to reuse it only if it can carry
physical dimensions without changing its existing semantics. Its live values decide it:
`63×89 мм`, `63.5×88 мм`, `Кишенька 68×94 мм`, `35PT`,
`35PT (~66×92 мм внутрішній)` — that is the **card/pocket format an accessory fits**,
sometimes as a non-metric grade, not the product's own outer size. A 3D item needs
Д×Ш×В of the object plus tolerance. A dedicated `Розміри` is created instead.

**Created:** one group `Характеристики 3D-виробу` (your naming, not `3D-друку`) plus 13
attributes, labels verbatim from the attribute-schema handoff:

Країна виготовлення · Спосіб виготовлення · Розміри · Маса · Комплектація ·
Рухомі елементи · Вікове позиціонування · Типовий строк виготовлення при відсутності
на складі · Може зустрічатися в Mystery Box Item · Живлення · Довжина кабелю ·
Колір світла · Умови використання

Lamp labels collapsed per your pick: `Матеріал корпусу`, `Розміри світильника` and a
second `Тип товару` are **not** created; lamps use the shared attributes with
lamp-specific values. `Назва товару` and `SKU (модель)` are native OpenCart fields
(`product_description.name`, `product.model`), so no attribute rows were made for them.

Acceptance criterion "no duplicate near-identical labels" verified by query: after
apply, **zero** attribute labels occur more than once in the whole catalog.
`attribute_description.name` is `varchar(64)`; the longest new label is 52 characters, and
the patch refuses to write anything that would truncate.

**Schema gate checked, not triggered.** The handoff asked whether the theme pulls
attributes into Product JSON-LD. It does not: `product.twig` renders `attribute_groups`
only as a visible table in `#tab-specification` with no `itemprop`, none of the five
`application/ld+json` blocks reference attributes, and no `additionalProperty` /
`PropertyValue` markup exists anywhere in the theme templates. New labels cannot reach
structured data or the Merchant feed, so `bs-merchant-schema-qa` is not required here.

## Work package 4 — Mystery Box AirPack + FAQ

This is where live discovery changed the plan most.

**Scope corrected: four products, not six.** The catalog contains exactly four Mystery
Box products — 77 (Pokémon Standard), 85 (One Piece Standard), 110 (Pokémon XL),
111 (One Piece XL). **No "Item" format product exists**, which is consistent: offer §6.12
defines Item as 3 boosters + one 3D item, and no 3D SKUs exist yet. When the Item SKUs
are created they will need their own round of this content.

**FAQ mechanism — the draft's format would have been silently dropped.** There is no FAQ
table or field; FAQ content lives inside the product description and
`catalog/view/javascript/bs-faq.js` normalizes it. Its first and highest-priority parse
path reads an existing `<section class="bs-faq-accordion">` and takes **only** its
`.bs-faq-item` children. All four rows already use that canonical structure with six
items each. The draft file's bare `<h3>` + `<p>` would have landed outside that structure
and never rendered. Each new entry is therefore built as a full seventh `.bs-faq-item`
with the row's own `data-bs-faq-id` prefix and matching aria wiring
(`…-button-7` / `…-panel-7`). Wording is verbatim from the draft — franchise-correct
(капсула for Pokémon, скриня for One Piece).

**Anchor.** No row has a «Комплектація» heading. The contents block is
`<h2>Що входить у …</h2>` + `<ul>`, and the wording differs per row — id=85 says
"Mystery box" where the others say "Mystery Mix". The AirPack block is placed directly
after each row's own contents `</ul>`.

**Placeholder container — no CSS added.** The handoff asked whether an existing PDP
info/disclaimer class should replace the draft's bare `<p><strong>`. There is none:
`boostershop-ds.css`'s only "hint" classes (`.bs-field-hint`, `.bs-co-field-hint`,
`.bs-installment-hint`) are checkout form-field helpers, not content blocks. The draft
ships as written. This patch adds **no CSS, no new class, no `!important`, no override**
— nothing for the UI/CSS review signatures to catch.

**Storage form preserved.** These descriptions are entity-encoded and the product
controller decodes them (line 313); the replacement bodies keep identical encoding.

**No runtime string surgery.** Each complete replacement body was generated offline from
the live value and embedded whole. The patch verifies the live row still hashes to its
expected prior value before writing and to the expected new value after. If a description
was edited since the backup, that product is skipped rather than overwritten, the others
still apply, and the file is retained for re-run after regeneration.

### `bs-merchant-schema-qa` applies to this package

Two structured-data surfaces move, though no schema template or feed file was edited:

1. `bs-faq.js` emits Schema.org **FAQPage** microdata (`FAQPage` / `Question` /
   `acceptedAnswer`). A seventh Question is added per product.
2. Product JSON-LD in `product.twig` contains
   `"description": {{ description|striptags … }}`, so the AirPack sentence and new FAQ
   text enter the Product `description` field.

Owner QA should include a Rich Results check on one Mystery Box page per franchise.

## Risky zones

| Zone | Touched? |
|---|---|
| checkout · payment · Hutko · Checkbox · fiscalization · Nova Poshta · order status | No |
| Merchant feed files · sitemap.xml · robots.txt · .htaccess · canonical · redirects | No |
| schema/JSON-LD | No template edited. WP4 changes **content** that FAQPage microdata and Product JSON-LD render — see above |
| DB | Yes, all four packages. Scoped rows only, JSON backups, transactional, SHA-verified, rollback documented in each patch header |
| CRM | No |

## Rollback

Every patch takes a JSON backup into `_patch_backups/<patch>-<timestamp>/db/` before
writing, wraps its writes in a transaction with in-transaction verification, and rolls
back on any failure.

- **Patch 1** — restore id=3 from `live_offer_before.json`; delete the archive rows whose
  IDs are recorded in `created_ids.json` (no ID is hardcoded).
- **Patch 2** — restore `description` for id=2 from `return_page_before.json`.
- **Patch 3** — delete exactly the IDs in `created_ids.json`, in the DELETE order given in
  the patch header.
- **Patch 4** — restore `description` per product from `product_<id>_before.json`.

## Deploy

Upload to `~/public_html` and run each separately, in any order — they are independent.
Each self-deletes on success; on failure or partial application it stays put for re-run.

```bash
php LEGAL-002b-3DP_offer-revision-and-archive_20260806.php
```

```bash
php LEGAL-002b-3DP_return-page-revision_20260806.php
```

```bash
php LEGAL-002b-3DP_3d-attribute-schema_20260806.php
```

```bash
php LEGAL-002b-3DP_mysterybox-airpack-faq_20260806.php
```

## Owner QA checklist

- [ ] `/information/publichna-oferta` — 21 ToC entries render, section 7 present,
      `Редакція від: 07.08.2026`.
- [ ] `/information/publichna-oferta-arhiv-2026-07-24` — 200, banner first, back-link works.
- [ ] `/information/publichna-oferta-arhiv-2026-05-26` — still 200 (untouched).
- [ ] Offer §21 → both archive links resolve, no 404 either direction.
- [ ] `/information/Obmin-i-povernennya` (capital O — confirmed against live `seo_url`) —
      new headings render, Telegram button unchanged.
- [ ] Admin → Catalog → Attributes — group `Характеристики 3D-виробу` with 13 fields; no
      duplicate labels; groups 7 and 9 unchanged.
- [ ] One Mystery Box page per franchise — AirPack text under the contents list, seventh
      FAQ entry opens/closes, correct капсула/скриня wording, no layout break at mobile /
      tablet / desktop.
- [ ] Rich Results Test on one Mystery Box page — FAQPage still valid with 7 questions.

## Status

Stays **In progress** in Notion per `ROADMAP_SOP.md` §6 — content/legal work is not Done
until owner publication plus live QA. Claude (chat) reviews the diff next; Claude Code
does not sign off on its own patches and does not write Notion.
