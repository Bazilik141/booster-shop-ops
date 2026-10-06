# GSS Foundation — independent COMPLETE PREAPPLICATION source45 review

Date:2026-10-06
Reviewer:Claude Code2.1.283; resolved model(s):claude-opus-5-5; effort=high.
Transport:subscription background CLI; tools/MCP/hooks/UI disabled.
Input:exact prepared 2026-10-06 bundle; SHA256DBC0D20E25FFA0670D7C483A5F415984375FBF5FCA6DB698E759539167056D0A;184887UTF-8bytes;1583 pinned input records;10 complete and16 projected source/evidence blocks.
Capture:stdout verbatim below; full coverage/hashes in GSS_foundation-command-complete-source45_claude-review-run_20261006.json.

---

## Verdict: Review OK

**Scope.** This verdict covers only the source in the COMPLETE PREAPPLICATION source45 packet. I accessed no files, database, secrets or UI, and executed nothing.

- It does not authorize attempt 3, apply, product work, Phase2, Auth, factors, effects, restore, owner UI, production or Foundation acceptance. The only grant is the narrow Phase1 scope at the end.
- D069–D074 and Q1 revision 2 stay closed. The journal stays at 2 of 3.
- **Evidence tags:** **[S]** static, from rendered source. **[H]** accepted historical premise. **[U]** unknown or not rendered.
- The source-only matrices (91/24/24/11/19, AST 64/270/0) run in RAM against fakes. They are not approval and not native proof.

---

### The functional delta [S]

- **What changed.**
  - `envelopePairs('maintenance')` now yields `transaction_timeout='180000'` and `idle_in_transaction_session_timeout='60000'`.
  - The fixture `pairs` keep 10000/10000. `statement_timeout=8000` and `lock_timeout=5000` are unchanged.
  - `commandMaintenanceDDLFenceSQL`, `assertCommandDDLFences(…,'maintenance')` and the ambient expectation all derive from the same function, so SET, readback and ambient cannot diverge.
- **Proof that it is the only change.**
  - `prepare` checks that the anchor appears exactly once (`split(anchor).length===2`).
  - It then asserts byte equality of `command-ddl-fences.mjs` with translated frozen44 plus that single replacement.
  - Every other lane file is asserted equal to translated frozen44. SQL and `source-archive-manifest.json` are byte-identical.
- **PostgreSQL semantics.**
  - Idle (60 s) is shorter than transaction (180 s), so both limits stay effective.
  - Every statement remains capped at 8 s.
  - An idle stall, such as a hung timeout-free Git call or fsync, is still cut at 60 s.
- **Checker.** The 19-case witness covers:
  - 180/60 acceptance;
  - refusal of 10000 and 0 for both keys;
  - refusal of an un-overridden `client` source;
  - refusal of the old 60000 transaction value;
  - refusal of a raised idle value;
  - fixture cross-contamination in both directions;
  - ambient refusal of the old 60000;
  - fence-before-readback-before-return ordering in `connectBoot`.

### Actual44 findings: disposition

**FND-I165: closed as a budget-design question [S]. The native fit stays [U].**

- **Arithmetic checked:**
  - 2×2784.996 + 209.642 + 3.645 = 5783.279
  - plus 16937 = 22720.279
  - ×3 = 68160.837, which is more than 60000
  - 60000 − 22720.279 = 37279.721
- **The component model matches the rendered `writeFixed`.** It covers one `assertCommandPathsIgnored`, two `operation('inspect')` calls each including the gate, and one `wx` write plus fsync.
- **Using maxima is conservative.**
  - The 2785 ms privacy sample is a cold-cache outlier; the steady state is about 1945 ms.
  - The 16.9 s prefix includes node startup, the pre-BEGIN gate and reconcile.
- **Cross-checks agree:**
  - `runnerSha256` equals the public alias hash.
  - The RAM sourceHashes equal the parent44 hashes in the equivalence table.
  - `old36FullGateCalls`=16 is 5 timing + 1 positive + 9 negative + 1 restore.
