# 3DP-CATALOG — canonical OpenCart name and article audit

Date: 2026-09-05

Executor: Claude (chat audit) · model=Opus · thinking=high

Use Claude for this evidence-driven catalogue review because it must reconcile live OpenCart records, migration sources, and a long revision history without writing to production.

## Requested outcome

Produce one authoritative handoff for Codex that maps every one of the 72 planned 3D products to its approved canonical OpenCart article and display name. The owner will attach that handoff to a new Codex dialogue before the migration continues.

This is a read-only audit. Do not edit OpenCart, CRM, the 3D-P workbook, Apps Script, Notion, repository migration payloads, or task statuses.

## Why this audit is required now

The current migration bundle deliberately preserved draft-table names and generated proposed articles for matching. Those names were never declared canonical, and 20 generated articles were explicitly marked `SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE`.

The owner has since uploaded 3D products to the website and assigned canonical names and articles there. Those exact OpenCart records are now the primary source for products that already exist on the website.

The live CRM already contains all 72 migration rows with the current proposed articles and draft-oriented names. The 3D-P workbook has not been migrated. Therefore, the next safe operation is to approve or correct the 72-row mapping before any 3D-P apply.

## Repository inputs

Read these current files from the repository root:

- `AGENTS.md`
- `plans/3dp-catalog-reset-20260902/import-manifest.json`
- `plans/3dp-catalog-reset-20260902/import-review.md`
- `plans/3dp-catalog-reset-20260902/migration-payload.json`
- `plans/3dp-catalog-reset-20260902/fifo-contract.md`
- `plans/3D-P_sku-naming-convention_20260807.md`
- `diagnostics/CRM-3DP_catalog-reset-intake_report_20260902.md`
- `crm/apps-script/SOURCE_STATE.md`
- `3d-print/apps-script-3dp-api/SOURCE_STATE.md`

The draft spreadsheet is:

`https://docs.google.com/spreadsheets/d/1gQLHxS-EGxIOwX3k8UhU-1HFRzpgFrlDTZaSelp4Tu4/edit?gid=1367929599#gid=1367929599`

The five product tabs are in scope. The consumables tab is out of scope.

## Fixed owner decisions

- Planned catalogue size: exactly 72 products.
- Import 59 active and 13 inactive products. The source `can print` flag maps to active/inactive SKU status.
- Import products that cannot or should not currently be printed as inactive.
- Do not import the two three-piece keychain sets:
  - `Набір брелоків ЧБС з 3 шт` / source `Брелоки` row 3.
  - `набір брелоків ОР х3` / source `Брелоки` row 13.
- `FIG-NAMI-201` / Nami L has RRP 750 UAH and buyout 500 UAH.
- Do not import consumables as products.
- Serhiy-paid consumables are included by Serhiy in actual manufactured-batch cost.
- Owner-paid consumables continue through the existing CRM consumables/write-off flow.
- Product operating cost must come from actual manufactured batches consumed FIFO. Draft estimates are planning data only and must not create stock or historical FIFO cost.
- Opening stock for this migration is zero.
- The owner chose to discard orphan order `OC-FOP-0339` and all of its remaining related data.

## Evidence rules

Use the actual OpenCart record for every product already uploaded to the website. Capture the exact:

- `product_id`;
- product `name` in the storefront language;
- OpenCart `model` field;
- OpenCart `sku` field;
- public product URL or exact database/export evidence.

Do not assume the public URL, page title, or visible short name is the article. Existing catalogue logic can use `sku` with a fallback to `model`, and many Booster Shop articles may live in `model`. Report both fields and state which one is canonical for the migration.

Claude has no production server access. Use the current public website and the newest owner-supplied cPanel backup/database export that actually contains these products. First verify the backup timestamp is newer than the owner's website uploads. If the exact fields are not publicly visible and the current backup does not contain the new records, stop that portion and request a narrow owner export/query containing only the relevant 3D product IDs, names, `model`, and `sku`. Do not infer missing values.

