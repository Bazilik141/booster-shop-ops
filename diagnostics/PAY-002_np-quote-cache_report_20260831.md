# Codex Report — PAY-002: reduce repeated Nova Poshta quote latency

Date: 2026-08-31

## Scope and result

Owner requests cart feedback correction and faster delivery/payment loading.
The separate `PAY-002_cart-canonical-stock_20260831.php` remains the cart fix;
its deployment/owner QA is not assumed. This independent runner optimizes the
Nova Poshta quote path and adds bounded Server-Timing evidence. It does not
change credit-provider selection or payment eligibility.

The optimization eliminates unnecessary tariff calls for qualifying free
shipping and repeated identical successful tariff lookups for 300 seconds in
the same session. It does NOT eliminate the first carrier lookup for a new
paid-shipping payload. Do not describe the whole 2–4 second issue as resolved
without production timing results.

## Current evidence

- Owner Network evidence: `checkout/shipping_method.quote` starts around 983 ms
  after navigation and waits 4.18 s for server response (4.19 s total); browser
  queue/stall are about 2 ms each. No session headers/cookie values are stored.
- `booster-debug-cart-checkout-20260831.tar.gz`, SHA256
  `3f2c0874d7a1e2119996e1e445d904d510727539094709d18a56403f997707ff`.
- `booster-debug-np-quote-internals-20260831.tar.gz`, SHA256
  `d5ec3b90d956deef635a10b6177b38170be14b6c2a1a191e8c12c053df6247e4`.
  The latter was already in Downloads although the owner attached the former again.

The shipping controller calls `model_checkout_shipping_method->getMethods`.
That model synchronously loops enabled shipping providers. Pinta's model calls
`getDocumentPrice` before checking whether the tariff display becomes
`За наш кошт`. Its API path constructs a new client, which opens another DB
connection and reads the configured key, then calls
`InternetDocument/getDocumentPrice` via blocking cURL. No existing price cache
was found. The API client allows up to 30 seconds and its array-union option
merge makes default timeout overrides ineffective. That shared library also
serves other operations, so this patch does not change it or its timeouts.

City/warehouse lookup models use local SQL. The currently supplied evidence
does not isolate their time or prove every millisecond is an external API wait.
New timing headers distinguish total quote execution from the price lookup.

## Files touched

```text
patches/PAY-002_np-quote-cache_20260831.php
scripts/tests/pay002-np-quote-cache.test.php
scripts/tests/fixtures/pay002-np/PintaNovaPoshtaCod/system/library/pintanovaposhta/pintanovaposhtaapi.php
diagnostics/PAY-002_np-quote-cache_report_20260831.md
```

Only two production targets:

- `extension/PintaNovaPoshtaCod/catalog/model/shipping/pinta_nova_poshta.php`
- `catalog/controller/checkout/shipping_method.php`

The API test double belongs only to local tests, makes no requests, and is NOT
embedded in the runner. Build/fixture artifacts remain under
`work/pay002-np-latency/` and are not staged.

## Behavior and constraints

- Free shipping uses the existing coupon-adjusted total and configured
  threshold. Only the display quote bypasses the tariff lookup; public carrier
  document operations are not repurposed or bypassed.
- Paid shipping still uses the exact existing API payload and fallback rules.
- Cache key hashes the complete request properties, store ID, and configured
  API-key context. It includes city pair, service type, weight, dimensions,
  declared cost and cargo/seat parameters. A payload change misses the cache.
- Session cache holds only opaque hashes, successful positive prices and
  expiry; at most eight entries and 300 seconds. Address/key values are not
  stored in it or logged. It is not shared across customer sessions.
- Lookup failures, malformed/nonpositive API amounts and fixed-rate fallbacks
  are not cached. API-disabled settings bypass the cache entirely.
- Existing payable shipping cost remains `0.0` with tax class `0`; the carrier
  tariff is informational. No tariffs are hardcoded or approximated.
- Only successful rates may be up to five minutes old. A new paid quote or
  expired cache still waits for the carrier; no shorter timeout is imposed.
