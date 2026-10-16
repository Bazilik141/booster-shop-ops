# Codex Report — ACC-007: history comment priority

Date: 2026-10-14
Revision: 1
Patch SHA-256: `0af604ca61123655c3cc54c054cf9f5d3a82fc8b51b00ac312a742fdeff93de6`
Independent Claude source review: pending when this report was frozen; saved separately.
Gate: G1 follow-up — review, owner dry-run, explicit owner go for apply, then owner QA. No mobile-header or ACC-008 work started.

## Outcome and authority

Owner request: when their notified text comment is available, hide the standard status explanation. With no displayable comment, retain the standard explanation. Notification-disabled comments stay hidden under the owner's confirmed policy.

This package changes only `catalog/view/template/account/order_history.twig`. It moves the existing `history.comment` print into the `else` branch of `history.bs_customer_comment is not empty`. The displayed comment retains the existing “Коментар” label, classes and `escape('html')|nl2br`. No backend, DB data, status, mail, wishlist, JS or CSS change.

## Current source and cause

The owner ran `cd ~/public_html && sha256sum catalog/view/template/account/order_history.twig` and returned SHA-256 `1c42a4e3de383e3e94a2fda14838534999470f6ac3ea4de8d7c0e14edd052b8d`. It matches the complete retained candidate of the deployed notified-comments patch, so a new archive was unnecessary. The owner screenshot confirms a nonempty notified comment appears alongside the generic explanation; the screenshot and its raw comment were not copied into artifacts/review.

Root cause: current template line 18 prints `{{ history.comment }}` unconditionally before the comment's `if` at line 19. Both appear for the same row. The new `if/else` makes these mutually exclusive. Twig `is not empty` retains the string `'0'` as a displayable comment.

The account model/controller, ACC-007 helper and language are hash-gated read-only dependencies. Their accepted encoding/notify projection, auth/customer/store/draft visibility, dates, counts and pagination are untouched. The owner hash confirms the current target only; the host dry-run verifies dependencies and current metadata. This is not a source archive or an executor production execution claim.

All prior accepted patch/report files remain byte-identical. Scope evidence: `diagnostics/ACC-007_history-comment-priority_evidence_20261014/source-intake.json`, `payload.json` and `source.diff`.

## Runner and validation

One existing target, no new production files. CLI/PHP8 gate, file/anchor/full-source/candidate SHA checks, read-only dependencies and DB metadata/theme/event preflight, atomic backup/write, exact-hash idempotence, self-delete, guarded rollback and automatic restore on apply failure. The preflight reads metadata only, no customer/history rows and no DB writes. No mysqlnd-only methods. No DB dump needed.

C4: no production PHP target is edited. The runner passed native `php -l`; the Twig candidate compiled/rendered with the project's native Twig ArrayLoader and autoescape=false. `php -l` on a Twig file would not validate Twig, so no such lint claim is made. Production PHP 8.0.30 from owner evidence; local PHP 8.3.30. Candidate/full-hash gates ensure the host receives the tested template; compiled cache still requires owner cleanup.

- 94 reused native model/controller/SQLite/Twig fixture assertions passed, including 18 focused assertions for exclusive comment/fallback branches and identical owner/generic text printed once. The count includes per-query read-only assertions and is not 94 independent scenarios. SQLite is not production MySQL proof.
- 22 narrow runner/locked-artifact assertions passed: one-file dry-run/apply, backend unchanged, repeat, exact rollback, missing/source/dependency/anchor drift, automatic restoration, later-edit refusal, path traversal, CRLF and runner lint.
- 49 browser assertions passed at 390/1024/1440 using retained fresh-drop CSS: 12 nonempty comment blocks, five generic fallback rows, no duplicated explanation in comment cells, escapes/entities/zero, inert HTML, long text/table bounds, pagination and hover/focus/active. Mobile and desktop synthetic screenshots inspected.
- Reversing the two exact recipes recovers the complete owner-hash-verified source. Added-line signature scan found zero new `!important`, `setTimeout`, `position:absolute/fixed`, magic pixels, `innerHTML`, `eval`, Twig `raw` or `include`. Existing classes/styles are unchanged; no shared CSS selector or override is added.

Tooling: `work/acc007-comment-priority-20261014/`. All fixtures contain synthetic data. No Git, server access, Notion or dashboard write. Native production render, host apply/cache and owner QA remain owner gates. GA4 is unchanged; no DebugView claim.

## Owner dry-run — first gate

Upload `patches/ACC-007_history-comment-priority_20261014.php` to `~/public_html` and run:

```bash
cd ~/public_html && php ACC-007_history-comment-priority_20261014.php --dry-run
```

Expected: `source_gate=1_target_ok`, `db_preflight=read_only_ok`, `db_mutations=none`, `dry_run=ok`, `done=ok`. On an error, stop and send the fixed error code. No backup or source write during dry-run.

## Owner apply — only after successful review/dry-run and owner go

```bash
cd ~/public_html || exit
php ACC-007_history-comment-priority_20261014.php && php -r 'require "config.php"; $base=realpath(DIR_CACHE); if ($base===false) {fwrite(STDERR,"cache_path_missing\n");exit(1);} foreach (glob($base."/cache.*") ?: [] as $f) {if (is_file($f) && !is_link($f) && !unlink($f)) {fwrite(STDERR,"cache_clear_failed\n");exit(1);}} $tpl=realpath($base."/template"); if ($tpl!==false) {if (strpos($tpl.DIRECTORY_SEPARATOR,$base.DIRECTORY_SEPARATOR)!==0 || is_link($base."/template")) {fwrite(STDERR,"unsafe_template_cache\n");exit(1);} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tpl,FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) {if ($f->isFile() && !$f->isLink() && substr($f->getFilename(),-4)===".php" && !unlink($f->getPathname())) {fwrite(STDERR,"template_cache_clear_failed\n");exit(1);}}} echo "cache_cleared=ok\n";'
```

Expected: one `changed=catalog/view/template/account/order_history.twig`, backup path, `done=ok`, `cache_cleared=ok`. No changed-PHP lint line is expected. Save the exact backup directory. Runner self-deletes after success. The cache command is the same previously fixture-verified guarded command; nested compiled PHP is removed, unrelated files retained, missing cache exits 1.

## Rollback and QA

Re-upload the same runner; in `~/public_html` run `php ACC-007_history-comment-priority_20261014.php --rollback=_patch_backups/<exact folder printed by this runner>`, then the same cache command. Backup prefix: `_patch_backups/ACC-007_history-comment-priority_20261014-<UTC>-<random>`. It restores only the previous notified-comments Twig, preserving the already-deployed backend and DB. It refuses later target edits; earlier packages' backups are not this rollback.

1. Read an existing nonempty notified comment: only that comment appears in “Повідомлення”; the standard explanation is absent in that row. Status badge/date remain visible.
2. A row without a public comment (including notify=0 or empty notified comment) still shows the standard explanation. Hidden drafts remain hidden.
3. Check quotes/ampersand/line breaks and long text at 390/1024/1440, history 1 → 2 → 1. No new production test data is needed.
4. Check Tier 1 pages/account opening/guest guard/checkout entry and new Twig/account-order error-log entries, as in the preceding report. Use a valid current 3D product from the catalogue instead of the owner-retired FIG-CHARM-001 URL. Do not submit/pay for an order.

Stop at the owner deploy/QA gate. The mobile-header wishlist request remains the next separate package after owner go.
