# Codex Report — ACC-008: owner r11 apply confirmed; authenticated QA pending

Date: 2026-10-16. Executor: Codex. Workspace: C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops.

## Current owner result — source apply complete, G2 still pending

Latest owner QA: positive persistence/isolation/admin behavior, with a pre-existing profile success message rendered as a cart toast. A separate two-file follow-up now has actual r13 Source Review OK and is awaiting owner apply/QA: diagnostics/ACC-008_profile-confirmation_report_20261016.md. No hidden-key before/after proof was supplied; G2/WP3 remain open/closed respectively. Do not repeat the historical r11 apply command.

Owner terminal evidence at **2026-10-16T12:09:07+00:00** confirms exact reviewed r11 checksum, unchanged successful admission metadata, backup of all eight existing target files, installation of the new helper and all eight replacements, five prewrite payload lints and five postwrite PHP8.0.30 lints, files_changed=9, done=ok, template_cache=cleared. The owner executed --db-backup-confirmed; the private dump itself was not ingested or independently inspected. Source backup: **../_patch_backups/ACC-008_custom-field-preservation_20261016T120907Z-d0ed554a**. The helper is new, so rollback removes it using the verified manifest rather than a before copy. Sanitized complete receipt: diagnostics/ACC-008_custom-field-preservation_evidence_20261015/host-apply-r11_20261016.json.

Successful code calls @unlink on the runner; its actual absence was not independently checked. Do not claim verified self-deletion or repeat apply merely to test it. Codex performed no hosting source/cache/DB write. The known MYSQL_OPT_RECONNECT warning remained nonfatal.

One public anonymous HTTPS smoke pass after owner apply returned final HTTP200/text/html for all eight Tier1 pages, with expected page titles and no basic PHP fatal/parse/warning signature. Checkout with an empty cart redirected to the cart, so this proves only the empty-cart entry path. The current homepage's Charizard 3D product link was used instead of retired FIG-CHARM-001. No authenticated forms, JS, visual state, nonempty checkout or NP/customer JSON behavior was exercised. Metadata-only record: diagnostics/ACC-008_custom-field-preservation_evidence_20261015/public-smoke-after-r11_20261016.json. An initial stdout-encoding error stopped the first smoke attempt before evidence was written; collection was corrected, with no site mutation.

Next dependency: owner QA on a controlled test customer/address, including private before/after custom_field comparison. G2 and WP3 remain unaccepted. Collect apply/public-smoke/owner-QA together for the next required actual-result assessment; no new source repair or extra optional-note review is implied by this clean receipt. Preserve actual r11 CODE review and its narrower boundary.

## Scope

Four authenticated storefront/admin customer/address forms reload persisted custom-field JSON under a transaction lock, merge only editable numeric fields, preserve service/unknown values and NP ownership, distinguish omission/clear and refuse malformed/lossy/duplicate JSON. No bulk rewrite, schema or runner DB write. The nine payload bodies are unchanged since actual r9 review: its reviewer reread them and accepted their logic in substance while returning the candidate for admission defects. R10 repaired canonical config grammar/paths/rollback/startup; r11 repaired actual admin event truthiness and related gateway concerns. No global positive r9/r10 verdict is claimed.

Actual r11 Claude Opus/high verdict: **Source Review OK for the runner/admission CODE boundary only**, no mandatory findings. It does not accept apply/deploy/production, complete G2 or authorize WP3. Full actual verdict: diagnostics/ACC-008_runtime-admitted-candidate_claude-review-r11_20261016.md. Request 92653326-9e0a-40eb-8a97-5334faf186aa completed via original PID 8492/session 20434, exit 0, saved subscription, isUsingOverage=false, no API key/fallback/tools/MCP. Full result read. Independent review-r11-code-closure.json verified all 137 frozen inputs and Git HEAD/index before these documentation-only updates. No code changed after review.

## Files touched

Ready for owner dry-run: patches/ACC-008_custom-field-preservation-r11_20261016.php.

SHA-256: **0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122**.

