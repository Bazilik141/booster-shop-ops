# RD-17-WP1A successor r3: integrated CODE/composition review

## Verdict: **Review OK** for owner Upload and Verify of the r3 pair only

There are no mandatory findings.

This verdict covers only the two artifacts below. It does not authorise Apply, which still needs the Verify receipt to be returned first. It does not authorise any retry or use of the old r2 pair, any server action by an agent, or owner QA. This is a fresh stage. It is not a replay of the consumed request `c5ce87dc…`, which ended in a provider refusal and produced no verdict.

| Artifact | SHA-256 | Bytes |
|---|---|---|
| `patches/RD-17-WP1A_profile-receiver-r3_20261026.php` | `ef0d7a7d87c58340b73cfdbb0fc26a497920adb7c38eeceaf8913dbe803f8e85` | 401823 |
| `diagnostics/RD-17-WP1A_owner-r3_20261026.ps1` | `0a2b8c0935a56197398268ba549857bf07badeae722f7b8c5c5ac1bfd6912712` | 11883 |

**How the hashes were bound.**
- These are the declared identities. I did not recompute any SHA-256; mechanical closure must confirm the byte identities.
- In the runner's encoded dependency table I decoded the Mono row by hand. `ZXh0ZW5zaW9uL21vbm9fY2hhc3Qv…IjoiYmVkNTNmYTQxODVi…` decodes to `extension/mono_chast/…":"bed53fa4185b…`.
- The packet's `$Sha` equals the runner hash above.
- The packet's `state.json` binds both the runner and packet hashes. A stale r2 state therefore cannot be reused.

---

## Mandatory findings

None.

---

## Assessment by requested area

### 1. Lineage justifies changing exactly one pin

**The capture.**
- The drift capture was reviewed before it ran and was executed once.
- It shows 106 of 107 dependencies matching and all 7 targets matching their before-state.
- The `--check` stop at 13:23Z also proves that runtime admission had already passed. The stop came from the dependency loop, which runs after runtime admission.

**The changed file.**
- The only drifted file is `extension/mono_chast/catalog/controller/payment/mono_chast.php`.
- Its observed hash is `bed53fa4…`. It has the same byte size (18817) as the retained after-image of the Mono diagnostic release (`guard-codes-r2`).

**Why bed53fa4 is a known, accepted version.**
- That diagnostic release had its own actual CODE review: `b73b49ce…`, recorded as READY FOR OWNER-RUN.
- The owner's accepted Apply receipt for it has 42 lines and shows the transition `6c53f412… → bed53fa4…`.
- `reconcile-mono-drift.py` re-validates all 114 rows strictly, rejecting duplicate keys.
- Applying the two recorded base64 replacements to the retained before-image reproduces the after-image exactly. Reversing them recovers the before-image exactly.

**Why this is not a blind pin swap.** The new expected value is tied by hash to a source whose content is retained and was reviewed. Its only changes are the support-code suffixes MC01, MC02, MC04 and MC05, as `guard-r2-source.diff` shows.

### 2. The successor is exactly the r2 engine plus one row

**Runner.**
- `build-profile-r3.py` asserts the following:
  - the source is r2 `27c6bec2…`;
  - the stale Mono row equals `6c53f412…`;
  - after masking the dependency table, the old and new engines are equal;
  - every one of the seven decoded payloads hashes to its `after`.
- At `--check` the runner also enforces `payload_hash_mismatch`, so any divergence between the target table and the payloads would fail closed at Verify.

**Packet.**
- The diff contains exactly four literal changes.
- The build asserts a reverse round-trip back to the reviewed r2 packet.
- I read the r3 packet in full. Everything else is identical to the r2 packet reviewed under `c6136634…`:
  - the six steps;
  - the receipt regexes;
  - the `&&` cache sequencing;
  - one-use Apply and Rollback;
  - strict host keys and the explicit agent pipe.

### 3. Old and new stages cannot become parallel writers

**The shared lock.** Both runners compute the lock path as `$stageRoot/RD-17-WP1A_profile-receiver_20261026.lock` in the same private directory. They create it exclusively (`xb`), so any concurrent execution is refused.

**The old pair cannot write sequentially either.**
- **Old Verify:** it would stop on `dependency_source_drift` because of the Mono drift. After an r3 Apply it would instead print `already_applied=yes`, and the packet rejects that as an unaccepted Verify.
- **Old Apply:** it needs `verified` in the old state, which has never been set.
- **Old Rollback:** it needs a backup recorded in the old state, and there is none.

**Backup grammar is consistent.** `RD16_ID` is unchanged, so the backup prefix `RD-17-WP1A_profile-receiver_20261026-<UTC>-<hex>` is identical in both runners. The r3 packet's Apply and Rollback regexes match that prefix exactly.

**Separation of state and staging.** The new receipts directory and the new staged basename keep the old state, and the old staged runner, untouched.

### 4. No native guard is bypassed

**Admission and ordering are unchanged:**
- runtime admission;
- theme, event and payment inventory checks;
- the registry pre-hash;
- no-op detection before dependency checks;
- the admission digest recomputed at Apply;
- backups and lint before any write;
- apply order 0,4,1,2,3,6,5, rollback order the reverse, and removal of the helper only.

**The admission digest changes, but only through the one dependency row.** The new digest will not match any earlier r2 digest. Verify establishes the new one.

**Mono is never a target.** Rollback restores the six existing files, removes the new helper, and never reads or writes Mono.

### 5. How Confirm and the current Mono controller interact

The 16 native cases exercise the real modified `Confirm.index`, readiness, the profile resolver and mapper, and the exact current Mono controller `bed53fa4`.

**What the cases confirm:**

