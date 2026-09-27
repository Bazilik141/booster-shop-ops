# CRM catalogue identity editor — implementation and live check

Date: 2026-09-27

## Scope and source

The owner requested dashboard editing of a CRM product's full name and SKU. The owner selected CRM-only writes, continued imports from the old OpenCart SKU, a hard block for 3D-P products, and historical CRM sales displayed under the new SKU with the former SKU kept in a journal.

The starting `crm/apps-script/Code.gs` mirror was the owner-reported V193 source in `SOURCE_STATE.md`. That provenance is not an independent pull of the live bound Apps Script project. Codex prepared the local implementation; the owner later reported publishing it as V195 and completing a live name-only edit. Codex made no live CRM write or deployment.

The owner had first published V194 at 21:42 on 2026-09-27. A bounded live-sheet read found `PKM-JP-MDEX-BBX` in `Товари!A67` and `Продажі!F263`. `Продажі!G263` reads `Товари!B67`, while that first editor changed only `Товари!C67`. The local follow-up changed the verified ordinary `B` formula to reference `C` during a name edit. The exact bound V195 Apps Script source has not been independently exported for byte comparison.

## Files touched

- `dashboard/booster-dashboard.html`: editor, inactive-SKU lookup, preview, confirmation, and reference counts.
- `crm/apps-script/Code.gs`: context/preview/apply routes, guarded migration, journal, old-SKU aliases for future imports, and retired-SKU creation guard.
- `crm/apps-script/SOURCE_STATE.md`: local candidate provenance and publication boundary.
- `crm/apps-script/tests/catalog-identity-edit.test.mjs`: focused migration and refusal cases.
- `crm/apps-script/tests/catalog-sku-create.test.mjs`: retired-alias creation cases.
- `crm/apps-script/tests/open-cart-identity-filter.test.mjs`: mock for the existing import test after journal lookup was added.

## Behavior and safeguards

The editor reads the current full name from `Товари`, including inactive products. It refuses 3D-P records, duplicate or retired SKU keys, stale previews, missing catalogue formula seeds, unknown exact-SKU references, hardcoded SKU formulas, and a dirty pre-change CRM integrity check. It previews the affected source-cell count before saving.

Apply runs under the existing POST script lock. It writes known manual SKU keys in `Продажі!F`, `Закупки!E`, `Списання!D`, `Міграції_Складу!D:E`, and the two component target-SKU ledgers, then changes the `Товари` source cells. For a full-name edit, it writes `Товари!C` and, only when `Товари!B` has the exact ordinary catalogue formula, changes that formula to a row-local reference to `C`. Existing sale-name formulas then show the new name without writing `Продажі!G`. The preview counts affected sale names separately from SKU cells. Manual or custom short-name rules and literal sale names block this automatic path. Formula projections in `РРЦ`, `Склад`, and `Майстер_Товарів` are checked but never overwritten. Historical sale prices, quantities, frozen costs, and dates are not rewritten. A `Зміни_SKU` journal stores old/new identities and `PENDING`, `APPLIED`, or recovery state. A failed write attempts rollback of both names and the original short-name formula, checks sale/source/projection readback plus CRM integrity, and marks `RECOVERY_REQUIRED` unless that verification passes.

OpenCart import and CRM sale/purchase/writeoff entry paths resolve old SKU aliases to the current CRM SKU. The OpenCart product itself is not edited. A `PENDING` or `RECOVERY_REQUIRED` alias blocks reuse until repaired. New catalogue products cannot reuse a retired SKU.

The CRM integrity checker carries forward its existing manual short-name exceptions through the SKU journal. This is necessary for legacy products whose `Товари!B` is intentionally a literal: changing their SKU must not make the existing literal appear to be a new formula defect. A full-name change on such a product is blocked for separate review rather than overwriting its curated accounting name.

## Local verification

- `node crm/apps-script/tests/catalog-identity-edit.test.mjs` — passed: preview has no writes; historical sale name follows a name-only edit through its existing formula, including when the short-name formula already references the full name; SKU and name migration preserves sale amounts and costs; alias chain and manual short-name exception chain; 3D-P, unknown dependency, custom short name, literal sale name, stale preview, dirty integrity, and retired-key refusals; verified rollback restores both names and the short-name formula.
- `node crm/apps-script/tests/catalog-sku-create.test.mjs` — passed, including new retired-alias cases.
- `node crm/apps-script/tests/open-cart-identity-filter.test.mjs` — passed.
- `node crm/apps-script/tests/preorder-cost-and-stock.test.mjs` — passed.
- `node crm/apps-script/tests/integrity-check.test.mjs` — passed.
- `node dashboard/tests/sku-name-display.test.mjs` — passed.
- Browser fixture QA of the editor at 1280, 768, and 360 px passed for long content, accounting-name display, name-only preview, apply button state, focus, hover, and bounds. No live API was called. The one-off local browser harness was removed after the pass.
- `git diff --check` for scoped tracked files — clean. Inline dashboard JavaScript and `Code.gs` parsed successfully.
- `node dashboard/tests/dashboard-contract.test.mjs` — fails on an existing, unrelated 3D category-list difference: the API includes `Кейс / контейнер для зберігання` and the dashboard list does not. The editor diff does not touch either category list.

## Bounded live check after owner-reported V195

The owner reported V195 at 22:43 on 2026-09-27 and a successful `PKM-JP-MDEX-BBX` operation. Read-only Google Sheets connector calls verified the following; they did not change cells:

- `Зміни_SKU!3` records old SKU = new SKU = `PKM-JP-MDEX-BBX`, the old/new names, state `APPLIED`, and `{}` SKU-cell transfers. This was a name-only edit. `APPLIED` is written only after the route's pre/post `apiIntegrityCheck_` calls succeed; it is evidence of those in-operation gates, not an independently rerun current integrity result.
- `Товари!A67` retains the SKU. `Товари!B67` is `=IF($A67="";"";$C67)` and both `B67` and `C67` display `Pokemon JP Mega Dream EX booster box`.
- Formula-derived names in `Продажі!G263`, `Закупки!F99/F176`, `РРЦ!B67`, and `Склад!B67` display that new name. The SKU remains in the one sale, two purchases, one stock migration source, catalogue, RRP, and stock rows. No matching write-off or component-ledger key exists for this SKU.
- The historical sale retains its pre-edit quantity, unit price, discount, total, frozen cost values, fees, margin, and profit (all checked against the row supplied by the owner). The sale-name and purchase-name formulas remain formulas.
- A bounded search of the relevant displayed-name columns in products, RRP, stock, sales, purchases, the SKU picker, and the CRM dashboard found no occurrence of the former accounting name `Pokémon — Mega Dream EX — JP — Booster Box`.

The live dashboard integrity tile could not be rerun through computer use: its `file:` URL was blocked by the browser security policy. The local source's exact byte identity with the bound V195 script remains unverified. A live SKU rename and subsequent import using an old OpenCart SKU remain untested production paths; they require an owner-selected low-risk product and final manual QA. The current name-only result is consistent across all identified CRM dependencies.

## Git scope

After this check, the owner explicitly authorized a commit and push if the verified result was sound. Stage only the seven files listed above; preserve unrelated working-tree changes and untracked files. No task ID was provided, so the commit uses the repository's CRM scope without inventing one.
