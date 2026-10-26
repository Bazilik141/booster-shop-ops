# RD-16 WP1 R4 Review: Event-Admission Diagnostic and Owner Launcher

## Verdict: **Changes requested**

This verdict covers only these two artifacts, as their content was supplied in the input:

| Artifact | Bytes | SHA256 |
|---|---|---|
| `diagnostics\RD-16-WP1_event-inspect_20261025.php` | 5371 | `d2b8a839fa6e58eb9833bb6334638292fb818448689f1ab33eb0b0a34d9caf8d` |
| `diagnostics\RD-16-WP1_event-inspect_owner_20261025.ps1` | 4250 | `8ca43dd011a146033afd569c1a10b45920294b9b618111acdb3cb12ee52cd739` |

Neither hash is approved for owner execution. I had no tools, so I did not recompute any hashes or sizes. Any fix produces new hashes, which need a new R5 ready-stage review.

The accepted R3 pair (runner `c8a8cc55…abfda1a`, owner packet `c9976145…aeaf`) and `state.json` are untouched by this package and stay as they are. Main Apply and WP2 remain **HELD**. No host facts are inferred.

## Mandatory defects

**M1. The PHP failure path is broken when the script is read from stdin.**
- The script runs as `php … <<'RD16_EVENT_INSPECT_PHP'`, so PHP reads it from stdin.
- In that mode, PHP CLI does not define the `STDIN`, `STDOUT` and `STDERR` constants. This is documented in the PHP manual (CLI I/O streams).
- So `fwrite(STDERR, …)` in the `catch` block throws an uncaught `Error` ("Undefined constant").
- Result: a fatal error, exit code 255 and no `RD16_EVENT_INSPECT_STOP` marker.
- It still fails closed: exit is non-zero, nothing goes to stdout, and `display_errors=0` means no leak.
- However, the stated failure contract is false. With a one-use, no-retry ledger, a failed run also cannot tell apart its roughly 15 causes (`php`, `root`, `mapping`, `connect`, `schema`, `readonly`, `row_cap`, `field_cap`, `relevant_cap`, `linked_source`, `source_cap`, `source_read`, `io_warning`, `transaction_close`, `prefix`).
- The harness never exercised this path, because the bootstrap is excluded under `RD16D_TEST`.

Required fix:
1. Emit a bounded stop line, preferably on stdout so the saved receipt captures it, e.g. `RD16_EVENT_INSPECT_STOP reason=<code>`, then `exit(1)`.
2. Take `<code>` from a closed allowlist of the literal `RuntimeException` messages above. Anything else maps to `internal`. Never include `$e->getMessage()` from a non-allowlisted source.
3. If stderr is kept, use `fopen('php://stderr','w')`, not `STDERR`.
4. Add a local test that runs the diagnostic via stdin (`php -n < diagnostic.php`). Locally it reaches the catch path, at the `php` or `root` check. Assert exit code 1, the exact stop line and no other output.

**M2. The claim of no remote temporary writes is not guaranteed by the shell composition.**
- Bash materializes here-documents as temporary files in `$TMPDIR` (default `/tmp`) in two cases:
  - always, on bash versions before 5.1;
  - on 5.1 and later, when the document exceeds the build-time pipe size. The roughly 5.3 KB body can exceed a 4 KiB `PIPESIZE`.
- The file is mode 0600, unlinked right away and holds only the non-secret diagnostic source. Even so, it contradicts a safety property stated in the approval scope.

Required fix, either option:
- **(a) Remove the here-document.** Make the last body line `exec "$P" -d display_errors=0 -d log_errors=0` and append the PHP source directly, with no delimiter. `bash -s` reads non-seekable stdin unbuffered, so PHP receives the remainder. This is still stdin-script mode, so M1's fix still applies.
- **(b) Restate the claim precisely** as "a transient 0600 shell temp file containing only the reviewed diagnostic source may be created and unlinked by bash", and have it explicitly accepted at the gate.

Option (a) is preferred.

## Verified as acceptable within the stated fake boundaries

