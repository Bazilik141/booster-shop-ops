<?php
declare(strict_types=1);

/**
 * UX-009 — search results page (product/search)
 * =============================================================================
 * Chain     : runner 5. Requires runners 1–3, 3b and 4 (UX-009_live-search_20261004) applied first.
 * Handoff   : handoffs/handoff_UX-009_search-page_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - макети.html», screen «Сторінка пошуку» (ux-b-search.jsx SearchPage,
 *             ux-b.css .ub-sp / .ub-sform / .ub-dc / .ub-next), states results / form open / nothing found.
 * Author    : Claude Code · 2026-10-04
 * Risk      : low — one template + CSS. Controller, URL parameters, meta robots and canonical are not touched
 *             (they come from the controller and common/header.twig; search.twig renders neither).
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-009_search-page_20261004.php
 *
 * WHAT CHANGES
 *   catalog/view/template/product/search.twig (whole template; SHA-guarded, so only the known file is replaced)
 *     - the stock fields (#input-search, #input-description, #input-category, #input-sub-category,
 *       #button-search) move unchanged into <details class="bs-sp-refine"> with summary «Змінити пошук»
 *       (sliders icon + chevron, full width on mobile); <details open> when there are no products. Inside: a DS
 *       card, two columns from 769px (search + «в описі» | category + «у підкатегоріях»), «Пошук» bottom right
 *       (full width on mobile). Label «Категорія» added; «Пошук:» shown without its colon (|trim(':')).
 *     - «Пошук» button: --bs-blue, white, 44px (was Bootstrap btn-primary).
 *     - H2 «Результати пошуку» kept in the markup, visually hidden (owner decision). <hr/> removed.
 *     - «Порівняння товарів (0)» removed (the controller still passes compare/text_compare; unused now).
 *     - list/grid: inline-SVG buttons in a 44×44 segment, active (common.js adds .active) --bs-blue-soft /
 *       --bs-blue; hidden ≤768 as before. Sort / limit: caption above (12.5px, --bs-ink-3), 44px, a 50/50
 *       grid on mobile. ids, onchange handlers, tooltip attributes unchanged.
 *     - nothing found: under the existing RD-06 bs-empty, «Подивіться розділи» — Pokémon TCG, One Piece Card
 *       Game, Інші TCG, Аксесуари with the burger's own URLs and colour dots (1 / 2 / 4 columns at <576 / ≥576
 *       / ≥1024), then the --bs-blue-soft panel «Не знайшли? Напишіть у Telegram — привеземо під замовлення»
 *       + «Написати в Telegram» → https://telegram.me/BoosterShop_Support_bot (owner decision 2026-10-04).
 *     - The inline script at the bottom is byte-identical (asserted).
 *   boostershop-ds.css — new section «UX-009-SP» after «UX-009-LS».
 *   common/header.twig — ds.css ?v= token only.
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: the stock OpenCart template (Bootstrap form rows, btn-primary, Font Awesome
 *   buttons) with no DS rules for #product-search — the new rules are a new section, nothing stacked on an
 *   existing DS rule. Selectors that must beat Bootstrap form classes or `.bs a` (0,1,1) are scoped to
 *   #product-search. No !important, no position rules, no setTimeout. #16245C is the existing navy hover
 *   (.bs-ghost:hover). Sizes from the approved design.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all three targets (runner 4 output) → marker → stock ids asserted once each, script block
 *   asserted identical → hazard scan → Twig parse gate on search.twig and header.twig → CSS balance gate →
 *   backup → write → restore-all on failure.
 *   Idempotent: marker «UX-009-SP» in search.twig and boostershop-ds.css. Self-deletes.
 *   ROLLBACK: copy the three files back from _patch_backups/UX-009_search-page_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runners 6–7 are not applied.
 *   TRIGGER: search from the form, sort, limit or list/grid stops working; PHP/Twig error on product/search.
 * =============================================================================
 */

const PATCH_ID = 'UX-009_search-page_20261004';
const MARKER   = 'UX-009-SP';
const TOKEN    = 'ux009sp-20261004';
const SEARCH   = 'catalog/view/template/product/search.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    SEARCH => '@@SHA:catalog/view/template/product/search.twig@@',
    DSCSS  => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER => '@@SHA:catalog/view/template/common/header.twig@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(SEARCH, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(SEARCH, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- search.twig ---------------------------------------------------------- */
$old = $files[SEARCH]['text'];
$scriptStart = "<script type=\"text/javascript\"><!--\n\$('#button-search').bind('click', function() {\n";
$scriptEnd = "\$('#input-category').trigger('change');\n//--></script>\n";
count_exact($old, $scriptStart, 1, 'old_script_start');
count_exact($old, $scriptEnd, 1, 'old_script_end');
$oldScript = substr($old, strpos($old, $scriptStart), strpos($old, $scriptEnd) + strlen($scriptEnd) - strpos($old, $scriptStart));

$search = <<<'BS_SP_TWIG'
@@FILE:search.twig@@
BS_SP_TWIG;
$search .= "\n";
count_exact($search, $oldScript, 1, 'new_script_identical');
foreach (array('id="input-search"', 'id="input-description"', 'id="input-category"', 'id="input-sub-category"', 'id="button-search"',
    'id="button-list"', 'id="button-grid"', 'id="input-sort"', 'id="input-limit"', 'id="display-control"', 'id="product-list"',
    'name="search"', 'name="description"', 'name="category_id"', 'name="sub_category"', '{{ pagination }}', '{{ results }}',
    '{{ text_no_results }}', '{{ heading_title }}', '{{ header }}', '{{ footer }}', '{{ column_left }}', '{{ column_right }}',
    '{{ content_top }}', '{{ content_bottom }}', 'class="bs-empty" role="status"', '<details class="bs-sp-refine"{% if not products %} open{% endif %}>',
    '<h2 class="visually-hidden">{{ text_search }}</h2>', 'https://telegram.me/BoosterShop_Support_bot') as $probe) {
    count_exact($search, $probe, 1, 'new_probe');
}
foreach (array('compare', 'fa-solid', '<hr', 'btn-primary', 'boostershop_tcg') as $gone) count_exact($search, $gone, 0, 'new_removed');
foreach (array('/catalog/pokemon"', '/catalog/one-piece"', '/catalog/more-tcg"', '/catalog/acsesuary"') as $href) {
    count_exact($files[HEADER]['text'], 'href="' . $href, 1, 'burger_url_source');
    count_exact($search, 'href="' . $href, 1, 'tile_url');
}
twig_hazard_gate($search, SEARCH);

/* ---- boostershop-ds.css --------------------------------------------------- */
$section = <<<'BS_SP_CSS'
@@FILE:section.css@@
BS_SP_CSS;
count_exact($files[DSCSS]['text'], 'bs-sp-', 0, 'ds_no_section_yet');
$anchor = "/* === /UX-009-LS === */\n";
$ds = replace_one($files[DSCSS]['text'], $anchor, $anchor . "\n" . $section . "\n", 'ds_after_ls');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token --------------------------------------------- */
$h = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(SEARCH => array($old, $search), HEADER => array($files[HEADER]['text'], $h)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    SEARCH => encode_text($files[SEARCH], $search),
    DSCSS  => encode_text($files[DSCSS], $ds),
    HEADER => encode_text($files[HEADER], $h),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-009_search-page_report_20261004.md');
