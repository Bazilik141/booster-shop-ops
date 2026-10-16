# Independent Claude CLI review вЂ” ACC-008 key-preservation check

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `fc46c2234b07c76be48e0a7c569d88db22d57315673ece1574f7d246bdac3ac0`
Packet SHA-256: `9539aac20c91150a9f8e4472338efe02e1e97b6caedc15007043640bbca56f57`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# ACC-008 key-preservation helper, round 15 (r14 M1/M2/M3 repair): independent CODE review

## Verdict: **Source Review OK**

This OK covers only the CODE of `patches/ACC-008_key-preservation-check-r15_20261016.php`, SHA-256 `fc46c2234b07c76be48e0a7c569d88db22d57315673ece1574f7d246bdac3ac0`. That SHA is as declared in `build.json`, `local-checks.json` and the report; I cannot recompute it here.

**This OK does not cover:**
- any host run or host result;
- owner QA;
- r13 deployment;
- G2 or WP3.

**Earlier artifact:** the r14 Return for changes still stands for `a31c2fb8…767c`. Do not run that file.

**Mandatory findings:** none. The optional items below do not justify a separate provider round.

**What I read in full:**
- the generated PHP;
- `build_probe.py`, `fixture-db.php`, `json-checks.php` and `run_checks.py`;
- the three result JSONs;
- `prepare_repair_r15.py`;
- the report and `repair-r15.md`;
- the r14 verdict and closure;
- the r13 verdict and closure;
- the review rule.

**What I relied on by identity only:**
- the template `probe.php`;
- `runtime_admission.php`;
- the guard source;
- the payloads.

Every body that actually executes is rendered inside the composite PHP, and I reviewed it there.

---

## r14 mandatory findings: all closed

### M1: name witness

**What the code now does:**
- The same single prepared SELECT returns 8 columns, now including `c.firstname`, `c.lastname`, `a.firstname` and `a.lastname`.
- `bind_result` binds 8 columns, and the fixture enforces `count===8`.
- `a08q_name_digest` keeps only an HMAC with the per-run random key. Raw names are never stored or printed.
- The digest is computed from `json_encode([first,last])`, so the boundary between the two names cannot be confused.
- Invalid UTF-8 or a name longer than 4096 bytes refuses (fail closed).
- Checkpoint schema 2 validates each row's `name` as 64-character hex.

**The preservation comparison:**
- `a08q_values_only` strips `name` from both sides before the strict `===` comparison.
- Intended name edits therefore cannot change the custom_field result.
- Key order still matches on both sides: the `count/digest/tracked` order is preserved through the JSON round trip, and the rows are `ksort SORT_STRING`.
- The `inconsistent_account_read` check still includes the name. It is deterministic within one statement, so this is correct.

**Result order:**

| Condition | Result | Exit | Checkpoint and script |
|---|---|---|---|
| Rows differ | `CHANGED` | 1 | retained |
| Zero existing keys | `INCONCLUSIVE_NO_EXISTING_KEYS` | 2 | retained |
| Customer name unchanged, or no address name changed | `INCONCLUSIVE_SAVE_NOT_DETECTED` | 2 | retained |
| Otherwise | `UNCHANGED` | 0 | cleanup runs |

- Address additions or deletions reach `CHANGED` first, so the name loop's `isset` restriction is safe.
- Output is limited to `customer_name_changed=yes|no` and `address_rows_name_changed=N`.

**Tests:**
- The positive path asserts both witness lines.
- Three no-save variants each require exit 2 with the checkpoint and script retained.
- Name canaries are asserted absent from stdout, stderr and the checkpoint.
- The `name_gate_removed` mutant is caught.
- A mutant that keeps `name` inside `$same` is implicitly caught, because the happy path would become `CHANGED`.

### M2: TTY refusal before input

- In `--before`, `stream_isatty(STDIN)` refuses before `stream_get_contents` and before any DB work.
- It runs after source and config admission, which is acceptable: no input has been read yet and those steps do not touch the DB.
- `--after` and `--cleanup` never read stdin.

**Bounded read:**
- The read is capped at 257 bytes, and `>=257` refuses. A silent prefix lookup is therefore impossible.
- Multiline or garbage input fails `FILTER_VALIDATE_EMAIL`.
- Error codes never echo the input.

**Tests:**
- The forced-TTY branch is tested with no checkpoint and no `query-shape.txt`.
- Every other before case runs through a real pipe, which shows that a pipe is not misdetected as a TTY.
- Native Linux TTY detection is honestly not claimed.

