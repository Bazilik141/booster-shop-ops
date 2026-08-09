# CRM-006 pass 1 — restore the РРЦ spill

Date: 2026-08-09 · Task: `CRM-006` · Executor: **Codex** · Author: Claude (chat)
Basis: `diagnostics/CRM-006_bounded-live-diagnosis_report_20260809.md` (accepted in review).

## 1. Authorisation

**Approved by the owner:** clearing the contents of **`РРЦ!A76:D76`** — those four cells only — and
the separate second micro-pass setting **`РРЦ!E75`** from `90` to `100`.

**Not approved, do not touch in this pass:**

- `Майстер_Товарів!P2` and its VLOOKUP index. One cell, but it flips `Активний` for ~72 rows at once.
  It needs a consumer inventory first — see §7.
- Any `Товари` or `Розхідники` formula restoration.
- Any fill-down, anywhere, for any reason.
- `РРЦ` rows `71:75` `A:D`. They are blank, they are a symptom, and after the spill rebuilds they
  must be populated **by the ARRAYFORMULA**, never by hand.

## 2. Who performs the write

**The owner does.** It is four cells and one Delete key. Routing a production Sheets mutation through
an agent to save the owner five seconds adds authority ambiguity for no benefit. Codex prepares,
verifies and records; the owner executes.

If the owner prefers Codex to execute the clear, he must say so explicitly in the active task. Absent
that sentence, Codex performs **reads only** in this pass.

## 3. Before any write — the rollback material does not exist yet

The diagnosis names the four literals as `PKM-EN-PBLK-BLR-SLP`, "its short name", `Pokémon` and
`Blister`. **"Its short name" is not a recorded value.** A rollback plan that references a string
nobody wrote down is not a rollback plan.

Also note the mapping is not column-aligned: `РРЦ!A76:D76` derives from `Товари!A76`, `B76`, `D76`,
`G76` — `D` comes from `G`. Restoring by copying `Товари!A76:D76` would be wrong.

Required first:

1. Bounded read of `РРЦ!A76:D76`, and record **all four values verbatim**, character for character,
   in the diagnostic. Quote them.
2. Owner creates a **named Google Sheets version**.
3. Owner saves the raw pre-write `integrity_check` JSON (not a screenshot). This is the "before" half
   of the `OPS-CRMINTEGRITY` pair.

## 4. Pass 1 — the clear

Owner action: select `РРЦ!A76:D76` → **Delete / Clear contents**.

**Not** "Delete cells and shift left". That would drag `E:H` leftwards and corrupt the row. One menu
item apart, entirely different outcome. State this in the owner-facing instruction verbatim.

`E76:H76` and every other row stay untouched.

## 5. Verification, in this order

1. Bounded read `РРЦ!A3:D3` — the `#REF!` must be gone and the seeds must show a spilled result.
2. Bounded read `РРЦ!A71:H76` — confirm:
   - rows `71:75` now carry SKU keys in `A:D`;
   - **the spilled SKU on each of rows 71–75 matches the SKU named in that row's existing note**, and
     `A75` is specifically `ACC-3D-DITTO-410`;
   - row `76` has keys again (if the spill stops at 75, row 76 now has a price and no key, which is a
     new `price_without_sku` created by this pass — that would be a defect of the change).
3. Run `integrity_check`, save the raw JSON as the "after" half, and record the before/after counts
   side by side in the diagnostic.

**Gate — do not proceed to pass 2 unless this holds:** `rrp_mismatch_3dp` now fires for
`ACC-3D-DITTO-410` reporting CRM `90` against 3D-P `100`.

That check is not a formality. It is the proof that row 75's manual price is keyed to the right
product. If it does not appear, the manual prices in `71:75` may have landed against the wrong SKUs —
**stop, change nothing further, and report.**

## 6. Pass 2 — the price correction, separately

Only after the gate in §5 passes.

Set `РРЦ!E75` from `90` to `100`. Owner-confirmed 2026-08-09: `100` is correct for
`ACC-3D-DITTO-410`; the 3D-P workbook is right and the CRM sheet is the one that was wrong.

Re-read `E75`, run `integrity_check` again, confirm `rrp_mismatch_3dp` clears for that SKU, and
record the third bounded output.

Two passes, two before/after pairs, two entries in the diagnostic. Do not merge them.

## 7. Recorded for the next stage, not for this one

`Майстер_Товарів!P2` uses `VLOOKUP(A2:A;Source_CRM_Products!A7:O;13;FALSE)` while `Активний товар`
is column **12**; column 13 is `Посилання на товар`. Independently corroborated against the `Товари`
header order recorded in `crm/apps-script/tests/integrity-check.test.mjs`.

Before that fix is specced, one question must be answered from live evidence:

> If `Майстер_Товарів.Активний` has been blank or wrong for a long time, why has the Облік dropdown
> been usable? Either it reads a different column, or the defect is recent.

Until that is answered, correcting the index is a change whose blast radius is unknown. Apply the
same discipline used in `3D-P-019` phase A: list every consumer of the column before touching it.

## 8. Housekeeping

`diagnostics/CRM-005_integrity-check-and-rule_report_20260809.md` still states that the manual cells
in rows 71–75 block the spill. That is now known wrong. Append a dated correction to that report
pointing at the CRM-006 diagnosis — do not rewrite the original sentence, mark it superseded.

The equivalent annotations in `diagnostics/CRM-005_first-live-baseline_20260809.md` and in the
`CRM-006` Notion page are already done.

## 9. Acceptance criteria

- [ ] The four original `РРЦ!A76:D76` values are recorded verbatim before any write.
- [ ] A named Google Sheets version exists, created before the write.
- [ ] Only `A76:D76` changed. `E76:H76` and all other rows are provably untouched.
- [ ] `A3:D3` no longer return `#REF!`.
- [ ] Rows `71:76` carry SKU keys, and each of `71:75` matches the SKU named in its own note.
- [ ] Before/after `integrity_check` raw JSON recorded for each pass.
- [ ] `price_without_sku` and `active_sku_without_rrp` counts drop sharply — this is the test of the
      cascade reading. If they do not, the reading was wrong and the remaining problems need a fresh
      diagnosis rather than more repairs.
- [ ] `rrp_mismatch_3dp` fires for `ACC-3D-DITTO-410` after pass 1 and clears after pass 2.
- [ ] No formula column anywhere is left holding a literal as a result of this work.

## 10. Rollback

Restore the four recorded literals into `РРЦ!A76:D76`, or restore the named Sheets version.

Needed only if the spill does not rebuild as expected, or if the spilled keys on rows `71:75` do not
match their notes. Both are read-detectable before anything else is touched, which is why §5 runs
before §6.
