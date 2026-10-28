# RD-18 WP2 detail visual/photo repair — ready package

Date: 2026-10-27. Executor: owner-assigned Codex.
Status: OWNER_APPLY_ACCEPTED_FINAL_OWNER_QA_PENDING.
Prior RD18 r3 was deployed and owner visual QA was rejected. This repair is
local only. RD17 remains accepted. No new deployment or owner acceptance.

## Delivered candidate and owning defect

The owner's two attached exports have exactly the registered x-dc markup;
only export token/runtime preambles differ. Approved desktop has a primary
history/products column and300px summary/delivery column. Deployed r3 placed
both information cards below full-width history/products (DS7607 and the
order_info.twig bottom-grid placement). Its duplicated expanded current-state
card,12px hollow timeline markers, missing thumbnails, generic44px title link
and mobile price-below-name rules further changed the composition.

WP2 replaces the owned RD18 CSS block in place and reorganizes only detail and
history Twig. Desktop columns, mobile24px heading/card sequence,14px panels,
22px recorded-event markers,64/56px image slots and quantity/title/total layout
follow the boards. All used variables exist. The8px board corner is the existing
--bs-s2 value; no missing --bs-r-btn variable or new global token. Labelled44px
per-product cart/return icon buttons retain native routes/forms/handlers.

Accepted G0/D2 boundaries remain: no speculative future event or new bulk cart
action; current state/date/message in a compact accessible disclosure, without
falsely identifying a history row. Latest appended history ID is not exposed by
the current model projection; sorted/paginated/repeated rows must not be guessed
as current. Notified comments remain visible and escaped, generic text retained.
Completed header badges stay dark/check-marked, never green; purchases retain
the existing DS purchase token. Existing real totals, payment method/invoice,
billing disclosure, snapshot recipient/telephone/address, credit and return
actions remain. Actual payment name stays visible in its disclosure summary.
Global header/breadcrumb and accepted RD16 shell extras are retained. Real
content and these functional requirements can change height versus mock data.

No !important/timer/absolute/fixed authored. Board and touch-target dimensions
are annotated. New source rules replace old detail rules; CSS prefix and suffix
outside the owned block are byte-exact, including the original terminal CRLF.
List/hub/status-map/language/shell source is untouched; former unchanged targets
are now pinned dependencies. Full header bytes normalize identically after
masking the single permitted DS token value. One writer, no Git or roadmap write.

## Actual photo sources and read/write path

Owner chose current catalogue photo, placeholder if removed. Source-only
collector Review OK request7e06cc29-0f65-4095-8e9c-f48aae700f1b covered only the
collector/owner command, not this repair. Owner then supplied3fresh sources,
76342bytes. Intake accepted exact archive/manifest/member hashes,112enabled
events/5previously reviewed relevant pairs/0unknown/0redacted,5routing hashes
matching the existing pins and expected image-root mapping.

Fresh whole-file pins:
- catalog/model/catalog/product.php ecf96b214b47f1efd5e3826caedeae6cc25d8d84650ae8a3915330f6f5561dc7;
- catalog/model/tool/image.php 85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71;
- system/library/image.php 7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9.

Complete called native paths were inspected locally. Catalogue constructor and
getProduct use SELECT, with current store/language/enabled/date filters; other
writers in the same class are NOT invoked. The constructor's1or2setting reads
already existed because the old info route loads that model. New work is at
most one getProduct call per distinct ordered product per detail request. Only
the returned image string is used; current name/price/stock never replace stored
order data. No change to protected account/order queries, page size or predicates.

New rd18Thumb memoizes per request, validates scalar/path/canonical image root,
preflights supported raster data and calls native tool/image.resize64x64.
Missing GD short-circuits before the native Image constructor can exit. Missing,
removed/unavailable, corrupt/outside-root or failing optional photo yields an
empty alias and a neutral placeholder. Native resize/WebP/same-stem collision/
GIF fallback and system Image functions remain unchanged. It can generate the
normal derived image/cache files on first use; no database or original-image
write is authored. Derivatives are not deleted by source rollback. No RD25
classification/resizeFit/measurement path is invoked. No benchmark claim.

