# Codex Report — 3D-P-027: variant SKU suffix validators

Date: 2026-09-16

## Scope and implementation

The handoff required the create-form SKU grammar to accept the existing base:

~~~
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}$
~~~

plus zero or more suffix segments:

~~~
^(BR|FIG|ACC-3D)-[A-Z0-9]{2,5}-\d{3}(?:-[A-Z0-9]{1,5})*$
~~~

Implemented exactly that in the two required copies:

- dashboard/booster-dashboard.html — threeDpSkuTypeError
- 3d-print/apps-script-3dp-api/Code.gs — NOMENCLATURE_SKU_PATTERN_3DP

Each copy has a reciprocal sync comment naming revision 9 of
plans/3D-P_sku-naming-convention_20260807.md as the source. Both validation
messages now give the suffixed example ACC-3D-ONIX-110-21-BLK.

The dashboard create path previously uppercased input before validating it. That
would have accepted ACC-3D-ONIX-110-blk, contrary to handoff acceptance
criteria. It now only trims, so lowercase remains invalid in the form and in
the API validator.

No colour-token allowlist was introduced. The grammar admits any uppercase
alphanumeric suffix token of length 1–5, per owner decision.

## Files added or changed for 3D-P-027

~~~
dashboard/booster-dashboard.html
dashboard/tests/3dp-sync-journal-static.test.mjs
3d-print/apps-script-3dp-api/Code.gs
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs
3d-print/apps-script-3dp-api/SOURCE_STATE.md
patches/3D-P-027_variant-sku-suffix-validators_20260916.js
diagnostics/3D-P-027_variant-sku-suffix-validators_report_20260916.md
~~~

The new patches artifact is an Apps Script paste guide, not a PHP runner. It
contains the two exact replacement anchors, the expected matrix, rollback
instruction, and no credentials.

## Explicitly not touched

- crm/apps-script/Code.gs — confirmed absent from the task diff.
- Main CRM validation, journal outcomes, and prefix-to-type coupling.
- SKU data, Sheets, Script Properties, deployment state, or dashboard task
  status.
- Edit/archive paths.

## Validation evidence

Passed:

~~~
node dashboard/tests/3dp-sync-journal-static.test.mjs
3dp-sync-journal dashboard static tests passed

3D-P-027 API validator matrix passed: 13 cases

node --check patches/3D-P-027_variant-sku-suffix-validators_20260916.js
Get-Content 3d-print/apps-script-3dp-api/Code.gs -Raw | node --check --input-type=commonjs
git diff --check ...
~~~

The dashboard static test and the API-focused matrix both cover the handoff
table:

- accepts the four legacy base SKUs and the three new suffixed SKUs;
- rejects missing mnemonic, trailing empty segment, lowercase suffix,
  overlength suffix, non-3D ACC-001, and sealed PKM-JP-EXSD-STD-GRS;
- asserts the API error and dashboard error contain the suffixed example;
- asserts the dashboard create path does not uppercase before validation.

## Existing suite blocker, not changed by 3D-P-027

The complete command for
3d-print/apps-script-3dp-api/tests/role-read-projections.test.mjs is not
green in the current shared worktree. It reaches and passes the new 3D-P-027
matrix, then fails an older V29 compatibility assertion for
3dp_batch_draft:

~~~
V23 owner response: 3dp_batch_draft
actual includes: quantity: 2
expected V29 response omits: quantity
~~~

The difference comes from pre-existing, uncommitted batch-draft candidate work
in Code.gs and its test, not from the SKU validator. This task does not alter
that compatibility contract or weaken the assertion. Claude should keep this
as a separate review gate rather than attributing it to 3D-P-027.

## Shared-worktree boundary

Before this task, Code.gs, SOURCE_STATE.md, and
role-read-projections.test.mjs already contained unrelated local candidate
changes. 3D-P-027 added only its narrow pattern/message/state/test hunks and
preserved the other changes. Review the task-owned hunks only; do not merge or
approve the unrelated candidate work under this task.

## Deployment and owner QA gates

Nothing was committed, pushed, pasted into Apps Script, published, or written
to a Sheet.

1. Create a named pre-deploy version in the bound 3D-P Apps Script project.
2. Apply only patches/3D-P-027_variant-sku-suffix-validators_20260916.js.
3. Publish a new Web App version.
4. Open the repository dashboard file and hard-refresh.
5. Create ACC-3D-ONIX-110-21-BLK as Функціональний аксесуар; it must pass.
6. Try ACC-3D-410; it must fail with the new shape/example.
7. Confirm an existing SKU can still be edited and archived.
8. Re-export the deployed 3D-P script and refresh Code.gs plus
   SOURCE_STATE.md; do not infer publication from this local source.

## Rollback

Dashboard: revert the validator and create-path trim-only change.

Apps Script: restore the named pre-deploy version, or restore the two old
anchors stated in the paste guide and publish a new version. No data migration
or Sheet rollback is needed.

## Claude review request

Verify the two task-owned regex literals are byte-identical, the trim-only
dashboard path is intentional for lowercase rejection, the test matrix matches
handoff section 7, and no main CRM source or unrelated candidate hunk is
included in a 3D-P-027 commit.
