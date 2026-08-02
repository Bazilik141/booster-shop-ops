# Codex Report — 3D-P-006: owner dashboard tab and batch calculator

Date: 2026-08-02

## Scope

Implemented the additive `3D-друк` dashboard page as a consumer of the bounded 3D-P Apps Script API. No `Code.gs`, main CRM, Google Sheets direct API, deployment, or business-data write was performed during implementation.

## Files touched

- `dashboard/booster-dashboard.html` — sidebar/page, isolated 3D-P credentials client, bounded reads, tables, batch calculator, guarded per-unit save/refetch, and `ROADMAP_FLOW` mirror.
- `diagnostics/3D-P-006_owner-dashboard-tab_report_20260802.md` — this report.

## Implemented contract

- Separate localStorage keys: `booster_3dp_api_url` and `booster_3dp_token`; neither shares the main CRM URL/token path.
- Read actions: `3dp_overview`, `3dp_skus`, `3dp_sales`, `3dp_plyushky`, `3dp_payouts`, `3dp_fixtures`, and bounded `3dp_get_range` for `Налаштування!A1:C4` and `Аналітика!A3:N17`.
- Batch inputs divide total weight/time by quantity before saving `Номенклатура!G:J`; an optional selected fixture saves its reference price to `N`. Every write has `expected_current`; completion fetches fresh `3dp_get_row`.
- The calculator reads, but cannot write, global constants from `Налаштування!B2:B4`.
- No plastic field and no packaging logic are present. Packaging remains 3D-P-010.

## Static validation

```text
node --check extracted dashboard script: passed
git diff --check: passed
36-unit formula probe: per unit 10g / 0.5h; material 5.0000; electricity 0.3672; amortization 6.0000; base 11.3672 UAH
```

## Remaining owner browser QA

- [ ] Open `3D-друк`, enter the deployed 3D-P `/exec` URL and owner token through the separate prompt; do not place either in source.
- [ ] Confirm overview, SKU, sales (including the existing discount columns), marketing, payouts, fixture dropdown, and displayed settings load through network requests to the 3D-P API only.
- [ ] Use a non-production test SKU/batch; save and confirm per-unit G:J values with the automatic fresh `3dp_get_row` message.
- [ ] Refresh the page and confirm `Огляд` still works and main CRM token behavior is unchanged.

## Known deliberate boundary

The model-link input is a local calculator draft only. The deployed Sheet/API has no owner-approved destination column for it, so it is not persisted. This needs an explicit follow-up schema decision, not an ad-hoc write.

## Rollback

Revert only `dashboard/booster-dashboard.html`; no server or Sheet schema was modified by this task.