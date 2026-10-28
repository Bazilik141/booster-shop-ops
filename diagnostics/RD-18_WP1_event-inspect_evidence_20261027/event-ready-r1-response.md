# Review OK: RD-18 WP1 event-inspect diagnostic and owner packet

**Verdict: Review OK. There are no mandatory findings.**

This verdict covers only the exact pair below, reviewed together. I ran no tools, computed no hashes and executed nothing. The identities come from the supplied file headers, and I checked them against each other.

| Artifact | SHA256 |
|---|---|
| Diagnostic PHP `diagnostics/RD-18-WP1_event-inspect_20261027.php` | `13c3bfe0b98a2d421caf53a902f503263c67f4b2db2619a3032675183430ae77` |
| Owner packet `diagnostics/RD-18-WP1_event-inspect_owner_20261027.ps1` | `aeb647be7552c110237799341fb3f3e4236094bc08c7c0bfb9b6f0251af8f310` |

The verdict is void for any other bytes.

## Identity cross-checks

- **Packet pins the PHP.** The packet's `$Sha` literal equals the PHP hash. `release-facts.json` and the report's "Exact ready identities" carry the same pair.
- **Embedded identities.** The PHP's labels match the supplied references:
  - `classifier_sha256` is `48f8b701…6d83`;
  - `main_runner_sha256` is `f2bd1d43…8221`;
  - `main_packet_sha256` is `2b2713c0…4b91`.
- **Failure evidence.** `owner-Verify-unadmitted-event_20261027.json` binds the main pair, the Verify receipt `1e00326b…` and the state `46320ed7…`. These match the unchanged hash-reference list.
- **The main pair is frozen.** The packet does not reference `rd18-main-20261027`, the main state, the staged runner or Verify/Apply.

## Predicate fidelity

- **Text comparison.** The diagnostic's `rd16_event_check` is identical, line by line, to the supplied "CURRENT MAIN EXACT PREDICATE". That covers the four exact `$reviewedHooks` pairs, the strict `in_array` on the raw pattern, prefix stripping, `|`→`.`, the 66-trigger list and the `event/theme` view-before rule.
- **The rename pass cannot alter it.** `prepare.py` slices the predicate from the main runner, inserts it, and only then applies the `RD-16`/`RD16`/`rd16d_` renames. None of those tokens occurs in the predicate (`rd16_event_check` does not contain `rd16d_`).
- **Byte-level guard.** `assert predicate in source` guards the inserted bytes. I could not byte-compare them myself.
- **What the predicate does.** It classifies strings only. No action is resolved, loaded or invoked, and no OpenCart bootstrap, model or provider runs.

## Code review: PHP

**Bootstrap (unchanged from the RD16 parent apart from names):**
- It requires the CLI SAPI and PHP 8.0.
- It checks the fixed root by `getcwd` and `realpath`, with no symlinks on the root or `config.php`.
- It checks the `DIR_*` mapping.
- It connects with `mysqli_report OFF`, an integer port and `utf8mb4`.
- `ob_start` discards any stray output from config.

**Privacy and redaction:**
- Config and credentials stay on the host and never appear in output.
- Failure output is limited to one of 16 fixed reason codes, or `internal`.
- `display_errors` and `log_errors` are off both through `-d` and `ini_set`.
- Only enabled `trigger` and `action` values are read, and only relevant rows are emitted.
- Values are returned in clear only if they are 512 bytes or less and use the technical-route character set. Anything else is returned as SHA256 plus byte length.
- Non-relevant rows contribute only to `enabled_rows_scanned`.
- The query reads no customer, order, setting, log or session data.

**SQL:**
- The prefix is validated before use.
- Required columns are checked with `SHOW COLUMNS` before the `SELECT`.
- The `SELECT` runs inside `START TRANSACTION READ ONLY`, with `rollback` in `finally`.
- The SQL is fixed, with no interpolation other than the validated prefix.

**Caps:** each cap stops fail-closed.
- `LIMIT 1025`, with more than 1024 rows → `row_cap`.
- A field over 4096 bytes → `field_cap`.
- More than 64 relevant rows → `relevant_cap`.
- More than 64 KiB for one file, or more than 128 KiB in total → `source_cap`.

**Sources:**
- Four fixed paths are walked component by component, and any symlink is rejected.
- A missing file is reported truthfully as `absent`.
- A zero-size file or a `filesize` failure stops the run (new `<=0` guard).
- Only `bytes` and `sha256` are returned. The parent's base64 body export is removed, so this is narrower than the accepted RD16 parent.

**Output:**
- Output is one JSON line plus a banner.
- Every emitted value is ASCII or hex, so `JSON_THROW_ON_ERROR` cannot be triggered by data.
- The contract fields are `writes=none`, `customer_reads=none` and `admission=none`.

