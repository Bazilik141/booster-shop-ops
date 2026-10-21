# ACC-009 C1-H1: assessment of the actual schema result and the one-function admission change

I used no tools and made no server, key or profile contact. This review covers only the supplied artifacts. Hash-only dependencies are reused by identity and were not re-verified.

## Verdict

**CODE and packet are OK for gated Upload → Verify → Preflight only.** Main Apply, cache, browser and QA remain held, as does any schema, index or SQL-mode change.

No code change is required. The mandatory items below are closure and record-keeping conditions, not code defects.

## 1. Actual schema result

- **The capture is consistent.**
  - 12 gate values were recorded. `history_integer_nonnull=fail` is the only failure.
  - The PK `order_history_id` is `int(11)`, `PRI`, `auto_increment` and `NO`.
  - `order_id` and `order_status_id` are `int(11)` and nullable `YES`.
  - InnoDB, a positive next ID and autocommit=1 are all OK.
  - Events, startup, theme, providers and order-session gates are all OK.
- **The cause is correctly attributed.** The parent guard assumed `NOT NULL` on all three columns, and the live schema never matched that assumption. The capture shows nothing wrong with orders.
- **The missing `order_id`-leading index stays a performance-only flag.** This is what the original code did. The return value is already `absent_performance_only`, and the function never required an index.
- **Non-strict SQL mode is correctly report-only.**
  - It does not change how `=`, `>`, `<>` or `MIN` behave in SELECT.
  - Non-strict mode only affects coercion on INSERT. The native INSERT writes explicit integer casts, so it is unaffected.

## 2. The code change and how it is bound to the shipped runner

- **The diff touches one function.**
  - Only the nullability loop inside `h1_metadata` changed.
  - The integer-type regex still applies to all three columns.
  - The PK, auto_increment, engine, autocommit, index-probe and event checks are untouched.
  - The return and stdout shape is unchanged.
- **`build_admission.py` mechanically proves nothing else changed.**
  - It asserts each anchor appears exactly once.
  - It asserts the prefix and suffix bytes equal the parent's.
- **I cross-checked the byte delta independently.**
  - The four replacement lines total 293 bytes and replace a 74-byte line: +219.
  - `'NO'` → `$nullable` adds +5.
  - Total +224, which matches 1087405 → 1087629 exactly.
  - The PS `$PinnedBytes` change agrees with this.
- **The two hashes for the method are not a conflict.** `metadata-after.php` (`c480…`) contains a `<?php\n` prefix, so its hash differing from `method_sha256` (`116cb…`) is expected.
- **The tests discriminate.**
  - The parent function refuses the actual captured columns, so the mutant is killed.
  - The successor admits them with the index absent.
  - All 8 nearby wrong shapes are refused.
  - Only the column rows come from the real capture. PK, engine, autocommit, index and event rows are fictional.

## 3. NULL semantics: what each level of proof actually shows

| Level | What it proves | What it does not prove |
|---|---|---|
| **SQLite** (28 snapshots) | The actual helper SQL text has standard three-valued NULL behaviour. `=`, `>` and `<>` reject NULL, and `MIN` ignores NULL. SQLite applies numeric affinity to the quoted literals, as MySQL does. | MySQL behaviour, transactions, concurrency, performance |
| **Fake driver** | Nothing about SQL. Its PHP filter is hand-written (`null>0` is false; `null!==41`), so its NULL behaviour is circular. Only the SQLite step validates the SQL. | — |
| **Native fixture** (36 assertions) | It runs the complete compiled model and Telegram code with real Registry and Controller classes. The INSERT regex confirms explicit integer `order_id`/`order_status_id`. The preseeded rows discriminate: if NULL were matched, row 1 (NULL order) or row 2 (NULL status) would become `MIN`. That would turn FIRST into LATER and fail the `order_add` assertion. | Delivery, real DB |
| **Production** | None. The live Preflight will prove live metadata admission only. | — |

**Answer to "is exact observed admission sufficient?"** Yes, for admission. The positive filter, the explicit integer writes and the non-NULL PK together make `NO/YES/YES` behaviourally equivalent to the parent's assumed `NOT NULL` contract for the FIRST/LATER decision.

**The NCRM test correction is acceptable.** Only the test expectation changed, not production code. The unchanged legacy NCRM filter sends `order_add` only, and no NCRM status feature is claimed.

## 4. PS packet composition

- **The diff is exactly three constants:** path, SHA and bytes, `12d2a211` → `82362b9f`. The PowerShell 5.1 parse passed, and control flow is byte-exact.
- **The pin derives a new state, so a fresh Upload is required.** The old stage and accepted Verify stay retained.
- **The author's order is correct:** Upload, then Verify, then Preflight, with no Apply and no repeated Unlock. The owner's existing work-period authorization is reused, and agents get no SSH or key access.

## Mandatory findings and closure criteria

- **M1. Closure requires an owner run of `82362b9f` as three separate steps:**
  1. A fresh Upload of runner `3403f0f2…` (1087629 bytes).
  2. A Verify whose reported SHA and bytes match.
  3. A Preflight that passes `history_column_contract` and every other original gate on the live query. The other gates are: PK/auto_increment, engine and next ID, autocommit, the event bindings, the void15 contract, the 26 source targets and 145 pins, Mono-fix preservation, and web/OPcache/source admission.
  - Any refusal is a STOP and must not be retried by changing the code.
  - Receipt stream hashes, Base64 lengths and exit codes must be validated mechanically, as for the capture.
- **M2. Record the exact-pin STOP semantics.**
  - Any nullability drift is a STOP, including a "fix" to `NOT NULL` on the secondary columns.
  - So no one may change the schema between now and Apply without a new reviewed successor.
- **M3. Record the NCRM legacy limit.**
  - NCRM receives `order_add` only.
  - The original fixture expectation was wrong and has been corrected.
  - Neither a regression nor an NCRM status feature is claimed.

## Optional findings (no implementation round needed)

- **O1.** `changed_secondary_nullability` mutates only `order_id`. An `order_status_id` YES→NO mutant would make the map fully mutant-complete. The risk is low because the same loop visibly covers both columns.
- **O2.** `test_metadata.php` loads the runner by filename. Asserting the runner's SHA `3403…` inside the test would bind it directly rather than through `build-proof` and the method hash.
- **O3.** The output field `original_accepted_C1_fixture_modified:false` is true only of the original file; the working copy does differ (see `native-fixture.diff`). A clearer label would avoid misreading it.
- **O4. Residual risk, recorded only, no new reads.**
  - A historical order whose only "positive" history rows have NULL `order_status_id` would classify FIRST on its next positive status, and would then send `order_add`/Telegram.
  - This can only arise from non-native writers.
  - It belongs to the same history-derived residual class already accepted.
  - The number of such rows is unmeasured, and that is by design.
- **O5.** With no index, each `addHistory` call runs a consistent non-locking scan for `MIN`. Carry this to the performance backlog; it is not in this scope.
- **O6.** These items from the prior closure stay carried to Apply:
  - The `getOrder` contract must remain missing-row-only (prior O3).
  - On rollback, restore ACC009 before any Mono rollback; the two use different backup roots (prior O4).
  - Owner-only Unlock (prior O5).

## Reuse note

The private compiler, transform and preservation files, the NULL-SQL result, the snapshots, the release facts and the author response are accepted by hash only. Raw business rows were never read. No new source access was granted and no new exports or DB mutations were made.
