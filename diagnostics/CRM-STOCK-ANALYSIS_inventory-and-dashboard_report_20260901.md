# CRM-STOCK-ANALYSIS — inventory counting repair and dashboard contract

Date: 2026-09-01

## Outcome

Local repair candidate prepared and regression-tested. No live spreadsheet cell, live Apps Script project, Web App version, or published dashboard was changed.

The QA pass expanded the original diagnosis: eight `Склад!H` rows contain the obvious wrapped/double-write-off formula, but all 95 populated stock rows use a mixed formula contract. Some rows subtract active reservations in `H`; others do not. The repair therefore replaces every populated `H` formula with one canonical balance instead of patching only the reported negative cells.

## Evidence

- Live CRM workbook `Booster Shop CRM — облік товарів` (`1PvlSlg3UoPw8Fbj98lHL-VGLB0HP8hgKUxsXPW1GkRg`), bounded reads on 2026-09-01.
- Live automation workbook `Booster Shop — Майстер-дашборд автоматизацій` (`1YUGdtxHQJee6vY8MdwRsrUxudJCMtnghOGPVJXwO5ik`), bounded reads on 2026-09-01.
- Local CRM mirror `crm/apps-script/Code.gs`; deployed baseline remains owner-reported CRM V157.
- Canonical local dashboard `dashboard/booster-dashboard.html`.

## Confirmed defects

### 1. Mixed and additive `Склад!H` formulas

The intended stock dimensions already exist in `Склад`: `E` purchased, `F` sold, `G` written off, `S` active order reservations, plus incoming/outgoing internal transfers from `Міграції_Складу`.

The old migration helper wrapped whatever formula happened to be in `H` and appended more `SUMIFS`. That produced two defects:

1. formulas that already subtract `G` received a second direct subtraction from `Списання`;
2. only migration-touched/preorder-touched rows received reservation subtraction, so `H` did not have one workbook-wide meaning.

Eight live rows contain the obvious old wrapper: MZERO-BST, MZERO-BBX, EB03-BST, ABYE-BST, ABYE-BBX, CHRS-BST, BETB-BST, and QCAC-BBX. A full arithmetic comparison also found currently visible semantic mismatches on OP10-BST and OP11-BST. All 95 populated rows are rewritten because leaving two formula families would recreate the defect on the next migration or reservation.

Canonical formula meaning:

`available after all active reservations = purchased - sold - written off + migration in - migration out - active reservations`

`Mystery Box` rows retain their existing zero-base exception before migrations and reservations.

### 2. `WRT-0226` was duplicated 80 times

The only duplicated non-empty write-off ID in the bounded full scan is `WRT-0226`:

- original: row 228;
- accidental copies: rows 238–316;
- all 80 records: 2026-08-24, `Власне відкриття`, `PKM-JP-INFX-BST`, quantity 10, reason `Коригування складу`, blank note.

The 79 copies add 790 false write-offs. The repair retains the first exact record and clears only input columns `A:D`, `F`, `K:L` in the copies. Formula columns `E`, `G:J` remain intact. Any different count, ID, SKU, date, quantity, reason, note, or any other duplicated write-off ID aborts the repair.

### 3. Dashboard mislabeled a derived shortage as incoming stock

Automation column `Черга_Складу!G` is headed `Очікується після резерву`, but the API exposed it as `expected` and the HTML labeled it `Очікується`. This made OP10/OP11 appear to have a negative shipment.

The API now reads authoritative stock dimensions directly from CRM `Склад`: `H` as raw available after current reservations, `Q` as raw incoming stock, `S` as the sheet reservation total, and `T` as the legacy incoming-after-preorder value.

The UI reports six separate values: `Доступно`, `Очікується`, `Після поставки`, `Фізично`, `Резерв`, and `Дефіцит після поставки`. Negative available values are clamped only for display; the raw value remains in `stock_raw` and drives the explicit shortage fields.

### 4. HWAK/MSYM requires an inventory-ledger correction

The owner confirmed that one `PKM-JP-MSYM-BST` was physically shipped instead of one ordered `PKM-KR-HWAK-BST`. The exact sales line is not safely identifiable from the stock ledger alone, and changing the ordered SKU would also rewrite sales analytics.

The repair records the physical substitution as a paired, marked stock correction in `Списання`:

- `PKM-KR-HWAK-BST`: quantity `-1` (restore the unit that was not shipped);
- `PKM-JP-MSYM-BST`: quantity `+1` (consume the unit actually shipped).

Both rows use marker `[CRM-STOCK-20260901-HWAK-MSYM]`. Zero or two matching marker rows are allowed; a partial or altered pair aborts. This preserves the commercial order history while repairing the physical ledger.

## Expected post-repair snapshot from the 2026-09-01 evidence

These are evidence-based expectations for the current snapshot, not immutable constants: a later legitimate sale, purchase, or order-status change may alter them before owner execution.

