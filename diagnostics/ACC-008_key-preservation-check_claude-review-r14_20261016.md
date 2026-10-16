# Independent Claude CLI review вЂ” ACC-008 key-preservation check

Date: 2026-10-16
Channel: native Claude Code 2.1.299, verified saved Claude.ai subscription
Model: claude-opus-5-5
Effort: high
Patch SHA-256: `a31c2fb87259210fa00bdf097352cb777575b2afccceb3c6131d8a314e63767c`
Packet SHA-256: `e788ddb313f50cd3cd185d35350c0ff7448410116145c987dbc512bd65f242e3`

Owner explicitly requested CLI subscription review. The packet was supplied on stdin; tools, MCP, hooks, skills and session persistence were disabled. No API key was loaded, no API fallback/account billing setting/Git/Notion/dashboard/deploy was used. This is independent source review, not native execution or production acceptance.

---

# ACC-008 key-preservation helper, round 14: independent CODE review

## Verdict: **Return for changes**

This verdict applies only to the CODE of `patches/ACC-008_key-preservation-check_20261016.php`, SHA-256 `a31c2fb87259210fa00bdf097352cb777575b2afccceb3c6131d8a314e63767c`.

- The owner must **not** run this SHA.
- There are three mandatory findings. Each fix is small, and all three belong in one fresh repair round.
- Nothing here accepts r13 deployment, owner QA, G2 or WP3.

**What I read:**
- the complete generated PHP;
- `build_probe.py`, `fixture-db.php`, `json-checks.php`, `run_checks.py` and `prepare_review.py`;
- the three result JSONs;
- the report;
- the r13 verdict and closure, and the review rule.

**What I did not read:** the template `probe.php`, `runtime_admission.php`, the guard source and the payloads. They are covered only by identity. The bodies that actually execute are all rendered inside the composite PHP, and I reviewed them there.

---

## What is correct

**Web and SAPI refusal**
- A non-CLI request returns 403 with an empty body before anything else runs.
- The PHP 8 check, the Linux check and the location check (one level above `public_html`, cwd `public_html`) all run before config or DB access.

**Config handling**
- The bounded literal grammar is reused and never evaluated.
- It requires a non-persistent `mysqli` target, the same DB target for catalog and admin, and the expected path mapping.
- Credentials are used only to construct `mysqli` and are never printed or stored.

**SQL**
- There is one prepared SELECT. The derived `LIMIT 2` plus the outer `LIMIT 33` refuses duplicate emails correctly in every ordering:
  - If the lower-ID account has 32 or fewer addresses, a row from the second account appears and triggers `email_matches_multiple_accounts`.
  - If it has 33 or more, the address cap triggers first.
- `LCASE` matches OpenCart's own email lookup. It costs a full table scan, which is acceptable for a one-shot read.
- The after mode binds the stored integer ID, so an email change cannot retarget the check.
- A single statement gives a consistent read, so the `inconsistent_account_read` check is a sound sanity check.
- The statement closes in `finally`, and so does the connection.

**Canonicalisation**
- Object keys are sorted. Integer keys from `get_object_vars` are cast back to strings, which loses nothing.
- Nested `{}` and `[]` are distinguished, array order is kept, and string, boolean and null are typed.
- Treating `1`, `1.0` and `-0.0` as equal is correct: the existing writer's `json_encode` produces exactly that normalisation.
- Floats beyond 2^53 and spellings that lose precision are refused by the copied raw checks.
- Duplicate keys, including escaped aliases such as `\u0078`, are refused.
- The root equivalence `''` = `null` = `[]` = `{}` matches OpenCart's `json_decode(..., true)` consumers.
- The HMAC uses a per-run random key held only in the private checkpoint. The row comparison is strict (`===`) and order-stable, because both sides go through `ksort SORT_STRING` and a JSON round trip.

**Checkpoint lifecycle**
- Before mode is exclusive: an existence pre-check plus `fopen 'xb'` under umask 077.
- Modes must be 0700 for the directory and 0600 for the file, with link and hardlink refusal.
- Expiry, source drift and DB-target drift are all checked, with the target as an HMAC.
- Before deletion, the code confirms the checkpoint still matches its hash at read time.
- CHANGED and INCONCLUSIVE keep the checkpoint. Cleanup never touches the DB and handles a corrupt checkpoint.

**Error handling**
- The error handler converts every warning into a fixed error code; `@`-suppressed warnings still reach it.
- Only `[a-z_]` codes are printed; anything else becomes `qa_check_failed`.
- Driver messages are never shown.

**Tests:** the 25 pure and 22 lifecycle cases plus the two mutants discriminate on the claims that matter. Both mutants are real.

---

## Mandatory findings

### M1. No objective link between the owner's saves and the compared rows

`UNCHANGED` is also the result when no save reached the selected rows, for example:
- the owner edited a different customer or address in admin;
- a save failed silently;
- the after phase ran too early.

On `UNCHANGED` the helper deletes its checkpoint, so none of this can be checked afterwards. For a tool whose only purpose is G2 evidence, this is a false-pass mode that owner self-reporting cannot exclude.

**Fix:**
1. Add `c.firstname, c.lastname, a.firstname, a.lastname` to the same single SELECT.
2. Store a keyed HMAC per row as `name` in the checkpoint. Never store raw names. Validate it as 64-character hex.
3. Exclude `name` from the preservation comparison (`$same`).
4. Print only `customer_name_changed=yes|no` and `address_rows_name_changed=N`.
5. Return `UNCHANGED` (and clean up) only if the custom_field set is identical, `existing_keys>0`, the customer name changed and at least one address name changed.
6. Otherwise return `INCONCLUSIVE_SAVE_NOT_DETECTED`, keep the checkpoint and exit 2.

