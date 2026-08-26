# PAY-002 — PUMB callback Basic Authorization passthrough

Date: 2026-08-25  
Scope: production-safe runner preparation only; no server execution or deployment by Codex.

## Evidence used

- Fresh owner backup: `backup-8.24.2026_10-35-09_boosters.tar.gz`.
- `homedir/public_html/.htaccess` contains active `RewriteEngine On` / `RewriteBase /`, but no `CGIPassAuth`, `HTTP_AUTHORIZATION`, or existing PAY-002 authorization-passthrough marker.
- `extension/pumb_credit/catalog/controller/payment/pumb_credit.php::validBasicAuth()` reads `HTTP_AUTHORIZATION`, then `REDIRECT_HTTP_AUTHORIZATION`; an absent header makes Basic-auth validation fail before credential comparison.

## Runner design

Phase 1 creates a random, temporary PHP probe in `~/public_html`, sends it a random Basic Authorization header over the configured HTTPS storefront URL, validates the probe's JSON result, and deletes the probe before proceeding. The runner prints whether the exact header reached PHP. Any transport, response, or cleanup ambiguity stops the run without modifying `.htaccess`.

If PHP receives the header, the runner changes nothing and reports that the remaining 401 diagnosis is the credential pair held by the shop and the bank. It self-deletes after that successful conclusion.

Phase 2 is reachable only after Phase 1 conclusively reports header absence. It inserts one marked conditional `mod_rewrite` rule immediately after the unique active `Options -Indexes` / `RewriteEngine On` / `RewriteBase /` anchor:

```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule ^ - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

The rewrite form was selected over `CGIPassAuth On` because the inspected `.htaccess` already operates through `mod_rewrite`, while the rewrite environment-variable bridge works with Apache 2.4/cPanel EA4 FastCGI handling without relying on a CGI-specific directive. The rule is conditional and does not alter URLs, redirects, request methods, or the PUMB extension.

## Safeguards

- No modification is attempted if the marker exists, the marker is malformed, the exact anchor is not unique, or Phase 1 is inconclusive.
- Before writing, `.htaccess` is copied to `_patch_backups/PAY-002_pumb-callback-basic-auth-passthrough_20260825-<UTC>-<random>/.htaccess.before`.
- After writing, the runner requires HTTP 200 from the storefront home page and `/catalog/Pokemon`. Any non-200 restores the backup immediately and exits with an error.
- It then repeats the Authorization probe. A missing or inconclusive header result also restores the backup immediately and exits with an error.
- A successful run leaves the marker for idempotency and self-deletes the runner. No cache clear is required for `.htaccess` processing.

## Local validation

`php -l patches/PAY-002_pumb-callback-basic-auth-passthrough_20260825.php` passed locally. The Phase 1/Phase 2 result is production-only and must be taken from the owner-run output.
