# PAY-006 — PUMB callback IP allowlist: state diagnostic

Date: 2026-10-16 · Executor: Claude Code · Source: this repository only (no backup, no server, no DB access)
Patch: `patches/PAY-006_pumb-callback-ip-add_20261016.php`

## 1. Other IP gates on the callback path

**In application code: none besides `allowedIp()`.**

- Live controller: the code was decoded from the base64 payload in `patches/PAY-003_pumb-callback-cidr_20260901.php`, and its SHA-256 matches the live `951dcf43…`. `handleCallback()` (line 160) runs Basic Auth first (`401 unauthorized`) and then `allowedIp()` (`403 ip_not_allowed`). The same function serves `callback` and `callbackTest`. No other check reads `REMOTE_ADDR` or any forwarded header.
- `194.44.66` occurs in runtime-irrelevant files only: tests (`scripts/tests/pay003-callback-cidr.test.php`, `pay002-final-preflight.test.php`) and the read-only reporter `scripts/PAY-002_final-preflight_20260831.php:57-63`. After the patch, that reporter will show `entries=17`. It does not gate anything.
- One nuance in the PAY-006 evidence wording ("a malformed entry fails closed"): the code checks each entry on its own. A malformed entry never matches, but the valid entries still match. The list fails closed only when **no** entry is valid. This has no effect on this change.

**`.htaccess`: not verifiable from the repo.** The repository has no copy of the live `.htaccess`. The reviews of 2026-08-24/25 (`diagnostics/PAY-002_pumb-callback-basic-auth-passthrough_review_20260825.md`) describe the LSCACHE block, the HTTPS/www redirect, the front controller and the Authorization passthrough. They record no IP rule, but they were not looking for one. Owner check (read-only, from `~/public_html`):

```bash
grep -n -i -E "require( not)? ip|deny from|allow from|REMOTE_ADDR|194\.44\.66|pumb" .htaccess; find . -name .htaccess -path "*pumb*"
```

Expected: no output.

**Hosting firewall / WAF / Imunify / LiteSpeed WebAdmin rules:** not visible from the repo or from the backup. On 2026-08-25 the access log recorded the bank's own address (`194.44.66.21`) as the client (`diagnostics/PAY-002_bank-test-drive_result_20260825.md:56,184`). So at that time no proxy or CDN replaced the source address before `REMOTE_ADDR`. Whether the host passes `3.123.129.140` is unknown until the bank sends from it.

## 2. Settings cache: unverified, checked live by the patch

- The module reads both lists through `$this->config->get('payment_pumb_credit_[test_]callback_ips')` (controller line 189). The conclusion "OpenCart 4 fills `config` from the `setting` table on every request, with no settings cache" is **unverified upstream knowledge**. The core files are not in the repo, so it is not based on the live bytes.
- The patch therefore scans the live files `catalog/controller/startup/setting.php`, `catalog/model/setting/setting.php` and `admin/model/setting/setting.php` (read-only, before any write) for the case-insensitive word `cache`. It prints `settings_cache_scan[<file>]=none_found|found:<lines>|missing` and `settings_cache_verdict=none_found|REVIEW_BEFORE_TRUSTING`. The owner reads this; `REVIEW_BEFORE_TRUSTING` means the hit lines must be checked before assuming the new value is live without a cache clear. The scan does not affect `done=` or the exit code.
- LiteSpeed Cache does not serve POST requests from cache. Callbacks are POST.
- The `pumb_credit_token_*.json` file in `DIR_CACHE` is the OAuth token cache (store → bank). It has nothing to do with callback IPs.

## 3. Admin form: can a later save revert the value?

Source: the admin controller and Twig as built by the repo-held patches (`PAY-002_pumb-credit-skeleton_20260728.php:280-285,315-316`, later changed by PAY-002 admin-status-preserve, PAY-002 preview-token-gate and PAY-004). The live admin file is not hash-verified here.

- **Read:** `getSetting('payment_pumb_credit')` loads the current DB values. Both keys are in `$keys`. Each one renders as a plain `type="text"` input with the full stored string as its value: no `maxlength`, no trimming or parsing.
- **Save:** `editSetting('payment_pumb_credit', $this->request->post)` deletes **all** `payment_pumb_credit` rows for store 0 and inserts the posted fields again. `validate()` checks only the permission and does not validate IPs. A save made from a page that was loaded **after** the patch therefore keeps `3.123.129.140`.
- **Revert risk (the only one):** a PUMB settings page opened **before** the patch run and saved **after** it posts the old lists and silently removes the new IP. The same risk applies to any other old value on that form. Owner action: no PUMB settings tab may be open during the run. Reload the page before any later save.
- Every admin save changes `setting_id` (delete + insert). This is why the patch and `rollback.php` match rows by `store_id`/`code`/`key` and compare-and-swap on the value, not by the ids 11146/11149.

## Patch verification (local fixture, MariaDB 10.11, InnoDB and MyISAM)

All runs passed. Covered cases:

- both lists appended, and the old part byte-identical;
- a second run gives `already_applied=yes`, no new backup;
- `rollback.php`, `rollback.sql` and the header SQL each restore the exact originals; repeating any of them changes nothing;
- `rollback.php` refuses a list that changed after the patch and still restores the other list;
- one empty list (or whitespace only): that list is untouched and reported, the other is written, `done=partial`; both empty: `done=refused`, no backup;
- refused before any write: a missing row, a duplicate row (same store, or another store), `serialized=1`, a wrong `code`, a wrong prefix, a missing controller;
- forced DB error on the second update: the first update is undone on both engines;
- other setting rows: byte-identical.

The decoded live `allowedIp()` was also run with the patched value. Allowed: `3.123.129.140`, `194.44.66.16`, `194.44.66.31` (TEST and PROD). Refused: `3.123.129.141`, `194.44.66.32`, and an empty `REMOTE_ADDR`. `X-Forwarded-For` is ignored.

`scripts/check-php-host-compat.php` reports OK for the runner and for the generated `rollback.php` (no PHP 8.1+ syntax, no mysqlnd-only calls). Local PHP is 8.3. The PHP 8.0.30 runtime itself was not run.

## Not verified by us

- A real callback from `3.123.129.140`: only the bank can produce one.
- `.htaccess` and host-level filtering (see section 1).
- That the live admin controller/Twig match the repo-built sources.

## Authority

DB write approval = the owner running the patch. No commit, push, deploy, Notion or `ROADMAP_FLOW` write was made by Claude Code.