**Tests required:**
- names unchanged with custom_field identical gives INCONCLUSIVE and keeps the checkpoint;
- names changed gives UNCHANGED;
- name canaries are absent from output and checkpoint;
- a mutant that removes the name gate must fail the suite.

### M2. Hidden entry is not enforced by the helper

If `--before` is run directly instead of through the documented block, `stream_get_contents(STDIN)` reads from the terminal with echo on.
- The email is then visible on screen.
- The owner is told to return "terminal output", so the email could leak into chat.

**Fix:** before reading, add `if (stream_isatty(STDIN)) acc008_fail('hidden_stdin_pipe_required');`. `stream_isatty` exists in PHP 8.0.

**Test:** test it where feasible. Otherwise document it as a native-only check and keep the check order before any read.

### M3. The fetch loop can end early without refusing

The post-loop check `if($fetch===false||$st->errno)` does not catch any other non-`null` return, such as `MYSQLI_DATA_TRUNCATED` under libmysql. In that case a partial row set would be accepted.
- With mysqlnd this cannot happen, but the check should not rely on that.

**Fix:** use `if($fetch!==null||$st->errno)`.

**Test:** add a fixture case where `fetch` returns `101` and the helper must refuse.

After the fixes:
- Rebuild the artifact with a new SHA.
- Update the report flow and the expected outputs.
- Send it in one fresh coherent round.

---

## Optional findings (fold into the same round if convenient; no round of their own)

1. **Error codes containing `:` or `/` are lost.** Codes such as `hosting_config_incomplete:admin` and `runtime_path_mapping_changed:…` collapse to `qa_check_failed`. Allowing `[a-z_:/A-Z]` in the safe-code regex would keep the diagnosis.
2. **Ownership is not checked.** Adding `fileowner(...) === posix_geteuid()` (where available) for the private directory and file would be defence in depth.
3. **The after and cleanup snippets can close the SSH session.** `cd … || exit` runs in the interactive shell, so a failed `cd` logs the owner out. Wrap both snippets in `( … )`, like the before block.
4. **The empty task directory is left behind** on success and on cleanup. An `rmdir` when it is empty would tidy this up; it is not a safety issue.
5. **The "cleanup without DB" test is weak.** The `query-shape.txt` it inspects was written by the earlier before phase. Delete the marker files before cleanup and assert they are absent afterwards. The code itself is correct by inspection: the cleanup branch returns before source checks, config admission and DB access.
6. **CLI and web PHP settings can differ.** A different `serialize_precision` in the CLI `php.ini` could cause numeric refusals that the web guard would not make. This fails closed. Note it as an evidence limit.
7. **A read-only transaction would add depth.** `START TRANSACTION READ ONLY` would add defence in depth, at the cost of the "single statement" wording. It is not needed; the code holds no write statement.
8. **More mutants would strengthen the suite.** Candidates: removing the row cap, removing the duplicate-account check, and making the tracked-digest preservation always true.

---

## Evidence limits (unchanged by any repair)

**Not proven locally:**
- native MySQL/MariaDB execution of the SQL;
- Linux 0700/0600 modes and nlink behaviour;
- hosting PHP 8.0;
- mysqlnd behaviour;
- email collation matching.

**What a passing result will and will not show:**
- A pass covers only keys that already exist on the selected account's rows, within that time window and for the forms the owner reports saving.
- A service key with `present_rows=0` has no coverage. Nothing may be seeded.
- No universal account coverage, NP variants, authorisation, `customer_activity` or shared checkout/First15 writers are shown.
- Even after M1, name changes show that the rows were rewritten. They cannot show *which* of the four forms did it; that still rests on the owner's report.
- This verdict is CODE review only, not a host result.

**Gates that stay in place:**
- The r13 owner apply gate is still pending; its receipt has not been received.
- G2 stays open.
- WP3 stays closed.

---

## Owner gate (for the repaired artifact only; new SHA)

**0. Preconditions**
- Decide on r13 first: either complete its reviewed apply and return the receipt, or postpone it until this check is fully finished.
- Install nothing between the phases.
- Finish within 24 hours.
- Use the same own or test account as the earlier QA.
- No checkout, address add/delete or custom-field edits during the window.

**1. Before phase**
- Run the reviewed subshell block: SHA check, `php -l`, hidden `read -s`, then pipe into `--before`.
- Expected output includes `source_variant=r11|profile_r13`, `config_admission=…`, `db_access=SELECT_ONLY`, `private_checkpoint=saved_outside_public_html`, `address_rows=N`, `before=ok` and `done=ok`.
- If any `error=` line appears, stop and return that output only. Never re-run blindly over a baseline.

**2. Saves (exactly four, names only, same account and same saved address)**
1. Storefront profile.
2. Storefront address.
3. Admin customer.
4. Admin address.

Make the **final** first and last names differ from the originals, for example with a suffix. Restore them only after the after phase. Wait until each save has completed.

**3. After phase**
- Run the reviewed after block in a subshell: SHA check, then `--after`.

**4. How to read the result**
- **`result=UNCHANGED` + `existing_keys>0` + `customer_name_changed=yes` + `address_rows_name_changed≥1`, with `cleanup=ok` and `done=ok`:** this is evidence for G2 assessment, limited to the `service_key …:present_rows/preserved_rows` that are actually present.
- **`CHANGED`:** keep the checkpoint. Return the output and do not run cleanup until the assessment is done.
- **`INCONCLUSIVE_*`:** return the output. Run cleanup only after the assessment.
- **Any `error=`:** stop and return the output.

**5. What to return**
- Both console outputs, plus a statement of which of the four forms were saved.
- Do not return the email, IDs, the checkpoint, JSON, values, admin URLs or screenshots with personal data.
- Cancelling with `--cleanup` is not QA acceptance.
