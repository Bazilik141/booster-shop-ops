# Handoff — RD-25 WP1-FIX: trim cut-out margins + correct cut/photo classification

Date: 2026-10-11
Executor: Codex · model=Sol · effort=high — Codex authored WP1 r3, F6 and the cache-miss repair this round (same-executor rule); the work is image-measurement heavy and threshold choices need evidence, not a small model.
Owner decision on executor: pending.

**This handoff has a mandatory diagnostic gate (Phase 0). Do not write the patch until the Phase 0 report has been delivered and the owner has replied "go Phase 1".**

## 1. Task ID

RD-25 (Notion: In progress) — follow-up to WP1. Patch ID: `RD-25-WP1-FIX`. WP2/WP3 stay blocked until this is deployed and QA'd.

## 2. Context

Live on production (owner-deployed): WP1 r3 (`patches/RD-25-WP1_media-fit-catalog_20261009.php`), F6 (`…_featured-image-payload_20261010.php`), cache-miss repair (`…_media-cache-miss_20261010.php`, deployed 2026-10-11).

Owner screenshots + live measurement found three defects. Evidence, numbers and per-product list: `diagnostics/RD-25-WP1_media-fit-visual-defects_claude_20261010.md` — read it first.

- **D1 (main):** `resizeFit()` scales the whole source canvas including its own transparent/white padding, so cut-outs render at very different sizes in the same row (OP-17 booster content = 42 % of its file width; OP-13 = 85 %). My r1 acceptance of F3 ("no source trimming") is withdrawn.
- **D2:** Album 400 yellow — light-grey studio photo (corners RGB 242–245, aspect 1.50) is `cut` → rendered as a shrunk grey rectangle.
- **D3:** Games 7 Days 63.5×88 sleeves — edge-to-edge package art with two white and two coloured corners is `photo` → cover crops the package.

Original scope and owner decisions: `handoffs/handoff_RD-25_product-media-slot_20261009.md` (in master), `design/DECISIONS.md` RD-25. Unchanged: desktop catalog 1:1, mobile 2 columns 4:5; cut = contain, bottom-aligned in the 84 % × 94 % box; photo = cover; no solid fill, no blurred-photo background.

## 3. Goal

Every cut-out product fills the same box consistently (packs of one shape render at the same height in a row); studio photos with a uniform non-white background render as photos; edge-to-edge package art is never cropped.

## 4. What to change

### Phase 0 — mandatory diagnosis (read-only, no patch)

0.1 **Fresh source gate.** Ask the owner for a one-file-set export and verify hashes before any analysis of code paths:

```bash
cd ~/public_html && tar -czf ~/RD-25-WP1-FIX-check-20261011.tar.gz catalog/model/tool/image.php system/library/image.php catalog/controller/product/thumb.php catalog/view/template/product/thumb.twig
```

Expected: `catalog/model/tool/image.php` = `9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285` (cache-miss after-hash); `system/library/image.php` = `b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9` (r3 after-hash); thumb controller/twig = their r3 after-hashes. Any mismatch → stop and report.

0.2 **Code verification.** Confirm in the exported `system/library/image.php` whether a usable crop method exists and its exact signature and alpha handling. Do not assume an OpenCart API; if none is usable, say so and propose the smallest addition.

0.3 **Measure all assets** (the 286 classified in r3, plus any primary product image newer than that set — list how you obtained it). For each: source dims/type; four corner RGBA; current v3 class + reason; content bounding box by alpha (> 16) and by distance from the corner background colour; proposed v4 class + reason; trimmed dims; resulting rendered content height/width as % of the 1:1 and 4:5 slot.

0.4 **Thresholds from data, not guesses.** Report histograms/gaps for: (a) alpha threshold and minimum content pixels per row/column so isolated noise and faint halos do not stop a trim; (b) "page-white" corner threshold (proposal: all channels ≥ 250 and corners near-uniform); (c) background-distance tolerance for bbox against a uniform corner colour. State the chosen values and why.

0.5 **Contact sheet.** Render before/after for every asset whose class or trimmed size changes, at 1:1 desktop and 4:5 mobile, grouped by category (One-Piece, Pokemon, more-tcg, acsesuary). Must include: OP-13, OP-15, OP-17 booster + box, EB-02 Rare Pack, Hot Wind Arena KR booster, Gallade, Mega Dream EX, Outlet Mix, Toploader 1 pc, acrylic stand, Album 400 yellow, both Album 360, Games 7 Days 63.5×88, Games 7 Days 9-pocket sheet, both Mystery Boxes, Geodude, Ditto cup, a dark 3D stand photo.

