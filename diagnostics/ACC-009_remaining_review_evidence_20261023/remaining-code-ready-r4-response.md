# ACC-009 r4: narrow repair, final integrated CODE/composition review

**Overall verdict: REVIEW_OK for all three packages.** The verdict covers the six exact identities in §1 and nothing else.

- **Owner sequence.** The release must follow the owner sequence in §6.
- **WP3c.** WP3c keeps its separate owner go and its fresh, consistency-attested full DB backup.
- **No host claims.** This review does not establish the cause of the host STOP or any host success.

**What I read.** I read the following statically:
- the complete new common algorithm;
- the complete decoded spec metadata for all three parts (payload bodies omitted by declaration);
- the three complete r4 owner packets and the exact diff;
- `equivalence.json`;
- the generator `prepare_r4.py`;
- `prepare_review.py`, the native admission fixture and the packet test;
- both check results;
- the r3 response, acceptance and release, and the r3 Preflight-STOP receipt;
- the dispatch and closure tooling.

**What I did not do.** I executed nothing and computed no hashes. Hash equality comes from the generator assertions and the rendered headers.

## 1. Per-package verdicts and exact identities

| Package | r4 runner SHA256 (bytes) | r4 owner packet SHA256 | Verdict |
|---|---|---|---|
| ACC-009-WP3b | `0854ffe33ac9239349d95967cca264a378c429a86b2a988f0d028dcc53957166` (465933) | `63b6601269f2c459c1febed893ec565fc3cb1262a548f7a79473c236bcbabaf2` | **REVIEW_OK** |
| ACC-009-N2 | `8c81393cd73c43a8ec70ae98deab0cf1c107fa3dc6cfd0d63ef58f158c8aff89` (900825) | `4aa1b0251a34ba60bff81d95880af1a35d4c40713c1cdb8c7397b59f10172d76` | **REVIEW_OK** |
| ACC-009-WP3c | `4f6b5a180caeb5e72f37eb3df14d7bbc36c401372632f9f98dfe369db460878c` (120709) | `1e4b4ddb1f154b908d9e64bbebf8d4fec9906aafb175effa06fd222a7494728c` | **REVIEW_OK** (activation gated) |

**Cross-checks.** The following values agree with each other:
- `release-facts.json`;
- `equivalence.json`;
- each packet's `$Sha`, `$Bytes` and `$TargetCount` (9/18/0);
- the remote `F/SHA/BYTES` constants;
- the inline source gate counts (9/18; the WP3c gate is disabled by `'1' = 0`).

**Size check.** Each runner grew by exactly 261 bytes. I counted the replacement by hand: the old line is 90 bytes and the new five-line block is 351 bytes, also 261. That is consistent with:
- a single ASCII-only substitution;
- no line-ending translation elsewhere, even though the generator round-trips the file through decoded text.

## 2. The defect and the repair

**The code defect is confirmed.** In r3, `ar_metadata` compared the codes from `SELECT code … ORDER BY code` positionally against a byte-ordered spec list.

The spec order is byte order: `PintaNovaPoshtaCod` and `SimpleCheckout` come before every lowercase code. Under any case-insensitive collation, `PintaNovaPoshtaCod` sorts among the `o…`/`p…` codes and `SimpleCheckout` before `telegram_notify`. An identical multiset would therefore STOP in r3.

I agree with the report that this does not prove the host multiset is unchanged. A binary collation combined with real drift produces the same STOP.

**The repair is correct and minimal:**
- **Sorting.** PHP 8.0 `sort(…, SORT_STRING)` is a locale-independent binary comparison: case-sensitive and byte-wise, with no `SORT_FLAG_CASE` and no `SORT_LOCALE_STRING`.
  - Both arrays are reindexed 0…n-1, so `===` compares a list against a list.
  - Values are strings from `bind_result` and strings from JSON.
- **Strictness is unchanged.** Exact bytes, case, spelling, trailing whitespace and multiplicity all remain significant:
  - duplicates are not merged;
  - a NULL code would still fail `===`.
- **Containment.** `$codes` and `$expectedCodes` are local and are not used after the assertion. Nothing else changed:
  - the query;
  - the cap (`LIMIT 129`, cap 128);
  - the error code;
  - the ordering relative to the other gates.