**SQL**
- `SHOW COLUMNS` is a metadata read and uses a validated prefix.
- `START TRANSACTION READ ONLY` is fail-closed.
- The single `SELECT` reads only `trigger` and `action` with `status=1`, and `LIMIT 1025` pairs correctly with the 1024 cap.
- The 4096-byte field cap and the 64-row relevant cap are fail-closed.
- `rollback()` sits in `finally`.
- Nothing else is queried, no customer tables are read and nothing is written.

**Privacy**
- Config is used only to connect; credentials are never output.
- Exception text is discarded.
- `rd16d_safe` hash-redacts values that are non-canonical or longer than 512 bytes.
- The output is limited to the four fixed core paths, read component-by-component with `is_link` checks, capped at 64 KiB each and 128 KiB total, and checked for exact length.

**Predicate fidelity**
- `build.py` copies the substring between fixed markers, so it is exact by construction.
- This cannot be checked against the R3 runner bytes here, because the runner was not supplied. The binding is to the supplied text.

**Bootstrap**
- Checks: CLI, PHP 8.0, `getcwd`/`realpath`/unlinked root and `config.php`, and `DIR_OPENCART` mapping.
- Output buffering absorbs any output from config.
- The success output is exactly two LF lines. The JSON is single-line: base64 contains no newline, and the JSON uses escapes.
- The code is compatible with PHP 8.0 by inspection: no 8.1+ syntax or functions.

**Shell composition**
- The PowerShell here-string is literal, CRLF is normalized and CR is refused.
- The argument allowlist covers all fixed options.
- The quoted delimiter does not appear on any line of the PHP source.
- Bash checks `HOME`, the root realpath and the PHP 8.0.30 version, and its exit status propagates through ssh.

**Launcher**
- The hash gate runs before anything else.
- The `CreateNew` exclusive attempt file is created before dispatch.
- Stdout is read asynchronously before stdin is written, so there is no deadlock.
- The receipt is written before validation.
- On any mismatch it stops without retry.
- A repeat run refuses before dispatch, and the fixture test confirms the receipt is unchanged.

**Tests**
- The fake boundary is honestly stated: fake mysqli; synthetic sources; only `Invoke-LfBody` and the root replaced; bash `-n` only; host bootstrap and the PHP 8.0 runtime not executed.
- The fixture test implicitly proves the root substitution took effect, because its fixture receipt assertion would fail otherwise.

## Optional notes (non-blocking)

- **O1. Bootstrap-to-source binding:** Return booleans for `DIR_SYSTEM === $root.'/system/'` and `DIR_APPLICATION === $root.'/catalog/'`. This proves the collected sources are the active ones.
- **O2. Source scope:** Consider adding `system/framework.php` and `system/config/catalog.php`, the config `action_event` hooks, to complete the actual-hook picture. Only do this if they are confirmed non-secret core files.
- **O3. Row attribution:** Consider adding `event_id`, `code` and `sort_order`, still passed through `rd16d_safe`, so denied rows can be attributed to an extension. This requires extending the schema check.
- **O4. Hash/read race:** The launcher hashes the PHP file and then rereads it. Read the bytes once, hash that buffer and compose the body from the same buffer.
- **O5. Wasted attempt:** Pre-check `Test-Path $Ssh` before creating `attempt.json`, so a missing ssh.exe does not consume the one-use attempt.
- **O6. Receipt wording:** The receipt is decoded UTF-8 text, not raw bytes. This is fine for the ASCII payload; say so in the report, or capture the bytes.
- **O7. Stricter end anchor:** Use `\z` instead of `$` in the success regex. As written, `$` also accepts an extra trailing newline.
- **O8. Extra tests:** Add fake-boundary tests for `field_cap`, `linked_source`, an absent source, `io_warning` and rollback failure.
- **O9. Owner precondition:** Before running, the owner should confirm that the real `work\rd16-event-20261025\owner-receipts\attempt.json` does not exist. The launcher refuses if it does.

## Gate

This review does not open the owner diagnostic gate. After M1 and M2 are fixed, an R5 review must bind the new PHP and launcher SHA256s. This review grants no execution, SSH, key, server or deployment authority. Main Apply and WP2 remain HELD.
