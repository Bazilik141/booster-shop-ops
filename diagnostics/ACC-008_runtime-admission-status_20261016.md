# ACC-008 runtime admission — partial host result

Date: 2026-10-16. Workspace: C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops. Executor: Codex.

## Owner-host facts

The returned attachment verifies the accepted diagnostic SHA 8ed70517c429e30bf6c3ff3b718452ce2ba205dd725d02da7b6fc3eeb409d368 and reports host time 2026-10-16T09:57:48Z. Sanitized complete evidence: diagnostics/ACC-008_custom-field-preservation_evidence_20261015/host-runtime-r8_20261016.json. Parsing and exact mapping comparison independently confirmed all 67 distinct source paths exist and their actual hashes equal the full expected manifest, with no drift or duplicates.

CLI PHP 8.0.30, mysqli client 8.0.35, server 5.5.5-10.11.19-MariaDB-cll-lve. Customer, address, customer_activity, session and customer_login all report InnoDB. Store 0 customer activity is enabled. Catalog/admin persistence flags are false and target-equality flags true, but the r8 parser limitations keep admin target flags provisional until the admission gate is strengthened. Fifty-one explicit OCMOD candidates are absent; this is not universal override proof. The CLI opcache.enable flag is not effective web/CLI cache proof.

Web probe status is unavailable, reason web_probe_protocol_rejected. The collector did not retain the failing HTTP status/content type, so the result does not distinguish non-200, content-type mismatch or oversized response. Do not invent a 403/404, PHP version, WAF block, document-root mismatch, redirect or successful web execution. done=partial, diagnostic_exit=1, runner_kept=yes. The owner command then removed the task file, and the subsequent direct URL returned diagnostic_http=404. Self-deletion did not run; this was owner-command cleanup. The deprecation warning remains nonfatal and no hosting configuration change is justified by it.

No source/apply/DB/cache mutation is reported. The main runner remains b5f37f9f82fade7e51f51d2592dc4c0b40f211df2aa499d1f38ae366dfd76da6 with unconditional apply refusal. G2 is not passed; WP3 has not started.

## Bounded independent preparation

The newest local full backup is still backup-10.15.2026_13-09-55_boosters.tar.gz. A further code-only whitelist intake from its verified 221b06d9...26f3 bytes obtained .htaccess and five First15/checkout consumer source files, with one optional nonexistent checkout/customer.php path recorded. No configs, logs, dumps, private records or archive code execution occurred. See diagnostics/ACC-008_routing-consumers-source-intake_evidence_20261016.json. These additional source bodies are not claimed current on hosting or independently accepted.

The archived .htaccess lines 57-59 canonicalize HTTP/www to HTTPS without www; lines 80-82 send only nonexistent filesystem paths to index.php. There is no explicit rule for this diagnostic filename in that archived file. This does not establish current rules, document root or the cause of the failed live request. No .htaccess edit is authorized or proposed.

The First15 consumer reads fresh customer custom fields, checks bs_first15_pending and can clear it through the unchanged customer model. Confirm snapshots read session customer custom fields. Protected checkout writer behavior remains a separate interaction consideration; these reads do not grant checkout edits or claim the admission analysis is complete. Preserve the pending duplicate-JSON-key, event/File-config/Loader/session/Pinta, strict admin target, effective override and backup-protection gates before apply.

## Smallest next owner evidence

Reuse the exact already-reviewed diagnostic unchanged. No new code or provider review request is needed for this standard HTTP observation; actual r8 acceptance is reused only within its read-only web-branch scope. Reupload it into public_html because the previous command correctly removed it. Run its public GET branch directly through curl first, without rerunning CLI/DB collection. Preserve HTTP status and content type. Do not follow redirects, send cookies or expose response headers. --fail suppresses HTTP error bodies; --max-filesize and --max-time bound the observation. Remove only the checksum-verified task file afterwards and check 404. Return the bounded output even if it fails; do not guess another URL or modify hosting security.

```bash
cd ~/public_html || exit
acc008_diag='ACC-008_runtime-evidence_20261016.php'
if printf '%s  %s\n' '8ed70517c429e30bf6c3ff3b718452ce2ba205dd725d02da7b6fc3eeb409d368' "$acc008_diag" | sha256sum -c -; then
  curl --fail --silent --show-error --max-time 15 --max-filesize 16384 --proto '=https' -w '\nweb_http=%{http_code}\nweb_type=%{content_type}\n' 'https://boostershop.website/ACC-008_runtime-evidence_20261016.php?acc008_runtime_probe=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
  printf 'web_exit=%s\n' "$?"
  rm -f -- "$acc008_diag"
  curl --silent --show-error --max-time 15 --proto '=https' -o /dev/null -w 'cleanup_http=%{http_code}\n' 'https://boostershop.website/ACC-008_runtime-evidence_20261016.php?acc008_runtime_probe=aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'
fi
```

Expected: complete version-only JSON with task ACC-008-public-runtime and matching nonce, web_http=200, application/json content type, web_exit=0, cleanup_http=404. A different response is evidence for routing/access diagnosis, not apply admission. No separate owner go is requested; the missing host observation is the dependency.
