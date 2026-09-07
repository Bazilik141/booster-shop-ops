# Claude review — TECH-015 WP3: WP3-0 inventory and the three runners

Date: 2026-09-07
Reviewer: Claude (chat). Author: Codex. Pre-deploy review — none of the three
runners has been run on production.

## Verdict

**Review OK; owner QA required.** All three runners are safe to deploy, in the
order and with the toggle sequencing below.

## Independent verification performed

Reproduced from the fresh backup `backup-9.7.2026_20-35-02_boosters.tar.gz`, not
taken from the Codex report.

- The three target templates hash to exactly the "before" values the runners
  demand: `payment_method.twig` `efee1a60…`, `thumb.twig` `0375e269…`,
  `common/cart.twig` `9b2a8c32…`. These also match the WP3-0 inventory table.
- Applying each runner's replacements outside the runner reproduces exactly the
  declared "after" hashes: `974d73f6…`, `ee483c07…`, `020b8f2a…`. Every anchor
  occurs exactly once in its target.
- WP3-0's platform gates check out: `index.php` in the fresh backup declares
  `VERSION = 4.1.0.3`, so the `>= 4.1.0.0` thumb branch and the `>= 4.1.0.1`
  cart_list branch are the correct ones to have applied.

WP3-0 itself is the right shape and is what this handoff asked for: it re-derived
state from a fresh backup instead of trusting the audit, and it reached the
honest conclusion that no package could be dropped.

## The check the runners' own preflight does not make

WP3a would have been dead on arrival for a reason none of its assertions cover,
so I verified it separately.

The vendor's `eventCatalogViewCheckoutPaymentMethodSaveAfter()` bails out at
`if (!$json_response || !isset($json_response['success'])) return;`. It never
sets `ps_add_payment_info` unless our own `checkout/payment_method.save` response
carries a `success` key. WP3-0 notes that the *template* has no `json['success']`
branch, which made this a live question: if the controller had also stopped
emitting `success`, the patch would have deployed cleanly, asserted green, and
produced no event — indistinguishable from the toggle being off.

Verified in the fresh backup: `catalog/controller/checkout/payment_method.php`
`save()` spans lines 295–541 and sets `$json['success']` at line 356. The vendor
handler will fire. WP3a is viable.

The runners assert the vendor's contract but not our side of it. Worth adding to
the pattern for any future package that consumes a vendor `$json` key.

## Package notes

**WP3a — sound.** Emission sits after the error guard and before the local
success-state updates, exactly where WP3-0 said it should. The whole block is
wrapped in `{% if ps_track_add_payment_info %}`, which the vendor only sets when
the toggle is on, and the runtime call is additionally guarded on
`window.ps_dataLayer` and on the response key existing. With the toggle off it is
inert. Shipping is correctly left unpatched — its vendor anchor survives, so a
patch there would have been pure risk for no gain.

**WP3b — sound, with one thing to fix later, not now.** The design does what the
handoff required: no `data-ps-track-event` attributes, no `preventDefault`, no
`setTimeout`, no re-dispatch — the runner asserts all four negatives. The form
submits normally and the listener only reads. The `ps_has_options` branch
correctly emits `select_item` for option-bearing products, which a tile cannot
add anyway, and `add_to_cart` only for directly addable ones. Missing datasets
degrade to a silent no-op via the `hasOwnProperty` guard.

The thing to fix later: `product/thumb.twig` is the per-card partial — the
category template pre-renders each card through it — so the 928-byte `<script>`
block is emitted **once per product tile**. A twenty-product category page
carries twenty identical copies (~18 KB uncompressed; far less after gzip, since
the blocks are identical). It is functionally correct — `bsTech015CatalogGa4Bound`
means only the first copy binds, and `<script>` is `display:none` so grid layout
is unaffected — but the listener belongs somewhere that renders once per page.
With TECH-043/044/045 all open on page weight and render blocking, this should
not become the house pattern.

Do not respin the patch for this now. Relocate the block to a once-per-page
template the next time a patch touches the catalogue templates, and keep the
per-tile `data-tech015-ga4-submit` attribute where it is.

Forward risk to carry into TECH-045, not a defect here: the listener needs
`window.jQuery` at body-parse time and returns silently without it. In the fresh
backup `header.twig` line 45 loads jQuery synchronously, so it is fine today. Any
future render-blocking work that adds `defer` to jQuery would silently kill this
listener — and the vendor module's own click handlers with it.

**WP3c — sound, and the best-placed of the three.** The call sits inside the
existing removal handler's AJAX `success`, so the event is emitted only after the
server accepted the removal — it cannot report a removal that failed. No
interception; the runner even asserts the `preventDefault` count is unchanged
from the original. Guarded on the vendor dataset key existing.

Unrelated observation while reading the anchor: the live mini-cart removal
handler contains a leftover `console.log(json)` on a production cart control.
Out of scope for TECH-015 — worth cleaning up in whatever task next touches
`common/cart.twig`.

## Deploy order and toggles

One patch at a time, Tier 1 URLs after each.

1. **WP3a**, then — and only then — the owner enables **both**
   `Track Add Shipping Info` and `Track Add Payment Info` in the module. Enabling
   before the patch gives a window where shipping data arrives and payment data
   silently does not, which is the half-data trap this package exists to avoid.
2. **WP3c** — lowest risk, independent file.
3. **WP3b last, on its own**, because it is the only one touching the buy button.

## QA that matters

For WP3b the cart checks outrank the tracking checks. Before and after the
patch: add from a tile, add the same product again, add a second product, open a
preorder tile, open an out-of-stock tile, change quantity in the cart, remove
from the mini-cart — then confirm line count and totals are identical to the
pre-patch behaviour. A tracking event is never worth a wrong cart.

Filter DevTools Network on the GA4 collect request itself, not on a narrow
string. GA4 batches several events into one request; that produced repeated false
negatives throughout WP2.

Run `bs-checkout-smoke` in full after WP3a and after WP3b.

## Reporting requirement

Put the production runner output in each report. WP2's hotfix run was never
captured and the owner had to supply it from memory.

## TECH-015 closure

Unchanged and still blocked on the two WP1 runtime gaps: an authorized PAY-003
recovery render, and a real Hutko return — with it, whether an unconfirmed Hutko
payment can reach `checkout/success` and book revenue for an unpaid order. The
owner's GA4 check that `purchase` is registered as a key event comes after WP3.
