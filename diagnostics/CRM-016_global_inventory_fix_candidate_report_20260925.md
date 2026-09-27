# CRM-016 global inventory candidate — 2026-09-25

## Provenance and authority

The main CRM mirror matched the owner's last deployed full source,
`work/CRM-016_CRM_API_from_V188_3dp_share.gs`, before this round. The Alerts
mirror matched the owner's reported V6 source,
`work/CRM-016_Alerts_API_from_V5_followup.gs`. No fresh post-deployment byte
export exists. This round changed local code only. No Web App, Sheet cell,
Script Property, customer order, or physical stock was changed.

## Implemented candidate

- Main CRM adds read-only `inventory_snapshot`, cached for at most 30 seconds.
  The complete paste file is `work/CRM-016_CRM_API_from_V188_inventory_fix.gs`.
- The dashboard's Stock and Products tabs and the Accounting 3D sale picker use
  the same snapshot. A rollout fallback keeps ordinary products visible if the
  old CRM API does not yet know this action, but hides all unverified 3D stock.
  The former Stock-tab overlay that replaced only `stock` was removed.
  Missing 3D source values display as `—`, including the deficit column.
- The Alerts API stops interpreting CRM-formula 3D negatives and queue actions
  as stock truth. It fetches the central snapshot for 3D print shortages,
  failed sale syncs, missing availability, and active catalogue gaps. The full
  paste file is `work/CRM-016_Alerts_API_from_V6_inventory_fix.gs`. The Alerts
  project needs `BOOSTER_CRM_URL` and `BOOSTER_CRM_TOKEN` in Script Properties;
  never place the token in source or chat. Without a working connection, it
  emits one source-unavailable alert and suppresses unverified negative-stock
  claims rather than producing false positives.
- For ordinary products, the Alerts API compares a negative CRM available
  balance with the reconciled physical accounting balance. Open preorder
  reservations that leave physical stock at zero or above no longer create
  false negative-stock alerts. `ACC-005` still creates a shortage alert.
- CRM `stock_alerts` summary counts no longer include 3D purchase actions from
  the formula-only automation queue. Ordinary SKU arithmetic remains based on
  CRM purchases, sales, write-offs, migrations and preorder reservations.

For a 3D SKU, 3D-P `Наявно зараз, шт` has already subtracted each committed
3D-P sale. The snapshot reconciles individual 3D-P sales by CRM row, order,
SKU and quantity with current CRM order status:

```text
physical = 3D-P available + open orders already committed in 3D-P
           - shipped/received CRM units not committed in 3D-P
available = physical - all open CRM order units
projected = available (no unrecorded future prints assumed)
```

`Нове`, `В обробці` and `Передзамовлення` are open reservations. `Відправлено`
and `Отримано` have left physical stock. Cancelled/returned lines do not
reserve. This follows the owner's 2026-09-25 rule. If the 3D-P response,
availability arithmetic, sale quantity, or sale identity is invalid, every
derived 3D quantity becomes unavailable. The dashboard no longer presents a
CRM negative alongside a 3D-P positive as if both came from one ledger.
The 3D-P read API has a 500-sheet-row cap; a response at that boundary is
rejected rather than silently treated as a complete sales ledger.

Expected current examples, assuming the live inputs have not changed since the
read-only audit:

| SKU | Available | Physical accounting | Reserve | Remaining issue |
| --- | ---: | ---: | ---: | --- |
| `ACC-3D-PKM-110` | 2 | 2 | 0 | none from CRM negative stock |
| `BR-CHARM-100` | 35 | 35 | 0 | none from CRM negative stock |
| `ACC-3D-PKM-200` | 2 | 2 | 0 | shipped/unpaid order already committed to 3D-P |
| `BR-DITTO-400` | 0 | 0 | 0 | sale sync failed; explicit alert remains |
| `FIG-ONIX-500` | 1 | 1 | 0 | 3D-P snapshot has two prints and one sale; owner confirmed one physical unit on 2026-09-26 |
| `ACC-005` | 0 | 0 owner-counted | 0 | owner recorded a compensating purchase; current CRM H is zero (verified below) |

