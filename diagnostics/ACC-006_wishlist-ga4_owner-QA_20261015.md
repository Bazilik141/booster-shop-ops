# ACC-006 GA4 — production apply evidence and owner QA acceptance

Date: 2026-10-15
Runner SHA-256: `c3a5175476d8c9e7933fa84a1cedb563d44bbd2861b72a12b29569ad73ba3de4`.

## Confirmed evidence boundaries

- Owner terminal output at `2026-10-15T16:59:58+00:00`: four targets changed, both PHP files lint clean on PHP 8.0.30, read-only DB preflight passed, `db_mutations=none`, apply `done=ok` and `cache_cleared=ok`.
- Owner-reported backup: `_patch_backups/ACC-006_wishlist-ga4_20261015-20261015T165958Z-f90477c8`. Its server contents were not independently accessed.
- Owner screenshot shows the installed wishlist event toggle on. Separate public rendered evidence confirms the published `add_to_wishlist` flag is true, rather than assuming the screenshot alone proves a saved setting.
- Read-only public product HTML/static JS at `2026-10-15T17:04:00+00:00`: current version reference `booster-wishlist.js?v=acc006-ga4-20261015`, served JS byte-identical to reviewed candidate (6,552 bytes; SHA-256 `f8ef6c244be886fb29f0414d57ce474e5331b513fe1ebb6ef7c3b630bc80340c`), active wishlist flag and six custom buttons without the native delegated tracking attribute. This confirms public composition/asset/configuration, not a successful add response or actual Google ingestion.
- Public checks made no wishlist action, browser JS execution or analytics/property write. No whole HTML, session cookie/token, measurement ID, customer data or private GA4 metric was retained.

Evidence sidecars: `ACC-006_wishlist-ga4_evidence_20261015/owner-apply_20261015.json` and `public-postdeploy_20261015.json`.

## GA4 QA evidence and owner acceptance

**GA4 receipt confirmed by owner screenshot:** the next owner reply shows `add_to_wishlist` in the Realtime event list after the requested manual test. This confirms event arrival, not exact per-action deduplication or item parameters. Only this technical QA observation is recorded; the screenshot and unrelated private analytics metrics are not copied into a review packet or repository artifact.

Precise per-device payload and duplicate-count checks use an enabled DebugView device. Aggregate Realtime totals can include other visitors and do not by themselves prove exact per-action counts. Google documents Tag Assistant as a personal-device debug-mode route; no global debug configuration change is needed.

Follow-up evidence: Realtime continues to list wishlist events, but the supplied detailed collection hit has `en=page_view`, not `add_to_wishlist`. The wishlist parameter-name table does not show currency/value/item values. The supplied Tag Assistant screenshot reports disconnected, and the DebugView screenshot does not establish a wishlist debug event. No duplicate behavior or payload correctness can be inferred from these images. Request one fresh add in the connected Tag Assistant shop tab and the specific wishlist event/data-layer details. Do not copy the supplied client/session/tag identifiers or the full analytics dump into repository or peer-review artifacts.

The next owner-provided MHTML was inspected locally as inert MIME/HTML, with scripts ignored and no raw archive copied. Its saved timeline contains entry **16, `add_to_wishlist`**, despite the disconnected UI status. The saved view is an overview and contains no item/currency/ecommerce payload fields. This is additional recorded-event evidence, not a full debug-session export, duplicate-count proof or item validation. Prefer opening entry 16 in the existing live Tag Assistant tab and reading its Data Layer details, rather than asking the owner to repeat a connection/addition solely because the current status says disconnected. The bounded observation is recorded in `owner-tagassistant-mhtml_observation_20261015.json`; client/session/tag identifiers are excluded.

**Owner data-layer payload now verified:** the subsequent pasted `add_to_wishlist` event contains one item, a populated identifier/name, `quantity=1`, `price=50`, `currency=UAH`, `value=50`, brand and category fields. The monetary fields are numeric and satisfy `value = price × quantity`. This verifies the supplied event structure and arithmetic; it does not independently re-query the product's expected identity/price or prove duplicate behavior. Only the bounded technical checks are saved in `owner-event-payload_check_20261015.json`; raw tag/gtm/session metadata and identifiers are excluded and the private owner QA payload is not sent to an external reviewer.

**Owner GA4 QA accepted:** the final owner reply, “все ок, працює норм.”, directly answers the two requested checks: adding an already-listed item produces no new event; removing and adding it again produces exactly one new event. Both are recorded as owner-reported passes, alongside the already confirmed event receipt and supplied payload structure/arithmetic. The bounded acceptance is saved in `owner-qa-acceptance_20261015.json`; no raw analytics/session data is retained. This reply does not separately attest every guest/login, cookie-policy, surface or Tier 1 scenario in the original report; those must not be described as individually observed by Codex. No further GA4 repair is indicated by the supplied evidence.

Official reference checked 2026-10-15: https://support.google.com/analytics/answer/9271392?hl=en (Realtime), https://support.google.com/analytics/answer/7201382?hl=en (DebugView).

State: owner deploy reported successful; public asset/flag verified; actual GA4 event arrival and supplied payload structure/arithmetic confirmed; duplicate and remove/re-add checks passed according to the owner; **OWNER_GA4_QA_ACCEPTED**. This is a local evidence disposition, not an external task/status transition or authorization to begin another work package. No Notion/dashboard or Git changes.
