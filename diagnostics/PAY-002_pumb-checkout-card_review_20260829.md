# PAY-002 — patch review (WP1 + WP2), pre-deploy gate

Date: 2026-08-29
Reviewer: Claude (chat), read-only. Nothing was run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-preview-token-gate_20260828.php`,
`patches/PAY-002_pumb-checkout-card_20260828.php`,
`diagnostics/PAY-002_pumb-checkout-card_report_20260828.md`,
`handoffs/handoff_PAY-002_pumb-checkout-card-token-gate_20260828.md`.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz` (newest owner drop),
files `extension/pumb_credit/**` and
`catalog/view/template/checkout/payment_method.twig` extracted read-only.

## Verdict

**Return for changes.** Six blocking defects. WP1 cannot execute at all; the
WP1 → WP2 sequence the handoff mandates was never exercised and produces a fatal
redeclaration; and neither runner restores files when its own `php -l` gate fails.

---

## Blocking

| ID | Where | What is wrong | Canon |
|---|---|---|---|
| B1 | WP1, `confirm gate` anchor line | The anchor is a **double-quoted** PHP string containing `$this->config->get(...)` and `$orderId`. PHP interpolates `$this` at runtime in global scope → `Error: Using $this when not in object context`. The runner dies on that line, before backups and before any write. WP1 cannot run in its current form. Fix: single quotes / nowdoc, as every other anchor in the same file already uses. | C2 — anchor is never even evaluated |
| B2 | WP1, `INSERT INTO … setting` | Rows are inserted with `key='preview_token'` and `key='public'`, but OpenCart stores the **full** key. Precedent in the same extension: `PAY-004` inserts `payment_pumb_credit_terms`, and the admin form fields are `payment_pumb_credit_*` (verified: admin controller `$keys` list + `payment_pumb_credit_test_mode` field name). `config->get('payment_pumb_credit_preview_token')` will never read these rows, so the gate can never open; the rows become orphans once the owner saves the admin form. The catch-block cleanup deletes the short keys, while the patch header promises to delete the long ones — header and code disagree. | C6 — rollback SQL must match what the patch actually writes |
| B3 | WP1 + WP2, `extension/pumb_credit/catalog/controller/payment/pumb_credit.php` | Both runners insert `private function pay002Available()` into the same class. Running WP1 then WP2 — the mandated order — yields `Cannot redeclare PumbCredit::pay002Available()`, a compile-time fatal. The fixture run in the report exercised WP2 **alone** on an unpatched fixture (the report states WP1 was not executed), so this path has no evidence behind it. | S2 — the two work packages must compose |
| B4 | WP1 and WP2, `lint()` | `lint()` → `need()` → `fail()` → `exit(1)`. `exit` does not throw, so the `catch (Throwable)` restore block never runs. In WP2 all three files are written **before** the lint loop, so a lint failure leaves `catalog/controller/checkout/payment_method.php`, `payment_method.twig` and the PUMB controller half-patched on production, with no restore and no self-delete. Combined with B3 this is the realistic failure path, not a theoretical one. | C4 — `php -l` gate with restore-on-fail |
| B5 | WP2, twig | Handoff §4 WP2.2 is not implemented. The drawer's provider head is hardcoded monobank (`payment_method.twig:264`, `<strong>Покупка частинами monobank</strong>`, `pay001-mono-label.png`) and the `pay001-checkout-provider--soon` PUMB article with `<em>СКОРО БУДЕ</em>` (line 268) is unconditional. No WP2 anchor touches either. Selecting PUMB therefore renders a card branded «Покупка частинами monobank» carrying PUMB terms, plus a PUMB card still reading «СКОРО БУДЕ». Acceptance criterion 6 is unreachable. The report records the retained card as a fact without flagging it as a gap. | S1 — handoff scope |
| B6 | WP2, `rx()` | `preg_replace($p, $r, $s, 1, $n)` with `need($n === 1, …)`. With the limit set to 1, `$n` can only ever be 0 or 1 — the assertion proves "at least one match", never "exactly one". C2 is not implemented in WP2. WP1's `replaceOnce()` does it correctly with `substr_count()`. On the current live twig the anchors happen to be unique (`option.monoOptions` occurs 3×, but each anchor is specific), so this is a latent drift hazard rather than a present mis-edit — it still has to be fixed before deploy. | C2 — anchor pre-check |

## High (must fix, not fatal on their own)

