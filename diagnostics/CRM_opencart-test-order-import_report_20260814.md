# CRM — OpenCart test-order import unblock

Date: 2026-08-14

## Outcome

The CRM no longer suppresses an OpenCart order because its customer phone, email, or name matches an owner/test identity stored in `Налаштування!A32:B60`.

The only remaining inbound duplicate guard is the existing exact `order_key` check: the first delivery inserts the sale; a repeated delivery of the same OpenCart order returns `ignored_existing_order` and creates no duplicate rows.

## Root cause

`upsertOpenCartOrder_()` called `isIgnoredOpenCartOrder_()` before it parsed product lines or inserted `Продажі` rows. That helper read `Налаштування!A32:B60` and returned true on:

- normalized phone match;
- normalized email match;
- partial normalized name match in either direction.

This was an identity-based owner-test convenience filter, not an OpenCart or CRM validation rule. A test checkout using the owner's normal details therefore returned `ignored_test_order` and never reached the CRM.

## Scope

- Included: remove the inbound identity-based suppression of OpenCart test orders.
- Preserved: Web App token check, script lock, product payload requirement, exact duplicate-order guard, normal sales mapping, and all existing accounting writes.
- Excluded: backfill of already-suppressed historical orders, automatic test-data cleanup, live deployment, and any spreadsheet data or configuration write.

## Files touched

```text
crm/apps-script/Code.gs
crm/apps-script/tests/open-cart-identity-filter.test.mjs
```

## Local verification

```text
OpenCart test-order import tests: passed
- owner-like phone/email/name inserts OC-FOP-1003
- a repeated delivery returns ignored_existing_order

Code.gs syntax parse: passed
git diff --check: passed
```

## Deployment and live QA (owner)

1. Publish the current local `crm/apps-script/Code.gs` as a new main CRM Web App version. It includes this change and the pending CRM-004 packaging fix.
2. Create one disposable OpenCart test order using the previously blocked owner phone, email, or name and an existing SKU.
3. Confirm one new `Продажі` order group with the expected `OC-FOP-…` key appears. It must not report `ignored_test_order`.
4. Re-send or trigger the same source delivery only if the normal OpenCart tooling safely supports it; confirm the CRM still has one order group, not two.
5. Run the read-only `integrity_check`. Any new problem code blocks acceptance.

## Risks and rollback

- Owner-identity orders now intentionally enter CRM. That is the requested change; test orders must be cleaned up deliberately rather than silently dropped.
- Existing orders that were previously suppressed are not retroactively imported by this change.
- Roll back by republishing the prior Apps Script version. No sheet data is changed by deployment itself.

## Remaining evidence gap

This is local source and stubbed-test evidence only. A fresh live Apps Script publication and one real disposable OpenCart owner-identity order are required to prove the end-to-end path.
