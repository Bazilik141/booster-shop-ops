# ACC-008 profile confirmation follow-up — r13 owner-applied, authenticated QA pending

Date: 2026-10-16. Executor: Codex. Workspace: C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops.

## Current independent verdict and owner command

Latest owner result: r13 profile QA is positive (standard green confirmation, one-use reload, inline empty-name error and changed-email success), and owner-host curl returns8/8 final HTTP200/exit0. Responsive live QA is deferred by explicit owner decision. Evidence is sanitized in diagnostics/ACC-008_actual-result_evidence_20261016. Owner additionally reports the saved-address cart toast; r16 one-controller follow-up is being independently reviewed before final actual-result/G2 assessment. R13 remains applied; no rerun. Earlier pending-QA/smoke wording is historical.

Public smoke after r13 is incomplete: direct anonymous GETs returned final200 without basic PHP signatures for nested Pokemon category and empty checkout entry (redirect to cart), while home/top category/sealed product/3D product/information/cart timed out. Evidence: public-smoke-after-r13_20261016.json. A single secondary web-tool check returned fetch502 for4 URLs and unavailable-via-tool for2, not independently established origin responses; evidence public-smoke-r13-secondary-channel_20261016.json. Do not count8/8 or infer source failure from transport results. No more automatic requests/retries. Need owner browser QA and bounded owner-host URL statuses before the coherent actual-result assessment; no G2/WP3 acceptance.

Current owner evidence update: r13 checksum, dry-run and apply succeeded on native PHP8.0.30 at2026-10-16T18:33:36Z. Dry-run reports files_pending=2; apply reports exactly Twig then controller changed, PHP lint OK, files_changed=2/done=ok/template_cache=cleared. Exact source/no-generated-OCMOD and SELECT-only preflight admitted; both apps map cache to ../ocartdata/storage/cache. Both scans report112 events, no active startups/relevant theme overrides,27 disabled custom fields and27 group mappings; nonpersonal metadata is identical between scans. Backup: ../_patch_backups/ACC-008_profile-confirmation_20261016T183336Z-0c0c2fea. Owner ran the reviewed DB-backup-confirmed flag; no private dump is ingested or independently verified. Evidence: host-dry-run-r13_20261016.json and host-apply-r13_20261016.json in this task's evidence directory. Self-delete is the accepted runner success behavior, but absence is not independently observed. No authenticated r13 QA result yet; an async question requests save/same-page/login/banner, one-use reload, validation and changed-email checks. No rerun of the successful runner.

The completed r15 comparison at18:22:48/18:26:46Z remains bounded: owner confirms all four form steps/all3 addresses; bs_np_v1 key/value equality is proven for those3 addresses; customer keys=0 and the other two tracked service keys are absent, with no coverage claimed for them. Required coherent actual-result review follows r13 owner QA and public smoke; G2/WP3 remain pending. Earlier private-check/apply-pending wording below is historical. The accepted patch is unchanged.

Actual **r13 Source Review OK** for this two-file CODE only. Mandatory r12 M1 is closed. Full actual verdict diagnostics/ACC-008_profile-confirmation_claude-review-r13_20261016.md was read. Request8db720fc-5a39-44e2-863b-c58dc31083de completed via original PID27828/session67139, exit0, actual claude-opus-5-5/high, saved subscription, isUsingOverage=false, no tools/API fallback. Independent review-r13-code-closure.json verified all159 frozen inputs and Git HEAD/index unchanged before these documentation updates. No code changed after review. R12 remains rejected and both original artifacts are retained. This is not production/follow-up apply, owner QA, G2 or WP3 acceptance.

## Completed owner deployment instructions — retained reference

The owner completed the exact r13 block below. Do not run it again. Original prerequisites: upload the exact r13 file one level above public_html and retain a fresh owner-held DB dump; no dump/customer data goes to agents. The reviewer explicitly accepted one checksum/dry-run/apply/cache chain; exact admission re-ran before writes. The reference refusal rule was to stop and return complete output, without bypass or retry.

```bash
cd ~/public_html || exit
acc008_patch='../ACC-008_profile-confirmation-r13_20261016.php'
printf '%s  %s\n' 'b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1' "$acc008_patch" | sha256sum -c - &&
php "$acc008_patch" --dry-run &&
php "$acc008_patch" --db-backup-confirmed &&
test -d '../ocartdata/storage/cache/template' &&
test ! -L '../ocartdata/storage/cache/template' &&
find -P '../ocartdata/storage/cache/template' -type f -name '*.php' -delete &&
printf 'template_cache=cleared\n'
```

