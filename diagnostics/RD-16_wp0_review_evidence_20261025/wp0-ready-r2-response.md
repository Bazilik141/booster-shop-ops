# RD-16 WP0 R2 Review: Collector and Owner-Pull Packet

## Verdict: Review OK

R1 mandatory issues M1, M2 and M3 are closed by source analysis. I found no new mandatory issue.

### Bound identities

These hashes are taken from the package. I did not recompute them.

| Item | Path | SHA-256 |
|---|---|---|
| Collector | `diagnostics/RD-16_wp0_collect_20261025.sh` | `00f14db7e815f4dd0bb9dad5e7f05e7335af1474223a0f0a1536028bd0b421d6` |
| Owner packet | `diagnostics/RD-16_wp0_owner-pull_20261025.ps1` | `0ff72f1729a7575e5eb9c6402a1c8f1e6d7e61005157efd68bbc660de3e37be4` |
| Transport fixture | `work/rd16-wp0-20261025/check-transport.ps1` | `05df460dbf062aff2eab15248959d013d2296404844fc86fd479e84d9a95264c` |
| Metadata lint copy | `work/rd16-wp0-20261025/metadata-lint.php` | `acfa08cf6c09e09707503e0e94b81bc1b89147664f8e033f1fec40f559397db0` |
| Report | `diagnostics/RD-16_wp0_source-request_report_20261025.md` | `ec60c4d3a89b10a093c11a599bea888c71001abd656351aea1844b87bca86741` |

- The packet's `$CollectorSha` equals the collector identity above.
- The report's executable identities match both executables.
- The metadata lint copy matches the PHP heredoc in the collector, by my reading.

### Launcher adoption

I explicitly adopt the exact supplied launcher as my narrow-pull instruction. It is bound to packet `0ff72f17…7b37e4` and collector `00f14db7…0b421d6`:

```powershell
& 'C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops\diagnostics\RD-16_wp0_owner-pull_20261025.ps1'
```

Conditions for that adoption:
- The owner runs it once.
- There is no retry on any `STOP`; results and artifacts are sent as-is.
- Codex checks delivery.
- Any byte change to either file voids this adoption.

### Limits

This is static review only. The fixture results quoted in the package are Codex's evidence. I make no claim about host, MySQL, PHP 8.0, OpenSSH or Windows PowerShell 5.1 runtime behaviour.

---

## R1 closure

### M1 — Exact stdin transport: closed

`Invoke-Rd16LfProcess` writes the LF-only UTF-8 bytes to `StandardInput.BaseStream` and then closes stdin. PowerShell's native-pipeline terminator is never involved. The supporting details are sound:

- **No deadlock.** Stdout is read with `ReadToEndAsync` before stdin is written.
- **No argument injection.** Each native argument is checked against `^[A-Za-z0-9_=/.-]+$` before the space join. All fixed options, `booster`, `bash` and `-s` pass, including `IdentityAgent=//./pipe/openssh-ssh-agent`.
- **Clean return value.** Every call inside the function is void or `[void]`, so it returns a single `pscustomobject`. That matters under `StrictMode` property access.
- **Failed sends are visible.** A failed stdin send forces a non-zero code while the captured stdout is kept.
- **Remote side.** The collector has no command that reads the `bash -s` stdin. The PHP calls read here-docs, `php -r` does not read stdin, and `tar`, `sha256sum` and `grep` take file arguments.
- **Trailing line.** The cleanup here-string ends without a final LF. Bash still executes an unterminated last line at EOF.
- **Fixture coverage.** The fixture runs the same function, extracted by AST, against real Bash. It covers LF and CRLF, the exact receipt regex, cleanup, and absence of both target files afterwards.

### M2 — Cleanup EOL guards: closed

- **Normalization order.** `$Cleanup` is normalized after placeholder substitution, and a remaining CR is refused. The helper refuses CR again before launch, so there are two independent guards.
- **Placeholders.** They are filled only with `[a-f0-9]{64}` regex captures.
- **Gates under errexit.** The `cd`, `[[ ]]` and hash gates are top-level or loop-body statements, so `set -e` applies to them.
- **Deletion paths.** `rm` uses exact absolute paths and runs only after the symlink, regular-file and both hash gates pass.
- **CRLF case.** It is covered by a CRLF packet copy in the fixture.
- **Collector EOL.** A CRLF checkout of the collector would fail the pinned-hash check, so that case fails closed before any connection.

### M3 — Raw receipt retention: closed

- `owner-receipt-raw.txt` is written before the exit-code check and before the regex.
- `cleanup-receipt-raw.txt` is likewise written before its checks.
- The fixture shows the stdout of the exit-7 case being preserved.

### R1 optional items adopted

I checked each adopted item:
- `require './config.php'`;
- the initialized `layout_modules` key;
- `$s->errno` checked after each fetch loop;
- theme counts added for `column_left`, `footer` and the account module;
- a single collector byte read used for both hashing and decoding;
- the cache path labelled as derived.

All are correct. Bind-by-reference with `foreach ... as $route` / `$layout` correctly updates the bound parameter. The read-only SQL acceptance from R1 still holds.

---

## Optional notes (not closure conditions)

1. **Possible BOM on stdin under Windows PowerShell 5.1.**
   - In .NET Framework, `Process.Start` wraps redirected stdin in a `StreamWriter` using `Console.InputEncoding` and sets `AutoFlush`. If the owner's console code page is 65001, this may emit a UTF-8 BOM before your bytes.
   - The effect looks benign. Bash would print `command not found` for line 1 (the shebang in the collector, `set +x` in cleanup) before errexit is active, and execution would continue.
   - The fixture does not state whether it ran under Windows PowerShell 5.1 (.NET Framework) or pwsh 7. Record which host ran it.

2. **Stderr visibility.**
   - Because `CreateNoWindow=$true` and stderr is not redirected, the remote `STOP=` codes depend on handle inheritance to reach the owner's console.
   - If they do not appear, a failure is still fail-closed, but the code is lost.
   - Consider redirecting stderr asynchronously to `$Drop\*-stderr-raw.txt`. It carries only `STOP=` codes and generic bash or ssh errors.

3. **Report encoding.** The report headings contain mojibake (`РІР‚вЂќ`), an em dash double-decoded. This is documentation only.

4. **Secret screen is still case-insensitive.**
   - R1 optional item 1 was not adopted. A false positive would be fail-closed.
   - However, the local drop is created first, so a false stop would cost a revision cycle.

5. **Existing private directories.** When `~/private` or `~/private/booster-pulls` already exists, its mode is not checked. `umask 077` only covers new artifacts. Consider adding `stat -c %a` checks or recording the mode.

6. **What the fixture does not cover.** It does not exercise:
   - the packet's top-level hash, decode and drop-directory path;
   - `scp`;
   - the drive-letter local path given to Windows `scp`, which has the same pattern as earlier owner packets.

   These remain unproven until the owner runs the packet.

7. **Cosmetic indentation.** The packet's post-receipt block is indented as if nested, but it is not. There is no behavioural effect.

8. **Unchecked rollback.** `$db->rollback()`'s return value is unchecked. The transaction is read-only and only SELECTs run, so this is harmless.

---

## Not claimed

- No host, DB or transport runtime proof.
- No currency of any ACC-009 after-hash.
- No shell-mechanism decision; that remains at G0.
- Nothing in WP1.
- WP0/G0 and the WP2 gates stand unchanged.
