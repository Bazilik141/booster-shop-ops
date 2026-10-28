# RD-18 WP4 — order detail polish (seven owner QA requests)

Date: 2026-10-28. Executor: Codex. Production is still WP3. WP4 is not deployed.
RD-17 is owner accepted. RD-18 final owner QA remains open. No Git, Notion or
dashboard mutation, agent SSH/key/server action, DB write or real NP call.

## Outcome

1. Hide the invoice row when native invoice_no is absent; retain genuine invoices.
2. Replace text_payment in both current UK bank language files with exactly:
   `Після зарахування оплати ми швиденько зберемо замовлення`.
   Owner delegated architecture: future instructions only; historical stored
   comments and their display remain unchanged. Bank controller/writers unchanged.
3. Compact delivery reads stored order address/NP labels, house and flat. It
   preserves the stored recipient/telephone bucket and does not consult today's
   profile, address book or carrier directory. Unrecognized point descriptions
   stay intact; non-NP delivery retains the native address-format path.
4. NP logo in delivery heading, for NP orders only.
5. Real `Трекінг` + NP-logo button at the right of the status heading, outside
   the disclosure; fixed official prefilled URL, noopener/noreferrer.
6. Compact NP state/TTN outside native details, visible while history is folded.
   Remove redundant title/cache-age hint/manual refresh. One optional read on
   page load, no polling or disclosure-triggered repeat. Same backend TTL/caps.
7. Three explicit centered support lines with support-bot link and public
   telephone `+38 063 674 32 52` / `tel:+380636743252`.

## Root causes and source ownership

Current WP3 order_info.twig:81 always prints the invoice caption with a TBC
fallback. Lines24–33 place NP state/link/refresh under closed details; its JS
waits for the first open. The delivery markup has no carrier image. Line86
puts all support text in one anchor. Existing rd18Delivery (order.php:600)
honors the country-format placeholders, which can omit available address_1
and the saved NP point. Fresh owner shape evidence confirms this combination
locally; real address values, names, phone and order ID are not exported to review.

UI source history: WP2 introduced the owned RD18_ORDER_LIGHT_A block; WP3 edited
it in place. Compiled runners encode payloads, so readable-selector rg against
patches alone finds no standalone text; exact accepted WP3 payload is the base.
This change edits that source block in place, removes the obsolete retry rules
and adds unique heading/button selectors. All external CSS prefix/suffix bytes
and normalized header bytes match. No new !important or absolute/fixed layout.
The support anchor's shared `.bs-account-link` at DS:7400 has align-self:flex-start;
the existing owned support rule now centers it. Changing the shared rule would
alter unrelated account links, so the component rule is the proper scope.

The logo uses the exact three rect/path attribute objects of official public
tracking IconLogo.vue/module412b, in a standalone32x32 SVG wrapper. No geometry
or color changes, active content, remote refs or browser third-party asset call.
It lives beside DS in its verified existing stylesheet directory, avoiding any
assumption about an uncollected image subdirectory. Source proof is local;
no user screenshot or real tracking number is used.

## Current evidence and bank-language chain

Owner source-only collector R2 Review OK, request
`4ae393a6-4a77-4fb3-b157-0939f6217a8a`; accepted capture has7files/11,677bytes,
zero current-target/routing drift and no new relevant DB hooks. Intake:
`diagnostics/RD-18_WP4_display-source_review_evidence_20261028/owner-display-source-intake-r1.json`.
Source bodies/selected-row flags stay local unless separately supplied; review
receives authored changed code and fictional tests, not raw customer evidence.

Fresh native Language::load resolves registered namespaces; startup/extension
adds language directories and startup/language.after attempts the selected
language-extension route. Both discovered UK bank_transfer files contain the
same old text_payment. Both are updated phrase-only. No assumed single winning
translation is required. Fresh system/config/catalog.php also reveals static
event/translation after language load. Local owner-supplied native translation
event/model confirm store/language/route DB overrides. These native files are
hash-pinned for hosting Verify, not assumed current from their older timestamp.
Bank controller hash matches the locally held source; its private financial
requisites/body are excluded from review. It composes future instructions from
language text_payment; there is no modification or invocation of that writer.

