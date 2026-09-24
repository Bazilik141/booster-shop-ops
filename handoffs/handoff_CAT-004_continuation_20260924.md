# Handoff — CAT-004 continuation (2026-09-24 → next session)

## Task ID
CAT-004 — Варіативність товарів (variant-family catalog model)
Notion: `3dc6bf20-bdb4-8164-b001-db2191f9f7e6` · Status: **In progress**

## Context
This handoff hands CAT-004 to a fresh Claude chat session. The prior session
(2026-09-22/24) triaged a stale diagnostic, confirmed the variant-family
model was approved and ran on production, reviewed and shipped the cosmetic
follow-ups, and did an unrelated repo-wide git cleanup. Read this file plus
`context-index.md` and `AGENTS.md` → "Variant products — canonical rules"
before touching anything.

## Done and deployed (owner-QA'd, committed to git as of commit `0eabe1a`)
All of the following are live on production and confirmed working by the
owner (selector, cart, credit widget, product-page metadata):

- `patches/CAT-004_variant-family_20260919.php` — the model itself: own
  `ocp5_product_variant_family` table, admin tab, product-page selector.
  Supersedes the abandoned native master/variant approach.
- `patches/CAT-004_variant-cosmetics_20260923.php` — selector spacing fix,
  removed duplicate "Колір"-style label text.
- `patches/CAT-004_info-column-reflow_20260923.php` — desktop trust-strip /
  `.bs-pp-meta` two-column reflow to shorten the info column.
- `patches/CAT-004_preorder-price-credit-layout_20260924.php` — preorder
  price-tier display + credit-widget layout.
- `patches/CAT-004_trust-credit-copy_20260924.php` — copy fixes on the trust
  strip / credit widget.
- `patches/RD-PP-META_status-card_20260924.php` — product status card;
  **supersedes** the meta markup touched by the cosmetics patch above.
- `patches/RD-11_cart-preorder-label_20260924.php`,
  `patches/RD-11_cart-preorder-quantity_20260924.php` — cart-page preorder
  label/quantity, shipped alongside CAT-004 because they touch the same
  preorder-status logic.

- `patches/CAT-004_RD-11_mobile-trust-cart-alert_20260924.php`,
  `patches/CAT-004_RD-11_trust-cart-finish_20260924.php`,
  `patches/CAT-004_mobile-sticky-atc-minicart-layer_20260924.php` — final
  product/cart UI fixes (mobile buy bar no longer covers the mini-cart).
  Deployed and owner-QA'd 2026-09-24; committed in `eef66a4`.

Full diagnostics trail: `diagnostics/CAT-004_RD-11_RD-PP-META_final_claude_review_20260924.md`
and the individual `_report_` files named after each patch above.

## What's next — CAT-004 subtasks SD-2 through SD-6 (not started)
Per the 2026-09-24 Notion note, the task stays **In progress** because these
are open:
- SD-2 — real product data pulled from the physical boxes (not yet sourced)
- SD-3 — attribute/key map for variant families
- SD-4 — product descriptions
- SD-5 — entry on the site (products created/attached to variant families)
  and in the CRM
- SD-6 — feed for the nine starter-deck Pokémon ex SKUs

These are content/data tasks, not patch work — expect `bs-content-brief` /
`bs-content-qa` / `bs-3dp-card-qa`-style review, not `bs-patch-review`, unless
new code surfaces are needed to support them.

## Parked, do not restart without asking the owner
- `patches/CAT-004_variant-selector_20260916.php` — a 2026-09-18 rework of the
  **abandoned** native master/variant selector. Not deployed, not
  functional against the current model, deliberately left uncommitted.
  Owner deferred a decision on a separate historical-only commit for it
  (2026-09-24: "обидва відкладаємо" — this and the `docs/CANON_v1_DRAFT`
  question below). Do not fold it into CAT-004 work; it documents a
  discarded approach.
- `docs/CANON_v1_DRAFT_product-content_20260913.md` — modified in the working
  tree but belongs to **CONTENT-007** (separate, in-progress task), not
  CAT-004. Leave it alone unless the owner explicitly reopens CONTENT-007.

## Repo state note (informational, not CAT-004 work)
The same 2026-09-24 session did an unrelated repo-wide git cleanup: RD-12,
PAY-002/PAY-003, 3D-P-007/027/CARDCONTENT, TEMP-NP-ADMIN-LOCKER-001
(canonical Notion task created as `OPS-006`), UI-CAT-GAP, UX-036-UI, and a
small tooling/housekeeping commit were all reviewed and pushed. Left
deliberately untouched: CRM-016/PACKAGING-001/002 (Codex mid-work),
GMC-OPS, TECH-015, CONTENT-007/CANON, OPS-L1 (all owner-flagged "in
progress, don't touch"), `dashboard/booster-dashboard.html` (CRM-016
in-progress dashboard code), and the `work/` scratch directories plus other
junk (`ChatExport_2026-08-31/`, `Claude outputs/`, the stray
`.tar.gz.gz`, `3d-print/serhiy-local-server/dist/` and the
"Локальна копія сервера Сергія" folder) — a `.gitignore` for `work/` was
discussed but never added; still open if the owner wants it.

## Likely files for SD-2–SD-6
- `AGENTS.md` → "Variant products — canonical rules" (the model's constraints)
- `context-index.md` (task-to-context lookup, not status)
- Newest owner-provided cPanel backup (never guess OpenCart structure)
- CRM Apps Script GET API (`sku_list`, `stock_alerts`) for bounded read-only
  checks before any content/CRM write

## Executor / role reminder
Claude (chat): strategy, content briefs/QA, handoffs, Notion status writes.
Codex / Claude Code: any new patch work SD-2–SD-6 might require. Owner:
scope approval, deployment, production QA — unchanged from `AGENTS.md`.