0.6 **Deliver** `diagnostics/RD-25-WP1-FIX_phase0_report_20261011.md` + evidence folder, then stop.

### Phase 1 — patch (only after owner "go Phase 1")

Rules to implement (exact thresholds from Phase 0):

- **R1 trim (D1):** for `cut` sources, crop to the content bounding box before fitting — alpha-based for transparent sources, background-distance-based for page-white/uniform-background sources. Only whole edge rows/columns of background are removed; product pixels never are. Store the crop box in metadata.
- **R2 page-white (D2):** "white background" = page-white per Phase 0 threshold, not ≥ 240. An opaque source with uniform non-white corners outside the 0.70–1.45 band becomes `photo` only if its content bbox (vs corner colour) lies inside the centred cover-crop window for both 1:1 and 4:5 with a safety margin; otherwise `cut` + R1.
- **R3 mixed edge (D3):** if at least one corner is page-white or transparent but not all four, the source is edge-to-edge art → `cut`. Opaque sources whose corners are all non-white keep the existing rules (Mystery Box banners must stay `photo`).
- Bump `RD25_MEDIA_VERSION` to 4 and the fit key prefix (`rd25-fit-v1` → `v2`) so metadata and fit files regenerate; old files stay unused, no deletion.
- `width`/`height` attributes emitted for the card `<img>` must match the served fit file (trimmed aspect). If that requires a thumb controller change, it is in scope; say so in Phase 0.
- One patch file, conventions C1–C7, hash-pinned to the Phase 0 hashes, PHP 8.0, no DB writes, self-delete. Keep the cache-miss guard and F6 behaviour intact.

## 5. Do not touch

- `system/library/image.php::resize()` and every existing `resize()` caller and cache URL.
- Product page gallery, popup/lightbox 700×700, JSON-LD / schema, og:image, Merchant feed (WP2/WP3 or out of scope).
- CSS geometry in `boostershop-ds.css` and the header cache-bust token (unless Phase 0 proves a change is required — then stop and ask).
- Source images in `image/catalog/` (never rewritten), the DB, vendor modules.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout, payment, Hutko, Checkbox/fiscalization, Nova Poshta, CRM.
- The Glory of Team Rocket box photo frame (content issue — owner replaces the photo).

## 6. Likely files / areas (verify against the export)

- `catalog/model/tool/image.php` — `mediaInfo()` classification, `resizeFit()` (confirmed target).
- `system/library/image.php` — only if Phase 0.2 finds no usable crop.
- `catalog/controller/product/thumb.php` — only if the img dimension calculation needs the trimmed size.

## 7. Acceptance criteria

- Phase 0 report exists with hashes, thresholds, full asset table and contact sheet; patch not started before owner approval.
- On https://boostershop.website/catalog/One-Piece desktop: OP-13, OP-15 and OP-17 booster images render with content height within ±5 % of each other; OP-17 box no longer visibly smaller than neighbouring boxes.
- `/catalog/Pokemon`: Hot Wind Arena KR booster and Gallade fill the cut box like the other boosters/sets.
- `/catalog/acsesuary`: Album 400 yellow renders as a photo (cover, rounded) like Album 360; Games 7 Days 63.5×88 shows the whole package; Toploader 1 pc and acrylic stand sit at the bottom of the box at full box height or width.
- Mystery Box banners and dark 3D photos unchanged (`photo`).
- Report lists every reclassification and every asset whose rendered size changes by > 5 %.
- Runner output: `changed_files=<n>`, `done=ok`, `self_delete=ok`; repeat → `already_applied=yes`.

## 8. QA / smoke test

Owner after deploy: hard refresh (Ctrl+F5) the four categories above on desktop and phone; compare with the Phase 0 contact sheet; home page Recommended block; add to cart from a listing; no PHP warning text on first view; OpenCart error log clean. Use `bs-deploy-verify` for the runbook.

## 9. Rollback note

Re-upload the same runner and run `--rollback=_patch_backups/<exact directory printed by the runner>`; it must refuse if the targets changed after apply. v4 metadata and v2 fit files stay on disk unused after rollback; v3/v1 files are still present, so the r3 rendering returns without regeneration. Roll back this fix before any rollback of the cache-miss repair or WP1 r3.

## 10. Recommended status after execution

RD-25 stays **In progress**. After owner QA of this fix: start WP2 (product gallery, reuse R1–R3).
