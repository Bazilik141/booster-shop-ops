# PAY-002 WP3 — patch review round 2: PUMB checkout card UI hotfix

Date: 2026-08-30
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-checkout-card-ui-hotfix_20260830.php` (rev 16:51),
`diagnostics/PAY-002_pumb-checkout-card-ui-hotfix_report_20260830.md` (rev 16:52),
round 1: `diagnostics/PAY-002_pumb-checkout-card-ui-hotfix_review_20260830.md`.
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz`, read-only.

## Verdict

**Deploy OK; є неблокуючі зауваження.** Both round-1 notes that were actionable
are closed, and the parse gate is real. One operational precondition must be
checked before the runner is uploaded, because the patch now refuses to apply
without it.

## Answers to the five verification points

1. **Order of operations — confirmed.** Inside the inner `try`:
   `writeChecked()` → re-read from disk → `parseTwigJavaScript($written, …)` →
   the six structural assertions → `twig_assert=ok` → marker → `done=ok`.
   Self-delete sits after the inner `try`. The parse therefore gates everything
   that follows it.
2. **Failure and absence both restore — confirmed.** Every exit from
   `parseTwigJavaScript()` other than success calls `fail()`, which throws; the
   inner `catch` does `@copy($backupPath, $path)` and rethrows; the outer handler
   prints one `ERROR: …` line and exits 1. The marker is written only after the
   assertions, so it cannot exist on a failed run, and `unlink(__FILE__)` is never
   reached — the runner is retained for a retry. A missing parser is treated the
   same way as a parse error, which is the correct choice.
3. **The active-credit guard behaves as described — confirmed.** It scans the
   built options for any `pay001Credit || pay002Credit` and suppresses the
   `gate.configured && gate.reason` push only when one is found. With no active
   credit provider the blocked row is still pushed, unchanged. Anchor
   `if (gate.configured && gate.reason) {` occurs exactly once in the live
   template.
4. **Scope — confirmed.** One target, `catalog/view/template/checkout/payment_method.twig`.
   The only other writes are the marker and the temporary extracted `.js`, both
   under `_patch_backups/`. No controller, gate, credential, `confirm()`,
   `mono_chast`, CSS, `.htaccess`, `checkout.twig` or database touch. The single
   grep hit for a "forbidden" term is the `config.php` presence check.
5. **PHP 8.0 — confirmed.** No `readonly`, `enum`, `never`, first-class callable
   syntax or any other 8.1+ construct in the runner.

## Independently verified, beyond the five points

- **The parse gate's `count === 1` premise holds.** The live template contains
  exactly one `<script>` block.
- **The extracted block is parseable JavaScript.** All four Twig tags inside it
  (`{{ language }}` in four AJAX URLs) sit within single-quoted JS string
  literals, so they are opaque to a JS parser. Extracted the block from the live
  template and ran `node --check` on it: clean, exit 0. The gate will not
  false-fail on the base file.
- **Round-1 note 2 is closed** by the `hasActiveCreditOption` guard — the latent
  "merged PUMB row plus a separate blocked mono row" path is gone.
- **Round-1 note 4 is closed** in the report: QA step 10 now tells the owner to
  clear the template cache from admin before concluding the hotfix did not apply.

## Non-blocking notes

1. **The runner now hard-requires Node on the production host, and that is
   unverified.** Candidates are `/usr/bin/node`, `/usr/local/bin/node`,
   `/opt/cpanel/ea-nodejs20/bin/node`, then bare `node` / `nodejs` from `PATH`.
   A cPanel box with, say, `ea-nodejs18` and nothing on `PATH` matches none of
   them. The failure is safe — Twig restored, no marker, exit 1 — but it leaves
   the owner with the broken checkout UI still live and a patch that will not
   apply. Check first, from `~/public_html`:

   ```
   php -r 'foreach(["/usr/bin/node","/usr/local/bin/node","/opt/cpanel/ea-nodejs20/bin/node","node","nodejs"] as $c){$o=[];$s=0;exec(escapeshellarg($c)." --version 2>&1",$o,$s); if($s===0){echo "OK ".$c." ".implode("",$o).PHP_EOL; exit;}} echo "NO NODE FOUND".PHP_EOL;'
   ```

   This mirrors the runner's own resolution order, so a green line here means the
   gate will find a parser. `NO NODE FOUND` means the runner must gain another
   path (a real one from the host) before it is uploaded.
2. **`$status` is initialised to `0` before each `exec()`.** Any path where
   `exec()` returns without setting it would read as a successful parse and print
   `js_parse=ok` having checked nothing. Evidence that this is not live risk: the
   production WP1/WP2 run printed `php_l=ok`, which requires a working `exec()`.
   Worth tightening only if the runner is reused elsewhere.
3. **The suppressed blocked row also suppresses mono's reason.** When mono is
   blocked and PUMB is active, the customer sees the working PUMB row and no
   explanation for mono. Correct for now and unreachable today — both providers
   share min 500 / max 500000 and the same preorder rule — and per-provider
   reasons are PAY-005's job. Note that `bsPay001SetCreditGate()` still receives
   the mono gate object, so a mono-specific warning may still render elsewhere on
   the page under the same divergence condition. Same trigger, same owner.

## Conventions

| Conv. | Status |
|---|---|
| C1 file exists | ok, plus a WP2-composition preflight |
| C2 anchor pre-check | ok — exact counts on all five anchors |
| C3 backup | ok, timestamped, path printed |
| C4 parse gate + restore | **now satisfied in substance** — `node --check` on the generated script, restore-on-fail reachable |
| C5 idempotent marker | ok |
| C6 DB | n/a, `database_touched=no` |
| C7 self-delete | ok on success, retained on failure |

Risky zone: checkout rendering only.

## Перед запуском

1. Run the Node check above. Do not upload the runner until it prints `OK …`.
2. Keep `payment_pumb_credit_status` disabled until the runner has succeeded and
   the cache is cleared.
3. Expected output: `js_parse=ok parser=…`, `twig_assert=ok`, `backup=…`,
   `changed=catalog/view/template/checkout/payment_method.twig`,
   `database_touched=no`, `done=ok`, `self_delete=ok`, then `cache cleared`.
   Anything else means the Twig was restored — re-upload before retrying (C7).

## Rollback

Restore `catalog/view/template/checkout/payment_method.twig` from
`_patch_backups/PAY-002_pumb-checkout-card-ui-hotfix_20260830-<ts>/` and clear the
cache. Never from a WP1/WP2 backup. Kill switch unchanged: disable
`payment_pumb_credit_status`.

## Смоук після

The report's ten-step QA is sound. The two additions from round 1 still stand and
are not yet in it:

- complete an order with a PUMB term selected and confirm the posted payment code
  is the PUMB one — the merged row is where a wrong code would hide;
- run the non-credit half of `bs-checkout-smoke` (Hutko, COD, IBAN), since
  `flattenPaymentMethods()` builds every payment row, not only the credit ones.

Then `bs-deploy-verify`.
