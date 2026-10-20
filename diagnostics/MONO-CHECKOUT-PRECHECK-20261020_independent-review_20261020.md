READY FOR OWNER-RUN

patch_sha256: `855eadfa570db3c7942e2bfd6c4cbdf7b87c601560d34344a73a6dac48ecfc32` (size 16318)
packet_sha256: `e9027e3e8b57a73da9c0b0cb9c08a5366a7e2d2941e39fa5363b2e0a9aabcd16`

This verdict applies only to these two byte-sets. Any byte change voids it. I recomputed no hashes and executed nothing. The binding is to the declared values. They are consistent across the frozen-dependency list, `patch-identity.json`, the runner `MF_SPECS`, the packet `PatchSha/PatchSize/PSHA/PSIZE`, `T1B/T1A/T2B/T2A/D1S/D2S`, and `owner-packet-local-results.json`.

---

## Mandatory findings

None.

## Basis (what I checked)

### Source edits

I decoded the base64 anchors and replacements. They match the unified diffs exactly, and both anchors and inserts are pure ASCII.

**`confirm.php`: `$order_id = 0;` added inside the existing missing-row block**
- Native `getOrder()` uses an unfiltered `WHERE order_id = (int)` lookup and returns `[]` only when the row is absent. So the reset fires only when the row really does not exist. There are no hidden status, store or customer filters.
- The write still requires `$status && $allow_order_write`. Read-only previews, invalid checkouts and the guest-oferta gate stay write-free.
- An existing draft still goes through `editOrder`, and a confirmed order stays untouched.
- `addOrder` sets the session `order_id` before the payment controller renders. As a result:
  - Mono `index()` builds `waiting_json` from the new ID instead of 0.
  - Mono and PUMB `confirm` see a real order.
  - Ordinary (non-credit) payment confirms get the same benefit.
- No new duplicate-order path is introduced, because the old row is absent.

**`credit_confirm.twig`: `idleLabel` captured at script-evaluation time**
- jQuery 3.7.1 `.html()` with `<script>` content goes through `domManip`, which evaluates inline scripts synchronously. The capture therefore happens before the caller's `.addClass('loading').text(...)`.
- The restore runs only in the `.done` non-redirect branch. These paths are unchanged:
  - the redirect path,
  - the `.fail` path that redirects to the waiting URL,
  - the `busy` guard,
  - the server-side duplicate and transaction guards.
- No automatic POST retry was added.
- No Twig delimiters were added, so the absence of Twig compilation proof is acceptable for these additions.
- Marker count is exactly 1 in each after-file, consistent with `mf_classify`.

### Runner

- **Fail-closed classification:** each target must exactly match the before-pin with no marker, or the after-pin with exactly one marker. Anything else stops.
  - Mixed states stop.
  - Anchor and replacement counts must both be exactly 1.
  - The candidate hash must equal the after-pin.
  - `php -l` runs on the source, the candidate, the temporary file and the installed file.
- **Write ordering:**
  - Backups are taken before any write. They hold both before-files, a manifest with mode/uid/gid, and an exact recovery runner copy, all created with `0600` permissions in a `0700` directory.
  - Writes are atomic same-directory renames, with metadata restored and verified.
  - Drift is rechecked per target, both before the apply loop and during it.
- **Rollback:** admission covers all targets and every needed backup before any restore.
  - "Before" targets need no backup, so partial application is handled.
  - It does not require both targets to be "after".
  - A drifted target blocks every restore.
  - The concurrent-drift recheck runs per restore.
- **Apply-failure catch:** the catch block reuses the same `mf_restore`. The test exercised it through a declared injected copy. The delivered bytes contain no test switch.
- **Self-delete:** runs only after success or on the idempotent no-op path.
- **Web exposure:** a non-CLI SAPI request returns 404 before any action.
- **PHP 8.0 compatibility:** the syntax is PHP 8.0-compatible. There is no `never`, `enum`, `readonly` or first-class-callable syntax.

### Packet

**Local guards**
- Requires exact Windows PowerShell 5.1 Desktop, `ConsoleHost`, 64-bit, the System32 `powershell.exe`, and the pinned working directory.
- The repo, patch, provenance and packet paths must be free of reparse points.
- The patch size and hash are re-asserted before `scp`.

**Native argument handling**
- `ConvertTo-WinArg` implements the standard CommandLineToArgv backslash and quote rules. It was tested against 8 samples.
- The remote command contains only `[A-Za-z0-9+/=]` plus fixed text, so remote shell quoting is trivially safe.
- The whole script travels as base64 and runs under `env -u BASH_ENV ... bash --noprofile --norc -s`.
- `main </dev/null` runs at the end, so a truncated script never executes `main`.