Expected clean dry-run: one host_payload_lint, exact runtime_sources, both known cache paths, config admission,112 expected events/empty active startups, db_preflight=read_only_ok, dry_run=ok, files_pending=2, done=ok. Expected apply: new private backup=../_patch_backups/ACC-008_profile-confirmation_<actual basename>, two source backup_file entries, Twig then controller changed, one php_lint, files_changed=2, done=ok, template_cache=cleared. The pre-existing MYSQL_OPT_RECONNECT warning remains nonfatal. Save the actual backup line and full output. If cleanup alone fails, sources may already be applied; report that state without blindly rerunning.

Owner focused QA: save the profile, stay logged in on the same edit form, see a green standard alert with no cart title/actions and persisted fields. The URL must carry customer_token normally, with no amp; query keys. Reload: notice disappears. Check a changed test email and empty-firstname inline validation too. Keep previous address/admin behavior and available mobile/tablet/desktop checks. Existing cart-styled warning toasts remain a known pre-existing limit. The pending private custom_field key/value preservation check is needed for G2; share only key names and pass/fail, not JSON/values. The owner has been asked asynchronously whether this check was performed. Do not advance WP3 yet.

Rollback only if required: reupload the SAME r13 runner, paste the actual backup path from this follow-up when prompted, and run:

```bash
cd ~/public_html || exit
acc008_patch='../ACC-008_profile-confirmation-r13_20261016.php'
read -r -p 'Paste this follow-up backup path: ' acc008_backup &&
printf '%s  %s\n' 'b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1' "$acc008_patch" | sha256sum -c - &&
php "$acc008_patch" "--rollback=${acc008_backup##*/}" &&
test -d '../ocartdata/storage/cache/template' &&
test ! -L '../ocartdata/storage/cache/template' &&
find -P '../ocartdata/storage/cache/template' -type f -name '*.php' -delete &&
printf 'template_cache=cleared\n'
```

Rollback restores the sources recorded in that follow-up's manifest to r11, including the old toast. It does not restore data. A partial-state manifest restores only files changed by that run. To undo the full package, this follow-up must be rolled back before the original r11 rollback; never bypass source drift or delete the guard manually.

## Owner runtime result and defect

The owner confirms profile/address names remain independent, temporary checkout recipient changes do not overwrite persistent customer details, and admin customer changes persist and appear on the storefront. Overall QA is positive except profile-save confirmation: the success text for an updated account appears under a cart title with cart/checkout buttons, while the cart remains empty. This is owner-reported behavior; raw JSON before/after, all NP variants/viewports, activity rows and cross-customer authorization checks were not provided. No hidden-key production proof is inferred. The private screenshot includes personal form values and is neither copied into task artifacts nor sent to Claude. Only its nonpersonal UI symptom is described.

Owner r11 apply is confirmed at12:09:07Z: exact reviewed SHA, nine changed files, five pre/post PHP8.0.30 lints, private source backup and template-cache cleanup. Evidence remains in diagnostics/ACC-008_custom-field-preservation_evidence_20261015/host-apply-r11_20261016.json. Public eight-page anonymous smoke is separate; empty checkout redirects to cart. WP3 remains unstarted pending this follow-up and the applicable result assessment.

## Root cause and scope choice

Current publicly served catalog/view/javascript/common.js, SHA58605f6df5f8ded195f994d44446920b478e331243c983d75df56e49c48d7c02, calls window.bsToast('success',json['success']) for every generic AJAX form at194. Its bsToast body at72–104 always supplies a cart title and cart/checkout actions (title at75). The profile controller's successful JSON uses that generic success key. R11 preserved this pre-existing response behavior; it did not add cart contents or calls. Prior RD-12_minicart-toast_20260921.php replaced the generic success alert with bsToast; RD-12 toast polish/layout/canonical follow-ups retain the cart-specific component. No claim that r11 introduced that component is made.

Avoid changing the shared cart renderer/caller or its header asset token under the owner's parallel ownership exclusions. At the owning profile controller/template, return a same-form redirect after the already committed save and session refresh, with a dedicated one-use success value. Render that value as an escaped standard Bootstrap success alert on the profile page, then consume it. No shared CSS, !important, setTimeout, absolute/fixed positioning or magic pixel value is added. The page reload is intentional; the user stays on the edit form with the persisted values and existing language/customer token. Existing validation failures keep their existing behavior; cart-styled global error warnings on other paths are not silently refactored.

## Artifact and guards

Candidate: patches/ACC-008_profile-confirmation-r13_20261016.php.
SHA256:b8758600daece9c37e002e78953bcb22a0a9c6811f89ac34dc4e951321be82a1.

