# Codex Report — ACC-007: notified history comments

Date: 2026-10-14
Revision: 1
Executor: Codex; independent Claude API review pending in this frozen report.
Patch SHA-256: `f089e31d3b482c107b2f6a719bf1466ab29b224eb79cfcc76947bb242207e9eb`
Gate: G1 follow-up — independent review, then owner host dry-run, deploy and QA. ACC-008 has not started.

## Outcome and owner authority

The separate follow-up adds nonempty `notify=1` order-history comments below the existing purpose-written status message. Comments render as escaped plain text with line breaks; unnotified comments remain absent. No DB data changes.

The owner reported on 2026-10-14 that comments marked for customer notification must also appear in the account. This changes the earlier handoff §2/§6 policy that omitted every raw history comment. The original deployed patch implemented that earlier policy correctly; this follow-up records the new requirement. `notify=1` means notification intent, not proof that an email was delivered. The UI says only “Коментар”.

The mobile-header wishlist request is a separate subsequent package. The obsolete `FIG-CHARM-001` smoke URL is recorded as owner-reported; this package does not change AGENTS.md, product data, URLs or the header. No Git, deployment, Notion or dashboard command was run.

## Fresh evidence and root cause

Owner archive: `ACC-007-followup-current_20261014.tar.gz`, 111656 bytes, SHA-256 `5ba0e4b895afcd687a7ffa40a21e54142dfeecd08f14295d7f0f7d4005b1044a`.

All 12 entries are unique, regular, expected source files; extracted outside the repository. The five supplied installed ACC-007 files match the independently reviewed original candidates after LF normalization. Evidence: `diagnostics/ACC-007_notified-history-comments_evidence_20261014/source-intake.json`.

- `catalog/model/account/order.php:281`: `getHistories()` intentionally selects only history ID, status ID and date. It cannot return the notified comment.
- `catalog/controller/account/order.php:455`: the fresh allowlisted view array deliberately replaces the old DB comment with the approved public status message.
- `system/library/request.php:119`: `Request::clean()` trims and HTML-encodes input with `ENT_COMPAT`. `catalog/model/checkout/order.php:984` stores the supplied comment plus `(int)$notify` without another entity transformation.
- `catalog/controller/mail/order.php:54` calls the existing history-mail path when notification is requested; its `history()` uses `strip_tags($comment)` at line 578. Read-only compatibility evidence, not email-delivery verification.

Long unbroken comments otherwise widen an auto-layout table and move the status column outside the viewport. The current `bootstrap.css:7299` already supplies `.text-break`; the comment block uses that existing utility. Its existing `!important` declarations are unchanged, and no new override, CSS rule, asset token, stylesheet or DS token is written. The previous ACC-007 history markup used no comment-wrapping class; patch search found no plain-text `text-break`/customer-comment override. No encoded-payload absence is inferred from that text search.

## Exact source scope

Three existing files, zero new production files:

| Target | Change |
|---|---|
| `catalog/model/account/order.php` | Add `CASE WHEN oh.notify = 1 THEN oh.comment ELSE '' END AS bs_customer_comment` to the existing bounded SELECT. |
| `catalog/controller/account/order.php` | Add only `bs_customer_comment` to the existing fresh history view array; cast nullable values, decode entities once and trim. |
| `catalog/view/template/account/order_history.twig` | Keep the public status message; add a labelled nonempty comment using `escape('html')` before `nl2br`, plus existing spacing/wrapping utilities. |

All other bytes in these files are preserved, including customer/store access, draft visibility, getOrder callers, real SQL pagination, counts, deterministic ordering, date presentation, auth/token behavior and all other controller/model methods. Status 15 remains hidden even with notify=1. Unknown historical IDs retain neutral presentation. Existing `column_comment` Ukrainian text is reused; no language edit.

Five byte-hashed read-only dependencies: the installed ACC-007 helper, Ukrainian account/order language, Request library, checkout/order writer and mail/order controller. Header and both CSS files are unchanged. The accepted ACC-006 patch, original ACC-007 patch and original report retain their locked SHA-256 values.

## Safety and runner behavior

CLI-only, PHP >=8.0, target-exists checks, source SHA/anchor-count/candidate SHA gates, read-only dependency hashes, atomic backups/writes, candidate and installed PHP lint, automatic restore on install/lint failure, full-hash idempotency and self-delete after success. Backup folder:

`_patch_backups/ACC-007_notified-history-comments_20261014-<UTC timestamp>-<random>`.

Read-only host preflight checks existing order/history/status schema and primary keys, language 4, unchanged status registry, Europe/Kyiv setting, theme overrides and active account-order/wildcard events. It additionally requires numeric integer `notify` and textual `comment` columns. The supplied archive does not contain live column metadata for these two fields: their local mock types are explicitly synthetic; the owner dry-run is the actual verification gate. No customer/order-history rows are queried by the runner. Only SHOW/SELECT metadata queries; no DB mutation or migration, so no separate dump is needed for this package. No mysqlnd-only methods.

One entity decode reverses the observed Request encoding. Direct-writer values containing entity spellings follow the same one-decode presentation policy; ambiguous old encodings are not rewritten. Escaping is explicit because the project Twig environment has autoescape disabled. HTML tags appear as inert text; links are not activated. The buyer's order-level comment and mail behavior are unchanged.

## Local validation

PHP 8.3.30 locally; PHP 8.0.30 on production from the owner's prior output. This is local fixture evidence, not host execution or a production claim.

