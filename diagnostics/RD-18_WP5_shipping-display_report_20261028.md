# RD-18 WP5 — stored NP delivery price and support wording

Date: 2026-10-28. Executor: Codex. Not deployed; final RD-18 owner QA open.
WP4 complete expected Apply accepted:9targets/8backups,66templates removed,
self-delete/cache cleanup/staged absence successful. Its Apply is consumed.
Backup `/home2/boosters/public_html/_patch_backups/RD-18-WP4_detail-polish_20261028-20261028T094517Z-6df334e56edf`.
Receipt acceptance: `diagnostics/RD-18_WP4_review_evidence_20261028/owner-Apply-accepted_20261028.json`.

## Owner request and result

Support becomes exactly `Є питання щодо замовлення?`.
For NP orders with a single zero-valued shipping total, use stored order UAH
payable after discounts: >=2000 inclusive is `За наш кошт`; below is the saved
positive shipping estimate if present, otherwise `За тарифами НП`. Add exactly
`Оплата доставки здійснюється окремо` immediately after the payment-method line
for the separate-payment branch. Do not add an informational tariff to totals.

The inclusive boundary is the native shipping rule, not a newly changed checkout
rule. Owner said orders above2000; existing source uses >=. A stale saved free or
paid quote label cannot defeat the boundary established by this order's UAH sum.
The display policy does not rewrite the order/quote or alter who was charged.
For foreign/invalid monetary context, use a recognizable stored payer label or
positive estimate; absent that evidence, do not guess a UAH rate/payer. The
generic tariff label can show without asserting separate payment in unknown cases.
Positive charged shipping totals, non-NP, missing/duplicate/invalid shipping
rows keep the native display and have no new notice. This protects already
charged historical money rather than falsely showing it as free/separate.

## Owning cause and source freshness

WP4 order controller formats every native order_total.value into total.text;
order_info.twig prints total.rd18_text. Pinta deliberately stores shipping cost
0.0 (ST-2c.5); tariff is display-only. Current NP quote source returns its
separate text and booster_display_text, with free-from2000 default and a
post-discount cart-payable comparison. Checkout confirm saves the entire
session shipping_method, and native account model decodes the stored JSON.
The display-only estimate therefore exists outside the payable shipping row.
The old template loses that distinction and prints a misleading currency zero.

Use these saved fields directly; never invoke current cart, coupon preparation,
shipping quote, NP tariff API, current profile or current exchange-rate lookup.
UAH amount is stored total * stored currency_value, matching native account
formatting, rounded to kopecks for the boundary. The saved positive estimate is
already formatted at order creation. Unknown/zero/markup is not an invented price.

WP4 Apply hashes establish the exact current3targets. Existing NP quote/model
dependencies matched the preceding host admission. Checkout confirm is an
additional owner-held source pin: host Verify must match it before application;
its older local timestamp alone is not presented as fresh host proof.96 complete
dependencies protect composition. Any drift is STOP, not hash replacement.
No raw customer screenshots/order rows/financial details are needed or sent.

## Scope, states and rollback

Three existing targets, exact before/after states in
`work/rd18-wp5-20261028/targets.json`:
0 catalog/controller/account/order.php — new private read-only projection and call
1 catalog/view/template/account/order_info.twig — shipping display/notice only
2 extension/ukrainian/catalog/language/uk-ua/account/order.php — wording

CSS, header token, shell, NP tracking/API/cache, native models/status/pagination,
forms and whole inline JS are byte unchanged and dependency-pinned. No cached
asset changes, so no token update is needed. Existing muted text style is reused.
This is scoped Twig conditional presentation; no overrides, !important, new
timers or absolute/fixed layout. Previous owning CSS history is reused unchanged.

Same reviewed file engine/runtime/theme/event/bank-translation safety guards,
new private WP5 identity and new one-use owner state; shared RD18 writer lock.
Three backups original-0..2 before any write. Apply order2,0,1 publishes labels,
controller then template; rollback1,0,2 reverses dependencies. All targets and
backups preflight before rollback writes; post-write failure auto-restores.
No new files, DB writes, actual NP requests, keys/config bodies, agent SSH/server
or cart/order creation. Bank instruction and historical comment logic untouched.
Future backup pattern:
`/home2/boosters/public_html/_patch_backups/RD-18-WP5_shipping-display_20261028-<UTC>-<nonce>/original-0..2`.
Rollback restores WP4, not a pre-WP4 site. Derived NP/image cache retained.

Owner stages privately outside webroot; public_html is working directory.
Upload -> Verify returns PHP8.0.x/admission64hex/syntax_gate=ok/
production_writes=0/backup=none/done=ok/RD18_WP5_VERIFY_OK. Accept receipt before
separate Apply. Apply prints3targetlines/changed_files=3/self_deleted=yes/done=ok,
then chained template cleanup and staged absence, RD18_WP5_APPLY_OK_OWNER_QA_REQUIRED.
Any STOP preserves evidence; no Upload/Verify/Apply replay or state reset.

## Validation and limits

