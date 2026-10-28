# RD-17 WP0 source-collection package: integrated CODE/composition review

## Verdict: **Code/Composition Review OK**

The verdict is bound to these exact bytes:

| Artifact | SHA256 |
|---|---|
| Collector `diagnostics/RD-17_wp0_collect_20261026.sh` | `6559ddced3910e3f922a09744ea5a01d84806bdd153463b82c4fbafae28d7e9b` |
| Owner packet `diagnostics/RD-17_wp0_owner-pull_20261026.ps1` | `7b799c408aceab7a3de1b08d709a20c032b58798ddf49acca6c33014bbc05a5a` |

The packet's embedded `$CollectorSha` equals the collector hash above.

I reviewed these other inputs at their supplied hashes:
- `AGENTS.md` `45c0d586…a6fe0`
- `CODEX_WORKFLOW.md` `7a847744…1780`
- Review policy `15396c9e…c18c`
- Local access `3cfeccc3…cd45`
- Handoff `7ce23762…7b036`
- WP0 report `060eb9bb…82e8b7`
- Fixture `83163eb3…7dea`

`run-exchange.py` (`b40a9179…c004`) is the review transport, not part of the owner package. It is not covered by this verdict.

**Scope of this verdict:**
- It covers only the source request: one owner-run collection, download, verification and exact cleanup.
- It does not pass G0, authorize WP1 or RD-18, grant deployment, or waive QA.
- It does not prove PHP 8.0, MySQL or SSH behaviour on the host.
- H1 (DS-token-only header exception) and D2 for RD-18 are recorded correctly. RD-18 stays blocked until RD-17 is deployed and the owner has completed QA.

## What I checked and found sound

**Allowlist and privacy**
- The list has exactly 27 literal paths, matching the report table, with no glob or directory.
- No `config.php`, `.env`, key, `DIR_STORAGE`, session, log, upload or dump is collected.
- Every path component is checked against symlinks, and each target must be a regular file.
- Size caps are 2 MiB per file and 8 MiB in total.
- The obvious-secret grep correctly distinguishes exit 1 (no match) from exit ≥2 (error). The report honestly labels it a screen, not a guarantee.

**Metadata PHP and SQL**
- `config.php` is required only inside the owner-run PHP, and its output is buffered and discarded.
- Errors are converted to a generic message. With `display_errors=0` and `log_errors=0`, no exception text or credentials reach any output.
- The prefix is regex-checked, `DB_PORT` is cast to int, and the code uses `bind_result` only, with no mysqlnd-only API.
- Column existence is checked via `SHOW COLUMNS` before any query.
- Queries run inside `START TRANSACTION READ ONLY` and end with a rollback.
- **Theme:** counts and enabled-sums across all stores, for six fixed routes. No `code` or business rows are returned.
- **Layout routes:** `? LIKE route` reproduces OpenCart's own lookup.
- **Layout modules:** codes and positions are regex/allowlist-bounded, with row and layout-id caps.
- Reusing bound parameters by reference inside `foreach` is correct.
- **Ordering:** metadata runs before the archive is created, so a metadata failure leaves no artifacts.

**Archive and hash binding**
- Sources are hashed before, metadata is collected, the archive is written, and sources are hashed again; the two hash sets must be equal.
- Each archive member is extracted to stdout and compared against the *pre* hash, so the archive is bound to the manifest.
- The count is asserted at 27.
- The manifest carries UTC start/finish, per-file hashes, the archive hash and the metadata.
- The stdout from the collector matches the packet's strict regex exactly. I traced it as five LF lines with lowercase hex.

**Failure and no-replay**
- **Remote:** artifacts must be absent before the run, and `set -C` (noclobber) closes the race between check and write.
- **Local:** the run refuses to start if the drop directory exists.
- Every failure stops without cleanup and preserves evidence. Raw stdout is saved before the exit code is checked.

