# Codex Report — CRM-005 transport verdict collision

Date: 2026-08-09

## Root cause and fix

`ok` was incorrectly used for both HTTP/API transport success and the integrity verdict. A completed
dirty check therefore returned `ok:false`, and the dashboard's generic `call()` correctly rejected
it as `API error` before the tile could show the bounded findings.

`crmIntegrityFinalize_()` now always returns `ok:true` for a completed check and carries the verdict
in `clean`. Existing request failures remain `{ ok:false, error: ... }`. The dashboard already
selects its tile state from `problems.length`, so it needs no behaviour change.

## Regression evidence

The extended CRM integrity test obtains a real `apiIntegrityCheck_()` result from the mock workbook,
then feeds it through the real, brace-extracted dashboard `call()` function:

- dirty `РРЦ` rows `71-75` arrive intact instead of throwing `API error`;
- a clean result arrives intact;
- `{ ok:false, error:'bad token' }` still throws `bad token`.

The unit test, tile test, Apps Script syntax check, dashboard-script syntax check, and
`git diff --check` passed locally. No live write or deployment was performed.

## Owner QA and rollback

Publish this `Code.gs` as a new named Web App version and hard-refresh. This collision fix needs no
dashboard code change: the live tile already proves the dashboard delivery is present. The first
integrity click should show `price_without_sku` for `РРЦ` rows `71-75`, not `API error`.

Restore the immediately preceding named Apps Script version if needed. The dashboard file is still
coupled with 3D-P-025 and CRM-005 UI work, but this transport fix is server-side only.