Nine payloads: new system/library/booster_custom_field_guard.php; catalog/controller/account/edit.php and address.php; adminEvhenii/controller/customer/customer.php and address.php; four existing profile/address Twig forms with hidden checkbox-presence markers. No shared CSS/header/media/photo/wishlist/GA4, checkout/registration/shared model writer, auth token, NP API/business logic, payment/fiscal/order/SEO, Git or Notion/dashboard write. Rejected r9/r10 artifacts are retained and must not be run; original r7 b5f37f9f...6da6 remains unchanged/apply-disabled.

## Dry-run result

Owner-host r11 dry-run at **2026-10-16T12:02:27+00:00** passed. Sanitized complete metadata and independent checklist comparison: diagnostics/ACC-008_custom-field-preservation_evidence_20261015/host-dry-run-r11_20261016.json. The owner terminal checksum matches this exact reviewed candidate. All five payload lints passed on CLI PHP8.0.30. The reviewed runner attested all101 exact source identities and no generated OCMOD, both literal/nonpersistent/same-target configs and their application mappings, five transactional engines,112 exact active event identities including status,27 disabled fields and27 group mappings, empty active startups and no relevant theme override. Both config-derived cache paths resolve to **../ocartdata/storage/cache**. Final lines: db_preflight=read_only_ok, dry_run=ok, files_pending=9, done=ok. No source apply or cache cleanup happened. The known MYSQL_OPT_RECONNECT deprecation is nonfatal.

All required output matches the actual r11 review checklist; no deviation or additional code repair is present. The actual reviewer expressly permits Codex to compare this clean result; another optional-note-only provider round is not needed. Earlier independent evidence established web PHP8.0.30/litespeed, mysqli8.0.35 and timestamp-validating OPcache. Those earlier web observations are not a new browser check.

Expected clean result: five host_payload_lint lines; runtime_sources=exact_reviewed_sources_and_no_generated_ocmod; both cache paths relative to cwd; config_admission=literal_nonpersistent_same_target_and_paths; event_rows_scanned between112 and512; active_startups=[]; metadata with112 expected status1 events; db_preflight=read_only_ok; dry_run=ok; files_pending=9; done=ok.

Any enabled startup, new PHP-truthy event, source/metadata/theme/config/engine/OCMOD mismatch or cap stop is evidence for independent assessment. Handler/table rows must not be disabled or edited to bypass it. All ordinary startup/event/theme inventories are printed before admission refusal so a combined issue does not need separate collection runs. Query/schema/cap failures can stop earlier and do not prove a complete inventory.

## php -l and local evidence

PHP lint passed for candidate, PHP payloads and test harnesses on local PHP8.3.30; the owner also passed all five r11 payload lints on hosting PHP8.0.30. Local-checks.json binds the candidate SHA to164 fictional real-function cases,71 exact generated config/preflight/source cases,13 copied runner lifecycle scenarios, six actual engine/model dispatch cases using a fictional DB and five mutants. Native application writes, browser JS and NP execution remain unproved. The status-filter mutant is detected by the fixture's SQL-shape assertion; separate truthiness cases exercise the runtime filtering behavior. Actual complete event/Twig owning contracts were supplied in r11; other unchanged payload/owning bodies were explicitly reused by identity, not claimed reread.

## Idempotency

Per-file exact before/after identities, marker and path/link checks persist. A full repeat returns already_applied=yes and self-deletes; --dry-run keeps the runner. Recognized partial states compose; unknown drift refuses. Both catalog/admin configs are parsed without execution with bounded literal/prior-local-constant concatenation; persistent or mismatched targets, wrong existing application/root/template/config paths and unsafe grammar refuse without printing values. Inactive event identities are not printed; all raw event rows are capped at512 and startup rows at64.

## Rollback

