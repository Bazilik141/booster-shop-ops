# Handoff — LEGAL-003: Public offer 06.10.2026 (Rare Pack) + archive of 07.08.2026

Date: 2026-10-06
Executor: Codex · model=Terra · effort=high — owner-assigned; well-bounded DB content patch that clones a proven precedent (`patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php`), but DB writes on a legal page justify high effort.

Delivery: patch files into `patches/`; the owner uploads to `~/public_html` and runs `php <patch>.php`. The executor never commits, pushes or deploys. No staging — the patch runs on production.

## 1. Task ID

LEGAL-003 — Notion `3dd6bf20-bdb4-81ea-8d89-e0e969227a59`. Publication gate for the Rare Pack cards (CAT-004, `OP-JP-*-RPK`).

## 2. Context

- Owner-approved text: `Booster_Shop_Public_Offer_2026-10-06_FINAL.docx` (repo root, SHA-256 `1e2fa1ae…4197`). Reviewed by Claude (chat) 2026-10-06: Review OK.
- Ready HTML body: **`handoffs/offer_html_20261006.html`** (SHA-256 `9e22347c80bd773292832614818ecba68d66b053af37a0e369f54d43e07efb71`, 89 596 bytes, 21 `<h2>`). Built from the live 07.08 body: every unchanged block is byte-identical to live; changed blocks follow live markup (`<p>`, `<h2>`, `<ul><li>`, `2.x. <strong>Term</strong> — …`, links as on live). Text equality with the docx verified line by line (351/351). Use it verbatim — do not re-convert the docx.
- Formatting decision already applied: three single paragraphs the docx renders as one-item bullets (6.9 «Outlet / Outlet Mix не означає…», «Через поштучне або оптове…», 6.14 «Гарантовані складові…») are `<p>`, not `<li>`.
- Live state (backup `backup-9.24.2026_16-35-03_boosters`):
  - `ocp5_information_description` id=3, language_id=4 — current offer «Редакція від: 07.08.2026»; body SHA-256 `08695acfe3c7e1a1b6f5d09360879d2af3e004384494c41742e1197e420e2cae`, byte-identical to `handoffs/offer_html_20260806.html`.
  - Archives: id=6 `publichna-oferta-arhiv-2026-05-26` (seo_url_id 1348), id=7 `publichna-oferta-arhiv-2026-07-24` (seo_url_id 1481). Offer itself: seo_url_id 670 `publichna-oferta`.
  - No archive of the 07.08 edition exists, while §21 of the new text links to it.
- Precedent: `diagnostics/LEGAL-002b-3DP_claude-code-implementation_report_20260807.md` (archive-row pattern, meta pattern, noindex finding, test harness approach).

## 3. Goal

Publish the 06.10.2026 offer on `/information/publichna-oferta` and keep the 07.08.2026 edition reachable as an archive at `/information/publichna-oferta-arhiv-2026-08-07`.

## 4. What to change

### WP1 — `patches/LEGAL-003_offer-rare-pack-and-archive_20261006.php` (required)

1. Precondition: live id=3/lang 4 `description` must hash to `08695acf…2cae`. If not — refuse, write nothing, exit 1, keep the file.
2. Create the 07.08 archive page exactly like the LEGAL-002b-3DP archive (id=7 pattern — verify against live id=6/7 at run time and log it):
   - `ocp5_information` (sort_order=0, status=1, same other columns as id=7);
   - `ocp5_information_description`: title and meta_title `Публічна оферта — архів 07.08.2026`; meta_description='' and meta_keyword=''; description = banner + the live 07.08 body taken from the DB at run time (after the hash gate), unchanged;
   - banner, same markup as id=7:
     `<blockquote><p><strong>Це архівна редакція Публічної оферти від 7 серпня 2026 року.</strong> Вона зберігається для довідки й не застосовується до нових замовлень. Актуальна редакція: <a href="https://boostershop.website/information/publichna-oferta">boostershop.website/information/publichna-oferta</a></p></blockquote>`
   - `ocp5_information_to_store` (new_id, 0); no `information_to_layout` row (id=6/7 have none — verify);
   - `ocp5_seo_url`: store 0, language 4, key `information_id`, keyword `publichna-oferta-arhiv-2026-08-07`. Refuse if the keyword already exists.