Nine local checks bind the exact patch/packet before and after each execution:
PHP8lint3files/JSunchanged syntax;20native private-helper cases with fictional
snapshots and language-only registry (unexpected model/cart/currency/API access
fails), 1999.99/2000/2000.01, coupon2100->1900, stored UAH rate, stale saved
labels, saved estimate/JSON, zero/bad/markup fields, foreign ambiguity and paid
quote, positive/nonNP/missing/duplicate/invalid native rows. Inputs unchanged.
Actual compiled3payload file engine6cases,3backups, no dependency writes,
already-applied no-op, tamper/drift/lock/admission refusal,2partial restores and
post-write automatic restoration. Decoded payloads/96deps equal local sources;
controller equality after only helper/call/one label omission, forms/scripts
and shared assets unchanged. Native PHP/Twig36before-after contexts retain exact
readtrace/products/totals/pager; only Twig presentation changes. Chrome32cases
across390/768/1280/1591: price/free/fallback/notice placement, exact support,
no overflow, before-after grandtotal and FormData equal. Real PS5.1+7AST/sixsteps/
receipt/state/replay run with both SSH/SCP replaced before invocation.

Executor inspected final mobile/desktop test screenshots. Short fallback text
keeps the existing totals grid compact; no CSS patch. Existing hover/focus/active
controls and native history/NP/link/photo behavior are unchanged, with WP4 exact
code and its reviewed native/browser evidence reused by hash. Do not rerun
unaffected provider/photo/history suites simply to increase counts.

Fixtures are synthetic, no customer seeding/DB/provider/cart/server action.
Host PHP/MySQL/schema/theme/translation overrides/actual stored quote availability,
live visual QA and actual shipping invoice price are not proven locally. A saved
estimate is informational, not a promise of the carrier's final invoice tariff.
Unavailable estimate correctly falls back to carrier tariffs. Owner Verify and
final mobile/desktop QA remain required. Reviewer gets code/text/hash evidence,
not screenshots/raw financial controller/config/photo/NP source bodies.

## Current gate

Integrated actual subscription Claude ready review pending for exact WP5 pair.
No owner action released yet. After Review OK, release Upload -> Verify only;
Apply is separate after receipt acceptance. Final owner QA remains open.


## Actual ready review and mechanical release

Actual subscription Claude **Review OK**, no mandatory code findings, request
`50e2dba4-1513-4503-8901-e07b58715a0b`. Payload 241,235 bytes SHA-256
`8b7bd8422bd955347f0acb9c925cdd89f45a476543a44afcf2742533544c2cf1`. Response SHA-256 `09480cc25dc2d7fc984389010f2e9076456671a5c3dfcd801bfb48d22d8c0f36`.
Runner SHA-256 `0274bc8dae812feb09121907ec6576ae73adc0201a491d5e5b4a6a9b3d947fd7` (151,558 bytes).
Owner packet SHA-256 `07dc12dab06f1e2486da92efed471cd76fd2582edb49f95d417eef415c208a39` (11,854 bytes).
All143 declared identities and nine exact-pair tests verified before
bookkeeping. Reviewed report/facts preserved. Code/commands/payload/dependency/test
bytes unchanged; no new result-model call. No source/customer/config/financial
body transfer or agent server action occurred.

Optional O1 estimate wording stays for live QA; O2 owner directly specified2000
display policy, no live-setting identity claimed; O3 visible kopeck rounding;
O4 four extra fallback checks pass locally without modifying frozen results;
O5 pinned list has no old support-key use; O6 PHP8.0 only; O7 cosmetic/recovery
edge retained without replay authority. Full dispositions in release closure.

M-G1 remains: **NEW Upload -> Verify only**, return receipt. M-G2 separate Apply
after accepted Verify; M-G3 final real mobile/desktop QA. No prior WP4 Apply or
source collector is replayed. STOP preserves evidence and state; no re-pin/reset.
New owner-receipts directory absent at release; WP5 not deployed.


## Owner Verify accepted (2026-10-28)

Complete saved Upload/Verify receipts match the accepted contract. Hosting PHP
8.0.30 at /usr/local/bin/php; syntax_gate=ok, production_writes=0, backup=none,
done=ok. Admission `b093a6d9321adfe98bc27de2fb6adfa0a3181d6ce72f4ad8b70df2417c35d520`. Exact reviewed deploy pair unchanged; all143
reviewed identities verified with preserved pre-bookkeeping report/facts.
One-use state uploaded/verified, no Apply attempt. No new model result call,
server action or owner-state edit. Current gate: owner **Apply ONCE**, send the
complete result; no replay/reset. Final owner QA remains pending.


## Final owner deployment and QA accepted (2026-10-28)

All3target before/after hashes and3backup paths match the reviewed contract; PHP8.0.30, unchanged admission,34template cache entries removed, selfdelete/cachecleanup/stagedabsence successful. Backup /home2/boosters/public_html/_patch_backups/RD-18-WP5_shipping-display_20261028-20261028T114546Z-9c641c71230e. Complete receipt accepted mechanically; no extra model result call. Owner explicitly stated QA ok after Apply. RD-18 technical implementation/deployment/owner QA is complete; RD-17 was accepted earlier. No implementation work remains in the assigned sequence. All consumed packets stay consumed, no replay/reset.

Final evidence diagnostics/RD-18_WP5_review_evidence_20261028/owner-Apply-QA-accepted_20261028.json. No Notion write was authorized or made. ROADMAP_SOP requires canonical status first, then mirror status; the connector returned an unverified older indexed In progress snapshot, not a current canonical Done confirmation. Dashboard status and its existing local changes are preserved; canonical synchronization remains a separate authorized administrative step, not missing implementation. No Git commit/push performed.
