# RD17 WP1C compact default-address checkbox

Date:2026-10-26. Executor:Codex, owner-assigned.
Status:OWNER_APPLY_AND_QA_ACCEPTED. Owner Apply completed 2026-10-26; final owner QA accepted 2026-10-27. RD17 deployed and owner-QA-passed; see RD-17_closeout_report_20261027.md. Earlier pending statements below are historical stage records.

Owner reports address/default/save functionality OK after WP1B deployment,
but requests a short checkbox in place of stock yes/no and the long sentence.
The private screenshot was inspected locally only. No image or actual names/
address contents are sent to peer review. Test data and previews are fictional.
Address functional acceptance is aggregate, not invented individual live tests.
Overall RD17 visual QA remains open; RD18 stays unstarted. No Git/Notion/dashboard
status or agent server/key/deploy/customer/DB/provider action is authorized.

Checkout observation resolved without a checkout change:owner explicitly says
another address was already selected. Current shipping.index31-37 uses the
stored default only when shipping_address session is empty; it preserves the
existing selection. WP1B deliberately changes only own default flags, never the
checkout session. This is the previously reviewed contract and matches the
reported case. No new diagnostic pull, automatic overwrite or checkout writer
is needed. No claim that a fresh empty checkout failed is made. Existing explicit
selection can be changed through the checkout's own list. Neither receiver/
profile names nor NP/payment/readiness logic is changed by WP1C.

Owning visual cause:the installed address_form.twig227-240 has a long title and
two native default radio controls inside default-card/options. Its five own
DS rules at7521-7525 were introduced by WP1B and produce the wide gray card.
Override history checked in current source and own WP1B authoring:these are
address-component rules, not shared shell/general control rules. Replace those
five literal source rules at their own location; no CSS stacking, important,
new override layer or global selector edit. The old inline form CSS had already
been removed by accepted WP1B and is not reintroduced.

Exactly three targets, all existing:
-address_form.twig:replace only default group plus a task marker. One native
 checkbox, label from existing entry_default=Основна адреса. Hidden default=0
 precedes checked default=1. Native PHP form parsing takes the last value,
 so checked submits1, unchecked submits0, matching the old radio contract.
 No application JS, PHP, language, guard, model, NP or endpoint is changed.
-DS:replace the own five old rules with the small new marked block. Native20px
 check mark/DS blue; label hit area44px; concise font, keyboard focus/hover/active.
 No gray wide panel. Existing DS spacing/color tokens only, no new hex values.
 New CSS has no important, timer or absolute/fixed. Conventional2px focus ring
 and44/20 touch/glyph dimensions are commented. Old NP absolute dropdown and
 timers persist unchanged outside this diff; do not classify them as new code.
-header:change ONLY existing DS token value under already-granted H1. Convention8
 reads/replaces current well-shaped token wholesale; non-token bytes pinned.

All original form source outside the default group/marker, including the complete
country/NP script tail, is byte-identical. Six other installed WP1B targets are
byte-identical. Native default constraints remain:unchecking the sole/current
main address may be rejected by the existing server rule, just as choosing No
was before; do not imply the UI now permits removing the only default. First
address auto-default rule remains in the protected writer. No new business rule.

Current source basis:owner WP1B Apply20:34:11Z, all9 exact after hashes,42 template
cache removals and staged-runner absence. Backup and complete receipt retained.
The materialized current source is the accepted27-file source plus those nine
after-images, not a guessed new live pull. Reuse accepted unchanged evidence;
host Verify re-admits three before states and64 dependencies. No blind re-pin.
H1 does not need another question. Parent source/old receipts untouched.

Runner is derived from the exact accepted WP1B R2 runner. Same metadata/event/
theme/layout/InnoDB/ownership/private-stage/runtime admission, with64 dependencies
(original58 plus six installed non-target WP1B after-states). No new application
writer or DB/customer row read/write in source deployment. New CSS source-splice
branch validates one exact old block, replaces with exact new block, then checks
the whole file after-hash. It uses literals, no runtime regex or blind override.
The no-op branch requires exact new block once plus other installed target
states and normalized header; survives a future independent CSS append/token
without retouching the token. This is not certification of unrelated later CSS.
Shared private lock deliberately remains RD-17-WP1B_addresses_20261026.lock to
serialize overlapping parent/current writers. Distinct WP1C task/backup/state.

Apply CSS(index1),form(index0),header(index2); rollback form0,header2,CSS1.
All three source files backed up as original0/1/2 respectively (actual names
original-0/original-1/original-2); no new files/backup=none targets. All-target/
backup preflight and partial rollback preserved; post-write failure restoration
tested. Backup pattern:
/home2/boosters/public_html/_patch_backups/RD-17-WP1C_default-checkbox_20261026-<UTC>-<12hex>/.
Private stage outside web root:
/home2/boosters/private/booster-patches/RD-17-WP1C_default-checkbox_20261026.php.
Owner state:work/rd17-default-check-20261026/owner-receipts/.
Rollback WP1C first before an old WP1B recovery; its old form/DS/header pins do
not consume the current child after-state. Future writers must use current
after-images or a reviewed compatible successor. No rollback is run or needed.