- **Assessment.** 180 s is three times an explicit 60 s planning allocation. It is a justified, bounded engineering allowance. It is not a proven worst-case fit, and the packet correctly does not claim one.
- **Why bounded beats disabled.** It is preferable to disabling the timeout (0 is refused). On a local fixture database, holding locks for up to 3 minutes has no external effect.
- **What stays unknown.** The unfinished whole-candidate inspection, the history insert, the final inspection, `noLeases`, COMMIT, and private-path write behaviour all remain [U].
- **Carried forward.** The attempt3 review must re-cite this budget evidence.

**FND-I166: closed [S].**

- The actual44 pre-dispatch checker and its JSON are rendered in full.
- Its 15 cases include `missing_published_archive` and `mutable_published_archive`, run against the real `assertCompleteCommandInputs`.
- The plan wording now matches the evidence.

### New findings

**FND-I167 (low; [U]; attempt 3 only; does not block Phase1): nothing rendered shows that no client-side or harness watchdog ends the maintenance process before the server's 180 s bound.**

- **What is covered.**
  - Per-statement client timers are harmless, because statements are capped at 8 s.
  - `execFile` has a 5 s timeout per PowerShell call, so those calls refuse rather than hang.
- **What is not rendered.** `config()` in `maintenance.mjs`, and the executor's own command-execution timeout for the attempt3 invocation, are not shown.
- **Why it matters.**
  - A whole-run or whole-command kill shorter than about 180 s plus the pre- and post-transaction work would end the transaction outside the reviewed fence.
  - That could happen mid-file-write or mid-COMMIT, and it would spend the last attempt.
- **How to close it in the attempt3 review packet:**
  1. Render the maintenance connection options. Show there is no whole-transaction or whole-run JS watchdog shorter than 180 s.
  2. State the executor command timeout used for the single invocation. It must be at least 300 s; 600 s is recommended.
  3. Optionally, have the outer capture (not `maintenance.mjs`) record wall-clock elapsed time, so the historical "collector omitted elapsedMs" gap does not recur.
- **No source change is requested.**

**Cosmetic, no action required:**
- The case label `ambient_exact_session60` asserts the 180/60 envelope.
- The checker header still says "I163/I164".
- `command-ddl-fences.mjs` still carries the "UNREVIEWED" comment.

### Conditions carried from this packet

- **The source44 Phase1 grant is superseded.** It must not be invoked, now or later. Only the source45 grant below is live, so at most one fresh Phase1 is run.
- **The 45 pre-dispatch witness is not rendered here [U].** It must exist before the Phase1 invocation, with the same 15 cases passed against the actual complete45 manifest and bundle hash.

---

## Phase1 scope: ORIGINAL0006 FRESH PHASE1 source45

**Granted, exactly as follows:**
- **Invocation.** One reserved, fresh run of `work/run-gss-command-phase1-original45_20261006.mjs` at `A3F4D290…4DF74`. That file is byte-equal to translated frozen44.
- **Precondition.** It is admitted only through the actual seal of this verdict, bound to the complete source45 manifest, and only after the 15-case gate45 pre-dispatch witness has passed.
- **What it runs.** The canonical36 suite: 17 test files, 144/144 tests and all 122 criteria attributed.
- **Brackets, before and after:**
  - whole-original `original_0006` with `fixtureMode` S0, including the rollback/connection closure record;
  - an unchanged source snapshot that includes the published DAEE archive (`DAEEA79A…AB8D`);
  - journal lineage at 2.
- **Child process.** It runs under `safeChildEnv`. The secret screen runs before the `wx` transcript and artifact writes.
- **DML.** Canonical36 uses fictional rollback DML. It is not SQL-read-only, and that is accepted under the standing D064/D065 local authority.

**Not granted:**
- apply or attempt 3;
- new keys, new roles, seeds or journal appends;
- retry, rerun, reset or learning from the failed catalog. A refusal or failure is recorded, not retried.
- invocation of the superseded source44 runner.

**What still applies afterwards:**
- Attempt 3 still needs a distinct actual independent SINGLE APPLICATION ATTEMPT3 review after a successful fresh Phase1. That review must bind:
  - the current COMPLETE45 manifest;
  - the fresh Phase1 proof;
  - the attempt UUID;
  - the budget evidence.
- That review must also close FND-I167.
- No owner-only gate is inferred.
- Max 3, consumed 2.
- `productApproval=false`, `attempt3Authorized=false`, `nativeWitnessAuthorized=false`.
- Phase2, Auth, factors, effects, restore, owner UI, production and Foundation all remain unproved.