Source backups are created before writes at **../_patch_backups/<printed basename>/** outside public_html, with private directory permissions and verified hashes. After a later owner apply, retain the printed backup line. Reupload this exact runner and use --rollback=<printed basename> from public_html. Rollback verifies identities/manifest and refuses to remove the helper if an applied controller outside its pending manifest would be orphaned. Do not manually delete the helper or bypass that stop. Restoring sources cannot recover JSON overwritten after deployment; a fresh owner-held DB dump is required before any apply. No actual backup/restore/deploy is claimed here.

## Historical owner dry-run command — completed successfully

Upload the exact r11 file to the hosting home directory, one level above public_html. The block also recognizes the exact basename inside public_html if it was uploaded there; no broad HOME search or other runner is executed. Its checksum is checked before execution.

```bash
cd ~/public_html || exit
acc008_patch='ACC-008_custom-field-preservation-r11_20261016.php'
if [ -f "../$acc008_patch" ]; then acc008_patch="../$acc008_patch"; fi
printf '%s  %s\n' '0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122' "$acc008_patch" | sha256sum -c - &&
php "$acc008_patch" --dry-run
```

The complete owner output was received and matched the checklist above. Do not repeat unchanged collection. No cache cleanup or source apply was part of that command. No extra routine go request is needed; fresh held DB dump and owner-only deployment/QA remain real dependencies.

## Historical owner apply procedure — completed by the owner

Before running: download and retain a fresh OpenCart database backup through cPanel Backup. Keep it private; do not upload it to this chat/repository. The flag below confirms that prerequisite. Source rollback cannot recover contact JSON overwritten by application writes after deployment. Use the exact already uploaded r11 runner, preferably one level above public_html. If that file is absent, reupload the reviewed local artifact; do not select an older runner.

```bash
cd ~/public_html || exit
acc008_patch='ACC-008_custom-field-preservation-r11_20261016.php'
if [ -f "../$acc008_patch" ]; then acc008_patch="../$acc008_patch"; fi
printf '%s  %s\n' '0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122' "$acc008_patch" | sha256sum -c - &&
php "$acc008_patch" --db-backup-confirmed &&
if [ -L '../ocartdata/storage/cache/template' ]; then
  printf 'error=template_cache_symlink\n'; false
elif [ -d '../ocartdata/storage/cache/template' ]; then
  find -P '../ocartdata/storage/cache/template' -type f -name '*.php' -delete &&
  printf 'template_cache=cleared\n'
elif [ ! -e '../ocartdata/storage/cache/template' ]; then
  printf 'template_cache=absent\n'
else
  printf 'error=template_cache_not_directory\n'; false
fi
```

This command rechecks admission at apply time. Any source/event/config drift stops before writes. Cache cleanup is chained after successful apply and removes compiled PHP files only under the single template directory confirmed for BOTH apps; it preserves directories, index/deny files and unrelated caches. Find does not follow symlinks. No guessed system/storage path or config execution is used.

Expected: backup=../_patch_backups/ACC-008_custom-field-preservation_<actual timestamp-and-suffix>, five php_lint lines, files_changed=9, done=ok, then template_cache=cleared (or absent if no template cache directory exists). The successful runner attempts to delete itself. Retain the actual backup line and return the bounded terminal output. If apply fails, do not rerun blindly. If only cleanup fails, sources may already be applied; report that distinct state before proceeding. No deployment, backup creation or cache cleanup by Codex is claimed.

### Owner rollback only if needed

Reupload the SAME r11 runner above public_html. Run the following block and, at the prompt, paste the actual backup= value from this apply (without the word backup=). The runner validates the basename and manifest; no guessed backup timestamp is used. The source-only rollback also attempts to delete its own runner after success.

```bash
cd ~/public_html || exit
acc008_patch='ACC-008_custom-field-preservation-r11_20261016.php'
if [ -f "../$acc008_patch" ]; then acc008_patch="../$acc008_patch"; fi
read -r -p 'Paste backup path from apply output: ' acc008_backup &&
printf '%s  %s\n' '0b0628010d799cd25011210e60a6e56dd30b369511f3b03dc64b8ce6a9acb122' "$acc008_patch" | sha256sum -c - &&
php "$acc008_patch" "--rollback=${acc008_backup##*/}" &&
if [ -L '../ocartdata/storage/cache/template' ]; then
  printf 'error=template_cache_symlink\n'; false
