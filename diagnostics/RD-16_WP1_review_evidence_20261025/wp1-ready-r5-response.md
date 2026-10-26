# RD-16 WP1 R5 Review: Read-Only Event Diagnostic and Owner Launcher

## Verdict: **Review OK**

There are no mandatory defects. R4 M1 and M2 are both closed.

### Bound artifacts

This verdict covers only these exact bytes, as supplied in the input:

| Artifact | Bytes | SHA256 |
|---|---|---|
| `diagnostics\RD-16-WP1_event-inspect_20261025.php` | 5824 | `61c329aa15b358ff3d8b1d43080b4ece8abea45ab1cc3749bcf5da0e9a88c448` |
| `diagnostics\RD-16-WP1_event-inspect_owner_20261025.ps1` | 4493 | `95e8591f630a76117e9c61270950968dffd46aa9ea60373e22234f812adae9c8` |

Conditions on the binding:
- I had no tools, so I did not recompute either hash or byte count.
- I did check one thing internally: the launcher's embedded `$Sha` equals the stated PHP hash.
- Before release, Codex must confirm that the on-disk bytes match both rows.
- Any byte change voids this verdict.
- The accepted R3 pair and `state.json` are untouched. They are cited only as the frozen predicate source and to show nothing changed. Runner `c8a8cc55…abfda1a` and packet `c9976145…aeaf` are not reopened.
- The predicate binds to the supplied text, which `build.py` extracts between fixed markers. The R3 runner bytes were not in this input.

## R4 mandatory closure

**M1, the failure marker in stdin mode: closed.**
- The `catch` block no longer references `STDERR`. It clears all output buffers, then writes `RD16_EVENT_INSPECT_STOP reason=<code>` to stdout and calls `exit(1)`.
- `<code>` comes from a closed literal allowlist. The list covers all 16 messages thrown in the file: php, root, mapping, connect, schema, readonly, row_cap, field_cap, relevant_cap, linked_source, source_cap, source_read, io_warning, transaction_close, prefix and query.
- Any other `Throwable`, including `Error`, `ParseError` from `config.php` and `TypeError`, maps to `internal`. No exception detail is emitted.
- The test meets the R4 requirement:
  - Native `php -n` reading the exact source from stdin returns exit 1, exactly `RD16_EVENT_INSPECT_STOP reason=php\n`, and empty stderr.
  - The bash → `exec` PHP path gives the same result.

**M2, the no-temp-file claim: closed by preferred option (a).**
- There is no heredoc. The `<<` check in the mock enforces this.
- The literal here-string ends without a trailing newline. The launcher then appends `\n` plus the source, so `exec "$P" -d display_errors=0 -d log_errors=0` is immediately followed by `<?php`.
- Bash reads stdin one byte at a time when the input is non-seekable, such as an sshd pipe or socket. If the input is seekable, bash syncs the offset before `exec`. Either way, PHP inherits exactly the remaining source.
- The two command substitutions, `readlink` and `php -r`, do not read stdin.
- The real local `bash -s` → `exec` PHP stdin test proves the remaining source is handed over intact: PHP reached the version check and emitted the exact marker.
- The report correctly states that `bash -n` covers only the shell prefix.

## Verified within the stated fake boundaries

**SQL and privacy**
- No change from the R4 acceptance.
- The SQL is: `SHOW COLUMNS`, which runs after prefix validation; then fail-closed `START TRANSACTION READ ONLY`; then a single `trigger`/`action` `SELECT` with `LIMIT 1025` against a 1024 cap.
- Caps: 4096 bytes per field and 64 relevant rows. Rollback runs in `finally`.
- Nothing is written and no customer table is read.
- Config is used only to connect and is never returned.
- `rd16d_safe` hash-redacts long or non-canonical values.

**Bootstrap**
- The new `DIR_SYSTEM` and `DIR_APPLICATION` mapping checks fail closed, so the collected core sources are the ones config actually points to. This closes R4 O1 more strongly than returning booleans would.
- The PHP 8.0 and CLI checks agree with the bash prefix's exact 8.0.30 check.

**Launcher**
- O4 closed: the bytes are read once, hashed, and that same buffer is transmitted.
- O5 closed: the `ssh.exe` check runs before the attempt is consumed.
- The `CreateNew` exclusive ledger is created before dispatch.
- O7 closed: the receipt is written before validation, and the success regex uses a strict `\z` anchor.
- The output must be exactly two LF lines. After that, the JSON contract fields are checked.
- The argument allowlist covers every argument. CR characters are refused.
- Stdout is read asynchronously before stdin is written.
- O6 is resolved by disclosure: the receipt is decoded UTF-8 text, which is acceptable for an ASCII payload.

**Tests**
- The fake boundaries are stated honestly:
  - mysqli is fake and the source files are synthetic.
  - Only `Invoke-LfBody` and the root are replaced.
  - The bash test substitutes local PHP `-n` for the host checks.
- Host PHP 8.0, MySQL, SSH and the server are not claimed as tested.
- The replay-refusal fixture still passes.

## Optional notes (non-blocking; not automatic new tasks)

**PHP diagnostic**

**N1. Reason codes are less precise in some cases.** Every case below still fails closed; only the reported code is less useful.
- `connect` is mostly unreachable. A failed `new mysqli` emits an `E_WARNING` even with `MYSQLI_REPORT_OFF`, and the error handler turns that into `io_warning`.
- A pattern of 4096 bytes or more makes `fnmatch` warn about exceeding `MAXPATHLEN`, which is also reported as `io_warning`.
- A `NULL` `trigger` or `action` in a relevant row reaches `rd16d_safe(string)` and is reported as `internal`.

**N2. PHP 8.0 shows startup errors by default.**
- `display_startup_errors` defaults to on in PHP 8.0. Any startup error would therefore land in stdout.
- This fails closed, through the line-count or regex check.
- `-d display_startup_errors=0` would keep the receipt clean.

**Owner and Codex procedure**

**N3. Remote stderr is not captured.**
- Remote stderr goes only to the owner's terminal, not the receipt.
- On a STOP, ask the owner for the full terminal text along with `stdout.txt`.

**N4. The launcher does not check its own hash.**
- It only records `packetSha`.
- Before release, Codex should confirm on disk that the launcher is `95e8591f…e9c8`.
- After the run, Codex should confirm that `attempt.json` has `phpSha` = `61c329aa…c448` and `packetSha` = `95e8591f…e9c8`.

**N5. Owner precondition (R4 O9 carried forward).**
- The real `work\rd16-event-20261025\owner-receipts\attempt.json` must not exist before the run.

**N6. Login-shell rc files are an inherited risk.**
- If an rc file on the remote side writes to stdout or reads stdin, it is inherited from the R3 transport, which is already accepted.
- This would fail closed through the two-line check.

**Tests and report**

**N7. Test gaps.**
- R4 O8 is still open: `field_cap`, `linked_source`, absent source, `io_warning` and rollback-failure tests.
- The owner mock could also assert the exact `exec …\n<?php` adjacency.

**N8. Stale report wording.**
- The R4-era section of the report still says "Raw stdout saved". The R5 section corrects this.

## Gate

This verdict permits Codex to give the owner one normal PowerShell invocation of the bound launcher, using the existing strict SSH alias and key. It grants me, and any agent, no authority over execution, Git or status, SSH, keys, the server or deployment.

The runtime result is unknown until the owner runs the read-only diagnosis. Main Apply and WP2 remain **HELD**.