The stock header now says `Фізично · облік`: it is derived from transaction
records, not a warehouse count. Negative derived physical balances for ordinary
SKU are shown as unknown and keep an accounting-shortage exception.

## Live Sheet corrections prepared, not applied

The current read-only formula preflight found exactly 93 older `Склад!H` formula
rows, 97 canonical rows, and no third variant among 190 nonempty SKU rows.
`diagnostics/CRM-016_formula_repair_targets_20260925.csv` lists each affected
row and SKU. The older formula is `E-F-G` (with the Mystery Box exception). The
canonical formula also adds migration in, subtracts migration out, and subtracts
preorder reserve `S`. Example canonical source: `Склад!H14`. Any repair must
re-read and match the exact current formula and SKU before changing a cell,
preserve every formula cell, record a rollback snapshot, run the CRM integrity
check before and after, and stop if a numeric result changes unexpectedly.
For row `{r}`, the exact proposed H formula is:

```text
=IF($A{r}="";"";N(IF($D{r}="Mystery Box";0;$E{r}-$F{r}-$G{r}))+SUMIFS('Міграції_Складу'!$H$2:$H;'Міграції_Складу'!$E$2:$E;$A{r})-SUMIFS('Міграції_Складу'!$G$2:$G;'Міграції_Складу'!$D$2:$D;$A{r})-N($S{r}))
```

The current automation `Черга_Складу!H30` spill formula adds `E+G`; `E` is
already preorder-net CRM `Склад!H`, while `G` is `Склад!T=Q-S`. This subtracts
`S` twice. Its replacement should add `E` and the gross incoming quantity
(`Склад!Q`, column 17 in `Source_CRM_Stock!A:T`):

```text
=ARRAYFORMULA(IF($A30:$A="";"";N($E30:$E)+IFERROR(VLOOKUP($A30:$A;Source_CRM_Stock!$A$7:$T;17;FALSE);0)))
```

This is a proposed formula, not a live write. Preview the entire spill and
confirm that the four preorder SKU projections move by their reservation count
while every other projection remains unchanged before applying it.

## Remaining evidence gates

1. `BR-DITTO-400`, `OC-FOP-0382`: five `INTERNAL_ERROR` results from 3D-P.
   The old execution log is not required from the owner. Obtain a narrow
   current FIFO batch read or instrument a fresh 3D-P source export, then
   repair/retry only that idempotent operation. The candidate shows
   this as an explicit sync error and conservatively subtracts the shipped
   unit; it does not pretend that 3D-P FIFO/cost is repaired.
2. `ACC-005`: this was an accounting shortage of 8 after 19 receipts, 26
   qualifying sales and one write-off. The owner later recorded an 8-unit
   purchase and confirmed zero physical stock. A bounded 2026-09-26 CRM read
   shows 27 receipts, 26 sales, one write-off and H=0. No agent adjustment is
   needed; the old alert should disappear on a fresh Alerts read.
3. `PKM-JP-ABYSS-BST` sale in `Продажі!214`: confirm whether it is the
   canonical `PKM-JP-ABYE-BST` and whether another movement compensated the 2
   units before changing the historical SKU.
4. 3D-P active `ACC-3D-TCG-600` is absent from CRM and lacks availability;
   `ACC-3D-DITTO-420` is inactive in CRM but active in 3D-P. The owner decided
   on 2026-09-26 to archive TCG-600 in 3D-P and activate DITTO-420 in CRM.
   These status writes are pending; the screenshot reflects the old statuses. Archived
   `FIG-ONIX-201-GREY` also lacks availability and is not treated as zero.
5. `FIG-ONIX-500`: the supplied 3D-P snapshot says two printed, one sold,
   one available. The owner confirmed one physical unit on 2026-09-26, so
   this count now agrees with the source ledger.

