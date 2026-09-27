# NCRM-21 — CRM→site stock correction and preorder ETA (scope, deferred)

Date: 2026-09-27
Author: Claude (Cowork). Scoping only — no code, no site or CRM write.
Owner decision 2026-09-27: implement inside NCRM, not on the Apps Script CRM.
Nothing here starts until the task is explicitly picked up.

## Owner requirements

1. **SKU registry (WP-A, prerequisite).** Map every CRM SKU to its OpenCart
   `product_id`. CRM names and site names differ, and the same product can
   carry a slightly different SKU in each system, so matching is
   owner-confirmed, not automatic. Only products whose site card exists and
   is enabled for display are in scope; CRM SKUs not yet on the site are
   excluded.
2. **Stock correction (WP-B).** Manual trigger (owner chose this over a
   schedule or a CRM-edit webhook). The run shows a diff of CRM stock vs site
   quantity; the owner can exclude any SKU from the correction list before
   applying; every applied run can be rolled back.
3. **Preorder ETA (WP-C).** For a product with preorder stock status and site
   quantity < 1, the delivery estimate on the product status card becomes
   owner-selectable per product: default `3–4 тижні`, alternatives `2–3 тижні`
   and `1–2 тижні`, offered when a purchase lot for that SKU is
   `Замовлено` / `В дорозі`. Example: OP-17 BST is on preorder at 3–4 weeks
   while several lots are already in transit to Ukraine.
4. WP-B and WP-C share WP-A but get **separate UI screens**.

## Evidence

- CRM already computes per-SKU stock including reserves and preorder
  reservations: `apiSkuList_` / `apiSkuStockMetrics_` / `apiDecoratePreorderStock_`
  in `crm/apps-script/Code.gs`. CRM-016 is introducing `inventory_snapshot`
  as the central stock read (`diagnostics/CRM-016_global_inventory_fix_candidate_report_20260925.md`).
- Known CRM↔site SKU drift, owner-confirmed in CRM-010 (2026-08-28):
  `PKM-JP-ABYSS-BST`→`PKM-JP-ABYE-BST`, `PKM-JP-ABYSS-BBX`→`PKM-JP-ABYE-BBX`,
  `PKM-KR-HWA-BST`→`PKM-KR-HWAK-BST`, `YGO-JP-BODE-BST`→`YGO-JP-BDOM-BST`,
  `PKM-MEGA-BOX`→`PKM-JP-MSYM-BBX`; 21 CRM SKUs were absent from the site then.
- Site stock-write precedent: `patches/OC-FOP-0328_order-quantity-repair_20260824.php`
  updates `ocp5_product.quantity` guarded by `subtract = 1`.
- The ETA is a hardcoded string in `catalog/view/template/product/product.twig`
  (RD-PP-META status card, shipped 2026-09-24 in `eef66a4`). Preorder is
  `stock_status_id = 8`, per the RD-11 cart predicate.
- Production: PHP 8.0, mysqli without mysqlnd, no staging.
- The newest cPanel backup in the repo root (`backup-9.7.2026_…`) is stale;
  WP-A needs a fresh backup or SQL dump.

## Risks

- Writing live product stock changes what customers can buy — risky zone
  (database). Required: dry-run diff, per-SKU owner exclusion, a stored copy
  of prior quantities per run, rollback matched on `product_id`, never on
  row ids.
- Stock source of truth is unsettled: CRM-016 `inventory_snapshot` today,
  NCRM inventory ledger (NCRM-04) later. Pick one before WP-B.
- Variant products (CAT-004 model): confirm whether any in-scope SKU holds
  quantity at option level rather than on the product row.

## Open questions for scoping

1. Authoritative stock figure for WP-B inside NCRM.
2. Where the registry lives in NCRM and how new products enter it (the
   natural hook is CAT-004 SD-5, product entry on site and in CRM).
3. How the ETA value reaches the site: storage (attribute or dedicated
   table) plus a `product.twig` / controller read — this needs a site patch.
4. Write path: NCRM is a local-only Next.js app (NCRM-17 deploy not
   started), so decide between an owner-run patch/export and a direct
   connection before WP-B/WP-C design.
