# RD-18 WP1 account orders light-A

Date: 2026-10-27 (Europe/Kyiv). Executor: owner-assigned Codex.
Status: R1_REVIEW_OK_OPTIONAL_REPAIRS_READY_AFFECTED_REVIEW_PENDING. Not deployed; no owner command released.

## Outcome, authority and owning causes

Owner accepted G0: `1 як на макеті. інше ок`. First stored product name plus
remaining positions is approved, as are native numeric pagination, recorded
history only and native per-product reorder. RD17 deployment/final owner QA
was accepted before RD18 began. H1 permits only the existing shared DS token
value per convention8; D2 variant1 is already decided. No further H1 question,
new business rule, model/query/status-map rewrite or new reorder endpoint.

Current order_list.twig13-42 and order_info/history use stock bordered tables
and inline legacy status styles. They lack explicit shell opt-in. The existing
hub latest badge uses bs-account-badge (DS7399), whose source always colors it
blue, while the hub controller omits the computed ACC007 group. New semantic
status classes replace only that badge; adding a presentation field to the
existing hub projection preserves all hub reads and remaining output.

Shared CSS history was checked in current DS and patches: new order component
prefixes have no pre-existing implementation. Existing RD16 mobile DS7412-7426
intentionally uses display:contents/order to rearrange hub cards. RD18 opts
only its own main into a flex box and the accepted sub-page chip style; editing
that shared rule would alter the accepted hub. Stock stylesheet.css18 clears
button outlines with important; new purchase-button focus uses box-shadow,
leaving the global rule intact. All these reasons are in owning CSS comments.
No new important, timer, absolute/fixed positioning or new DS token. Existing
inline reorder script is transplanted byte-for-byte, not reauthored.

## Final behavior and protected contracts

List: stored order number/date/total, recorded status-change timestamp and D2
badge, stable first product and correctly inflected remaining-position count,
filled blue Details link, empty state and native pager. Size remains10. One
existing getProducts call per visible order adds at most10 reads; each fetches
all product rows of that order, not a claimed10-row bound/latency benchmark.
Smallest stored order_product_id identifies the summary consistently because
the protected native query has no ORDER BY. All old count/read calls remain.

Detail: same native stored products, quantities, options/subscription/reward
information, formatted prices and stored order totals, invoice/payment method,
shipping and billing snapshot, customer order comment, native returns and
conditional credit-status link. No financial recomputation. Native per-product
form markup/control IDs/names/values and complete cart/history inline handler
are exact. Purchase uses existing --bs-buy; the no-green D2 rule concerns status
badges. Support link is the assigned bot URL with blank/noopener.

History: each native visible row rendered once, same pagination/order/time/
fallback/comment precedence. The map defines no next-step sequence, so none is
invented. Current state is explicitly highlighted in a separate panel inside
the status section using the native current-order attestation. No arbitrary
event at the end of a page is declared current: repeat/backdated/missing events
and paginated history cannot establish its identity from the existing query.
Native history fragment paging remains; no list load-more AJAX is added.

ACC007 library, model/query bodies, owner scope, hidden-status predicate,
status counts, page bounds and history notify mask remain untouched/hash-pinned.
The native current status/date/message and all original display data stay in
the controller; only presentation fields are appended. Creation-date display
uses the same verified Kyiv parser, with a separate truthful missing-date
label, rather than exposing the old strtotime(false)->epoch fallback. No
timezone, date storage, ACC007 method or business validation rule changes.

Stored recipient name/phone/address come from the order, not today's profile.
Optional middle is read only from shipping_custom_field.rd13_receiver_middlename;
empty/missing/malformed values are not filled from a potentially different
payment recipient. The accepted RD17 writer contract is source evidence, pinned
for host re-admission. Old orders lacking it remain truthful. Display aliases
decode one transport entity layer then escape; native form payload fields are
unchanged. No customer/order rows were exported or inspected.

One group map on hub/list/detail/history: in_progress gold-soft/warning-fg;
in_transit blue-soft/blue; completed ink2/paper-white/check; cancelled_problem
line2/ink3. Neutral fallback keeps its truthful label with line2/ink3 and no
check. Status15 remains hidden through unchanged ACC007 logic. The library's
legacy color/style outputs remain for other consumers but are unused here.

The existing shell already expresses order/page; no shell target. Mobile
page-local JS reveals the current chip horizontally at DOMContentLoaded, without
moving document scroll or changing shared navigation. It has no timeout,
observer, backend action, new global handler or history-fragment reinjection.
Addresses/edit sources are not targeted; new CSS selectors cannot match them.
Actual hub Twig output is identical after normalizing its one status badge.

## Source and independent-check boundary

Fresh owner capture30 files at2026-10-27T06:58:53Z; all28 applicable release pins
match. Receipt/manifest/member/hash/privacy/cleanup proof and G0 are retained in
RD18 WP0 reports/evidence. New pagination sources have no old release pin and
are not falsely claimed as matches. Current three order-template DB override
counts are zero; actual theme lookup matches those routes. Events/effective
runtime are re-admitted by the runner before any source write.

