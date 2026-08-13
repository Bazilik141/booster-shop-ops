# Codex Report — 3D-P-019: Phase A fixture category and payer setup

Date: 2026-08-11

## Scope

Implemented the accepted Phase-A setup action only: append `Розхідники!O` as `Платник`, rename the
two verified fixture rows from `3D-друк` to `Фурнітура`, and backfill them as `власник`.

After review, a local follow-up safeguard was added but is **not yet live**: strict O-column dropdown
validation (`власник` / `Сергій`) and an `onEdit` owner default with a visible toast when a manual
fixture row leaves O blank. Its owner deployment is the next bounded action.

The multi-line order/write-off forms, Serhiy pending purchases, fixture consumption, and `Продажі!V/W`
wiring remain Phase B and are untouched. The setup action is deliberately not exposed through the
Web App API or any normal sale/write-off path.

## Files touched

```text
crm/apps-script/Code.gs                         — owner-run setup3dp019FixturePayerPhaseA()
crm/apps-script/tests/3d-p-019-phase-a.test.mjs — local behaviour and safe-fail regression test
diagnostics/3D-P-019_fixture-phase-a-setup_report_20260811.md
```

## Safety contract

Before any write, the action requires:

- the verified `Розхідники!A3:N3` header sequence, including the N dropdown header;
- no used data after O, so appending does not shift an unknown column;
- exactly two rows whose category is `3D-друк` or `Фурнітура`;
- those rows to be exactly `FUR-BR-COLOR-MIX` and `FUR-BR-CARB`, each once;
- a blank or `власник` payer only. An existing `Сергій` or another value stops the action.

Only after every check passes does it append O (when absent), rename categories, and fill blank
payers. The current local revision also applies strict two-value validation to O4:O80 and defaults a
manually added fixture with a blank payer to `власник`, with an on-sheet toast. It never changes the
`Упаковка`/`Маркетинг` rows or any existing A:N column.

## Local validation

```text
node --input-type=module -e "import { readFileSync } from 'node:fs'; new Function(readFileSync('crm/apps-script/Code.gs', 'utf8')); console.log('Code.gs syntax parse passed');"
node crm/apps-script/tests/3d-p-019-phase-a.test.mjs
node crm/apps-script/tests/integrity-check.test.mjs
```

Results: `Code.gs syntax parse passed`; `3D-P-019 Phase A setup tests passed`; and `CRM
integrity-check tests passed`. Node 24 rejects `node --check` for the `.gs` extension, so the first
command parses the same file content directly instead.

The Phase-A test covers a first run, repeat run (`already_applied`), strict validation, a manual-row
owner default/toast, wrong legacy header, an unexpected third fixture-category row, an already-applied
sheet, and an unexpected existing payer. It exercises the actual function extracted from `Code.gs` in
an Apps Script-shaped mock.

## Idempotency

On a fully migrated sheet the action returns:

```json
{"ok":true,"action":"3dp019_phase_a_fixture_payer_setup","header_added":false,"payer_validation_enforced":false,"category_rows_renamed":0,"payer_rows_backfilled":0,"already_applied":true}
```

It does not insert another column or rewrite the two rows.

## Live execution evidence

The owner created **CRM Apps Script V102** at **2026-08-11 09:55 Kyiv**, pasted the reviewed local
`Code.gs`, and ran `setup3dp019FixturePayerPhaseA()` in the bound CRM project.

```json
{"ok":true,"action":"3dp019_phase_a_fixture_payer_setup","header_added":true,"category_rows_renamed":2,"payer_rows_backfilled":2,"already_applied":false}
```

The pre-change integrity result was `ok`; immediately after the action, the bounded live
`integrity_check` returned `clean=true`, `problems=[]`, `rrp_mismatch_3dp.compared=1`, and
`elapsed_ms=5938`. This is production proof for the category/payer migration, not proof for Phase B.

**Important:** V102 predates the local strict-validation/default safeguard. A new owner deployment and
one repeat run are required before the O-column review findings can be marked resolved. That repeat
must report `header_added=false`, `category_rows_renamed=0`, `payer_rows_backfilled=0`, and
`payer_validation_enforced=true` on its first run from the revised source.

## Rollback

Rollback is precise: set `Розхідники!B` back to `3D-друк` only on the two named rows, clear their
O-cell values, then remove O only if no later data has been added to it. Do not delete O if Phase B
or any other work has started using it.

## Post-execution QA checklist

- [x] The pre-change `integrity_check` bounded output was `ok`.
- [x] The action appended one O-column, with no A:N column insertion (`header_added=true`).
- [x] Only the two preflighted fixture rows were renamed and backfilled (`2` / `2`).
- [x] The post-change `integrity_check` was clean with no problems.
- [x] CRM Apps Script V102 records the live action and the local mirror as its source.
- [ ] Owner visual check: O3 is `Платник`, N remains the dropdown formula seed, and the two named
      rows visibly show `Фурнітура` / `власник`.
- [ ] Deploy the revised source, repeat `setup3dp019FixturePayerPhaseA()`, and verify O4:O80 has only
      the strict `власник` / `Сергій` dropdown.
- [ ] Add one temporary fixture row with O blank; verify the owner default/toast, then remove the
      temporary row without touching the two live fixture rows.

## Residual risk

This is a live CRM sheet-structure and financial-data change. The execution log and clean
post-change integrity response prove the V102 category/payer migration only. The validation/default
safeguard is local until deployed, and fixture consumption, order/write-off forms, payer-aware sale
wiring, and Serhiy pending-purchase flow remain Phase B.
