# RD-16 WP1 main R2, R8 affected ready review

**Verdict: Review OK.** There are no mandatory findings. The R7 M1 is closed. Optional notes follow.

This approval opens only the owner steps **Upload → Verify** for the exact pair below:
- Apply remains a separate owner decision.
- Deployed-WP1 owner QA must pass before WP2.
- It grants no execution, Git, status, SSH, key, server or deployment authority to any agent.

## Bound identities (approved)

I had no tools, so these are the supplied values, not recomputed. They are consistent across the facts file, the equivalence record, the runner-fixture result and the packet `$Sha` literal:
- The runner grew by 2,198 bytes (87,299 → 89,497). That fits the rewritten guard, the multiset function, the longer SQL and the two constant lines.
- The packet length is unchanged at 11,839 bytes. That fits an equal-length hash-literal swap.

| Artifact | Bytes | SHA-256 | Status |
|---|---|---|---|
| `patches/RD-16-WP1_account-shell-hub_20261025_r2.php` | 89,497 | `2104651019f35584f9fa24ab2310a16c65e2ba3abdeb381baa2472a9e2860651` | **Approved for owner Upload → Verify** |
| `diagnostics/RD-16-WP1_owner-r2_20261025.ps1` | 11,839 | `f1c6ffc2d0f49a4ee6354c17f69f22acf5e619bd546eab6723d21106eed016bd` | **Approved for owner Upload → Verify** (binds the runner hash above) |

The following pairs stay non-runnable:
- The R7 rejected pair (`a46a3add…` / `de32ed40…`) stays rejected.
- The R3 pair (`c8a8cc55…` / `c9976145…`) is SUPERSEDED, DO NOT RUN. That holds even if its old Verify could later pass.

## Reused scope, unchanged by exact identity

I accept the stated equivalence and do not reopen these items:
- the targets blob `b94a9e2f…` and dependencies blob `a636e3b5…`, which are byte-identical to R7;
- the seven `before`/`after`/`normalized_before` values, which are runner-enforced;
- the 44 pins;
- the unchanged `rd16_event_check` (the diff shows no change);
- the file engine, backup, rollback, lock, no-op and self-delete behaviour;
- the UI17, backend5 and six-order composition evidence;
- the owner sequencing.

The only packet change is the SHA literal. `$Name`, `owner-receipts-r2` and the remote `F=` path match the R7-reviewed text.

## M1: closed

- **Query.** It reads both keys across all stores, ordered by `(store_id,key)`, with `LIMIT 1025` and a 1,024-row cap. It runs inside the READ ONLY transaction. Only a SHA leaves the function, and no values reach output or error codes.
- **Value bound.** `LEFT(value,257)` with `strlen > 256 → STOP` cannot create a passing value by truncation. Any oversize value, measured in bytes or characters, stops.
- **Grouping.**
  - Store IDs are canonical non-negative integers.
  - A duplicate `(store,key)` stops.
  - `serialized !== '0'` stops, including NULL.
  - This matches the startup decoder: decoding is refused, never guessed.
- **Effective config.**
  - `array_replace($stores[0], $values)` reproduces `getSettings` (store 0, then store N, ascending overwrite).
  - Store 0 is evaluated itself.
  - Stores without rows, including nonexistent or negative `store_id` values from the request, resolve to store 0 and are covered.
- **Derivation (O1).**
  - The theme is `config_theme==='default' ? theme_default_directory : config_theme`. Using `===` is equivalent to the vendor's `==` because the strings are non-numeric. NULL maps to `''`.
  - The segment must match `^[A-Za-z0-9_-]{0,64}$`. That excludes `.` and `/`.
  - These prefixes must be absent:
    - `<theme>/template/`, with an empty theme mapped to `template/template/` (the same POSIX target as the vendor's `//` path);
    - `default/template/`.
  - The base prefix is exempt because it holds the targets themselves.
  - `rd16_walk` refuses linked components. A directory where a file is expected stops via `not_regular_file`.
  - `basic` with the files absent passes. This removes the R7 false-STOP risk on stock OpenCart 4.
- **Fixtures.** These discriminate every M1 branch:
  - store-N unsafe value: STOP;
  - store-N alternate file: STOP;
  - inherited `config_theme` with an overridden directory and a file present: STOP;
  - store 0 only: pass;
  - duplicate key: STOP;
  - serialized value: STOP.
- **Fixture tests.** The admission tests run on functions extracted from the hash-bound runner itself. The 11-case suite reran under `runner-fixture-r8b` and binds `2104…`.

The batched items are also resolved:
- **O2:** an exact four-element multiset.
- **O3:** `DIR_TEMPLATE` required and exact; `DIR_CATALOG` must be undefined.
- **O4:** comparison against the decoded R3 blob.
- **O5:** the old pair is retired.
- **O6:** cross-pair and `|` alias fixtures.

The code is PHP 8.0-compatible. Arrow functions appear only in the tests.

## Optional (non-blocking)

**N1. The `DIR_MODIFICATION` check is nominal.**
- `strpos($path, DIR_MODIFICATION)===0` tests whether the template path lies *under* the modification directory.
- OCMOD-style redirection works differently. It maps `DIR_APPLICATION…` to `DIR_MODIFICATION.'catalog/…'`, and that prefix never matches a public_html path. So if the constant were defined, a redirected copy, including one of the base `header.twig`, would not be detected.
- This is non-blocking: stock OpenCart 4 `config.php` does not define `DIR_MODIFICATION`, and it was optional at R7.
- **Recommendation:**
  - In the next revision, replace the check with "STOP if `defined('DIR_MODIFICATION')`".
  - Until then, read the "modification_prefix STOP" evidence as covering the literal prefix case only.
  - Owner QA of the header token and hub would surface the defect.

**N2. Small fixture gaps.**
- `default/template/` present → STOP.
- Empty theme with `template/template/` present → STOP.
- Invalid store ID.
- A row-cap case.

The code paths exist; these cases would only document them.

**N3. The multiset is stricter than the R7-accepted predicate.**
- A database row such as `view/*/before:event/theme` was previously admitted. It now STOPs as an extra row.
- This is consistent with the live capture of exactly 4 relevant rows. It is fail-closed, not fail-open.

**N4. The rollback path now inherits the stricter constants.**
- A host `config.php` change after Apply that alters `DIR_TEMPLATE` or defines `DIR_CATALOG` would block the scripted rollback.
- That is fail-closed and would need a targeted assessment.

**N5. The runtime SQL and constant code has only been reviewed statically.**
- The fixture stubs `rd16_runtime`.
- Owner Verify is the first execution of this code. Any defect there would STOP with `runtime_admission_failed` before backup.
- Host PHP 8.0, MySQL schema and settings, constants, OPcache, the authenticated UI, `common.js` and gtag remain owner Verify/QA items.

**N6. Carried over.** R7 O7 and R3/R2 optional notes 1–5 remain non-blocking.

## Gates

1. Owner Upload, then Verify, with the `2104…`/`f1c6…` pair only.
2. Apply is a separate owner decision.
3. Deployed-WP1 owner QA must pass before WP2.
4. On any STOP, preserve receipts and do not replay.