- Server-Timing reports `pay002_shipping_quotes` (whole model call) and
  `pay002_np_price` with `hit`, `miss`, or `free`. Miss duration includes API
  client construction and carrier request. Hit/free duration 0 denotes the
  skipped API operation, not a claim that the whole request costs zero time.

## Exact guards

| Target | Before SHA256 | After SHA256 |
|---|---|---|
| Pinta shipping model | `2dcdc5d824b662803f7534c92bd3de56c4ccef55ec2a4a62e60382d230ee6a90` | `24923ef2c40b28fc2045c487db41713a5e653aeac7ceb35ebe48ecdd7ce7ab23` |
| Shipping controller | `9853ba87ef2722b329621d8b517727044ec002e520ba251e18d6c146aa1d203f` | `eb14c74de452aa3aa074780fa046704084f50f44d730fe389e9fe79a7d2639e9` |

Read-only dependency hashes also guard checkout shipping/cart/coupon models
and the Pinta API client. Candidate hashes/one-count anchors are checked before
backup and write; partial/mixed source states fail closed. Cart and payment
controller hashes are not dependencies, so the separate cart fix can run in
either order without colliding with this runner.

## Local validation

```text
PASS release runner on real archive; exact after SHA; self-delete; repeat
PASS source drift rejected before writes
PASS injected syntax failure restores both original files
PASS regression detects missing cache in original source
PASS cold paid quote unchanged; repeat identical without API/client construction
PASS warehouse/courier free skip; coupon-adjusted threshold remains authoritative
PASS payload/account/store/currency changes invalidate; bounded opaque cache
PASS expired and malformed cache are ignored
PASS API disabled does not reuse an API tariff
PASS curl/HTTP/API/invalid/zero results are not cached
PASS existing fixed-rate fallback preserved
PASS timing headers expose only durations and hit/miss/free
```

Tests execute the real exported Pinta shipping model with isolated service
stubs and a fake API response. They prove control flow, output preservation,
invalidation and no extra API calls, not live carrier speed. No bank, carrier,
database or production requests were made. Runner and generated PHP lint pass
locally on PHP 8.3; code uses PHP 8.0-compatible syntax and the runner lints on
the production PHP binary before reporting success.

## Idempotency and rollback

Exact post-image returns `already_applied=yes`. Backups are stored at:
`_patch_backups/PAY-002_np-quote-cache_20260831-<timestamp>-<suffix>/`.
Restore both relative target paths and clear normal cache. The session cache
becomes unused on rollback; no bulk session deletion is needed. Deployment
does not write to DB; normal runtime writes a bounded session cache.

## Owner run command

Upload the PHP runner only to `public_html`:

```bash
cd ~/public_html || exit
php PAY-002_np-quote-cache_20260831.php && php -r 'require "config.php"; foreach (glob(DIR_CACHE . "cache.*") ?: [] as $f) if (is_file($f)) @unlink($f); foreach (glob(DIR_CACHE . "template/*") ?: [] as $f) if (is_file($f)) @unlink($f); echo "cache cleared\n";'
```

## Owner QA / next gate

- [ ] New paid quote: tariff matches expected; read only `Server-Timing`, not
  Cookie/Set-Cookie headers. `miss` can still be slow.
- [ ] Reload identical valid checkout within five minutes: `hit` and identical
  tariff; compare total quote duration and time until payment options appear.
- [ ] Qualifying free-shipping cart: `free`, correct `За наш кошт` display.
- [ ] Coupon dropping payable below the free threshold restores the paid
  tariff; changing address/type or quantity requests a matching new tariff.
- [ ] Mono/PUMB preferred term and delivery total remain correct; do not create
  a bank order as part of this check.
- [ ] Normal Tier 1 site smoke. No CSS, Twig, or layout changes are shipped.

If `hit`/`free` is still slow, compare whole quote timing with request TTFB to
investigate local SQL/startup/session waits. If `miss` API time dominates, cold
lookup latency remains a carrier dependency; removing that wait from checkout
would require a separate asynchronous display-tariff flow, not an assumed fix.

No deployment, commit/push, Notion change, or roadmap status transition was made.
