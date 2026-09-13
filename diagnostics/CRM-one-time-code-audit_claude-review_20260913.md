# Claude review — CRM one-time main-code audit (2026-09-13)

Verdict: **Review OK with one blocker and two returned defects.**
The audit's reasoning is sound and its confirmed-spent table is correct as far as
it goes. It must not be turned into a cleanup patch until A1 is resolved.

Verified against the staged mirror `crm/apps-script/Code.gs`
(679,087 bytes, staged 2026-09-13 16:43) and
`dashboard/booster-dashboard.html`. Method: full-file reference count per
identifier, enclosing-function resolution for every call site, custom-menu
`addItem` targets, `ScriptApp.newTrigger` targets, `doGet`/`doPost` action
dispatch, and a scan for dynamic dispatch (`eval`, `new Function`,
`globalThis[name]`) — none exists, so static reachability is authoritative for
everything except triggers installed by hand in the project UI.

---

## A1 — BLOCKER: the mirror already diverges from live V176, in the cache path

The audit's first implementation rule is "use a fresh live source export as the
baseline". The divergence it warns about is not hypothetical — it is already
present and it is nameable.

| Where | Live V176 (per CRM-013 report) | Staged mirror today |
| --- | --- | --- |
| `crm013EncodeCacheValue_` `Code.gs:1723-1727` | gzip + Base64 for values above 95 KB | plain only; returns `value_too_large` above 95 KB |
| `crm013WriteCacheValue_` `Code.gs:1729-1736` | 12 KB shard manifest + parts, `stored_gzip_sharded` | single `cache.put`, no shard path |

`SOURCE_STATE.md` records no baseline after **V173 (2026-09-11)**. V174, V175
and V176 were published but never recorded.

Consequence: a cleanup patch built on this mirror and pasted whole would
silently revert the live gzip/shard cache implementation as an undeclared
side effect of a change described as "deletion of spent code".

Note separately that reverting it is the *correct* end state on the measured
evidence — the shard path produced `overview_secondary` 5,211 → 6,091 ms and
`ltv_report` 1,848 → 2,972 ms with zero cache hits, i.e. pure cost. The defect
is not the code change; it is that the change is undeclared, unrecorded, and
would ride into production inside an unrelated patch.

Required before any cleanup work:

1. Owner supplies a fresh complete `.gs`/`.txt` export of the live project.
2. Compare it with the mirror; record the result and every V174-V176 delta in
   `SOURCE_STATE.md`.
3. Declare the cache revert as its own reviewed line item with its own
   before/after evidence, not as cleanup collateral.

---

## Question 1 — is the confirmed-spent table complete?

**The seven rows are correct. The inventory they sit in is not complete.**

Every confirmed-spent entry verified: definition only, no call site anywhere in
`Code.gs`, no string in `booster-dashboard.html`, no `addItem` menu target, no
`newTrigger` handler.

| Function | Refs in `Code.gs` | Dashboard | Menu | Trigger |
| --- | ---: | ---: | --- | --- |
| `diagnoseCrmStockCounting20260901` / `repairCrmStockCounting20260901` + 9 `crmStockCounting*` helpers | definition only; all 9 helpers referenced exclusively inside 3975-4163 | 0 | no | no |
| `crm011BackfillPaymentDates_` | 1 call, from `setupCrm011FinanceColumns` only | 0 | no | no |
| `setupCrm011FinanceColumns` | 0 calls (2 hits are a comment and an error string) | 0 | no | no |
| `setupCrm012RevenueRecognition` | 0 | 0 | no | no |
| `setupCrm012PurchaseSupplierForm` | 0 | 0 | no | no |
| `crm012ZenRecordHistoricalTopupUahForOwner` | 0 | 0 | no | no |
| `crm012ZenSheetFormMislabelScanForOwner` / `crm012RepairLot0181AfterOwnerApproval` | 0 | 0 | no | no |
| `crm0123dpNameDivergenceReportForOwner` | 0 | 0 | no | no |

Block boundaries are contiguous and self-contained. `3975-4163` sits between
`inventoryMigrationEnsureReservationStockFormulas_` (ends 3973) and
`inventoryMigrationRollback_` (starts 4165) with no interleaving, and no
`crmStockCounting*` helper is referenced from outside it.

### Defect 1 — three entries are missing from the audit entirely