Checks (local, not production acceptance):
-PHP8.0.30 lint exact runner; actual native Twig under pinned render dependencies
 and existing mbstring. Full unchanged Address controller and shell render17
 fictional contexts, including first/new/default/nondefault/long/courier cases.
 Auth/models/config/URL/outer header fake. No real DB/checkout/customer/provider.
-Actual Chrome/current full common/profile/universal/account-address/inline NP
 scripts plus captured Bootstrap/DS/stylesheet and pinned font:15 layout cases,
 390/768/1280,new/edit/default/nondefault/32-character names/courier. One labelled
 checkbox, no yes/no/long title,44px label, no overflow; click label, Space,
 focus-visible,hover/active pass. Actual jQuery save requests hit fake HTTP;
 native PHP8 parse_str consumes those real serialized bodies. Checked and off
 before/after parsed POST maps are identical, all non-default controls identical,
 one request only, native Latin-name blocking preserved. No claim that raw
 checked serialization is byte-identical:duplicate default values are intentional
 and parsed to the same scalar. No new save writer/handler/client mutation.
-Actual PHP file engine8 cases with final3 payloads and fake runtime/dependency
 hashes:three replacements/backups, own CSS splice/no-op after later append,
 partial restore, corrupted backup, target/dependency/header/shared-lock/admission
 refusal, independent bad-block anchor refusal, post-write source restoration.
 Unix ownership/MySQL/host/SSH not exercised by local fixture.
-Exact owner packet AST/six steps/replay refusal pass on native PowerShell5.1/7,
 whole SSH/SCP boundaries mocked. No agent transport or credentials read.

One integrated independent subscription Claude review required for the coherent
ready pair. No separate design/command-author/result call is needed. Meaningful
repairs need affected checks/fresh review; expected complete owner receipts close
mechanically. Original screenshot never exported. Reviewer sees text/code and
image hashes only, not images; owner aesthetic QA remains separate.

After Review OK plus identity closure:owner Upload->Verify; return complete
receipt before separate Apply. Verify expects admission64hex,syntax_gate=ok,
production_writes=0,backup=none,done=ok,RD17_WP1C_VERIFY_OK. Upload marker
RD17_WP1C_UPLOAD_OK. Apply expects3 exact states and backup,changed_files=3,
self_deleted=yes,done=ok,template_cache_cleanup=ok,staged_runner_absent=yes,
RD17_WP1C_APPLY_OK_OWNER_QA_REQUIRED. Cache runs only after source success with&&.
No replay on error/drift/ambiguous receipt. Final owner checkbox visual/save QA
still required before full RD17 closure or any RD18 work. No real test order,
payment/provider/shipment/message or data migration is authorized.

Exact candidate pair:
{
  "runner": "patches\\RD-17-WP1C_default-checkbox_20261026.php",
  "runner_sha256": "5821c91f9e5f649cbfec65623ee3c118ccb1dd01e4804e592aad334c3165853a",
  "runner_bytes": 99726,
  "packet": "diagnostics\\RD-17-WP1C_owner_20261026.ps1",
  "packet_sha256": "7fa079594688c4389e3021a6ce9bdc3fedc08f9b9c6f2ea661b2945daa771fb4",
  "packet_bytes": 11875,
  "targets": 3,
  "existing": 3,
  "new": 0,
  "dependencies": 64,
  "status": "LOCAL_CANDIDATE_NOT_REVIEWED",
  "H1": "already granted; only current DS token value",
  "shared_lock": "RD-17-WP1B_addresses_20261026.lock"
}

|Target|Before|After|Backup|
|---|---|---|---|
|catalog/view/template/account/address_form.twig|67929316263952efc6c6840dcf1789d5a9a5f56cb72e4e68574f3136b6574980|e7ad580e87875565d129c08ee0f223262e3a86c51832f2d5b083bc85fbadaf50|original-0|
|catalog/view/stylesheet/boostershop-ds.css|44a3bd466cc4914975ce1807a65f7db809bf49546b828eacbaa970c6235bc995|f1411862e5aed5da5e0da32b41e49e22e78f4ca8d297a7367bcac7b7be89ba67|original-1|
|catalog/view/template/common/header.twig|686742eb5f0fff2d3bcf3f8cf2e2b8d2ef2b74263813e8f3b450b02e084ddfbd|dfeb11467afd43eb760bc95ea1cb0f38a0640e85ce3a90c77f5439fb7a2f33a2|original-2|


## Accepted independent review and mechanical release closure

Actual subscription Claude Opus5-5/high returned Review OK with no mandatory
findings, requestbeb4ec74-8e04-42de-ba15-e930625cea33. Exact reviewed pair remains:
runner5821c91f9e5f649cbfec65623ee3c118ccb1dd01e4804e592aad334c3165853a;
packet7fa079594688c4389e3021a6ce9bdc3fedc08f9b9c6f2ea661b2945daa771fb4.
All114 declared inputs match at completion; no tracked content changed during
review. Native Get-FileHash confirms the pair; owner receipt folder absent.
Review original result/run/response/identity closure are preserved under
checkbox-ready-r1 in diagnostics/RD-17_WP1C_review_evidence_20261026.
Response SHA256 a8f848e00d8b7c5660255797916a0926258c61a951a8d9d0cd9828b0d7f73e7a.
Subscription verified, no tools/MCP, no API or paid fallback. Original report
and release-facts retained before this verdict/status-only local bookkeeping;
code/commands/tests are unchanged. No extra result model call is required.

