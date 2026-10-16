# ACC-008 separate read-only runtime diagnostic

Date: 2026-10-16. Executor: Codex. Workspace: C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops.

Latest owner result received: the collector returned partial evidence at 2026-10-16T09:57:48Z. All 67 source hashes match and five relevant tables are InnoDB, but web_probe_protocol_rejected leaves web PHP/OPcache unverified. The owner-command cleanup removed the diagnostic and its URL returned 404. The full historical collection block below has already run; do not repeat CLI/DB collection. Current smallest next HTTP-only action and remaining limits: diagnostics/ACC-008_runtime-admission-status_20261016.md. Main runner remains unchanged/apply-disabled.

## Boundary and reason

The owner's r7 host dry-run verified SHA b5f37f9f82fade7e51f51d2592dc4c0b40f211df2aa499d1f38ae366dfd76da6 and all five PHP payload lints on CLI PHP 8.0.30 at 2026-10-16T08:25:05Z. It returned 27 disabled custom fields, 112 active DB events and no relevant active theme overrides, then correctly stopped at active_override_or_event_requires_independent_review. The nonfatal MYSQL_OPT_RECONNECT warning was not this stop's cause. Sanitized complete metadata is retained in the task evidence directory as host-dry-run-r7_20261016.json.

No apply/source/DB mutation happened. The main runner is unchanged and still hard-disables apply with apply_requires_reviewed_runtime_admission. Its r7 Source Review OK covers only its read-only collection, not admission of these live dependencies or G2.

This separate diagnostic collects remaining technical facts before a coherent runtime-admission review. Artifact: patches/ACC-008_runtime-evidence_20261016.php. SHA-256: 8ed70517c429e30bf6c3ff3b718452ce2ba205dd725d02da7b6fc3eeb409d368. Actual r8 Claude Opus/high Source Review OK accepts only safe read-only collection, with no mandatory defects in that boundary. Full result: diagnostics/ACC-008_runtime-evidence_claude-review-r8_20261016.md. Request 6721ae74-2a2e-40a5-8b31-61eb1769c798 completed in the original PID 40640; saved Claude.ai subscription, isUsingOverage=false, no API fallback or tools. Independent closure verified 92 frozen inputs and Git HEAD/index before this documentation-only update. No code changed after review.

## Collected facts and privacy

- Catalog/admin DB persistence and target-equality booleans only. Admin config is parsed without execution and only literal target constants are considered; credentials and actual target values are never returned. Catalog config is loaded as on the accepted host preflight, under an output trap. Persistent catalog connections are refused before DB connection. No application framework/session is bootstrapped.
- Read-only prepared SELECTs with bind_result and integer DB_PORT return engine names for customer, address, customer_activity, session and customer_login; config_customer_activity becomes bounded store-ID/validity/boolean rows. No customer/order/session records, config contents, credentials, hostnames, database names, dump or private logs are returned. Unsupported activity setting values are marked invalid without printing their values.
- CLI/web PHP version, SAPI, mysqli client version and OPcache flags; DB server version only. No environment dump or phpinfo. There are 67 exact source identities from whitelisted code-only intakes of the newest owner backup, and bounded candidate OCMOD file identities. Missing/drifted files are evidence rather than silently admitted. OCMOD candidates do not prove universal override absence. DIR_EXTENSION is checked against public_html/extension via a boolean, not disclosed as a filesystem path.
- A GET-only web branch returns only versions/OPcache flags and an echoed caller nonce. It never loads config, DB or session; invalid/missing/array nonce or non-GET returns empty HTTP 403. This branch is intentionally public diagnostic metadata, not an authenticated endpoint. The nonce binds the CLI's fixed-origin HTTPS response and discourages stale-cache confusion; it is not authorization.
- The CLI calls only https://boostershop.website/<diagnostic basename> with TLS peer/host verification, HTTPS protocols, no redirects, 5/10-second connect/total limits and a 16-KiB response cap. It validates the complete JSON schema, status, content type and nonce. No raw failed response or cURL error message is printed. Failed web evidence returns useful CLI metadata plus done=partial and keeps the file for diagnosis. Complete collection deletes only this uploaded diagnostic file and reports self_deleted=yes, done=ok.

