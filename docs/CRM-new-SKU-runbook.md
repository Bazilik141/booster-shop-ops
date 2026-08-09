# Main CRM — new SKU runbook

Use this procedure for every new SKU. It protects the three linked catalogues without reading a
workbook into an agent context.

## Before the change

1. In the dashboard, run **Перевірити CRM** and save the bounded result in the task diagnostic.
2. If the result already has problems, record them as baseline. Do not silently repair unrelated
   rows during the SKU task.
3. Do not edit formula columns: `Товари` short name/current price, `РРЦ` A:D formula seeds,
   `Розхідники` derived stock/cost fields, or `Майстер_Товарів` formula outputs.

## Main CRM catalogue

1. Add the SKU once in `Товари`, using only manual-input columns and the existing validations.
2. Set `Активний товар` deliberately. An active SKU is expected to appear in the downstream master
   catalogue and to have a current RRP.
3. Wait for `РРЦ` A:D to expand the SKU and product name automatically. Only then enter the price,
   update date, and note in that same SKU-keyed row.
4. Never enter a price/date/note in a blank `РРЦ` row. It blocks the array formulas and hides every
   later SKU, which is the defect the integrity check is designed to catch.

## Automation master catalogue

1. Wait for the source refresh that feeds `Майстер_Товарів`; do not create a parallel manual row.
2. Confirm the exact SKU appears once, its `Активний` value matches `Товари`, and its CRM price is
   populated from the CRM source.
3. If it does not appear, stop and investigate the source refresh rather than typing over the
   master-sheet formulas.

## 3D SKU only

1. Add the product to 3D-P `Номенклатура` through the approved 3D-P path.
2. Confirm its actual RRP matches the CRM `РРЦ` value after the CRM row is keyed by SKU.
3. Do not infer that a source edit is deployed. Follow the 3D-P mirror and owner deployment rules.

## After the change

1. Run **Перевірити CRM** again.
2. A new problem code, a new row range, or a changed count is a defect in this SKU change. Stop and
   repair only with a bounded follow-up scope.
3. Put the before/after results, the exact sheets and rows touched, and any remaining baseline
   problems in the diagnostic. Owner QA and deployment evidence remain separate from this local runbook.
