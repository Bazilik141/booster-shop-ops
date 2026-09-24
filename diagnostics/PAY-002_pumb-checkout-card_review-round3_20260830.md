# PAY-002 — patch review round 3 (WP1 + WP2)

Date: 2026-08-30
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-preview-token-gate_20260828.php` (unchanged since
2026-08-29 06:35), `patches/PAY-002_pumb-checkout-card_20260828.php`
(rev 2026-08-30 08:58), `diagnostics/PAY-002_pumb-checkout-card_report_20260828.md`
(rev 2026-08-30 08:59), plus rounds 1 and 2.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz`, read-only extraction of
`extension/pumb_credit/catalog/controller/payment/pumb_credit.php` and
`catalog/controller/checkout/payment_method.php`.

## Verdict

**Return for changes.** Both round-2 blockers are genuinely fixed. This round
introduced one defect that is more serious than anything found so far: the
WP1 → WP2 sequence now leaves `confirm()` with **no server-side gate at all**.

## R3-1 · Blocking — WP2 deletes the PUMB server gate instead of relocating it

Round 2 left two gates in `confirm()`: the one WP1 installs (correct position,
after the language load) and the one WP2 inserted before the language load. The
round-2 review asked for the WP2 one to be moved. The revision removed **both**:

```php
$confirmReplacement = <<<'PHP'
        $this->load->language('extension/pumb_credit/payment/pumb_credit');
PHP;
$p = rx($p, $confirmPattern, $confirmReplacement, 'PUMB confirm gate');   // replaces the line with itself — no-op
$p = replaceCounted($p, "        if (!\$this->pay002Available() || !\$orderId) { … }", '', 1, 'PUMB legacy gate removal');
```

The first call substitutes the language-load line with an identical string, so
nothing is inserted. The second deletes the line WP1 installed. WP1's gate was
not a duplicate — after the revision it was the only one.

Reproduced by applying WP1's three `confirm()` transformations and WP2's two, in
order, to the live 2026-08-28 controller. Anchor counts: language line 1,
removal target 1 — so both operations apply, the runner does not abort. Result:

```php
    public function confirm(): void {
        // PAY-002: server-side token/public gate is authoritative; UI is not trusted.
        $this->load->language('extension/pumb_credit/payment/pumb_credit');
        $orderId = (int)($this->session->data['order_id'] ?? 0);

        $order = $this->db->query("SELECT * FROM `" . DB_PREFIX . "order` WHERE `order_id`='" . $orderId . "' LIMIT 1")->row ?? [];
```

`pay002Available()` occurrences in the whole generated file: **1**, and it is in
`index()` — UI rendering only. What is gone from `confirm()`:

- the token / `payment_pumb_credit_public` check — the endpoint accepts a request
  from any session, with or without the preview flag;
- the `payment_pumb_credit_status` check the original code had before WP1 — a
  disabled extension no longer stops a bank application;
- the `!$orderId` guard. With `order_id = 0` the order query returns nothing and
  the following min/max branch replies with an error, so this one case fails
  closed **by accident**, not by design.

The remaining comment still says the server-side gate is authoritative, which
will read as reassurance to the next person in this file.

This is the exact control the handoff designates as load-bearing: once
`payment_pumb_credit_status` is enabled for the preview, the disabled-status
safety net is gone and this gate is what replaces it. `requestedTerm()` still
validates the term, but a valid term is not authorisation.

Fix: leave WP1's gate untouched. WP2 should insert nothing into `confirm()` — or,
if the comment placement is to be tidied, move the existing line rather than
delete it. The `'PUMB confirm gate'` no-op `rx()` should be removed; as written
it consumes an anchor check while asserting, by its label, something it does not
do.

## R3-2 · Medium — the payable total is now computed on every checkout request

The shared-payable refactor is right in shape and closes round 2's N2: one
memoized `pay002CheckoutPayable()`, passed into both gates, one coupon/totals
pass per request. Verified there are three call sites of `pay001MonoChastGate()`
in the live file (lines 101, 150, 326) and all three still work with the new
optional parameter.

