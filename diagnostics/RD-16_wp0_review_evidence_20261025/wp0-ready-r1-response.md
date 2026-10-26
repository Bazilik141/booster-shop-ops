# RD-16 WP0 Review: Source Collector and Owner Pull Packet

## Verdict: Changes requested

**Reviewed identities.** These hashes are taken from the package; I did not recompute them.
- Collector `diagnostics/RD-16_wp0_collect_20261025.sh`: SHA-256 `1a857cd02714686204e16749117a35640da868a79fc7420c3cac75b5bde99f6c`.
- Owner packet `diagnostics/RD-16_wp0_owner-pull_20261025.ps1`: SHA-256 `c86c9ad422cc59c8c9725e25858e88b4251b3b9f329fb4a53d913bf50857b547`.
- The packet's `$CollectorSha` matches the collector identity above.

**Launcher adoption is withheld.** I do not adopt the supplied launcher as an owner-run pull instruction. The owner should not run it until the mandatory items below are closed and a fresh pair of hashes is reviewed.

**Limits of this review.** It is static source analysis only. The mocked PHP fixtures give no host, DB, PHP 8.0, MySQL, OpenSSH or PowerShell runtime proof, and none is claimed.

---

## Mandatory issues

### M1 — PowerShell appends a line terminator to piped stdin, so the success path can end non-zero

**Scope:** packet lines `$Body | & $Ssh ... 'bash -s'` and `$Cleanup | & $Ssh ... 'bash -s'`.

**The problem**
- Windows PowerShell writes each pipeline string to a native command's stdin followed by `Environment.NewLine`, which is CRLF on Windows. This is the familiar `'x' | git hash-object --stdin` gotcha.
- The collector body is LF-normalized, but the packet then pipes it as a string. The remote `bash -s` therefore receives a final line containing only `\r`.
- Bash runs that line as a command named `$'\r'` and gets 127 (command not found). Because `set -e` is active, the script's exit status becomes 127. This happens after the archive and manifest were created and the five-line receipt was printed.

**Consequences**
- `$LASTEXITCODE -ne 0` throws "collection failed … do not retry" on a run that actually succeeded.
- The remote artifacts and the local drop directory are left behind.
- The no-replay design then forces a full r2 cycle.
- The cleanup pipe has the same defect. A successful cleanup ends with 127 and reports "remote cleanup unresolved".

**Why the tests missed it:** the fixture ran the collector directly under Git Bash. No test exercised the PowerShell-to-native stdin path.

**Closure condition**
1. Make transmission terminator-safe by either:
   - writing the exact LF-only UTF-8 bytes to ssh's stdin with no added terminator (for example `System.Diagnostics.Process` writing to `StandardInput.BaseStream`, then closing it); or
   - ending both bash bodies with an explicit final `exit 0` line, so bash never reads an appended terminator. With `set -e`, reaching that line already implies success.
2. Add a local fixture that pipes the exact packet bodies through the same PowerShell construct into local bash, with no SSH. It must show:
   - exit 0;
   - the exact five-line receipt parsing with the real regex;
   - cleanup exit 0 against synthetic files.
3. Rebind both new hashes.

### M2 — The cleanup here-string inherits the `.ps1` file's line endings, which can disable all of its guards

**Scope:** the `$Cleanup = @' … '@` block.

**The problem**
- Unlike `$Body`, `$Cleanup` is not normalized. PowerShell here-strings keep the source file's newlines.
- The report does not state the packet's EOL, and nothing checks it at run time. A CRLF copy, for example after a Git autocrlf checkout, runs whatever bytes are on disk.
- With CRLF line endings:
  - `set -euo pipefail\r` fails, so errexit is never enabled.
  - `cd ".../booster-pulls"\r` fails.
  - The `[[ … ]]\r` guards become syntax or condition failures that do not stop execution.
  - `rm -- <archive> <manifest>\r` still runs, in `$HOME`, using relative names.
  - `printf` still prints `remote_export_cleanup=ok`.
- In short, the hash gate in front of `rm` stops being a gate. This is a deletion-safety defect, not just a reliability defect.

**Closure condition**
1. Normalize `$Cleanup` with `.Replace("`r`n","`n")` after placeholder substitution.
2. Before each send, assert that neither body contains `"`r"`; STOP otherwise.
3. Optionally, make cleanup use absolute paths (`rm -- "$HOME/private/booster-pulls/RD-16-…"`) so a failed `cd` can never redirect it.
4. Cover this with the M1 fixture, including a CRLF-saved copy of the packet as a negative case.

### M3 — "Preserve it" is impossible: failed or unexpected receipts are discarded

**Scope:** the packet's collection-failure and receipt-mismatch branches.

**The problem**
- `owner-receipt.txt` is written only after the regex succeeds.
- On non-zero exit or regex mismatch, the captured `$Receipt` is thrown away, yet the message tells the owner to "Preserve it; do not retry".
- With no-replay in force, losing the stdout (and the archive and manifest hashes in it) prevents the targeted assessment the report promises.

**Closure condition**
1. Write the raw captured stdout to `$Drop\owner-receipt-raw.txt` immediately after the ssh call, before checking exit code or regex.
2. Optionally, capture stderr to a file in the drop as well. It carries only `STOP=` codes and generic bash errors.
3. Show both in the M1 fixture.

