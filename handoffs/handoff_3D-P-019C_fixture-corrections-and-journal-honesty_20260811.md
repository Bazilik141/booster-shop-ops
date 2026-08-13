# 3D-P-019 phase C — fixture usage corrections, honest journal outcome, historical payer cleanup

Date: 2026-08-11 · Task: `3D-P-019` (phase C, pre-deploy delta on the un-deployed phase B revision)

**Executor: Codex · model=Sol · effort=xhigh**

Justification: Codex authored phase A and the un-deployed phase B revision in this same `Code.gs`
this round. Swapping executors mid-round would be a parallel-writer violation. The work is a CRM
financial and sheet-structure change — a risky zone — so it does not go on a small model.

---

## 1. Task ID

`3D-P-019` — phase C. This is a **delta on top of the current un-deployed working-tree revision of
`crm/apps-script/Code.gs`**, not a new feature and not a rewrite of phase B.

## 2. Context

Phase A is live: CRM Apps Script **V102**, 2026-08-11 09:55 Kyiv. Byte-verified by the reviewer —
the owner's V102 export equals the repository `HEAD` copy of `Code.gs` plus exactly the
`setup3dp019FixturePayerPhaseA` block (+109 lines, 0 deletions), and contains **no** phase B code.

Phase B is implemented in the working tree and **not deployed**. Independent review found the happy
path correct: F9 is enforced both in `build3dp019FixtureUsagePlan_` and in the on-edit guard, F6
warns without blocking, `crm3dpFixtureFrozenForOrder_` guards both mixed payer and zero 3D-P units,
the `Розхідники!H` `SUMIFS` matches on fixture code **and** payer, and the new formula satisfies —
rather than violates — the existing `crmIntegrityCheckRowFormulas_` rule. All three local test
suites pass.

Three findings were raised. The owner decided on 2026-08-11:

| # | Finding | Owner decision |
|---|---|---|
| 1 | No correction path exists through the forms | **Build a correction path** |
| 2 | Historical `Продажі!W` values are fabricated | **Reviewer's choice** — see §4 WP3 |
| 3 | Frozen `V` is an order-level average, not per-SKU actual | **Accepted as-is** for the current CRM. Do not change. Record it as a known limitation |

### Why finding 1 blocks deployment

`build3dp019FixtureUsagePlan_` rejects any second entry for the same `(source, reference)` pair with
`Фурнітура для … уже внесена в журнал. Повторне списання заблоковане.` Combined with
positive-only quantity and an append-only ledger, there is currently **no way through the UI** to add
a forgotten fixture line or to correct a wrong quantity. The phase B rollback plan's own instruction
— "append an equal and opposite correction" — is not executable through any form. The only remaining
route is hand-editing a financial ledger, which bypasses every validation phase B just built. F6
deliberately allows saving an operation with insufficient stock, so the owner will reach this state
in normal use.

### Why finding 2 needs action

The **currently live** V102 hardcodes `fixture_payer: 'власник'` in `crm3dpFrozenSaleInputs_` and
derives the fixture cost from the `Номенклатура!N` reference price. Every `Продажі!W` written so far
therefore claims `власник` regardless of the truth, and no fixture was ever actually consumed —
the ledger did not exist. After phase B deploys, the same column holds fabricated old values and
truthful new ones with no marker between them.

## 3. Goal

Make the fixture usage ledger correctable through the existing forms, make the sync journal name the
real cause when fixture allocation fails, and remove the fabricated payer claims from pre-cutover
3D-P sale rows — without touching the phase B happy path that review already cleared.

## 4. What to change

Three work packages. WP1 and WP2 ship in the same paste (Apps Script is a single file). WP3 is a
**separately invoked** owner-run setup action so it keeps an independent rollback.

### WP1 — honest journal outcome for fixture allocation failure

`crm3dpFixtureFrozenForOrder_` throws on a mixed-payer ledger and on zero 3D-P units. The throw is
caught by the outer `catch` in `sync3dpSales_` and journalled as `skipped_api_error`. That is false:
the API is healthy and the problem is in the data. The same `catch` already special-cases
`skipped_schema`, so the pattern exists.

Add a dedicated outcome — suggested name `skipped_fixture_allocation` — resolved the same way the
schema case is resolved. Prefer a recognisable error marker over string-matching a human-readable
sentence: string equality on a message is brittle, and the existing `skipped_schema` check is
already the fragile precedent, not the model to copy.

Fail-open is unchanged: the CRM sale still saves.

### WP2 — fixture usage corrections

**Entry point: the existing `Оновити_продаж` fixture rows.** Do not add a new form area. Today,
entering fixture lines there for an order that already has ledger rows is rejected. Change that path
so it appends **correction** rows instead.

Required semantics — every one of these is a hard requirement, not a preference:

1. **New ledger `Джерело` value `Коригування`.** Existing `Продаж` and `Списання` rows are never
   modified or deleted. The ledger stays append-only.
