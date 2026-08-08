# Codex Report — 3D-P-014: CRM-local sync failure journal

Date: 2026-08-08

## Scope

Implemented handoff rev 2 only. The sync journal now lives in the main CRM spreadsheet and is
written locally from `sales.getParent()`; the 3D-P Apps Script project and workbook were not
changed. This makes a broken or unavailable 3D-P URL visible without a second network dependency.
Follow-up on 2026-08-08 preserves a useful, sanitized error reason and de-noises normal per-line
journal results.

## Source evidence

- `crm/apps-script/SOURCE_STATE.md` records the CRM live export pulled 2026-08-08 11:41 Kyiv.
- The repository `3d-print/apps-script-3dp-api/Code.gs` remains unchanged; `sync_journal` is not
  exposed there or to the Serhiy API surface.
- The local CRM `Code.gs` is now a prepared, not deployed, source candidate. `SOURCE_STATE.md`
  explicitly marks that distinction.

## Files touched

```
crm/apps-script/Code.gs                              — CRM-local journal, owner-token read action, source labels
crm/apps-script/SOURCE_STATE.md                      — mirror/deployment-state accuracy
crm/apps-script/tests/3dp-sync-journal.test.mjs      — mock regression coverage
dashboard/booster-dashboard.html                     — CRM journal panel in 3D-друк → Інформація
dashboard/tests/3dp-sync-journal-static.test.mjs     — dashboard wiring/static safety checks
```

## Implementation

- Hidden tab `_Журнал_3DP_синхронізації` is created only on the first journal event, has the
  approved seven columns, retains the newest 1,000 rows, and never calls `SpreadsheetApp.getActive()`.
- Every journal append is one bounded row write; failure is swallowed and cannot block the CRM order.
- `sync_journal` is a bounded, newest-first CRM `doGet` action. It stays behind the existing CRM
  owner-token gate and returns at most the requested recent limit.
- The hook records caller source as `apiAddSale_`, `apiUpdateSale_`, or `unknown` for legacy
  unlabelled calls. `updateSaleStatus()` was not changed.
- Journal detail uses a closed fallback for normal outcomes, but preserves a sanitized and
  240-character-bounded real error reason when available. URLs, token/key values, Bearer values,
  phone numbers, and e-mail addresses are redacted before either journal or Logger output.
- A remote HTTP failure retains its status and safe machine-readable `code`; unbounded remote
  `error` text is not trusted. The generic `error` fallback is neutral rather than claiming that an
  order ID is missing.
- A normal CRM trigger line emits one final journal row: a newly created sale with an already
  applied stock adjustment remains `created` and carries that ledger state in `detail`, rather than
  producing a second `noop` row.
- The dashboard panel uses the existing main CRM API client, escapes every returned field, marks
  non-`created`/`updated`/`noop` outcomes with the existing `issue-tag` treatment, and has an API
  refresh button rather than requiring a browser page reload.

## Local validation

```
node .\crm\apps-script\tests\3dp-sync-journal.test.mjs
3dp-sync-journal tests passed

node .\dashboard\tests\3dp-sync-journal-static.test.mjs
3dp-sync-journal dashboard static tests passed

git diff --check
passed
```

The CRM mock test compiles the full current `Code.gs` in a VM and covers: created, update/noop,
invalid quantity, negative-stock warning, duplicate-key warning, no 3D-P SKU, absent configuration,
unavailable 3D-P API, schema mismatch, unknown source, CRM-token read access, newest-first limit,
1,000-row retention, phone/e-mail/token/URL redaction, neutral future `error` detail, one-row
created-plus-ledger behavior, and journal-write fail-open behavior.

## Not verified locally

- Apps Script deployment and the real Google Sheets context.
- Actual timing before/after a live order save.
- Dashboard visual behavior with the owner’s locally stored CRM and 3D-P credentials.
- Owner QA cases against live CRM and the real 3D-P Web App.

## Owner deployment and QA

1. In the **main CRM** bound Apps Script project, create a named pre-deploy version.
2. Replace only `Code.gs` with `crm/apps-script/Code.gs`; do not edit the 3D-P Apps Script project.
3. Publish a new version of the existing CRM Web App. No new Script Properties or token values are
   required.
4. Test the seven handoff cases: create, update/noop, no-3D-P SKU, broken URL,
   absent URL property, dashboard refresh, and before/after timing.
5. Export the deployed CRM `Code.gs` back into the repository and update `SOURCE_STATE.md` with
   the actual pull time and owner-reported deployment version.

## Rollback

Restore the named prior CRM Apps Script deployment version and publish it. The journal tab is
additive; it may remain hidden as historical diagnostics. Remove it only if the owner explicitly
approves deleting those diagnostic rows.

## Risks

CRM mutation flow remains a risky zone. The regression test proves local fail-open control flow,
but only owner live QA can prove Apps Script runtime behavior, Web App deployment, and real-order
latency.