## Code review: owner packet

**Order of operations:**
1. The PHP hash is checked before anything else.
2. `ssh.exe` must exist before an attempt is consumed.
3. `attempt.json` is created exclusively, with `CreateNew` and `FileShare None`, before dispatch.
4. The raw stdout is saved before branching on the exit code.

**Contract checks:**
- The output must end with the exact banner.
- There must be exactly two lines.
- `task`, `writes`, `customer_reads` and `admission` are each compared with a case-sensitive match.
- `Set-StrictMode 2.0` makes any missing property throw, so it also stops the run.

**Transport:**
- `Invoke-LfBody` is identical to the reviewed, owner-executed RD16 packet.
- The connection is batch mode with strict host-key checking, the explicit agent pipe, publickey only, and no forwarding or agent-add.
- The argument character set is checked.
- The body is LF-only (CR rejected) and fed to `bash -s`.
- The guards are fixed `HOME`, a non-symlinked `public_html` and pinned `/usr/local/bin/php` 8.0.30, followed by `exec php` on the remaining stdin.

**One use:** a replay is refused by the `CreateNew` exception before dispatch, and the receipt is preserved.

**Diff from the RD16 parent:** limited to task names, the hash, the receipt directory, the `unadmitted_count` print and the `admission` check.

## Tests (as reported, not executed by me)

- **PHP collector: 12 cases.** These run the exact collector under native PHP 8.0.30 with a fake `mysqli`. They cover classification, the redaction and safe-field cap, the bare/global pattern, schema, readonly, query failure with rollback, the row, relevant, field and source caps, and an absent source.
- **Packet: PS 5.1 and PS 7, 4 cases plus replay.** The whole transport function is replaced before execution. They cover success, nonzero exit, bad contract, and source tamper (0 calls, nothing consumed), with the replay preserving the receipt.
- **Assessment.** These are adequate for this read-only diagnostic. The scope-error repair confined to the harness is acceptable.

## Optional findings (non-blocking; any code change needs an affected review)

- **E1: equivalence with the main guard is not in this evidence.** I did not see the main runner's event query or every site that emits `unadmitted_target_event`.
  - If the main guard's SQL differs, or that error is also raised outside the `allowed=false` path, the diagnostic may return 0 or different pairs.
  - The report already says a zero count grants nothing.
  - Closure: at intake, record the exact main-runner query and emit sites from `f2bd…`, and treat any count of 0 or any mismatch as non-conclusive. Do not resume the main runner on that basis.
- **E2: the packet checks only part of the result.** Strict local intake should also verify:
  - the three identity hashes;
  - that `unadmitted_count` equals the number of rows with `allowed=false`;
  - `php` is 8.0.30;
  - the integer types;
  - the four core hashes against the main runner's pins, before reusing any earlier dispatch semantics.
- **E3: a connection failure is reported as `io_warning`, not `connect`.** With `MYSQLI_REPORT_OFF`, `mysqli::__construct` raises a warning that the error handler converts first. This is still generic and safe, because the warning text is never printed. Interpret `io_warning` broadly.
- **E4: some edge cases stop as `internal` or `io_warning`.**
  - A NULL `trigger` or `action` in a relevant row causes a TypeError in `rd18d_safe(string)`, which stops as `internal`.
  - A pattern of 4095 bytes or more makes `fnmatch` warn, which stops as `io_warning`.
  - Both are fail-closed and inherited.
- **E5: test results are not bound to hashes.** The results JSONs do not embed the PHP or packet hash they tested. This is the same as R2-O3.
- **E6: minor code nits.**
  - The bytes come from `filesize` while the hash comes from `hash_file`, so there is a negligible race.
  - The header comment reads "and4", missing a space. This is cosmetic.

## Evidence limits

- **Not executed or certified.** No real hosting MySQL, SSH, stdin-to-bash-to-PHP stream, remote shell profile output or `public_html` was exercised or certified. The LF stream transport is accepted on the basis that its code is identical to the owner-executed RD16 parent.
- **No source bodies seen.** I did not see the main runner body or any extension or callback source.
- **Callback identity is still unknown.** I make no claim about whether the rejected hook is harmless. Any actual callback is assessed only after the receipt, through one bounded, named source supplement if needed.
- **What this review grants.** Release of this diagnostic only. No admission, allowlist change, main Verify or Apply retry, state reset, deploy, Git, Notion or dashboard action is granted.

## Owner use (not granted by this review)

1. Run the packet once.
2. On success, return the terminal output (`RD18_EVENT_INSPECT_OK`, `unadmitted_count=N`, receipt path).
3. Any STOP means preserve `attempt.json` and `stdout.txt` and do not retry. Never run the main Verify or Apply.