66 dependencies use the latest accepted RD17 state plus fresh RD18 sources;
two accepted checkout snapshot-writer sources explain the read contract and
are pinned, not edited. All dependency source identities checked locally.
Runner narrows old address admission to the actual order/hub/shell/pager view,
controller and protected-model surfaces; fixed all-store theme/layout/event
metadata reads only, prepared statements/bind_result, no get_result dependency.
Accepted four exact extension hook pairs and their source hashes retained.
Any other relevant hook, alternate template, target/dependency/non-token drift,
layout change or different expected hook multiset is STOP, not automatic re-pin.
No unrelated address InnoDB requirement is retained for this read-only task.

Local proof (fictional data, no hosting/DB/provider operation):
- Native PHP8.0.30 lints runner/two controllers/language. Actual pinned Twig and
  current controller/shell/status library render17 before/after order contexts,
  plus5 native hub-Twig group contexts. Registry/models/auth/config/URLs/header/
  footer are fake. Original model call traces and pager arguments match except
  approved list summary calls; all old product/option/total fields match.
- Real Chrome:48 order layouts plus15 hub layouts at390/768/1280, long names,
  14 product rows, neutral/technical statuses, unknown/missing dates, optional
  and malformed middle, HTML/entity edges, empty/history-paged cases; no page
  overflow/new page errors. Active order chip visible; native shell once.
  All D2 computed colors/checks verified; address badge unchanged. Five actual
  DOM FormData pairs identical. Three real native handler POSTs hit a loopback
  fake cart, one native history-page click hits a loopback fragment. Focus/
  hover/active/touch geometry checked; screenshots inspected at all3 widths.
- Seven native file-engine cases with nine actual payloads: check/apply/backup/
  no-op after later CSS/token/partial rollback, tampered backup, target/dependency/
  header/lock/admission rejection and automatic restore after postwrite fault.
  Runtime metadata/dependency hashes are explicitly fake in these tests;
  Unix real ownership, MySQL and production runtime are not certified locally.
- Exact extracted native event/template/multiset gates:102 order hook cases,
  20 exact-pair/mutation checks, six broad patterns,13 theme cases, duplicate/
  missing/extra multiset rejection. Real local files, no live metadata DB.
- Actual PowerShell5.1 and7 packet AST/six-step/state/replay tests with complete
  native SSH/SCP/transport replacement. No owner packet/server/key tool invoked.

Evidence under work/rd18-main-20261027: preservation, static/render/browser/
runner/admission/packet JSONs. Browser test initially used a near-edge pointer
point; center-point native clicks corrected the fixture, without a production
handler change. Final48 layouts/3 POSTs pass. A mistaken default PS7 path was
not found; actual discovered bundled PS7 was then tested. No installation or
auth reset. No screenshot or real personal values enter the reviewer packet.

## Owner execution, receipt and rollback

Exact runner/packet hashes and per-target states follow in the generated table.
All nine targets exist; backup originals0..8, no absent/new-file branch or SQL
rollback. Private stage: ~/private/booster-patches/RD-18-WP1_orders_20261027.php;
cwd ~/public_html. Apply order labels/CSS, order+hub projections, views, token
last. Rollback restores views first, projections, CSS/language, header last.
Every live target and needed backup preflight before restore; mixed partial
before/after states supported; foreign drift stops. Local payload lint and
postwrite lint have exact restore behavior; runner self-deletes on success.

Backup expected:
/home2/boosters/public_html/_patch_backups/RD-18-WP1_orders_20261027-<UTC>-<12hex>/
Full actual backup path is supplied only by owner Apply. No backup/deploy yet.
Header accepts a different well-shaped before token if all non-token bytes
match, reads it and replaces it wholesale per H1/convention8. It never appends
or anchors on the old literal token; no-op never re-busts a later token.

One-use owner packet: Upload then Verify, return result before separate Apply.
Expected Upload RD18_WP1_UPLOAD_OK. Verify: PHP8.0.30/lint, admission64hex,
syntax_gate=ok,production_writes=0,backup=none,done=ok,RD18_WP1_VERIFY_OK.
Apply recomputes admission, then exact9 per-target before/after/backup receipts,
changed_files=9,self_deleted=yes,done=ok, template_cache_removed=count,
template_cache_cleanup=ok,staged_runner_absent=yes,
RD18_WP1_APPLY_OK_OWNER_QA_REQUIRED. Verified DIR_CACHE cleanup occurs only
after source success using &&. Any ambiguity/error stops, preserves consumed
state and receipts; no reset, reapply, generic rollback or replay of old packets.
RollbackUpload/RollbackVerify/Rollback are a separate owner recovery decision.

Owner post-Apply QA: hard-refresh, desktop/mobile list/detail, real statuses/
recorded timestamps/totals/delivery snapshot vs admin, history and order page2
if available, notified comments if available, one legitimate native reorder to
cart without checkout/payment. Confirm addresses/edit and hub except badge.
Tier1 public availability smoke follows deployment. Source/test evidence is
not production acceptance. No agent SSH/key/server/deploy/DB/customer/provider/
cart write, Notion/dashboard mutation or Git commit/push. Final owner QA remains.