elif [ -d '../ocartdata/storage/cache/template' ]; then
  find -P '../ocartdata/storage/cache/template' -type f -name '*.php' -delete &&
  printf 'template_cache=cleared\n'
elif [ ! -e '../ocartdata/storage/cache/template' ]; then
  printf 'template_cache=absent\n'
else
  printf 'error=template_cache_not_directory\n'; false
fi
```

Expected: rollback=source_only_ok and done=ok, followed by template_cache status. On any refusal, especially rollback_would_orphan_applied_controller, stop and send the error; do not delete helper/controller files manually. A DB restore is a separate owner decision, not part of this command.

## Post-deploy QA — current owner gate

Use one controlled test customer and its own existing addresses. No real customer/order dump or raw JSON should be returned to agents. Before form saves, privately retain that test customer's and the test addresses' current custom_field values through the owner's own DB view. Compare after each corresponding save: existing bs_first15_pending, bs_np_v1, rd13_receiver_middlename and unknown/nested keys must retain their values. A key absent from test data is not exercised; report that gap rather than create production metadata or reactivate disabled sample fields. Existing27 custom fields remain disabled; live checkbox/clear semantics cannot be claimed from these visible forms alone and remain covered by fictional fixtures until an approved live field exists.

Core owner sequence:

1. Storefront account edit: change only the test customer's first name, save, reopen and verify persistence plus unchanged other fields/service JSON.
2. Storefront address edit: change only the test recipient's name, save and reopen. Existing city, region, warehouse/poshtomat/courier and printable NP address must remain consistent. Compare address custom_field privately. Exercise available test delivery variants; record missing variants honestly.
3. Admin customer form: edit only the same test customer's first name, save and reopen. Verify normal success and private customer custom_field preservation.
4. Admin address form: wait for region options to load, edit only the test recipient's name, save and reopen. Existing NP references/text and other address values must survive. An unexpected warning is a failed/pending case to report verbatim, not permission to bypass validation.
5. With a product in the test customer's cart, open checkout and confirm the saved delivery details still load. Do not place/pay an order or create NP counterparties/shipments. Public empty-cart HTTP smoke does not substitute for this.

Owner returns pass/fail per step, which NP variants and viewport sizes were exercised, whether private JSON comparison passed and any exact warning (without personal data). A generic visual success must not be relabelled as hidden-key or untested-variant proof. If a failure appears, stop package progression and diagnose the actual output. After owner response, perform the applicable coherent actual-result review automatically; do not request another routine go.

- All four forms: preserve bs_first15_pending, bs_np_v1, rd13_receiver_middlename and unrelated values on a controlled test account; omission and explicit clearing independently.
- NP warehouse/poshtomat/courier, quoted/ampersand text and admin name-only after zones load; conflicting location must warn without a write. Existing multiline/zone timing can safely refuse.
- Ownership/permission/auth boundaries, default-address behavior, activity rows, First15 and checkout entry without placing/paying an order.
- Mobile/tablet/desktop, long text and keyboard states; current Tier1 URLs, selecting a valid present 3D product instead of retired FIG-CHARM-001.

## Side effects / risks and review-note disposition

Only four authenticated writers gain the merge guard. Unchanged checkout/First15 shared writers can still normalize or overwrite stale JSON; this is not universal concurrency protection. Non-NP admin escaping and warning priority are historical limits. Any generated OCMOD blocks; unknown startup handlers remain unadmitted. All action_pre_action controllers are pinned, not every bootstrap/helper/vendor body. Source/cache/owner production behavior remains a separate proof boundary.

R11 has no mandatory source findings. Optional root trailing-slash, aggregated error codes, fixture refinement and stale-comment nits do not trigger another code/provider round. Theme status semantics were previously provided in full in r7/r9; actual catalog model getTheme uses SQL status='1'. Its source identity is pinned; no new theme code is added here. Source output and inspection are not deployment proof. The complete actual r11 notes remain attached for a future necessary stage. WP3 stays unstarted until the owner confirms ACC-008 live plus QA passed.
