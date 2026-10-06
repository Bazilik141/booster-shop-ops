# GSS Foundation — independent COMPLETE PREAPPLICATION source47 review

Date:2026-10-06
Reviewer:Claude Code2.1.283; resolved model(s):claude-opus-5-5; effort=high.
Transport:subscription background CLI; tools/MCP/hooks/UI disabled.
Input:exact prepared 2026-10-06 bundle; SHA256C87652F157A7E35F408165E96848EF9EF4F4AFC471D32A99A9CED481CAF9D87E;173558UTF-8bytes;1819 pinned input records;8 complete and12 projected source/evidence blocks.
Capture:stdout verbatim below; full coverage/hashes in GSS_foundation-command-complete-source47_claude-review-run_20261006.json.

---

## Verdict: Review OK

**Scope.** This verdict covers only the source rendered in the COMPLETE PREAPPLICATION source47 packet. I accessed no files, database, secrets or UI, and I executed nothing.

- The only grant is the narrow Phase1 scope at the end. It does not authorize attempt 3, apply, product work, Phase2, Auth, factors, effects, restore, owner UI, production or Foundation acceptance.
- D069–D074 and Q1 revision 2 stay closed. The journal stays at 2 of 3.
- **Evidence tags:** **[S]** static, from rendered source. **[H]** accepted historical premise. **[U]** unknown or not rendered.
- The 91/24/24/11/19/26 matrices and AST 66/278/0 are source-only proofs against fakes. They are not approval and not native proof.

---

### Actual45 failure: assessment

**The record is internally consistent [S].**
- The transcript shows 144 tests, 143 passed, 1 failed, 0 cancelled, skipped or todo, and 40216.2954 ms.
- The single failure is `sql-file-guard.test.mjs:7`, a `strictEqual` where actual is `daee…ab8d` and expected is `7c52…d8e3`.
- The root capture's `failingTestSha256` is `23E8…A7B8`. That equals the rendered frozen36 file header and the pin in `phase1-safety47`.
- The reservation is recorded as consumed, with `automaticRetry=false`.

**Cause [S].** Frozen canonical36 kept the prepublication archive pin. COMPLETE44 and COMPLETE45 correctly require the published DAEE archive.
- So the suite I granted in the source45 review contradicted its own DAEE bracket.
- **The source45 review did not catch this.** It should have checked the canonical36 literal pins against the mandatory archive input.

**Disposition [S].** The handling is correct:
- the failure is preserved;
- the 45 authority is treated as consumed;
- there is no rerun, no attempt 3 and no journal append.

**One residual unknown [U], with no action needed.** The refusal is tagged `phase:"after"` and `untagged_no_sqlstate`.
- In the runner, `assert.equal(child.status,0)` runs after the after-bracket, the snapshot comparison and the journal check.
- The record does not show which of those assertions fired. So actual45 does not prove that the after-bracket and source snapshot passed.
- This does not matter, because 47 requires both fresh brackets anyway.

### Corrected selection [S]

**The test correction is a single-literal change.**
- I compared the rendered corrected copy (`B51F…5D26`) with frozen36 (`23E8…A7B8`). They differ only in the line-7 hash literal.
- The test name, the legacy `assertLegacyMigrationNames(names)` refusal and all lexical cases are unchanged.
- The test still pins exactly one value. This aligns it with an independently reviewed and mandatory pin rather than relaxing it. DAEE appears in the 45 bracket and in the `archive-timeouts47` cases `attempt3_archive_DAEE`, `_missing` and `_stale`.

**The runner checks compatibility before anything is consumed.** All of these run before `reserveOriginalPhase1Invocation`, `verifiedRunningStack` and `secretPath`:
- the exact 17-name `deepEqual`;
- an anchor check that the old hash appears once (`split(...).length===2`);
- byte equality between the copy and `oldTest.replace(7C52→DAEE)`;
- the published-archive hash check against `DAEE`.

A refusal at this stage consumes no reservation and reads no secret.

**Execution details [S].**
- `args.length===19`, which is 2 flags plus 17 files.
- The relative imports from the lane47 copy have the same depth as in lane36, so they resolve to the same `gss/tools` modules.
- The test name is unchanged, so attribution of all 122 criteria by name from map36 still holds.
- The success receipt records `executedTestFiles`.

**Binding of the 16 unchanged canonical36 files [H].** The runner binds them by name only. Their content is bound through `assertCompleteCommandReview` and the manifest `snapshot()`.
- This is the same mechanism the 45 grant relied on.
- The 45 transcript shows all 16 passing.

**The checker's negative variants all bite [S].** I traced each one through the extracted block:
- `extra_name` and `missing_name` fail the `deepEqual`;
- `old_anchor_absent` and `old_anchor_duplicate` fail the `split` count;
- `stale_copy`, `weakened_copy` and `missing_copy` fail the equality check or throw;
- `stale_archive` fails the hash check.

### Secret boundary [S]

**Top-level structure.** The rendered runner contains only four things at top level:
1. imports;
2. `persistPhase1Failure`;
3. `async function runPhase1`;
4. a single `runPhase1().catch(()=>{…})`.

**Audit finding 1 is closed.**
- Every statement runs inside the async function, including `JSON.parse` of the secret, the key-shape `deepEqual` and both `assert.match` calls. Synchronous throws inside it become rejections.
- The outer handler takes no parameter, so the raw error cannot be reached.
- It emits a fixed record with `passed:false`, `automaticRetry:false`, `applicationAuthorized:false` and `sqlState:null`.

**Outside the boundary [H].** Only module evaluation of the imports remains outside.
- It is byte-identical to accepted45 under the lane translation.
- `runtimePasswords()` is called only inside the inner try.