Review limits and optional dispositions recorded, no new task/code change:
-the reviewer read complete current runtime code but did not recompute its
 identity against parent body; executor's exact native comparison/static hash
 supplies that evidence. No visual screenshot inspection by the reviewer.
-rollback order may briefly republish old token before old CSS. Cosmetic narrow
 rollback-only window recorded; no rollback/recovery is requested or run.
-existing guard rejects unsetting current main with legacy deletion-worded
 error_default. It is unchanged from selecting No; owner QA should recognize it.
-no-op marker can report installed content after an unresolved restore with old
 token; owner packet already STOPs and never permits replay/reset. No recovery
 inference follows a no-op. Preserve consumed owner state on every unexpected
 outcome.
-minor typography/inherited classifier comments remain; accepted literal CSS
 token values and actual source/tests are authoritative, not stale comment counts.
-review's suggested dark-theme QA is outside the explicitly assigned light-A
 scope and deferred RD29. It does not grant RD29 work or waive required light-A
 owner QA. Existing theme preview remains unavailable/unchanged. No dark work.

Release only exact owner packet -Step Upload then -Step Verify, with full output
returned before separate Apply. Expected RD17_WP1C_UPLOAD_OK, admission64hex,
syntax_gate=ok,production_writes=0,backup=none,done=ok,RD17_WP1C_VERIFY_OK.
Any target/dependency/header-non-token/metadata drift is STOP, not a blind re-pin
or retry. Apply and final owner mobile/desktop visual and saved0/1 confirmation
remain separate gates. Existing checkout selection remains by reviewed contract.
No source deployment yet for WP1C, no full RD17 closure or RD18 execution,
no agent server/key/DB/customer/provider/Git or canonical status write.


## Owner Upload/Verify accepted; separate Apply ready

Complete saved owner Upload21:21:23Z and Verify21:21:26Z receipts and local state
match the exact independently reviewed pair. uploaded=true,verified=true and no
Apply attempt. PHP8.0.30 at/usr/local/bin/php; runner lint passes; admission
6d0c085c0b2ff4ece2a527e467d2ae324a96a0d1a38346084e0f55e5823d41c0.
syntax_gate=ok,production_writes=0,backup=none,done=ok. Owner console reports
RD17_WP1C_UPLOAD_OK and RD17_WP1C_VERIFY_OK. Receipt/state hashes retained in
diagnostics/RD-17_WP1C_review_evidence_20261026/owner-Verify-success_20261026.json.
This expected result closes mechanically under canonical policy, no new Claude
call or executable change. It proves host admission/lint only, not deployment
or aesthetic acceptance. Existing selected checkout remains untouched.

Owner may now run the exact RD-17-WP1C_owner_20261026.ps1 -Step Apply once.
It recomputes admission and backs up all3 existing files before source writes,
then installs own CSS, form and token, and clears template cache only after
source success. Expected per-target states as table, backup/original-0(form),
original-1(DS),original-2(header),changed_files=3,self_deleted=yes,done=ok,
template_cache_cleanup=ok,staged_runner_absent=yes,
RD17_WP1C_APPLY_OK_OWNER_QA_REQUIRED. Return complete result before any repeat,
rollback or acceptance. Drift/error/ambiguous result is STOP; preserve consumed
state, never reset it. Agent did not deploy or access server/keys/data/provider.
After accepted Apply, owner mobile/desktop checkbox appearance and saved checked/
unchecked behavior still require QA. Full RD17 not Done; RD18 still unstarted.


## Owner Apply and final QA accepted (2026-10-27)

Exact saved Apply receipt and applied/consumed state checked: all three before/after and backup paths match, PHP8.0.30/syntax gate passed, changed_files=3, self_deleted=yes, done=ok,42 templates removed, cache cleanup OK, private runner absent. Backup: /home2/boosters/public_html/_patch_backups/RD-17-WP1C_default-checkbox_20261026-20261026T212236Z-57c2094a8236. Complete expected receipt closes mechanically, no extra Claude call or changed executable bytes. Owner now reports “done. QA ok”; accepted as final checkbox QA, retaining earlier address/default/save and profile UI acceptances. Public anonymous representative smoke: eight page types HTTP200, no captured fatal marker; three first-pass timeouts resolved in one bounded follow-up. Retired FIG-CHARM-001 replaced by the owner-provided current Charizard3D URL, as in ACC009 accepted smoke; no claim the obsolete literal passed. Checkout entry with empty anonymous cart redirects to cart. No authenticated/provider/log-specific proof invented. Source/QA evidence: owner-Apply-QA-success_20261027.json and public-smoke[_followup]_20261027.json in this evidence directory. RD17 release/QA complete; no Notion/dashboard/Git mutation. RD18 may start WP0 only, with fresh-source G0 stop.
