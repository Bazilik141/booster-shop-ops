
---

# Addendum — 2026-09-11, after the final report

## Closing verdict on CRM-012

The returned defect is fixed: `recognition_fallback_orders` and
`identity_conflict_orders` are now rendered in the finance quality line with
readable labels («Передзамовлень із розрахунковою датою визнання»,
«Замовлень із конфліктом ідентифікації»). No new duplicate declarations; the
temporary repair files are gone from the repository.

The `LOT-0181` runtime evidence reconciles, and not merely arithmetically:
−30,763.40 + 27,078.40 = −3,685.00, and −3,685 is exactly the balance the
supplied ZenMarket history ended at (`ZEN-HIST-011`). The CRM balance now equals
the real ZenMarket balance. `historical_topup_uah_missing: 0` on live data is
the proof that the WP1 counter is genuinely dynamic — the previous hardcoded
literal could only ever have returned 1.

## Blocking the mirror record — the V173 export is stale

The owner-supplied export `Версія 173, 11 вер. 2026 р., 1745` is
**byte-identical to the V171 export** (same SHA-256, same 10,461 lines,
621,598 chars). It contains none of the CRM-012 code: no `Дата отримання`
stamping, no supplier field (`readFormRange_(formSheet, 'A4:B9')` rather than
`'A4:B10'`, no `Постачальник`), no `crm012RepairLot0181AfterOwnerApproval`.

This is almost certainly a stale export rather than a stale deployment: the
CRM-012 setup functions **cannot run at all** from V171 source, yet
`setupCrm012RevenueRecognition()` created `Продажі!Дата отримання` as column 35
and the `LOT-0181` repair executed with real balance movement. The published
project therefore does contain the code; the exported file does not.

**Do not record V173 in `SOURCE_STATE.md`.** The mirror gate stays open. It
closes only when an export that actually contains the CRM-012 functions is
compared against the repository. Until then `SOURCE_STATE.md` correctly still
says V171, and that is honest rather than stale.

A quick self-check before sending the next export: it must contain the string
`setupCrm012RevenueRecognition`. If it does not, it is not the current head.

## WP5 — why `placeholders: 0` does not contradict the screenshots

Both observations are true and they do not meet.

The dashboard Products tab renders `Майстер_Товарів!Назва` (via `apiSkuList_`,
which falls back through `Назва товару` and `Повна назва на сайті`). The
divergence report reads `Товари!Коротка назва` as its product name and uses
`Майстер_Товарів!Назва` only as a lookup.

Critically, the report builds its SKU set from
`Object.keys(productBySku).concat(Object.keys(remoteBySku))` — that is, from
`Товари` rows passing `is3dpCatalogSku_`, plus the 3D-P catalogue.
`Майстер_Товарів` never contributes a SKU. So a row that is a placeholder in
`Майстер_Товарів` but absent from (or unrecognised in) `Товари` is invisible to
the report by construction.

The placeholder test itself is sound and anchored:
`/^(?:3d[- ]?друк|3d print|друк)$/i`, which matches `3D-друк` exactly.

**One number decides which cause applies.** The report returns `total` alongside
`placeholders`. If `total` is 0 or excludes the `BR-*` rows, the SKU selection is
the cause — `is3dpCatalogSku_` requires either a canonical packaging SKU or a
`Сет / група` / `Формат` value containing "3D", and those rows evidently do not
qualify in `Товари`. If `total` includes them and `placeholders` is still 0, then
the stored value is not exactly `3D-друк` — trailing whitespace, a different
dash, or the name living in a fallback column.

WP5 is otherwise correct: nothing was renamed, nothing invented, no write path
enabled. But it cannot be called complete while its zero result is unexplained
against a directly observed symptom. Re-run it and read `total`.

## Optimisation — not started, and the accumulation is measurable

No part of the C1–C4 performance proposal has been implemented. The ZenMarket
report stated that explicitly for its own round, and nothing since has changed
it. C1 — measure before reordering anything — remains the prerequisite.

The owner's instinct about one-shot accumulation is correct in direction and
smaller in magnitude than it feels. Measured against V171:

```
Code.gs            10,647 lines  (V171: 10,461)   +186 lines, +3.0%
total functions       596
…ForOwner one-shots     6
setup…()               13
repair/backfill        11
```

Thirty of 596 functions — about 5% — run once or rarely. CRM-012 itself added
only 3% of file size, so this round is not the problem; the pattern across
rounds is what deserves a decision. Roughly nine of those thirty are provably
spent: dated repairs (`repairCrmStockCounting20260901`,
`previewCrm011OcFop0324Repair`, `repairCrm011OcFop0324`,
`crm012RepairLot0181AfterOwnerApproval`, `crm011BackfillPaymentDates_`) and the
structural setups that have already executed (`setupCrm011FinanceColumns`,
`setupCrm011ZenMarketAccount`, `setupCrm012RevenueRecognition`,
`setupCrm012PurchaseSupplierForm`).

Deleting them is safe only against evidence, never against a name or a date —
that is exactly C4, and it stays last. The correct order remains C1 (measure),
C3 (remove duplicated work, starting with `loadUpdates()` building the whole
Accounting page and setting `loaded.accounting = true`), then C4, with C2 (idle
prefetch) deliberately skipped until measurement justifies it.
