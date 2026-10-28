# RD-18 WP1 r3 exact order-view event admission successor

Date: 2026-10-27 (Europe/Kyiv). Executor: owner-assigned Codex.
Status: M1_REPAIRED_LOCAL_CHECKS_PASSED_AFFECTED_READY_REVIEW_PENDING. No deployment.

## Evidence and owning cause

Owner metadata capture09:06:14Z accepted through strict local intake: exact
diagnostic/packet/classifier/main hashes, types/counts/PHP8.0.30, two-line result,
no-writes/no-business-reads/no-admission contract.112 enabled events scanned,
five relevant pairs under the old slash-only classifier, exactly one rejected by the held main runner. This capture does NOT prove absence of dot-form model hooks; corrected future Verify rescans all enabled rows before writes:

`catalog/view/account/order_info/before`
в†’ `extension/ps_enhanced_measurement/analytics/ps_enhanced_measurement.eventCatalogViewAccountOrderInfoBefore`.

All four routing core hashes match existing66dependency pins. Current
startup/event strips catalog prefix; Event uses prefix regex matching and
Action splits the literal dot method then invokes through the factory. The
guard's exact raw pair was missing, while its conservative target scope
correctly rejected this actual order-view callback. This is not a model/query
or account UI defect. No registration, setting, module or callback is disabled.

Source evidence already exists among the66pins (full controller/model/JS):
- controller `cd8910524931b33244ae24db4d082c48aba9ae6e635de53a6c16cd2f614f43a1`;
- model `eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4`;
- JS `fa103e101401196e647da4e833436c19580bf6d20b20afdda1eb5417485cdb13`.

Callback bodies were not freshly re-exported in this metadata-only capture.
Accepted source artifacts are reused conditionally: r3 Verify/Apply recheck
those entire file hashes before any source write. Any drift means STOP and a
named fresh source pull, not re-pinning or assuming the callback is harmless.

## Actual callback behavior and compatibility

Controller method1809 and its complete called helper/model methods inspected.
It early-returns when module disabled or all four product-tracking flags off.
Otherwise it loads existing language/models and rereads order products/current
catalog product/manufacturer/category/option data. It appends analytics fields,
special/product_id/option metadata to product projections, stores the existing
session ps_item_list_info bucket and builds view/selection/cart data-layer JSON.
The model rewrites Twig source via the module's native getTemplateBuffer and
replaceViews functions. Only footer tracking injection matches either current
old or new order template. Two legacy href/reorder anchor searches already
match zero in the native old template and continue matching zero in RD18; no
new tracking attributes or analytics repair is added.

No purchase event, remote request, status/payment/order/address writer or DB
mutation is called by this path. Existing session analytics writes are retained,
not falsely described as zero application session writes. The model helper
methods on this path issue SELECT only. Unrelated refund/business methods in
the same large class are not invoked. Stored totals/names/status/history and
native form controls are not replaced by analytics catalog values. Removed
catalog products retain the same pre-existing product_id=null form behavior.

Native proof: actual pinned Event, Action, extension controller/model, native
account getProducts and pinned Twig execute before/after in8paired fictional
contexts: disabled, flags off, cart, view, full, category2, missing catalogue
product, no products. Uses the loader's actual four-reference argument shape
(route/data/code/output); output override remains empty and native template
buffer fallback reads actual old/new files. Registry/factory/catalog/authenticated
view data/config/language/tax/currency/DB results/session are explicit fakes.
The fake DB rejects every non-SELECT. Original totals/status/date/snapshot/form
fields stay unchanged except the same pre-existing product metadata updates.
Queries, model-load trace, catalogue calls, analytics JSON, session bucket and
replacement-anchor counts compare exactly before/after across all8cases.

Real Chrome with actual jQuery/common/analytics JS:24 paired contexts
(48documents at390/768/1280) have identical FormData and recorded view/merge
outputs before/after; no purchase event/new click tracking. One native reorder
click on each old/new full case produces two matching single loopback fake
POSTs, not duplicate requests. All external HTTP aborted, ps_dataLayer is a
local recorder, no real telemetry/cart/provider/business operation. Page errors
zero. These are hook interaction checks, not real GA4 delivery proof. Existing
R2 visual63layouts/17native contexts remain reusable because all nine target
payloads are exact unchanged bytes; no new production UI/CSS/JS is authored.

## Successor scope and safety

r3 admits only this exact fifth raw trigger/action pair. It also closes mandatory M1 from the first affected review: retain the conservative slash aliases and add the actual Loader dot-form events for all10 protected account/order methods, before and after. The classifier now catches unadmitted dot-form model handlers rather than silently ignoring them. No handler is newly admitted on model routes. Four previously
reviewed pairs remain, and the full admitted multiset must equal all five
captured rows. Aliases, broad patterns, wrong methods, duplicate/missing/extra
rows and other relevant hooks remain STOP. This is not a module-wide allowance
or an automatic admission generated from arbitrary diagnostic strings.

All nine compiled target contracts/payload bytes and66dependencies are exactly
the already-reviewed R2 versions. H1 token-only/D2/G0 decisions unchanged. No
ACC007 model/map/query/visibility/history/pagination, ACC002/008/009 or checkout
change. Source/backup/restore/lint/cache engine unchanged apart from task
identity and a fixed shared predecessor lock; only admission event-target coverage changes. Exact guards are source admission,
not application logic. Runtime hash guard still admits every dependency before
any backup/source write, and Apply rechecks the actual Verify admission.

