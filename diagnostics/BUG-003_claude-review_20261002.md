# BUG-003 — Claude review of WP1–WP3

Date: 2026-10-02 · Reviewer: Claude (chat) · Author: Claude Code
Inputs: the three patch files in `patches/`, `diagnostics/BUG-003_order-status-stock-fix_report_20261002.md`,
handoff `handoffs/handoff_BUG-003_order-status-stock-fix_20261002.md`.
Verification: all three files read in full; `php -l` on copies (PHP 8.3) — no syntax errors.

Verdict: **Deploy OK; non-blocking notes.**

## Conventions and scope

C1–C7 present in WP1 and WP2 (WP3 is read-only: no backup needed, self-deletes). WP1 lints the
candidate before touching the live file, writes setting then file, restores both on any failure,
and stores rollback SQL with the exact `setting_id`. Pre-checks cover anchor count, mixed state and
the exact old setting value. No `get_result()` / `fetch_all()`; PHP 8.0-compatible. Scope matches
handoff §4–§5; the two additions (CLI-only 404 guard, order-status cache clear) are safe.
No destructive SQL: one bounded UPDATE (by `setting_id` + old value, affected_rows checked),
one INSERT, one rollback DELETE by exact id + language + name.

## Non-blocking notes

| ID | Where | Note |
|---|---|---|
| N1 | WP1:274 | `opcache_invalidate()` in CLI does not reach the web server's opcache. Normally harmless (timestamps are validated); if QA step 4 still restocks, suspect a stale opcache before anything else. |
| N2 | handoff §7 | `grep -c` counts lines (3), not occurrences (5). Executor's corrected `grep -o … | wc -l` commands in the report are the ones to use. |
| N3 | WP3 | Estimate assumes today's processing list and `subtract` flag for all history; deleted orders and manual edits are invisible. Caveated in the CSV header — use only to guide a physical count. |

## Owner-facing risk (confirmed by reasoning, stated once)

Orders already in «Отримано» had their units returned by the bug. After WP1, «Отримано» holds stock,
so moving such an order to a non-holding status (Скасовано, Помилка, Протерміновано, draft) or
deleting it returns its units a second time. Leave pre-fix delivered orders alone until the WP3
reconciliation is finished. Admin «edit order» nets to zero (void 5→15, re-confirm 15→5).

## Expected run output

- WP1: `anchor_old_count=5`, `occurrences_replaced=5`, `config_complete_status_old=["5","12","10","14"]`,
  `config_complete_status_new=["5","12"]`, `php_lint=ok`, `done=ok`.
- WP2: `changed_row=order_status_id:<n>,language_id:4,name:Скасовано` (n = 24 on the 24.09 dump),
  `done=ok`.
- WP3: `csv=…/bs-reports/BUG-003_stock-inflation_<ts>.csv`, `done=ok`; product 73 inflation ≥ 246.
- Any `ERROR:` line → stop and send the full output to Claude; nothing else to do.
