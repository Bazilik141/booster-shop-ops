# Handoff — UI-BADGE-DEDUP: remove dead `.bs-badge--preorder` rules

Date: 2026-10-09 | Parent: design canon actualisation 2026-10-09 (`design/`)
Executor: Codex or Claude Code (owner decides) · model=Terra / Sonnet · effort=medium
Justification: three known lines in one shared stylesheet plus the standard cache-bust; no discovery needed, but `boostershop-ds.css` is a soft risky zone (AGENTS.md, UI/CSS discipline 6), so not a small model.

Task ID status: proposed. Not in Notion or `ROADMAP_FLOW` yet; the owner creates it or renames it before execution.

## 1. Task ID
`UI-BADGE-DEDUP`

## 2. Context
Owner decision 2026-10-09 (`design/DECISIONS.md`, Foundation → "Preorder colours"): the preorder badge is amber, as live; the «Передзамовити» button stays `--bs-blue-light`. The live `boostershop-ds.css` (copy: `live-snapshots/20261009_design-canon/boostershop-ds.css`, cache token `ui-pcard-badge-20261006`) defines `.bs-badge--preorder` three times:

| Line (2026-10-09 copy) | Block | Rule | Status |
|---|---|---|---|
| 142 | base badge set (`-- Badges --`) | `background: var(--bs-blue-soft); color: var(--bs-blue); border: 1px solid #c7d2fe;` | dead, always overridden |
| 384 | `/* --- Stock badge (add instock variant to existing .bs-badge system) --- */` | `background: #fef3c7; color: #92400e; border: 1px solid #f59e0b;` | effective (first amber) |
| 5079 | after `.bs-trust-strip__item span`, before `.bs-btn-preorder` | identical amber rule | duplicate |

All three have equal specificity, so the last one wins; the computed style is amber everywhere.

## 3. Goal
Exactly one `.bs-badge--preorder` rule remains, at the base badge set, with the amber values. The rendered badge does not change anywhere.

## 4. What to change
- `catalog/view/stylesheet/boostershop-ds.css`
  - Line 142: replace the blue declaration values with the amber values (`#fef3c7` / `#92400e` / `1px solid #f59e0b`). Keep the selector, its alignment and the neighbouring lines.
  - Line 384: delete the `.bs-badge--preorder` line only. Keep `.bs-badge--instock` and the comment above it.
  - Line 5079: delete the `.bs-badge--preorder` line only. Keep the two `.bs-btn-preorder` lines after it.
  - Add a one-line marker comment at the edited base rule (patch convention 5), e.g. `/* UI-BADGE-DEDUP 20261009: single amber preorder badge (owner 2026-10-09). */`.
- `catalog/view/template/common/header.twig` — CSS cache token for `boostershop-ds.css`, read and replaced wholesale per patch convention 8.
- Line numbers are from the 2026-10-09 copy. The executor anchors on exact rule text and asserts each anchor count before writing (convention 2): the blue rule = 1, the amber rule = 2.
- Before editing, grep `patches/` for `bs-badge--preorder` (known hit: `CAT-004-SD-7_rare-pack-listing-badge_20260918.php`) and state in the report whether any applied patch's rollback or marker logic anchors on the lines being removed.
- No `!important`, no new override layer: the fix edits the source rule and removes the dead ones (UI/CSS discipline 3).

## 5. Do not touch
- `.bs-btn-preorder` (both definitions, incl. the hardcoded `#3B82F6` duplicate near line 5080): out of scope; record it in the report as a follow-up candidate only.
- `.bs-badge--instock` and every other badge modifier.
- Any Twig/PHP markup, including `product/thumb.twig` and `product/product.twig`.
- Tokens in `:root`.
- `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`.
- Checkout, payment (Hutko), fiscalization (Checkbox), order flow.
- Merchant feed, schema / JSON-LD, GA4 scripts.
- Database: no DB access, no DB changes.

## 6. Likely files / areas
- `catalog/view/stylesheet/boostershop-ds.css` (confirmed in the 2026-10-09 live copy).
- `catalog/view/template/common/header.twig` (cache token; path per earlier patches, the executor must verify against the live file).
- Live source: the 2026-09-24 cPanel backup predates nine CSS patches and is stale for this file. Before writing the patch, ask the owner for fresh copies of both files (command below). Write the patch against those, not against the backup.

Owner command, run in `~/public_html` on the server:
```
tar -czf UI-BADGE-DEDUP-live-20261009.tar.gz catalog/view/stylesheet/boostershop-ds.css catalog/view/template/common/header.twig
```

## 7. Acceptance criteria
- In the patched `boostershop-ds.css`, `grep -c 'bs-badge--preorder'` = 1 (selector occurrences, the marker comment excluded), and that rule carries `#fef3c7`, `#92400e`, `#f59e0b`.
- `grep -c 'c7d2fe'` returns 0 (if `#c7d2fe` is used anywhere else, the executor reports it and keeps that use).
- The served stylesheet URL carries the new cache token; `header.twig` contains exactly one `boostershop-ds.css?v=` reference.
- Computed style of `.bs-badge.bs-badge--preorder` on the live site after deploy: background `rgb(254, 243, 199)`, color `rgb(146, 64, 14)`, border colour `rgb(245, 158, 11)` — identical to before.
- No other selector's computed style changes (fixture diff of computed styles for badges, `.bs-btn-preorder`, product tile at 1280 / 768 / 390).
- Repeat run prints `already_applied=yes`; `php -l` passes; backup written before any write; self-delete after success.

## 8. QA / smoke test (owner, after deploy)
1. Hard refresh (Ctrl+F5) a category page and a product page: nothing looks different.
2. If any product is on preorder, its badge is amber/brown and its «Передзамовити» button is blue.
3. Product pages: the «В наявності» badge is unchanged (light green).
No checkout or payment smoke test needed: no risky zone is touched.

## 9. Rollback note
Restore `catalog/view/stylesheet/boostershop-ds.css` and `catalog/view/template/common/header.twig` from `_patch_backups/UI-BADGE-DEDUP_preorder-badge-rules_20261009-<timestamp>/`, then clear the OpenCart theme cache. Rollback trigger: any badge or button colour on catalog or product pages differs from before the patch.

## 10. Recommended status after execution
Executor: patch ready → owner deploys → owner QA OK → Done (Claude writes the Notion status on the owner's word). Delivery: patch file `patches/UI-BADGE-DEDUP_preorder-badge-rules_20261009.php`; the owner uploads it to `~/public_html` and runs `php UI-BADGE-DEDUP_preorder-badge-rules_20261009.php`. The executor never commits, pushes or deploys.
