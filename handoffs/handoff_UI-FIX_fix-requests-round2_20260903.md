# Claude Code Handoff — UI-FIX batch: round-2 fix request (2026-09-03)

Executor: Claude Code. Response to the round-1 fix addendum in
`diagnostics/UI-FIX_mobile-desktop-polish-batch_report_20260902.md`.

**One finding, blocking, scope limited to the two DB patches.** The two file
patches are cleared and are being deployed separately — do not touch them.

## Round-1 fixes: verified, closed

Re-checked on disk, not taken on report:

- **F3** — `grep "): never"` returns nothing across all four patches; all 5
  signatures now plain. Independently re-scanned with a `token_get_all` scan for
  `never`/`enum`/`readonly`/`final const`/first-class-callable/`0o`/intersection
  types/8.1+ functions, canary-validated against a file containing all eight:
  all four patches clean. `php -l` on 8.4: clean.
- **F1** — the `fail('information_row_unexpected_content:...')` branch is in
  place at `UI-FIX_cms-content_20260903.php:279`. The corrupted-row test is the
  right test and the right result.
- **F4** — FAQ `$items` array replaced verbatim; all four questions and all four
  hrefs present.
- **F5** — row 5 of the deviations table, with the LCP rationale. Correct place.

## The finding — `get_result()` / `fetch_all()` are fatal on this host

**Blocking for `UI-FIX_cms-content_20260903.php` and
`UI-FIX_price-discount-rows_20260903.php`.**

The round-1 addendum concluded "mysqlnd is present. No change needed," reasoning
that `LEGAL-002_offer_mono_pumb_archive_20260724` has a `_patch_backups`
directory on production, therefore it executed there, therefore `get_result()`
worked. **That inference does not hold, and the conclusion is the opposite of
what this repo's own evidence says.**

What the LEGAL-002 reports actually record:

- `diagnostics/LEGAL-002_offer_mono_pumb_archive_v3_report_20260724.md`:
  "the host PHP build has no `mysqlnd`, so `mysqli_stmt::get_result()` is
  unavailable. V3 replaces result reads with metadata + `bind_result()`."
- `diagnostics/LEGAL-002_offer_mono_pumb_archive_v4_report_20260724.md`:
  "V2 failed before transaction due to `get_result()`; diagnostic confirmed a
  PHP mysqli build that also lacks `fetch_all()`."

Why v1 still produced a backup directory without disproving that: v1 reads its
schema through `$db->query('SHOW TABLES ...')` and `$db->query('SHOW COLUMNS ...')`
at lines 43 and 47 — plain `mysqli::query()`, which does **not** need mysqlnd —
and stopped at the column guard on line 475 (`ocp5_information.bottom` missing).
Its first prepared-statement read, the one using `get_result()`, is at line 408,
inside a function called later. v1 started, wrote backups, and stopped before
ever reaching it. A backup directory proves the patch started, not that it
reached the line in question.

Only the prepared-statement result path needs mysqlnd. That is exactly the path
both 2026-09-03 DB patches use:

| File | Line | Call |
|---|---|---|
| `patches/UI-FIX_cms-content_20260903.php` | 134 | `$result = $statement->get_result();` |
| `patches/UI-FIX_cms-content_20260903.php` | 136 | `$out = $result->fetch_all(MYSQLI_ASSOC);` |
| `patches/UI-FIX_price-discount-rows_20260903.php` | 133 | `$result = $statement->get_result();` |
| `patches/UI-FIX_price-discount-rows_20260903.php` | 135 | `$out = $result->fetch_all(MYSQLI_ASSOC);` |

Both are inside the shared `rows()` helper. `execute()` (the write helper) does
not use either call and needs no change.

**Consequence, stated precisely.** The first `rows()` call in each patch is the
table probe — `UI-FIX_cms-content_20260903.php:247` and
`UI-FIX_price-discount-rows_20260903.php:181`, both `SELECT 1 FROM <table> LIMIT 0`
— and both sit well before `begin_transaction()` (lines 397 and 292). So the
patch dies on its very first read, before any write, exactly as LEGAL-002 v2 did.
Nothing is corrupted; the patch is simply dead on arrival, with a bare PHP fatal
instead of any of its own messaging. This is invisible to `php -l` and invisible
to any local run, because local PHP has mysqlnd.

## The fix

Replace the `get_result()` + `fetch_all()` body of `rows()` in **both** patches
with a `result_metadata()` + `bind_result()` read. The proven implementation is
already in this repo — `bs_stmt_rows()` in
`patches/LEGAL-002_offer_mono_pumb_archive_v4_20260724.php`, lines 406-416, which
ran to completion on this host:

```php
$metadata = $stmt->result_metadata();
if ($metadata === false) bs_fail('Cannot read SQL result metadata');
$row = []; $refs = [];
foreach ($metadata->fetch_fields() as $field) { $row[$field->name] = null; $refs[] = &$row[$field->name]; }
if (!call_user_func_array([$stmt, 'bind_result'], $refs)) bs_fail('Cannot bind SQL result columns');
$rows = [];
while ($stmt->fetch()) { $copy = []; foreach ($row as $key => $value) $copy[$key] = $value; $rows[] = $copy; }
$metadata->free();
return $rows;
```

Keep `rows()`'s existing signature and return shape — a list of associative
arrays keyed by column name — so no caller changes. Two things that bite here:

- `bind_result()` binds **by reference**; the per-iteration `$copy` is required.
  Appending `$row` directly gives N identical rows.
- Preserve the existing `need()` guards around `prepare`/`bind_param`/`execute`,
  and keep failure messages in the same format the rest of the patch uses.

Do not change any query, any planning logic, any assertion, or anything the
round-1 review already cleared. This is a read-helper swap and nothing else.

## Verification

The host condition cannot be reproduced locally — local PHP has mysqlnd, so a
local run passes either way. So:

1. `grep -n "get_result\|fetch_all\|mysqli_fetch_all"` returns nothing in either
   patch.
2. Re-run the same local fixture as round 1 and diff the results against the
   round-1 run: same planned rows, same counts, same dry-run output, same
   `already_applied=yes` on repeat, same `restore.sql`. The rewrite must be
   behaviour-identical.
3. Re-run the F1 corrupted-row test — the new read path must still reach the
   `information_row_unexpected_content` failure.
4. `php -l` on both.

The owner gates the run with a one-line host check before either patch is
executed, so a wrong assumption here stops at his terminal rather than on the
site.

## Your open question — the 8.1+ scanner

Put it in `scripts/`. That directory already holds exactly this kind of tool
(`check-3dp-catalog-contract.mjs`, `bs-wave-verify-20260828.sh`,
`PAY-002_final-preflight_20260831.php`), so it needs no new convention, and this
class of error has now reached review three times.

One caveat worth building in: a syntax scanner would not have caught today's
blocker. `get_result()` is valid PHP 8.0 syntax — it fails on a **host
capability**, not a language version. If you add the scanner, give it a second
list for host constraints: `get_result`, `fetch_all`, `mysqli_fetch_all`, and
anything else this host is known to lack. The two constraints now live in
project memory as `project-production-php-80` and `project-production-no-mysqlnd`.

---
Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01LiZyzyoCvT5guWsBVPtTgf
