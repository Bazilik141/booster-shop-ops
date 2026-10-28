# RD17 WP1B address pages and direct default action

Date:2026-10-26. Executor:Codex, owner-assigned.
Status:R2_REPAIRED_LOCAL_CANDIDATE_NOT_REVIEWED. Do not Upload/Verify/Apply yet.
RD17 WP1A profile bridge and compact checkout action are deployed and owner
accepted "qa ok". Continue RD17 only; RD18 stays unstarted. No Git/Notion or
dashboard status write; no agent SSH/key/server/deploy/DB/provider/customer call.

Owner G0 outcomes:separate add/edit form route(option1A), direct set-default
button without editing, no dedicated patronymic on address pages, keep existing
NP order. Profile-to-checkout names are the separately deployed prerequisite.
Owner scope amendment explicitly authorizes preparing the narrow default POST
writer for a future customer's deliberate own-address action; this is not an
agent DB write or migration grant. H1 is already granted for all RD17/RD18 WPs:
only DS cache-token value in header, convention8. No other header byte changes.

Source basis:27 exact files from owner capture10:51:48-49Z plus previously
accepted unchanged RD16 context.58 dependencies including login/Cart.Customer
and checkout address consumers. Every overlapping accepted WP1A-r3 dependency
matches. New runtime re-admits exact files, all-store theme rows, known event
multiset, alternate theme paths, account layout/module, address column names
and InnoDB engine, before any source writes. Metadata reads only, no business
rows. PHP8.0.30; no mysqlnd get_result. Verify/Apply stop on drift, no blind pin
replacement. No claim that source hashes prove live browser behavior.

Source causes and override history:
-address.twig:11 calls legacy column-left and address_list.twig:2-12 has table
 and icon-only actions; native controller:index59/form551 opt-ins invoke menu.
-address_form.twig:13-270 owns unscoped hardcoded visual rules including green
 submit. Those rules are removed at source; replacement address component rules
 go in DS under .bs-account-page. ACC002 hydrator/ACC008 address/ACC009 N2 history
 and RD16 shell/edit rules inspected, not reverted or stacked globally.
-Shared stylesheet.css:18 button:focus outline:none!important suppresses normal
 button outlines. New address controls use component box-shadow focus, no new
 important and no edit to that shared rule. Existing WP1A-UI's earlier isolated
 browser fixture did not load stylesheet.css, so its prior outline-only focus
 claim was narrower than the actual full cascade; that accepted package is not
 modified here. Do not represent that prior claim as full-cascade proof.
-RD16 mobile hub .bs-account-main display:contents is intentional. Address-only
 main box restores logical chip-nav->content order, like accepted edit opt-in.
 New address chip styles leave shared shell pair/hub/edit unchanged. Horizontal
 current-chip reveal scrolls only the nav strip, never the page. No timers.

Changes:
1.Full native Address.index/form opt into existing shell(active=address,page).
 getList adds safe stored city/point/courier detail/country-region projections.
 List intentionally omits recipient names per owner's accepted direction. It
 uses stored NP labels/house/flat only, with stored legacy fallback, no profile
 name/patronymic inference or placeholder address content. All new output is
 escaped; one true default badge.0/1/3/long/courier/escaped contexts exercised.
2.Separate add/edit form preserves every original field/control/hidden stub,
 generic custom-field rendering, NP DOM IDs and all existing country/NP script
 bytes.48px controls, blue save, Cancel returns by unchanged back link. No
 pattern, new patronymic field/prefill hint, NP initialization or payload change.
 Full NP helper/saveAcc008/delete source blocks are exact unchanged bytes.
3.New local account-address JS takes presentation handling of the SAME native
 form save action and SAME existing delete URL/method. It removes only that
 form's data-oc-toggle=ajax, leaving common.js alone; otherwise shared common
 would emit cart toasts. Native ACC009 capture validation and NP target submit
 handlers still run first; refused submissions never reach the new handler.
 Same jQuery.serialize data, server field mappings/values/redirects retained.
 Scoped account notice uses textContent/live region. Generic upload handler and
 its fields remain native. No second endpoint/writer or new delete confirmation
 is added; fresh source had no confirmation. Network/unconfirmed errors lock
 repeat mutation until reload. Accepted server failures unlock. Successful
 list mutation refreshes native list fragment; refresh failure advises reload.
 Existing one-use session.success consumption is unchanged. Interrupted redirect
 can leave a later stale server flash: cannot solve that in presentation without
 touching protected session/writer semantics, as accepted G0 already recorded.
