# TECH-045 round 1 — patch review (Claude chat)

Date: 2026-09-28 · Reviewer: Claude (Cowork) · Author: Claude Code
Verdict: **Deploy OK; non-blocking notes.**

Read in full: all five patches (WP-B payload decoded separately: 3 × `wOF2` + OFL.txt, SHA-256 match).
`php -l` (8.4, copy): clean on all five. Conventions C1–C8: present in all five (file check,
exact anchor counts, backup before write, restore on failure, marker/`already_applied`, no DB,
self-delete, token read-and-replace). Scope matches handoff §4 incl. the WP-C0 extension; nothing
from §5 touched. Anchors cross-checked against `live-snapshots/20260928_tech045-render-blocking/`.

## Notes (non-blocking)

| ID | Where | Finding |
|---|---|---|
| N1 | `boostershop-ds.css:4872` (checkout confirm button), `:2230` (`.mini-cart-checkout-btn`) | Still `--bs-green` (#16A34A) after WP-D; every other purchase button becomes #15803D. Two greens on purchase actions until a checkout-scope round. Not in the home audit; checkout is §5. |
| N2 | `booster-product-polish.js` + `booster-typography.css:179` | Preorder products show «У кошик» and purchase green instead of «Передзамовити»/blue (confirmed live on DM24-RP3). Pre-existing; needs its own task. |
| N3 | twig gate in all patches | Reads `config.php` only to regex `DIR_STORAGE`; nothing printed. Acceptable. |

## Run notes
- Order A → B → C0 → C → D is enforced by the runners for B (needs A) and C (needs C0).
- Each runner self-deletes on `done=ok` or `already_applied=yes`; a repeat run needs a re-upload.
- Backups: `_patch_backups/<PATCH_ID>-<Ymd-His>/<same relative path>`.
- After-measurement: the same 18 PSI runs once all five are live.

---

# Round 2 — WP-E review (2026-09-29)

Verdict: **Deploy OK.** `patches/TECH-045_wpe-purchase-green-sizes_20260929.php` read in full; `php -l` clean;
conventions 1–8 present; scope = handoff "Round 2" items 1–4 (checkout change is colour/size only, owner-approved).
Specificity check of the success-link fix: `a.bs-btn-primary` (0,1,1) after `.bs a` (0,1,1, ds.css:96) wins by
order; `.bs a:hover` (ds.css:97) sets only the underline, which `a.bs-btn-primary:hover` removes; toast rules
`.bs-toast__actions .bs-btn-primary` (0,2,0) still win. No finding.

## Product mobile LCP regression (round-1 after-set, 5.5 → 7.6 s in 2 of 3 runs)
Not caused by WP-E. Hypothesis to test next: `product.twig:61` main image has no `fetchpriority="high"`
(home's LCP tile has it), while WP-B added a high-priority `<link rel="preload" as="font">` for Manrope
cyrillic on every route — on slow 4G the font preload can win bandwidth over the product LCP image.
Cheapest test: add `fetchpriority="high"` to `product.twig:61` (one attribute) and re-measure product mobile
×3. Not authorised yet — owner decision.