## R1 actual review and selected optional repairs

Actual subscription Claude Opus5-5/high returned Review OK, no mandatory findings, requestd8eb3b6d-54fc-4ca2-8c06-4ee1791c652c. Both old exact hashes bound.122 declared inputs unchanged at R1 completion; identity closure and preclosure report/facts preserved. R1 original result/response/run retained; no claim that R1 approves later bytes. Reviewer did not fully decode base64, see screenshots or execute/hash tests, and did not read the stylesheet18 selector. Full plain after-tree and runtime payload hash guards supplied its readable source basis. No real MySQL/native transport/runtime proof.

Before owner release, selected optional O1/O2 and comment-only O6 repaired: native nl2br comment keeps one original newline per line, while billing-address HTML breaks still become newlines. Method display aliases show stored label text without embedded logo/markup, instead of displaying literal tags. They are escaped and never update stored data or payment/delivery behavior. CSS comment explains767px and2px timeline border; declarations identical. Only order controller0 and CSS7 after-images change; all before hashes, seven other targets, normalized runner/runtime engine/66dependencies and owner packet except its runner hash are exact R1 matches. Copies of all R1 artifacts retained under work/r1-reviewed.

Affected tests now17native before/after +5hub,48order+15hub browser cases. New multi-line comment and HTML method-name cases pass at3 widths; billing break and FormData checks retained. Current-payload engine7 and packetPS5.1+7 six-step/replay retested. Metadata pure-gate block unchanged and prior102/20/6/13 tests retained. See r1-to-r2.diff/r2-repair-proof.json. Required affected review is a continuation of CODE/composition repair, not an early-design/result exception.

Other optional dispositions: O3 phone is stored order contact, not a claimed shipping-phone field; label deliberately says Telephone in order. O4 external partial state/no-op is no recovery permission; consumed owner packet STOP remains. O5 duplicated compiled nav scripts derive from one authoring file; existing page-label pattern retained, no out-of-scope shared-shell refactor. O7 owner D2 contrast choice retained without reopening it. No runner/packet source was deployed or uploaded.


## Exact current release identities (affected review pending)

Runner SHA256 `f2bd1d4312923ba7c1d4cdc46cf83ccf44a631f50a027e87f9e3a6bd9a428221`; owner packet SHA256 `2b2713c0ff86a824dacf224b8105ed779d198893943f1d138e8bccb5a3724b91`.

|Index / target|Before SHA256|After SHA256|Backup|
|---|---|---|---|
|0 catalog/controller/account/order.php|05d7de4ef69585cb5422af9a0badc4f0fa5bef4cd0b33cbc272a39bd00078352|d024d4a2f97cd14aa5f0ddda115acc2dc6a3a270fe59910e99b53bf74509a03f|original-0|
|1 catalog/view/template/account/order_list.twig|f526db9ccec43c92577be9fd120910e8be4000efa611b9c6ad7c8b02c4a9c241|8b97d74cfa8997ecde2bda0869724f84d1502bc4f2a3e2102f2e659a5bcb917a|original-1|
|2 catalog/view/template/account/order_info.twig|73067a00da6be949f80b725fbfb608ce62e41cd7cefbca936b83400af0b91793|3a7a46587a4d8da8bc5e6152a92b4fcace2186b28b5a84fd6bb8a1b986dbe041|original-2|
|3 catalog/view/template/account/order_history.twig|322d458b90e70c987082431074e9215afd94b8bdbe6298491b77f472b58abd62|be4e25f2742ce7a46fa1084213ed3418265f8407f98bf053d814430d947cc98f|original-3|
|4 catalog/controller/account/account.php|fcb06de0862686ab1d8afc333651a7f97fb6aa9615d5bf13909d3a1b6a055b5c|00d61ca1c85529a79581fe35f293264f143ab8f7110017dc9ad6386506df1aaa|original-4|
|5 catalog/view/template/account/account.twig|7cf795ace7b9c8f81ddfc44d246eb8273c1aa9dc77f55a7b751c93d08b166520|745a669b8a1cf46c754801a9f8abf4e646d9aece9969f4198290ec7c4c00f0e8|original-5|
|6 extension/ukrainian/catalog/language/uk-ua/account/order.php|bd25abfe02408347c9ef1770e6134df11101a8c98fcc6e4d0dedfbdee841dcea|f6f5124797bfa6e8cb2b3f8b4d3c41fa947e6582bdd3db5ab0bc12e902ad8d06|original-6|
|7 catalog/view/stylesheet/boostershop-ds.css|f1411862e5aed5da5e0da32b41e49e22e78f4ca8d297a7367bcac7b7be89ba67|b3db6ec93a0d9954efd89e1d99abc7d5481df28bcd87750855ff01fded7c104a|original-7|
|8 catalog/view/template/common/header.twig|dfeb11467afd43eb760bc95ea1cb0f38a0640e85ce3a90c77f5439fb7a2f33a2|e578f64fa92a666aff7d3fa8e3436df071d287b6fae9db3d4b2a5e1aa8144a32|original-8|