---

## Areas checked and found acceptable (subject to M1–M3)

**Read-only SQL and privacy**
- `config.php` is required only inside owner-run PHP. Credentials stay out of argv and stdout.
- All output is buffered, and the error handler throws away messages. `display_errors=0` and `log_errors=0` mean no log writes.
- `MYSQLI_REPORT_OFF` is set explicitly, which matters because the PHP 8.0 default differs from 8.1.
- `DB_PREFIX` is regex-validated. All queries are prepared, fixed-text SELECTs.
- Selected data:
  - theme: counts only, never the override `code` body;
  - layout_route: candidate rows only, with the same `? LIKE route` semantics as OpenCart;
  - layout_module: codes and positions, capped and validated.
- No customer, order, session, payment or settings values are read.
- This fits the CODEX_WORKFLOW exception for reviewed PHP runners reading bounded non-secret metadata. It is not a direct DB CLI session.

**Schema checking:** `SHOW COLUMNS` runs before the `READ ONLY` transaction. A missing column fails closed before any artifact exists.

**PHP 8.0 compatibility**
- No mysqlnd-only APIs are used (`bind_result`/`fetch` only).
- No 8.1+ syntax is used.
- `JSON_THROW_ON_ERROR` and static closures are fine.
- Fatal errors stay silent and produce a non-zero exit, which maps to `STOP=metadata_failed`.

**Source paths**
- 34 unique paths, consistent with the OC4 layout and the handoff list.
- No config, key, log or dump paths.
- The three ACC-009 files are hard-required, which correctly gates the stale-backup premise.

**Race, hash and archive safety**
- Symlink walk on every component; size caps per file and in total.
- The `$( … ) || stop` construct correctly captures grep exit status in the else-branch.
- Pre/post `sha256sum` comparison around both metadata and tar.
- Each archive member is re-hashed by extracting it to stdout, which catches a swapped symlink or a transient change.
- `set -C` gives exclusive creation of both artifacts, and pre-existence also fails closed.
- `umask 077`.
- Both GNU and binary hash markers are handled.

**Commands that could consume `bash -s` stdin:** none. Every `read` uses a here-string or here-doc, `tar` writes rather than reads stdin, and `php -r` does not read stdin.

**PowerShell transport**
- Same option set as the reviewed ACC-009 agent mode: BatchMode, strict host-key checking, the agent pipe override of `IdentityAgent none`, no forwarding, public key only.
- Placeholders are replaced only with regex-validated hex.
- Remote deletion targets two exact names after hash re-verification, with no wildcard.
- `$OutputEncoding` is restored in `finally`.

**Failure semantics:** apart from M1–M3, every failure is a STOP without retry, and no production write path exists.

---

## Optional notes (not closure conditions)

1. **Secret screen false positives.** `grep -Ei` makes `AKIA[0-9A-Z]{16}` and `sk-…` case-insensitive. Embedded base64 in large CSS could then trigger a false stop. Matching those tokens case-sensitively keeps the screen fail-closed without the noise.
2. **Config file binding.** Use `require './config.php'` so the file checked by `is_file`/`is_link` is the one loaded, rather than one resolved through `include_path`.
3. **Truncated fetch loops.** `while ($s->fetch())` cannot tell an error from end-of-rows. Checking `$s->errno` after each loop would prevent silent truncation. Also initialize `$meta['layout_modules'] = []` so the key always exists.
4. **Theme `status` semantics (G0 interpretation).** Before relying on "zero enabled rows means no override", confirm from the pulled `catalog/model/design/theme.php` that lookups filter on `status`. If they do not, treat `rows_all_stores > 0` as a possible active override. Checking the backup copy before the run would also confirm the `status` column exists and avoid a fail-closed stop.
5. **Wider metadata (G0 gap).** The menu and shell decision may also depend on:
   - theme rows for `common/column_left`, `common/footer` and `extension/opencart/module/account`;
   - `event` rows (code, trigger, action, status) whose triggers match `view/account/%`, `controller/account/%` or `view/common/column_left%`.

   These are cheap, bounded technical additions that could be bundled into r2. Otherwise they remain G0 uncertainties after intake.
6. **Template cache path.** `template_cache_directory` is derived as `DIR_CACHE . 'template/'`. Label it as derived and confirm it against the pulled `system/library/template/twig.php` before any later cache step.
7. **Hash-then-read gap.** The packet hashes the collector and then reads the file again. Reading the bytes once, hashing them and decoding them would remove the local gap.
8. **Repository hygiene.** The drop under `work\` will hold a production-source `.tar.gz`. Confirm `work/` is ignored and excluded from autosync and owner staging. Commit policy already bans archives.
9. **Shell startup output.** Any non-interactive `.bashrc` output on the host would break the exact receipt. That fails closed, but M3 makes it diagnosable.
10. **Execution policy.** The launcher assumes a PowerShell execution policy that permits local scripts. Prior owner packets suggest it does, but this is not verified here.

---

## Not claimed

- No source or DB runtime and no host execution.
- No currency of any ACC-009 after-hash.
- No shell-mechanism decision.
- Nothing in WP1.
- The preliminary option (a) recommendation is noted but remains a G0 decision after intake.
