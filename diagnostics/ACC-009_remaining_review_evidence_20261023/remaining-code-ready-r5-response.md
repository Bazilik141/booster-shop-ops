# ACC-009 r5: packet-only Apply-buffer repair, final integrated CODE/composition review

**Overall verdict: REVIEW_OK for all three parts.** It covers only the six identities in §1.

Two release conditions apply before owner Apply (§5):
- **R1:** create and mechanically check the r5 release file.
- **R2:** confirm from the accepted runner body how file modes are set. This interaction falls outside the declared r4 reuse scope and I could not see it.

Neither is a defect I established in these bytes.

**What I read:**
- the three complete r5 packets and the exact diff;
- `prepare_r5.py`, `equivalence.json`, `reproduce_r4.ps1` and its result;
- the reconstructed r4 dispatched script;
- `test_packet_entry.ps1`, `validate_r5.py`, `test_shell.py` and both result files;
- the three rendered r5 Apply scripts;
- the r4 response, acceptance and release;
- the actual r4 Apply-STOP receipt, marker and Preflight assessment;
- the dispatch and closure tooling and the policy.

**What I did not do.** I executed nothing and computed no hashes. Hash equality comes from the generator and equivalence assertions and the rendered headers. I did not re-read runner or payload bodies; they are reused by declaration.

## 1. Per-part verdicts and exact identities

| Part | Runner (retained r4, unchanged) | r5 owner packet | Verdict |
|---|---|---|---|
| WP3b | `0854ffe33ac9239349d95967cca264a378c429a86b2a988f0d028dcc53957166` (465933) | `9397ee99f49b1d113db87c068fbad8dc02ae6152b55d51b56e3c390ab9e4a1c6` | **REVIEW_OK** |
| N2 | `8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89` (900825) | `d251819bf6318b8832b31f9098a97b9c4cf451bfa6ee2ab2113ae5b4f21d7ca1` | **REVIEW_OK** (sequenced after WP3b + QA) |
| WP3c | `4f6b5a180caeb5e72f37eb3df14d7bbc36c401372632f9f98dfe369db460878c` (120709) | `256a8b5cd860dc57fb538edfac3f4c62c94a98a2dcf60c1d90161afe781e0620` | **REVIEW_OK** (activation gated) |

These values agree with each other:
- `release-facts.json` (r5);
- `equivalence.json`;
- the r4 release runner hashes;
- each packet's `$Name`, `$Sha`, `$Bytes` and `$TargetCount` (9/18/0);
- the `F`/`SHA`/`BYTES` constants in `$Common`;
- the inline gate task and count (9/18; WP3c disabled by `'1' = 0`);
- the rendered r5 Apply headers.

## 2. Causal claim: independently confirmed

**The mechanism is correct.**
1. The top-level `try` is not a new scope. `$bytes=$Utf8.GetBytes(...)` therefore rebinds the script-scope `$Bytes`, because PowerShell variable names are case-insensitive.
2. `Invoke-Remote` reads `$Bytes` by dynamic scope. `[string]` of a `byte[]` joins the bytes with `$OFS`, a space.
3. The "self-identical" replacement `.Replace('465933', [string]$Bytes)` then injects `123 13 10 …`. That replacement is a no-op only while `$Bytes` stays intact.
4. Bash parses `BYTES=123 13 10 …` as an environment prefix on the command `13`. The result is exit 127, and `set -e` terminates `main`. Exit 127 propagates through `exit $?`.

**The evidence is decisive, not inferential:**
- The reconstructed full script, built with the actual r4 renderer plus the exact saved `Apply-attempt.json` bytes, is reported to hash to `70517cfe…6f84`. That equals `remote_script_sha256` in the actual STOP receipt.
- A byte spot-check is consistent with PS5.1 `ConvertTo-Json` output:
  - the prefix `123 13 10 32 32 32 32 34` decodes to `{`, CRLF, four spaces, `"`;
  - the tail `… 83 84 79 80 34 13 10 125` decodes to `STOP"`, CRLF, `}`.
- Line 6 is the `F=…; SHA=…; BYTES=…` line, which matches the stderr `line 6`.

**Why the earlier steps were unaffected.** Upload, Verify and Preflight never execute the marker statement. `Read-LocalRunner` runs before the marker, so the local identity check used the correct integer.

**The r4 failure is no-write.** Only lines 2–6 ran: shell options, `export`, `umask` and plain assignments. Then `13` failed under errexit. Empty stdout corroborates this, because `pkt_php=` would otherwise have printed.

Nothing else was reached:
- the remote directory and `$HOME` checks;
- `hash_ok`;
- PHP;
- `cd`;
- the runner;
- the backup;
- cache cleanup.

**Classification.** This is a known fail-closed outcome. I agree that no separate actual-result review is needed. No rollback applies.

**Determinism.** Any PS5.1 marker JSON starts with `{` + CRLF, so the unconsumed r4 N2 and r4 WP3c packets would fail identically at line 6 on Apply. That failure is safe but useless; see O-r5-6.

## 3. Repair inspection

