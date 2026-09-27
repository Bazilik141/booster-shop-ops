# CRM-016: repeated inventory load latency

Date: 2026-09-27

## Evidence

Owner-supplied CRM-013 telemetry from the dashboard recorded three
`inventory_snapshot` reads (183,725 response bytes each): 12,968, 15,887,
and 43,425 ms server time; 15,585, 18,613, and 46,783 ms network time.
Browser render time was 90, 17, and 88 ms. Each response reported
`cache_state=value_too_large`; the current Apps Script cache has a 95,000-byte
limit and skips oversized payloads. This establishes repeated server work on
rapid navigation, not a slow table renderer. It does not isolate which
server-side sheet or 3D-P read caused the variable first-load time.

## Local change

`dashboard/booster-dashboard.html` now reuses a successful, verified
`inventory_snapshot` for 60 seconds within the open browser tab. The value
is cleared after successful CRM or 3D-P POST requests, a 3D-P connection
change, or manual hard refresh. Unavailable/unverified snapshots are never
cached. No token or response is persisted to browser storage.

## Validation and limits

Focused cache and invalidation test passed. Existing CRM-016 dashboard
inventory contract and inline JavaScript syntax test passed. This is local
source validation; no owner browser timing after publication is available.
The change should avoid repeated network calls during quick tab switches;
it does not speed up the first `inventory_snapshot` request. A separate server
profile is needed before changing CRM or 3D-P read paths.

## Rollback

Revert the added browser cache helper and its invalidation calls in the
dashboard; the original direct `call('inventory_snapshot')` path remains the
fallback behaviour.
