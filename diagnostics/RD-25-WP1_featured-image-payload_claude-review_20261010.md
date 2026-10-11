# Claude review — RD-25 WP1 F6 companion: featured image payload

Date: 2026-10-10 · Patch: `patches/RD-25-WP1_featured-image-payload_20261010.php` (209 lines, read in full, sha256 `0f7b394c…2aa3`) · Report: `diagnostics/RD-25-WP1_featured-image-payload_report_20261010.md`

**Verdict: Deploy OK.** Closes F6 from r1–r3.

- Independent recompute: source `work/rd25-featured-20261010/source/…/featured.php` hashes to the pinned before value `aa9bd528…f839`; anchor count 1, LF only; applying the two added lines yields exactly the pinned after value `b981a764…eaae`.
- Change: one comment line + `'image' => $product['image']` in the `$product_data` array passed to `product/thumb`. r3 thumb reads `$data['image']` (r3 runner l.393) and only falls back to `getProduct()` when it is empty, so the duplicate per-card lookup disappears. Legacy `resize()` thumb, price/tax/stock/preorder fields, axis and view are unchanged.
- Raw `$product['image']` (no `html_entity_decode`) matches the r3 fallback path and the other catalog callers — no new behaviour.
- C1–C7: target/writability/symlink check, hash + anchor preflight, verified backup, candidate lint before write, post-write lint + readback with restore, all-or-nothing marker `RD-25-WP1-F6 20261010`, no DB, self-delete. Rollback verifies current after-hash and backup before-hash.
- Disjoint from the eleven r3 targets (r3 touches `featured.twig`, not the controller); either apply order works. A controller-only change needs no template cache clear on its own.
- Source hash pinned to the 2026-10-10 archive: a later edit of `featured.php` makes the runner stop before writing.

Owner QA: home page Recommended cards show photos in the new 1:1 slot (cut/photo as in category), names, prices, sale/preorder/out-of-stock states, add-to-cart works; no PHP errors.
