# ACC-008 address-edit confirmation — r16 owner-applied and QA accepted

Date: 2026-10-16. Executor: Codex. Workspace: C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops.

## Current actual verdict and owner gate

Final r17 actual-result assessment now accepts the composed r11/r13/r16 deployment/owner QA and bounded preservation/smoke evidence: Actual-result Review OK, no mandatory findings. Full verdict diagnostics/ACC-008_actual-result_claude-review-r17_20261016.md, independent50-input/Git closure diagnostics/ACC-008_actual-result_evidence_20261016/review-r17-result-closure.json, current final report diagnostics/ACC-008_actual-result-r17_report_20261016.md. No more r16 runner execution is needed. External statuses and WP3 permission remain untouched.

Latest native result: owner checksum/dry-run at19:14:51Z and apply19:14:52Z passed on PHP8.0.30 with the exact accepted SHA,103-source/no-OCMOD/SELECT-only admission, one controller/native lint/files_changed1/done=ok. Source backup ../_patch_backups/ACC-008_address-confirmation_20261016T191452Z-d8256667. Owner directly says QA-ok for the focused delivery checks; no granular URL/empty-name/timing proof is invented. Fresh post-r16 anonymous8-page smoke passes final200/uncapped HTML/no tested basic PHP signature. New receipts/QA/smoke are in this task's evidence directory. The one-file follow-up is applied; do not reupload/rerun. Current final actual-result report is diagnostics/ACC-008_actual-result-r17_report_20261016.md; G2 remains pending that actual independent assessment. Earlier owner commands and pending-apply wording below are retained historical references.

Actual r16 Source Review OK for this one-controller CODE/composition only, mandatory findings none. Full actual verdict diagnostics/ACC-008_address-confirmation_claude-review-r16_20261016.md was read. Request2e6e2867-aec9-4644-b636-e65ade577c2f completed through original PID45168/session54697, exit0, actual Claude Opus5.5/high, verified saved subscription, isUsingOverage=false, no tools/API key/fallback. Independent review-r16-code-closure.json verified all61 inputs and Git HEAD/index before these documentation-only updates. Patch/checker/test code is unchanged. This does not accept deployment, owner QA, final actual results, G2 or WP3; never replay the consumed request.

Owner takes a fresh DB dump, retains it privately, uploads the exact runner above public_html and runs the single reviewed chain:

```bash
(
  cd ~/public_html || exit
  acc008_patch='../ACC-008_address-confirmation-r16_20261016.php'
  printf '%s  %s\n' 'dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e' "$acc008_patch" | sha256sum -c - &&
  php "$acc008_patch" --dry-run &&
  php "$acc008_patch" --db-backup-confirmed
)
```

Expected dry-run: one host_payload_lint for address.php, exact reviewed103-source/no generated OCMOD admission, both cache mappings ../ocartdata/storage/cache, literal config admission,112 events/no active startups/relevant overrides, db_preflight=read_only_ok/dry_run=ok/files_pending=1/done=ok. Expected apply: ACC-008_address-confirmation_ source backup, exactly one backup_file/changed_file for account/address.php, php_lint=ok/files_changed=1/done=ok. Known MYSQL_OPT_RECONNECT deprecation is nonfatal. Return complete output and keep the printed backup basename; any other error/nonzero/unexpected state stops for assessment, no bypass/retry. No Twig cache deletion.

Owner QA on an existing own/test address: change only recipient name and save with NP selection kept, remain logged in on that same saved address, see its normal green alert without cart title/actions and persisted values/NP selection. Privately verify URL contains correct address_id/customer_token and no amp; keys; do not send the token URL. Reload removes the message. Empty-firstname save gives inline validation with no redirect/success; restore valid name. Existing add-address live check is optional, not a requested new record mutation. Owner-deferred responsive checks are not re-requested. Reviewer permits waiting a few seconds and repeating one save only if the first still shows the old toast, to allow OPcache timestamp revalidation, then reporting results. Return QA and receipt for the fresh coherent final-result review; no WP3 yet.

Rollback if required: reupload the same r16 artifact and use the actual printed source-backup basename:

```bash
(
  cd ~/public_html || exit
  acc008_patch='../ACC-008_address-confirmation-r16_20261016.php'
  read -r -p 'Paste r16 source backup basename: ' acc008_backup || exit
  printf '%s  %s\n' 'dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e' "$acc008_patch" | sha256sum -c - &&
  php "$acc008_patch" "--rollback=${acc008_backup##*/}"
)
```

Rollback restores only this address controller, keeps profile-r13 installed and needs no Twig cache cleanup. It restores source, not data. Optional review findings are retained without another provider round: future URL-body gate, low-impact stale generic flash, unchanged delete/warning toasts, unchanged native alert accessibility and the basis-text typo.

## Current scope and root cause

The owner confirms r13 fixed profile confirmation, then reports a saved-address edit still displays the cart title/actions around the address-updated message. Private screenshots were inspected only in conversation and never copied or sent to a reviewer. This is the previously documented generic AJAX limitation outside the profile-only patch, not evidence that r13 changed address behavior.

Baseline catalog/controller/account/address.php:734 sets json.success=text_edit after the successful existing-address save/commit/session refresh. Current common.js:194 sends every generic AJAX json.success to bsToast, whose title/actions at72–104 are cart-specific. Override/history check: RD-12_minicart-toast_20260921.php and RD-12-UI-FIX_toast-canonical-rebuild_20260922.php route generic success through bsToast; later layout/polish patches retain it. R13 intentionally avoided shared common.js/header/CSS under parallel ownership. This follow-up retains that boundary.