**Byte level.**
- `prepare_r5.py` applies three anchored replacements, each asserted to occur exactly once.
- A byte-level reverse substitution must equal the original r4 bytes. Because the reverse is global, any pre-existing `r5` string would make the assertion fail. The proof is therefore conservative.
- The diff shows exactly three hunks per packet, at lines 23, 119 and 266. Everything else is r4-identical.
- This closes O-r4-1 for these bytes.

**Collision inventory: this is the check prior reviews skipped.**
- **Script variables read inside functions:**
  - `Read-LocalRunner`: `$Local`, `$Bytes`, `$Sha`.
  - `Invoke-Remote`: `$Common`, `$Name`, `$Sha`, `$Bytes`, `$Utf8`, `$Ssh`, `$SshOpt`, `$Alias`.
  - `Invoke-OwnerScp`: `$Scp`, `$SshOpt`, `$Local`, `$Alias`, `$Name`, `$Utf8`.
  - `Assert-Release`: `$Root`, `$Part`, `$Sha`, `$PacketSha`.
  - `Get-Prior`: `$Rcpt`, `$Task`, `$Sha`, `$PacketSha`.
  - `Read-Result`: `$Task`, `$Metadata`, `$TargetCount`.
  - `Assert-Transport`: `$Sha`.
- **Top-level assignments after the declarations:** `$record`, `$r`, `$copy`, `$prior`, `$mode`, `$auth`, `$cleanup`, `$item`, `$age`, `$a`, `$marker`, `$markerBytes`, `$body`, `$json`, `$failure`, `$failed`.
- **Result:** the case-folded intersection is now empty. `$Bytes` was the only collision.
- **Function-local names are harmless.** Names such as `$a` and `$age` in `Assert-Release`/`Get-Prior`, and the `$Body`/`$Mode` parameters, are function-local assignments or bound parameters, so top-level names of the same spelling cannot affect them.
- **No automatic-variable clash.** `Invoke-Remote` uses `$in`, not `$input`.

**Renderer and substitution order.**
- `__MODE__` is replaced at both occurrences.
- `__AUTH__` is base64, with no `'`, `_` or `__CACHE__`.
- `__CACHE__` is inserted last, so nothing is re-substituted into it.
- The rendered WP3b and N2 scripts show `source_gate && { … }`. WP3c shows `source_gate ` with no cache block.

**Metadata auth (WP3c).**
- It is unchanged and computed before the marker.
- `$a`, `$item` and `$age` collide with nothing a function reads.
- The auth binds task, backup hash, backup UTC and the Preflight `metadata_sha256`.
- It is embedded inside single quotes.

## 4. Failure ordering, no-replay, receipts and fixture fidelity

**Apply ordering:**
1. Release.
2. Local runner identity.
3. `Rcpt` reparse check.
4. Consumed check.
5. Verify ≤ 2 h.
6. Preflight ≤ 30 min.
7. WP3c only: backup and confirmation.
8. Atomic `CreateNew` marker.
9. Render.
10. Transport.
11. Strict `Read-Result`.

**Failure behaviour:**
- Any failure before step 8 leaves the packet unconsumed; the owner re-preflights.
- Any failure at step 8 or later, including a marker write error or SSH start failure, leaves the packet consumed with a STOP receipt.
- The consumed refusal precedes `$record`, so it writes no receipt and prints terminal output only. That is acceptable.

**Fresh-attempt safety.**
- r5 uses a new receipt directory. `Get-Prior` also binds `packet_sha256` to the r5 hash, which the r4 receipts cannot satisfy.
- Upload, Verify and Preflight are mechanically enforced before Apply.
- The r4 marker, the STOP receipt and the staged runner stay untouched.
- This is a new assessed attempt, not a replay.

**Release path.** The new path is `diagnostics\ACC-009_remaining_repair_r5_evidence_20261023\review-release.json`.
- It does not yet exist, so the packets remain HELD.
- If pointed at the r4 release file, the per-part `packet_sha256` check would refuse.

**Apply-only bash, never yet run on the host (re-read):**
- `source_gate` sits on the left of `&&`, so errexit is suspended inside it. Explicit `return` codes cover the runner exit and the inline gate (`return 4`).
- `count=$((count+1))` is safe under `-e`.
- `find -type f -print0` with `read -d ''` and per-member validation fails closed. Any cache STOP after the source change surfaces through `pkt_fail` and a non-zero exit.
- The `main()` wrapper means truncated stdin never invokes `main`.

**Fixture fidelity (good):**
- The fixture runs the actual AST-extracted top-level `try` and the functions through `Invoke-Expression`, in the fixture script scope. That reproduces the same dynamic-scope model as the real packet, which is exactly the bug class.
- The real runner identity is read, and the real `$PacketSha` is computed.
- The mock replaces only what follows `$psi`, keeping the actual text rendering.
- It asserts `BYTES=<file length>` at render time, which is discriminating. The r4 negative controls fail on the actual old bytes.
- After Apply it asserts that `$Bytes` is still an integer.
- The three replay refusals leave `rendered.sh` unchanged.
- The 6-line shell prefix test proves old 127 and new 0 without any path, PHP or network access.

