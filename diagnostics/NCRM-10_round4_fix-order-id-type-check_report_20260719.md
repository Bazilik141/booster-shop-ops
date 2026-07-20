# Codex Report — NCRM-10 round 4: order-id and live item-price validation

Date: 2026-07-19

## Scope

The handoff identified a type mismatch in order-sync validation: the OpenCart
bridge sends order_id as a JSON number, while the Edge Function read only
strings. That specific issue is fixed in index.ts without changing the shared
text helper or any PHP, SQL, secret, RLS, checkout, or Apps Script code.

Fresh source tracing found one directly adjacent blocker in the same validator:
the actual PHP buildPayload method emits products[].unit_price, but the Edge
Function validated and mapped products[].price only. Changing only order_id
would therefore move a real payload from the identifier 400 to the product
price 400. To satisfy the handoff requirement that the actual payload passes
every validation check, the same single Edge adapter now prefers unit_price
and retains price as a backward-compatible fallback. No other field behaviour
changed.

## Files touched

    ncrm/supabase/functions/order-sync/index.ts
        — accept numeric/string order_id and actual unit_price item field
    diagnostics/NCRM-10_round4_fix-order-id-type-check_report_20260719.md
        — this report

## Exact change

- orderIdText converts a safe JSON integer to its plain decimal string; strings
  continue through the existing trim-only text path. Negative, fractional,
  non-finite, and unsafe numeric values still fail the existing digits-only
  validation.
- itemUnitPrice reads unit_price first and uses price only if unit_price is
  absent or invalid. It is used both for validation and for the RPC payload, so
  accepted data is sent consistently.
- The shared text helper is unchanged; lastname, telephone, comment, SKU,
  model, payment, shipping, event, and order key validation behaviour remains
  unchanged.

## Realistic payload trace

The smoke payload mirrors the deployed PHP buildPayload shape:

    event: order_add
    order_id: 256 as a JSON number
    order_key: OC-FOP-0256
    date_added: 2026-07-19 12:00:00
    products[0].sku: ACC-001
    products[0].quantity: 1 as a JSON number
    products[0].unit_price: 100 as a JSON number

Validation after the change:

1. orderIdText(256) returns 256, which passes the digits check.
2. order_key passes the OC-FOP number format check.
3. date_added becomes 2026-07-19.
4. products is a non-empty array.
5. canonicalSku returns ACC-001; quantity is positive integer 1; itemUnitPrice
   returns numeric 100 from unit_price.

The same complete payload was sent to the local Edge Function twice: once with
numeric order_id 256 and once with string order_id 257. Both returned HTTP 200
with skipped=true and reason=test-filter. The TEST marker intentionally stopped
execution after validatePayload and before any RPC write, proving the full
validator path accepts both identifier representations and the actual item
price field without leaving test rows.

## 45-versus-65-byte discrepancy

The numeric order_id bug fully explains how the reviewed source can produce an
invalid OpenCart order identifier 400. It does not explain why the observed
Supabase metadata reported 65 response bytes while the literal JSON error
produced by this source is 45 ASCII bytes. The function invocation log did not
contain the literal response body, so this remains an unresolved observability
discrepancy rather than evidence against the code defect.

The owner smoke test after deployment is the closure condition: if a new real
order still returns 400, capture the response status, execution_time_ms, and
error_log tail again before starting another fix.

## Out-of-scope observation

The PHP bridge also sends a top-level numeric discount_total while the Edge
Function currently derives discount from a totals array. The actual bridge
does not include that totals array, so this can make imported discounts zero.
It does not cause validation rejection and was not changed in this round; it
requires a separate scoped financial-mapping review.

## Dry-run result

Local Edge Function compilation and request smoke:

    edge_smoke=numeric_order_id=ok
    edge_smoke=string_order_id=ok

Both requests used the full realistic payload shape above and were filtered
before database insertion. The local helper was stopped after testing. No
cloud Function, cloud database, OpenCart server, or secret was changed.

## PHP lint result

Not applicable: this round changes TypeScript only. Local Edge Function serve
successfully compiled and executed the changed source.

## Idempotency

Not applicable to a TypeScript source deployment. Re-deploying the same
order-sync source is replacement-safe; order insertion itself remains guarded
by the existing unique opencart_order_id constraint.

## Rollback

Redeploy the pre-round-4 order-sync index.ts from the known round-3 source
revision. No migration or OpenCart file needs restoration.

## Run command (owner)

From the NCRM directory, after Claude review:

    npx supabase functions deploy order-sync --no-verify-jwt
    npx supabase functions list

No db push, PHP patch, secret replacement, or cache clear belongs to this
round.

## Post-deploy QA checklist

- [ ] Functions list shows order-sync ACTIVE after deployment.
- [ ] Place one ordinary, non-test storefront order and confirm one matching
      sales row plus sale_items arrives promptly.
- [ ] Confirm error_log has no new NCRM-10 order sync failed line.
- [ ] Place a TEST-marked order and confirm test filtering remains unchanged.
- [ ] If the real order still fails, retain its Supabase invocation status and
      execution_time_ms plus the matching error_log tail.

## Side effects / risks

- The change accepts the live PHP numerical order id and unit_price values while
  continuing to accept the earlier string id and price forms.
- The unresolved 45-versus-65-byte metadata difference means the real-order
  smoke remains mandatory; no claim of full production root-cause proof is
  made before that result.
- The separate discount mapping observation should be scheduled before trusting
  CRM financial totals, but it is intentionally not bundled into this
  validation fix.