Existing form() already consumes session.success once, and address_form.twig:8–10 already renders its standard green alert. No template or visual style change is necessary. Replace only the existing-address success response with the existing session.success message plus a raw=true same-form JSON redirect carrying current language/customer token/integer saved address_id. The user remains on that saved address's edit form. The existing ordinary banner appears and is consumed by the existing form() implementation; reload removes it.

## Candidate and preserved behavior

Runner: patches/ACC-008_address-confirmation-r16_20261016.php.
SHA256: dfbec3da8b8c2c45ba3e4f1e30cb2fdb7dc5931e1c4de7ee83193a6689296b0e.
Exactly one target: catalog/controller/account/address.php.

The builder mechanically reverses the exact changed response block and requires byte equality with the owner-admitted baseline. Guard calls, write/commit order, NP validation/directory/model calls, custom_field merge, shipping/payment address refresh/invalidation, authentication and ownership stay byte-identical. Add-address/registration/First15 redirects, delete behavior, other controllers and all Twig/CSS/JS stay byte-identical. The old cart-styled delete/error messages on other paths remain outside this requested save-confirmation fix. No shared assets, GA4/wishlist/header/media/photo, auth/checkout/NP business logic edits, live DB writes, Git/Notion/dashboard writes or WP3.

Baseline freshness: owner r13 dry-run/apply at2026-10-16T18:33:36Z admitted the exact103 source identities and metadata. Address controller/template remained r11 identities; the r16 admission advances only the two already owner-applied profile-r13 identities. New runner rechecks all103 sources/no generated OCMOD and the same SELECT-only config/metadata/startup/theme/engine gates before writing. Drift requires assessment, never bypass. Runtime admission is byte-identical to accepted r13; runner changes are task filename/backup prefix/marker/title plus new payload and admitted r13 baseline.

Backup prefix ACC-008_address-confirmation_. One file identity/idempotence marker, private source backup, host payload/prewrite lint, staged lint/restore-on-fail, manifest rollback and self-delete are retained. Rollback restores this one controller to its pre-follow-up bytes and leaves the profile-r13 fix installed. If undoing the full series, reverse order r16 then r13 then main r11; never manually delete the guard. Source rollback cannot restore overwritten data. Fresh owner-held DB dump confirmation is inherited and required; dumps/config/customer records must not be sent to agents.

No Twig files are changed, so compiled-template cache cleanup is not needed. Earlier public runtime evidence reported OPcache timestamp validation/revalidate2, but that is historical context; owner QA must verify the new response actually executes.

## Local evidence and limits

Native local PHP8.3.30 lint passed for patch, controller and confirmation checker. All164 real-function fictional regression cases pass with accepted profile-r13 sources present, plus14 address save/form/real Twig cases,71 exact admission cases and8 runner lifecycle cases. Three discriminating mutants fail: restoring json.success, removing raw=true, and redirecting to the list instead of the edited form. Confirmation cases cover saved address_id/token/language, committed save before flash, one-use real banner, reload, inline failure/no redirect, unauthorised/cross-customer refusal, commit failure/no flash, invalid NP/no flash, existing shipping/payment refresh and add-address existing flow with/without required-after-register flag. Nonempty-cart First15 branch is unchanged by byte equality, not newly exercised here.

The first reused preflight fixture still selected the profile payload target for its partial-state test and failed locally; the fixture was adapted to the one address target. Its original output is retained at work/acc008-address-confirmation-20261016/preflight-initial-fixture-failure.txt. Candidate code did not change; the final full check pass is warning-free. The only base roundtrip fixture addition is a fictional getZonesByCountryId method required by the real storefront form renderer. Other164 cases are retained.

These are fictional DB/directory services and actual PHP save/form/Twig functions. No new authenticated hosting, JS-browser, InnoDB or live NP result is claimed. Existing generic common.js redirect/raw=true behavior is supported by exact accepted source identity and owner-passed profile-r13 flow. No new JS/CSS/template is proposed. Owner declines additional live responsive checks of the old design; defer them honestly, do not mark passed or ask again.

## Composition with prior owner results

R11 deployment/QA, r15 selected-address comparison and r13 owner apply/QA are recorded separately. R15: all four owner-confirmed form steps/all3 addresses, bs_np_v1 preserved3/3, customer keys0, other tracked keys absent, result=UNCHANGED/cleanup=ok/done=ok. This is final-state equality across the sequence before r13, not per-write or absent-key proof. R13 owner profile screenshots show standard green confirmation, inline empty-name error, changed-email success; owner confirms reload behavior. Exact URL syntax/reload timing are not independently captured. Owner hosting anonymous curl returns8/8 final200/exit0; empty checkout redirects to cart and bodies are discarded. Earlier Codex route timeouts remain retained with unknown cause.

The address-confirmation defect keeps final-result review/G2 open. Review this one-controller CODE and its interactions with the already applied r13/main guard and the current owner evidence. If CODE accepted, provide the owner one checksum/dry-run/apply block, expected files_pending1/files_changed1, backup/rollback and focused existing-address save/reload/invalid-name QA. No template cache deletion, new data seeding, paid API fallback, deployment/status authority or automatic WP3 progression is implied. Only after owner application and QA can the coherent actual-result stage be completed.
