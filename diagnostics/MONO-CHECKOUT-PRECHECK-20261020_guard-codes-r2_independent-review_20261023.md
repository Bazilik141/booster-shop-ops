READY FOR OWNER-RUN

patch_sha256: `04b509078af8e772ea055172f76cf0ca8f364ac366789cbfdcaab3ef7ce957e7` (16825 bytes)
packet_sha256: `242b27e09e79cfec8e3fc088388e70d0d075567b94ffdeccd5f675a2d30a6d3e`

This verdict binds only these two byte-sets. Any byte change voids it.

I recomputed no hashes and executed nothing. The two identities are bound by agreement across five sources:
- the frozen-dependency list
- `guard-r2-identity-20261023.json`
- `guard-r2-static-checks-20261023.json` and `guard-r2-packet-static-lint.json`
- the runner `MF_SPECS`
- the packet's `$PatchSha`/`$PatchSize` and `PSHA`/`PSIZE`

The pins `T1B`/`T1A`, `P1A`, `P2A`, `D1S`, `D3S` and `D4S` agree with the October23 refresh manifest and the N2 release subset. `D2S` agrees with the accepted October20 source.

---

## Mandatory findings

None.

## Basis

### Source recipe
I decoded all four base64 literals by hand.

- **Anchor 1** is exactly the original combined `!status || !$orderId` line.
- **Replacement 1** is:
  - the marker comment,
  - an MC01 line (status check first),
  - an MC02 line (`!$orderId`).
  
  The order and the `pay003Reply` call are the same as before, so the same requests are refused.
- **Anchor 2** is exactly the original `preg_match` guard line, including the literal `\\.`.
- **Replacement 2** keeps the condition byte-identical. It only selects the suffix via `!empty($order['order_status_id'])`.

**Marker:** `MF_MARKER` is absent from the before-source and appears once in the after-text, on the comment line. This is consistent with `mf_classify`.

**Anchor uniqueness:** both anchors are unique full lines.

**Unchanged:** everything else matches the supplied diff byte-for-byte, including:
- `getOrder`
- `require_once` and `accepts()`/`refusal()`/`reply()`
- the remaining guards
- the create, 409 and failure paths
- `pay003Reply`
- the callback

**Nothing added:** no query, log, session write, remote call or retry. All suffixes are fixed literals.

**PHP 8.0:** the change uses only concatenation and `empty()`.

### Reachability (stated limits confirmed)
- `accepts()` requires `method($order['payment_method'])` to be one of three exact codes, via an anchored `/D` regex. That set is a subset of the guard regex.
- Therefore, under the pinned `a21e…` library, **MC04 and MC05 are structurally unreachable**. The patch correctly leaves the gate untouched.
- So with this exact composition, the plain `error_unavailable` text can only come from the MC01/MC02 sites.
- A `grant` redirect from `pay003Reply` can hide MC01. MC02 cannot be hidden, because `context(0)` returns `[]`.

### Runner
I read the full body from `mf_out` to EOF.

**Classification and apply:**
- Classification is exact before (marker count 0) or exact after (marker count 1). Anything else stops.
- Anchor and replacement counts must each be 1. The candidate must equal the after-pin and pass `php -l` during `--preflight`.
- Backup is unique, mode 0700, and includes metadata, the original file, the manifest and an exact recovery runner.
- Drift is rechecked twice. Install is an atomic rename with metadata and lint verification.
- Any apply failure triggers restore. The runner self-deletes only on success or on the idempotent no-op.
- `changed_files=1` equals `count(MF_SPECS)`.

**Rollback:**
- Every target and every needed backup is admitted before any restore.
- Rollback restores only the Mono file, to `6c53…` (the ACC009 after-image).

**Rollback name binding:**
- The backup-dir format `MF_ID-Ymd-His-hex8` matches both the PowerShell and the Bash `BDRE`.
- The manifest's `runner_sha256` equals `PSHA`, which is the hash of the recovery copy.

