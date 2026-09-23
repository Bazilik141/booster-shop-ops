# Codex Report — CRM-015: marketing gift reclassification

Date: 2026-09-23

## Outcome and scope

The owner-reported live repair and dropdown checks passed. Correct the CRM-015 accounting route so standalone 3D
marketing writeoffs appear in `Маркетингові_плюшки`, not `Продажі`, with the
buyout purchase amount in the gift row and one manufactured-batch FIFO consume.
Prepare a guarded repair of the one existing allocation whose sales projection
the owner deleted. Make the sales-channel dropdown accept dashboard values
without invalidating historical channel labels. The owner performed the live
Sheet writes and Apps Script deployment; Codex did not write live Sheet data.

## Owner-reported live verification

- Payout sync: 28 formulas updated; second preview confirmed
  `already_applied=true`, zero formulas left, zero blockers.
- Historical reclassification: second preview confirmed allocation
  `3DP-A-marketing-CRM015-MUDW8DKS-EO12RHOR`, gift row 3, 25 UAH buyout,
  `already_applied=true`, zero blockers. This is the intended single FIFO
  allocation, not a second consume or a new CRM expense.
- Sales-channel sync: second preview confirmed `already_applied=true`,
  `blocker_count=0`, 995 rows checked. The owner confirmed the red dropdown
  warning is gone.
- The owner confirmed CRM integrity OK after these changes. The 3D deployment
  version/bytes and a completed payout period were not supplied.
- The local temporary route and `CRM-015.html` have been removed after QA.
  The bound project still needs owner-side deletion and clean republication.

The 2026-09-22 CRM-015 report's statement that marketing writeoffs belong in
`Продажі` is superseded by the owner's 2026-09-23 correction.

## Grounded live evidence (owner-provided)

- CRM Marketing expense of 25 UAH remains in `Витрати` for
  `ACC-3D-PKM-110 × 1`, dated 2026-09-23; it must not be created again.
- The owner deleted the entire mistaken 3D `Продажі` row. `A2:D8` then showed
  only the three legitimate sales in rows 2–4.
- `3dp_fifo_reconcile` reported exactly one issue:
  `FIFO_PROJECTION_QUANTITY_MISMATCH` for
  `3DP-A-marketing-CRM015-MUDW8DKS-EO12RHOR`, expected 1, actual 0.
- Live `Маркетингові_плюшки!A1:F20` had one incomplete row 2 (2026-09-21,
  `F2=1`, blank SKU) and otherwise blank data; no `E` formulas appeared in
  rows 2–20. The candidate deliberately leaves row 2 untouched.
- Live `Виплати!A2:A20` had no periods. `B2:B20` formulas sum only
  `Продажі!K` by `Продажі!S`; they do not include gift purchase sums.
- The owner-provided 2026-09-23 CSV export has exactly one gift data row:
  2026-09-21, `F=1`, blank SKU/purchase price/purchase sum. The HTML workbook
  archive confirms A:H gift headers and blank payout period rows. Thus there
  are no older *recorded purchase sums* in this supplied snapshot to pay now;
  the incomplete gift cannot be assigned a fabricated buyout.
- The sales-channel screenshot showed legacy validation choices `Сайт`,
  `Директ`, `Instagram`, `Telegram`, `OLX` while an existing sale contains
  `OpenCart`; the dashboard writes `OpenCart`, `Telegram`, `OLX`, `Monobazar`,
  `Вручну`, `Інше`.

## Candidate implementation

- New writeoff: `CatalogFifo.gs` writes `Маркетингові_плюшки!A/B/C/D/E/F/H/I`.
  `E` is a row formula `C×D`, explicitly seeded because the live exemplar has
  none. `F` decreases SKU stock through the existing availability formula.
  `I` freezes Serhiy-paid fixture accrual separately from the buyout; the
  marketing date is written as a real Sheet date for period aggregation.
  FIFO batch counters are consumed once; no `Продажі` row is created. An
  existing dirty FIFO reconciliation blocks further new marketing writeoffs.
