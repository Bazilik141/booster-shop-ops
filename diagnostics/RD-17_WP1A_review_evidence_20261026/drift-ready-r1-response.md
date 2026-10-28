# RD-17-WP1A dependency-drift diagnostic: integrated CODE/composition review

## Verdict: Review OK, for one owner hash capture only

There are no mandatory findings.

This verdict covers only the exact pair below. It does not permit main Verify, Apply, retry, pin changes or edits to the main runner or packet. Main WP1A stays HELD on dependency drift.

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `diagnostics/RD-17-WP1A_dependency-drift-inspect_20261026.php` | `55e5339d45e4aa6e414617c4cf5c0bd330d135aa051061d906467efaa1f407da` | 30972 |
| `diagnostics/RD-17-WP1A_dependency-drift_owner_20261026.ps1` | `acafd789f9fad535e975ef5ca034c8a82543e512ac3282587ebc742ae113eed3` | 34167 |
| Embedded manifest (decoded JSON) | `409bfa8a9a50f5a644ec62d9dd49939c08249d42a02e773cf43f8323528b412e` | 114 rows |

**How the bindings were checked**
- These are the declared identities. I did not recompute any SHA-256; byte identity is for mechanical closure to confirm.
- The packet's `$InspectorSha` equals the PHP hash above.
- `$MainSha` and `$MainPacketSha` equal the r2-reviewed main pair and `state.json`.
- I hand-decoded the head and tail of the embedded base64:
  - the first row is `catalog/controller/event/activity.php` with before `fd7b529f…`;
  - the first target row is the new helper with `absent` → `51b644c8…`;
  - the last row is `checkout-reskin.js` with after `dbafb5c8…`.
- These match `profile-dependencies.json`, `profile-targets.json` and the r2 target table.
- I did not decode every byte. Divergence between the PHP and PS manifest copies is still caught at runtime:
  - PHP checks its manifest against `409bfa…`;
  - PS requires `manifest_sha256` to equal `409bfa…`;
  - PS requires every returned row's `kind/path/before/after` to equal its own copy, index by index.
- Builder equality against the main's decoded tables is local evidence I relied on. I did not re-run it: the main body is identity-only.

---

## Mandatory findings

None.

---

## Assessment by requested area

**Zero production writes, DB access or source-body export**
- The PHP uses only `is_link`, `file_exists`, `is_dir`, `is_file`, `realpath`, `stat`, `hash_file`, `clearstatcache` and stdout.
- There is no `fopen` for write, no `config.php`/`require`, no mysqli and no `exec`.
- The bash preamble runs only `readlink`, `command -v` and `cd`, then PHP with `-n`.
- The main runner is hashed, never executed.
- Output contains only the root, paths, hashes, sizes, flags, counts, PHP version and capture times.

**The diagnostic matches the stop it is meant to explain**
- Main Verify's `error=dependency_source_drift` can come only from the dependency loop.
- That loop runs after runtime admission and after the target-before check.
- So the stop proves that at 13:23Z:
  - runtime admission passed, including the cache `realpath` equality under `/home2/boosters` and `DIR_OPENCART` equal to the root;
  - all 7 targets matched before;
  - the two registry dependencies matched.
- The inspector computes the same quantity per path as `rd16_state(rd16_walk(...))`: hash, or `absent` when `file_exists` is false.
- The inspector is stricter than the main in a few ways: canonical `realpath` equality, file stability and non-regular-file handling. Each of these fails closed with a named error and never hides drift.

**Root, path, symlink and stable-hash handling, including missing paths**
- Root must be `realpath(getcwd())===/home2/boosters/public_html`, and bash pre-checks each directory with `readlink -f`.
- Each path component is checked for symlinks. Every intermediate component must be a canonical directory.
- A missing component or leaf is reported `absent`. An existing non-directory parent fails as `source_parent_invalid`.
- `.` and `..` are rejected.
- Each file is hashed twice and stat'd twice (dev, ino, size, mtime, ctime, mode), with a final `realpath` check.
- A 10 MB cap applies; the largest relevant file, the main runner at 401823 bytes, is well below it.
- The error handler turns any warning into `filesystem_read_failed`.

**Staged-main identity**
- Remotely, the staged main must equal `27c6bec2…`. A self-deleted (applied) runner appears as `absent` and fails as `staged_runner_drift`.
- Locally, the main, the main packet and `state.json` are pinned, and `applyAttempted` must be absent.

**Receipt fields, rows, flags and totals**
- PS checks the exact top-level key set (case-sensitive), integer types, constants, the 107/7/114 counts, all 8 row keys, row identity per index and the observed-hash or `absent` shape.
- An `absent` row must have bytes 0.
- PS recomputes `matches_before` and `matches_after`, recounts drift totals and compares them with the receipt.
- The PHP's `matches_after` expression is the same as the PS `$AfterMatch`.

**Timestamp types on PowerShell 5.1 and 7**
- 5.1: JavaScriptSerializer leaves ISO strings as strings and returns Int32.
- 7.5 and later: `-DateKind String` keeps strings; Newtonsoft returns Int64, which the `[long]` check accepts.
- 7.0–7.4: no DateKind, so timestamps become DateTime and fail the regex. That fails closed (see O-4).