For a planned row with no live OpenCart match:

1. state `NO_LIVE_MATCH`;
2. evaluate the proposed article against the approved naming convention and collision evidence;
3. approve it only when the repository evidence is unambiguous;
4. otherwise state `OWNER_DECISION_REQUIRED` and present the exact conflict.

Never include secrets, customer records, order payloads, or unrelated database data in the result.

## Known items that require explicit resolution

- Resolve all 20 rows currently marked `SKU_PROPOSED_UNVERIFIED_AGAINST_EXTERNAL_CATALOGUE`.
- Resolve `FIG-ZORO-410` versus the inconsistent historical `FIG-ZORO-400` reference.
- Resolve migration `FIG-PKBL-600` versus the earlier worked example `FIG-PKBL-100`; use current live/canonical evidence and the later convention revisions.
- Correct or explicitly approve the draft `BGC` spelling for `ACC-3D-PKM-202`; the grading brand is expected to be checked against `BGS` evidence.
- Replace abbreviated or colloquial draft names such as `Брелок Ч`, `Підставка мала`, and `Хантер в полоску` with the exact canonical website names when a live record exists.
- Check that every canonical article is unique across the full live OpenCart catalogue, not only within these 72 rows.
- Record inactive products with unresolved RRP/buyout as unresolved; do not invent prices.

## Required deliverable

Create this single Markdown file:

`handoffs/handoff_3DP-CATALOG-CANONICAL-DECISIONS_claude-to-codex_20260905.md`

It must be self-contained and contain:

1. A short conclusion: `READY_FOR_CODEX_CORRECTION` or `BLOCKED`, with the exact remaining blockers.
2. Evidence provenance: live website access date, backup/export filename and timestamp, query or source used, and any rows that lack live evidence.
3. A human-readable table with exactly 72 rows and these columns:
   - `source_tab`
   - `source_row`
   - `source_name`
   - `current_crm_sku`
   - `current_crm_name`
   - `live_product_id`
   - `live_model`
   - `live_sku`
   - `canonical_sku`
   - `canonical_name`
   - `rrp`
   - `buyout`
   - `active`
   - `decision`
   - `evidence`
   - `owner_approval_required`
4. A fenced `json` block containing the same 72 records in machine-readable form. Use `null` for unknown values; do not use an empty string to hide uncertainty.
5. A list of every change from the current migration payload in this format:
   - `source_tab/source_row: OLD_SKU -> NEW_SKU; OLD_NAME -> NEW_NAME; evidence`.
6. A separate list of unresolved decisions, if any.
7. A collision result for all 72 canonical articles against the full current OpenCart catalogue.
8. A final count reconciliation: 72 total, 59 active, 13 inactive, two excluded sets absent, zero duplicate canonical articles.

Use these values for `decision`:

- `APPROVE_CURRENT`
- `CHANGE_TO_LIVE_CANONICAL`
- `APPROVE_CONVENTION_NO_LIVE_MATCH`
- `OWNER_DECISION_REQUIRED`

Do not silently normalize spelling, punctuation, character names, sizes, or product types. Every change needs evidence.

## Acceptance gates

The handoff is ready only when all of the following are true:

- The mapping has exactly the same 72 source identities as `import-manifest.json`.
- Every live match records exact `product_id`, `model`, `sku`, name, and evidence.
- Every one of the 20 unverified proposed articles has an explicit decision.
- `FIG-ZORO-410/400`, `FIG-PKBL-600/100`, and `BGC/BGS` are explicitly resolved.
- `FIG-NAMI-201` remains RRP 750, buyout 500.
- The two excluded three-piece sets are absent.
- No canonical article collides with an unrelated OpenCart product.
- Unknown values are `null` and every unresolved point is visible.
- No live or repository mutation was performed during this audit.

If any gate cannot be proved, return `BLOCKED` and specify the smallest owner action or exact export needed. Do not replace missing evidence with a recommendation presented as fact.
