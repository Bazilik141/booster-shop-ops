# ACC-009-N3 integrated final CODE/composition review

**Verdict: REVIEW_OK**

I found no mandatory findings. This verdict covers only the two byte identities below. I did not execute anything, and I did not recompute these hashes myself. They come from the supplied facts and the file headers.

## Reviewed identities

| Artifact | SHA256 |
|---|---|
| Runner `patches\ACC-009-N3_doors-helper-private-release_20261021.php` (43998 bytes) | `f97f09a82048be1c4efc2fcff875537fa9023c017307153a129f93761b3d68bc` |
| Owner packet `diagnostics\ACC-009-N3_owner-PS_20261021.ps1` | `702dc0d0fb0bca43df30c5ed39c2b0891ab50306bb0d0db55295eaae061bdd93` |
| Source transform (unchanged) | `43dc466f…0da1` → `2138e156…7b93` |

## What was checked and holds

**Source and metadata admission**
- The embedded spec is the accepted old N3 base64. `build.py` reuses it and asserts the old patch hash.
- The decoded edit equals `N3.diff`.
- `acc009s_preflight` correctly replaces the stale target pin (`8e07…`) with the current hash.
- The 49 pins, OCMOD scan, admin/system events, startups and namespace inventory are rechecked by the unchanged helper span.
- The runner refuses any current hash other than before or after. It also refuses an after-image without the marker.

**Entry and staging**
- The runner requires CLI on PHP 8.0.
- It requires the exact realpath `/home2/boosters/public_html` as the working directory.
- It checks the root, the stage and `__FILE__` for links and realpath equality, and requires the runner to sit directly in the private stage.
- Mode admission is strict, and extra arguments are refused.
- The packet independently checks the real home, the directories, that the stage is outside the webroot, the PHP path and version, and the staged size, hash and realpath before every run.

**Temporary web probe**
- Creation is exclusive (`fopen 'x'`), with a random 128-bit name and a separate nonce.
- The probe is sealed by GET method, nonce (`hash_equals`) and a 60-second TTL.
- It loads no OpenCart, config or NP code.
- The fetch is bounded: HTTPS with peer and name verification, no redirects, the fixed UA, and an 8193-byte cap.
- Cleanup runs in `finally` and unlinks only owned bytes. A cleanup failure or changed bytes throws and overrides the return value, so `web_runtime`/`probe_cleanup` are never logged and no source write follows.
- A file left behind becomes inert after the TTL. The CGI tests exercise the actual generated endpoint.

**Apply, restore and rollback**
- The order is: verified private backup plus manifest, then a same-directory temp file with hash and mode, then a check that the target still has the expected hash, then atomic rename, lint, and an after-hash check.
- On failure the runner restores only from before or after. Unknown bytes are left in place and the backup is kept.
- Rollback admission checks the backup name, manifest, backup bytes and current target before any write, and refuses an after-image that is not N3 (C2 must be reversed first).
- Rollback is not released in the packet. That is correct.

**Self-delete**
- Self-delete happens only in `--apply` (including the already-applied case) and in real `--rollback`, never in `--rollback-check`.
- A failure is classified by the target hash in the wrapper (exit 4).

**PowerShell packet and receipt parser**
- Each step is a separate invocation.
- Prerequisites are bound to the runner and packet hashes and expire after 4 hours (Verify) and 30 minutes (Preflight).
- The Apply marker is written before the remote call and blocks every later step.
- stdout, stderr and exit codes are captured in full, and stderr is checked against an exact allow-list.
- I hand-traced `Test-RunnerReceipt` against the real entry output (`entry-apply-receipt.txt`) and against the dry-run and already-applied code paths, including Preflight in the already-applied state. Order and line counts match exactly.
- Field offsets (`web_runtime=`, `backup_path=` are 12 characters) are correct.
- The after/before/unknown split is sound.
- `scp` correctly drops `-T`, and the local path with spaces is quoted.

**Fake boundaries are adequate**
- The lifecycle adapter replaces only path spelling, the unchanged accepted preflight, and the HTTPS producer.
- The real fetch and DB metadata are first exercised by the owner's Preflight. That step is a dry run with no source write, and it fails closed.

## Mandatory findings

None.

## Optional findings

None of these blocks release. Each is recorded without a new review round.

1. **Restore cannot tell its own write from a concurrent one.** Scope: runner `acc009r_apply` / `acc009r_rollback` catch blocks.
   - If a second identical runner raced, `actual===after` (or `before` during rollback) would trigger a wrong "restore".
   - This requires concurrent owner runs, and the outcome is still a valid before or after state.
   - Closure: a "rename done" flag plus an advisory `flock`. This is already listed as deferred hardening.

2. **Packet accepts `already_applied` after a "planned" Preflight.** Scope: packet Apply classification.
   - If Preflight showed `planned` and Apply shows `already_applied`, an unknown actor changed the target in between. The packet still reports OK, and no N3 backup is created.
   - Closure: compare against the Preflight receipt and treat this transition as a result exception. Alternatively, Codex flags it during mechanical closure.

3. **Pre-runner failures are classified as unknown.** Scope: packet Apply.
   - SSH auth or connect failures, and `hash_ok` failing before the runner starts, are classified `STATE_UNKNOWN` (exit 5).
   - This is conservative and safe. An explicit "not started" wrapper line could refine it.

4. **Probe boolean parsing is narrow.** Scope: probe body.
   - `ini_get(...)==="1"` would report "false" for `On`-style values set through `php_value`.
   - Closure: Codex's mechanical receipt check should compare `web_runtime` with the retained host facts (`true` / `true` / `2`). Any difference should be treated as a result exception, not silently admitted.

5. **Local timeout can interrupt the remote run.** Scope: packet, local 180-second kill.
   - A SIGPIPE on the remote side could interrupt the runner mid-apply.
   - The atomic rename keeps the target at before or after, and `STATE_UNKNOWN` is the correct classification.
   - No change is needed.

6. **Parser coverage is partly by reading only.** The PowerShell parser was not run against the real-entry dry-run receipt or the dry-run already-applied receipt. I verified both by reading. Adding fixtures would be an optional hardening.

7. **Minor host-variant edges, both fail closed.**
   - If the `tempnam` fallback is used, it could leave a file in `/tmp`.
   - The probe's `0604` mode could be refused on unusual permission setups.

## Limits

- This is a static, no-tools review. No hashes were recomputed and nothing was executed.
- Current host state is not proven. The fresh pins in each step decide, and a mismatch means STOP.
- The real HTTPS self-fetch, mysqli metadata and production self-delete are unexercised until the owner runs Preflight and Apply.
- The root probe is not proof of admin bytecode.
- This is not remote NP attribution, end-to-end shipment or owner functional acceptance.
- File rollback cannot undo remote recipient/sender address or document effects.
- C2 stays HELD.

## Owner next action

1. Unlock the Booster key per the access doc.
2. Run the reviewed packet steps Upload, Verify, Preflight and Apply as separate invocations.
3. STOP at any non-zero exit. Do not retry Apply or run a rollback.
4. Send the receipts for mechanical closure, including the optional finding 4 comparison.
5. After a successful Apply, wait longer than the reported `revalidate_freq`, then perform N3 functional QA.
6. Lock the key.
