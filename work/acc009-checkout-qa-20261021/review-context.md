# ACC-009-QA bounded follow-up

Owner QA1,2,4 accepted on 2026-10-21. QA3 requested visible hints, immediate
middlename readiness refresh, correct saved-courier return behavior, removal of
the address-confirmed success notice, smaller desktop gap, mobile hint +20%,
and placeholder "Для адресної доставки". Owner has NOT applied/released these
new bytes. The prior successful parent Apply release is consumed. Do not claim
the owner's real-order QA separately proves suppression of draft notifications.

## Owning causes and change map

The admitted R5 generated frontend files match today's accepted parent after
hashes exactly. No new owner archive was needed. Root causes in those files:

- checkout-reskin.js:1057-1078: receiver save acknowledges the view and updates
  receiver status, but never calls the existing customerSaved/readiness renderer.
  Its input handler already owns 400ms debounce and blur persistence. Keep that
  single write path; repaint local hints on input/change/blur and after success.
- acc009-checkout.js:95-98 and checkout.twig:932-942: required middlename depends
  on the hidden shipping quote code. During saved-address transitions that code
  may still describe the previous noncourier method. One courierRequired helper
  now uses the selected canonical NP tuple (or the actual guest/manual editor),
  with hidden-code fallback only when canonical data is not yet available.
  Existing address loading/stale gate and authoritative server guards remain.
  Starting metadata loading also disables the old CTA immediately.
- acc009-checkout.js:302: explicit finish success creates an orange live status
  "Адресу підтверджено." next to the CTA. Remove that success message; retain
  failure/uncertain statuses and the actual readiness hint.
- acc009-checkout.js:332 and checkout-reskin.js:885 own the placeholder. Both
  paths now say "Для адресної доставки"; the required label/aria flag still
  changes with courier selection.
- Live public boostershop-ds.css:6559-6563 imposes muted gray,12.5px; Bootstrap
  mb-3 retains a16px bottom margin. Lines6615-6619 impose12px tail gap;6657
  adds2px before confirm. Prior patch history: RD-13 reskin round3 owns the
  muted hint; round4 owns tail gap; round6 and RD-13.1D/E add responsive/adoption
  and overflow behavior. New hint markup drops text-muted/mb-3 entirely, using
  its own component class/color/margin. Blocked hint uses existing danger token
  and600 weight; mobile <=900px15px (exact20% over12.5px). Normal hint12.5px
  desktop. A single documented desktop-only row-gap6px in the owning checkout
  inline component style halves the legacy12px gap (plus retained2px margin).

The desktop gap rule is a narrow, explicitly justified component override:
the page already owns its component styles in checkout.twig. Keep shared DS
bytes and the global header/cache token unchanged; avoid a shared-file edit and
new source collection solely for checkout presentation. There is no additional
!important, timer, absolute/fixed positioning or unlabelled pixel constant.
Existing unrelated signatures in full sources are unchanged context. Both
changed JS reference tokens are read/shape-validated and replaced wholesale;
idempotent runner never changes them again.

## Impact/dependencies

Three existing targets only: acc009-checkout.js, checkout-reskin.js,
checkout.twig. Read-only dependencies pinned in the runner: five unchanged
checkout JS/Twig components, common.js native Chain, public Bootstrap/DS/
typography CSS (9 files total). No shared JS/CSS, provider, model, mail, events,
database, server config, route or H1 notification logic is written. Accepted
parent receipt and exact source identities establish baseline only; this runner
still refuses target/dependency drift on the owner's host.

## Evidence and limits

- browser-result.json: 13 meaningful transition/interaction checks. Actual
  admitted generated Twig3.18, frontend JS and PHP register/receiver/address
  endpoints plus actual Request.clean/declaration helper execute locally.
  Loopback HTTP, registry/session/directory services, quote/payment JSON,
  CAPTCHA and provider side effects are fictional. One native COD template
  reaches one fictional provider after a slow receiver save and one accepted
  declaration. No bank/order/CRM/Telegram production effect is certified.
- Reproduced old-source defect before changing files: clearing middlename left
  old ready text and enabled CTA. New code blocks immediately, saves once via
  400ms debounce/blur, then enables automatically after valid input. Saved
  warehouse and postomat -> courier, slow metadata, stale quote code and early
  click during slow save are discriminating tests. Guest autosave is covered.
- Guest address-finish test explicitly models directory selection by filling
  the existing hidden refs from fictional selected data-ref. The older fixture
  seeded data-ref only. This does not prove legacy guest snapshot hydration;
  it tests the finish success-message change after valid directory selection.
- Actual public CSS obtained via anonymous GET today. Real component scripts
  adopt native Twig DOM at412/768/1280 widths. Each verifies font,color,weight,
  gap, long multi-line hint/no overflow, disabled/focus/hover/keyboard active.
  Screenshots were inspected. Fictional labels, header/footer omission, local
  font fallback and fake payment/CAPTCHA remain limits; owner visual QA remains.
- runner-result.json: native PHP8.0 on isolated fictional filesystem: apply,
  all hashes, backup, self-delete, idempotency, drift stop, all-backup preflight,
  partial rollback, and an explicitly injected second-target lint failure with
  restoration. PHP -l is not a Twig compiler. Twig is rendered in native local
  fixtures; production compile/cache behavior needs owner execution/QA.

Rollback all three targets from the exact printed backup after checking all
target and backup identities, including partial before/after states. Roll back
this follow-up before the parent C1+H1 runner. No business-data undo. Preserve
all parent receipts, backup and consumed release facts.

Next gate: independent ready review binds new runner and Claude-authored PS
hashes after Codex checks the command packet; only then request owner deployment.

Command author request2e348634-a4f9-4606-88a8-2e39789fdba3 completed separately.
Its original response is retained. Codex repaired the draft BEFORE this ready
review: normal SCP transport instead of base64 upload, literal same-shell
source_apply && cleanup_apply, finite private cache-list cap before any deletion,
exact historical warning allowlist, packet-bound state and strict wrapper rows.
No final executable bytes were reviewed earlier. command-repairs.diff preserves
the derivation. Native PS5.1 parse and actual local runner dry/apply receipt
helpers passed; four incomplete/contradictory receipts refused. All four Bash
step bodies passed parse-only bash -n. The exact extracted cleanup function
removed2 fictional PHP files while preserving directories/nonPHP, and refused
10001-file input before deleting any; no full owner packet or SSH was executed.