4.Direct makeDefault is a new authenticated/token-validated owner-scoped POST,
 digit/size-bounded address_id only. Helper locks the current own address range,
 verifies ownership, switches ONLY own default flags in one CASE UPDATE,
 confirms exactly one selected own row, commits. Already-default is no UPDATE.
 Query/commit failure rolls back and exposes a generic error only. No address
 text/JSON/profile/NP/session/cache selection write. Cart.Customer.getAddressId
 queries flags on each call; no in-object cached default to invalidate. Native
 checkout shipping index uses current default only when no explicit shipping
 selection exists. Existing explicit selection stays, so no destructive session
 reset is introduced. New/default future lookup sees the stored choice.

Concurrency boundary for special review attention:the helper serializes its own
 concurrent range updates; real InnoDB scheduling is NOT locally exercised.
 Native legacy delete prechecks default outside its later DELETE, and existing
 form save/default writers have their existing transaction boundaries. They are
 frozen and untouched. No certification of global serialization across legacy
 delete/save/admin/checkout writers is claimed. A concurrent old delete can
 already race a default change through the current form. Assess whether the
 narrow new action changes that material risk or needs a protected-scope owner
 decision; do not silently infer authorization to rewrite ACC008/native writers.
 Normal same-page mutation single-flight is new UI protection, not cross-tab
 transaction proof. No schema/unique index/backfill or real test rows introduced.

Runner/owner packet:
Derived from accepted RD16-WP2 guarded file engine.9 targets,7 existing/2 absent.
Labels/DS/JS/helper first, controller/views next, token last. Reverse compatible
views/controller first, token/DS/language then remove new helper/JS. New files
backup=none; all7 existing originals before writes. Rollback all-target/backup
preflight handles partial restored states; post-write source failures restore.
Header current token read+shape/normalized non-token bytes checked; replace
wholesale. Complete content markers (append block exactly once, all replaced
targets exact) make no-op exit before dependency/token admission, even after
later independent CSS append/token change. No token used as installed marker.
No SQL/customer writes by source runner. The future application helper contains
only the owner-approved default-flag operation, never executed by deployment.
Private stage outside webroot; cwd public_html; same-device atomic renames.
All source apply/cache order with&&, one-use owner state and strict host/agent.
Owner Upload->Verify complete receipt->separate Apply->owner QA. Apply9 targets,
changed_files=9, emitted exact backup/original-index mapping, self_deleted=yes,
done=ok, template_cache_cleanup=ok, staged_runner_absent=yes and
RD17_WP1B_APPLY_OK_OWNER_QA_REQUIRED. Do not replay unexpected outcomes.
Backup:/home2/boosters/public_html/_patch_backups/RD-17-WP1B_addresses_20261026-<UTC>-<12hex>/.
Rollback restores source only; it cannot undo a customer's later legitimate
default choice or address save. No SQL rollback/data migration is proposed.

Checks and limits:
-Native PHP8.0.30 lint runner/controller/helper/language; new JS Node parse.
-Full new Address.index/form/getList and unchanged shell plus pinned native Twig
 rendered15 fictional contexts. Auth/models/config/URL/outer header mocked.
 Pinned mbstring DLL enabled for html_attr escaping; initial fixture/runtime
 configuration issues were fixed, not production defects. No Composer platform
 workaround or new dependencies were installed; existing production Twig used.
-Actual Chrome/current common/profile/universal/inline NP, captured Bootstrap,
 DS/stylesheet and local pinned font:38 layouts at390/768/1280,0/1/3/long rows,
 three native directory modes, five identical before/after control+FormData
 contexts, exact real serialized save payload pair, Latin blocking, account
 warning/server field feedback, narrow POST/default refresh and native delete
 method/error text safety. Other public ancillary CSS/full production header,
 live session, auth, DB/provider/order are not exercised. Images fictional only.