## Validation

- Main and Alerts Apps Script mirrors parse in Node's V8 parser.
- `crm-016-inventory-snapshot.test.mjs`: received, open committed, open missing,
  shipped missing, preorder shortage, ordinary stock, missing availability,
  and overallocated sale cases pass.
- `crm-016-inventory-alerts.test.mjs`: 3D print/sync/catalogue/source alerts
  pass; healthy 3D and ordinary stock do not produce 3D alerts; EB-03 preorder
  does not create a physical-negative alert while `ACC-005` does.
- `crm-016-inventory-contract.test.mjs`: every inline dashboard script parses;
  Stock, Products and Accounting use the central snapshot and fail closed.
- Existing CRM-016 3D picker, order-share and sync-journal focused tests pass.
- The broader `dashboard-contract.test.mjs` currently stops on a pre-existing
  3D category-list difference (`Кейс / контейнер для зберігання` in its
  expected list, absent from the current dashboard list); this inventory
  change did not alter that list. Its obsolete stock-overlay assertions were
  updated to the new inventory action, but the unrelated category gate remains.
- `git diff --check` reports no whitespace errors in changed tracked code.

This is local/static validation, not a live Apps Script execution, new print,
real order, physical count, or production QA. The previous V188/V6 full files
remain available for rollback. No commit or push was made.

## Owner deployment report, 2026-09-25

The owner reports deploying the full CRM candidate as Web App V190 at 22:17 and
the full Alerts candidate as Web App V7 at 22:16. The owner then ran the CRM
integrity check: `clean: true`, `problems: []`, 67 3D-P RRP rows compared,
6 skipped for missing CRM RRP, 9,354 ms. This is production publication and a
bounded integrity result, not proof that the new `inventory_snapshot` action,
dashboard stock view, or Alerts-to-CRM connection works. Their read-only live
smoke checks remain the immediate gate before formula or history repairs.

## Owner live snapshot, after V190/V7

The owner supplied a bounded `inventory_snapshot`/Alerts response. CRM reports
`source_status: ready`. Reconciled stock is `ACC-3D-PKM-110` available/physical
2/2, `BR-DITTO-400` 0/0 with `3dp_sale_sync_missing`, and `FIG-ONIX-500` 1/1
from the 3D ledger. Ordinary `OP-JP-EB03-BST` reports available 0, physical 1,
reserve 6, projected deficit 0; its carried CRM `мінусовий_залишок` issue tag
remains stale in the snapshot. `ACC-005` reports available 0, unknown physical,
projected deficit 8, with `crm_accounting_shortage`. The only two snapshot
exceptions shown were ACC-005 and BR-DITTO-400.

The Alerts response contained one active `3dp_source` item. A subsequent
bounded read showed `details: CRM inventory connection is not configured`.
The Alerts project's `BOOSTER_CRM_URL` and `BOOSTER_CRM_TOKEN` Script Properties
must be set with the current CRM Web App `/exec` URL and token; no source change
or code redeployment is required for this configuration. The owner must not
send the token in chat. The browser's local-file-origin warning is unrelated to
the two successful API calls.

The live EB-03 payload also revealed a presentation defect: a non-negative
physical accounting balance retained the raw CRM Master
`мінусовий_залишок` issue tag. A full V190-based follow-up candidate at
`work/CRM-016_CRM_API_from_V190_stock_issue_tag_fix.gs` removes that stale tag
for ordinary SKU with non-negative physical balance, while preserving true
shortages such as ACC-005 and low-stock advice. It was a local candidate at
this point and was subsequently published as V191 (below). The canonical local
dashboard also filters that stale tag at display time. Its contract test passed.

## Owner V191 publication and Alerts screenshot, 2026-09-26

