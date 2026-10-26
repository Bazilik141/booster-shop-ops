# RD-16 WP2 Read-Only Intake r1: Review of Diagnostic and Owner Launcher

## Verdict: **Review OK**

There are no mandatory code changes. The binding intake conditions below are mechanical steps for Codex, not code edits. The optional notes do not block release.

## Bound artifacts

| Artifact | Bytes | SHA256 |
|---|---|---|
| `diagnostics\RD-16-WP2_intake_20261026.php` | 9900 | `f3b5cdc13a78b96ffdb92ee8d7ce297b530b4a03b8f6aad7d92777a4eeccd6ba` |
| `diagnostics\RD-16-WP2_intake_owner_20261026.ps1` | 4496 | `897303201d8aa5937eebb481aa7925696949f9a16b0e4085e8a567c685133b1a` |

**Binding conditions**
- I had no tools, so I did not recompute either hash.
- The launcher's embedded `$Sha` equals the stated PHP hash.
- Before release, Codex must confirm that the bytes on disk match both rows. Any byte change voids this verdict.

**Derivation check (manual)**
- **Launcher.** It differs from R6 `e0c06bc5…` only in five places:
  - the source filename (−6 bytes)
  - the hash (same length)
  - the receipt directory (+5)
  - the task string ×2 (−12)
  - the markers ×2 (−4) and the final message (+25)
  
  That nets exactly +8 bytes (4488 → 4496). All transport safety from R6 carries over byte for byte: SSH options, argument allowlist, CR refusal, async read, `CreateNew` before dispatch, receipt saved before validation, the `\z` match, the two-line rule and the contract check.
- **PHP.** My manual delta from R6 `f24136e1…` came within a few bytes of 9900 but did not reconcile exactly. I did not rely on it. Instead I reviewed the final bytes in full.
  - The R6 logic is carried unchanged: `rd16_event_check` matching, rd16d_safe structure, the event SQL, the read-only transaction with rollback in `finally`, the row, field and relevant caps, link, size and read checks, the stdout-only STOP path, and the bootstrap.
  - The changes are exactly these:
    - the 26 targets
    - `%` added to the safe character class
    - schema checks for 3 more tables
    - 3 bounded metadata queries inside the transaction
    - 21 added path literals
    - `DIR_EXTENSION` and `DIR_TEMPLATE` pinning, which closes R6 O1
    - 2 new reason codes
    - the task string and markers

## Safety, privacy and composition

**SQL**
- All statements are SHOW COLUMNS or SELECT. Every SELECT runs inside `START TRANSACTION READ ONLY` with rollback in `finally`. There are no writes and no access to customer, settings, `code` or config values.
- **Theme query.** It returns counts only. `GROUP BY` over three IN values gives at most 3 rows. The cap check is redundant but harmless.
- **`layout_route` query.** It uses `'<route>' LIKE `route``, which correctly mirrors OC4 `model/design/layout::getLayout`. Ids are cast to int, and route values pass through `rd16d_safe`. Adding `%` exposes only ASCII route wildcards, so it is not a privacy concern.
- **Module query.** `layout_module` rows are code, position and int only, and are capped at 16.

**Classifier**
- The 26 targets cover:
  - the `account/edit` view
  - the bare `account/edit` route used by the router for GET
  - the `.index`/`.save` and `|index`/`|save` variants
  - the WP1 hub, shell and header
- Prefix matching through `fnmatch($p.'*')` remains conservative against OC's start-anchored `preg_match` in `Event::trigger`.
- `allowed` is informational only. I agree it grants no hook admission.

**Sources**
- There are 33 fixed literals with no globbing or dynamic paths. Each path component is link-checked. The 512 KiB per-file and 2 MiB total caps fail closed.
- Absent files are reported as `absent`, not as errors. Sources are hashed and base64-encoded, never included.
- The ~1.23 MiB estimate gives about 1.7 M JSON characters, under the cap.

**Bootstrap**
- `config.php` is required privately for the DB connection and mapping only.
- The two new mapping pins match standard OC 4.0.x `config.php`. If the host deviates, the run fails closed with `mapping` and consumes the attempt. That is acceptable.

**Identities and authority**
- The task ID, both markers and `owner-receipts-intake` are distinct, so the consumed WP1 ledgers cannot collide.
- One owner-run PowerShell invocation is permitted. No remote staging, temp files, cache or state writes, so no rollback is needed.
- This review gives no execution, SSH, key, server, Git, status or deployment authority to me or any agent.

## Fixtures

**What passes**
- The fake-mysqli collector runs against the exact 33 paths, and covers the following, plus the per-file `source_cap`:
  - success and the `account/%` layout candidate
  - redaction
  - row, relevant and schema refusal
