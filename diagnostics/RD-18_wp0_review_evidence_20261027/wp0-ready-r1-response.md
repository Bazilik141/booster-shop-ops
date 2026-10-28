# RD-18 WP0 source-collection review

**Verdict: Review OK** for the source-only collection gate.

The verdict binds these two declared hashes:

| Artifact | SHA256 |
|---|---|
| Collector `diagnostics/RD-18_wp0_collect_20261027.sh` | `18348636391bc60acc48f2556f135d1e3663264355b999592d17117744207744` |
| Owner packet `diagnostics/RD-18_wp0_owner-pull_20261027.ps1` | `fe60983a3c2765b3005dfb14ea3c86217f4858630590b94488918251fc6a74b8` |

I did not hash any bytes myself. I confirmed that the packet's embedded `$CollectorSha` literal equals the declared collector hash. The verdict covers the supplied text under those two identities only. Any byte change voids it for the changed scope.

Scope: the owner may run one source/metadata pull, then the task stops at G0. This grants no implementation, deployment, QA, Git, Notion or dashboard authority.

## What I checked

**Comparison with the reviewed RD-17 pair.** I compared RD-18 line by line against the RD-17 pair (`6559ddce…` / `7b799c40…`, Review OK under `a787f5b4…`). The only differences are:
- the variable prefix, task ID and artifact names;
- the 30-path allowlist and the receipt regex `source_files=30`;
- the drop path, the function name `Invoke-Rd18LfProcess` and the final banner;
- the metadata route lists:
  - theme routes: `account/order`, `order_list`, `order_info`, `order_history`, `shell`, `account/account`, `common/header`, `common/footer`, `common/pagination`;
  - layout candidates: `account/order`, `account/order.info`.

None of the transport, guard, SQL or cleanup control flow changed.

**Allowlist.** The 30 entries match `source-list.json` and the report table. They contain no `config.php`, `.env`, `DIR_STORAGE`, log, session, upload or dump path. All are fixed literals: no glob or directory.

**Collector guards.**
- Webroot and private directories: no symlink, and `pwd -P` must equal the expected path.
- Each path is walked component by component with `-L`, and each file must be a regular file.
- Size caps: 0 < size ≤ 2 MiB per file, 8 MiB in total.
- The obvious-secret grep correctly separates exit 1 (no match) from other failures.
- Source hashes are taken before and after the archive is written, and must be equal.
- Each archive member is re-hashed against the ordered pre-hash list, and the count is checked.
- `set -C` plus the pre-existence check gives exclusive creation and no replay.
- No command reads the stdin of `bash -s` except the PHP heredoc.

**Metadata PHP.**
- Requires CLI and PHP 8.0, and refuses a linked config.
- Validates the `DB_PREFIX` charset and passes `DB_PORT` as an int.
- Checks schema columns before querying.
- Runs a read-only transaction with prepared statements and `bind_result` (no mysqlnd-only API).
- Output is bounded: at most 100 rows per route and 32 layout IDs, with charset and position regexes. Output shape violations fail closed.
- On failure it prints only a generic error.
- Output is counts, store/layout IDs, route patterns, module codes and the `DIR_CACHE` path. It contains no business, customer or order rows, and no DB template bodies.

**Owner packet.**
- Checks the collector hash and refuses an existing local drop directory.
- Sends the body as LF bytes and rejects any remaining CR.
- Allows only a safe character set in native arguments.
- Uses strict host checking, batch mode and the agent pipe, with no forwarding.
- Saves raw stdout before branching on the exit code.
- Validates the exact receipt regex.
- Downloads both files, then checks both hashes against the receipt and the manifest's `archive_sha256` and `done=ok`.
- Cleanup removes only the two named regular files, after a symlink, path and per-file hash guard. It uses no glob or recursion.
- Every failure path preserves evidence and says not to retry.

## Mandatory findings

None.

## Optional findings (recorded, not blocking)

- **O1. Theme route form.** Before interpreting zero counts as "no override", confirm the fresh `event/theme.php` and `model/design/theme.php` route form, as in RD-17 O1. `account/order` is a controller route, so its count is informational only.
- **O2. Consumed attempt.** Any failure after the local drop directory is created consumes r1, including `required_source_missing` (for example if `order_history.twig` is absent) and SSH or SCP failure. Recovery is a new reviewed r2 identity after targeted assessment, never a manual deletion.
- **O3. ACC-007 side dependencies.** If the fresh `acc007_order_status.php` reads its map from a DB table, or from a language or include file outside the allowlist, G0 should name that missing fact as one supplement. Do not infer the D2 group mapping without it.
- **O4. Theme counts for legacy-menu templates.** No counts are taken for `common/column_left` or `extension/opencart/module/account`. This is acceptable because WP1 suppresses the menu at callers. Note the gap if G0 relies on template-level state.
- **O5. English language fallback.** The `en-gb` order language file is not collected. Collect it only if G0 needs fallback-key evidence.
- **O6. Inherited cosmetic and transport limits.** The orphan indentation after `$ReceiptText` and the possible BOM in the Framework are carried over unchanged from RD-17. They are harmless.

## Evidence limits

- **Not executed by me.** I ran nothing, did not re-hash files and did not inspect boards or screenshots.
- **Author-reported checks.** The following are reported by the author, not verified by me:
  - Bash fixtures with 30 synthetic sources and fake PHP (success, missing, drift and existing-artifact cases);
  - PS 5.1 AST checks and the extracted LF/CRLF, receipt, cleanup, failure and bare-CR tests;
  - native PHP 8.0.30 lint;
  - the default-sandbox MSYS IPC block followed by a scoped fixture pass.
- **Never exercised:** the real host PHP, MySQL, SSH, SCP, keys or production. The new route filters have not run against live `layout_route` data.
- **RD-17 baseline.** The owner-executed RD-17 WP0 receipt is not in this packet. My reliance on it is limited to the RD-17 review closure and the user's statement.
- **Scope of the counts.** Read-only theme counts do not establish event or OCMOD absence or the effective runtime.
- **Secret screen.** It is an obvious-signature screen only. Local intake must screen the pulled source before any further disclosure.
- **No current-state claim.** A full expected receipt closes mechanically. Drift against the WP1C DS/header and the RD-16 shell expected hashes must then be assessed at G0, not auto-accepted or auto-replaced.