-14 native controller+actual Login.validate+helper cases with fake DB/customer/
 session/response:own success/no-op, foreign/missing/GET/bad token/logged out/
 malformed ID, begin/lock/update/commit/confirmation failures; exact unknown JSON,
 text/foreign rows/session retained. No real SQL/lock/engine proof locally.
-Native PHP file engine8 cases/actual9 payloads, runtime/dependency hashes fake:
 apply9/seven backups, dynamic token/no-op after future CSS, partial restore,
 backup/target/dependency/new-asset/header/lock/admission refusal and failure
 restoration. No Unix ownership/host permission/cache/MySQL proof.
-Native event classifier baseline19,exact-pair/mutations22,edit4,address24;
 alternate-theme13 and strict hook multiset. InnoDB metadata path needs owner
 Verify; real host engine/permissions not assumed from the file fixture.
-Owner packet AST/six steps/replay refusal on Windows PowerShell5.1 and7,
 whole SSH/SCP boundaries mocked. No agent transport/server action.
-New signature scan:no!important/timers/fixed. Absolute only existing NP dropdown
 source replacement, comments explain its geometry and232px native cap; conventional
 borders,focus2px,44/48px touch controls,800px old form width,999px inherited
 chip pills explained. No invented DS colors/tokens. Contrast token pairs retained.

All meaningful repairs need affected checks/fresh review. Current CODE verdict
pending; exact pair must be bound and every declared input unchanged at return.
Optional findings recorded, not automatic new tasks. After expected host receipt
close mechanically; owner aesthetic/function QA and full RD17 closure separate.
No owner real order/payment/provider/shipment/message/DB migration is authorized.


## R1 actual review and affected R2 repair batch

R1 request a1b9543d-8376-471b-a724-9c9cf1044ad8 returned Changes required,
binding runner0bcb27c2fa60fdb9c8104c5101d427c64e2cb3bc1dda46e2ab739762259cb41b
and packet858d2263ee64a38b40c62eaa06740155f1176c347bfb52d544a79b2885b78144.
All124 declared inputs unchanged at return; exact pair and original sources
preserved. No owner Upload/Verify/Apply was released or run for this candidate.

M1 repaired:non-JS OpenCart add/edit/delete URLs were already &amp;-encoded.
New template attribute escaping encoded them twice. Controller presentation now
decodes them once before escaping. Native route/auth/delete/save bodies remain
untouched. Test fidelity corrected:actual resolved Add/Edit/Delete href and
default/delete/save request query tokens/IDs and absence of amp;-prefixed keys
are asserted. No mocked-route glob alone can admit the original defect.

M2 exact owner evidence, verbatim from the original direct instruction:
"Так, ми у вкладці адреси можемо і надалі не відображати ПІБ, але це стосується
саме адрес у кабінеті, а не в чекауті."
The scope amendment now preserves the complete original quote and clarifies
that the prior patronymic-only summary was narrower than the actual authority.
No new owner question/decision/code change is required for M2. Address list
name omission stays authorized, and separate form first/last fields remain.

Executor additionally reproduced malformed stored labels causing Array-to-string
warning under the full native list controller. New card-only shape admission
checks scalar metadata before calling the protected hydrator, otherwise uses
the stored stock address text. Tests now cover label/type/house arrays and
exact fallback. The original form hydrator/NP/save/delete/guard/model/JSON
are unchanged. This is a substantive repair included in the same fresh review,
not claimed covered by R1. extra-corrupt-r1-finding.json records the failing
native PHP case, and test-render now passes15 contexts.

Adjacent bounded presentation repairs O1/O2/O4 share this batch:exact native
991.98/767.98 breakpoints, one entity decode before safe Twig escaping of stored
card text, and marker-validated list fragment replacement (login/full-page HTML
is refused). Tests include stored quoted/ampersand text and unexpected login
HTML retaining the existing list. New stylesheet/JS/list hashes are reviewed
here; no hidden extension of protected writers. O3 extra isolation-level fact
is deferred; O5 existing explicit checkout selection remains in owner QA.