Exactly two targets, both requiring the exact successfully installed r11 bytes:

- catalog/view/template/account/edit.twig — escaped one-use success banner; applied first.
- catalog/controller/account/edit.php — set/consume the dedicated success value and redirect after the existing commit/session update. Guard, validation, ownership, JSON merge/model writes and other three form controllers remain unchanged.

Runner reuses the actual r11 admission implementation byte-for-byte: nonexecuting bounded config parsing, both cache mappings, same/nonpersistent DB flags, SELECT-only field/group/event/startup/theme/engine gates and whole OCMOD refusal. Source baseline is103 identities: the101 reviewed identities advanced to the owner-confirmed r11 payload states, plus the newly installed helper and the publicly served common.js. The latter is fresh public response evidence, not yet raw-host hash proof; the runner checks the hosting source before any write. No new owner archive is needed unless that gate detects drift. The103 source-path manifest was mechanically checked against local code-only bytes. No root/admin config, dump, log or customer row is included.

Only task marker and private backup prefix are adapted from the reviewed runner. Before/after identities, pending/partial-state recognition, private backups, pre/post PHP lint, staged rename, restore-on-fail, manifest rollback and self-delete remain. Backup name begins ACC-008_profile-confirmation_. The fresh owner-held DB dump flag remains required; no DB data is mutated by the runner. Cache cleanup stays owner-side and template-only at the host-proven ../ocartdata/storage/cache/template path, common to both apps. Applying this follow-up makes the old full-r11 runner rollback refuse controller drift; to undo both, first roll back this follow-up to exact r11, then consider the original r11 rollback. Never delete the guard manually.

## Validation

Local PHP8.3.30 lint passed. Fictional actual-function suite:164 original roundtrips (fixture seeded with the fictional customer token; the two session-rollback expectations adjusted accordingly),12 new success/redirect/one-use/escaping/validation/commit/auth cases,71 exact generated preflight cases and9 copied runner lifecycle cases. The latter stub DB/source admission only; actual source admission is exercised separately by the71-case suite. Three mutants—reintroducing a success response, removing Twig escaping, and removing the raw redirect flag—fail the focused tests. The last fails specifically confirmation:redirect_same_form_and_token. Initial fixture failures were missing token setup and incorrect executeForm use, not observed hosting failures; the fixtures were corrected and final output is warning-free.

Browser fixture uses actual public common.js/jQuery/CSS, the actual Twig3.18/OpenCart-rendered fictional form and mocked local HTTPS responses from actual PHP fixture output. All390/768/1280 widths pass: exactly one save request, same-form redirect, standard success banner, zero cart-toast calls, long message wrapping without horizontal overflow, keyboard submit focus/hover and no JS errors. Screenshots were visually inspected at all three widths. This is a local browser fixture, without the real site header/sidebar or authenticated hosting data; it is not owner production QA or native InnoDB/NP proof.

Detailed evidence under work/acc008-profile-toast-20261016/: local-checks.json, confirmation-tests.json, preflight-tests.json, browser-checks.json, runner-fixture-apply.txt and fictional screenshots. No production application mutation occurred in this Codex session.

## Review preparation history and remaining boundaries

Actual r12 review returned for mandatory M1: JSON redirect requires Url::link raw/JS=true, and the previous URL stub hid that distinction. Original result and149-input/Git closure are preserved, rejected232a... artifact unchanged. R13 adds true, models default HTML escaping in the URL stub, adds the exact removal mutant and reruns the browser on regenerated actual response. The host-gated catalog/controller/account/login.php line211 independently uses the same raw=true JSON-redirect contract; its full body is supplied in the focused repair. Actual Url::link body was not ingested and is not claimed examined or newly gated. Actual automatic saved-subscription Claude Opus/high repair review is next. One coherent packet covers the bounded r11 owner result, its explicit remaining evidence limits and this follow-up CODE plus composition/lifecycle/browser tests. Prior r11 accepted CODE gateway and r9 accepted payload logic in substance are reused by exact identity; no global positive r9/r10 or owner hidden-key acceptance is invented. Do not deliver/apply before actual verdict and frozen-input/Git closure. No paid API, Notion/dashboard/Git writes or WP3.

The reviewer has now accepted the corrected CODE and the owner-only chained block above. Any unknown source/event/theme/startup/config/engine stops before writes and needs assessment, never bypass. Retain printed backup name. Source rollback does not recover historical data. Other storefront/admin writers remain outside this follow-up. Actual Url::link body, universal shared-writer protection and absent owner hidden-key/NP/activity/authorization coverage remain unproved; do not broaden the CODE verdict into those claims.
