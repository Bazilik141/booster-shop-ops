# PAY-002 — preflight compatibility correction (v2)

Date: 2026-08-31

## Scope and evidence

Owner ran v1 and reported a MYSQL_OPT_RECONNECT deprecation warning followed by
`done=error`, `phase=settings`. The script reached settings after connecting and
setting the connection charset. The redacted v1 output cannot distinguish SQL
failure, missing fetch_all(), or settings decoding failure. The exact live cause
is therefore not yet proven. The warning alone is not a diagnosis.

Source inspection found a portability defect: the result reader called
`mysqli_result::fetch_all()`. The [PHP manual](https://www.php.net/manual/en/mysqli-result.fetch-all.php)
states that before PHP 8.1 this method was available only with mysqlnd. Hosting
targets PHP 8.0, so the diagnostic must not require that optional capability.

## Changes

- `scripts/PAY-002_final-preflight_20260831.php`: replace fetch_all with
  fetch_assoc iteration and free the result; label output v2.
- Add safe error classification/code, PHP version, mysqlnd/function capability
  booleans, and query-versus-fetch stage. Never print SQL or exception messages.
- `scripts/tests/pay002-final-preflight.test.php`: fake results deliberately
  lack fetch_all. Add query/fetch error injection and redaction assertions.

No SQL scope, source hashes, config behavior, secret projections, payment code,
order data, bank calls or production settings were changed. The amount defect
identified in the previous report remains unfixed and blocks public launch.

## Validation

- Red: v1 fails the existing fixture success assertion after removing fetch_all
  from the fake driver.
- Green: v2 passes the same fixture, plus error-stage/classification/redaction
  tests. `preflight_tests=ok; real_db_calls=0; bank_calls=0`.
- `php -l`: no syntax errors (local PHP 8.3.30, not hosting PHP 8.0).
- Read-only behavior remains: no database writes, bank API requests, cache
  cleanup, source edits or self-deletion during an owner run.

## Owner next step

Overwrite only the uploaded diagnostic with v2 under the same filename, then:

```bash
cd ~/public_html || exit
php PAY-002_final-preflight_20260831.php
```

Return the output with `diagnostic=PAY-002-final-preflight-v2`. Success means
collection, not integration readiness. If still failing, use the safe error
fields to diagnose before any change. No rollback is needed for a read-only
collector; no hosting configuration changes are requested to silence warnings.

No commit, push, deploy, or roadmap/status write performed.
