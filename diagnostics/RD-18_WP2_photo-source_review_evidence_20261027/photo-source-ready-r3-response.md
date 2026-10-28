# Review OK: RD-18-WP2-photo-source collector and owner packet

**Verdict: Review OK.** There are no mandatory findings.

This approval covers only the exact pair below, as a source-only diagnostic collector and owner transport. I reviewed the supplied text only. I used no tools, did not read files, and did not access SSH, the server or the database.

| Artifact | SHA256 | Bytes |
|---|---|---|
| `diagnostics\RD-18-WP2_photo-source_collect_20261027.sh` | `9880a4d6ecaf52123f0805f1304586c1ac9efac8aff9e877dd44818e84fe27f8` | 13555 |
| `diagnostics\RD-18-WP2_photo-source_owner_20261027.ps1` | `67975e481a020917a81bcfd04f1da430b6eb29a1fc4f0b05eb3f56a12f34fd08` | 7058 |

**How the two hashes are bound:**
- The packet embeds the collector hash and refuses to run if the file on disk does not match.
- The PS5.1 (5.1.26100.9711) and PS7 (7.6.5) mock results both record this exact pair.
- If either file's bytes change, this review is void.

**Not certified:**
- The UI repair or any deployment.
- Real server, MySQL, Unix or native SSH behaviour.
- The current contents of the three source files.
- Whether any event handler is acceptable.

## Collector (bash)

**Webroot, paths and replay**
- The script refuses to start if the webroot is missing, is a symlink, or its physical path does not match.
- Both private directories are checked for symlinks. Every component of each source path is walked and checked for symlinks.
- It stops before creating anything if the archive or manifest already exists (no replay).
- With `set -C` and `umask 077`, the archive and manifest are created with exclusive 0600 writes.

**Source screens**
- Each file has a size cap of 2 MiB and a total cap of 8 MiB.
- The obvious-secret grep correctly tells exit 1 (clean) apart from exit ≥2 (stop). In bash, `$?` at the start of an `else` branch is the status of the `if` condition, so this works.
- The secret screen is only an obvious-signature check, as the packet itself says. It does not guarantee the files are secret-free.

**Integrity**
- The pre- and post-hashes of the sources must match, or it stops with `source_drift`.
- Each archived member is extracted to stdout and hashed against the pre-hash list. The hash-line format and the file count are both enforced.
- The archive hash is written into the manifest, and the manifest hash is printed in the receipt.

**stdin safety**
- The script is fed to the server through stdin (`bash -s`).
- No child process inherits that stdin and reads it: `php -r`, `grep`, `stat`, `sha256sum` and `tar` all take file arguments, and the PHP heredoc replaces stdin for PHP.

**PHP metadata**
- It requires PHP 8.0 CLI and refuses a symlinked config. It checks the root against `getcwd()` and uses an integer port.
- Every warning is converted to an exception, and failures print only a generic STOP line, so credential text cannot leak.
- The prefix is checked against a regex and the schema is checked before querying.
- `START TRANSACTION READ ONLY` is used. Unsupported server versions fail closed.
- The single fixed SELECT reads only `trigger` and `action`. A 1025th row stops, more than 64 relevant rows stops, and `finally` always rolls back.
- Values are redacted to a hash plus length unless they are safe, at most 512 characters, and use the allowed characters.
- The output includes five core-routing file hashes and one image-root boolean. No callbacks or business rows are touched.

**Event predicate**
- It does not admit any new callback. Every relevant model trigger is reported with `already_reviewed=false`.
- `fnmatch(p)` combined with `fnmatch(p.'*')` mimics OpenCart's start-anchored regex matching of trigger prefixes. Where it differs, it over-detects (for example, `[` acts as a character class), which is the safe direction.
- Repeatedly stripping `catalog/` and `system/` is a superset of what the startup registration does. It also over-detects.
- The text of `photo-event-predicate.php` appears identical to the embedded function, by visual comparison only.

## Owner packet (PowerShell)

- The collector hash is checked first. An existing drop folder stops the run before any transport.
- The script is sent with byte-exact LF line endings and CR is rejected. Native arguments are checked against a character whitelist.
- The receipt must match an exact anchored regex.
- Each download is checked against the archive and manifest hashes in the receipt. The manifest must contain the archive hash and `done=ok`.
- Cleanup removes only the two exact non-symlink regular files, and only after their remote hashes are checked again. There is no recursive or wildcard delete.
- Every failure path saves raw stdout and says not to retry.

## Evidence consistency

- The fixture sizes match their contents: `success.stdout` is 212 bytes, the stderr files are 29, 18 and 31 bytes, and the empty files have the empty-file SHA256 (`e3b0…`).
- The metadata test counts are consistent: 11 rows in total, 10 relevant, and 5 not yet reviewed (5 reviewed WP1 rows, 5 new rows, 1 unrelated).
- The packet mock uses the archive, manifest and receipt produced by the real Bash/tar fixture, so the mocked packet run and the collector fixture are tied together.

## Mandatory findings

None.

## Optional findings, with what would close each

| # | Finding | Closure |
|---|---|---|
| O1 | `predicate_sha256` `c64e78…` does not match any transferred file (the predicate file is `15cb68…`), and how it was computed is not stated. | State how it was computed, or bind it to the file hash. It does not block this review, because I reviewed the embedded code directly. |
| O2 | The native PHP test extracts from `photo-source-metadata.php` (`84397dc8…`), whose body was not supplied. The claim that it equals the embedded PHP is unverified by me. | Optionally provide a byte-equality receipt. |
| O3 | Model triggers using the `\|` separator, such as `product\|getProduct`, are not in the relevance list. Such rows would only be counted, not exported. This is safe only if `loader.php` matches the WP1-reviewed version. | At intake, any drift in the core-routing hashes means STOP. Optionally add `\|` aliases in a future revision. |
| O4 | Test gaps: no direct coverage of the slash form `product/getProduct/before`, `system/` prefixes, a bare `catalog` (global) row, the `\|` action normalisation, the secret, size and symlink stops, the PHP-version stop, or the cleanup hash substitution. | Optional extra fixtures. |
| O5 | The test writes `photo-packet-results.json`, but the PS51 and PS7 results files were renamed outside the script. | Optionally script the rename. |
| O6 | The collector may also create the `private` and `booster-pulls` directories (0700), not only the "2 artifacts". If a run stops after `tar`, a partial archive stays on the server. | Optionally document this. The packet already says to preserve files and not retry. |
| O7 | The tar headers record the owner and mtime. | Optionally add `--numeric-owner --owner=0 --group=0`. |
| O8 | Event coverage is limited to `getProduct` and `resize`. | If the UI repair later calls other getters (for example `getProducts` or `getImages`), extend the predicate and review it again. |

## Conditions for use (not defects)

- The owner runs these exact bytes once. Any STOP means no retry.
- At intake, any of the following stays STOP or unresolved:
  - a relevant row with `already_reviewed=false`
  - any redacted value
  - `image_root_is_expected=false`
  - core-hash drift from WP1
  - a source-hash or archive mismatch
- Nothing in this approval grants an allowlist, a source patch, a cache clear or a deployment.