**No replay or Apply, and local raw-receipt persistence**
- The drop directory guard runs before transport.
- Raw stdout is saved before any parsing.
- A non-zero exit or any validation failure stops with the raw receipt kept.
- `state.json` is never written.

**Native stdin, LF, UTF-8 and EOF transport**
- The PHP must contain no CR and no heredoc delimiter. The whole body is normalised to LF and re-checked for CR.
- The body is UTF-8 without BOM, written to `BaseStream` after `ReadToEndAsync` is started (no deadlock), then stdin is closed to give EOF.
- The quoted heredoc delimiter prevents expansion. PHP's stdin is the heredoc, so it cannot consume the rest of the script.
- On a .NET Framework console at UTF-8 code page, the `AutoFlush` setter could emit a BOM at `Process.Start`. This would only break the sacrificial `set +x` line before `set -e` is active, so it is harmless.

**Strict host keys and owner-only existing agent**
- Options: `BatchMode`, `StrictHostKeyChecking=yes`, `IdentitiesOnly`, the explicit Windows agent pipe, and no AddKeys, forwarding, password or keyboard-interactive.
- Every argument is allowlisted.
- This grants no agent SSH or server access.

---

## Optional findings (recorded, not blocking)

**O-1: The bash `die` codes may be invisible.**
- Stderr is not redirected and `CreateNoWindow=$true`, so `diagnostic_error=…` may not reach the owner's console.
- In that case `receipt-raw.txt` is empty and only "hash capture failed" is shown.
- Closure: none needed for this run. If the capture fails, use the exit code plus whatever the console shows. A successor could print `die` codes to stdout or capture stderr separately.

**O-2: A local exception can leave the drop directory empty.**
- The drop directory is created before transport.
- A local exception before the raw write (for example, stdin `Close` throwing after a broken pipe) leaves an empty `owner-drift-r1/`, and the replay guard then blocks a rerun.
- There is also no `WaitForExit` timeout; a stalled ssh needs Ctrl+C.
- Both outcomes fail closed and touch nothing in production. A follow-up capture would need a new directory or stage, under an owner or executor disposition.
- Closure: record only.

**O-3: The "exists only on stdin" claim is slightly overstated.**
- Depending on the bash version, a ~31 KB heredoc may go through a transient temp file in `$TMPDIR` or `/tmp`, unlinked immediately.
- That is not a production write and the PHP is not sensitive.
- Closure: optional wording fix in the report: "stdin/heredoc only; no persisted remote file".

**O-4: PowerShell 7.0–7.4 would consume the capture and then STOP.**
- The ISO→DateTime coercion fails the timestamp check.
- Closure: run with 5.1 or 7.5 and later. If it happens, re-validate `receipt-raw.txt` locally rather than re-capturing.

**O-5: Some hardening is possible but not required.**
- PS regexes use `$`, which also matches before a trailing `\n`; `\z` would be exact.
- `state.json` could require the exact key set `{runnerSha, packetSha, uploaded}`.
- `-o RequestTTY=no` would guard against a future config change.
- The PS manifest copy could be checked against `409bfa…` before transport. The runtime row-equality check already covers divergence.

**O-6: `absent` can also mean "not visible".**
- `file_exists` returns false on EACCES, so `absent` means "not visible to the owner account". This is the same as the main runner's semantics.
- Closure: keep this in mind during reconciliation if a reported-absent path is expected to exist.

---

## Fake boundaries and test fidelity

Adequate for this read-only scope.

| Real | Faked or untested |
|---|---|
| PHP inspector functions and full 114-row control flow on PHP 8.0.30 | Root, staged main and expected hashes (fictional) |
| PS AST, local hash, state and receipt validation on PS 5.1 and 7.6.5 | The whole ssh process function |

Not exercised:
- the bash preamble and heredoc transport;
- end-to-end parsing of real inspector output by PS (the PS fixture JSON was generated by PS, not PHP);
- Linux `is_link`, `realpath` and stat stability (tests ran on Windows);
- the contradictory-flag, bad-hash, timestamp, PHP-version and wrong-main-sha branches in PS.

By inspection these paths are correct, and each failure mode is fail-closed with no production effect.

---

## Closure conditions

1. Mechanical identity check: confirm both artifact SHA-256 values above are unchanged before the owner runs the packet.
2. The owner runs the packet once, with no `-Step` and no main packet, and returns:
   - the full console output;
   - `owner-drift-r1/receipt-raw.txt`;
   - `owner-drift-r1/receipt.json` if one was produced.
3. A valid capture is an observation only. Zero drift does not authorize re-Verify or Apply.
4. Reconcile any drift in a bounded way:
   - identify the owning change or accepted release;
   - request only the fresh source needed;
   - repair and review the affected main logic;
   - review a successor main pair before admission.
   Never replace a single pin blindly.
5. A failed capture with an expected receipt closes mechanically. Any new capture attempt needs a fresh drop identity, recorded with its reason.