### M3: strict end of fetch

- `if($fetch!==null||$st->errno)` now refuses `false` and `101`, along with any other non-null value.
- The fixture's `101` after the first row refuses, closes the statement and connection, and creates no checkpoint.
- The false-only EOF mutant is caught.

---

## Independent reassessment

### Privacy
- The email travels only by a hidden `read -s`, then the `printf` builtin, then a pipe. It never appears in argv, history or output.
- The `( … )` block is parsed in full before the read runs, and `set +x` is set.
- If the owner pastes more than one block at once, the extra text could be consumed by `read`. It would then fail email validation, fail closed and not be echoed.

**The checkpoint:**
- It holds IDs, counts, HMACs, source hashes, the DB-target HMAC and the random key.
- It holds no values, names, email or credentials.
- Because the key is stored next to the digests, low-entropy names and values could be found by dictionary search. Treat the checkpoint as being as sensitive as the DB itself. It must never leave the host. The design already requires this.

**Errors:**
- Errors are reported through a typed exception with a fixed-code regex. The `:` and `/` characters are now kept.
- Every other Throwable is collapsed to `qa_check_failed`.
- Warnings, including `@`-suppressed ones and PHP 8.1+ deprecations, become `runtime_warning`. Driver and connection messages, which include the DB username, are never printed.

### SQL
- The prefix is validated as alphanumeric plus underscore, and the WHERE clause is constant.
- The derived `LIMIT 2` together with the outer `LIMIT 33` and the 32-row cap still refuses duplicate emails in every ordering. A second account is detected before the cap is incremented.
- A duplicate email where neither account has addresses also refuses, through the two NULL-address rows.
- `--after` binds the stored integer ID, so an email change cannot retarget the check.
- The statement closes in `finally`, and so does the connection.
- There are no write statements and no `get_result`.

### Numeric values, duplicate keys and canonicalisation
- The five parser methods are unchanged and were accepted in r14.
- The `canonical()` and `pairs()` reasoning from r14 still holds. JSON floats with a zero fraction encode as integers without `JSON_PRESERVE_ZERO_FRACTION`, so the stated equivalences match the writer's normalisation.
- A root list is keyed by `0..n`, which matches `assoc=true` consumers. It is irrelevant for real custom_field objects.

### Lifecycle
- `--before` is exclusive in two ways: an early existence and link check, then `fopen 'xb'` under umask 077.
- A `fopen` failure surfaces as `runtime_warning` instead of `checkpoint_exclusive_create_failed`. It still fails closed and the umask is restored.
- 0700/0600 modes are required, links and hardlinks refuse, and ownership is checked when POSIX functions are available.
- Expiry is checked from 60 seconds in the future to 24 hours old.
- Source drift is checked as an exact ordered variant match.
- DB-target drift is checked as a keyed HMAC that excludes credentials.
- Before deletion, the code confirms the checkpoint still matches its hash at read time.
- Cleanup returns before source, config or DB work, and handles a corrupt checkpoint.
- A linked or wrong-mode checkpoint makes cleanup refuse and keeps the evidence (fail closed).

### Source variants
- The `r11` and `profile_r13` sets of nine identities each match exactly, with their keys in the same order. Both are tested.
- A mixed state or an install between phases refuses.

### No business-data writes
- By inspection, the only filesystem writes are the private checkpoint, its own deletion and the script's self-deletion.
- There are no DB writes.

### Testing adequacy
- 25 pure cases, 28 lifecycle cases and 4 mutants all discriminate on the claims that matter.
- The `constant_full_digest` mutant is caught by the pure suite through the child's `assert`.
- The cleanup test now deletes the DB marker files before cleanup and asserts they stay absent.

---

## Optional findings (none needs a round of its own)

1. **Address coverage attribution.**
   - `address_rows_name_changed>=1` does not show that a renamed address actually carried keys.
   - `service_key …:present_rows` may come from a different address.
   - A future refinement could print one count: the number of renamed address rows that had at least one existing key.
   - Until then, use the assessment rule in the owner gate below.
2. **Cleanup can fail after `result=UNCHANGED`.** The `UNCHANGED` line prints before cleanup. If cleanup then fails, the output has `UNCHANGED` but no `done=ok`, and exit is 1. The gate below treats that case as an assessment item.
3. **The empty private directory is left behind.** An `rmdir` when empty would tidy this up. It is not a safety issue.
4. **More mutants would strengthen the suite.** Candidates:
   - removing the row cap;
   - removing the duplicate-account check;
   - making tracked preservation always true;
   - removing `values_only`.