The owner reports CRM Web App V191 at 13:45 from the complete stock-issue-tag
follow-up. The dashboard screenshot now shows four active, SKU-specific alerts:
`ACC-005` accounting negative stock, `BR-DITTO-400` missing 3D sale sync,
`ACC-3D-DITTO-420` active in 3D-P but inactive in CRM, and `ACC-3D-TCG-600`
active in 3D-P but missing from active CRM. The `3dp_source` alert is gone,
which supports that Alerts V7 now reaches CRM, and EB-03 is absent from this
negative-stock list. The image does not verify physical shelf quantities,
FIFO batches, catalogue intent, or a post-V191 CRM integrity run.

Source inspection of the 3D-P FIFO path explains why the CRM journal cannot
diagnose `BR-DITTO-400` on its own: `fifo3dpPlanAllocation_` throws ordinary
Errors such as `FIFO_INSUFFICIENT_COSTED_STOCK` and
`FIFO_INVALID_LAYER_COST`, while `respond3dp_` labels errors without a `code`
property as `INTERNAL_ERROR`. The CRM transport then preserves only that code.
This identifies a loss of diagnostic detail, not which FIFO condition occurred
for this sale. A matching 3D Apps Script execution error or narrow print-log/
batch check is required before any retry or ledger write.

## Read-only formula preflight, 2026-09-26

Current live metadata confirms the exact CRM `Склад` and automation
`Черга_Складу`/`Source_CRM_Stock` tabs. Bounded reads of all 190 populated SKU
keys and `Склад!H3:H192` formulas still found 93 older formulas, 97 canonical
formulas, and no third pattern. Every one of the 93 row/SKU pairs exactly matches
`diagnostics/CRM-016_formula_repair_targets_20260925.csv`. The seven populated
`Міграції_Складу` rows and current preorder-reserve `Склад!S3:S192` imply zero
numeric change for all 93 targets if only their formula shape is normalized.

`Черга_Складу!H30` still contains the double-reservation formula `E+G`.
Current `Source_CRM_Stock!A7:A196` and `Q7:Q196` match CRM `Склад!A3:A192`
and `Q3:Q192` for all 190 SKUs. Replacing the spill formula with the proposed
`E+Q` changes only four current projections: OP07 booster 0→1, EB03 booster
-3→3, OP17 booster 0→7, and OP13 booster 33→34. Each delta equals that SKU's
current preorder reserve. No formula was written; this is a read-only preflight
for a separately approved live repair with before/after integrity checks and a
rollback snapshot.

The exact read-only preflight and rollback inputs are saved in
`diagnostics/CRM-016_formula_repair_preflight_20260926.json`. This snapshot
must be revalidated against live cells before any future apply; it is not
permission to write formulas.

## Owner count and catalogue decisions, 2026-09-26

The owner confirmed `ACC-005` has zero physical units and entered the purchase
that offsets its former -8 accounting balance. A bounded live read of
`Склад!A56:T56` now shows 27 received, 26 sold, one written off, H=0,
incoming Q=0 and preorder S=0. The previous negative alert predates this
purchase and should clear after a fresh Alerts calculation. No stock write was
made by the agent.

The owner confirmed `FIG-ONIX-500` has one unit physically present, matching
3D-P's one available unit and the central inventory snapshot. The prior
owner-reported count of zero is superseded.

At that preflight point, the owner wanted `ACC-3D-DITTO-420` active in CRM. A bounded live read found it
on `Товари!160` with manual status `Активний товар = Ні`; the guarded CRM
`set_3dp_sku_active` action can change exactly this SKU with
`expected_active = Ні`. The owner wants `ACC-3D-TCG-600` archived in 3D-P.
It has no CRM product row, so the dashboard's paired CRM+3D-P status toggle
cannot be used for this case: its CRM step would fail first. The dedicated
3D-P `3dp_nomenclature_archive` action accepts the current nomenclature row
and `expected_status = Активний`. Both catalogue writes were unapplied at that
point and
need readback/alert verification after execution.

