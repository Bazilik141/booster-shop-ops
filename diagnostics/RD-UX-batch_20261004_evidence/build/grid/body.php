<?php
declare(strict_types=1);

/**
 * UX-003 stage 3 — page grid fluid from 576 to 991px (variant B)
 * =============================================================================
 * Chain     : runner 6. Requires runners 1–3, 3b, 4 and 5 (UX-009_search-page_20261004) applied first.
 * Handoff   : handoffs/handoff_UX-003_grid-fluid-991_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - етап 3.html», theme «Сітка 576–767», variant B (ux-b-stage3.jsx GridDemo)
 * Author    : Claude Code · 2026-10-04
 * Risk      : global CSS — every page's wrapper at 576–991px. No markup, no checkout form, no Bootstrap file.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003_grid-fluid-991_20261004.php
 *
 * WHAT CHANGES
 *   boostershop-ds.css — new section «UX-003-GRID» after «UX-009-SP»: `main > .container` at 576–991px gets
 *     max-width:none and the header's side gutters (10px to 768, 32px from 769 — values read from the current
 *     body.bs .bs-header rules and measured: header content edge 10 / 32px). Up to 768 the page's top-level
 *     .row also gets --bs-gutter-x: 20px, so its negative margins match the 10px padding (otherwise R07MOB5's
 *     #content max-width:100vw clamps the column on one side). <576 and ≥992 unchanged.
 *   common/header.twig — ds.css ?v= token only.
 *
 * UI/CSS DISCIPLINE
 *   Root cause: Bootstrap's .container max-width steps (540 / 720) in bootstrap.css, which is not edited (out of
 *   scope by handoff). The DS override is scoped to the page wrappers (`main > .container`, 0,1,1 beats
 *   Bootstrap's 0,1,0 without !important); header.twig's inline `header .container` rule matches nothing in the
 *   current header (it uses .bs-header__inner) and is outside main anyway. Page-specific wrapper rules keep
 *   priority by specificity. Gutter values are the header's (10 / 32px), not new magic numbers.
 *
 * KNOWN, NOT CHANGED (pre-existing, owner decision needed — see the report)
 *   At ~769–905px the header's action row (Увійти + Telegram + the full cart label) is wider than the viewport,
 *   so the page scrolls horizontally there. Present in the untouched 2026-10-04 live pull; it is header markup,
 *   outside this package's .container scope.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on both targets (runner 5 output) → marker → anchor → CSS balance gate → Twig parse gate on
 *   header.twig → backup → write → restore-all on failure. Idempotent: marker «UX-003-GRID» in
 *   boostershop-ds.css. Self-deletes.
 *   ROLLBACK: copy both files back from _patch_backups/UX-003_grid-fluid-991_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runner 7 is not applied.
 *   TRIGGER: new horizontal scroll, a broken slider or table on a tablet width.
 * =============================================================================
 */

const PATCH_ID = 'UX-003_grid-fluid-991_20261004';
const MARKER   = 'UX-003-GRID';
const TOKEN    = 'ux003grid-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    DSCSS  => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER => '@@SHA:catalog/view/template/common/header.twig@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

$section = <<<'BS_GRID_CSS'
@@FILE:section.css@@
BS_GRID_CSS;
$anchor = "/* === /UX-009-SP === */\n";
$ds = replace_one($files[DSCSS]['text'], $anchor, $anchor . "\n" . $section . "\n", 'ds_after_sp');
css_balance_gate($ds, DSCSS);

$h = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
twig_gate($root, array(HEADER => array($files[HEADER]['text'], $h)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS  => encode_text($files[DSCSS], $ds),
    HEADER => encode_text($files[HEADER], $h),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-003_grid-fluid-991_report_20261004.md');