5. **Shell compatibility.**
   - `read -s` requires bash; in `sh` or `dash` it fails closed through `|| exit`.
   - `FILTER_VALIDATE_EMAIL` refuses IDN/Unicode emails; this also fails closed.
   - Use bash and an ASCII-email test account.
6. **Stale wording.** The packet preamble still says "Two mutants" (it is now 4).
7. **Earlier r14 optional items still stand:**
   - the CLI and web `serialize_precision` can differ (this fails closed);
   - an optional read-only transaction.

---

## Evidence limits

**Not proven locally:**
- native MySQL/MariaDB execution of the SQL and the email collation;
- mysqlnd behaviour;
- Linux 0700/0600 modes, nlink behaviour and ownership;
- native `stream_isatty`;
- hosting PHP 8.0 CLI.

**What a pass will and will not show:**
- A pass covers only keys that already existed on the selected account's rows, within that time window.
- A service with `present_rows=0` has no coverage. Nothing may be seeded.
- The name digests show that the customer row and at least one address row were rewritten. They cannot show *which* of the four forms did it; that rests on the owner's report.
- No universal account coverage is shown, nor NP variants, authorisation, `customer_activity` or shared checkout/First15 writers.

**r13 status:**
- `source_variant=profile_r13` would show only that the r13 source files are present.
- It is **not** the r13 apply receipt. That gate stays pending.

---

## Owner gate (this SHA only)

### 0. Preconditions
- Decide on r13 first: either finish its reviewed chained apply and return its full output, or postpone it until this check is completely finished.
- Install nothing between the phases.
- Finish within 24 hours.
- Use bash, and use the same own or test account as the earlier QA, with an ASCII email.
- Preferably the account has exactly one saved address that holds the hidden keys.
- No checkout and no address add/delete during the window. Do not edit custom fields.
- Paste one block at a time.

### 1. Before phase
Run the report's reviewed subshell block: SHA check, `php -l`, hidden `read -r -s`, then pipe into `--before`.

**Expected output:**
- `source_variant=r11|profile_r13`
- `config_admission=…`
- `db_access=SELECT_ONLY`
- `private_checkpoint_modes=checked_0700_0600`
- `private_checkpoint=saved_outside_public_html`
- `address_rows=N`
- `before=ok`
- `done=ok`

Any `error=` line means stop and return only that output. Never re-run over an existing baseline.

### 2. Saves (names only, same account and same address)
1. Storefront profile.
2. Storefront address.
3. Admin customer.
4. Admin address.

Wait until each save has completed. The **final** first and last names of the customer and of the address must differ from the originals. Restore them only after a passing after phase.

### 3. After phase
Run the reviewed after subshell: SHA check `&&` then `--after`.

### 4. How to read the result
**Evidence for G2 assessment requires all of these:**
- `result=UNCHANGED`
- `existing_keys>0`
- `customer_name_changed=yes`
- `address_rows_name_changed>=1`
- `cleanup=ok`
- `done=ok`
- exit 0

**Scope of that evidence:**
- It covers only the `service_key …:present_rows/preserved_rows` entries that are greater than 0.
- Address-writer coverage counts only if `address_rows_before=1`, or if the owner confirms that the saved address is the one holding the keys. Otherwise address coverage is unattributed.
- The customer key count is `customer_keys_before`.
- The address key count is `existing_keys` minus `customer_keys_before`.

**Other outcomes:**
- **`UNCHANGED` without `done=ok` (cleanup failed):** stop and return the output. The checkpoint state needs assessment.
- **`CHANGED`:** the checkpoint and script stay in place. Return the output. Do not run cleanup, do not re-run, and do not infer data loss until the assessment is done.
- **`INCONCLUSIVE_*`:** return the output. Do not re-run `--after` or `--cleanup` until assessed.
- **Any `error=`:** stop and return the output. Do not bypass or retry.

### 5. What to return
- Both console outputs, plus which of the four forms were saved.
- Do **not** return the email, IDs, the checkpoint, JSON, values, names, admin URLs or screenshots with personal data.
- Cancelling with `--cleanup` is not QA acceptance.

**Gates that stay in place:** G2 stays open, the r13 owner receipt stays pending, and WP3 stays closed.
