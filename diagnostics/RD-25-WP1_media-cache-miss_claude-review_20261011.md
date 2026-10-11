# Claude review — RD-25 WP1 cold metadata cache warnings

Date: 2026-10-11 · Patch: `patches/RD-25-WP1_media-cache-miss_20261010.php` (223 lines, sha256 `085cbb19…c32c3`) · Report: `diagnostics/RD-25-WP1_media-cache-miss_report_20261011.md`

**Verdict: Deploy OK.**

- Before hash `a42869ef…` equals the r3 runner's `after_sha256` for `catalog/model/tool/image.php` (r3 runner l.129). Independent recompute from the fixture backup original: anchor count 1, result `9d6aaec5…` = pinned after hash.
- Diff: `is_file`/`is_readable` guard returns a cache miss before reading; the single `file_get_contents` runs under a scoped `E_WARNING` handler restored in `finally`. Classification, fit generation, keys, CSS, controllers untouched. Diagnosis is consistent with an OpenCart error handler that does not honour `@` under PHP 8 (`error_reporting()` is no longer 0 inside `@`).
- Runner body below `$originals = [];` is byte-identical to the reviewed F6 runner apart from the task name; C1–C7 as reviewed there.
- Remaining `@` calls in the model (mkdir/unlink/rename/tempnam) fire only on failure paths; they would print on the storefront only if a write fails. Non-blocking.

## Owner decision (not part of the patch)

The storefront printed PHP warnings into customer HTML, so error display is on in production. Recommended: Admin → System → Settings → store → Server: "Display errors" = No, "Log errors" = Yes. Any `@`-suppressed warning anywhere in core or extensions would otherwise also reach buyers.

## Sequencing

The pending D1–D3 fix (`diagnostics/RD-25-WP1_media-fit-visual-defects_claude_20261010.md`) edits the same model file and must be pinned to the post-repair hash `9d6aaec5…`. Deploy this repair first.
