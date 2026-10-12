# Handoff for a new Claude dialogue — RD-27 (product image standard) and RD-28 (logo + favicon)

Date: 2026-10-12 · Audience: the next Claude (chat) session that plans and reviews these two tasks · Executor for patches: Codex (owner decides per task).

Notion: RD-27 `3f76bf20-bdb4-8185-ab2b-ed03e51c3072`, RD-28 `3f76bf20-bdb4-811b-935d-f6a320eb6360` (both Not started). Dashboard `ROADMAP_TASKS` rows and `ROADMAP_SOP.md` §5 registry rows added 2026-10-12.

## Hard sequencing gate

Neither task deploys anything until **RD-25 WP2+WP3 are deployed and owner-QA'd** (RD-25 is being closed in the previous dialogue). Reasons: WP2/WP3 runners hash-pin `common/header.twig` and the gallery/cart templates; RD-28 edits the header; RD-27 changes source images, which changes RD-25 cut/photo classification and would confuse WP2/WP3 QA. Planning, the pilot and Phase 0 diagnostics may start earlier. Before any Phase 0 export, confirm with the owner that RD-25 is closed and take fresh hashes from the post-RD-25 site.

## Context you need (read, do not re-derive)

- RD-25 framing canon (owner 2026-10-11): whole product always visible, empty space acceptable. `cut` (transparent or page-white background) = contain, bottom-aligned in an 84 % × 94 % box; `photo` = contain, bottom-aligned, full tile. Classification and crop run in `catalog/model/tool/image.php` (metadata v4, fit key `rd25-fit-v2`, 1.25 s admission budget, `LOCK_NB`). Cache keys include source mtime + size, so **an image replaced at the same path regenerates automatically**; legacy `resize()` cache also regenerates when the original is newer (verify in code before relying on it).
- Image inventory already exists: `diagnostics/RD-25-WP2-WP3_phase0_evidence_20261012/measured-inventory.json` and `product-image-inventory.csv` — 134 enabled products, 281 effective image refs, 245 unique source files (149 cut / 96 photo), per-source class, reason, dimensions. 22 empty additional-image rows exist and are skipped by the controller (no DB cleanup in scope).
- Known problem images from RD-25: Geodude and Ditto cup (dark rectangles, reshoot), Album 400 yellow (wide light-grey studio photo), Glory of Team Rocket box (light frame baked into a 366 px source), several 3D photos on dark/grey backgrounds. Earlier owner statement: 3D photos will be reshot gradually.
- Canon docs: `design/README.md`, `BRAND.md`, `RULES.md`, `DECISIONS.md` (RD-25 entry must be restored/updated at RD-25 closure — check it is there before you start), logo files in `design/assets/logo/` (10 files: `logo-unit-booster-shop.{svg,png,webp}`, `logo-mark-color.{svg,png,webp}`, `logo-mark-monochrome.{svg,png,webp}`, `logo-mark-favicon.png`).
- Brand/trust rules (project contract): curated sealed TCG store; never misrepresent sealed products; never invent product facts.

## RD-27 — product image standard (Pixelcut)

Owner intent (2026-10-12): standardize the whole product image library to one style, process it through Pixelcut (https://www.pixelcut.ai/create), and replace images on the site **without per-photo manual work**.

Facts checked 2026-10-12 (re-verify before quoting): Pixelcut Pro ≈ $8–10/month, unlimited background removal and upscale, 1,000 batch exports/month, commercial license; API ≈ $0.05 per background removal, $0.10 per upscale/generate-background (credits $0.01). Output format/transparency and resolution limits were not confirmed — check the docs or a pilot export.

Non-negotiable constraints:
1. **Sealed products** (boosters, boxes, displays, sets, tins): background removal only, and only if the current source is not already a clean cut-out. No AI background generation, outpaint, upscaling or "enhance" on pack art — it can alter printed text/artwork. Most sealed sources are already transparent cut-outs; leave them.
2. Pixelcut's generative features are acceptable only for **non-sealed** items (3D prints, accessories) and only if the owner approves the look on the pilot; never add props, scenes or text that are not the product.
3. Keep originals: every replaced source must be backed up byte-for-byte before replacement; rollback must restore exact bytes.
4. Prefer same path + same format. If transparency forces JPG → PNG/WebP, the DB image paths (`product.image`, `product_image.image` — verify prefix and tables in the newest backup) need a single mapping patch with backup and rollback. Merchant feed / JSON-LD image URLs will change with the path — run `bs-merchant-schema-qa` on that case.

Proposed flow (each step gated by the owner):
- **Decide the target style** with the owner first (transparent cut-out on the white page vs one uniform light background; shadows were rejected in RD-24). Use a small design comparison, not prose.
- **Scope the batch** from the inventory: which of the 245 sources actually need processing (likely the 96 `photo` sources and the non-white backgrounds), which stay untouched.
- **Pilot** 10–15 images across types in Pixelcut by the owner; Claude reviews against the constraints.
- **Batch** by the owner (Pixelcut account is the owner's; no credentials in chat or files).
- **Phase 0 (Codex)**: diff every processed file vs original (dimensions, alpha, visible content), RD-25 reclassification table, before/after contact sheet at 1:1 / 4:5, cold-cost estimate.
- **Phase 1 (Codex)**: one upload/replace runner (+ DB mapping only if formats change), conventions C1–C7, backups, rollback, no cache deletion required.

## RD-28 — logo and favicon

Live state 2026-10-12: header logo `image/catalog/One Piece/BS Big logo.png` (270×84 raster, `?v=tech013-wp2-20260806`, appears twice in the page: header and body); favicon `image/catalog/One Piece/BS Big logo1.jpg` (a JPG). Source of truth: `design/assets/logo/`.

Scope for Phase 0 (Codex, read-only): find every logo use — header markup/setting (`config_logo`), favicon (`config_icon`) and any hardcoded `<link rel=icon>`, apple-touch / manifest icons, transactional emails, Organization/WebSite JSON-LD `logo`, og:image default, footer, checkout/success pages, admin store image settings. Report which are admin settings (owner changes in admin) vs template code (patch). Propose: SVG logo in the header with explicit width/height (no layout shift, cache-bust per convention 8), favicon set generated from `logo-mark-favicon.png` / `logo-mark-color.svg` (ico 16/32/48, png 180 apple-touch, 192/512 if a manifest exists). Schema touch → `bs-merchant-schema-qa`. Keep the old files (no deletion) until owner QA.

## Working rules for the new dialogue

- Reply to the owner in short plain Ukrainian; agent files in English.
- Claude never commits, pushes, deploys or touches the server; Codex commits only on a direct owner request.
- Each task gets its own Phase 0 → review → Phase 1 → review → owner deploy → QA, one patch per work package.
- Status changes: Notion + dashboard mirror in the same session (see `ROADMAP_SOP.md`).