New Verify/Apply admission schema-checks translation route/key then uses prepared
COUNT only for text_payment under both routes, conservatively across all
stores/languages. Any competing DB override stops; no values are read/exported
and there is no DB mutation workaround. Current startup/config/language and
translation event/model/native bank controller are pinned. Four bank language
edges, translation-model and bank-controller/confirm edges are added to the
protected event set. Five exact prior hook pairs remain the only allowances.
Unknown callbacks stop; no arbitrary extension or theme admission is introduced.

## Exact release contract

Nine targets: eight existing and one new local SVG. Exact states/hashes/bytes in
`work/rd18-wp4-20261028/targets.json`;90 dependencies and origins likewise local.
Targets, in backup-index order:

0. catalog/controller/account/order.php
1. catalog/view/template/account/order_info.twig
2. extension/opencart/catalog/language/uk-ua/payment/bank_transfer.php
3. catalog/view/stylesheet/boostershop-ds.css
4. catalog/view/template/common/header.twig — H1 existing DS?v value only
5. extension/ukrainian/extension/opencart/catalog/language/uk-ua/payment/bank_transfer.php
6. extension/ukrainian/catalog/language/uk-ua/account/order.php
7. catalog/view/javascript/booster-order-tracking.js
8. catalog/view/stylesheet/booster-nova-poshta.svg — before absent, backup none

Private staging only, public_html working directory, shared logical
RD-18-WP1_orders_20261027.lock. Upload -> Verify -> separate owner Apply; fresh
one-use local state. Never replay any consumed WP1/2/3 Apply. Guard source
hashes/anchors/theme/events/schema/paths/lint/admission before writes. Header is
last. Existing-file backups original-0..7, new SVG no original. Full eventual
backup path is printed by Apply under:
`/home2/boosters/public_html/_patch_backups/RD-18-WP4_detail-polish_20261028-<UTC>-<nonce>`.
Post-write failure auto-restores. All backup/target states are checked before
rollback writes; restore8 originals/delete only matching new SVG, including
partial rollback. Native NP/image derived caches remain, no arbitrary cleanup.
Repeat content markers produce already_applied without rewriting later DS/header
changes. Owner packet chains successful runner && verified template-cache cleanup
and confirms staged runner absence. Runner self-deletes on success.

Expected Verify: admission_sha256, syntax_gate=ok, production_writes=0,
backup=none, done=ok. Expected Apply: nine exact targets, changed_files=9,
self_deleted=yes, done=ok, template_cache_cleanup=ok, staged_runner_absent=yes,
RD18_WP4_APPLY_OK_OWNER_QA_REQUIRED. Deterministic receipt acceptance is not QA.

## Local validation and limits

Eleven checks bind each exact deploy pair before/after with fixture/result hashes:
PHP8lint5files/JSsyntax;186 protected event cases/25pair cases/13theme cases;
native private display method8snapshot cases, native Language2namespace loads,
phrase-only bank bytes and exact extracted prepared-COUNT0/1 gate;
actual compiled9payload file engine7scenarios with8backups/1new file, exact
partial rollback/tamper/drift/lock/admission refusal and automatic restore;
decoded payload/dependency/header/CSS/controller/forms/inline JS boundary proof;
native PHP/Twig before/after28contexts preserve the full initial readtrace,
products/totals/pager; real Chrome88order+20hub layouts at390/768/1280/1591,
five exact FormData pairs,4fake cart POSTs and native AJAX history page2;
40carrier scenarios at320/390/768/1280/1591, actual8second AbortController,
collapsed visibility, no repeat requests/retry controls, none/unavailable/errors,
HTML/long strings/invalid TTN/forged URL/cross-origin/no-root, hover/keyboard ring,
44px targets, asset decode, invoice absence, NP street/flat/long address and
support lines/phone. Full PS5.1+7six-step state/receipt/replay checks replace both
SSH and SCP before invocation; no native transport or host action.

Executor visually inspected current synthetic mobile/desktop screenshots.
The official mark preserves a one-line status heading at390; support centered.
Initial fixture-only token expectation was corrected to actual DS blue. Asset
location was corrected to the verified existing parent, and the completed file
engine proves auto-restore plus the final successful route. These are local
limits, not claims of owner-host execution. No tests use actual financial data.

WP3 native model/DESC history/visibility/notify/pagination, NP API/helper/cache,
native photos/reorder/login and D2 status colors are unchanged. Exact byte/hash
proof reuses accepted WP3 native SQL/provider/photo evidence; those unrelated
suites are not re-run. Controller equality after omitting only display helper
and3new labels proves endpoint/model/cart/comment logic unchanged.

