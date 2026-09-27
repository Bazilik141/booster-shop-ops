# CRM-016: 3D dashboard follow-up from owner-reported CRM V188

Date: 2026-09-24. The owner reports CRM V188 and Alerts V6 deployed at 19:31,
and a clean integrity check (`problems: []`, 14,503 ms). Neither Web App was
deployed or modified live during this follow-up.
The owner confirms V188 uses the last supplied CRM API file,
`work/CRM-016_CRM_API_from_V187_followup.gs`. This is owner-confirmed source
provenance; no separate V188 export was inspected.

## Corrected inventory interpretation

The earlier `OP-JP-EB03-BST` diagnosis conflated available balance with
physical stock. The live sheet formula gives -5 after open-order reservations;
the dashboard shows 11 physically on hand, 16 reserved, 5 short against those
reservations, and 8 incoming. The current negative-balance alert is therefore
about availability, not a physical count below zero. The owner confirms that
the second Onix print was not recorded; `FIG-ONIX-500` physically has 0 in
the 3D tracker after one print and one sale.

## Changes

- Replaced the calculator and product-editor SKU selects with one searchable
  control. It matches both SKU and product name, supports keyboard selection,
  limits the visible list to 12 matches, and retains the active-only rule in
  the calculator.
- Restored the focus and caret position after each search keystroke in the
  "All products" table. The cause was a full table-block `innerHTML` replacement
  from `setThreeDpInfo('search', ...)`. Search now redraws only the product
  table and skips the other 3D information sections.
- Replaced stretched two-column analytics lists with compact content-sized
  panels and six-month bars. The recommendation placeholder no longer takes
  an empty half-panel.
- Added a read-only cached CRM `3dp_order_share` action. It counts distinct
  non-cancelled, non-returned CRM order IDs dated in the current Kyiv month,
  and marks orders containing at least one canonical 3D SKU. The tile shows
  numerator, denominator, and percent; zero orders display no percent.

## Verification and gate

- The complete candidate API differs from the prior V187 follow-up file only
  by the new read route, its month-specific cache key, and its helper.
- Local CRM and dashboard JavaScript syntax parse; focused count/exclusion
  test passes; `git diff --check` passes. No browser test against the owner's
  authenticated 3D data or live Web App was run.
- The complete local API candidate is
  `work/CRM-016_CRM_API_from_V188_3dp_share.gs`; the dashboard is the canonical
  `dashboard/booster-dashboard.html`. The new tile requires the candidate CRM
  API to be published; until then it reports the API error instead of an
  invented percentage. Alerts V6 needs no code change for this UI follow-up.
