# RD-17 source supplement: collector and owner packet review

## Verdict: Code/Composition Review OK

| Artifact | SHA-256 bound by this verdict |
|---|---|
| Collector `diagnostics/RD-17_checkout-source_collect_20261026.sh` | `c8aca9e1106e4629384fc44e629f4fbe24f450b2f8452f87c1f3d057f1997ae5` |
| Owner packet `diagnostics/RD-17_checkout-source_owner_20261026.ps1` | `61ffc18ef3d4f2c48f69fda95617746d78fd2d83aafdfea1978db46d7b7aed0f` |

**How the hashes are bound:**
- I checked the supplied bodies against these declared identities. I did not run any hashing tool.
- The packet's `$CollectorSha` is byte-for-byte the declared collector hash.
- Any change to either file makes this verdict inapplicable.
- I relied on context hashes as given: AGENTS.md `45c0d586…`, CODEX_WORKFLOW.md `7a847744…`, the review policy `15396c9e…`, the access document `3cfeccc3…`, the scope amendment `d2ffa628…`, the report `11ff8715…` and the harness `3a49e5bc…`.

**Scope of this verdict:**
- It covers the read-only collection pair only.
- It is not approval of any future default-address or profile-name bridge patch.
- It grants no deployment, DB or SSH authority.

## Checks performed

- **Privacy**
  - `config.php` is loaded only inside owner-run PHP, with output buffered, errors converted to exceptions and display/log turned off.
  - Only sanitized JSON or a fixed STOP line can reach stdout or stderr.
  - There are no row exports, and secret settings are never queried.
  - The obvious-secret screen is honestly labelled as limited.
  - Hash-only bodies are never archived or printed.
- **Schema checks and prepared reads**
  - `DB_PREFIX` is allowlisted.
  - Column presence is checked before reads.
  - All value inputs use bound parameters, with `bind_result` and no mysqlnd-only APIs.
  - Reads run under `START TRANSACTION READ ONLY` and end with a rollback.
  - Row and ID caps are in place, and route, code and position values are validated.
  - Every failure path is fail-closed and happens before the archive exists.
- **58 identities and symlinks**
  - The 27 archived sources and 31 hash-only files are disjoint; I checked this.
  - Every path component is checked for symlinks, as are the webroot and the private directories.
  - `pwd -P` canonicality is verified.
  - Only regular files are accepted, with size caps (a total cap for sources).
- **Drift**
  - Pre/post hashes are compared for both lists around the metadata step and archive creation.
  - Each archive member is independently rehashed against the manifest's pre-capture lines.
  - The member count is enforced.
- **Archive exclusion**
  - The archive is built from the exact 27-file list only, as shown by the harness's 27-member count assertion.
- **Manifest and receipt compatibility**
  - The five-line LF receipt exactly matches the packet's anchored regex.
  - The manifest carries the file list, per-file hashes, dependency hashes, UTC start and finish times, archive hash, metadata and `production_writes=0`.
  - The packet cross-checks the manifest's `archive_sha256` and `done=ok` lines.
- **Download before cleanup**
  - Both downloads are verified against the receipt hashes before cleanup runs.
  - Cleanup re-verifies both hashes remotely, refuses symlinks and non-regular files, and removes only the two exact names.
  - The substituted placeholders are hex only, so they cannot inject commands.
  - The cleanup receipt must match exactly.
- **No replay**
  - Remote artifacts must be absent at start, and the `set -C` (noclobber) setting backs this up.
  - An existing local drop directory blocks a rerun.
  - Every failure branch says to preserve evidence and not retry.
- **Inherited LF transport**
  - CRLF is normalised to LF, and any remaining CR is refused.
  - Native arguments are checked against an allowlist.
  - Stdout is read asynchronously, and a failed send is converted to a non-zero result.
  - The script body hash is pinned.
  - Inside the script, nothing reads stdin while it is being consumed by `bash -s`.
- **Authority**
  - The packet is owner-run only.
  - It uses strict host-key checking, `BatchMode`, the agent pipe override and no forwarding, consistent with `BOOSTER_LOCAL_ACCESS.md`.
  - No source, cache or DB mutation occurs.

## Mandatory findings

None.

## Optional findings (non-blocking; record only)

- **O1 – harness assertion has no effect.** In `check-collector.sh`, the line `! tar -tzf "$archive" | grep -q '^catalog/model/checkout/order.php$'` can never fail. Bash `set -e` ignores a pipeline negated with `!`.
  - Exclusion is still proven by the 27-member count assertion combined with the collector's per-member verification.
  - To close: rewrite it as `if tar -tzf "$archive" | grep -qx 'catalog/model/checkout/order.php'; then exit 1; fi` on the next harness touch.
- **O2 – untested branches.** These are reviewed by inspection only:
  - symlink-component rejection;
  - per-file and total size caps;
  - the secret-signature stop;
  - archived-member mismatch.
  
  Synthetic cases could be added later if the harness is reused.
- **O3 – auth failure blocks local rerun.** If SSH authentication fails before connecting (for example, the key is not loaded and `BatchMode` makes ssh exit with 255), the local drop directory already exists. The packet then tells the owner not to retry, even though nothing ran remotely.
  - This is safe and fail-closed.
  - Recovery needs an assessed new identity, not a manual deletion. Note this in the owner instructions.
- **O4 – cosmetic.** There is leftover 4-space indentation after `$ReceiptText` in the packet. It has no semantic effect.
- **O5 – tar owner names.** Tar headers will carry the host account's owner and group names. That account path is already accepted in AGENTS.md. `--numeric-owner` could be used in future collectors.

## Limits and future gates (not conditions of collection)

- **Runtime is unproven.** Host PHP 8.0, the MySQL/MariaDB read-only transaction support, the `theme.status` column, the PHP on the non-interactive PATH, a clean non-interactive stdout and SSH/SCP behaviour all remain owner-run evidence. Local mocks prove none of them. Any mismatch is a fail-closed STOP.
- **Disclosure gate at intake.**
  - Newly collected bodies, especially `confirm.php`, `success.php` and the NP admin and model files, may contain embedded literals that the narrow signature screen does not catch.
  - Before any body is disclosed to review, run the strict intake and the reviewed sanitizer/allowlist.
  - Owner-local storage under the ignored `work/` path is not disclosure.
- **Hash matches are identity, not certification.** A changed consumer among the four order/admin hash-only files needs a separate bounded, privacy-safe supplement.
- **The metadata is not admission.** Theme and layout counts are not event, OCMOD or default-layout admission. Fresh host pins remain required before any later patch application.
- **Later packages need their own review.** The default-address action, the profile-name bridge and the RD-17 visuals each still need their own integrated review, plus owner deployment and QA.

**Reviewer identity:** claude-opus-5-5 through the subscription. No tools were invoked, and nothing was executed or replayed.