3. Replace id=3/lang 4 `description` with the embedded `handoffs/offer_html_20261006.html` (gzip+base64 + SHA guard, as in the precedent). Do **not** touch title, meta_title, meta_description, meta_keyword.
4. One transaction; JSON backups to `_patch_backups/<patch>-<ts>/db/` (`live_offer_before.json`, `created_ids.json`); in-transaction verification; idempotent re-run reports `already_applied`; self-delete only on full success.
5. Gate the archive link with the exact `href="…arhiv-2026-08-07"` attribute (the precedent found that bare-URL matching collides with other archive slugs).

### WP2 — `patches/LEGAL-003_outlet-origin-value_20261006.php` (optional — **run only if the owner approves in the task**)

The offer (5.2, 6.10) names the Outlet origin value «Outlet — оптова закупка насипом»; live product 73 has `ocp5_product_attribute` attribute_id=18, language 4 = «Outlet — оптова закупка партіями». Change only that value for product 73, with a guard on the old value, a JSON backup and an idempotent re-run. A separate file, so it can be rolled back on its own.

## 5. Do not touch

- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`
- checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, order status, Merchant feed, schema/JSON-LD
- `ocp5_information` ids 1, 2, 4, 5, 6, 7 (including the 24.07 and 26.05 archive bodies and banners); id=3 meta fields and title
- `information.twig` — including the two inert `heading_title == 'Публічна оферта'` hacks and the legal-styling title check (known, separate cleanup)
- noindex for archives — out of scope, needs a controller change (see precedent report)
- the Rare Pack cards and their attributes (CAT-004); any product other than 73 (and 73 only under WP2)
- checkout consent text (4.3 wording unchanged)

## 6. Likely files / areas

Confirmed in the 24.09 backup: `ocp5_information`, `ocp5_information_description`, `ocp5_information_to_store`, `ocp5_seo_url`, `ocp5_product_attribute` (WP2). Template to clone: `patches/LEGAL-002b-3DP_offer-revision-and-archive_20260806.php`. Live may have changed after 24.09 — the executor must verify against actual project files and rely on the run-time hash gate.

## 7. Acceptance criteria

- `php -l` passes on PHP 8.0 (production has no 8.1+, and no mysqlnd — no `get_result()` / `fetch_all()`).
- Scratch-DB test seeded from the 24.09 dump: fresh apply, idempotent re-run, precondition failure (altered id=3 → refuses, exit 1, file kept).
- After apply, id=3 description SHA-256 = `9e22347c…fb71`; the embedded blob decodes byte-for-byte to `handoffs/offer_html_20261006.html`.
- New archive: one row in each of the three information tables + one `seo_url`; its description starts with the banner, and the rest is byte-identical to the pre-patch id=3 body.
- `/information/publichna-oferta-arhiv-2026-08-07` → 200.
- Report in `diagnostics/LEGAL-003_offer-rare-pack-and-archive_report_20261006.md`.

## 8. QA / smoke test (owner, after deploy)

- [ ] `/information/publichna-oferta` — 21 sections in the table of contents, «Редакція від: 06.10.2026», 2.17 Rare Pack present.
- [ ] §21 — three archive links, each opens with 200: 07.08, 24.07, 26.05.
- [ ] The 07.08 archive starts with the banner; its §20 says 07.08.2026.
- [ ] §1.3 and §19 — both Telegram links open (channel and support bot).
- [ ] Checkout — the offer consent checkbox link still opens the offer (no order needed).
- [ ] WP2 only: product 73 «Походження товару» = «Outlet — оптова закупка насипом».

## 9. Rollback note

- WP1: restore id=3 `description` from `live_offer_before.json`; delete exactly the IDs in `created_ids.json` (seo_url → to_store → description → information). No hardcoded IDs.
- WP2: restore the product 73 attribute value from its JSON backup.

## 10. Recommended status after execution

Notion LEGAL-003 stays In progress until the owner deploys and passes the QA above; then Done on owner authorization (Claude chat writes Notion). The Rare Pack cards (CAT-004) can go live only after that, and each must carry a filled «Походження товару» — offer 6.6–6.8 now make it mandatory.