**Download and cleanup ordering**
- Both files are downloaded first.
- Local hashes are checked against the receipt hashes.
- The manifest must cross-check the archive hash and contain `done=ok`.
- The remote side then re-hashes both files, and `rm` removes exactly those two named files. There is no wildcard or recursion.
- Hashes are regex-validated before being substituted into the cleanup body, so no injection is possible.

**LF transport**
- CRLF is normalized and any stray CR is rejected.
- Bytes are written to the stdin base stream, and arguments are allowlisted.
- No remote child command reads the inherited stdin, so the script piped to `bash -s` is not consumed by any child process.

**Fixture fidelity**
- The fixture uses real Bash, tar and sha256sum with only PHP mocked.
- The success, missing-source, mid-collection-drift and existing-artifact-preserved cases each give a discriminating result.

## Mandatory findings

**None.**

## Optional findings (non-blocking; record, no new review round)

- **O1 — theme count route-shape assumption.** If the fresh `model/design/theme.php` stores routes in a different form, the exact-match counts silently read zero.
  - Option for a later revision: add the total and enabled counts for the whole theme table, which is still aggregate-only.
  - Either way, the intake must confirm the route form from fresh source before treating zeros as meaningful. The report already says zeros are not proof of absence.
- **O2 — pre-remote SSH failure consumes r1.** An agent that is not loaded or an auth failure ends in exit 255, after `owner-drop-r1` has already been created. Local retry is then blocked.
  - This is safe, but recovery needs executor assessment.
  - A future revision could run a trivial readiness echo before creating the drop.
- **O3 — remote shell startup output.** If `.bashrc` prints to stdout for non-interactive SSH, the strict receipt check fails after the artifacts exist. This fails closed and the artifacts are preserved, so no change is needed.
- **O4 — BOM on stdin under a UTF-8 console code page.** .NET Framework's `Process.StandardInput` may emit a UTF-8 BOM on a 65001 console.
  - The impact here is harmless: line 1 of both bodies (shebang, `set +x`) would fail with "command not found" before `set -e` is active.
  - The comment "byte-exact" is therefore not strictly guaranteed. Future bodies should not put a security-relevant command on line 1.
- **O5 — fixture gaps.** The fixture does not exercise:
  - metadata failure leaving no artifacts;
  - a pre-existing manifest alone;
  - a symlinked component;
  - the PowerShell packet itself, with mocked ssh/scp.

  I verified the PowerShell receipt contract by hand. These remain disclosed fake boundaries.
- **O6 — hygiene.**
  - Fix the stray 4-space indentation left from a removed block in the packet.
  - Record the fixture output and these verdict identities in the report's "Record final identities" placeholder; this is bookkeeping and closes mechanically.
  - Confirm `work/` is git-ignored, since the archive and manifest land there.
  - The `CODEX_WORKFLOW.md` narrow-pull text ("Subscription Claude CLI writes the pull block") is stale against the 2026-10-21/24 amendments. That is a documentation follow-up, not a blocker.
- **O7 — adapter, out of scope.** `run-exchange.py` has a duplicated `assert`, and its gates are `assert`-based, which `python -O` disables.

## Material fake-boundary and interaction gaps (accepted limits, not defects)

1. The metadata PHP/SQL has never run against PHP 8.0 or MySQL/MariaDB. Its first real execution is the owner run. It fails closed before the archive is created, so the worst case is a consumed r1 requiring r2.
2. The PowerShell, OpenSSH, agent and scp path is proven only by AST parse plus precedent. The `IdentityAgent` pipe literal relies on ACC-009 packet bytes that were not supplied to me.
3. Template loading through an extension theme (`config_theme`) or events is not captured by the theme counts. `twig.php`, `event/theme.php` and the theme model are pulled, so the intake can check this. Event/OCMOD admission remains a pre-application requirement, as the report states.

## Next

1. Owner runs the packet at `7b799c40…` once.
2. Executor performs strict intake, the drift comparison and the four G0 findings.
3. **STOP for the owner's G0 decision.**