The final event guard retains all86accepted r3 targets and5exact hook pairs,
adds8protective dot/slash before/after edges for getProduct and resize, and
requires the same five-row multiset. No new handler is admitted. Image-root
runtime mapping is checked for Verify/Apply, not imposed on file-only rollback.
All73dependency source hashes must freshly match before backup/source writes.
Any event/core/source/runtime drift is STOP, never a blind re-pin/allowlist.
Language/other-model hook coverage remains the recorded prior limit; no whole
store or analytics delivery certification is asserted.

## Exact guarded pair and contract

Runner: patches/RD-18-WP2_detail-visual_20261027.php.
Owner packet: diagnostics/RD-18-WP2_owner_20261027.ps1.
Exact hashes/bytes and5target before/after states are in
work/rd18-visual-20261027/release-facts.json and targets.json. No new target file.
Prepared pair is NOT Review OK or released until actual integrated acceptance.

Five targets, indices0..4:
0 catalog/controller/account/order.php;
1 catalog/view/template/account/order_info.twig;
2 catalog/view/template/account/order_history.twig;
3 catalog/view/stylesheet/boostershop-ds.css;
4 catalog/view/template/common/header.twig (token value only).

Shared logical private writer lock stays RD-18-WP1_orders_20261027.lock. New
private runner identity, local owner-receipts and backup namespace; no import,
reset/replay or cleanup of old consumed r3 state/stage. CSS full-file replacement
is anchored to exact before hash and exact owned-block diff. The block-kind
no-op checks that entire exact new owned block occurs once, so a later unrelated
CSS append or token remains untouched. All other target states still match.
Dynamic changed_files uses actual target count (five), not copied nine.

Apply order3,0,2,1,4: styles, photo projection, history/detail, token last.
Rollback order1,2,0,3,4: compatible old views before data projection/style and
header last. Full live/backup preflight and partial-state recovery retained.
Backups only after future successful admission:
/home2/boosters/public_html/_patch_backups/RD-18-WP2_detail-visual_20261027-<UTC>-<12hex>/original-0..4.
None created. No SQL rollback. Future exact receipt supplies full actual path.
Automatic restore on post-write/lint failure, self-delete and verified template
cache cleanup only after successful source Apply with &&. No image-cache purge.

Expected owner route after actual Review OK: NEW packet Upload then Verify,
return output before separate Apply. Verify PHP8.0.30/lint/admission64hex,
syntax_gate=ok,production_writes=0,backup=none,done=ok,RD18_WP2_VERIFY_OK.
Apply5exact target receipts,changed_files=5,self_deleted=yes,done=ok,
template_cache_removed=count,template_cache_cleanup=ok,staged_runner_absent=yes,
RD18_WP2_APPLY_OK_OWNER_QA_REQUIRED. Any STOP/partial result preserves receipts;
no retry/reset or inferred rollback grant. Rollback is a separate owner choice.

## Verification, bindings and limits

All10local jobs bracket the exact runner/owner-packet pair before and after;
fixture/result hashes retained in bound-check-results.json. Native PHP8lint;
pure admission126legacy+photo trigger cases/25pair mutations/13theme cases;
five-payload file engine7cases; full six-step/replay PS5.1+7transport mocks.
Native actual Loader/catalogue constructor/getProduct/tool Image resize/WebP/
library/GD execute on synthetic local rasters and fake SELECT results:10photo
cases including cold/warm PNG/WebP, same-stem JPEG fallback, GIF, removed/missing/
corrupt/outside-root/getter-failure/invalid ID, request memo and no-GD fallback.
Only four native getter/image model event strings observed, all guarded.

Actual controller/shell/status map/pinned Twig21before/after contexts preserve
protected read traces/pager/stored products/totals exactly, except current-photo
getProduct/resize calls and the new rd18_thumb field. Fake registry/auth/models/
config/header; photo renderer preflights real synthetic rasters but uses stub
catalogue/image URL services, separately validated against native APIs above.
No real MySQL/host/provider/customer/cart operation or rendering claim.

Real Chrome60order+15hub layouts at390/768/1280: no overflow, approved desktop
summary alignment/mobile order, heading/radius/thumbnail sizes,14item long-name
edge and very long currency values; hover/focus/active originally asserted only on the44px buy button. Decoded64px
PNG responses verified, correct fixture image MIME and explicit loopback-only
network gate. Exact FormData5pairs,3single fake native-cart POSTs and native
history page2 request; no page errors. Complete native inline scripts and hidden
forms are byte-identical. Invoice/payment/billing/notified comments/escaped names
and stored patronymic remain checked. Bare Twig ArrayLoader omits production
wrapper/cache; server permissions/GD/real images/integration require owner QA.

