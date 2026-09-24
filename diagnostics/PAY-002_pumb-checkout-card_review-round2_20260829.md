# PAY-002 — patch review round 2 (WP1 + WP2)

Date: 2026-08-29
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-preview-token-gate_20260828.php` (rev 2026-08-29 06:35),
`patches/PAY-002_pumb-checkout-card_20260828.php` (rev 2026-08-29 06:36),
`handoffs/handoff_PAY-002_pumb-checkout-card-token-gate_20260828.md`,
round 1: `diagnostics/PAY-002_pumb-checkout-card_review_20260829.md`.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz`, read-only extraction of
`extension/pumb_credit/**`, `catalog/controller/checkout/payment_method.php`,
`catalog/view/template/checkout/payment_method.twig`.

## Verdict

**Return for changes.** Every round-1 defect is fixed and verified. Two new
blocking defects were introduced in this round, one of them a runtime fatal that
`php -l` cannot see, the other a design coupling that would keep PUMB invisible
on the current production configuration.

## Round-1 defects — all closed

| ID | Round-1 defect | Status in rev 2 |
|---|---|---|
| B1 | WP1 anchor in double quotes interpolated `$this` | fixed — single-quoted anchor, matches live `pumb_credit.php:9` verbatim |
| B2 | WP1 wrote short setting keys | fixed — inserts and rolls back `payment_pumb_credit_preview_token` / `payment_pumb_credit_public`. Verified against the live admin controller: `$keys` drives `$data['payment_pumb_credit_' . $key]` and `editSetting('payment_pumb_credit', $this->request->post)` stores post keys verbatim, so the long form is correct |
| B3 | WP1 and WP2 both declared `pay002Available()` | fixed — WP2 no longer inserts it and asserts `WP1 must run before WP2` before any write |
| B4 | `fail()` used `exit`, so the restore block never ran | fixed — `fail()` throws; lint failures inside the try now restore |
| B5 | drawer head hardcoded monobank, `СКОРО БУДЕ` unconditional | fixed — `pay001Drawer()` is replaced wholesale with an `isPumb` branch for image/alt/label and the "soon" card rendered only for the mono provider. **Diffed line by line against the live 2026-08-28 function: faithful, nothing dropped** (blocked-gate branch, `pay001-parts`, summary block, `data-pay001-monthly` / `-left` / `-phone`, `pay001SyncPhone($drawer)` all preserved) |
| B6 | `rx()` could not detect multiple anchors | fixed — `preg_match_all` count check before `preg_replace` |
| H1 | `matchesChoice()` / `creditIntent` had no PUMB branch | fixed |
| H2 | duplicate `button-confirm` id, `location.reload()`, no error display | id renamed, error region and `.fail()` handler added, success redirect added — **but see N1** |
| H3 | PUMB gate recomputed coupon totals a second time | fixed — `pay002PumbGate(array $pay001_gate)` reuses the computed payable — **but see N2** |
| H4 | `public` checkbox could not be switched off | fixed — hidden `value="0"` companion before the checkbox |
| M1–M5 | lint coverage, twig linted as PHP, unchecked writes, `pay001Credit: true` for PUMB, hardcoded `[345]` | all fixed (`writeFileChecked`, lint on PHP files only, `pay001Credit: !group.pay002_credit`, `([0-9]{1,2})`) |

## Blocking — new in this round

**N1 · WP2, generated `index()` — PHP `+` used for string concatenation.**
The nowdoc emits:

```
... '<script>(function($){var u=' + $json + ',success=' + $successJson + ';' ...
```

PHP has no `+` operator for strings. Under PHP 8 this raises
`TypeError: Unsupported operand types: string + string` (reproduced in a
sandbox), and it is a **runtime** error, so `php -l` reports the generated file
as clean and the WP2 fixture run passes. The failure surfaces on the live
checkout the first time a customer with the preview flag reaches the confirm
step with PUMB selected — the payment block dies. Round 1's version of this same
line used `.` correctly; this is a regression. Fix: `.` in both places.

