# Codex Report — CRM dashboard purchases refresh

Date: 2026-08-10

## Scope

Fix the dashboard view after a purchase is added outside the page, using `yskh291` as the reported case. No CRM sheet cells, Apps Script source, formulas, or production deployment were changed.

## Evidence and root cause

- Live metadata confirmed the target tab is `Закупки` (sheet ID `58135100`), with headers on row 2.
- A bounded live read of `Закупки!A2:T309` found five open `yskh291` lots: `LOT-0123` through `LOT-0127`, rows `114:118`, all `В дорозі` and without a delivery date.
- Reproducing the deployed `recent_purchases` eligibility logic against the current data returned 15 open lots and included all five `yskh291` lots. Therefore the record is neither absent from CRM nor filtered by the API.
- `showRecTab()` held `accountingState.recentLoaded.purchases = true` indefinitely. A dashboard already open before the external CRM write reused its stale in-memory list instead of issuing a new request.

## File touched

```text
dashboard/booster-dashboard.html — force a fresh `recent_purchases` request whenever the Закупки tab opens
```

## Local verification

```text
Dashboard purchase refresh smoke passed
git diff --check
```

The smoke test parsed the dashboard's inline JavaScript, called `showRecTab('purchases')` with an already-loaded tab state, and asserted `loadRecTab('purchases', true)`.

## Owner QA

1. In the local dashboard, open **Оновлення записів → Закупки**.
2. Confirm the five `yskh291` lots appear in the active purchase list, grouped as the empty-track parcel if no track number has been recorded.
3. Switch away and back to **Закупки**; the list should remain current without a full browser refresh.

## Risk and rollback

Low risk: only the read request on tab opening changes. No API payload, CRM data, or selection logic was modified. To roll back, restore the previous cached-only `showRecTab()` condition.
