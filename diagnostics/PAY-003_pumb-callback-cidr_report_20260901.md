# Codex Report — PAY-003: PUMB callback CIDR support

Date: 2026-09-01

## Scope

Add exact-IP plus IPv4/IPv6 CIDR matching to the existing PUMB callback source-IP allowlist before PROD cutover.

The patch does not change settings, credentials, callback URLs, the database, or the active TEST/PROD mode. It makes the module capable of safely accepting a bank range such as the owner-provided `/28` after that value is explicitly saved during cutover.

## Root cause

`allowedIp()` previously performed only strict string membership:

```php
in_array($remoteAddress, $allowedEntries, true)
```

Therefore a configured CIDR entry could never match a real sender address. The new `ipMatches()` helper uses `inet_pton()` and prefix-bit comparison. It supports exact IPv4/IPv6 values and CIDR ranges, rejects malformed entries, and reads only `REMOTE_ADDR`; forwarded headers are intentionally ignored.

## Files

```text
patches/PAY-003_pumb-callback-cidr_20260901.php
scripts/build-pay003-callback-cidr.mjs
scripts/tests/pay003-callback-cidr.test.php
work/pay003-callback-cidr/candidate/extension/pumb_credit/catalog/controller/payment/pumb_credit.php
```

Runtime target:

```text
extension/pumb_credit/catalog/controller/payment/pumb_credit.php
```

## Local verification

```text
checks=22 result=ok network=0 database_writes=0
candidate PHP lint: OK
runner PHP lint: OK
fixture install: done=ok, self_delete=ok
fixture repeat: already_applied=yes, self_delete=ok
fixture rollback: rollback=ok; original SHA256 restored
fixture drift: refused before writes with exact SHA256 mismatch
```

Covered cases:

- exact IPv4;
- both boundaries and outside values of the supplied IPv4 `/28` shape;
- non-canonical network address with correct prefix masking;
- invalid/negative/overflow prefixes;
- invalid network strings and missing remote address;
- exact IPv6 normalization and IPv6 CIDR;
- IPv4/IPv6 family mismatch;
- comma-separated exact and CIDR entries;
- non-empty invalid allowlist fails closed;
- `X-Forwarded-For` is not trusted;
- TEST and PROD callback setting keys share the same matcher.

Patch SHA256:

```text
f5abaff5aa9ca2eeaf0b9f0a2170163384d590aa55d6b3da5dd89bf58c796689
```

## Rollback

The runner prints a backup directory under:

```text
_patch_backups/PAY-003_pumb-callback-cidr_20260901-<timestamp>-<suffix>/
```

Run its `rollback.php`, then clear OpenCart cache. The patch itself performs no irreversible operation.

## Post-deploy gate

1. Confirm patch output contains `done=ok`, `settings_changed=no`, and `self_delete=ok`.
2. Keep TEST mode and public visibility unchanged.
3. Obtain the rotated PROD password.
4. Confirm PROD callback URL and Basic Auth ownership with PUMB.
5. During a separate owner-approved cutover, save the bank CIDR in the PROD callback IP setting and run a controlled callback test.

## Risk

Low and isolated to callback source-IP matching. An empty allowlist preserves the pre-existing allow-all behavior; any non-empty malformed allowlist fails closed. Correct runtime callback acceptance still depends on the host exposing the bank connection address in `REMOTE_ADDR` and on valid Basic Auth.