| Area | Result |
|---|---|
| Normal success (parts 3, 4 and 5; profile, manual or explicit-empty recipient; guest; degraded registry or name on warehouse) | Exactly one fictional bank create and two payment writes |
| Seal safety (profile middle name changed, middle name cleared for courier, customer identity changed, bad browser token) | Refused by `accepts()` with zero bank calls and zero payment writes |
| Existing diagnostic codes | MC01 and MC02 still emitted, with no bank call |
| Idempotency | An existing transaction returns `idempotent:true` with no new create |
| Phone and payload | The profile bridge never alters the order telephone; the Mono payload uses telephone, total and products only, never recipient names |

**MC04 and MC05 remain structurally unreachable.** `accepts()` still runs before payment parsing, as the guard-r3 review established.

**Fake boundaries are adequate:**
- The cURL fake is namespace-scoped. The run uses `-n`, has no global cURL and sets `allow_url_fopen=0`.
- DB, order and credit storage are fakes; the credit model's `grant` is false.
- The per-request profile cache is reset between Confirm and the Mono call, which models two separate HTTP requests correctly.

**Not established by these tests:**
- the real `mysqli` driver, order model and credit model;
- FPM and opcache behaviour;
- a real bank round-trip;
- browser concurrency.

None of these block approval, because they are pinned at Verify or covered by owner QA.

### 6. Material context and reuse

**Reused by identity, not re-reviewed.**
- `checkout.php`, `receiver.php`, the Twig template and `checkout-reskin.js` were supplied by identity only. They are reused from the r2 review by their unchanged identities. Their payloads are byte-identical, and the runner enforces each `after` hash.
- I relied on the r2 `Review OK` (`c6136634…`) for their content. I did not re-read their bodies now.

**Re-run evidence for r3.**
- 8 engine cases on the r3 runner.
- 6 packet steps with replay refusal, on both PowerShell 5.1 and PowerShell 7.
- The new 16-case Mono suite.

These are local results, not host proof.

---

## Optional findings (recorded, not blocking)

**O-1. Composition with the Mono diagnostic's own lifecycle.** This needs to be recorded before WP1A Apply.
- **Removing or rolling back the Mono diagnostic before WP1A Apply.**
  - The Mono diagnostic is temporary, and a reviewed follow-up is planned to remove it.
  - If that removal, or a Mono rollback to `6c53f4…`, happens between r3 Verify and Apply, the r3 Apply will stop on `dependency_changed_before_write`. That is fail-closed, but it would need yet another successor.
  - Sequence the two releases deliberately.
- **Rolling back the Mono diagnostic after WP1A Apply.**
  - The reviewed Mono r3 packet admits three files as exact pins:
    - parent `confirm.php` at `333c43…`;
    - `checkout.twig` at `e17c96…`;
    - the readiness library at `a21e8ccf…`.
  - WP1A changes all three.
  - So after WP1A Apply, the existing Mono rollback and recovery packet will refuse.
  - Any Mono removal or rollback after WP1A must be a successor built against the post-WP1A hashes, or must run only after a WP1A rollback.
- **Closure:** add this sequencing note to the report and handoff. No code change is needed.

**O-2. The dependency origins file is stale.** `profile-dependency-origins.json` still labels the Mono row "accepted ACC009 N2 context".
- **Closure:** relabel it, for example "owner-applied Mono diagnostic guard-r2, 2026-10-23, reconciled by owner-drift-r1". This is bookkeeping only and closes mechanically.

**O-3. The old r2 runner is still staged.**
- `RD-17-WP1A_profile-receiver_20261026.php` remains in private staging.
- It is inert:
  - it fails Verify on the Mono drift;
  - after an r3 Apply it reports a no-op;
  - its packet has no way to reach Apply or Rollback.
- Because both runners share the task ID and manifest format, the old runner's `--rollback` could technically process an r3 backup if someone invoked it by hand.
- **Closure:** none required now. Remove it later through a reviewed owner action and never invoke it manually.

**O-4. A leftover shared lock blocks Verify.** If a prior run left `…_20261026.lock` behind, r3 Verify will fail with `exclusive_create_failed`. That is fail-closed, as before. Assess the receipt; do not delete the lock automatically.

**O-5. A later Verify check could still stop.** Between the 13:53Z capture and Verify, the host could change: the event set, payment inventory, theme settings or the profile registry. Any of these produces a new STOP. Treat it as a new held outcome; never adapt the runner.

**Carried from r2:** O-A through O-D, and O2, O3, O4 and O6, are unchanged and already recorded in the report.

---

## Closure

1. **Mechanical identity check.** Before Upload, confirm that `Get-FileHash` returns `ef0d7a7d…` for the runner and `0a2b8c09…` for the packet. Confirm that `work/rd17-main-20261026/owner-profile-r3-receipts/` does not yet exist.
2. **Owner Upload.** Run `-Step Upload` with the r3 packet. Expect `RD17_WP1A_UPLOAD_OK`.
3. **Owner Verify.** Run `-Step Verify`. A successful Verify prints, in order:
   - `php_path=` and `php_version=8.0.x`;
   - `admission_sha256=<64hex>`;
   - `syntax_gate=ok`;
   - `production_writes=0`;
   - `backup=none`;
   - `done=ok`;
   - `RD17_WP1A_VERIFY_OK`.

   Return the complete receipt before any Apply. Any `error=`, `packet_error=` or STOP is a held outcome: do not rerun and do not change pins.
4. **Bookkeeping.** Record O-1 and O-2 in the report. Record this verdict together with the byte identities from mechanical closure.
5. **Still outstanding.** All of the following remain pending:
   - the r3 Apply;
   - the Apply receipt;
   - owner QA, as listed in the report;
   - the separate Mono-diagnostic sequencing in O-1;
   - WP1B and RD-18.