| ID | Where | What is wrong |
|---|---|---|
| H1 | WP2, twig | `normalizeChoice()` gains a `pumb_credit` branch, but `matchesChoice()` (~line 102) does not: `choice === 'pumb_credit'` falls through every branch, so a restored or pending PUMB selection never matches its own code. Same omission at line 280 (`creditIntent = normalizeChoice(...) === 'mono_chast'`) and in the blocked-credit fallback row (lines 283–294), which hardcodes `mono_chast.mono_chast_3` and `monoOptions: []`. |
| H2 | WP2, PUMB `index()` | Emits `id="button-confirm"`, colliding with the theme's own confirm button id on the same page. On success it calls `location.reload()` instead of following the success redirect; on `j.error` it renders nothing, so a customer whose term is rejected sees a dead button. Acceptance criterion 9 cannot be observed from the UI. |
| H3 | WP2, `pay002PumbGate()` | Re-runs `prepareCouponTotal()` and a second full `getTotals()` pass in the same request that `pay001MonoChastGate()` already completed. This is money-affecting code and coupon/threshold refresh is precisely where the post-cutover ST-2c defect lived. Reuse the already-computed `$pay001_gate['payable']` instead of recomputing totals a second time. |
| H4 | WP1, admin twig | The `payment_pumb_credit_public` checkbox has no hidden `value="0"` companion. An unchecked checkbox is not posted, so OpenCart keeps the previous value — once «показувати ПУМБ усім клієнтам» is on, it cannot be switched back off from the form. This is the switch that exposes PUMB to every customer. |

## Medium

| ID | Where | What is wrong |
|---|---|---|
| M1 | WP1 | Only the catalog controller is linted. The admin controller and the model are written but never checked. |
| M2 | WP2 | `php -l` is run on `payment_method.twig`. A file with no PHP tags always lints clean, so this line is a false assurance, not a check. |
| M3 | both | `file_put_contents()` return values are never checked — a failed or partial write is silent. |
| M4 | WP2 | PUMB options are given `pay001Credit: true`, routing PUMB through monobank-specific gate messaging (`bsPay001SetCreditGate`, `pay001GateMessage`). |
| M5 | WP2 | The term regex hardcodes `([345])` while `payment_pumb_credit_terms` is treated as the source of truth everywhere else. |

## Conventions

| Conv. | WP1 | WP2 |
|---|---|---|
| C1 file exists | ok | ok |
| C2 anchor pre-check | ok (`substr_count`), but B1 makes one anchor unusable | **fail** (B6) |
| C3 backup | ok | ok |
| C4 `php -l` + restore | **fail** (B4), partial coverage (M1) | **fail** (B4), false check on twig (M2) |
| C5 idempotent marker | ok | ok |
| C6 DB + rollback SQL | **fail** (B2) | n/a — `database_touched=no`, correct |
| C7 self-delete | ok | ok |

Risky zones touched: checkout · payment · order flow · DB (WP1). Both patches
correctly leave `payment_pumb_credit_status` disabled and do not fill production
callback credentials.

## What the report does and does not prove

The fixture evidence is real and useful: WP2 alone applies cleanly to the
2026-08-28 backup and is idempotent on a second run. It does **not** cover the
WP1 → WP2 sequence (B3), the DB write (B2), or any restore path (B4). The
report's own risk list should say that the sequence is untested rather than
listing only production gates.

Credential field names in both gates were verified against the live admin
controller and are correct: `api_base`, `oauth_url`, `oauth_username`,
`oauth_password`, `point_of_sale_code`, `partner_name`.

## Rollback (restated, for when a corrected pair is deployed)

Reverse order — WP2, then WP1.

- WP2: restore the three files from `_patch_backups/PAY-002_pumb-checkout-card_20260828-<ts>/`. No DB change to undo.
- WP1: restore four files from `_patch_backups/PAY-002_pumb-preview-token-gate_20260828-<ts>/`, then delete the two settings rows it inserted — **using the key form the corrected patch actually writes**.
- Fastest kill switch, no rollback needed: set `payment_pumb_credit_status` back to disabled in admin.
- C7: both runners self-delete on success. A repeat run needs a fresh upload.

## Smoke after a corrected deploy

`bs-checkout-smoke` (full 11 steps — purchase flow), then `bs-deploy-verify`.
`bs-seo-risk-gate` on the WP1 preview route before it goes up.

## Returned to

The executor that authored these files. Do not open a parallel patch author for
PAY-002 this round.