- Payout: an owner-only temporary `payout_sync` preview/apply in `CRM-015.html`
  checks existing B formulas, adds gift column I only if free, and updates
  `Виплати!B` to sum Sales K/S plus gift purchase E and Serhiy fixture I by
  month. It refuses unexpected formulas or manual B values, fingerprints the
  state, and rolls back on error. Future `3dp_payout_create` writes this formula
  explicitly. Run payout sync before reclassifying the 25-Uah gift; then the
  owner may create the `2026-09` period through the existing payout API.
- Retry: a committed `marketing_gift` must match the gift row's SKU, quantity
  and buyout. A removed legacy `marketing_writeoff` sales row is reported as
  `LEGACY_MARKETING_PROJECTION_MISSING`, never silently replayed with buyout 0.
- Historical correction: task-only `CRM-015.html` is evaluated server-side by
  a thin temporary API route. It previews the exact allocation, requires the
  expected FIFO mismatch and an empty former sales row, fingerprints the live
  state, then writes one gift row and changes only the allocation's source type
  and projection-row pointer. It does not change batch counters, sales rows or
  the CRM expense. The action verifies FIFO reconciliation and attempts
  rollback on error. Delete the HTML and temporary route after owner-verified
  execution, then republish the clean 3D API.
- Sales-channel validation: a permanent owner-only preview/apply action reads
  current `Продажі!M` validation and values, blocks unknowns, and accepts the
  six dashboard labels plus the three legacy-only labels. Apply requires the
  preview fingerprint and restores old validation if verification fails.
- The dashboard success message now points to marketing gifts, not sales.

## Verification

- Syntax parse before live repair: `Code.gs`, `CatalogFifo.gs`, main CRM `Code.gs`, deployment
  paste source, and the `CRM-015.html` server scriptlet — passed.
- Before cleanup, the temporary-code test matrix passed 14/14, including
  stale-fingerprint rejection, one-time reclassification and payout sync.
- After cleanup, direct Node runs passed 12/12 CRM-015 tests and 9/9 FIFO
  tests. They cover the permanent gift-ledger route, payout formula, channel
  validation, source parity, and absence of the temporary action and HTML.
- `node --test` could not spawn its test process in this Windows sandbox
  (`spawn EPERM`); running each test file directly executed the same cases.
- Syntax parse after cleanup passed for 3D `Code.gs`, `CatalogFifo.gs`, main
  CRM `Code.gs`, and the owner paste source.
- `git diff --check` — passed.
- See the owner-reported live verification above; it does not prove byte-level
  identity of the cleaned, not-yet-published source.

## Open gates and risks

- The cleaned `work/CRM-015_3dp_Code_final.gs` excludes the one-time
  reclassification route. It is a local publication candidate, not the
  currently deployed source. The owner must replace the bound `Code.gs`,
  delete bound `CRM-015.html`, and republish the existing 3D Web App.
  Do not paste the broader repository mirror: it contains unrelated
  batch-draft changes.
- The main CRM response-key correction (`gift_row_3dp`) and dashboard
  success-message correction are local only. They do not change the confirmed
  25-Uah accounting entry; their publication is a separate owner gate.
- No payout period was created in the supplied evidence. Existing and newly
  entered marketing gift purchase sums flow into `Виплати!B` by gift date.
  Before marking a period paid, complete the historical inventory/backfill and
  reconcile the amount. The incomplete gift row 2 is untouched and requires
  separate factual review.
- No byte-verified export of the corrected live 3D deployment was supplied.
  The isolated CRM-015 branch is separate from a heavily dirty `master`
  checkout and its unrelated unpushed commit.

## Rollback and next owner gate

Preserve the current working 3D API deployment as a rollback version. To
remove the completed one-time maintenance endpoint, copy only
`work/CRM-015_3dp_Code_final.gs` into the bound 3D `Code.gs`, delete bound
`CRM-015.html`, publish a new version of the **existing** Web App deployment,
and retain its `/exec` URL. Then verify normal 3D reads and the channel
validation preview. Do not run the historical reclassification again and do
not create a second CRM expense.
