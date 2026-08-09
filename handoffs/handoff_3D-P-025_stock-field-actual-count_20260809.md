# Handoff — 3D-P-025: stock correction takes the actual count, not a delta

Date: 2026-08-09
Executor: **Codex** · model=Terra · effort=medium
Justification: small, well-bounded change against files already identified here. No discovery needed.
Owner decides.

---

## 1. Task ID

`3D-P-025` · Notion `3b76bf20-bdb4-81a6-838b-d6a27eff68bc`

## 2. Context

The `КОРИГУВАННЯ НАЯВНОСТІ` panel in the 3D-друк tab shows `Поточна наявність (Наявність!G)` and then
asks for `Delta (ціле число)`. The owner reads the current figure, thinks "there are actually N on the
shelf", and types N — which is applied as an increment.

Confirmed live on 2026-08-09: the owner entered `99` meaning "99 in stock" and `ACC-3D-DITTO-410`
became `196`. The ledger row reads `99 · тестові коригування`. Nothing malfunctioned — the field
asked for one thing and the owner supplied another.

This is an input-semantics defect. It will keep happening because the panel displays a *count*
directly above a field that wants a *change*.

## 3. Goal

The owner types the real current stock. The system computes the difference itself and records that
difference in the ledger.

## 4. What to change

**Dashboard — `dashboard/booster-dashboard.html`, `КОРИГУВАННЯ НАЯВНОСТІ` panel.**

- Replace the `Delta (ціле число)` input with `Фактична наявність зараз, шт`.
- Below the field, show the derived change live, e.g. `Буде записано: −3 шт` or `Буде записано: +12 шт`,
  recomputed as the owner types.
- If the entered count equals the current stock, the derived change is `0` — **disable the submit
  button** and say `Змін немає`. Do not write a zero row into the ledger.
- Keep the existing reason field and its requirement.
- Keep the existing explanatory line that the record is appended to an append-only ledger and that
  `Наявність!G` is not overwritten — it is accurate and still needed.

**API — `3d-print/apps-script-3dp-api/Code.gs`, `3dp_adjust_stock`.**

The action currently accepts a delta. Two acceptable shapes; pick one and say which in the report:

- **Preferred:** keep the API contract as a delta and compute the delta in the dashboard before
  sending. Smallest blast radius — the deployed write path does not change at all.
- **Alternative:** accept an `actual_count` parameter alongside the existing delta parameter, compute
  server-side, and reject a request that supplies both. Only choose this if the dashboard cannot
  reliably know the current stock at submit time.

If the preferred option is taken, the dashboard must re-read the current stock immediately before
computing, not rely on a value rendered minutes ago. A stale base would silently produce a wrong
delta — which is the same class of bug this task exists to remove.

## 5. Do not touch

- The append-only adjustment ledger `_Коригування_наявності` and its columns.
- The `Наявність!G` formula. Stock stays derived, never overwritten by a direct cell write.
- Existing ledger rows. The `+99` test row from 2026-08-09 stays; the owner will correct it through
  the same panel once this ships.
- The fail-open rule: an adjustment that drives stock negative still applies, with a warning.

## 6. Acceptance criteria

- [ ] The field is labelled as the actual current count, not a delta.
- [ ] The derived change is shown before submission and matches what lands in the ledger.
- [ ] Entering the same number as current stock disables submission; no zero row is ever written.
- [ ] Entering a smaller number writes a negative row; a larger number writes a positive row.
- [ ] The base value used for the calculation is read fresh at submit time; prove it with a test
      where the stock changes between render and submit.
- [ ] `Наявність!G` remains a formula after the operation.
- [ ] Existing ledger rows are untouched.

## 7. QA / smoke test

Owner, after deployment, on `ACC-3D-DITTO-410` (currently `196`, inflated by the `+99` test row):

1. Enter `97` with reason `виправлення тестового коригування`. Confirm the panel previews `−99`
   before submission, the ledger records `−99`, and stock reads `97`.
2. Enter `97` again — submission must be blocked with `Змін немає`.
3. Enter `100` — ledger records `+3`, stock reads `100`.

## 8. Rollback note

Dashboard-only if the preferred option is taken, so rollback is a `git checkout` of one file plus a
hard refresh. Nothing is written to the workbook by this change itself. No named Sheets version is
required.

## 9. Recommended status after execution

`In progress` until owner QA above passes. Claude (chat) is the Notion writer; the executor writes
no Notion property.

## Risk classification

Low. Not an SEO or checkout zone. It does touch stock accounting, so the "no zero rows" and
"fresh base value" criteria are not optional — a wrong delta here silently corrupts inventory.
