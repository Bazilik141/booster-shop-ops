# Codex Report — ST-2c: real NP shipping cost + content-sweep Phase 0

Date: 2026-07-19

## Scope

Phase 0 was run against the live server at `/home2/boosters/public_html` on
2026-07-19. Part A is prepared as a source-only patch. Part B remains
discovery-only: no product, category, information-page, schema, or other
customer-facing content was edited.

## Phase 0 findings

- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
  has two live `cost => 0` quotes: warehouse (line 63) and doors/address
  (line 94). Both already call `getDocumentPrice()` before discarding that
  value. The current threshold key exists in code but has no persisted DB row;
  the model and admin fallbacks are `1500`.
- `payment_hutko_shipping_include=0` in the live settings table. Hutko already
  computes its charged amount as `order total − shipping`; its controller is
  read-only in this task.
- `catalog/view/javascript/checkout-reskin.js` has the two `RD13-STUB` markers,
  a hardcoded `FREE_SHIP_THRESHOLD = 2000`, and calculates eligibility from
  `grand || subtotal`, i.e. payable total after coupon discounts. This conflicts
  with the approved pre-discount subtotal rule.
- The existing safe response-only route is `checkout/coupon.summary`; no
  `checkout/confirm.confirm` call is added.
- CRM order readback was not available to the diagnostic runner because it has
  no CRM endpoint/token. The patch does not alter an order-sync payload shape;
  the required API readback remains a post-deploy QA item.

## Part B — findings list, no edits

### Confirmed threshold copy

`ocp5_product_description` has 44 Ukrainian (`language_id=4`) records with the
same free-shipping claim, including its displayed sentence:
`безкоштовна доставка Новою Поштою від 1500 грн`.

Affected product IDs:

```
50, 56, 57, 58, 59, 61, 63, 65, 67, 68, 69, 70, 71, 72, 74, 76,
77, 78, 79, 80, 81, 82, 83, 84, 85, 87, 88, 89, 90, 91, 92, 93,
94, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 115
```

These 44 records are the only confirmed DB rows for a future owner-approved
Part B replacement from `1500` to `2000`. The source diagnostic output is the
verbatim evidence for each row and its surrounding description context.

### Explicit exclusions

- `ocp5_information_description`, `information_id=3`, **Публічна оферта**,
  says only that the possibility of free delivery is shown at checkout. It has
  no threshold amount, so it must not be changed.
- `ocp5_category_description`: zero matches.
- `catalog/view/template/product/product.twig` contains the old threshold in
  JSON-LD: `Безкоштовна доставка Новою Поштою від 1500 грн; ...`. It is a
  schema file, explicitly out of scope for ST-2c Part B; leave it unchanged
  unless a separate schema-approved task is opened.
- Remaining file hits are generic OpenCart "free shipping" labels or the
  current checkout progress component, not a customer-facing 1500-threshold
  claim to sweep.

## Files touched

```
patches/ST-2c_phase0_live_diagnostics_20260718.php — executed live, self-deleted
patches/ST-2c_real-np-shipping-cost_20260719.php    — prepared Part A patch
diagnostics/ST-2c_real-shipping-cost-content-sweep_report_20260719.md
```

The Part A patch changes only:

```
extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
catalog/controller/checkout/coupon.php
catalog/view/javascript/checkout-reskin.js
```

## Dry-run result

Local fixture run completed with the live-confirmed NP model and reskin source
shapes:

```
changed_files=4
hutko=read_only payment_hutko_shipping_include=0; charged_amount=order_total-shipping
cache_clear=required
done=ok
```

Post-run scan found zero `RD13-STUB`, `FREE_SHIP_THRESHOLD`,
`grand || subtotal`, or `checkout/confirm.confirm` matches.

## php -l result

```
No syntax errors detected in ST-2c_real-np-shipping-cost_20260719.php
No syntax errors detected in extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php
No syntax errors detected in extension/PintaNovaPoshtaCod/admin/controller/shipping/pinta_nova_poshta.php
No syntax errors detected in catalog/controller/checkout/coupon.php
```

## Idempotency

After the fixture applied all four markers, a second run returned:

```
already_applied=yes
```

## Rollback

The runner backs up every changed file before writing to:

```
_patch_backups/ST-2c_real-np-shipping-cost_20260719-<timestamp>/
```

Restore the four corresponding paths from that directory, then clear OpenCart
cache via `DIR_CACHE`.

## Run command (owner)

```bash
cd ~/public_html || exit
php ST-2c_real-np-shipping-cost_20260719.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Post-deploy QA checklist

- [ ] In NP admin settings, confirm the free-delivery value displays as `2000`;
      change it once to a test value and save, then restore `2000`.
- [ ] Run `bs-checkout-smoke`; no draft/void order must be created while totals
      refresh.
- [ ] Check guest and logged-in flow for warehouse, parcel locker, and address,
      with subtotal below and at/above 2000, with and without First15.
- [ ] Below 2000: NP shipping is non-zero and equals the API tariff; at/above
      2000: shipping is zero and the visible label is `За наш кошт`.
- [ ] Confirm eligibility remains based on item subtotal before discount, while
      Hutko amount equals order total minus the shipping line.
- [ ] Read one new order through `action=orders&status=active&limit=1`; flag any
      shipping-field shape change rather than changing CRM code.
- [ ] Review this report's 44 Part B records before authorising any content edit.

## Side effects / risks

Checkout/payment risk remains high: the first live tariff response must be
validated against Nova Poshta for all three delivery modes. No Hutko, Checkbox,
CRM, `url.php`, DB schema, or JSON-LD code is changed.