R1 concurrency disposition:no materially new unresolved risk and no protected
writer change required, within the explicit existing-race boundary. This is a
review judgment, not native InnoDB scheduling proof or a global guarantee.
New helper, runtime/event/admission/transaction/rollback mechanics and58
dependencies are unchanged. R2 normalized runner excluding only target-table
embedding is byte-identical. Only four target payloads changed, as listed in
r2-repair-proof.json; exact old/new diffs retained. Source before states and
rollback contract remain the same, so no owner-source recollection is needed
unless host Verify observes drift. Existing event/theme tests reused by exact
unchanged function identity. Updated native render15/browser38, default14,
file-engine8 and six owner-packet steps5.1/7 all pass with declared fake limits.
Original r1 failures are preserved; known inadequate assertions are corrected.
Fresh R2 subscription Claude CODE review of the complete affected delta and
new exact executable pair remains required. No release based on R1 verdict.

Exact candidate pair:
{
  "runner": "patches\\RD-17-WP1B_addresses_20261026.php",
  "runner_sha256": "4fdcb89f6a652a6a6fc270cfbbf51757bdf998b1c7394a345c228230f0194523",
  "runner_bytes": 201799,
  "packet": "diagnostics\\RD-17-WP1B_owner_20261026.ps1",
  "packet_sha256": "88f9f06452ca3c0f2daf27eb787ee9d3b971c3ba8b9e443024ea7fc81a3be5dd",
  "packet_bytes": 11840,
  "targets": 9,
  "new": 2,
  "dependencies": 58,
  "H1": "granted; header token only",
  "status": "LOCAL_CANDIDATE_NOT_REVIEWED",
  "production_DB_writes_by_runner": 0
}

|Target|Before|After|Backup index|
|---|---|---|---|
|catalog/controller/account/address.php|462560a1f0d9c2c2c59523a5cab5de320620716a1e0985dc2cbb36986b21dff2|6bf596790c62b0d0832fb7531c2f1481e81fbdbd0ded54d56f6a627f966151fc|original-0|
|catalog/view/template/account/address.twig|d112f21ae397d390e06ebb1f828b3b2e2b1a838ef2931df8ed1aa435415427c2|1a32571f0e35995eef1c62b4718bf2e8a0a76314c764719e249e915384c6fed8|original-1|
|catalog/view/template/account/address_list.twig|8d8e5b454ace66b2b515825997eaade60ad0c2a899cbe0c7607a811fb2cbf20e|77e9681e7a132049e073ef8d3f9a220d4d022c8d2c5d79431c7b0305680f7ac3|original-2|
|catalog/view/template/account/address_form.twig|a5d2ad5d4d08406994c00583c722c825c45000c05097c7963d44231ba03ed39f|67929316263952efc6c6840dcf1789d5a9a5f56cb72e4e68574f3136b6574980|original-3|
|extension/ukrainian/catalog/language/uk-ua/account/address.php|a3a1a85269f27a755d629e6e3de0224e600f6da9eb48d3b9e79b5173c25c5cf2|ca6c35e0707650c5ace0333ba2bcd6004027aa3866fa5134144d364963aab592|original-4|
|catalog/view/stylesheet/boostershop-ds.css|e1d33339b3056de105b430c0a7e5153a4ae3e4be32922f45126118f3b4a2696f|44a3bd466cc4914975ce1807a65f7db809bf49546b828eacbaa970c6235bc995|original-5|
|catalog/view/template/common/header.twig|66b626a4b99fc6e563fb68754dc49ec3eedce7a509349b43bd5c3bcb2f1d5a51|686742eb5f0fff2d3bcf3f8cf2e2b8d2ef2b74263813e8f3b450b02e084ddfbd|original-6|
|catalog/view/javascript/booster-account-address.js|absent|4954bc45f50ac0784052e75b7a262ed0ed5e7a40e119d9ffd9eb91300194f16b|none|
|system/library/booster_address_default.php|absent|5311979eebccbfba4305b3699fe54e690e587686a6cd3dc784c6ed3720ea9dc6|none|