- **No other comparison has the same defect.** I checked every other comparison:
  - **Events:** both sides are re-sorted in PHP before `===`.
  - **Registry columns:** compared with `array_diff`.
  - **Sender rows:** checked as count plus membership.
  - **Snapshot queries:** ordered by integer IDs and compared only against the same DB/query (preflight hash versus apply).
  - **Callers and readers:** compared after `ksort`.

**Single change point.** `prepare_r4.py` asserts that the old line occurs exactly once and that AR_SPEC is byte-equal to r3. `prepare_review.py` independently re-asserts:
- AR_SPEC equality with r3;
- that the factored algorithm equals `common-algorithm.php` for all three runners;
- that each decoded spec equals its test spec;
- that every payload body hashes to its declared `after`.

The algorithm I reviewed is therefore the algorithm in all three executables.

## 3. Mandatory findings

**None.**

## 4. Checks performed

**Admission strictness.** Fresh r4 Preflight still re-runs every gate:
- target states;
- the 222/225/227 context pins;
- the 24 callers and 37 readers;
- OCMOD;
- config and cache path;
- events;
- startup and theme;
- extension multiset;
- grants and triggers;
- language and registry (27 fields, all inactive, no mapping);
- sender (N2/WP3c);
- the web runtime probe.

Nothing reverts to old pins and nothing loosens.

**Classification of the r3 STOP.** The r3 STOP is a no-write fail-closed outcome:
- `ar_targets`, `ar_admit` and `ar_metadata` up to this point are read-only;
- `acc009a_rows` accepts only `SELECT`;
- the run was `--dry-run`;
- the STOP precedes the web probe, the only filesystem write in a dry-run.

**What the r3 STOP shows about earlier gates.** Because of the execution order, the earlier gates passed on the host at 12:08:50Z on 2026-10-23. Those gates are:
- target before-states;
- the 222 context pins;
- caller and reader maps;
- an empty OCMOD;
- config and cache path;
- the active-event set;
- startup and theme counts of 0.

This is historical evidence only; r4 re-checks all of them. It does retire r3's "most likely remaining STOP (tree drift)" for that moment.

**Fixture fidelity.**
- The tests use the actual candidate code: the helpers are extracted from each r4 runner, and `prepare_review.py` checks that extraction.
- Only three functions are removed: `ar_db`, `acc009a_config` and `acc009r_web`. The removal also drops the adjacent `const AR_SPEC`, which correctly explains the reported "Undefined constant" draft failure. `ar_spec()` is not called by the fixture.
- Each part uses its own decoded 23-code list. The fixture covers:
  - three distinct permutations, all accepted;
  - added, removed, duplicate and case-changed lists, all refused with the same code.
- The changed lines run before sender and registry, so the fixture `id=WP3b` does not hide anything relevant.
- The fake boundary is the mysqli transport only.

**Packets.** The diff shows exactly the expected lines in each packet:
- runner name, hash and size;
- the r4 receipt path;
- the r4 release path;
- the self-identical `Invoke-Remote` replacement.

All other logic is byte-unchanged from the r3-accepted packets. That covers:
- release-before-transport;
- strict transport and result validators;
- the 2 h / 30 min freshness windows;
- the atomic `CreateNew` Apply marker;
- the 600 s wait with no replay;
- `&&`-gated, template-PHP-only cache cleanup after source success only;
- WP3c backup attestation;
- strict known-host checking with no forwarding.

**Release and no-replay.**
- The r4 packets accept only `…r4_evidence…\review-release.json` whose per-part hashes match.
- `Get-Prior` additionally binds `runner_sha256` and `packet_sha256` from the receipts.
- The new r4 receipt directory holds no Apply marker.
- A stale or failed r3 Preflight therefore cannot authorize an r4 Apply.
- The r3 artifacts and staged runner are untouched.

**Backup.** Unchanged. Source apply writes a private per-run backup with a manifest. Dry-run reports `backup=none`. Rollback is manual and assessed.

