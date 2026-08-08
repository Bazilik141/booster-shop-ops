# Patch Handoff — 3D-P-022: align the SKU trigger with the canonical convention

Date: 2026-08-08 · Notion: `3b66bf20-bdb4-81b0-ad36-d2e9fb81cb52` · related: `3D-P-014`, `3D-P-010` WP4, `3D-P-CARDCONTENT`
**Executor: Codex · model=Terra · effort=medium-high** — Codex is already in `crm/apps-script/Code.gs`
for `3D-P-014` this round, so keeping the same author avoids a parallel-writer violation. The change
itself is small and now fully specified, so `Sol/xhigh` is not warranted; but it sits in the CRM sync
trigger, so not a small model either. **Owner decides.**

> **This is a prerequisite for `3D-P-014` owner QA, not a follow-up.** As of 2026-08-08 there is no
> SKU that satisfies all three conditions needed to run that QA at once. Fix this first.

## 1. Task ID

`3D-P-022`

## 2. Context

Found during `3D-P-014` owner QA on 2026-08-08. There are **three different definitions** of a valid
3D-print SKU in the system, and they disagree on the `ACC-3D-` family. All three were read from
source, not inferred:

| Surface | Pattern for `ACC-3D-` | File |
|---|---|---|
| CRM sync trigger `is3dpPackagingSku_` | `ACC-3D-\d{3}(?:-[A-Z0-9]+)*` — three digits **immediately** after the prefix | `crm/apps-script/Code.gs:826` |
| Dashboard create-form validator `threeDpSkuTypeError` | `ACC-3D-\d{3,}` — three or more digits, **nothing allowed after them** | `dashboard/booster-dashboard.html:859` |
| Canonical convention (owner-approved 2026-08-07) | `ACC-3D-<МНЕМОНІКА>-<XYZ>`, e.g. `ACC-3D-PKM-130` | `plans/3D-P_sku-naming-convention_20260807.md` |

Verified by running the patterns:

```
ACC-3D-DITTO-410   trigger: NO    dashboard: NO    convention: YES
ACC-3D-PKM-130     trigger: NO    dashboard: NO    convention: YES
ACC-3D-410         trigger: YES   dashboard: YES   convention: NO
FIG-CHARM-001      trigger: YES   dashboard: YES   legacy, works today
BR-CHARM-100       trigger: YES   dashboard: YES   convention: YES
```

Consequences today:

1. **Every `ACC-3D-` SKU in the approved convention table fails the sync trigger** — all of
   `ACC-3D-PKM-110/120/130/200/201/202/300`, `ACC-3D-DITTO-410/420`, `ACC-3D-OP-500/600`. The whole
   functional-accessory family would silently never reach the 3D-P workbook.
2. **The owner cannot even register such a SKU through the dashboard** — the create form rejects it
   with «SKU має відповідати BR-…, FIG-… або ACC-3D-….»
3. The `3D-P-014` journal reports case 1 as `skipped_no_3dp_sku`, which reads as "this order has no
   3D product" rather than "this SKU shape is not recognised". The failure is visible but misleading.

`BR-` and `FIG-` are already permissive in both validators and are **not** affected.

The 3D-P Apps Script API has **no** SKU-shape validation at all (verified — the only regexes there
cover ledger reasons, column refs and range parsing). So the two surfaces above are the only
enforcement points.

Root cause: the trigger was written 2026-08-02, the dashboard validator on 2026-08-02, and the
convention was agreed 2026-08-07. Nobody reconciled the three.

## 3. Goal

A SKU written per the canonical convention is recognised everywhere: accepted by the dashboard create
form, and recognised by the CRM sync trigger. Nothing that works today stops working.

## 4. What to change

The two surfaces get **deliberately different strictness**. This is the core of the design, not an
inconsistency:

- **The sync trigger must be permissive.** Its job is "never miss a 3D-P sale". A false negative is
  silent data loss — the exact failure class this whole task family exists to remove.
- **The create form should be strict.** Its job is "don't let a malformed SKU into the catalogue".
  A false positive here just shows a human an error message, which is cheap and self-correcting.

**4.1 CRM trigger — widen `ACC-3D-` to match the style `BR-`/`FIG-` already use.**

Make the `ACC-3D-` branch accept a mnemonic segment. The three branches then collapse to one shape:

```
^(?:BR|FIG|ACC-3D)-[A-Z0-9][A-Z0-9-]*$
```

This accepts the canonical form (`ACC-3D-PKM-130`), the SKU already in the CRM catalogue
(`ACC-3D-DITTO-410`), the legacy shape (`ACC-3D-410`) and everything `BR-`/`FIG-` accept today.
No SKU that currently syncs stops syncing.

No collision with non-3D accessories: those are numbered `ACC-0XX` (see the convention doc, and
`plans/accessories_sku_cards_20260620.md`), and none of them begin with the literal `ACC-3D-`.
Confirm this against the live CRM `Товари` before shipping.

**4.2 New journal outcome `skipped_sku_shape`.**

When a sale line's SKU starts with `BR-`, `FIG-` or `ACC-3D-` but still fails the full trigger
pattern, journal it as `skipped_sku_shape` with a `detail` naming the offending SKU, instead of
folding it into `skipped_no_3dp_sku`. A line with no 3D prefix at all stays `skipped_no_3dp_sku`.

Be aware this becomes a thin guard rather than a common path once 4.1 lands — that is intended. Its
value is that a future tightening or a typo like `ACC-3D-` with nothing after it can never again be
reported as "no 3D product in this order".

Reuse the existing sanitisation and closed-outcome-map machinery from `3D-P-014`; add the new outcome
to the map with a neutral description. Do not introduce a second detail-building path.

**4.3 Dashboard create-form validator — enforce the canonical grammar.**