`BR-DITTO-400` is the one known sale-ledger exception in the comparable 3D
SKUs. The historical CRM journal has five remote `INTERNAL_ERROR` entries
but no underlying 3D-P exception message. The owner should not be asked to
recover old execution logs; this follow-up needs a bounded current FIFO batch
inspection or diagnostic improvement using a fresh 3D-P source export before
any sale retry. No sale or FIFO ledger write was made.

## 2026-09-26 follow-up: marketing proxy, catalogue status and live CRM cell

The owner used the existing `add_3dp_marketing_writeoff` workflow for one
`BR-DITTO-400` unit as a one-time proxy for the old unsynced sale. A bounded
CRM read found the resulting marketing expense in `Витрати!74` for UAH 30.
The underlying customer sale remains `Продажі!387`, order `OC-FOP-0382`,
quantity 1, status `Отримано`. There is no matching CRM row in
`3D_облік_замовлень` and no extra CRM `Списання` row for this SKU. The CRM
formula balance at `Склад!H113` remains -1 because it has zero purchases and
one sale; this formula is not the 3D physical source. The owner's reported
3D-P marketing operation consumed one costed unit and accrued Serhiy's buyout.
The agent did not independently read the current 3D-P API result.

In V191, the central `inventory_snapshot` subtracts any fulfilled CRM sale
missing from the 3D-P CRM-sale ledger from 3D-P availability. After the
marketing proxy consumed the same unit, this creates a claimed one-unit
deficit from two accounting paths. The local CRM follow-up now withholds
stock/physical/deficit values only when 3D-P reports nonnegative availability
but that extra missing-fulfilled subtraction would make physical stock
negative. It keeps `3dp_sale_sync_missing` and `3dp_physical_unverified`.
The local Alerts follow-up keeps the specific sale-sync alert for that
unverified case, explicitly warns against a blind sale retry, and does not
issue a print-deficit alert. This is an honest
unverified display, not a ledger link or a second FIFO allocation. The UAH 30
marketing expense and Serhiy accrual remain as entered by the owner; no
finance or 3D-P data was changed. The full local files are
`work/CRM-016_CRM_API_from_V191_unverified_stock_fix.gs` and
`work/CRM-016_Alerts_API_from_V7_unverified_stock_fix.gs`; no newer Web App
deployment is reported.

The dashboard's existing archive action always called
`set_3dp_sku_active` first. For `ACC-3D-TCG-600`, which has no CRM product row,
that API returns `SKU not found in CRM` before the 3D-P archive request.
The local dashboard now offers an explicit 3D-P-only archive confirmation on
that exact error; it never uses this fallback for restore or other errors.
The 3D-P status has not yet been changed.

The product synchronization action used active-only `sku_list`. It treated
inactive-but-existing `ACC-3D-DITTO-420` as missing, then `add_sku` returned
`SKU already exists with different CRM fields or RRP`. The local dashboard
now offers a guarded `set_3dp_sku_active` activation after that exact duplicate
error; it does not overwrite the existing name or RRP.

The owner explicitly confirmed that `ACC-3D-DITTO-420` should be active.
Before the one-cell live edit, `Товари!A160` matched that SKU,
`Товари!L160` was the manual value `Ні`, its validation source
`Налаштування!AB4:AB5` allowed `Так`/`Ні`, and the SKU-keyed RRP was UAH 450.
The agent changed only `Товари!L160` to `Так` through one Sheets updateCells
request and read it back as `Так`; SKU, RRP and neighbouring cells were
unchanged. The CRM API's 300-second `sku_list` cache was not invalidated by
this direct cell edit, so dashboard readback may lag. No post-edit Apps Script
`integrity_check` response or refreshed `inventory_snapshot` was available.

Focused local checks passed: main CRM inventory snapshot, Alerts issue
selection, dashboard catalogue status recovery and dashboard inline syntax.
Production QA of the two new API files and the dashboard behaviour remains
with the owner.