**Composition.** WP3b, N2 and WP3c payloads and pins are unchanged. If r3 WP3b were ever applied, r4 WP3b would report `already_applied` and the packet would refuse with a STOP, not a write. There is no composition hazard.

**Privacy.** The packet contains:
- extension codes, which are technical identifiers;
- fictional fixture names;
- a receipt with only technical fields.

It contains no credentials, business data or raw protected H1 bodies.

## 5. Optional items (not release-blocking)

- **O-r4-1: generator rigor.**
  - **Gap:** `prepare_r4.py` uses global substring replacement plus text-level reverse-equivalence. That does not, by itself, prove that only intended occurrences changed or that bytes outside the edit are identical.
  - **Why it does not block:** the exact diff and the 261-byte delta close this here.
  - **Future fix:** use anchored replacements with occurrence-count assertions, plus a byte-level reverse check (r4 bytes with the block swapped back must hash to the r3 SHA).
- **O-r4-2: mismatch diagnostics.** A future reviewed change could emit the bounded added and removed code lists (technical identifiers only) on `extension_namespace_inventory_changed`. That would avoid a separate collector round. Do not change r4 for this.
- **O-r4-3: stronger fixture cases.**
  - Add an equal-count multiset substitution, for example replacing one code with a duplicate of another.
  - Add a negative control that runs the r3 helper against the SQL-like order, proving the test actually discriminates.
  - `strcasecmp` differs from MySQL `general_ci` for `_`. This is immaterial given order independence.
- **O-r4-4: closure tooling.** `close_review.py` still checks the legacy `ACC-009_remaining_evidence_20261022` path (r3 O-r3-5).
  - For r4, record a mechanical release-path closure as was done for r3.
  - Do not edit that script while it is a frozen declared input.
- **O-r4-5: supersession.**
  - Record in the continuation pointer that the r3 packets are superseded and that the owner uses only r4.
  - Running r3 again is not unsafe, but it is pointless.
  - The staged r3 runner may stay in the private stage; any removal is a later, explicit owner action, never automatic.
- **Retained from r3:** O1 (fail-safe profile/admin fallback plus the runbook warning, recommended before WP3c), O2–O5, and r3 O-r3-1 to O-r3-4.

## 6. Limits and next owner gate

**Limits.**
- I executed nothing.
- I relied on generator assertions for hash and byte equality and for the AR_SPEC/payload identity with r3. I did not re-read the payload bodies now.
- r3 REVIEW_OK is reused only for:
  - the unchanged source payloads and spec;
  - the rollback and metadata algorithms;
  - the owning dependencies;
  - the browser and lifecycle suites.
- The host's code list and collation are unknown.
- Real MySQL behaviour, NP provider semantics, the full SimpleCheckout bootstrap and owner UI QA remain unestablished.

**Next owner gate.**
1. **Record the release.** Record this acceptance and create `diagnostics\ACC-009_remaining_repair_r4_evidence_20261023\review-release.json`.
   - Schema `acc009.remaining-review-release.v1`, status `REVIEW_OK`.
   - Bind this request's ID and the six hashes in §1.
   - Any byte change voids the verdict for that part.
2. **WP3b r4 up to the evidence gate.** The owner runs Upload, Verify and Preflight as separate invocations, then sends the complete Preflight output.
   - Apply only after that evidence gate.
   - The packet requires a Preflight-OK no older than 30 minutes. If the gate takes longer, re-run the dry-run Preflight first; it is safe to repeat.
   - A Preflight that gets past this point will create and delete the nonce web probe. A cleanup STOP needs manual assessment.
3. **If Preflight STOPs again:**
   - **At `extension_namespace_inventory_changed`:** treat it as a real multiset difference. Collect only a bounded technical code inventory with a separately reviewed collector. Never guess a baseline, and no automatic retry.
   - **At any later gate** (grants/trigger visibility, registry, language/field count, mapping, runtime): record it as STOP and assess.
4. **After WP3b Apply and focused QA:** N2 follows the same pattern.
5. **WP3c:** only with a separate owner go and a fresh, consistency-attested full DB backup. O1 is recommended first.
6. **Throughout:** no replay or blind rollback, no SSH/auth changes, no SimpleCheckout retirement, and no Git scope.
