# Claude review — TECH-015 WP2: GA4 `begin_checkout` on the checkout page

Date: 2026-09-07
Reviewer: Claude (chat). Patch author: Codex. Post-deploy review — both the WP2
runner and its corrective hotfix were authored, and the first was deployed and
regressed on production, before this review.

## Verdict

**Review OK; owner QA required.**

The delivered state is correct and runtime-proven. What is missing is evidence
discipline, not code: the report's own checklist leaves the hotfix run unticked
and its "Production execution evidence" section records only the superseded
runner. See the owner items at the end.

## Independent verification performed

Reproduced here, not taken from the Codex report.

1. `catalog/view/template/checkout/checkout.twig` from
   `backup-9.3.2026_21-30-35_boosters.tar.gz` hashes to
   `d355ae2c…fb937f` — the "before" the first runner demands.
2. Applying the first runner's single replacement outside the runner yields
   `fb7d6a8a…49499e`, the hash that runner printed on production at
   2026-09-07 09:36:44.
3. Applying the hotfix's replacement to that result yields
   `675e6cb1…09cafb`, the hotfix's declared "after".
4. The final template contains exactly one `pushEventData('begin_checkout'`,
   exactly one `pushEventData('qualify_lead'`, exactly one
   `<h1>Оформлення замовлення</h1>`, and zero `<h1>{{ heading_title }}</h1>`.

The two runners therefore compose to exactly the state Codex describes, with
nothing else touched.

## The regression, and who owns it

The first runner did what my handoff told it to do: restore
`<h1>{{ heading_title }}</h1>` so the vendor module's registered handler could
inject at its anchor. On production the H1 rendered `Мій кошик` and no event
fired.

Cause: `catalog/controller/checkout/checkout.php` never assigns
`$data['heading_title']`. It uses the checkout language value for the document
title and breadcrumb only. By Twig render time the child-controller language
loads have overwritten the global `heading_title` with the cart value. The
hardcoded H1 that RD-13 introduced had been masking this since 2026-07-06.

**This is a defect in my handoff, not in Codex's execution.** I offered Option A
as "cheapest and self-maintaining" and flagged only the copy consequence, having
verified the language file but not whether the controller assigns the variable.
A copy-only change was assumed safe and was not. The lesson is recorded in
project memory: on this install, never assume `heading_title` reaches a
customized template — grep the controller for the assignment first.

Customer-facing exposure: the live checkout showed the wrong H1 between the
09:36:44 deploy and the hotfix. Cosmetic, no functional or payment impact.

## What the hotfix actually does, and why it is the better design

It does not restore the vendor's anchor. It hardcodes the approved heading again
and renders the vendor-prepared variables directly:

```twig
{% if ps_track_begin_checkout and ps_begin_checkout %}<script>ps_dataLayer.pushEventData('begin_checkout', {{ ps_begin_checkout }});</script>{% endif %}
```

This is sound and I verified the contract it depends on: the module's
`eventCatalogViewCheckoutCheckoutBefore` returns early only when the module is
disabled or both `begin_checkout` and `qualify_lead` tracking are off. Neither
holds. It sets `ps_track_begin_checkout` / `ps_begin_checkout` on `$args`
*before* attempting its own string injection, so the payload is available to the
template whether or not the vendor's anchor exists. Payload construction stays
in vendor code — no cart logic is duplicated, and no vendor file is edited.

The result is better than what my handoff asked for: it is immune to the
`heading_title` collision, keeps the visible copy under our control, and does not
depend on a vendor string-match surviving future template edits. The runner also
fails closed unless the vendor still exposes all four `$args` keys, so a vendor
update that drops them stops the patch rather than shipping a dead snippet.

One asymmetry worth recording: `{{ ps_begin_checkout }}` is rendered unescaped
and relies on OpenCart's Twig autoescape being off — which it is, proven by the
correct payload arriving at GA4. WP1 instead emits hex-escaped JSON with an
explicit `|raw`. WP2's form is the vendor's own convention, so this is
consistency with the module rather than a defect, but if Twig autoescaping is
ever enabled the WP2 snippet breaks silently while WP1 keeps working.

Unlike WP1, WP2 degrades safely if the module is removed: the `{% if %}` guard
makes the whole block a no-op. WP1's unguarded `ps_dataLayer` call still deserves
the one-line `if (window.ps_dataLayer)` wrapper recommended in the WP1 review.

## Runtime evidence assessment

Accepted. The decisive item is the DevTools Network entry: one request initiated
by `js?id=G-283QW89TX8`, status `204`, batched payload carrying
`en=begin_checkout`, `cu=UAH`, `epn.value=800`, empty `ep.coupon`, and one item
(id 85, price 800, qty 1) with name, Booster Shop affiliation, Bandai brand,
categories and checkout-list metadata.

That metadata is itself proof of which code path ran: affiliation, brand and
category come from the vendor's payload builder and appear nowhere in WP1's
Booster-built payload. Only the hotfix template can have produced it.

The several earlier "no event" observations were diagnostic false negatives —
GA4 batches `page_view` and `begin_checkout` into one transport request, so
filtering the Network list narrowly hid it. The console inspections
(`begin_queued: 1`, processed order `js -> config -> event begin_checkout`) were
sound intermediate steps, not evidence of failure.

## Known behaviour, not defects

- `begin_checkout` is page-entry based with no deduplication. Re-entering or
  reloading checkout emits again. This is standard GA4 behaviour and matches the
  vendor module's own design; do not "fix" it with a session guard.
- The same anchor now also carries the `qualify_lead` snippet. That setting is
  off and its Lead Associations are empty, so it is inert. If it is ever enabled,
  it will start emitting from here.

## Owner items

1. **Confirm the hotfix actually ran on production, and paste its runner
   output.** The report's checklist leaves that box unticked and its production
   section records only the superseded 09:36:44 run. The browser evidence makes
   it near-certain the hotfix is live, but the repository record must not be left
   claiming post-hotfix results without the run that produced them — and the
   report's rollback path names only the first runner's backup directory, so the
   hotfix's own backup path is currently unrecorded.
2. **Run the Tier 1 storefront URLs** from `AGENTS.md` — the last unchecked QA
   item. No payment or order needed.

## TECH-015 closure

Still not closeable. WP2 is done pending item 1 above, but the two WP1 runtime
gaps stand: an authorized PAY-003 recovery render, and a real Hutko return —
including the open question of whether an unconfirmed Hutko payment can reach
`checkout/success` and book revenue for an unpaid order.

## WP3 note

WP2 established the pattern that makes WP3 straightforward when the owner wants
it: the template consumes vendor-prepared `$args` directly instead of depending
on a vendor string match. `checkout/shipping_method.twig` still has its original
anchor, so `add_shipping_info` would work from the toggle alone;
`checkout/payment_method.twig` has lost its anchor and needs the WP2 pattern.
Enabling both toggles without that patch would produce the half-data trap already
flagged.
