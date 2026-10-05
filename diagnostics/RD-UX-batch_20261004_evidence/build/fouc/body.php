<?php
declare(strict_types=1);

/**
 * UX-003 — category heading flash (runner 8b)
 * =============================================================================
 * Chain     : runner 8b. Requires runners 1, 2, 3, 3b, 4, 5, 6, 7 and 8 applied first (deployed post-runner-8 state).
 * Handoff   : handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md, section «Runner 8 deployed;
 *             runner 8b — category heading flash (owner QA, 2026-10-05)».
 * Author    : Claude Code · 2026-10-05
 * Risk      : category pages (template markup order only). No CSS value, PHP, JS, URL, canonical or DB change.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003_category-heading-fouc_20261005.php
 *
 * WHAT CHANGES
 *   catalog/view/template/product/category.twig — the first inline <style>…</style> block (R-03 / UI-FIX heading,
 *   subcategory and card rules, including the ones that hide .bs-heading-full / .bs-heading-mobile and size the H1
 *   below 992px) moves, byte-identical, from after the page markup to directly after {{ header }}. A one-line Twig
 *   comment above it is the idempotency marker; Twig comments render nothing. Every other byte stays.
 *
 * ROOT CAUSE
 *   The block sat after the header card, so a browser that paints before the parser reaches it showed the H1 at
 *   boostershop-ds.css's 26px with both spans visible, then jumped to the 12px caption (largest on phones). Moving
 *   it ahead of the markup applies the rules before the first paint. Cascade order is unchanged: still after the
 *   <head> stylesheets and before the second inline block. No new rule, no !important.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard (runner 8 output) → marker → block located by its exact start/end lines, count-checked and
 *   hash-checked → proof: new template minus the marker and the block == old template minus the block → hazard
 *   scan on the marker → Twig parse gate → backup → write → restore on failure. Idempotent: marker
 *   «UX-003-FOUC» in category.twig. Self-deletes.
 *   ROLLBACK: cd ~/public_html && copy category.twig back from _patch_backups/UX-003_category-heading-fouc_20261005-<ts>/,
 *   refresh the theme cache, Ctrl+F5.
 *   TRIGGER: Twig error or broken layout on a category page.
 * =============================================================================
 */

const PATCH_ID = 'UX-003_category-heading-fouc_20261005';
const MARKER   = 'UX-003-FOUC';
const CATEGORY = 'catalog/view/template/product/category.twig';

const EXPECTED_SHA = array(
    CATEGORY => '@@SHA:catalog/view/template/product/category.twig@@',
);
const BLOCK_SHA = '@@BLOCKSHA:catalog/view/template/product/category.twig|</div>\n<style>\n  .bs-subcategory-nav {|</style>\n\n\n\n\n<script>@@';

@@LIB@@

$root = start_runner();
$files = array(CATEGORY => load_text($root, CATEGORY));
out('file_preflight=ok:' . CATEGORY . ' eol=' . ($files[CATEGORY]['eol'] === "\r\n" ? 'crlf' : 'lf'));
if (marker_state($files, array(CATEGORY), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

$old = $files[CATEGORY]['text'];
$head = "{{ header }}\n";
if (strncmp($old, $head, strlen($head)) !== 0) fail('anchor_header_not_first_line');
count_exact($old, $head, 1, 'header');
count_exact($old, "\n<style>\n", 2, 'inline_style_blocks');

// The block: from "<style>\n  .bs-subcategory-nav {" to its own "</style>\n" (the first one after it).
$startNeedle = "</div>\n<style>\n  .bs-subcategory-nav {";
$endNeedle = "</style>\n\n\n\n\n<script>";
count_exact($old, $startNeedle, 1, 'block_start');
count_exact($old, $endNeedle, 1, 'block_end');
$a0 = strpos($old, $startNeedle);
$b0 = strpos($old, $endNeedle);
if ($b0 < $a0) fail('block_order');
if (hash('sha256', substr($old, $a0, $b0 + strlen($endNeedle) - $a0)) !== BLOCK_SHA) fail('block_sha256_mismatch');
$a = $a0 + strlen("</div>\n");
$b = $b0 + strlen("</style>\n");
$block = substr($old, $a, $b - $a);
count_exact($block, '<style>', 1, 'block_one_open');
count_exact($block, '</style>', 1, 'block_one_close');
foreach (array('.bs-heading-full', '.bs-heading-mobile', '.bs-cat-header__title h1') as $probe) {
    if (strpos($block, $probe) === false) fail('block_probe_missing=' . $probe);
}
if (preg_match('~\{\{|\{%~', $block)) fail('block_contains_twig_tags');
if (strpos($old, '{% if products %}') > $a) fail('block_not_after_page_markup');

$marker = "{# " . MARKER . " (2026-10-05): this block moved here unchanged from after the page markup, so the heading rules apply before the first paint. #}\n";
twig_hazard_gate($marker, CATEGORY);
$without = substr($old, 0, $a) . substr($old, $b);
$new = $head . $marker . $block . substr($without, strlen($head));

// Proof: everything except the moved block and the marker is byte-identical, and the block itself is unchanged.
$check = substr($new, strlen($head) + strlen($marker) + strlen($block));
if ($head . $check !== $without) fail('template_minus_block_not_identical');
if (substr($new, strlen($head) + strlen($marker), strlen($block)) !== $block) fail('block_not_identical');
if (strlen($new) !== strlen($old) + strlen($marker)) fail('length_mismatch');
foreach (array('{% if products %}', '<script type="application/ld+json">', 'id="bs-load-more-btn"', '{{ footer }}') as $probe) {
    count_exact($new, $probe, substr_count($old, $probe), 'probe_count');
}
count_exact($new, "\n<style>\n", 2, 'inline_style_blocks_after');
count_exact($new, MARKER . " (2026-10-05)", 1, 'marker_once');
if (strpos($new, $head . $marker . "<style>\n  .bs-subcategory-nav {") !== 0) fail('block_not_directly_after_header');
out('template_minus_block=identical');
out('block=identical bytes=' . strlen($block));

twig_gate($root, array(CATEGORY => array($old, $new)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(CATEGORY => encode_text($files[CATEGORY], $new)), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-003_category-heading-fouc_report_20261005.md');