| Function | Line | Refs | Classification the audit owes it |
| --- | ---: | ---: | --- |
| `tgSetupCommands` | 6912 | 0 | One-time Telegram command registration. Spent or owner-run maintenance — unstated. |
| `testNewsEditorialAudit` | 8570 | 0 | A development test entry point living in the production main file. Not a repair, not product behaviour. |
| `setupNewsDigestTrigger` | 7323 | 0 | Trigger installer. Belongs on the **keep** list for the same reason `runNewsPruneOnce` does; its absence makes the keep list look like it was assembled ad hoc. |

Add `setupCrmRowCapacityTrigger` (`Code.gs:1029`) explicitly to the keep list as
well — the current entry says "row-capacity setup" without naming it.

### Defect 2 — every deletion orphans a recovery instruction

Four of the deletion sets are named in error or hint text that survives them:

- `Code.gs:9790` — `'CRM-011_SETUP_REQUIRED: спочатку запусти setupCrm011FinanceColumns …'`
- `booster-dashboard.html:2642` — `'Спочатку один раз запусти setupCrm011ZenMarketAccount у Apps Script.'`
- `Code.gs:7179`, `8746`, `9320` — the same pattern for the three of the four
  older wrappers.

Each cleanup patch must rewrite the message it invalidates in the same commit.
An error telling the owner to run a function that no longer exists is worse
than the dead code it replaced.

### Residual, not closable from here

Static search cannot see triggers installed by hand in the Apps Script UI. Before
the deletion patch, read the project's **Тригери** list once and record the
handler names in `SOURCE_STATE.md`. This is the one reachability dimension the
audit correctly flags and neither it nor this review can prove.

---

## Question 2 — the CRM-011 FIFO transcript conflict

**Technically the set is free. The conflict is resolvable with one read-only
owner command instead of a documentation ruling.**

The set at `Code.gs:9136-9277` is completely isolated: all four constants
(`CRM011_FIFO_COST_DEFAULT_SKU_`, `_TOLERANCE_`, `_MAX_ROWS_`, `_ORDER_`) and all
eight functions have **zero** references outside that range. Deleting it cannot
break anything.

Two corrections to the audit:

1. The range ends at **9277**, not 9276. Line 9277 is the closing brace of
   `repairCrm011OcFop0324`. Deleting `9136-9276` leaves a stray `}` and the file
   will not parse.
2. The conflict does not need to be settled from documents. `previewCrm011OcFop0324Repair()`
   (`Code.gs:9263-9269`) calls `repairCrm011FifoCostRows_` with `dry_run: true`
   and performs **no writes** — it only compares the frozen unit cost on
   `OC-FOP-0324 / PKM-EN-Q2-MTIN-SAL` against a recomputed FIFO cost.

Recommended resolution — one owner run, no risk:

```
previewCrm011OcFop0324Repair()
```

- `rows[0].would_change: false` → the drift is gone; delete `9136-9277` with the
  confirmed-spent wave and record the preview output as the evidence.
- `rows[0].would_change: true` → the repair is still needed. Do not delete it;
  move the block to a standalone `TEMP_CRM011_OCFOP0324_*.gs` task file, run it,
  then delete it from both places.

That converts an unresolvable documentation conflict into a recorded fact.

---

## Question 3 — the ZenMarket setup split

**Confirmed, and the reachability is worse than the audit states.** The audit
says `crm011ZenEnsureSheet_` "can write missing headers during ordinary paths".
The specific ordinary path is a cached **GET**:

```
doGet  action=finance_report   Code.gs:1438
  apiFinanceReport_            Code.gs:10657
    crm011ZenBalanceSnapshot_  Code.gs:10674 → 10188
      crm011ZenRequireSetup_   Code.gs:10190 → 9911
        crm011ZenEnsureSheet_  Code.gs:9918
          sheet.getRange(1, index + 1).setValue(headers[index])   Code.gs:9900
```

`crm011ZenRequireSetup_` passes `appendOnlyHeaders = true` for
`ZenMarket_Поповнення` (9918), which reaches the `setValue` at 9900; it also
reaches the full header write at 9892 when row 1 is blank. So opening the
**Фінанси** page can mutate sheet structure. This is a live defect today,
independent of any cleanup, and it is also why the cleanup cannot proceed here
first.

Required shape:

- `crm011ZenRequireSetup_` becomes a pure validator: the three sheets must exist,
  their headers must match, otherwise throw `ZENMARKET_SETUP_REQUIRED` /
  `ZENMARKET_SCHEMA_CONFLICT`. No `insertSheet`, no `setValue`, no
  `setFrozenRows`.
- All creation and header-writing moves into `setupCrm011ZenMarketAccount()`,
  which is the only function allowed to mutate the schema.
- The other 12 call sites keep working unchanged, because all of them already
  require the sheets to exist.

Only after that is published and the three sheets are verified can the setup/seed
code leave the main file.

---

## Question 4 — the four older structural setup wrappers

**Later wave. Keep them for now — but not for the reason the audit gives.**

`setupCrm004PackagingValidation` (5227), `setup3dp019FixtureUsagePhaseB` (7012),
`setupOrderComponentUsage` (8712) and `setup3dpOrderLineAccountingCRM` (9408)
all have zero callers. Their second reference is an error string that names them
as the recovery instruction when the schema they build is missing
(`7179`, `8746`, `9320`).

That is their value: they are not spent one-off code, they are the documented
repair route for a sheet or column that the owner might delete or rename. They
cost four function bodies. Removing them removes the only in-product answer to
"this tab is missing, now what".

Condition to revisit, not before: a bounded read-only schema proof per feature
**and** rewritten error messages pointing at whatever replaces them. Neither is
worth doing in this round.

---

## Agreement with the audit, unchanged

- The read-only diagnostics `crm011PaymentDateAuditForOwner` (10651) and
  `crm011PassBVerificationForOwner` (10696) are owner-choice. They write nothing;
  the payment-date audit in particular is a reasonable periodic finance control.
  Recommendation: keep both.
- The keep list is correct for the entries it contains.
- Rule 5 of the implementation boundary — every future temporary repair lives in
  its own task-named file and is deleted locally and live after its verified run
  — is the rule that prevents this audit from being needed again. It should be
  promoted out of this report into `ROADMAP_SOP.md`.

## Sequence

1. Fresh live export; record V174-V176 and the cache-path delta in `SOURCE_STATE.md`. (A1)
2. Read the installed trigger list; record the handler names.
3. Run `previewCrm011OcFop0324Repair()`; record the output.
4. Publish the ZenMarket validator/setup split on its own, with integrity before/after.
5. Only then: the confirmed-spent deletion patch, plus the three unclassified
   entries above, plus the error/hint text each deletion invalidates.

---

# Addendum — 2026-09-13, later: ZenMarket read-only validator candidate

Reviewed `diagnostics/CRM-zenmarket-read-only-validator_report_20260913.md`
against the mirror (`Code.gs`, 680,103 bytes, staged 2026-09-13 18:57).

Verdict: **Review OK. Publish it — with one correction to the publication gate.**

## Blocker A1 is closed, and closed properly

`SOURCE_STATE.md` now records **V177 BYTE-VERIFIED (2026-09-13)**, normalized
SHA-256 `5eaf9171…fb8a5a`, 10,704 lines, plus the CRM-013 cache outcome as it
actually stands live (`value_too_large`, no gzip, no shards). That is the fresh
export A1 required, and it closes the V173→V176 recording gap.

Two further gaps from the main review are also closed with evidence:

- **Installed triggers read from the project UI**: `maintainCrmRowCapacity`,
  `runNightlyInventoryMaintenance`, `keepWarm`, `runNewsPruneOnce`. No deletion
  candidate is among them. That was the one reachability dimension neither the
  audit nor this review could prove statically.
- **`previewCrm011OcFop0324Repair()` was run**: `Продажі!289`,
  `would_change:false`, `already_applied:true`, both sides 551.90 / 585.01.
  Question 2 is resolved as "spent"; the FIFO block joins the deletion wave and
  needs no temporary wrapper. The line-range correction still stands: delete
  **9136–9277**, not 9136–9276.

## Scope of the diff — proven, not asserted

Independently verified rather than taken from the report: reversing only the
validator change in the current mirror — restoring the three
`crm011ZenEnsureSheet_` calls inside `crm011ZenRequireSetup_`, deleting
`crm011ZenValidateSheet_` and its two comment lines — reproduces exactly

```
5eaf9171fdfd4be5edf05c2037939e2fa16bd3a41fdcb72b5408604241fb8a5a
```