**Fixture limits:**
- `Assert-Release` is stubbed, so the r5 gate body was verified only statically. It differs only in the path.
- Prior receipts, remote output and the backup are authored, so `Read-Result` success restates the contract.
- No real ssh or scp ran.
- PS7-to-PS5.1 `PSModulePath` stripping was applied to the child only.

## 5. Findings

**Mandatory findings (block this verdict): none.**

**Release conditions before owner Apply (not defects in these bytes):**

- **R1. Create and check the release.**
  - Create the r5 `review-release.json`: schema `acc009.remaining-review-release.v1`, `REVIEW_OK`, this request's ID, and the six hashes from §1.
  - Run the pure extracted r5 `Assert-Release` against it. Expect three exact accepts and three altered-hash refusals.
  - Record a mechanical release-path closure. `close_review.py` still checks the legacy path (O-r4-4).
  - **Closure:** the saved check results.

- **R2. File-mode interaction with `umask 077` (WP3b/N2).**
  - `$Common` sets `umask 077`, and the PHP runner inherits it.
  - Files created without an explicit `chmod`, such as `file_put_contents` or temp+rename, get mode 0600. That includes the new static assets: `booster-account-profile.js` ×2 (WP3b), `booster-account-universal.js` ×2 and, if rewritten by rename, `acc009-checkout.js` (N2).
  - PHP read by lsphp under suEXEC is proven fine by the Preflight probe. Static files served by the web server may return 403 when not world-readable, which would surface as a QA failure after the source has changed.
  - This was not addressed in the r3/r4 text I have, and I cannot see the runner.
  - **Closure:** a mechanical citation from the accepted runner bytes that targets get explicit modes, for example 0644, or keep the prior mode. If they do not, the WP3b/N2 verdicts do not carry to Apply; a runner change then needs review.

**Optional items (not release-blocking):**
- **O-r5-1. Harden against this bug class** in the next substantive revision only:
  - make the identity constants `Set-Variable -Option Constant`;
  - assert `$Bytes -is [int]` and the `$Sha`/`$Name` regexes in `Invoke-Remote`;
  - add a remote check `[[ "$BYTES" =~ ^[0-9]+$ ]] || die bytes_literal`;
  - drop the no-op self-replacements.
- **O-r5-2. Collision lint.** Add a cheap AST lint that case-folds top-level assignments and intersects them with variables read in functions, then run it on every future packet.
- **O-r5-3. PowerShell version.** Run the packets in Windows PowerShell 5.1, as the owner did. Under PS7, `ConvertFrom-Json` returns a `DateTime`, and `[datetime]::Parse` may misjudge receipt age. That is fail-closed in UTC+ zones.
- **O-r5-4. Duplicate test code.** `test_shell.py` duplicates the shell half of `validate_r5.py`. Record which one produced `shell-checks.json`.
- **O-r5-5. Retained items.** O1 (graceful fallback plus warning before WP3c), O-r4-2 and O-r4-3.
- **O-r5-6. Supersession.** Record that the r4 N2 and r4 WP3c packets are superseded. They would fail at line 6 the same way. The r4 WP3b packet is permanently consumed. Do not remove or alter the r4 artifacts.

## 6. Evidence reuse limits and next owner gate

**Reuse limits.**
- r4 REVIEW_OK is reused only for the byte-identical runners: specs, payloads, states 9/18/0, and the admission, backup, rollback and metadata algorithms.
- It is also reused for unchanged packet logic that the diff proves identical. I re-read the full r5 packets here anyway.
- The source and browser suites are reused, not re-run.

**Not established:**
- host state since 13:26Z;
- real MySQL, LiteSpeed static-serving and NP behaviour;
- owner UI QA;
- runner file-mode handling (R2).

**Next owner gate:**
1. **Release.** Record this acceptance, then close R1 and R2.
2. **WP3b Upload, Verify, Preflight.** The owner runs the r5 steps as separate invocations and sends the complete output.
   - Upload is expected to report `already_present_same_hash`; anything else needs assessment.
3. **WP3b Apply.** Apply only after assessment, within 30 minutes of a successful Preflight; otherwise re-run Preflight first.
   - Expected result:
     - exit 0;
     - `applied`, `changed_files=9`;
     - a backup matching `/home2/boosters/_patch_backups/ACC-009-WP3b-<UTC>-<8hex>`;
     - `self_deleted`;
     - one `pkt_cache_removed_php=N` line and one `retained` line.
   - Any deviation is a STOP with targeted assessment: no repeat Apply, no blind rollback. A consumed r5 marker means any further attempt needs a new packet and its own reviewed disposition.
4. **N2.** Only after WP3b success and focused QA.
5. **WP3c.** Only with a separate explicit owner go and a fresh, consistency-attested full DB backup held by the owner.
6. **Throughout:** no agent SSH/key/server access, deployment, Git or status writes, auth or key reset, or SimpleCheckout retirement.
