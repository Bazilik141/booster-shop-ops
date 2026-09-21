# Codex Follow-up Report — 3D-P-027: dashboard SKU normalization

Date: 2026-09-19

## Owner decision and scope

Owner decision dated 2026-09-18: the dashboard create form must normalize a
typed SKU to uppercase before validation. A user may type
acc-3d-onix-110-21-blk; the form must validate and submit the canonical
ACC-3D-ONIX-110-21-BLK value.

This follow-up changes only the dashboard create-path expectation and the two
focused test matrices. It does not alter the SKU grammar itself.

## Implemented behavior

- dashboard/booster-dashboard.html normalizes the new-SKU input with
  trim().toUpperCase() before calling threeDpSkuTypeError.
- The dashboard validator retains the revision 9 suffix grammar:

~~~
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}(?:-[A-Z0-9]{1,5})*$
~~~

- The 3D-P Apps Script copy remains byte-identical to that regex.
- The previous lowercase rejection row was removed from the dashboard and API
  acceptance matrices. The matrix is now 12 cases; lowercase is a form-input
  normalization behavior, not a raw-regex acceptance criterion.
- Raw lowercase remains invalid against the strict API grammar. The dashboard
  never submits it raw because it normalizes first.

No token membership or token-order rule was added.

## Files changed by this follow-up

~~~
dashboard/booster-dashboard.html
dashboard/tests/3dp-sync-journal-static.test.mjs
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
diagnostics/3D-P-027_suffix-normalization-followup_report_20260919.md
~~~

## Explicitly not changed

- 3d-print/apps-script-3dp-api/Code.gs
- 3d-print/apps-script-3dp-api/SOURCE_STATE.md
- crm/apps-script/Code.gs
- Apps Script deployment state, sheets, CRM data, or task status

The current worktree contains a separate existing diff in
crm/apps-script/Code.gs. This follow-up did not inspect, edit, stage, or
otherwise change that file.

## Validation evidence

Passed:

~~~
node dashboard/tests/3dp-sync-journal-static.test.mjs
3dp-sync-journal dashboard static tests passed

dashboard/API suffix regexes identical; create path normalizes input

git diff --check
~~~

The focused dashboard test verifies both conditions together:

1. The validator uses the revision 9 suffix regex.
2. The create path applies trim().toUpperCase() before it calls that validator.

The 12-case strict API matrix confirms unchanged acceptance of legacy and
suffix SKUs and unchanged rejection of malformed base, trailing-hyphen,
overlength-token, non-3D, and sealed-TCG inputs.

## Known separate test state

The full role-read-projections.test.mjs suite has an older, unrelated V29
compatibility mismatch caused by pre-existing batch-draft work returning
quantity. This follow-up does not weaken or repair that assertion.

## Deployment and QA

No commit, push, Apps Script paste, publication, or sheet write occurred.

The previously reported 3D-P Apps Script publication remains owner-reported
only. Live QA is still pending when the owner next creates a real SKU:

1. Type a lowercase suffixed article such as acc-3d-onix-110-21-blk.
2. Confirm the form shows or creates the canonical uppercase article.
3. Confirm ACC-3D-410 remains rejected.
4. Confirm an existing SKU can still be edited and archived.

## Claude review request

Review only the three task-owned behavior hunks above. Confirm that uppercase
normalization occurs before validation, the two regex copies remain identical,
and the removed lowercase case has not been replaced by a relaxed raw regex.