**Inner failure path.**
- The inner native catch is unchanged apart from its writer.
- If inner reporting itself throws, the outer catch emits a second fixed record. `wx` prevents it from overwriting a first record that was already persisted.

**Audit finding 2 is closed.** The writer:
- loops on short writes;
- refuses any write count that is not a safe integer, is less than 1, or exceeds the remaining bytes;
- calls `fsyncSync` and `closeSync`;
- catches every fault and returns only a boolean.

It never changes `exitCode` semantics and never grants retry or application. It makes no claim to fsync the directory entry, which matches the plan.

**The checker evaluates the real code [S].**
- It evaluates the actual AST text of the `.catch` expression and the writer.
- It injects sentinels through `JSON.parse`, `assert.match` with the 64-character and 96-character patterns, and plain `Error`, each with the writer both succeeding and refusing.
- For every combination it confirms the sentinel appears neither in the record nor in the logs.
- The fake-IO matrix covers successful short writes with full-byte reassembly, plus open, zero-write, write, fsync and close faults, each with the expected close behaviour.
- The count is 1 + 8 + 6 + 1 + 8 + 2 = 26, which matches the JSON.

### Bindings [S]

- The runner hash `9AD2…AF94` matches across `corrections47`, `phase1-safety47` and the coverage list.
- The equivalence table has 93 entries. Adding the corrected copy gives 94 lane files.
- The SQL and `source-archive-manifest.json` are byte-identical to their parents.
- The `maintenance.mjs` hash `E130…7ECA` is consistent across the pure-draft, fault and archive-timeout proofs. Its behaviour is therefore unchanged from accepted45:
  - 180 s transaction and 60 s idle for maintenance;
  - 10 s for the fixture;
  - 8 s statement and 5 s lock.

### New findings

**FND-I168 (low; evidence capture only; no source change requested).** The invocation is now deferred past today, which exposes three gaps:
- The outer record always says `phase:'setup_or_reporting'`. It cannot distinguish a refusal before reservation from one after it.
- The runner does not check at start that the `original47_failure` file is absent. A failure after an earlier (forbidden) invocation would therefore reach stderr only.
- File names, and the `date` fields in both the success artifact and the failure records, are hard-coded to `20261006` / `2026-10-06`, but the run will happen on a later day.

**How to close it, using the executor's capture without editing the runner:**
1. **Immediately before the single invocation**, verify that all four of these are absent: the 47 artifact, the transcript, the failure file and the 47 reservation file.
2. **After the run**, record presence and hash for the reservation and failure files, the exit code, and the actual wall-clock start time, end time and date.

**FND-I169 (informational).**
- After `closeSync` throws, the `finally` block retries close on the same descriptor. On POSIX, retrying `close` can hit a reused descriptor.
- Here the path is synchronous, on Windows, and has no concurrent opener, so the risk is negligible. No change is requested.

**Cosmetic, no action required:**
- The runner header still says "Cannot execute before actual COMPLETE42 acceptance".
- The `EXACT FROZEN45 MODULE EQUIVALENCE` label actually lists unissued46 parents.

### Conditions carried

- **Prior grants.** The 45 grant is consumed and the 46 packet was never granted. Only the 47 grant below is live.
- **47 pre-dispatch witness [U].** The 47 counterpart of the 15-case pre-dispatch witness is not rendered here. It must exist and pass against the actual COMPLETE47 manifest and bundle hash before invocation.
- **FND-I167.** It carries forward unchanged and is for attempt 3 only.
- **Day end.** The owner's stop applies. After this verdict, and its seal if accepted, no native47 invocation happens today.

---

## Phase1 scope: ORIGINAL0006 FRESH PHASE1 source47

**Granted, exactly as follows:**
- **Invocation.** One new reserved, fresh run of `work/run-gss-command-phase1-original47_20261006.mjs` at `9AD2F2DB…01AF94`.
- **Preconditions.** It is admitted only when all three hold:
  - the actual seal of this verdict exists, bound to the COMPLETE47 manifest;
  - the 47 pre-dispatch witness has passed;
  - the FND-I168 absence checks have passed.
- **What it runs.** 17 files: 16 unchanged canonical36 files plus the corrected copy `B51F1203…5D26`. That is 144/144 tests, with all 122 original criteria attributed.
- **Brackets, before and after:**
  - whole-original `original_0006` with `fixtureMode` S0, including the rollback and connection closure records;
  - an unchanged source snapshot that includes the published DAEE archive (`DAEEA79A…AB8D`);
  - journal lineage at 2.
- **Child process.** It runs under `safeChildEnv`, and the secret screen runs before the `wx` writes.
- **DML.** The suite uses fictional rollback DML. It is not SQL-read-only, and that is accepted under D064/D065 local authority.
- **Not a reuse.** This is a new authority. It is not a reuse of the consumed 45 authority.

**Today the grant stays UNUSED.** It may be used only later, starting from this verdict and seal and from fresh preconditions.

**Not granted:**
- apply or attempt 3;
- new keys, new roles, seeds or journal appends;
- retry, rerun, reset or learning from the failed catalog;
- model fallback;
- invocation of any runner from 44, 45 or 46.

**What still applies afterwards:**
- Attempt 3 needs a distinct actual SINGLE APPLICATION ATTEMPT3 review after a successful fresh 47 Phase1. That review must bind:
  - COMPLETE47;
  - the fresh proof;
  - the attempt UUID;
  - the budget evidence (I165);
  - closure of I167, including the config, an outer watchdog of at least 300 s (600 s planned) and wall-clock capture.
- No owner-only gate is inferred.
- Max 3, consumed 2.
- `productApproval=false`, `attempt3Authorized=false`, `nativeWitnessAuthorized=false`.
- Phase2, Auth, factors, effects, restore, owner UI, production and Foundation all remain unproved.