## 2026-09-27: 3D-P archive committed despite `Missing token` response

The owner reports CRM Web App V192 and Alerts Web App V8 at 07:55, followed by
an archive error for `ACC-3D-TCG-600`: `3D-P статус не змінено: Missing token.`
The supplied screenshot shows Script Properties of the **Automation/Alerts**
project, which are separate from both the 3D-P project's properties and the
browser's local 3D-P credentials. The checked-in 3D-P source returns `Missing
token.` only when the received request supplies an empty token; a nonempty but
wrong token produces `Invalid token.` This error therefore does not prove that
the owner omitted a Script Property. The exact transport failure remains
unverified because no browser network trace or current bound 3D-P export was
provided.

A bounded SKU search in `Номенклатура!A1:O300`, followed by a read of `P74`, found
`ACC-3D-TCG-600` with API status `Архів`. Its status history records
`2026-09-27 07:59:02 [dashboard] Статус: Активний → Архів`, confirming that
the write committed despite the error shown to the owner. No repeat archive
request or direct Sheet write was made by the agent.

The local `toggleThreeDpArchive` now treats a failed 3D-P POST response as
ambiguous and reads that exact SKU through `3dp_get_row`. When the target
status is confirmed it reports success and does not roll back CRM. When the
original status remains it may roll back a CRM status that this action changed.
When the readback fails or yields another status it leaves the paired status
untouched and tells the owner not to retry before reconciliation. Focused tests
cover all three paths, and the dashboard inline-script syntax test passed.
This is a local dashboard change; no new Apps Script code or deployment is
required for it.

## 2026-09-27 release audit before repository sync

The owner authorized committing, pushing, and updating task progress. The
owner reports deployed CRM V192 and Alerts V8. These are owner reports, not
fresh bound-project exports; the repository mirrors match the complete local
paste files supplied for those versions. The dashboard is the canonical local
file. The `ACC-3D-TCG-600` 3D-P archive was confirmed from its exact status
cell and history, even though the browser reported `Missing token.`

CRM-016 remains open for the following acceptance gaps:

1. The first attempt to remove `PKM-EN-CHRS-BST` from `OC-FOP-0335` was
   blocked by later FIFO sales. The subsequent API revision permits the
   read-only-inspected one-lot preorder pattern, where every later sale refers
   to that same arrived lot. The specific edit has not been retried live after
   that revision. Other later-sale patterns remain blocked pending a bounded
   FIFO replay design.
2. `BR-DITTO-400` has a CRM sale without a matching 3D-P sale entry. The
   marketing write-off used as a proxy also created a UAH 30 expense and
   Serhiy accrual. V192 hides an unverified physical/deficit figure and keeps
   the sync exception; it does not reconcile these ledgers.
3. The old `Склад!H` formulas and `Черга_Складу!H30` still need a separately
   gated repair. Targets and read-only preflight are recorded above; no live
   formula write was performed. This prevents claiming that every legacy stock
   surface is corrected.
4. The owner-reported clean V190 integrity check does not prove V192 stock,
   purchase, order correction, finance or 3D-P behaviour. A post-V192 live QA
   pass and fresh performance measurements are outstanding. The earlier
   dashboard telemetry showed slow Finance/Overview loads, so speed is not
   closed by the code review alone.

The Telegram webhook issue was owner-reported fixed. The purchase, alerts,
finance and 3D UI fixes have focused local tests, but their live acceptance is
distinct from those tests. Do not mark CRM-016 Done until the owner accepts the
remaining behaviour or explicitly splits these gaps into follow-up tasks.
The 30 focused/local compatibility checks in the release pass succeeded.
The wider `dashboard-contract.test.mjs` still has a category-list assertion
failure: its expected 3D category `Кейс / контейнер для зберігання` differs
from the current dashboard/API category registry. That registry belongs to a
separate 3D nomenclature scope and was not changed to make this test pass.