New runner: patches/RD-18-WP1_orders-r3_20261027.php.
New owner packet: diagnostics/RD-18-WP1_owner-r3_20261027.ps1.
New private stage filename and local owner receipts under
work/rd18-admission-20261027/owner-receipts. No predecessor state import/reset,
old Verify/Apply replay or private stage cleanup. Old f2bd/2b271pair and its
uploaded-only state remain untouched/inert. Same logical private writer lock
`RD-18-WP1_orders_20261027.lock` prevents simultaneous old/new execution.

Expected backup prefix is now:
/home2/boosters/public_html/_patch_backups/RD-18-WP1_orders-r3_20261027-<UTC>-<12hex>/.
All9existing target backups original-0..8; target before/after states unchanged
from the R2 report/table. No actual backup exists yet. Restore views first,
header last; full live+backup preflight, partial before/after recovery and drift
STOP retained. No SQL rollback. No-op/header-only partial-state limit remains;
consumed-state STOP grants no replay/recovery inference.

Native PHP8.0.30 lint passes; updated gate tests25exact pair/mutation,
102legacy order-trigger,6broad,13theme plus exact five-row multiset tests pass. Actual pinned Loader::callback additionally emits20native dot-form events for the10 methods used by current RD18 controllers. The old classifier silently misses all20; the repaired classifier rejects60prefix variants plus9broad dot patterns. Factory/model/Registry/recording Event are fakes, no actual model/DB. The review said11listed methods; source/consumer inventory proves10, all covered. Exact pair hashes bracket every one of9 rerun jobs before/after and bind fixture/result hashes in bound-check-results.json. Native
file engine7cases with unchanged9actual payloads pass, including shared old
lock refusal, source/dependency/header/admission/backup drift and automatic
restore. Packet six-step/state/replay tests PS5.1+7 fully replace SSH/SCP
transport before invocation. Initial test fake SQL accepted only SELECT plus
space and missed newline SELECT; fixture regex corrected. Initial packet mock
still emitted the predecessor backup prefix; fixture corrected. Neither
failure caused a production/owner-packet change. Real MySQL, permissions,
enabled config/real analytics and native owner transport are not certified.

## Release boundary

Affected independent actual subscription Claude review must bind the new exact
runner/packet hashes and assess the actual callback and narrow admission. No
separate result/design exception call is needed for the known original STOP.
After acceptance, owner runs only the NEW packet Upload then Verify, returns
output before separate Apply. Expected RD18_WP1_R3_UPLOAD_OK then
RD18_WP1_R3_VERIFY_OK with lint/admission/production_writes=0/backup=none/done=ok.
Any new hook/source/dependency/runtime drift remains STOP, not proof that the
guard should be widened again. Apply remains owner-only, exact9targets,
self-delete, && template cache cleanup and RD18_WP1_R3_APPLY_OK_OWNER_QA_REQUIRED.

Owner QA after future Apply remains the R2 list/detail/snapshot/history/totals/
native-action/method-label/comment checks and public Tier1 smoke. No code or
canonical status is deployed/closed by local proof. No agent SSH/key/server,
real customer/order/DB/provider/analytics/cart mutation, Notion/dashboard or
Git write. No template/event setting repair or wider analytics work granted.


## Exact candidate identities

Runner SHA256 `bc6c7ab5cf137e1e5b3825f72980b4e1726e242c35bc66a892f1cdb3e0bedd60` (164754bytes); packet SHA256 `d7b0d9b12f30f2fca2cfaa67a388345910a2e9b79286f0af10cbf34da14fb20e` (11852bytes).

## First affected review and batched disposition

Actual subscription Claude Changes requested request fe3fd821-eae1-4197-8171-8d818597d748, response be51060f76f6d48976198ce366d9c49d46c7d60e4832def3243c6d6d7db13603. It binds only the initial unreleased4f567/6f4a pair, preserved in r1-reviewed. No owner successor receipt/state exists. New candidate rebuilt in the same r3 filenames; requires a fresh request, never replay the consumed review. Exact first121input hashes checked at completion and before repair. No R2 predecessor byte/state change.

- M1 mandatory: actual Loader dot-form coverage repaired and tested with actual emitted events (20old misses versus69new rejections). All10actual current protected account/order consumers covered; no order/query/status/model source change. Future Verify may correctly reveal another handler and STOP; the old5row capture does not imply it is absent.
- O1 optional: other catalog/manufacturer/language bodies and language/other-model hooks remain outside this declared admission/pin set. Analytics fixtures fake these services; no claim of comprehensive extension/whole-store runtime certification. No wider guard expansion or analytics repair.
- O2 optional:9rerun jobs bracket exact current runner/owner-packet hashes before and after, preserve fixture/result hashes and test boundaries in bound-check-results.json. Includes engine7, pure admission, actual Loader,8before/after hooks,24browser pairs and PS5.1+7six-step transport mocks.
- O3 optional: stale numeric twelve-dependency comment replaced with accurate complete-source dependency wording.
- O4 optional: predecessor old packet is superseded and must never run. Existing old stage/state left intact under the existing owner boundary; shared lock prevents overlap. No cleanup/delete action authorized.
- O5 optional: hook test renders through pinned Twig ArrayLoader directly rather than the production twig.php wrapper/cache path. Template buffers/actual callback and core4argument dispatch checked; production wrapper/cache behavior remains owner Verify/Apply/QA boundary.

No extra early-design/actual-result call. The next review is the required affected ready review of the batched M1 repair and coherent successor.
