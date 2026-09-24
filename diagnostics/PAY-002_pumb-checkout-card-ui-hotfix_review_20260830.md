# PAY-002 WP3 — patch review: PUMB checkout card UI hotfix

Date: 2026-08-30
Reviewer: Claude (chat), read-only. Nothing run, uploaded, committed or deployed.
Inputs: `patches/PAY-002_pumb-checkout-card-ui-hotfix_20260830.php`,
`diagnostics/PAY-002_pumb-checkout-card-ui-hotfix_report_20260830.md`,
owner production screenshots (2026-08-30, PUMB visible under preview token).
Live evidence: `backup-8.28.2026_13-26-46_boosters.tar.gz` (pre-WP2 twig, the base
the WP2 output is derived from), read-only.

## Verdict

**Deploy OK; є неблокуючі зауваження.** The diagnosis matches the screenshots,
the fix is at the right layer, and the three re-typed functions were diffed
against the live originals — nothing is dropped. Four non-blocking notes, one of
which the owner should act on during the run itself.

`(scope S1 unverified — WP3 has no handoff; scope taken from the production
observation and the round-4/5 reviews.)`

## Diagnosis — confirmed

The screenshots show two independently selectable «Сплатити частинами» rows, the
monobank drawer still carrying the `СКОРО БУДЕ` PUMB card, and a second PUMB
drawer below it. That is exactly what the WP2 code produces: the controller
returns `mono_chast` and `pumb_credit` as two payment groups,
`flattenPaymentMethods()` turns each into its own credit option, and the WP2
drawer appended the "soon" card on any non-PUMB render without checking whether a
real PUMB card existed. Nothing here points at the gate, the token, or the DB.

## What was verified against the live file

- **Identifiers.** Every function the rewrites call exists in the live template:
  `paymentMatchesChoice` (the live name — not `matchesChoice`), `normalizeChoice`,
  `escapeHtml`, `pay001GateMessage`, `pay001Money`, `pay001SyncPhone`,
  `window.pay001PaymentsWord`, plus the `booster_category` and
  `pay001_from_modal` fields. No ReferenceError risk from the rewrite.
- **`flattenPaymentMethods()` fidelity.** Diffed against the live original: both
  non-credit branches (`group.option` with `booster_category`, and `group.code`)
  are reproduced verbatim, as is the sort/preferred selection. The only changes
  are intentional — one merged `creditOption`, per-provider arrays, totals and
  preferred values, and provider-prefix filtering so a group cannot absorb the
  other bank's codes.
- **Dropped fields are dead.** The merged option no longer carries `preferred` or
  `id: 'mono_chast'`. `option.preferred` had exactly one reader — the old drawer
  line the hotfix replaces. `option.id` has no reader at all; the radio id is
  built from the loop index. Safe.
- **`findPaymentOption()` fidelity.** Same shape, extended to search both term
  arrays and accept either credit flag.
- **The submitted code is unchanged.** The term buttons still carry
  `data-pay001-code`, and the click handler still ends in
  `savePayment(code, 'Сплатити частинами', …)` — the hotfix only narrows the
  summary update from `$drawer` to `$provider`. Selecting a PUMB term still posts
  `pumb_credit.pumb_credit_<n>`, mono still posts its own code. This was the main
  risk in a merged row and it is handled correctly.
- **The merged option flows through the existing render path.** WP2's blanket
  `option.pay001Credit → (option.pay001Credit || option.pay002Credit)` means the
  row renders as a credit row when either flag is set, which is what the merged
  option produces in all three configurations.
- **Anchors are unique.** All four — three function bodies and the six-line click
  handler block — occur exactly once in the live template. The runner counts them
  with `preg_match_all` / `substr_count` before replacing, so drift fails closed
  before any write.

## Non-blocking notes

1. **The runner has no parse gate for what it actually writes.** C4 normally means
   `php -l` with restore-on-fail; the target here is Twig with a large embedded
   `<script>`, so no PHP lint applies and none is claimed. But the post-write
   assertions are string-presence checks, not a parse — a malformed emitted
   `<script>` would still print `twig_assert=ok`, and the restore path could never
   fire for the one failure mode that matters on a JS-driven checkout. In
   practice the output is deterministic given matching anchors and the executor
   parsed it in the fixture, which is why this is not blocking. If `node` exists
   on the host, a `node --check` of the extracted script block before the marker
   is written would close it properly.
2. **A latent duplicate-row path remains.** When monobank is configured but
   unavailable, the template still pushes its own blocked credit row
   (`gate.configured && gate.reason`). If PUMB is simultaneously available, the
   customer sees the merged PUMB row plus a second, disabled «Сплатити
   частинами» row. Today both providers share min 500 / max 500000 and the same
   preorder rule, so their gates move together and the case cannot occur — it
   opens the moment `payment_pumb_credit_min_total` and
   `payment_mono_chast_min_total` diverge, or one provider is switched off alone.
   This belongs with **PAY-005**, which already owns the unavailable-row rework.
3. **The non-selected provider card shows no active term.** `is-active` is set
   only on the selected provider, so the other bank's card renders its numbers
   with no highlighted button. Defensible — it makes the selection unambiguous —
   but confirm it reads as intentional during QA rather than as a broken pill.
4. **Cache.** The change is invisible until the compiled template cache is
   dropped. The report's one-liner globs `DIR_CACHE . "template/*"` at one level
   only. If the drawer still looks unchanged after Ctrl+F5, clear the cache from
   the admin panel before concluding the patch failed.

## Conventions

| Conv. | Status |
|---|---|
| C1 file exists | ok, plus a WP2-composition preflight that refuses to run before WP2 |
| C2 anchor pre-check | ok — exact counts on all four anchors |
| C3 backup | ok, timestamped, path printed |
| C4 lint + restore | restore path ok; parse gate not applicable to Twig and not substituted (note 1) |
| C5 idempotent marker | ok — `.pay002-ui-hotfix-marker` |
| C6 DB | n/a, `database_touched=no` |
| C7 self-delete | ok on success, retained on failure |

Risky zone: checkout rendering. No controller, gate, credential, `confirm()`,
`mono_chast`, CSS, `.htaccess` or DB change.

## Перед запуском

Keep `payment_pumb_credit_status` disabled until the runner has succeeded and the
cache is cleared. Upload only this runner.

Expected output: `twig_assert=ok`, `backup=…`,
`changed=catalog/view/template/checkout/payment_method.twig`,
`database_touched=no`, `done=ok`, `self_delete=ok`. Anything else means it
restored — do not re-run without re-uploading (C7).

## Rollback

Restore `catalog/view/template/checkout/payment_method.twig` from
`_patch_backups/PAY-002_pumb-checkout-card-ui-hotfix_20260830-<ts>/` and clear the
cache again. Do **not** roll back from a WP1 or WP2 backup — that would also undo
the deployed PAY-002 work. Kill switch unchanged: disable
`payment_pumb_credit_status` in admin.

## Смоук після

The report's eight-step QA is right; add two things it does not cover:

- with a PUMB term selected, complete the order and confirm the posted payment
  code is the PUMB one — the merged row is exactly where a wrong code would hide;
- the regression half of `bs-checkout-smoke` on the non-credit methods (Hutko,
  COD, IBAN), since `flattenPaymentMethods()` — which builds every payment row,
  not only the credit ones — was replaced wholesale.

Then `bs-deploy-verify`.
