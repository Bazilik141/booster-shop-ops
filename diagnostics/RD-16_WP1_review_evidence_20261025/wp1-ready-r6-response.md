# RD-16 WP1 R6 Review: Hook-Source Supplement Diagnostic and Owner Launcher

## Verdict: **Review OK**

There are no mandatory findings. The optional notes and intake conditions below do not block release.

## Bound artifacts

| Artifact | Bytes | SHA256 |
|---|---|---|
| `diagnostics\RD-16-WP1_hook-sources_20261025.php` | 6475 | `f24136e1a7d01c12128ae3fd58f26645ee3519e08943045f7943fd59bc0b9154` |
| `diagnostics\RD-16-WP1_hook-sources_owner_20261025.ps1` | 4488 | `e0c06bc511010eb513517dfee51e6626378b1289e0e3d2a55e5f607129daaaa1` |

**Binding conditions**
- I had no tools, so I did not recompute either hash.
- I did confirm that the launcher's embedded `$Sha` equals the stated PHP hash.
- Before release, Codex must confirm that the bytes on disk match both rows. Any byte change voids this verdict.
- The accepted R5 pair (`61c329aa…c448` / `95e8591f…e9c8`), its consumed `attempt.json` and the receipt are not reopened. They are cited only as the derivation base.

**Derivation check (manual count)**
- I compared both files line by line against the R5 pair.
- The only PHP changes are: the header comment, 8 added path literals, the two caps, the task string and both markers.
- These account exactly for +651 bytes (5824 → 6475). By my count: +12 for the comment, +640 for the paths, +2 for the caps, −1 for the task string and −2 for the markers.
- The only launcher changes are: the source filename, the hash, the same-length receipt directory, the task string (×2) and the markers (×2). These account exactly for −5 bytes (4493 → 4488).
- `rd16_event_check`, `rd16d_safe`, the SQL, the read-only transaction, rollback in `finally`, the row, field and relevant caps, the bootstrap and mapping checks, the per-component link check, the size and length read check, the error allowlist, the stdout-only STOP path, the SSH options, the argument allowlist, the CR refusal, async stdout read, `CreateNew` before dispatch, receipt-before-validation, the strict `\z` match, the two-line rule and the contract check are all byte-identical to R5. R5's acceptance therefore carries over for them.

## Scope, privacy and authority

**Paths**
- The paths are fixed literals. There is no globbing, directory listing, input-derived path or dynamic action traversal.
- The 8 added paths match the action-route mapping of the four observed denied rows:
  - 3 handler controllers
  - 2 owning models, loaded by the enhanced-measurement and reCAPTCHA hooks
  - 2 JS assets and 1 CSS asset, injected by live search and enhanced measurement
- The 4 core files are revalidated in the same capture as the event rows.

**Caps**
- Per-file 512 KiB and total 2 MiB are justified by the historical 266,277-byte controller.
- Exceeding either cap fails closed (`source_cap`).
- An absent file is reported as `absent`, not as an error.

**Data and execution**
- There are no new reads of DB tables, settings or customer data.
- `config.php` is still `require`d only to connect to the DB, exactly as in R5. Its values are never returned.
  - The wording "no config read" in `source-set.json` should be read as "no config data returned".
- Sources are hashed and base64-encoded, never included or evaluated.
- There are no remote writes, staging, heredoc or temp files, and no changes to events or admission. No rollback is needed.

**Identities**
- The task ID, markers and receipt directory (`work\rd16-hooks-20261025\owner-receipts`) are distinct, so the consumed R5 ledger cannot be reused or overwritten.

**Authority**
- One owner-run PowerShell invocation only.
- This review grants no execution, Git or status, SSH, key, server or deployment authority to me or any agent.

## Tests (within stated fake boundaries)

**What passed**
- The fake-DB collector test passes using the exact twelve-path list.
- It covers: success, read-only SQL, redaction, row cap, relevant cap, schema and the per-file `source_cap` at 524,289 bytes.
- The native PHP stdin failure and the real `bash -s` → `exec` PHP handoff pass with the exact `RD16_HOOK_SOURCES_STOP reason=php` output.
- The PowerShell AST, receipt preservation and replay-refusal fixture pass.
- The fixture writes only under `owner-fixture-<guid>`, not the real receipts directory.

**Limits**
- The limits are disclosed honestly: local PHP 8.3, fake mysqli, synthetic sources, and no host, SSH or server proof.

## Intake conditions (Codex, mechanical; not code changes)

1. **Preconditions.** The real `work\rd16-hooks-20261025\owner-receipts\attempt.json` and `stdout.txt` must be absent before release.
2. **Ledger check.** After the run, `attempt.json` must have `phpSha` = `f24136e1…9154` and `packetSha` = `e0c06bc5…aaa1`.
3. **Payload shape.**
   - `sources` must contain exactly the 12 keys.
   - Each present entry's decoded length and SHA must match, and their sum must equal `source_bytes`.
4. **Drift detection.** If either check below fails, hold the composition and do not re-run.
   - The 4 core source hashes must equal the R5 capture (6,145 bytes total).
   - `relevant_rows` must equal the R5 set of four (trigger/action) with `allowed=false`.
5. **Holds.**
   - If any needed path is `absent`, hold the composition that depends on it.
   - If a captured handler or model references a file outside the 12 (for example an extension Twig template, language file or helper library), also hold that composition.
   - Do not expand the list ad hoc. Any new file requires a new reviewed supplement.

## Optional notes (non-blocking)

**O1. `DIR_EXTENSION` is not checked.**
- The mapping check pins `DIR_OPENCART`, `DIR_SYSTEM` and `DIR_APPLICATION`, but not `DIR_EXTENSION`. Extension autoloader resolution is also not collected.
- The extension bytes are therefore "the files at the standard OC4 path", not proven to be the loaded classes.
- The practical risk is very low, because the standard config uses `DIR_OPENCART.'extension/'`. Any future supplement should still add the equality check.
- Intake should record this as an assumption.

**O2. Large JSON line in `ConvertFrom-Json`.**
- At the caps, the JSON line could reach about 2.8 M characters.
- I believe Windows PowerShell 5.1 lifts the old v3 2 MB `MaxJsonLength` limit, and the expected real payload is well below that anyway.
- If `ConvertFrom-Json` throws, the receipt is already saved and the run fails closed. Codex should state up front whether a receipt from a run where the launcher threw after saving it is eligible for mechanical intake.

**O3. Test gaps.**
- R5 N7 is still open: no tests for aggregate `source_cap` (e.g. 5 × 512 KiB), absent source, `linked_source`, `field_cap`, `io_warning` or rollback failure.
- An absent-source test now matters more, since absence is an explicitly expected outcome.

**O4. `build.py` replacements are unchecked.**
- Only the list replacement is asserted. The other `str.replace` calls are not count-checked.
- This is harmless here, because I verified the final bytes directly. Future builds should assert replacement counts.

**O5. Treat captured sources as private.**
- Captured third-party extension code may carry licensing or embedded vendor values.
- Quote only the excerpts needed in later review packets.

**O6. Carried forward from R5.**
- N1: reason-code precision.
- N2: `display_startup_errors`.
- N3: remote stderr is not in the receipt. On a STOP, collect the owner's terminal text.
- N4: the launcher does not check its own hash, so Codex must check it on disk.
- N6: rc-file inheritance.

## Gate

This verdict permits Codex, after it has verified the hashes on disk, to give the owner one normal PowerShell invocation of the bound launcher. It gives no authority to anyone else.

The runtime result is unknown until the owner runs the read-only collection. **Main Apply and WP2 remain HELD.** No hook admission or event change follows from this review.