**N2 · WP2, `pay002PumbGate()` — PUMB availability now depends on monobank.**
`payable` is taken from `$pay001_gate['payable']`. The live
`pay001MonoChastGate()` returns early with `'payable' => 0.0` in two cases:
when `pay001MonoChastConfigured()` is false (which starts with
`if (!$this->config->get('payment_mono_chast_status')) return false;`), and when
the cart contains a preorder item. With `payable = 0.0`, `pay002PumbGate()`
always falls to `reason = 'threshold'` and PUMB never becomes available.

PAY-001 records `payment_mono_chast_status = 0` on production (production is
blocked pending the monobank `point_id`), so as written PUMB would not appear
even with a correct token — acceptance criterion 6 fails. Independently of
today's value, this is the wrong shape: switching monobank off later must not
silently disable PUMB.

Fix without reintroducing the round-1 double-computation: compute the payable
total once per request, above both gates, and pass it into each; or have
`pay001MonoChastGate()` populate `payable` before its `configured` early return.
The owner should also confirm the live `payment_mono_chast_status` value, since
it decides whether this is a latent or an immediate failure.

## Medium

**N3 · WP2 confirm gate is inserted before the language load.**
The live `confirm()` loads its language file on the first line of the body; the
new early return is inserted immediately after the opening brace, so
`$this->language->get('error_unavailable')` runs before the file is loaded and
returns the raw key. The gate still fails closed — only the message is wrong.
Move the insertion after the `$this->load->language(...)` line.

**N4 · Five terminal `str_replace()` calls have no anchor count.**
`option.pay001Credit`, `selected.pay001Credit`, `!desiredOption.pay001Credit`,
`group.pay001_total`, `pay001Credit: true, pay002Credit` are rewritten with
`str_replace`, which cannot fail and cannot report drift — the C2 discipline the
rest of the runner now follows. Verified they currently match the live file
(2 / 1 / 1 / 7 occurrences respectively), so the edits do land today. The
`option.pay001Credit` rewrite is blanket across all 7 occurrences, including two
that earlier `rx()` calls had already converted, producing a redundant
`((a || b) || b)`. Wrap them in the counted helper.

## Low

- **N5** — no `pay002_credit_gate` is exported to the template, so a PUMB
  customer below the minimum sees no "unavailable" row or reason, unlike mono.
  Not required by the handoff; worth a follow-up.
- **N6** — with `fail()` now throwing, any preflight failure before the `try`
  block surfaces as an uncaught `RuntimeException` with a stack trace instead of
  the clean `ERROR: …` line C1 asks for. Wrap the body or catch at top level.

## Conventions

| Conv. | WP1 | WP2 |
|---|---|---|
| C1 file exists | ok (message shape, N6) | ok (message shape, N6) |
| C2 anchor pre-check | ok | ok for `rx()`; gap for the five `str_replace` (N4) |
| C3 backup | ok | ok |
| C4 `php -l` + restore | ok — three PHP files linted, restore reachable | ok — restore reachable; cannot catch N1 |
| C5 idempotent marker | ok | ok, plus a correct WP1-before-WP2 ordering assert |
| C6 DB + rollback SQL | ok — header and code now agree | n/a, `database_touched=no` |
| C7 self-delete | ok | ok |

Risky zones: checkout · payment · order flow · DB (WP1). Both patches leave
`payment_pumb_credit_status` disabled and do not touch production callback
credentials.

## Rollback (unchanged from round 1)

Reverse order — WP2, then WP1, from
`_patch_backups/<PATCH_ID>-<ts>/`. WP1 additionally needs its two settings rows
deleted. Fastest kill switch with no rollback: set
`payment_pumb_credit_status` back to disabled in admin. Both runners self-delete
on success; a repeat run needs a fresh upload.

## Smoke after a corrected deploy

`bs-checkout-smoke` (full 11 steps), then `bs-deploy-verify`.
`bs-seo-risk-gate` on the WP1 preview route.

## Returned to

The same executor. No parallel patch author for PAY-002 this round.