No application/DB/cache write, event disablement, archive ingestion, main-runner change, commit/push/deploy, Notion/dashboard write or WP3 work is included. Deleting the diagnostic is its only intended filesystem mutation. Removing that uploaded file manually is sufficient diagnostic cleanup; there is no data rollback.

## Local checks and acceptance limits

Local PHP 8.3.30: lint for the generated diagnostic and two fixtures; 24 exact-function cases; seven CLI cases using fictional DB/HTTPS under php -n; five HTTP cases through a real local PHP web SAPI with no config present. Cases cover parser ambiguities, target/privacy canaries, source drift/missing paths, OCMOD admin mapping, invalid settings, complete/partial/self-delete lifecycle, unsupported args, accidental config output, persistent connections, malformed/extra/stale/oversized web responses, protocol policy, empty HTTP 403, public-only JSON and no session cookies. Non-diagnostic files are hashed before/after lifecycle cases. Main-runner SHA remains unchanged.

Evidence: diagnostics/ACC-008_custom-field-preservation_evidence_20261015/runtime-evidence-local-checks_20261016.json. These are local fictional and web-SAPI checks; no actual hosting DB/HTTPS/PHP-web behavior, runtime admission, production mutation or owner QA is claimed. PHP 8.0-compatible syntax/APIs are used; hosting execution is still required after independent source review.

## R8 nonblocking findings and disposition

All notes from the full actual verdict remain attached to the later coherent admission stage. They do not justify an optional-nit-only provider round or changing this accepted collector before receiving missing host evidence.

The admin parser fails safely for typical literal OpenCart config forms, but it is not a complete PHP evaluator. Short-circuit/ternary expressions, early return/exit/goto and numeric octal semantics can produce inaccurate target-equality flags; therefore those flags are provisional, not runtime admission. No owner config export or extra visual-confirmation round is requested. A later apply-admission preflight must close this semantic gap before relying on the flags. OCMOD candidate absence is not override absence; both realpath calls returning false can also make the extension-root equality flag misleading. CLI opcache.enabled reflects opcache.enable, not effective opcache.enable_cli. None of these diagnostic values admits apply.

Other retained limits include tokenizer availability, bounded SELECT behavior with libmysqlclient LONGTEXT metadata, fixture temp-root hygiene and source-check coverage. The first 31 files originated from two narrow owner packages and were subsequently compared with the newer full backup as recorded in diagnostics/ACC-008_render-source-intake_evidence_20261015.json; the other intake bodies came directly from that verified backup. This is recorded provenance, not proof of current hosting bytes. Reviewer concern about the provenance sentence is clarified by that pre-existing comparison record rather than a new source claim.

## Owner collection and cleanup

Upload this separate diagnostic into public_html, then run the following exact block. The checksum gate prevents running or deleting an unverified local file. The block removes only this task diagnostic after the run even on partial/error, and checks its public URL. Return the bounded terminal output. Expected complete path: runtime_metadata=..., self_deleted=yes, done=ok, diagnostic_exit=0, diagnostic_http=404. Any partial/error or HTTP response other than 404 requires assessment; it does not authorize apply. If web OPcache serves a removed script, source deletion alone is insufficient and owner hosting cache action will be needed.

```bash
cd ~/public_html || exit
acc008_diag='ACC-008_runtime-evidence_20261016.php'
if printf '%s  %s\n' '8ed70517c429e30bf6c3ff3b718452ce2ba205dd725d02da7b6fc3eeb409d368' "$acc008_diag" | sha256sum -c -; then
  php "$acc008_diag"
  acc008_diag_status=$?
  rm -f -- "$acc008_diag"
  printf 'diagnostic_exit=%s\n' "$acc008_diag_status"
  curl --silent --show-error --max-time 15 --proto '=https' -o /dev/null -w 'diagnostic_http=%{http_code}\n' 'https://boostershop.website/ACC-008_runtime-evidence_20261016.php?acc008_runtime_probe=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
fi
```

## Next dependency

Actual review and post-result identity closure are complete. Owner-host evidence is now the dependency. Do not rerun unchanged r7 or invoke apply. Use the returned nonpersonal metadata to assess actual runtime interactions and prepare a separately reviewed main-runner admission revision only when the evidence supports it. Owner-held fresh DB dump, owner deployment and QA remain later requirements. WP3 waits for confirmed ACC-008 live plus owner QA; repeated routine go requests were removed by the owner.
