# PAY-002 WP3 — patch review round 3: SHA gate replacing the Node parse gate

Date: 2026-08-30
Reviewer: Claude (chat), read-only on the repository. The runner was executed
only in an isolated sandbox fixture, never against production.
Inputs: `patches/PAY-002_pumb-checkout-card-ui-hotfix_20260830.php` (rev 17:34),
`diagnostics/PAY-002_pumb-checkout-card-ui-hotfix_report_20260830.md` (rev 18:57),
`booster-debug-files.tar.gz` (owner-supplied live Twig), rounds 1–2.

## Verdict

**Deploy OK.** Every claim in the report was reproduced independently, including
both hashes, the JavaScript parse, and both failure paths. This is the strongest
evidence position of the whole task.

## Independently reproduced

The owner-supplied archive holds one file,
`catalog/view/template/checkout/payment_method.twig`. Working from it in an
isolated fixture (a scratch `public_html` with a stub `config.php`):

| Check | Result |
|---|---|
| `BEFORE_SHA256` vs the owner-supplied live Twig | `0d18197b…e9b2` — **match** |
| Runner applied to that file | `sha_gate=ok before=0d18197b after=7fde759a`, `twig_assert=ok`, `done=ok`, `self_delete=ok`, exit 0 |
| Resulting file hash vs `AFTER_SHA256` | `7fde759a…006e` — **match** |
| Backup contents | hashes to `0d18197b…e9b2` — the rollback copy is byte-exact |
| Generated `<script>` blocks | exactly 1 |
| `node --check` on the generated script | exit 0 |
| `php -l` on the runner | clean |

Structural invariants in the generated Twig: `id: 'credit_installments'` ×1,
`function providerCard(` ×1, `СКОРО БУДЕ` ×1, `data-pay002-provider` ×1,
`hasActiveCreditOption` present, `(isPumb ? '' :` **absent**, and
`savePayment(code, 'Сплатити частинами'` still ×1 — the provider-specific payment
code is still what gets posted, which was the main risk of the merged row.

## Failure paths reproduced

- **Repeat run with the marker present** → `already_applied=yes`, exit 0, nothing
  touched.
- **Marker removed, run against the already-patched file** → refused with
  `ERROR: live source SHA256 mismatch expected=0d18197b… actual=7fde759a…; write skipped`,
  exit 1, file unchanged, runner retained for a retry, and — worth noting — **no
  backup directory created**, because the gate fires before the backup step. No
  litter from a refused run.

## Round-2 findings — status

| ID | Round-2 note | Status |
|---|---|---|
| 1 | Node required on a host that has none | **closed** — no `exec()` and no parser dependency remains in the runner; the gate is `hash('sha256', …)` + `hash_equals()` |
| 2 | `$status` pre-initialised to 0 before `exec()` | moot — no `exec()` left |
| 3 | Suppressed blocked row hides mono's reason | unchanged and accepted; unreachable while both providers share min 500 / max 500000, and owned by PAY-005 |
| P2 (optional structural checker) | correctly **not** shipped — it would have risked false failures on the regex literals in this file |

## Conventions

| Conv. | Status |
|---|---|
| C1 file exists | ok, plus the WP2-composition preflight |
| C2 anchor pre-check | ok — exact counts on five anchors, now backed by a whole-file hash |
| C3 backup | ok — verified byte-exact against the original |
| C4 lint/parse + restore | satisfied in substance and **stronger than a lint**: the written file is proven byte-identical to an artifact parsed with `node --check` locally, and a mismatch restores |
| C5 idempotent marker | ok — reproduced |
| C6 DB | n/a, `database_touched=no` |
| C7 self-delete | ok on success, retained on failure — both reproduced |

Risky zone: checkout rendering only. No controller, gate, credential,
`confirm()`, `mono_chast`, CSS, `.htaccess`, `checkout.twig` or database change.

## The tradeoff, stated plainly

The BEFORE gate binds this patch to one exact byte-image of the live template. If
anything edits `payment_method.twig` on production between the owner's `tar` and
the run — another patch, an admin theme edit, a manual fix — the runner refuses
and new hashes plus another review are required. That is the correct default
here, and it is documented in the report. Practical consequence: run it soon, and
do not deploy any other checkout patch in between.

## Housekeeping

`booster-debug-files.tar.gz` sits in the repository root under the generic name
the AGENTS.md live-source instruction produces, so the next live-source drop will
overwrite it. The provenance of `BEFORE_SHA256` depends on that file — rename it
to something dated (e.g. `live-snapshots/20260830_payment-method-twig.tar.gz`)
so the evidence stays traceable after the next drop.

## Перед запуском

Keep `payment_pumb_credit_status` disabled until the runner has succeeded and the
cache is cleared. Upload only this runner. Do not touch the template first.

Expected output — the parser line is gone, the hash line replaces it:

```
sha_gate=ok before=0d18197b after=7fde759a
twig_assert=ok
backup=…
changed=catalog/view/template/checkout/payment_method.twig
database_touched=no
done=ok
self_delete=ok
cache cleared
```

`live source SHA256 mismatch` means production is not the file that was verified
— stop, send the full error, do not bypass. Any other failure means the Twig was
restored; re-upload before retrying (C7).

## Rollback

Restore `catalog/view/template/checkout/payment_method.twig` from
`_patch_backups/PAY-002_pumb-checkout-card-ui-hotfix_20260830-<ts>/` and clear the
cache. Never from a WP1/WP2 backup. Kill switch unchanged: disable
`payment_pumb_credit_status` in admin.

## Смоук після

The report's ten-step QA stands. The two additions from rounds 1–2 are still not
in it and still matter:

- complete an order with a PUMB term selected and confirm the posted payment code
  is the PUMB one;
- run the non-credit half of `bs-checkout-smoke` (Hutko, COD, IBAN) —
  `flattenPaymentMethods()` builds every payment row, not only the credit ones.

Then `bs-deploy-verify`.
