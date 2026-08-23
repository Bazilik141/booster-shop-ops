# Codex Report — 3D-P-007-WP1c: analytics provisioning hotfix

Date: 2026-08-23

## Scope

Fix the observed post-WP1c failure for `BR-BULB-100`:

`Serhiy profit share is not configured in Analytics for SKU BR-BULB-100.`

The defect was in the owner-only canonical-SKU assignment path. It changed a
draft row to `Активний`, but did not provision the matching derived
`Аналітика` row. The next `3dp_get_row` then correctly required the share and
failed. The test inputs `РРЦ=2` and buyout price `1` do not cause this error.

This hotfix changes only the 3D-P Apps Script. It does not change CRM, the
`Продажі` column set, the 50/50 rule, Serhiy's identity boundary, or any live
Sheet data.

## Files touched

```
patches/3D-P-007-WP1c_analytics-provisioning-hotfix_20260823.js
    — complete replacement for the bound 3D-P Apps Script Code.gs
3d-print/apps-script-3dp-api/Code.gs
    — local editable source
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
    — regression coverage for provisioning, repair, draft exclusion, and rollback
```

## Behaviour

- Assigning a canonical SKU now synchronizes `Аналітика!A4:N17` from active
  `Номенклатура` rows within the same write lock.
- A newly active SKU gets the approved default Serhiy share `0.5` (50%). A
  valid existing owner-set share remains attached to its SKU and is preserved.
- Drafts are excluded from the calculator, so they cannot consume a sellable
  analytics row or become readable as products by accident.
- `Аналітика` is bounded to its existing 14 active-SKU rows. A header mismatch,
  duplicate analytics SKU, invalid existing share, or capacity overflow fails
  before the SKU assignment can remain active.
- If synchronization or audit logging fails, the assignment restores the draft
  key/status/history and the full `Аналітика!A4:N17` snapshot.
- Public function `repair3dpActiveNomenclatureAnalytics()` repairs already
  active products such as `BR-BULB-100`. It is idempotent and writes one audit
  record only when it actually changes the calculator.

## Local verification

```
node 3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
PASS — 11 analytics-provisioning checks: repair of an already-active missing
       SKU, formula row, 50% default, successful SKU read, preservation of an
       owner-set share, idempotent public repair, draft exclusion, and rollback
       of SKU/status on schema failure.

new Function(Code.gs)
PASS — Apps Script source syntax accepted.

node 3d-print/apps-script-3dp-api/tests/api.test.mjs
PASS — active setup-route guard remains unchanged.

git diff --check -- <scoped source and test files>
PASS

patch SHA-256 = B1718DBC64755545751ECFE158B688DDF3A9EC60A3FEB70EC9E1706930A35FD3
local source SHA-256 = B1718DBC64755545751ECFE158B688DDF3A9EC60A3FEB70EC9E1706930A35FD3
```

## Deployment and repair order (owner)

1. In the 3D-P Google Sheet, create a named version in Version history.
2. In its bound Apps Script project, replace the complete `Code.gs` content
   with the patch file content and save it.
3. Create and deploy a new Web App version. This makes future owner SKU
   assignments provision their calculator row automatically.
4. In the Apps Script function picker, run
   `repair3dpActiveNomenclatureAnalytics` once. It initializes the current
   `BR-BULB-100` row and any other active SKU whose share is blank.

No CRM deployment is required.

## Post-deploy owner QA

- [ ] The repair execution returns `ok: true`; `initialized_skus` includes
      `BR-BULB-100` if it was still missing a share.
- [ ] `Аналітика` shows `BR-BULB-100` in the calculator with `% прибутку
      Сергію = 50%`; its derived cells remain formulas, not pasted values.
- [ ] Reload the 3D dashboard and open `BR-BULB-100`: the missing-profit-share
      error is gone.
- [ ] Create one disposable draft: it does not appear in `Аналітика`.
- [ ] Assign that draft a valid canonical SKU: exactly one calculator row
      appears and its initial Serhiy share is 50%.

## Rollback

Restore the previous Apps Script deployment version. The repair changes only
the derived calculator block `Аналітика!A4:N17`; it records the change in
`_Аудит_API`. Before rerunning the repair, use the named Google Sheets version
from the first deployment step if a manual data rollback is required.

## Side effects / risks

- The public repair clears stale derived values/formulas only inside the fixed
  calculator block `A4:N17`, then rebuilds it from active `Номенклатура` rows.
  It does not touch the market-reference block below row 17.
- An existing blank share for an active SKU is initialized to 50%, the
  previously recorded default for a newly provisioned calculator row. A
  nonblank invalid share is rejected rather than silently altered.
- No commit, push, publication, or live Sheet write was performed by Codex.