| SKU | Available after reserve | Incoming | After arrival | Physical | Active reserve | Deficit after arrival |
| --- | ---: | ---: | ---: | ---: | ---: | ---: |
| PKM-KR-HWAK-BST | 0 | 0 | 0 | 0 | 0 | 0 |
| PKM-JP-MSYM-BST | 15 | 0 | 15 | 15 | 0 | 0 |
| OP-JP-EB03-BST | -11 (UI: 0) | 12 | 1 | 3 | 14 | 0 |
| OP-JP-OP10-BST | 0 | 0 | 0 | 1 | 1 | 0 |
| OP-JP-OP11-BST | 1 | 0 | 1 | 2 | 1 | 0 |
| PKM-EN-CHRS-BST | 2 | 0 | 2 | 3 | 1 | 0 |
| PKM-JP-INFX-BST | 24 | 0 | 24 | 24 | 0 | 0 |
| PKM-JP-MZERO-BST | 28 | 0 | 28 | 28 | 0 | 0 |

Additional corrected live mismatch: `PKM-JP-ABYE-BST` changes from 17 to 21 under the canonical formula. The other structurally wrapped rows are normalized even where the current numeric result happens to remain unchanged.

## Repair implementation

`crm/apps-script/Code.gs` adds:

- canonical formula builder `inventoryMigrationStockBalanceFormula_()`;
- read-only public preflight `diagnoseCrmStockCounting20260901()`;
- idempotent public apply `repairCrmStockCounting20260901()`;
- exact duplicate/marker guards;
- rollback of duplicate inputs, appended correction inputs, all original `H` formulas, and `I:J` cost values on failure;
- post-write arithmetic validation of every populated `H` row against the source ledgers;
- pre/post CRM integrity checks and cache invalidation.

The dashboard/API change stops using the automation queue's after-reserve column as raw incoming stock and makes the time horizon of every shortage explicit.

## Local verification

- Complete Node parse through the Apps Script test harness: passed.
- All CRM Apps Script tests: passed, including exact WRT duplicate refusal/idempotency, canonical formula assertions, EB03 projection, and raw-Q API mapping.
- Dashboard syntax and contract test: passed.
- Scoped `git diff --check`: passed.

This is local/static proof only. Google Sheets formula recalculation, live FIFO results, Web App publication, and owner dashboard QA remain gated.

## Live execution evidence

Owner-reported CRM V159 publication: 2026-09-01 17:55 Kyiv.

Read-only preflight returned the exact expected state: 95 populated formula rows checked, 95 requiring canonicalization, 79 exact `WRT-0226` copies, and two HWAK/MSYM correction rows.

At 17:58 the supplied apply transcript returned `already_applied:true`, zero remaining duplicate/substitution/formula mutations, `stock_balances_verified:95`, `current_cost_skus_updated:38`, clean pre/post integrity, and zero introduced problems. This transcript is therefore an idempotent repeat after the mutation had already completed; the original mutation-run counters were not supplied. The final values match the expected snapshot exactly:

- HWAK 0 and MSYM 15;
- EB03 -11 available, 12 incoming, 1 projected;
- OP10 0 and OP11 1 available after reserve;
- CHRS 2;
- INFX 24;
- MZERO 28.

Live spreadsheet repair and arithmetic verification are complete. The owner subsequently refreshed the dashboard and reported visual QA as successful. The live repair/dashboard gate is closed.

## Owner execution gate

1. Create a fresh workbook copy.
2. Paste the complete reviewed `Code.gs` into the bound Apps Script project, but do not publish the Web App yet.
3. Run `diagnoseCrmStockCounting20260901()` and retain its JSON/log output. Expected against the inspected snapshot: 95 formula rows checked/to repair, 79 duplicate write-off rows to clear, and 2 substitution rows to append.
4. Only if that preflight matches, run `repairCrmStockCounting20260901()` once and retain the returned pre/post integrity and selected-SKU snapshot.
5. Run the diagnose function again. It must report zero formula repairs, zero duplicate rows, and zero substitution rows; the repair function itself must also return `already_applied:true` on a repeat.
6. Publish a new CRM Web App version, then refresh the local dashboard and smoke the eight listed SKUs plus ABYE-BST.

## Risks and rollback

- CRM is a risky zone. The repair is deliberately fail-closed on any live drift in the corrupted write-off fingerprint.
- `apiIntegrityCheck_` does not cover warehouse/FIFO arithmetic; the repair therefore performs a separate all-row stock-balance verification.
- If Apps Script throws after mutation, the wrapper attempts an in-session rollback. The fresh workbook copy remains the authoritative external rollback if execution is interrupted or the script runtime is terminated.
- No dashboard publication mechanism is run by this change; the edited HTML remains local until the owner approves its normal publication/sync path.