**Remote guards**
- The home, root, private and staging chains must be plain directories.
- Staging must be `0700` and owned by the user. The staged runner must match the exact SHA and size and have mode `0600`.
- The PHP CLI must be `/usr/local/bin/php` version 8.0.x.
- `config.php` constants must match through a bounded, output-buffered `-r` probe.
- The template cache directory must be symlink-free.
- Both dependency pins (`checkout.twig`, `order.php`) are re-admitted.

**Sequencing**
- Template-cache cleanup is chained with `&&` after verified runner success (`done`, `self_delete`, staged file absent) on Apply, and after `rollback_ok` on Rollback.
- Post-hashes are checked afterwards: `after_both` for Apply, `before_both` for Rollback.
- Rollback deliberately skips the dependency pins.
- The rollback runner is copied with `set -C` from the backup, admitted by exact hash, and removed only after success.

**Receipt handling**
- The receipt parser is strict:
  - It requires the BEGIN/END envelope.
  - It handles CRLF, and a lone CR stops.
  - Unknown or duplicate fixed keys stop.
  - Runner lines are allowlisted.
  - Trailing output stops.
  - It enforces `remote_result` and the exit code.
- Every emitted fixed key is in `$fixedKeys`, and every runner key is in `ReceiptRe`.
- No key is emitted twice within a body.
- Valid receipt lines, including `backup_dir`, are printed incrementally, so they survive a later STOP.
- No stderr is printed, and no secrets appear in output.

## Optional findings (non-blocking)

- **O1 – backups in the webroot.** `_patch_backups` sits inside `public_html` and holds `recovery_runner.php` (which returns 404 to any non-CLI request) and source copies.
  - A direct web request to the backup `confirm.php` would hit a class-not-found fatal error. If production `display_errors` is on, that could expose a path.
  - This is the existing convention. A deny rule would be hardening.
- **O2 – no cache-only action.** If cache cleanup fails after runner success, the packet STOPs. Recovery is Upload, then Apply (the idempotent `after` path re-runs the cleanup). The packet header does not document this path.
- **O3 – remote reason can be masked.** `remote_or_runner_line_rejected` is checked before the remote STOP reason, so it can hide the specific remote reason. This affects diagnostics only.
- **O4 – cleanup scope.** Cache cleanup deletes all compiled files under the admitted `template/` directory, not only `credit_confirm`. "Exact" here means the exact directory. This is acceptable, since Twig cache names are hashed.
- **O5 – minor lint cleanup gap.** `mf_lint` does not close the process when `fwrite` fails part-way. The impact is minor and fail-closed.
- **O6 – review-input encoding only.** The Cyrillic text in the supplied twig, checkout and Mono excerpts was displayed as mojibake in this input. The UI test's exact Ukrainian label assertion passed, which implies correct UTF-8 bytes on disk. The patch anchors are ASCII. There is no effect on the patch.

## Acceptance limits

- This is preparation certification only. It is not proof of deployment, host runtime, bank behaviour, or the customer's root cause.
- Local runs used PHP 8.3.30 on Windows. Not proven:
  - Linux ownership and `chown` paths,
  - PHP 8.0 CLI behaviour, `proc_open` availability, and the FPM version,
  - opcache revalidation and Twig auto-reload,
  - theme overrides and the production jQuery version.
- If `proc_open` is disabled, the HOME/`home2` chain contains a symlink, or the staging directory is missing, Verify should STOP before any writes. That is fail-closed, not a defect.
- The customer's exact session state is unknown. Admin-list absence proves nothing.

## Owner action

1. **Check the packet hash.** In a normal 64-bit `powershell.exe` 5.1 console (not ISE), set the location to exactly `C:\Users\14bez\Downloads\Booster Shop\booster-shop-ops`. Run `Get-FileHash` on the packet and confirm `e9027e3e…aabcd16`. If it differs, stop. The packet itself checks the patch hash.
2. **Upload, then Verify.** Run `-Action Upload`, then `-Action Verify`.
   - Expect `verify_admission=before`.
   - `after` means the patch is already applied.
   - Report any STOP and do not create directories ad hoc.
3. **Apply.** Run `-Action Apply` and record the `backup_dir=` line. If you get a STOP, report the full output, do not rerun blindly, and do no manual edits.
   - Exception: for a `template_cache_cleanup_failed_after_runner_success` STOP, the recovery is Upload then Apply, but only after review.
4. **Manual QA.** Test mobile and desktop:
   - an ordinary checkout,
   - Mono create through to the bank handoff,
   - PUMB, if it is public or in preview.
   
   Confirm that a definite error restores the original label and that no duplicate orders appear.
5. **Rollback, if needed.** Run `-Action Rollback -BackupDir <exact backup_dir>`. It accepts partial application.

The key stays loaded per your decision. No key, database or customer-data step is requested.