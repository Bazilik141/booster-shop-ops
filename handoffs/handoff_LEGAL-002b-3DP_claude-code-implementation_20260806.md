# Claude Code Handoff — LEGAL-002b-3DP: оферта + повернення + AirPack картка Mystery Box + атрибутна схема 3D

Date: 2026-08-06 | Parent: LEGAL-002 (continuation, Notion `LEGAL-002b-3DP`)
Executor: **Claude Code** · owner decision 2026-08-06 (not Codex for this task)

This handoff bundles **4 independent work packages**. Per project convention, each
produces its **own separate patch file** in `patches/` — do not merge them into one
file; each must be independently rollback-able. Suggested filenames given per
package below.

## Shared context

- Site: OpenCart 4, DB prefix confirmed from live backup (`backup-8.5.2026_10-49-27_boosters.tar.gz`, extracted 2026-08-06) = **`ocp5_`**.
- Single store (`store_id=0`), single language (`language_id=4`, Ukrainian — confirmed only row in `ocp5_language`).
- Mechanism for `information` pages proven and deployed by LEGAL-002 (patch `patch-r09fix-toc-offer-20260526-v3.php`, then the 2026-07-24 offer update): `description` field in `ocp5_information_description` is raw HTML (not double-encoded), theme scans `<h2>` to auto-build the sidebar TOC — no manual TOC list needed.
- Live rows confirmed by direct extraction from the 2026-08-05 backup (`mysql/boosters_ocart49.sql`), **not assumed**:
  - `ocp5_information_description` id=**3**, language_id=4 = live Публічна оферта. Body confirmed byte-identical to `handoffs/offer_html_20260724.html` (LEGAL-002 deployed correctly).
  - `ocp5_information_description` id=**2**, language_id=4 = live Обмін і повернення.
  - `ocp5_information_description` id=**6**, language_id=4 = existing archive page (26.05.2026 edition, from LEGAL-002).
  - `ocp5_seo_url`: `(store_id=0, language_id=4, key='information_id', value='3', keyword='publichna-oferta')`, `(...,'2','Obmin-i-povernennya')` (capital O, verify exact case before writing anything that depends on it), `(...,'6','publichna-oferta-arhiv-2026-05-26')`.
  - `ocp5_information` id=6: `sort_order=0, status=1` (status=1 = reachable by direct URL; the page is simply not linked from nav/footer — that's a separate menu concern, not this flag).
  - `ocp5_information_to_store`: one row per information_id, `(6,0)` for the archive. No row exists for id=6 in `ocp5_information_to_layout` (default layout used, no override needed).
- Attribute tables (schema, from live backup):
  - `ocp5_attribute_group` (`attribute_group_id` PK autoincrement, `sort_order`) — currently at id 10 next.
  - `ocp5_attribute_group_description` (`attribute_group_id`, `language_id`, `name`) — existing: id=7 `Характеристики`, id=9 `Характеристики аксесуарів`.
  - `ocp5_attribute` (`attribute_id` PK autoincrement, `attribute_group_id`, `sort_order`) — currently at id 36 next.
  - `ocp5_attribute_description` (`attribute_id`, `language_id`, `name`).
  - Existing attributes under group **9** (`Характеристики аксесуарів`) that **semantically overlap** the new 3D schema: id=27 `Тип товару`, id=29 `Матеріал`, id=30 `Розмір / Формат`, id=33 `Сумісність з картками`. Existing under group **7**: id=20 `Виробник`.
- Content source files (already in `handoffs/`, owner-confirmed parts I–III):
  - `offer_html_20260806.html` — new live offer body.
  - `offer_html_archive_20260724.html` — archive body (banner + verbatim old text).
  - `return_page_20260806.html` — new live return-page body.
  - `mysterybox_airpack_faq_20260806.html` — AirPack block + FAQ, Pokémon and One Piece variants.
  - `handoff_LEGAL-002b-3DP_3d-attribute-schema_20260806.md` — attribute names/values, source of truth for work package 3.

## Work package 1 — Offer live update + archive creation

Patch: `patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php`

**What to change:**
- `ocp5_information_description` id=3, language_id=4: replace `description` with the full content of `offer_html_20260806.html`. Same backup+SHA-256-verify pattern as the 24.07.2026 LEGAL-002 patch.
- Same row: replace `meta_description` with: `Публічна оферта Booster Shop: умови оформлення замовлення, оплати, доставки й повернення TCG-продукції, Mystery Box, аксесуарів і товарів 3D-друку.` — `title` and `meta_title` stay unchanged (already correct).
- Create new information page for the 24.07.2026 archive, mirroring id=6 exactly:
  - `ocp5_information`: new row, `sort_order=0, status=1`.
  - `ocp5_information_description`: new row, language_id=4, title `Публічна оферта — архів 24.07.2026`, description = full content of `offer_html_archive_20260724.html`, `meta_title`/`meta_description`/`meta_keyword` = empty string (matches id=6 pattern — verify against actual id=6 row before assuming empty).
  - `ocp5_information_to_store`: `(new_id, 0)`.
  - `ocp5_seo_url`: `(store_id=0, language_id=4, key='information_id', value='<new_id>', keyword='publichna-oferta-arhiv-2026-07-24', sort_order=0)`.
  - Do not add an `ocp5_information_to_layout` row (id=6 has none — default layout).

**Open item — needs owner confirmation before this package is marked complete:**
Should the new archive page be `noindex`? LEGAL-002 left this open and it does not appear the 26.05.2026 archive (id=6) ever got a resolved answer either — verify current `meta_robots` state of id=6 in the live backup before deciding, and if setting noindex, treat as **High-risk per `bs-seo-risk-gate`**: do not touch `robots.txt`/`sitemap.xml` for this, only a per-page meta_robots field if the theme/controller already supports it.

**Do not touch:** `sitemap.xml`, `robots.txt`, `.htaccess`, redirects/canonical, checkout/payment/fiscalization, Merchant feed, `ocp5_information` ids 1/4/5, nav/footer menus.

**Acceptance criteria:**
- `GET https://boostershop.website/information/publichna-oferta` → 200, body contains `Редакція від: <strong>06.08.2026</strong>`, exactly 21 `<h2>` tags, section 7 title `Товари 3D-друку, декоративні та електричні вироби` present.
- `GET https://boostershop.website/information/publichna-oferta-arhiv-2026-07-24` → 200, body starts with the archival banner, rest byte-identical to the pre-change id=3 description (verify via the SHA-256 backup taken before the UPDATE).
- Section 21 of the live offer links to both `publichna-oferta-arhiv-2026-07-24` and `publichna-oferta-arhiv-2026-05-26`; both archive pages link back to the live offer; no 404s either direction.

**Rollback:** standard patch discipline — backup old id=3 row (JSON) to `_patch_backups/` before UPDATE, SHA-256 verify after write, `php -l` gate with auto-restore, self-delete on success. For the new archive rows: since this is a net-new INSERT (not an UPDATE), rollback = `DELETE` the exact new `information_id`/`seo_url_id` captured at insert time — write these IDs into the patch's own rollback log, do not hardcode assumed IDs.

## Work package 2 — Return page (Обмін і повернення) update

Patch: `patches/LEGAL-002b-3DP_return-page-revision_20260806.php`

**What to change:**
- `ocp5_information_description` id=2, language_id=4: replace `description` with the full content of `return_page_20260806.html`. Same backup+SHA-256 pattern. `title`, `meta_title`, `meta_description`, `meta_keyword` — unchanged (not in scope for this round).

**Do not touch:** everything else on this row and every other `information_id`.

**Acceptance criteria:**
- `GET https://boostershop.website/information/Obmin-i-povernennya` (verify exact case of the slug against the live `ocp5_seo_url` row before using it — dump showed capital `O`) → 200.
- Body contains headings `Повернення бустерів та Mystery Box`, `3D-товари`, and the string `14 календарних днів`.
- Telegram button still points to `https://telegram.me/BoosterShop_Support_bot` and displays `Написати в Telegram` — unchanged from before this patch (verify, don't just assume the draft preserved it correctly).

**Rollback:** same pattern — JSON backup of old id=2 row before UPDATE, SHA-256 verify, `php -l` gate, self-delete.

## Work package 3 — 3D product attribute schema (definitions only, no product assignment)

Patch: `patches/LEGAL-002b-3DP_3d-attribute-schema_20260806.php`

**Scope boundary — read this first:** this package creates attribute **definitions** (rows in `ocp5_attribute` / `ocp5_attribute_group` and their `_description` tables) so they exist in Admin → Catalog → Attributes. It does **not** assign any attribute to any product (`ocp5_product_attribute` stays untouched) — no 3D product SKUs exist yet to assign them to. Populating actual product cards is a separate, later task (Notion `3D-P-CARDCONTENT`, owner-driven, different session).

**Diagnose before writing (do not skip):** four existing attributes already cover concepts this schema also needs — id=27 `Тип товару` (group 9), id=29 `Матеріал` (group 9), id=30 `Розмір / Формат` (group 9), id=33 `Сумісність з картками` (group 9), and id=20 `Виробник` (group 7). Before inserting anything new, confirm with the owner:
- (a) reuse these five where the concept is an exact match (`Тип товару`→"Тип виробу", `Матеріал`→"Матеріал", `Виробник`→"Виробник") rather than creating near-duplicate attributes, and
- (b) whether the new 3D-only concepts (Країна виготовлення, Спосіб виготовлення, Розміри-with-tolerance, Маса, Комплектація, Сумісність broader-than-cards, Рухомі елементи, Вікове позиціонування, Типовий строк виготовлення, Може зустрічатися в Mystery Box Item, plus the lamp-specific: Живлення, Довжина кабелю, Колір світла, Умови використання) go into the existing group 9 (`Характеристики аксесуарів`) or a new group (e.g. `Характеристики 3D-друку`).

This is a catalog-taxonomy decision, not a copy-paste task — present both options with a one-line tradeoff each (per `AGENTS.md` UI/CSS-patch-discipline pattern, applied here to attribute schema) and get an explicit owner pick before the INSERT statements are final. Full field list, exact Ukrainian labels and per-field notes: `handoff_LEGAL-002b-3DP_3d-attribute-schema_20260806.md` — take every label verbatim from that file, do not invent new ones or rephrase.

**Before finalizing, check:** does `boostershop-ds` theme pull product attributes into Product JSON-LD / structured data anywhere? If yes, run `bs-merchant-schema-qa` before this patch is considered done — new attribute labels reaching schema output is exactly the kind of change that skill exists to gate.

**Do not touch:** `ocp5_product_attribute` (no product assignment in this package), groups 7/9's *existing* rows (only add, never edit/delete an existing attribute without a separate explicit owner-approved patch).

**Acceptance criteria:**
- Admin → Catalog → Attributes shows the confirmed set (reused + new) with exact Ukrainian labels from the attribute-schema handoff, language_id=4.
- No duplicate near-identical labels left unresolved (e.g. both `Тип товару` and a new `Тип виробу` existing side by side would mean the reuse-vs-new decision above was skipped).
- `ocp5_product_attribute` row count unchanged before/after.

**Rollback:** this is a DB structural change — include exact rollback SQL in the patch header (`DELETE FROM ocp5_attribute_group WHERE attribute_group_id IN (...)` etc.), using the actual auto-increment IDs assigned at insert time, captured and logged by the patch itself before declaring success.

## Work package 4 — Mystery Box card: AirPack block + FAQ

Patch: `patches/LEGAL-002b-3DP_mysterybox-airpack-faq_20260806.php`

**Scope boundary:** content is ready (`mysterybox_airpack_faq_20260806.html`, Pokémon/капсула and One Piece/скриня variants), but the exact **product rows** and **FAQ mechanism** are not confirmed — this needs live-file discovery before writing the patch, which is why it's its own package.

**What to change:**
- Locate the live Mystery Box product SKUs (Standard/XL/Item, Pokémon and One Piece) in `ocp5_product_description` (or wherever product body copy lives in this theme) via the live backup.
- Determine whether this theme's PDP has a **separate structured FAQ component** (accordion, distinct DB table/field) or whether FAQ content is just appended HTML inside the product description body. Do not guess — if a dedicated FAQ mechanism exists, use it; otherwise append as HTML matching the existing product-page content style.
- Insert the matching AirPack block (near the комплектація/contents block on each card) and the one FAQ entry, per SKU per franchise, using the exact wording from the draft file — no rewrites.
- Placeholder container: the draft uses plain `<p><strong>` — deliberately no invented CSS class. Check `boostershop-ds` for an existing info/disclaimer-block class used elsewhere on PDP before shipping with bare `<p>`; if one exists, use it (per `AGENTS.md` UI/CSS patch discipline — no `!important`/new override without stated reason).

**Do not touch:** product price/stock/attribute fields, any non-Mystery-Box product, category structure.

**Acceptance criteria:**
- Each live Mystery Box product page (list them in the diagnostic step — expect up to 6: Standard/XL/Item × Pokémon/One Piece, verify actual count against the catalog) shows the AirPack disclosure text near комплектація and the FAQ entry, correct franchise-specific wording (капсула vs скриня).
- Spot-check 3 breakpoints (mobile/tablet/desktop) — block doesn't break layout.

**Rollback:** JSON backup of each touched product's prior description/FAQ field before write, SHA-256 verify, `php -l` gate, self-delete.

## Shared QA checklist (owner runs after all 4 patches deployed)

- [ ] Hard-refresh `/information/publichna-oferta` — ToC sidebar renders all 21 sections correctly (auto-generated from `<h2>`).
- [ ] Both archive links from offer §21 resolve, banner text correct, back-links work.
- [ ] Hard-refresh `/information/Obmin-i-povernennya` — new headings/text render, Telegram button unchanged.
- [ ] Admin → Catalog → Attributes — new/reused fields correct, no stray duplicates.
- [ ] Open 1 Mystery Box product page per franchise — AirPack block + FAQ visible, no layout break at 3 breakpoints.
- [ ] Spot-check 1 product's JSON-LD (View Source / Rich Results Test) if work package 3 touched anything schema-visible.

## Recommended status after execution

Stays **In progress** in Notion (`LEGAL-002b-3DP`) after Claude Code finishes — per `ROADMAP_SOP.md` §6 Definition of Done, content/legal work is not `Done` until owner publication + live QA. Claude (chat) reviews the `git diff` for all 4 patches next; owner deploys and runs the QA checklist above; only then does Claude set `Done`.

## Model / effort recommendation per package

| Package | Model | Thinking | Why |
|---|---|---|---|
| 1 — Offer + archive | Sonnet | medium | Multi-table but fully specified; mirrors a proven LEGAL-002 pattern; one open owner-confirm item (noindex) |
| 2 — Return page | Sonnet | low | Single-row replace, fully specified, proven pattern |
| 3 — Attribute schema | Sonnet | high | Architecturally ambiguous (reuse-vs-new decision) — not risky-zone, but needs careful diagnosis before writing |
| 4 — Mystery Box AirPack/FAQ | Sonnet | medium | Needs live-file discovery (product rows, FAQ mechanism) before implementation |

Do not run any of these on Haiku — none are purely mechanical once the open items above are factored in.