### Packet
**Fixed keys per action:**
- Every emitted fixed key in each action is in `$fixedKeys` and is emitted once.
- The new keys `dependency_readiness_php` and `dependency_credit_php` are emitted by `dependency_checks` in Verify and Apply.
- Both new keys are asserted in Verify and in Apply.

**Receipt lines:**
- All runner receipt keys match `ReceiptRe`.
- The longest `target=` line is about 445 characters, under the 700 limit.
- `error=` text is sanitized to the allowed character set.

**Name separation:**
- `B` and `RBN` differ from the old staged names, so the obsolete runner stays inert and untouched.

**Rollback scope:**
- Rollback deliberately skips the dependency checks, as stated.

**Unchanged transport and helpers:**
- The seven helper bodies are claimed byte-identical.
- I read `Read-RemoteOutput` and `Invoke-Packet` in full.

### UI/CSS
The changed code is PHP string suffixes only. It adds no styling, timers, absolute/fixed positioning, `!important` or pixel rules. The caller's existing `setTimeout(…,0)` is unchanged.

## Optional findings (non-blocking, no new review requested)

- **O1. Stale wording.**
  - The packet header still says the error gains suffix `[MC01]..[MC05]`.
  - The stop code `parent_credit_confirm_twig_not_first_patch_after` keeps its old name.
  - Both are comment or label only.
- **O2. The N2 JavaScript is not live-admitted.** The N2 after-image `catalog/view/javascript/acc009-checkout.js` (`f944…`) is frozen but not checked on the host. The composition does not depend on it. It does affect how a redirect/no-code result should be read, since the seal header that `accepts()` needs is presumably attached by that script.
- **O3. Reading the customer's result.** Since MC04/MC05 cannot appear here, the outcomes mean:
  - **MC01 or MC02:** the error came from that guard.
  - **Redirect to checkout with the ACC009 notice:** the ACC009 refusal path, not the generic error.
  - **Any other fixed sentence or no code:** a different source or stale FPM/opcache. Inconclusive; report the fixed wording only.
  
  Other fixed sentences include the config-incomplete, minimum, phone, method-mismatch and review texts, a `monobank:…` text, and the JS fallback.
- **O4. Defense in depth only.**
  - Apply does not re-hash D1–D4 after the write.
  - PowerShell Apply does not re-assert the `checkout_twig` and `order_php` keys.
  - Both are already enforced by remote STOP before `remote_result=ok`.
- **O5. Inherited items:**
  - No opcache/FPM reset in either direction.
  - The webroot `_patch_backups` convention.
  - The codes are visible to any visitor who hits these guards.
  - The temporary codes must be removed through the reviewed follow-up.

## Limitations

**Static only.** No hashes recomputed and no runner, packet, SSH or host execution. Accepted evidence is limited to:
- local PHP 8.3.30 `-l`,
- the PowerShell 5.1 AST parse,
- `bash -n` on the five bodies.

No new behavioral tests were run.

**Inputs I could not check independently:**
- The framework body's identity with the previously accepted runner; that runner's text was not supplied.
- The seven helper bodies' identity with the accepted versions.
- The language-file text for `error_unavailable`.
- The contents of `acc009-checkout.js`.

**Unknown until owner Verify/Apply or later:**
- native CLI 8.0, FPM version and opcache,
- the actual rendering of the customer's page,
- the customer's state.

This is a classifier only. It is not a fix and not bank proof. The original incident predates ACC009 and N2, and nothing here attributes it to them.

## Owner action

Run the packet in the normal 64-bit `powershell.exe` 5.1 from the pinned repo root, in this order:

1. Confirm `Get-FileHash` shows `242b27e0…3d6a3e`.
2. Run `-Action Upload`.
3. Run `-Action Verify`. Proceed only on `verify_ok` with `target_pin=before`.
4. Run `-Action Apply` and record the exact `backup_dir=` line.

On any STOP, report the full output and do not rerun.