Signature audit: no new !important or absolute/fixed; existing accepted timeline
geometry remains. One documented8second setTimeout is a cancellable request
deadline and is cleared in finally. New44px targets and2px keyboard ring follow
existing account controls; SVG numbers are official geometry, not layout hacks.

NOT PROVEN locally: actual hosting schema/translation override absence/native
PHP/cURL/MySQL/Unix modes, real NP state, shipping snapshots beyond local shape,
live theme/cache wrapper behavior or final owner QA. Runtime Verify and owner
deployment/QA remain required. The independent reviewer receives text/hash
evidence, not screenshots or raw acquired bank/controller/config/photo/NP bodies.

## Current gate

Integrated final subscription Claude review pending for this exact patch/packet.
No owner Upload/Apply command is released yet. After accepted review, release
Upload+Verify only; accept that receipt before giving the separate Apply step.
Owner QA: invoice absent/real, compact locker/branch/courier address and stored
full recipient, NPlogo/tracking at mobile/desktop, visible NP region while history
folded, three support lines and phone. New bank instructions use the new phrase;
already stored instructions intentionally retain their historical wording.


## Actual ready review and mechanical release

Actual subscription Claude **Review OK**, no mandatory findings, request
`2456770f-2dd7-4b17-aa88-5b48975b91af`. Payload 311,020 bytes, SHA-256
`9331e1ad851919c03212eabead48a1cbd2c8fdda445579da8ebc0310d52e8026`. Response SHA-256 `ff0b11ca38a5e068a468480f4c81670ab64c2baeebcb0f6d9d435415bbdc85b7`.
Runner SHA-256 `6e0228de91fa957afca9dc63a908e160633c1fab401ccecdbd52e1ddf7c46e9b` (661,890 bytes).
Owner packet SHA-256 `b5e92850ebb857afbce0469877f2512ef79e66533a1de058e412780c605719ab` (11,841 bytes).
All162 declared input hashes and eleven pair-bracketed checks were
verified before bookkeeping. Exact deploy/target/dependency/test bytes unchanged.
Reviewed report and facts are preserved separately. r1/r2 were local preparation
only; r3 was the first actual final WP4 review. No repeat model result call.

O1 native link formula additionally matches the unchanged actual PHP helper on
two fictional numbers; real TTN click remains owner QA. Supplemental Chrome
active-state/support-center checks pass four widths against verified DS token.
These local additions were not read by Claude and did not alter frozen files.
O2 initial loading shift/one endpoint read is accepted for owner QA; O3 unused
labels/localization cosmetic deferred; O4 read the primary runner error before
secondary staged-absence text; O5 never RollbackUpload after rollbackAttempted;
O6 inspect compact point qualifiers on real orders; O7 UK-only wording accepted.
All optional scopes/limits are recorded in the release closure.

Current owner gate: **NEW Upload -> Verify only**, send full receipt. The exact
packet SHA must match before its first invocation. Apply is a later separate
owner action after expected receipt acceptance. Any STOP preserves all evidence;
no replay/reset/DB workaround/allowlist expansion. Final live owner QA remains
required, with historical bank wording intentionally unchanged.


## Owner Verify accepted (2026-10-28)

Complete saved Upload/Verify receipts match the accepted contract. Hosting PHP
8.0.30 at /usr/local/bin/php; syntax_gate=ok, production_writes=0, backup=none,
done=ok. Admission `7e79a7a74b77dc6f54babd7eae538b729d627d259091bd22bfe539626ccd8f42`. Exact reviewed deploy pair unchanged; all162
reviewed identities verified with preserved pre-bookkeeping report/facts.
One-use state uploaded/verified, no Apply attempt. No new model result call,
server action or owner-state edit. Current gate: owner **Apply ONCE**, send the
complete result; no replay/reset. Final owner QA remains pending.


## Owner Apply accepted; two further QA changes

All9target states match;8backups/newSVGnone,66templates removed, selfdelete/cachecleanup/stagedabsence successful. Backup /home2/boosters/public_html/_patch_backups/RD-18-WP4_detail-polish_20261028-20261028T094517Z-6df334e56edf. Apply consumed; no replay/reset. Owner requests add Є to support question and correct display-only NP shipping price/free-from2000 plus separate-payment notice. Final owner QA remains open.