`threeDpSkuTypeError` should accept the convention shape and reject the rest:

```
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$
```

Mnemonic length 2–5 is taken from the approved table, which contains both `OP` (2) and `CHARM`,
`JIGGL`, `DITTO`, `OPFRT` (5) — **verify that range against the convention doc's mnemonic list before
hardcoding it.** Keep the existing prefix→type coupling check unchanged, and update the error message
to state the expected shape with a concrete example.

This validator runs **only on create** (`if(!row){...}` at
`dashboard/booster-dashboard.html:863`) — verified. Editing or archiving an existing legacy SKU is
therefore unaffected. Confirm that still holds in the code you patch.

## 5. Do not touch

- `sync3dpSales_` control flow, matching, stock semantics, packaging calculation — only the SKU
  predicate and the one new outcome.
- The `3D-P-014` journal sheet schema, its column set, the row cap, the sanitiser, or the
  `sync_journal` read action.
- `updateSaleStatus()` / `updatePaymentStatus()` — still `3D-P-010` WP4, separate patch.
- Any existing SKU string in any sheet. **This task renames nothing.** If a legacy SKU does not match
  the convention, that is a separate data decision for the owner, not a patch.
- The 3D-P Apps Script project and workbook.
- `plans/3D-P_sku-naming-convention_20260807.md` — it is the canonical source; code aligns to it, not
  the reverse.
- The non-3D `ACC-0XX` accessory family.
- Standing protected zones: `sitemap.xml`, `robots.txt`, redirects, canonical, `.htaccess`, checkout,
  payment, fiscalization, Merchant feed, schema.

## 6. Likely files / areas

- `crm/apps-script/Code.gs` — `is3dpPackagingSku_` (line ~826), the trigger filter (line ~1057), and
  the outcome map added by `3D-P-014`. **Re-verify line numbers against the live script**; the mirror
  currently holds prepared-but-not-deployed `3D-P-014` work, per
  `crm/apps-script/SOURCE_STATE.md`.
- `dashboard/booster-dashboard.html` — `threeDpSkuTypeError` (line ~859).
- Tests: extend `crm/apps-script/tests/3dp-sync-journal.test.mjs` and
  `tests/3d-p-010-crm-packaging-pull.test.mjs`; extend the dashboard static test for the validator.

**Delivery.** Two deliverables, one work package, because this is one logical change:
(a) a paste block in `patches/` for the CRM script — owner pastes into the live main-CRM project and
publishes a new Web App version; (b) a direct edit of `dashboard/booster-dashboard.html`, which needs
no deployment. The PHP runner flow does not apply. Per `AGENTS.md` → "Apps Script mirrors", refresh
`crm/apps-script/Code.gs` and the state in `crm/apps-script/SOURCE_STATE.md` in the same session.

## 7. Acceptance criteria

1. `node --check` passes on the patched CRM source; dashboard static test passes.
2. Trigger table proven by test, not inspection:

   | SKU | expected |
   |---|---|
   | `ACC-3D-DITTO-410` | match |
   | `ACC-3D-PKM-130` | match |
   | `ACC-3D-410` | match (legacy preserved) |
   | `FIG-CHARM-001` | match (legacy preserved) |
   | `BR-CHARM-100` | match |
   | `ACC-001` | no match |
   | `MBX-STD-001` | no match |
   | `ACC-3D-` | no match, journalled `skipped_sku_shape` |

3. A sale line with `ACC-3D-DITTO-410` produces `created` in the journal, not `skipped_no_3dp_sku`.
4. A sale line with a 3D prefix but broken shape produces `skipped_sku_shape`; a line with no 3D
   prefix still produces `skipped_no_3dp_sku`.
5. Dashboard create form accepts `ACC-3D-DITTO-410` and rejects `ACC-3D-410` with a message naming
   the expected shape.
6. `node --test tests/3d-p-010-crm-packaging-pull.test.mjs` — all existing tests still pass.
7. The bounded diff touches only the SKU predicate, the outcome map, the validator, and tests.

## 8. QA / smoke test — owner

CRM risky zone (sync trigger inside order save). No payment, checkout, fiscalization or Nova Poshta
code is touched, so `bs-checkout-smoke` is not required. Not an SEO/schema change.

1. Create a named pre-deploy version of the CRM Apps Script project.
2. Paste the CRM block, publish a new Web App version.
3. In the dashboard: `3D-друк → Вироби → + Новий SKU`, register `ACC-3D-DITTO-410`, type
   `Функціональний аксесуар`, with the real product name. It must now be accepted.
4. Confirm it appears in the 3D-P SKU list.
5. **Only now run the `3D-P-014` QA cases** — `ACC-3D-DITTO-410` is then the first SKU present in the
   CRM catalogue, present in 3D-P `Номенклатура`, and recognised by the trigger.
6. Re-export the deployed `Code.gs` into the repo and update `SOURCE_STATE.md`.

## 9. Rollback note

Both changes are single-expression edits. Rollback = restore the previous regex in each place and, for
the CRM, publish a new Web App version; the dashboard needs only a file revert. No data is written or
migrated by this task, so there is nothing to undo in either spreadsheet. Keep the pre-deploy CRM
version in Apps Script history.

Note the asymmetric risk: rolling back 4.1 re-breaks `ACC-3D-` syncing silently, whereas rolling back
4.3 only restores a stricter form. If only one has to be reverted, revert the dashboard, not the
trigger.

## 10. Recommended status after execution

`In progress` until owner QA step 5 passes, because the point of this task is that `3D-P-014` QA can
run. Then `Done`. Notion status is written by Claude (chat); the executor may update the
`ROADMAP_FLOW` entry for `3D-P-022` within this authorised implementation.