- The predicate checks cover edit view, index, save and pipe triggers.
- PHP `-n` stdin and `bash -s` → `exec` PHP give the exact STOP marker.
- The PowerShell AST, receipt and replay-refusal fixture substitutes only the root and the transport.
- A failed root substitution would be detected: the missing fixture receipt makes the test throw, and the prepare step asserts the real `attempt.json` is absent.

**Disclosed limits**
- Local PHP 8.3, synthetic sources, and no host, MySQL, SSH or PHP 8.0 proof.

## Binding intake conditions (Codex, mechanical)

1. **Preconditions.** Before release, both `work\rd16-wp2-20261026\owner-receipts-intake\attempt.json` and `stdout.txt` must be absent. The prepare step asserts only `attempt.json`; check `stdout.txt` too.
2. **Ledger.**
   - `phpSha` must be `f3b5cdc1…d6ba`.
   - `packetSha` must be `89730320…133b1a`.
   - `php` must be `8.0.30`.
   - The top-level key set must be exactly what the code emits.
3. **Payload.**
   - `sources` must contain exactly the 33 keys.
   - Each present entry's decoded length and SHA must match.
   - Their sum must equal `source_bytes`.
4. **Drift against WP1.**
   - The 12 R6 paths are compared with the R6 capture.
   - The WP1-applied targets are compared with the accepted R2 after-hashes: `account/shell.php`, `account/shell.twig`, `boostershop-ds.css`, `common/header.twig` and the uk-ua `account.php`.
   - Any mismatch is recorded and holds WP2 composition. Do not re-run.
5. **Holds.** Do not expand the list ad hoc; any new file needs a new reviewed supplement. Hold the affected composition in any of these cases:
   - a needed path is `absent`
   - a new relevant row's handler or model is outside the 33
   - `theme_enabled` is non-empty for a route
   - the `account/edit` layout candidate is not 6
   - a non-core module is in layout 6
6. **R6 O2, resolved now.** Intake eligibility when the launcher throws after saving the receipt:
   - **Eligible:** a throw inside `ConvertFrom-Json` only. Codex's own parser must then pass conditions 2–4 on a receipt that ends with the exact OK marker and has exactly two lines.
   - **Not eligible:** a throw from the contract mismatch, a non-zero exit code or a STOP marker. In those cases, collect the owner's terminal text and do not retry.
7. **Private handling.** Treat captured sources as private (R6 O5). Quote only the excerpts later review packets need.

## Optional notes (non-blocking)

- **O1. Theme `status=1` filter.**
  - The handler (`controller/event/theme.php`) and the model (`model/design/theme.php`) are not captured, so it is unproven that disabled rows are ignored.
  - Counting by `(route, status)` would be strictly more conservative.
  - Alternatively, cross-reference the WP0 theme-row evidence.
  - Adopting this requires r2 and a rebind.
- **O2. Hard-coded `layout_id=6`.** Deriving the ids from the candidate rows (int-cast, `IN`) would avoid a hold if the edit page maps elsewhere.
- **O3. Uncaptured files.**
  - `booster-account-universal.js`, `common/column_left.php` and the account-sidebar module source are not captured. They were in the WP0 list.
  - This is acceptable if WP2 does not touch them and WP1 evidence covers them. Otherwise hold as in condition 5.
- **O4. Trigger scope.** `model/*` and `language/*` triggers are not enumerated. This is acceptable because save, model and validation are untouched. If WP2 adds language keys, note that `language/.../after` hooks could override them.
- **O5. Schema reason codes.** `schema` does not say which table failed (R6 N1).
- **O6. Test gaps.**
  - Fake-SQL matching is prefix-only for the metadata queries, and query order relative to `START TRANSACTION` is not asserted.
  - There are still no tests for: absent source, aggregate cap, `linked_source`, `field_cap`, `io_warning` or rollback failure (R6 O3).
  - Python `text=True` stdin on Windows may translate `\n` to CRLF; byte input would be more faithful.
  - The build step's `replace` calls are still not count-checked (R6 O4).
- **O7. Launcher wording.** The final message repeats "send this output". This is cosmetic.
- **Still applicable from R6:** N2 (`display_startup_errors`), N3 (remote stderr is not in the receipt), N4 (the launcher does not self-hash) and N6 (rc-file inheritance).

## Gate

Once Codex has verified both hashes on disk and condition 1, Codex may give the owner one normal PowerShell invocation of the bound launcher.

This does not open WP2 Upload or Apply, and it does not open a header-token exception, any header or data write, or deployment. **Main WP2 remains HELD** pending its own reviewed ready package.
