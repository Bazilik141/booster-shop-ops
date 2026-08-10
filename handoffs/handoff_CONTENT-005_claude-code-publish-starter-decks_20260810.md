# Handoff — CONTENT-005: publish five One Piece starter-deck products in OpenCart

Date: 2026-08-10 | Task: `CONTENT-005` | Notion: `3b86bf20-bdb4-81d6-acad-dc7d32b55500`
Executor: **Claude Code** · model=Opus · thinking=high — owner assigned this step to Claude Code.
Justification for the model: this is a database write on production with no staging, and it needs live
discovery of OpenCart 4 structure rather than a mechanical edit.

Content source of truth: `plans/CONTENT-005_starter-deck-cards_final_20260810.md`.
Review history: `diagnostics/CONTENT-005_chatgpt-draft-review-round1_20260810.md`.

Delivery: **one** patch file `patches/CONTENT-005_starter-decks-publish_20260810.php`, dropped into
`patches/`. The owner uploads it to `~/public_html` and runs
`php CONTENT-005_starter-decks-publish_20260810.php`. Claude Code never commits, pushes or deploys.

---

## 1. Task ID

`CONTENT-005` — publish `OP-JP-ST32-STD` … `OP-JP-ST36-STD` as five OpenCart products in preorder
status.

## 2. Context

The owner bought lot `yskh293` on 2026-08-10: one OP-16 booster box plus five starter decks,
ST-32 to ST-36. The OP-16 box **already has a live, active product page in preorder status** — it is
out of scope and must not be touched. The five decks have no pages.

Copy for all five was drafted by ChatGPT, reviewed, corrected, and every product fact re-verified
against the official Bandai pages on 2026-08-10. It is final. **Do not rewrite it, do not "improve"
it, do not translate it.** Copy it verbatim from the plan file. If something in it looks wrong, stop
and say so rather than editing.

## 3. Goal

Five product pages live at their own human-readable URLs, each carrying the approved copy, the
ten-row attribute table, price ₴700 and the same preorder behaviour the owner already has on the
OP-16 box — with a rollback that removes all five cleanly.

## 4. What to change

### Step 0a — the FAQ accordion. Rule established, do not re-derive it

This was an open question in the first draft of this handoff. It is now closed, with evidence.

`bs-faq.js` was extracted from the owner's cPanel backup
(`backup-8.5.2026_10-49-27_boosters.tar.gz` → `homedir/public_html/catalog/view/javascript/bs-faq.js`,
11 367 bytes) and read on 2026-08-10. Its canonical branch, quoted from source:

```
var canonical = root.querySelector('.bs-faq-accordion');
var nodes = canonical.querySelectorAll('.bs-faq-item');
var qEl = nodes[i].querySelector('.bs-faq-q, .bs-faq-toggle, h4, h5, summary, strong');
var aEl = nodes[i].querySelector('.bs-faq-a, .bs-faq-answer, .bs-faq-panel, p, div');
```

**The rule: write the canonical `.bs-faq-accordion` markup into the description**, exactly as it
appears in the plan file. The script reads `.bs-faq-toggle` and `.bs-faq-panel` directly and restyles
the block. The loose formats (`<h4>`, `<strong>?</strong>`, `<dl><dt>`) exist only as fallbacks for
pages whose copy was written sloppily; new pages must not rely on them. The script also handles a
standalone `.bs-faq-accordion` outside the description container.

Two things this also settles, so nobody re-opens them:

- The apparent "missing answers" on the live OP-15 page were an artefact of reading the page as plain
  text. Answers live in `<div class="bs-faq-panel" hidden>`, which a text extractor skips. **The
  accordion is not broken and there is no defect to report.**
- The plan file's markup was taken verbatim from two live product cards the owner supplied
  (`prod-80`, `prod-mega-symphonia-box`), so it is the shape already in production.

What you still must do:

1. Confirm the deployed `bs-faq.js` on the server matches the backup copy; the backup is from
   2026-05-08 and the script may have changed since. If it differs, say how before proceeding.
2. Confirm `data-bs-faq-id` values `prod-st32` … `prod-st36` do not collide with any existing product
   description in the database.
3. Copy the accordion markup character for character. Do not reformat it, do not strip the ARIA
   attributes, do not renumber the button/panel ids.

### Step 0b — discovery before writing a single line of the patch

**Never guess OpenCart structure.** Read the newest owner-provided cPanel backup, or the live
database, and record in the patch header:

1. Database table prefix.
2. `language_id` actually in use (one storefront language, per `AGENTS.md`), and `store_id`.
3. The **complete configuration of the live `OP-JP-OP16-BBX` product** —
   `/product/One-Piece-OP-16-The-Time-of-Battle-Booster-Box-JP`, ₴5000, button `Передзамовити`,
   confirmed live 2026-08-10 — its `product` row
   (`quantity`, `stock_status_id`, `status`, `date_available`, `tax_class_id`, `shipping`,
   `manufacturer_id`, `sort_order`, `subtract`, `minimum`), its `product_to_category`,
   `product_to_store`, `product_to_layout` and `seo_url` rows.
4. The attribute IDs and attribute group used by that product. The plan file's table has **eight**
   rows. Seven of them already exist — confirmed live on the OP-15 box page 2026-08-10: `Назва сету`,
   `Мова`, `Тип пакування`, `Стан`, `Виробник`, `Рік випуску`, plus `Додатковий вміст` from the June
   batch. **Exactly one attribute is new: `Кількість карток у колоді`.**

   Owner decision 2026-08-10: only that one may be created. `Лідер колоди` and `Вікове маркування`
   were considered and **rejected** — the attribute list is already overgrown, so the leader and the
   9+ marking live in the description text instead. Do not re-add them, and do not invent further
   attributes.
5. Manufacturer `Bandai` exists and is linked on the OP-15 box page — confirm the `manufacturer_id`
   rather than creating a second Bandai.

**The OP-16 box is the template.** Everything that is not product copy — category, store, layout,
tax class, preorder mechanism, sort order — is cloned from it. That is the whole point: it removes
guesswork about how "передзамовлення" is implemented on this shop, which is **not** documented in the
repository and must not be invented.

Report findings to the owner **before** the patch is written if any of these is true:

- the OP-16 box is configured in a way that cannot be cloned for a different product;
- one or more of the ten attributes does not exist and would have to be created;
- the manufacturer `Bandai` does not exist;
- the intended category is ambiguous.

### Step 1 — the patch

For each of the five SKUs, in one patch, with one transaction per product:

- `product` — `model`/`sku` = the SKU exactly as written (`OP-JP-ST32-STD` …), price `700.0000`,
  everything else cloned from the OP-16 box.
- `product_description` — `name` = the H1 from the plan file, `description` = the HTML block verbatim,
  `meta_title`, `meta_description`, `meta_keyword` from the plan file.
- `seo_url` — the keyword from the plan file
  (`One-Piece-Starter-Deck-ST-32-Roronoa-Zoro` and the other four). Check for collision first; abort
  the whole patch on collision. **The SKU must never appear in a URL** — `TECH-012` already had to
  301 the legacy SKU-slug pattern away.
- `product_to_category`, `product_to_store`, `product_to_layout` — mirroring the OP-16 box.
- `product_attribute` — the eight rows per product from the plan file's table, in that order.
- `product_related` — link each deck to the sibling named in its plan entry and to the OP-16 box.

Images: **none.** The owner photographs the physical product and uploads images through the admin
afterwards. Do not reference an image path that does not exist and do not invent a placeholder.

### Step 2 — patch conventions

All seven conventions in `AGENTS.md` apply. Two need explicit attention here:

- **Convention 6 — DB changes.** This is a database write, so the patch header must carry complete
  rollback SQL, and the owner must approve it explicitly before running. The rollback must remove
  the rows from `product`, `product_description`, `product_to_category`, `product_to_store`,
  `product_to_layout`, `product_attribute`, `product_related` and `seo_url` for exactly these five
  products, keyed by the `product_id` values captured at insert time and echoed by the patch on
  success.
- **Convention 5 — idempotent marker.** A second run must detect the five SKUs already exist and exit
  with `already_applied=yes`, not create duplicates. Duplicate products are far more painful to undo
  than a failed insert.

The patch must echo, on success, the five `product_id` values and the five final URLs, so the owner
can paste them straight into QA.

## 5. Do not touch

- `OP-JP-OP16-BBX` — the live OP-16 box product, its page, its SEO URL, its category links. Read-only
  template.
- Any other existing product, category, or attribute definition.
- `sitemap.xml`, `robots.txt`, `.htaccess`, canonical logic, redirects. The sitemap is regenerated
  afterwards by the existing process (`sitemap-regen.sh`), never hand-edited inside this patch.
- Checkout, payment, Hutko, Checkbox, fiscalization, Nova Poshta, order status.
- Merchant feed configuration and any schema/JSON-LD template. The theme generates Product schema
  from the product record; do not hand-write JSON-LD, and do not add GTIN, reviews, ratings or review
  counts — none exist.
- The main CRM and its Apps Script. `CRM-008` owns the CRM side.
- Notion and `dashboard/booster-dashboard.html` — Claude (chat) is the writer.
- The copy itself.

## 6. Likely files / areas

Production OpenCart 4 database and the newest cPanel backup for discovery. No Twig or PHP source
file needs to change — this is data, not code. If you conclude a template change is required, stop
and say why; that would be a different task.