2. **Signed quantity.** A correction row may carry a negative quantity. `Продаж` and `Списання`
   rows keep the positive-only rule.
3. **The frozen unit cost of a correction is copied from the ledger rows it corrects**, never re-read
   from `Розхідники`. If the fixture price changed between the sale and the correction, a
   re-read would make the correction fail to cancel the original.
4. **Reference stays the reference of the corrected operation** (order id / `WRT-*` id), so the
   `Розхідники!H` `SUMIFS` and the `V`/`W` derivation net out without extra logic.
5. **The `(source, reference)` uniqueness guard must exempt `Коригування`** — otherwise the second
   correction is blocked exactly like the first was.
6. **F9 must hold on the net result.** After applying corrections, the set of payers for one
   reference must still be exactly one. A correction may never introduce a second payer.
7. **Net quantity per `(reference, fixture, payer)` may not go below zero.** Reject with a message
   that states the current net and the requested change.
8. **`crm3dpFixtureFrozenForOrder_` must include `Коригування` rows** in its filter. It currently
   selects `source === 'Продаж'` only, so corrections would be silently ignored and `V` would never
   move. If the net total reaches `0`, emit `V = 0` and `W` blank — the same representation phase B
   already uses for a sale with no fixture.

**The hard part — an already-synced 3D-P sale row.** `V`/`W` are written when the 3D-P sale row is
created; the `matches` path only writes the `G` expense. A correction entered after the sale has
synced will therefore not reach the 3D-P workbook.

Recommended resolution: **update `V`/`W` on the existing 3D-P sale row** via the existing
`3dp_write` action with `expected_current` optimistic locking, and journal the outcome. Rationale:
`3D-P-015` freezes sale economics to protect a recorded sale from *later price changes* — not from a
correction *of that same operation*. A value that is frozen and known to be wrong is the exact defect
`3D-P-019` exists to remove. If the executor finds a concrete reason this is unsafe, **stop and put
it to the owner** rather than choosing silently — this is the one point in this handoff where the
owner may want to overrule.

If the write cannot be applied (lock mismatch, API down), fail open and journal it. Never block the
CRM-side correction because the 3D-P side is unreachable.

### WP3 — clear fabricated payer data on pre-cutover 3D-P sale rows

**Reviewer's decision on finding 2, per the owner's delegation:** do **not** backfill and do **not**
invent a marker column. Clear the fabricated values, so that the absence of data reads as
"unknown" instead of asserting a payer that was never verified. Blank is truthful; `власник` is not.
This also produces exactly the representation phase B already emits for a fixture-free sale
(`V = 0`, `W` blank), so no consumer needs a special case.

One owner-run, idempotent action, separate from `setup3dp019FixtureUsagePhaseB()`:

1. **Report before writing.** A dry-run mode that returns how many 3D-P `Продажі` rows carry a
   non-empty `W` or a non-zero `V`, and how many of those have **no** matching ledger row. The owner
   sees the counts before anything is written.
2. Write only to rows with **no** corresponding `Використання_фурнітури` entry. A row the ledger
   covers is real data and must never be touched.
3. Set `V = 0` and `W = ''` on those rows only.
4. Idempotent: a second run reports `already_applied` and writes nothing.
5. Return counts: rows inspected, rows skipped because the ledger covers them, rows cleared.

Run order at deploy time: phase B setup first, then this — so the ledger exists and step 2 can
distinguish covered rows.

## 5. Do not touch

- The phase B happy path already cleared by review: F9 enforcement logic, the F6 warning behaviour,
  the `Розхідники!H` `SUMIFS` shape, the ledger schema and its helper column, the on-edit guard.
- **Finding 3 — the order-level averaging in `crm3dpFixtureFrozenForOrder_` stays exactly as it is.**
  The owner accepted it for the current CRM. Record it as a known limitation in the report; do not
  "improve" it.
- `setup3dp019FixturePayerPhaseA()` — it is live in V102 and must stay byte-stable.
- `CRM_3DP_SALES_FROZEN_HEADERS_` and the `T:W` header contract. WP2 changes the **values** in `V`/`W`,
  never the header set. Changing the header set breaks the sync across two deployed scripts at once.
- Any `Продажі` row that the fixture ledger covers.
- `Списання` semantics — normal sale consumption is still not a write-off.
- Serhiy pending purchases and the owner-confirmed import (F3/F5) — out of scope, still phase D.
- The 3D-P Apps Script project. This handoff is main-CRM `Code.gs` only.
- Protected zones, untouched by this task and listed for completeness: `sitemap.xml`, `robots.txt`,
  redirects, canonical, `.htaccess`, checkout, payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas

Likely, to be verified by the executor against the actual working-tree file — not confirmed:

- `crm/apps-script/Code.gs`
  - `sync3dpSales_` outer `catch` — WP1
  - `crm3dpFixtureFrozenForOrder_` — WP1 error marker, WP2 item 8
  - `build3dp019FixtureUsagePlan_` — WP2 items 1, 2, 5, 6, 7
  - `append3dp019FixtureUsage_`, `next3dp019FixtureUsageIds_` — WP2 items 1–4
  - the `Оновити_продаж` fixture-line path — WP2 entry point
  - a new owner-run setup action — WP3
