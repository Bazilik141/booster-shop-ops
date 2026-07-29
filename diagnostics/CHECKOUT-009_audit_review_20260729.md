# Review report — CHECKOUT-009 architecture audit round

Date: 2026-07-29
Reviewer: Claude
Handoff: `handoffs/handoff_CHECKOUT-009_shipping-selection-not-registered_20260729.md`
Reviewed artifacts:

- `plans/CHECKOUT-009_checkout-architecture-map_20260729.md` (518 lines)
- `plans/CHECKOUT-009_checkout-behaviour-register_20260729.md` (175 lines, 40 rows)
- `plans/CHECKOUT-009_checkout-state-consolidation-options_20260729.md` (399 lines)
- `diagnostics/CHECKOUT-009_shipping-selection-not-registered_report_20260729.md`

## Verdict

**Audit round accepted. Option C (staged) endorsed, with four conditions below
that must be satisfied before the implementation round is authorized.**

The handoff's audit-round acceptance criteria are met: all seven architecture
sections present, the behaviour register was built from the three required
sweeps with a verbatim marker before-image, 40/40 rows carry a disposition in
every option, zero `UNKNOWN-PURPOSE` rows, and no production write, commit or
push was performed. The root cause is stated at file/function/line level and is
consistent with all four owner observations, including why a reload does not
help and why re-selecting a saved address does.

I did not re-run Codex's mechanical accounting. The points below are gaps and
side effects, not repeated checks.

## Assessment of the root cause

`checkout-reskin.js:391` sends every coupon JSON response — including the
read-only `coupon.summary` — through `totalsChanged('coupon', …)`, which since
`ST-2C-COUPON-SHIPPING-20260728` means `couponChanged()`: revision advance,
`clearPaymentState()`, display-text reset, recovery quote with
`autoSelect:false, resaveCurrent:true`. The address-triggered save is then
discarded by the stale-response guard, and the recovery branch cannot re-save
because the hidden shipping code is already empty. The gates at
`checkout.twig:877-917` and `payment_method.twig:68-69` are correct; their input
never recovers.

This explains the regression timing correctly: the same
`totalsChanged('coupon')` routing existed before 2026-07-28 and was harmless
while that handler only cached the summary. The 07-28 round converted the
handler into a mutation without narrowing what reaches it. That is a
classification defect, not a bad fix — which is why rollback would have been the
wrong instrument and the owner's audit-first directive was correct.

## Conditions before the implementation round

### 1. The logged-in root cause is inferred, not reproduced (blocking)

The guest path has production reproduction evidence. The logged-in explanation —
initial `coupon.summary` at `checkout-reskin.js:455` overlapping
`bootstrap()`'s auto quote — is plausible and consistent with the owner's
workaround, but no authenticated reproduction or HAR was run
(report §"Evidence gaps" item 3). Option A/C Stage 1 fixes the logged-in path
on the strength of that inference. Require the authenticated HAR before
implementation, not after.

### 2. Cache-key population effect is under-analysed (blocking for sequencing)

The audit correctly identifies that production still serves
`?v=r135-cart-refresh-20260725` for both checkout scripts while the files
contain 07-28/29 logic, and correctly requires a new immutable key with the
fix. Two consequences are not drawn out and should be, because they change how
the owner reads the current situation:

- Codex reproduced the failure in a **new anonymous session**, i.e. cold cache,
  i.e. current JS. Customers with a **warm cache** are executing pre-07-28
  JavaScript. The share of customers currently affected is therefore unknown
  and probably not 100% — and, symmetrically, **bumping the cache key without
  the fix would extend the failure to everyone.** No key bump before the fix
  ships.
- The same stale key means the 2026-07-28/29 rounds may have been QA'd in a
  browser still running pre-patch JavaScript. Treat every "verified" outcome
  from those two rounds as unconfirmed until re-checked on the new key. This is
  a process finding, not a code finding, and it belongs in the implementation
  round's QA design.

### 3. First15 auto-apply must be proven server-side (blocking for Option A/C Stage 1)

Stage 1 makes `coupon.summary` a pure query with no requote. The register notes
First15 "can auto-apply on later eligible checkout". If that auto-application
is realised server-side before the bootstrap quote, the free-shipping threshold
stays correct and the change is safe. If any part of it depends on the summary
response driving a client-side totals mutation, removing the mutation path
leaves a stale quote for exactly the eligible-returning-customer case — a
silent wrong-shipping-cost defect of the same family as ST-2c. The options
document's control ("verify summary cache/free-shipping projection still
renders") is weaker than this risk. Require explicit proof of where the
auto-application happens, plus a threshold test with an auto-applied First15 in
both directions around ₴2,000.

### 4. Pinta is a third-party extension (non-blocking, must be recorded)

Stage 1 edits
`extension/PintaNovaPoshtaCod/catalog/view/template/shipping/js_checkout_shipping_address_form.twig`
and deletes the 250 ms timer there. No artifact mentions that a Pinta extension
update will overwrite that file and silently reintroduce the duplicate-writer
condition behind CHECKOUT-009. Record this in the implementation patch header
and in the behaviour register row for the Pinta writer, so a future upgrade is
checked against it.

## Points I explicitly agree with

- Option C over Option A: Stage 1 is a strict subset of the Option B target
  design, so it does not become another layer to work around later.
- Option B not first: replacing hidden-input authority while production order
  placement is degraded is the wrong sequencing.
- Rollback rejected: it would have discarded correct ST-2c behaviour to hide a
  classification bug that predates none of it.
- The "analytics: confirm one event per real save, **not zero**" control — the
  failure mode most likely to be missed when deduplicating writers.
- Keeping `alterRedirectShippingAddressSave()` (registered no-op under
  ACC-002F) rather than opportunistically deleting it.

## Note on scope tension, now resolved

The owner refused a band-aid while guest checkout was blocked. The audit shows
the correct fix is also the small one: Option C Stage 1 is three to four client
files, no controller, model, DB or payment-transport change. There is no longer
a trade-off between "correct" and "fast" — the implementation round can be
authorized as soon as conditions 1–3 are satisfied.

## Not proven by this review

Everything in the audit rests on the owner-supplied archive
`CHECKOUT-009-live-evidence-20260729-155027.tar.gz`
(sha256 `2990f6ab…fffc79`, captured 2026-07-29T12:50:27Z) plus one anonymous
production reproduction. I did not access the server, the database or the live
site. The DB theme-override topology remains unproven (the collector queried a
nonexistent `theme` column); a Twig override in the database would change which
source actually executes and would invalidate parts of the file-level mapping.
