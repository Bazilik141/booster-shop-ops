# Codex Report — CHECKOUT-009: shipping selection not registered

Date: 2026-07-29  
Codex config: model=Sol · effort=xhigh

## Scope

Completed the owner-authorized architecture audit and consolidation proposal.
No runtime patch, rollback, deployment, DB write, commit, push, Notion update
or roadmap status change was performed.

The audit used the owner-supplied post-deployment live archive, current
production guest reproduction, DB event/safe-setting evidence, loaded asset
inventory, live source markers and repository patch/diagnostic history.

## Files touched

```text
plans/CHECKOUT-009_checkout-architecture-map_20260729.md
plans/CHECKOUT-009_checkout-behaviour-register_20260729.md
plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md
diagnostics/CHECKOUT-009_shipping-selection-not-registered_report_20260729.md
```

## Evidence intake

```text
archive=CHECKOUT-009-live-evidence-20260729-155027.tar.gz
sha256=2990f6ab406786b8eae4d104da98aa3411a39555d35cd58ed540674549fffc79
capture_utc=2026-07-29T12:50:27Z
capture_root=/home2/boosters/public_html
php_version=8.0.30
archive_entries=287
unsafe_archive_entries=0
owner_confirmed_post_ST-2c_threshold_alignment=yes
```

The `_patch_backups` listing contains the deployed ST-2c coupon, mini-cart
requote and mini-cart threshold-alignment families. The DB snapshot confirms
enabled checkout/analytics/Pinta/order-history events, Mono enabled, and no
enabled PUMB safe-setting row in the captured subset.

## Production reproduction

Performed in a new anonymous browser session with one ₴700 product and
test-only nonpersonal contact values.

```text
np_region=Харківська
np_city=Харків
np_point=Відділення №1: вул. Польова, 67
captcha_solved=no
order_created=no
```

After the address/warehouse interaction settled:

```json
{
  "shipCount": 1,
  "shipValue": "",
  "shipTextValue": "",
  "paymentValue": ""
}
```

The shipping-method group remained empty, the summary still requested delivery
and payment, and the confirmation button remained disabled.

Production loaded:

```text
catalog/view/javascript/checkout-state.js?v=r135-cart-refresh-20260725
catalog/view/javascript/checkout-reskin.js?v=r135-cart-refresh-20260725
```

This confirms the previously reported cache-bust gap after the 2026-07-28/29
JavaScript changes.

## Root cause

`catalog/view/javascript/checkout-reskin.js:351-392` routes every coupon JSON
response, including read-only `coupon.summary`, through:

```text
window.bsCheckoutState.totalsChanged('coupon', ...)
```

`checkout-state.js:251-255` therefore invokes `couponChanged()`. That advances
the checkout revision and launches a recovery quote with
`autoSelect:false,resaveCurrent:true`.

The concurrent correct shipping save is ignored by the stale-response guard in
`shipping_method.twig:53-55`. Because the hidden shipping code is empty, the
recovery branch at `shipping_method.twig:150-168` neither auto-selects nor
re-saves a method. Downstream gates at `checkout.twig:877-917` then correctly
report not-ready from the empty projection.

Guest `register.save` makes the ordering explicit by calling coupon summary
before `addressSaved()` at `checkout.twig:1539-1549`. Logged-in first render has
the same initial summary/bootstrap overlap; changing to another saved address
later works because the initial summary has already settled.

## Behaviour accounting

```text
registered_behaviours=40
option_A_preserved=34
option_A_replaced=6
option_A_removed=0
option_B_preserved=25
option_B_replaced=15
option_B_removed=0
option_C_stage1_preserved=34
option_C_stage1_replaced=6
option_C_final_preserved=25
option_C_final_replaced=15
unknown_purpose=0
unaccounted_rows=0
```

Recommendation: Option C, staged consolidation. Stage 1 is the source-correct
semantic fix and writer cleanup; Stage 2 migrates hidden-input authority to
typed delivery, totals, payment and readiness snapshots.

## Validation

```text
architecture_map_lines=518
behaviour_register_lines=175
options_lines=399
behaviour_rows=40
required_architecture_sections=present
per_option_disposition_accounting=40/40
implementation_patch=not_created_by_scope
php_lint=not_applicable
node_check=not_applicable
dry_run=not_applicable
```

## Evidence gaps

1. Theme override export failed because the initial collector queried a
   nonexistent `theme` column. A schema-safe read-only command is included in
   the architecture map.
2. Full guest and logged-in HAR request/response evidence remains required
   before implementation approval.
3. Logged-in reproduction was not independently run because no authenticated
   test session was available.
4. No final-order, bank, CRM or Telegram production smoke was performed.

## Rollback

Not applicable: no live or existing tracked runtime source was changed. The
four new audit files can be removed from the repository if the owner rejects
the audit artifacts.

## Owner next action

1. Run the schema-safe theme override export in the architecture map.
2. Capture guest and logged-in HARs without placing an order.
3. Choose Option A, B or the recommended Option C.
4. Authorize a separate implementation round for the selected option.

## Side effects / risks

- Production guest checkout remains blocked; this audit intentionally did not
  deploy a mitigation.
- A browser/CDN may still execute pre-2026-07-28 JavaScript because the live
  asset key is stale.
- DB Twig override topology and authenticated request ordering are not yet
  proven.
- Unrelated dirty worktree changes were preserved.