at 10,703 + 1 lines. The ZenMarket validator split is therefore the **only**
change to `Code.gs` since live V177. Nothing else rides along in this paste.

## The change itself

Correct and minimal. `crm011ZenValidateSheet_` (`Code.gs:9913-9927`) reads and
throws only. `crm011ZenRequireSetup_` (`9929-9939`) routes all three sheets
through it. `crm011ZenEnsureSheet_` retains the writes but is now referenced
only from `setupCrm011ZenMarketAccount()` (`9963-9965`) — no orphan, no second
schema writer. The GET→`setValue` path documented in Question 3 is gone.

The only behavioural delta is the append-only branch for
`ZenMarket_Поповнення`: blank header cell, previously `setValue`, now throw.
The strict branches for `ZenMarket_Рахунок` and `ZenMarket_Лоти` are unchanged —
they threw on any mismatch before and still do.

## Returned defect — the publication gate does not test what changed

The gate's step 3 is "open Finance once, run `integrity_check`". That step
**cannot fail**, whatever the sheet headers look like.

`crm011ZenBalanceSnapshot_` (`Code.gs:10207-10213`) wraps its
`crm011ZenRequireSetup_` call in `try/catch` and returns
`{ available: false, setup_required… }` on any throw. Finance degrades to the
"run setup once" hint (`booster-dashboard.html:2642`) and renders normally. So
the one page the gate exercises is the one page that swallows the new error.

Every other caller is unwrapped and now fails hard where it used to self-heal:
`addPurchase` (138), `updatePurchase` (250), `apiUpdatePurchaseBatch10_` (3345),
`crm011ZenmarketTopup_` (10048), `crm011ZenmarketCorrectBalance_` (10074),
`crm011ZenEnsurePurchaseBaselines_` (10172), `crm011ZenSyncPurchaseLots_`
(10186), `crm011ZenMarketVerificationForOwner` (10215), `crm011ApiAddPurchase_`
(10252). A single blank header cell in `ZenMarket_Поповнення` would block the
ZenMarket purchase path entirely while Finance keeps looking fine.

Residual risk is low — on live V177 the old self-heal has already written any
blank header permanently, and a *wrong* non-blank header would have thrown
under the old code too. But low is not verified.

### Corrected gate

1. **Before publishing** — look at row 1 of `ZenMarket_Поповнення`. All ten
   cells must be non-empty and read exactly: `Payment ID | Дата | Сума JPY |
   Курс JPY за 1 UAH | Сума UAH | Gateway | Джерело оцінки | Примітка |
   Request ID | Створено`. If any cell is blank, run
   `setupCrm011ZenMarketAccount()` first, on the current live version.
2. Paste and publish the reviewed candidate.
3. `apiIntegrityCheck_()` — expect clean.
4. **`crm011ZenMarketVerificationForOwner()` — expect `ok: true`.** This is the
   step the report is missing: it is the only single command that runs the new
   validator on all three sheets without a `try/catch` in front of it.
5. Open Фінанси once and confirm the ZenMarket KPI still shows a real JPY
   balance, not the "run setup once" hint. A hint there means step 4 lied or
   was skipped.

Report step 4 — never manufacture a broken header in production to test the
failure path — is correct and should stay.

## One correction to the report's closing sentence

The report ends "…before this candidate becomes live **or the Zen setup/seed
code can be deleted**." Deleted is the wrong word for this block.

`setupCrm011ZenMarketAccount()` is now the sole schema writer *and* the recovery
route named in two live strings: the validator's own
`ZENMARKET_SETUP_REQUIRED` (`9933`) and the dashboard hint
(`booster-dashboard.html:2642`). The historical seed is not separable from it
either — `crm011ZenHistoricalRows_()` doubles as the re-run guard
(`setupCrm011ZenMarketAccount` requires `ZEN-HIST-000` and `ZEN-HIST-011` to be
present before it will accept a non-empty ledger).

So: **move** the block — `setupCrm011ZenMarketAccount`, `crm011ZenEnsureSheet_`,
`crm011ZenHistoricalRows_` — to its own file inside the same Apps Script
project, where it stays callable and both messages stay true. Do not delete it
live. This is the same distinction as Question 4: a completed one-off repair is
spent; a setup function is a standing recovery tool.
