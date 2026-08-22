# Codex Report — 3D-P-007 WP1b: Serhiy write rights + payout acknowledgement

Date: 2026-08-22

## Outcome

WP1b is ready locally as a paste-ready replacement for the bound 3D-P Apps
Script. It grants Serhiy the approved `Номенклатура!Q:R:S` writes, role-stamped
actual-count stock corrections, and two visible payout acknowledgements. It
does not change the CRM script, `Продажі`, the WP1 identity boundary, dashboard,
tokens, a live Sheet, publication, Git history, or Notion.

The delivered patch is byte-identical to the updated local `Code.gs`.

SHA-256: `1D4CF5992C0C3FD9F59D3F446AE4254EAEB21A0C233C112C84CEF168C425B5D8`

## Source and scope evidence

- Local baseline: 3D-P V23 source mirror. `SOURCE_STATE.md` records the
  normalized owner V23 export received on 2026-08-16; this is source evidence,
  not a new Web App publication proof.
- The V23 mirror has `Виплати!C1 = Термін перевірки Сергієм`. It is a review
  deadline, not an acknowledgement: it contains neither a role nor a received/
  agreed event timestamp. WP1b therefore adds two distinct columns rather than
  repurposing that deadline.
- A live Sheet header read was not performed: this local task has no authorized
  deployed 3D-P API credential. Before any Sheet write, the owner runs the
  included read-only preflight, which fails closed unless `Виплати!A1:F1` and
  `_Коригування_наявності!A1:D1` match the verified baseline.

## Implemented behaviour

### Nomenclature Q/R/S

- Serhiy may write `Q` (`РРЦ фактична, грн`), `R` (`Ціна під викуп, грн`), and
  `S` (`Посилання на модель`).
- `Q` and `R` accept finite non-negative numbers from `0` to `100000`; `S`
  accepts only a trimmed `http(s)://` URL up to 2048 characters.
- Every accepted direct or appended Q/R/S write by either role adds one row to
  the existing hidden `_Журнал_налаштувань_3DP`: Kyiv timestamp, role, field
  header, old value, new value, and SKU. Rejected writes append no row.
- The shared journal receives only a new `SKU` column; historical settings rows
  retain an empty SKU. There is no third competing journal shape.
- All current Serhiy-writable `Номенклатура` columns, including Q/R/S and the
  pre-existing L/M/N writes, now sit in the projection `baseline`; they remain
  readable if `SERHIY_FULL_ECONOMICS_VISIBLE_3DP` is later turned off.

### Stock correction

- `3dp_adjust_stock` accepts `owner` and `serhiy`; its actual-count,
  stale-write, delta-ledger, and non-negative-stock semantics are unchanged.
- `3dp_stock_adjustments` is role-open and projected for Serhiy after the
  schema setup. The hidden ledger gains `Роль` in column E; every new row stores
  the authenticated role.
- Existing API-authored ledger rows are backfilled as `owner`: the prior code
  guarded every stock correction with `assertOwner3dp_`. No existing SKU,
  delta, reason, or timestamp is changed.

### Payout acknowledgement

- `Виплати!G:H` are added as `Згода Сергія із сумою (Київ, роль)` and
  `Кошти надійшли Сергію (Київ, роль)`. Blank means not acknowledged; an entry
  is a Kyiv timestamp plus `serhiy`, never a bare boolean.
- Both headers are in the Serhiy payout projection `baseline`.
- New API actions: `3dp_payout_acknowledge` and the explicit
  `3dp_payout_acknowledgement_correct`. Only Serhiy may call them. A normal
  repeat fails with `ACKNOWLEDGEMENT_ALREADY_SET`; a correction requires the
  previously read value and a reason.
- Corrections preserve the prior value in the hidden append-only
  `_Журнал_підтверджень_виплат_3DP` (Kyiv time, role, period, kind, old/new,
  reason). Money receipt can only be acknowledged after the owner marks the
  period `Виплачено`; both acknowledgements reject un-published periods.
- `3dp_payout_create` and `3dp_payout_mark_paid` retain their owner-only guards.

## Files changed

```text
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
patches/3D-P-007-WP1b_serhiy-write-rights_20260822.js
diagnostics/3D-P-007-WP1b_serhiy-write-rights_report_20260822.md
```

## Local verification

```text
node -e "new Function(Code.gs)"
Code.gs syntax ok

node 3d-print/apps-script-3dp-api/tests/api.test.mjs
{"ok":true,"active_cleanup_route":true,"archived_setup_routes_removed":5,"preview3dpApiSetup_retained":true}

node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs "Версія 23, 13 серп. 2026 р., 2017.txt"
{"ok":true,"owner_paths_preserved":11,"v23_owner_responses_compared":13,"serhiy_projection_checks":54,"settings_journal_checks":12,"wp1b_write_checks":23,"payout_acknowledgement_checks":17,"full_economics_checks":10}

git diff --check
no output
```

The V23 comparison runs before the requested schema extension and proves all
13 existing owner-read responses byte-identical. After setup, it re-compares all
unaffected owner paths; the intentional payout/stock schema additions are the
only excluded response extensions.

## Owner deployment gate

No production action was taken. Paste the patch into the **bound 3D-P Apps
Script project only**, save it, then run the following functions from the Apps
Script editor in this order:

```javascript
preview3dpWp1bSchema()
// Review planned_changes. If all anchors are expected:
setup3dpWp1bSchema()
```

`setup3dpWp1bSchema()` is idempotent. It adds only `Виплати!G:H`,
`_Коригування_наявності!E`, and (if the existing settings journal is present)
`_Журнал_налаштувань_3DP!F`; it also hides the stock ledger again. Only after
that succeeds should the owner create and publish a new 3D-P Web App version.
It validates all three target schemas before its first write, so a drifted
later target cannot leave an earlier migration partially applied.

## Post-deploy QA

- [ ] Run `preview3dpWp1bSchema()`; confirm only the three scoped header changes.
- [ ] Run `setup3dpWp1bSchema()` twice; the second result is `already_applied: true`.
- [ ] As owner, change Q/R/S and verify exactly one shared-journal row per
      accepted change; reject a negative price and a non-HTTP(S) model link.
- [ ] As Serhiy in WP3 joint QA, set each acknowledgement, retry the first one,
      then correct it and inspect the hidden payout-acknowledgement journal.
- [ ] As Serhiy, submit an actual count; verify `Наявність` equals the number
      entered and ledger `Роль = serhiy`.
- [ ] As Serhiy, confirm payout/stock read responses include the two
      acknowledgements and ledger role; as owner, confirm payout create/paid
      actions still work.
- [ ] Run `integrity_check`; expect `clean=true`, `problems=[]`.

## Rollback

Republish the already owner-QA'd WP1 rev 2 Apps Script version, then hard
refresh. Do not delete the three new columns or either journal sheet: after a
code rollback they remain inert audit data. No live mutation occurred in this
task.

## Remaining risks

- There is no staging environment. Publication and live Sheet writes remain the
  owner's production gate.
- The code cannot prove the deployed Sheet header state locally; the provided
  preflight rejects a drifted schema before its migration writes.
- Dashboard controls for these API actions are explicitly out of scope (WP2 /
  WP2b). The API and Sheet schema are ready for that separate work.