Table and column names above are **likely, not confirmed**: verify every one against the actual
schema before writing.

## 7. Acceptance criteria

- [ ] Five URLs return HTTP 200: `/product/One-Piece-Starter-Deck-ST-32-Roronoa-Zoro`,
      `…-ST-33-Kuzan`, `…-ST-34-Charlotte-Katakuri`, `…-ST-35-Sabo`, `…-ST-36-Eustass-Kid`
- [ ] Each page's `<h1>` is exactly the H1 from the plan file
- [ ] Each page shows price **₴700** and the same preorder state the OP-16 box shows
- [ ] Each page's attribute table shows all eight rows, in the plan file's order
- [ ] Description HTML outside the FAQ block contains only `h2`, `h3`, `p`, `strong`, `ul`, `li` — no inline styles, no extra classes
- [ ] The FAQ block is stored exactly as in the plan file: `section.bs-faq-accordion` with `data-bs-faq-accordion`, a unique `data-bs-faq-id`, `h2.bs-faq-title`, and four `div.bs-faq-item` each pairing a `button.bs-faq-toggle` with a `div.bs-faq-panel[hidden]` via matching `id` / `aria-controls` / `aria-labelledby`
- [ ] The FAQ block renders as an accordion, visually identical to the OP-15 box page: four questions, each opening to reveal its answer, chevron animating
- [ ] Opening a FAQ item on mobile width (390px) reveals the answer without clipping; tap target is at least 44px
- [ ] `document.querySelectorAll('.bs-faq-item').length === 4` on each of the five pages
- [ ] The `Характеристики` tab exists on all five pages and shows the eight rows; the `Опис` and `Відгуки` tabs behave as on the OP-15 box page
- [ ] `meta_title` and `meta_description` match the plan file character for character
- [ ] No SKU string appears in any URL; `/product/OP-JP-ST32-STD` and its siblings return 404 or are absent, not 200
- [ ] Each deck page shows related products including the OP-16 box
- [ ] The OP-16 box page is byte-identical in content and still reachable at its existing URL
- [ ] Second run of the patch reports `already_applied=yes` and creates nothing
- [ ] Patch header carries working rollback SQL; the five `product_id` values are echoed on success
- [ ] `diagnostics/CONTENT-005_starter-decks-publish_report_20260810.md` written, including the
      discovery findings from Step 0 and the five product IDs

## 8. QA / smoke test

Not a checkout/payment change, so `bs-checkout-smoke` does not apply. Two gates do:

- **Structured data / feed — `bs-merchant-schema-qa`.** Five new products enter the Merchant feed and
  generate Product schema. Claude (chat) runs this gate **after** publication, before the owner
  submits anything to Merchant Center.
- **SEO — `bs-seo-risk-gate`.** New URLs and a sitemap regeneration. Classify before the sitemap is
  regenerated, not after.

Owner-run checklist after the patch:

- [ ] Open all five URLs on desktop and on a phone; check the description renders and the attribute table is not broken.
- [ ] Open and close every FAQ item on one deck page, then compare it side by side with the OP-15 box page — they must look and behave the same.
- [ ] Confirm the buy/preorder button behaves exactly as on the OP-16 box page.
- [ ] Add one deck to the cart and remove it — no checkout, just confirm the product is purchasable.
- [ ] Open the OP-16 box page and confirm nothing about it changed.
- [ ] Upload the product photos through admin and re-check the pages.
- [ ] Regenerate the sitemap by the existing process and confirm the five URLs appear once each.

## 9. Rollback note

Two layers:

1. The patch's own backup and rollback SQL in its header, keyed by the echoed `product_id` values.
2. If the patch aborts mid-way, no partial product may remain: wrap each product's inserts in a
   transaction and roll back on any failure, including the `seo_url` collision check.

Because this is a DB change on production with no staging, the owner should take a database backup
immediately before running the patch. State that in the patch's run instructions.

## 10. Recommended status after execution

`In progress` until the owner has run the QA list and uploaded the images, and until
`bs-merchant-schema-qa` has passed on the five new pages. Then the owner authorizes closure and
Claude (chat) writes `Done` in Notion and mirrors `done` in `ROADMAP_FLOW`.

---

## Sequencing risk the executor should know about

`CRM-008` creates these same five SKUs in the main CRM and has not run yet — it is queued behind
`CRM-007`. The OpenCart → CRM order pipeline (`NCRM-10`) matches orders by SKU. If a customer
preorders a deck before `CRM-008` lands, that order may arrive in the CRM against an unknown SKU and
need manual handling.

This does not block publication — it is the owner's call whether to publish first and accept a short
window, or wait for `CRM-008`. Say which one the owner chose in the diagnostic; do not decide it
yourself.
