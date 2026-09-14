# CRM — ZenMarket read-only validator split

Date: 2026-09-13

## Outcome

Prepared a local CRM candidate that prevents ordinary ZenMarket read paths from
changing spreadsheet structure. It is not pasted, published, or live.

## Root cause

`crm011ZenRequireSetup_()` was reached from `finance_report` through
`crm011ZenBalanceSnapshot_()`. It called `crm011ZenEnsureSheet_()`, whose
append-only top-up-header branch can call `setValue`; a blank sheet header could
therefore be written while opening Finance.

## Change

- Kept `crm011ZenEnsureSheet_()` as the setup-only schema writer used by
  `setupCrm011ZenMarketAccount()`.
- Added `crm011ZenValidateSheet_()`, which only reads sheet/header state and
  throws `ZENMARKET_SETUP_REQUIRED` or `ZENMARKET_SCHEMA_CONFLICT`.
- Routed `crm011ZenRequireSetup_()` through the pure validator for the ledger,
  lot index, and top-up journal.
- Added a focused test proving valid schema is unchanged and a missing header
  is reported without being restored.

## Files changed

```
crm/apps-script/Code.gs
crm/apps-script/tests/crm-011-zenmarket-account.test.mjs
crm/apps-script/SOURCE_STATE.md
diagnostics/CRM-zenmarket-read-only-validator_report_20260913.md
```

## Local verification

```text
node --test crm/apps-script/tests/crm-011-zenmarket-account.test.mjs
tests 7
pass 7
fail 0

Code.gs parse: passed
git diff --check: passed
```

## Owner publication gate

1. Before pasting, inspect `ZenMarket_Поповнення!A1:J1`. Every cell must be
   non-empty and exactly match: `Payment ID`, `Дата`, `Сума JPY`, `Курс JPY за
   1 UAH`, `Сума UAH`, `Gateway`, `Джерело оцінки`, `Примітка`, `Request ID`,
   `Створено`. If any is blank, run `setupCrm011ZenMarketAccount()` on the
   current live version first; do not publish this candidate against incomplete
   schema.
2. Owner pastes and publishes only the reviewed candidate.
3. Run `apiIntegrityCheck_()` and require `clean: true`.
4. Run `crm011ZenMarketVerificationForOwner()` and require `ok: true`. Unlike
   Finance, this path does not catch a schema-validation error.
5. Open Finance once and confirm the ZenMarket KPI shows the real JPY balance,
   not the setup hint.

Do not deliberately remove or rename a production header to test the failure
path. Any actual schema recovery remains a separate owner-approved action.

## Risk and rollback

Risk is low: valid existing Zen sheets keep the same data contract; invalid
schema now fails rather than silently mutating. If a production regression is
observed, restore the prior Apps Script deployment version. Do not delete or
rename any ZenMarket sheet during rollback.

`setupCrm011ZenMarketAccount()` and `crm011ZenHistoricalRows_()` are not spent
one-time code: together they are the sole schema writer and idempotent recovery
route. They must remain callable; a later hygiene task may move that block to a
separate Apps Script file, but must not delete it.