The behaviour change is at the top of `getBoosterCheckoutPaymentMethods()`:

```php
$payable = $this->pay002CheckoutPayable();
$pay001_gate ??= $this->pay001MonoChastGate($payable);
```

`pay002CheckoutPayable()` runs unconditionally. Previously
`pay001MonoChastGate()` returned before `prepareCouponTotal()` and `getTotals()`
whenever monobank was not configured — which is the current production state.
So a store with monobank off went from zero coupon/totals passes per
payment-method request to one, for every customer, not only token holders.
Memoisation keeps it to one per request, and the arithmetic is unchanged, but
this is the coupon/threshold area that produced the post-cutover ST-2c defect.
Either restore the short-circuit (compute the payable lazily, inside the gates
that actually need it) or state the change explicitly and cover it in the smoke
run.

## R3-3 · Low

- The WP1-before-WP2 assertion (`need(strpos($p, 'private function pay002Available…')`)
  now runs **after** the gate removal and passes because `index()` still uses the
  method. It no longer verifies what its message claims.
- Deleting the gate line leaves a blank line in `confirm()`.
- Unchanged from round 2: preflight failures before the `try` block surface as an
  uncaught `RuntimeException` with a stack trace rather than the clean `ERROR: …`
  line C1 asks for.

## Round-2 findings — status

| ID | Round-2 defect | Status |
|---|---|---|
| N1 | PHP `+` used for string concatenation in the generated `index()` | fixed — `.` in both places |
| N2 | PUMB availability coupled to monobank's gate | fixed — shared `pay002CheckoutPayable()`, both gates take the same value (see R3-2 for the side effect) |
| N3 | confirm gate inserted before the language load | **not fixed — resolved by deleting the gate** (R3-1) |
| N4 | five uncounted `str_replace` calls | fixed — `replaceCounted` with explicit counts 1 / 7 / 2 / 1 / 1, all matching the live twig |
| N6 | uncaught exception on preflight failure | open, low |

Round-1 fixes all still hold: setting-key form, ordering assert, restore-on-fail,
anchor counting in `rx()`, drawer branding and the conditional `СКОРО БУДЕ` card.

## Report accuracy

`diagnostics/PAY-002_pumb-checkout-card_report_20260828.md` states, for this
round, "the duplicate WP1 gate in the confirm path is removed during WP2
composition" and records the fixture assertion
`confirm language-load precedes the single server gate: ok`. Neither holds for
the runner as delivered: the gate removed was the only one, and the generated
file contains no gate in `confirm()` for that assertion to have found. The
assertion evidently checks that the language load precedes something else, or was
produced by a runner revision that differs from the file in `patches/`. Whatever
the cause, a green fixture line was recorded for a control that is absent — that
matters more than the defect itself, because it is the check that was supposed to
catch it.

## Conventions

Unchanged from round 2 and compliant on both runners: C1, C2 (now including the
five counted twig replacements), C3, C4, C5, C6 (WP1 only), C7. The no-op
`'PUMB confirm gate'` anchor is a C2 wart rather than a violation.

Risky zones: checkout · payment · order flow · DB (WP1).

## Rollback

Unchanged. Reverse order — WP2, then WP1, from `_patch_backups/<PATCH_ID>-<ts>/`;
WP1 additionally needs its two settings rows deleted. Kill switch with no
rollback: set `payment_pumb_credit_status` back to disabled in admin.

## Smoke after a corrected deploy

`bs-checkout-smoke` (full 11 steps), then `bs-deploy-verify`.
`bs-seo-risk-gate` on the WP1 preview route. Add one explicit negative case to
the run: call the PUMB confirm route from a session that never opened the preview
URL and confirm it is refused before any bank call.

## Returned to

The same executor. No parallel patch author for PAY-002 this round.