Fixture issues corrected before review: missing DIR_SYSTEM, Windows mixed-slash
native image root, redundant is_readable returning false despite actual readable
file (removed in favor of real getimagesize/catch), copied nine-file stdout
replaced with dynamic count, terminal CSS CRLF preserved. These caused only
local fixture/candidate failures, no production mutation. Final results use the
current exact pair; initial failures are not passed off as host proof.

Executor inspected reference and repair screenshots. Interactive comparison:
work/rd18-visual-20261027/comparison.html. Before/after use same fictional data;
approved boards have their own demo data. Review must explicitly state whether
it sees screenshots or relies on measured geometry. Never claim visual/owner
acceptance from source/text-only review or public HTTP availability.

## Next gate

Actual subscription Claude integrated ready review returned Review OK with no
blocking code defect. The direct owner grant covered the321475-byte final packet
and necessary affected repairs; no broader server-source transfer followed.
Reviewer saw text/code/geometry claims, not screenshot images or native acquired
source bodies. Ten tests are local evidence, not production or visual acceptance.

Owner first checks the exact packet self-hash, then NEW WP2 Upload -> Verify.
Return the complete Verify output before a separately released Apply. No earlier
packet replay/reset, source repin, stage cleanup or rollback is authorized.

## Current exact candidate identities

Runner SHA256 `7848eea3a118ecfaf89f948bebffbee83a9d06dbd8b22682216067322853df7a` (614028bytes); owner packet SHA256 `b212cb6591594071429fc6d1ab499acf20882f751d6720b06074c8ba91b9d9b1` (11844bytes). All10jobs and current5decoded payloads match. Actual final ReviewOK request74b56916-585b-44e0-b4a8-3d39b24a0204 now recorded; no deployment yet. Current screenshot PNGs in comparison.html include only synthetic photo fixtures; nativeAPI cache files separately tested. Active desktop order-icon gold/white modifier is page-local and follows the board, leaving shared shell/list/hub source unchanged.

## Mechanical review closures and owner acceptance checklist

M1: owner command includes exact packet Get-FileHash before its first execution;
runner identity is also checked locally and by the reviewed owner packet.
M2: owner returns complete Verify: PHP8.0.x/admission64hex/syntax_gate=ok/
production_writes=0/backup=none/done=ok/RD18_WP2_VERIFY_OK. Any STOP or
already_applied is held for assessment; never retry/reset/re-pin it.
M3: original hover/focus/active assertion covered the buy button only, now stated
accurately. Additional post-review audit-focus.cjs measured7controls at3widths,
all21keyboard focus states visible. Existing DS source7360 provides account
anchor focus outline2px/offset2px; stock stylesheet19-21 clears only button
outlines, not anchors or summaries. Return/thumb/title use existing anchor rule;
buy uses authored shadow, summaries their existing outline. No executable byte
changed and no new corrective package is needed. These are local browser facts.
M4: owner QA still checks visible keyboard focus, real catalogue photos (GD/WebP,
cold load, removed-product placeholder), real totals/payment/billing, and layouts
on desktop/tablet/mobile. No final acceptance before owner confirmation.
M5: rollback requires exact unchanged post-Apply targets and recorded backup.
Later shared CSS/token edits cause STOP even though no-op detection tolerates
them. Never force rollback. Native derivative image/cache files remain.

Optional observations deferred to owner QA/later scope: tablet composition,
comment panel below columns, creation-date wrapping, duplicate product tab stops,
2x raster sharpness and blue markers for recorded cancelled events. No hidden
behavior or accepted functional contract changed to resolve optional preferences.

## Owner-host deployment receipt accepted

Owner Apply2026-10-27T19:55:27Z matched all5before/after hashes and exact reviewed pair/admission. Five original-file backups: /home2/boosters/public_html/_patch_backups/RD-18-WP2_detail-visual_20261027-20261027T195527Z-f6a50df13b8e/original-0..4. Template cache removed37; cleanupok, self-deleteyes, stagedrunnerabsentyes. One-use Apply consumed; never replay/reset it. No agent server access. This confirms the expected host execution receipt, not final owner visual/function QA. Evidence diagnostics/RD-18_WP2_review_evidence_20261027/owner-Apply-accepted_20261027.json. Owner QA checklist is recorded there; RD18 remains unaccepted pending owner QA.