- `crm/apps-script/tests/3d-p-019-fixture-usage.test.mjs` — extend
- `crm/apps-script/SOURCE_STATE.md` — refresh after the owner deploys
- `diagnostics/3D-P-019_phase-c_report_20260811.md` — new

## 7. Acceptance criteria

Measurable, all locally provable before deployment:

1. A mixed-payer ledger state journals `skipped_fixture_allocation` — not `skipped_api_error` — and
   the CRM sale still saves.
2. Zero 3D-P units with fixture ledger rows journals the same outcome, sale still saves.
3. A correction with negative quantity appends a `Коригування` ledger row; the original row is
   byte-unchanged.
4. The correction's frozen unit cost equals the corrected row's unit cost, proven by a test in which
   `Розхідники` unit cost changed in between.
5. A correction that would introduce a second payer for one reference is rejected, and the message
   names both payers.
6. A correction driving net quantity below zero is rejected, and the message states the current net
   and the requested change.
7. Two consecutive corrections on one reference both succeed — the `(source, reference)` guard does
   not fire for `Коригування`.
8. `Розхідники!H` for the affected fixture equals the net of `Продаж` + `Списання` + `Коригування`
   rows for that fixture and payer.
9. `crm3dpFixtureFrozenForOrder_` includes corrections; a full reversal yields `V = 0` and `W` blank.
10. WP3 dry-run returns row counts and writes nothing.
11. WP3 leaves every ledger-covered row untouched, clears only uncovered rows, and a second run
    reports `already_applied` with zero writes.
12. `node crm/apps-script/tests/3d-p-019-phase-a.test.mjs`,
    `node crm/apps-script/tests/3d-p-019-fixture-usage.test.mjs` and
    `node crm/apps-script/tests/integrity-check.test.mjs` all pass.
13. `Code.gs` parses (`new Function(readFileSync(...))` — `node --check` rejects the `.gs`
    extension on Node 24).

## 8. QA / smoke test

Local, by the executor, before handing back: acceptance criteria 1–13.

Owner-run, on production, after the owner pastes and publishes — **no staging exists, every
deployment is live**:

1. Create a named Google Sheets version.
2. Run `integrity_check`; keep the output as the phase C baseline.
3. Paste the complete current `Code.gs`; publish a new Web App version; record its number and time.
4. Run `setup3dp019FixturePayerPhaseA()` — must return `already_applied: true`. This is the expected
   result, not an error.
5. Run `setup3dp019FixtureUsagePhaseB()` once.
6. Run the WP3 action in **dry-run** first; read the counts; then run it for real.
7. Run `integrity_check` again. Any new problem is a defect of this change.
8. Reversible test sale with a fixture line → check the ledger row, `Розхідники!H`, and `V`/`W` on
   the 3D-P sale row.
9. On the same order, enter a **negative** fixture line through `Оновити_продаж` → check that a
   `Коригування` row appended, `H` netted, and `V`/`W` updated on the existing 3D-P sale row.
10. Attempt a mixed-payer correction → confirm the F9 message names both payers.
11. Attempt a correction below zero net → confirm rejection.
12. Confirm the ledger stores a **frozen** unit cost, not a reference to `Розхідники` — the point of
    the ledger is that tomorrow's fixture price does not rewrite yesterday's operation.

## 9. Rollback note

- **WP1/WP2 (code):** revert to the V102 source, which is byte-verified against the owner's export
  `CodeJS - CRM (Версія 102, …).txt` in the repository root, and re-publish. Leave the ledger and the
  form areas in place; they are inert without the code.
- **WP2 (data):** never delete ledger rows. An incorrect correction is undone by a further approved
  correction.
- **WP3:** this one is genuinely destructive to values that were fabricated but are still values.
  The named Google Sheets version from QA step 1 is the rollback. Run the dry-run first and keep its
  output — that is the only record of what the rows held before.
- **Phase A:** unchanged and not part of this rollback.

## 10. Recommended status after execution

`3D-P-019` stays `In progress`. Phase D (Serhiy pending purchases, owner-confirmed import, F3/F5) is
still open, so this task does not reach `Done` on phase C. Recommendation only — the owner authorizes
closure and Claude (chat) performs any Notion write.

## Delivery

This is Apps Script, not an OpenCart patch — there is no `patches/` file and no `php <patch>.php`
step. Delivery is: the executor edits `crm/apps-script/Code.gs` in the repository, runs the local
suites, and writes `diagnostics/3D-P-019_phase-c_report_20260811.md`. **The owner** then pastes the
file into the live bound script editor and publishes the new Web App version. The executor never
commits, pushes, publishes or deploys, and does not update the deployed-version line in
`SOURCE_STATE.md` until the owner reports the published version number.