| Check | Result |
|---|---|
| Actual account model/controller + SQLite PDO SELECTs + native Twig ArrayLoader, autoescape=false | 76 assertions passed; includes read-only queries, notify 0/1/NULL/2, hidden drafts, ownership/store/guest guards, 10+7 pagination, empty/NULL/zero comments, one entity decode, line breaks and inert raw/encoded HTML. |
| Hosting-runner fixtures | 37 assertions passed; dry-run, exact apply, all nine read-only files, repeat without backup, rollback to already-deployed ACC-007 bytes, schema/dependency/event drift, missing/partial/anchor failure, write/post-lint restoration, rollback tamper/later changes/traversal, CRLF preservation. |
| Static scope and lint | 26 assertions passed; `php -l` clean for runner and two changed PHP targets; reverse recipes recover the complete original files; nine read-only files and three locked artifacts unchanged. |
| Headless Chrome, fresh archive CSS | 43 assertions passed at 390/1024/1440; notified text/entities/zero, no injected DOM or runtime error, no table/document overflow with long paragraphs/unbroken text, pagination link hover/focus/active and no-record fallback. |

The 76 count includes per-query read-only assertions; it is not 76 independent business scenarios. Synthetic screenshots inspected at mobile and desktop; no owner screenshot or live customer/payment data was copied into evidence or the review packet. Added-line scan: zero new `!important`, `setTimeout`, `position:absolute/fixed`, unexplained pixels, `innerHTML`, `eval`, Twig `raw` or `include`.

Reproducible tooling: `work/acc007-comments-20261014/`. Evidence: the matching diagnostics evidence directory contains the payload, source diff/intake and test JSON. SQLite checks actual generated SQL semantics but do not substitute for the owner MySQL preflight. No new JS or GA4 changes; GA4 DebugView remains unverified.

## Owner host dry-run — first gate

Upload only `patches/ACC-007_notified-history-comments_20261014.php` to `~/public_html`, then run:

```bash
cd ~/public_html && php ACC-007_notified-history-comments_20261014.php --dry-run
```

Expected: `source_gate=3_targets_ok`, `db_preflight=read_only_ok`, `db_mutations=none`, `dry_run=ok`, `done=ok`. On `error=...` or `done=error`, stop and send the fixed error code. Do not share config contents or comments. This step creates no backup and writes no site files.

## Owner apply and cache cleanup — after successful review/dry-run and owner go

This one block preserves a nonzero failure exit status and avoids a multiline PHP argument to the cPanel CLI wrapper:

```bash
cd ~/public_html || exit
php ACC-007_notified-history-comments_20261014.php && php -r 'require "config.php"; $base=realpath(DIR_CACHE); if ($base===false) {fwrite(STDERR,"cache_path_missing\n");exit(1);} foreach (glob($base."/cache.*") ?: [] as $f) {if (is_file($f) && !is_link($f) && !unlink($f)) {fwrite(STDERR,"cache_clear_failed\n");exit(1);}} $tpl=realpath($base."/template"); if ($tpl!==false) {if (strpos($tpl.DIRECTORY_SEPARATOR,$base.DIRECTORY_SEPARATOR)!==0 || is_link($base."/template")) {fwrite(STDERR,"unsafe_template_cache\n");exit(1);} $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tpl,FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) {if ($f->isFile() && !$f->isLink() && substr($f->getFilename(),-4)===".php" && !unlink($f->getPathname())) {fwrite(STDERR,"template_cache_clear_failed\n");exit(1);}}} echo "cache_cleared=ok\n";'
```

Expected: exactly three `changed=...` lines, two `php_lint=...:ok` lines, backup path, `done=ok`, then `cache_cleared=ok`. Save the exact backup folder. The runner self-deletes after success.

## Rollback

Re-upload the same runner. Run `php ACC-007_notified-history-comments_20261014.php --rollback=_patch_backups/<exact follow-up folder>` in `~/public_html`, then use the same cache-cleaning command above. It restores the three files to their already-deployed original ACC-007 versions. It never restores/modifies DB rows and refuses target changes made after this follow-up. Do not use the earlier status-presentation backup for this follow-up rollback.

## Owner post-deploy QA

1. Open an owned existing order with a nonempty history comment marked customer-notified. Under its standard public message, “Коментар” must show the expected text and line breaks. Use the existing test order; do not change a business order solely for QA.
2. On the same history, comments with notification disabled remain absent. A blank notified comment adds no empty block. A notified draft (15), foreign customer's order and another store remain hidden.
3. Check `&`, double quotes, apostrophe and a multiline comment: no extra `&amp;`/`&quot;`. Existing tag-like text must stay plain text, never execute; do not inject new test payloads into production.
4. Check 390/1024/1440, long content and page 1 → 2 → 1. Dates, status badges, counts and pagination remain correct, and the comment does not push the table outside the page.
5. Check account login/logout, guest order-history guard, address/profile opening and checkout entry. Inspect the existing OpenCart error log for new account-order/Twig errors; share only redacted error lines.
6. Tier 1: home, `/catalog/Pokemon`, `/catalog/Pokemon/Pokemon-booster-box`, `/product/One-Piece-Boosters-OP-11`, a currently valid 3D product opened from the catalogue (the owner retired FIG-CHARM-001), `/information/oplata-i-dostavka`, cart and checkout entry. Do not submit/pay for an order.

Stop at G1 follow-up until the owner reports deploy/QA and says go. The separate mobile-header package comes next; ACC-008 follows only after the owner's next gate.
