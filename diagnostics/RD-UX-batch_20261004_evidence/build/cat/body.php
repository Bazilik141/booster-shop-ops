<?php
declare(strict_types=1);

/**
 * UX-003 stage 3 — category filter: panel above the grid (variant C)
 * =============================================================================
 * Chain     : runner 7 (last; may ship later). Requires runners 1–3, 3b, 4, 5 and 6
 *             (UX-003_grid-fluid-991_20261004) applied first.
 * Handoff   : handoffs/handoff_UX-003_category-filter-C_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - етап 3.html» → «Фільтр категорії» → «C · фінал» (ux-b-stage3.jsx FilterFinal,
 *             .ff-* styles in that HTML), widths 1440 / 768 / 390.
 * Author    : Claude Code · 2026-10-04
 * Risk      : category pages (SEO filter URLs — markup move only). Filter module PHP, the apply logic, the category
 *             controller, canonical, meta robots and URL parameters are not touched.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003_category-filter-C_20261004.php
 *
 * WHAT CHANGES
 *   catalog/view/template/product/category.twig (whole template, SHA-guarded; the text before the toolbar and
 *   everything from the JSON-LD block to the end of the file are asserted byte-identical)
 *     - one row in the header card: subcategory chips | «Фільтр» | sort. Chips scroll horizontally without a
 *       scrollbar up to 991px and sit in a --bs-line-2 segment from 992. «Фільтр»: secondary 44px, sliders icon,
 *       label, selected-count badge (--bs-blue), chevron, aria-expanded / aria-controls; open = --bs-blue-soft.
 *       ≤575: «Фільтр» and sort are 44×44 icon buttons (the native select is a transparent layer over the icon;
 *       its onchange is unchanged). Sort 200px, 220px from 992. No «Фільтр» when column_right is empty.
 *     - the panel under the row renders column_right — the filter module — with the aside wrapper swapped
 *       (`|replace`): stylesheet.css hides #column-right below 992px as the old mobile drawer. Foot: «Скинути»
 *       (shown while something is checked; unchecks and lets the module apply) and «Згорнути».
 *     - removed: the desktop toolbar, the mobile subcategory grid (.bs-subcat-tabs), the mobile action row with
 *       its «Фільтр» (#mobileFilterToggle) and the drawer-toggle JS, the side {{ column_right }}, and their inline
 *       CSS. Product grid ≥992: 4 columns at full width — a CSS width for the row-cols-lg-3 class that stock
 *       common.js forces on every load (the template's row-cols-lg-4 never survives it). Below 992 the header card
 *       gets the 14px bottom margin the removed action row used to provide.
 *     - new inline script: panel open/close, per-group collapse (≤575 only the first group open, otherwise all),
 *       counters, «Скинути». Filters are still applied by the module's own script.
 *     - review N3: the inline :root no longer redefines --bs-sh-sm (it flattened the sticky header's shadow on
 *       category pages); the load-more button keeps its old shadow as a literal.
 *     - UX-004 active-filter chips: moved unchanged (still under the row). NOTE: the live controller sets
 *       $data['active_filters'] = [] and never fills it, so this block renders nothing on production — before and
 *       after this runner. Not changed here; see the report.
 *   extension/opencart/catalog/view/template/module/filter.twig — the markup above the script only: each group is a
 *     header button (name + selected count + chevron, aria-expanded/aria-controls) over its checkboxes (18px, blue
 *     when checked, 36px rows). name="filter[]", values and input ids are unchanged; #button-filter stays in the
 *     DOM, hidden. The <script> is byte-identical (asserted): a change still applies the filter after 250 ms.
 *   boostershop-ds.css — new section «UX-003-FILTER» after «UX-003-GRID»; the .bs-cat-header__toolbar rules the
 *     new row replaces are removed (no other template uses the class).
 *   common/header.twig — ds.css ?v= token only.
 *   Read-only dependency, SHA-guarded, not written: common/column_right.twig (its aside line is what `|replace`
 *   matches).
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old layout: the stock column_right + the R-03 toolbar / T9 mobile grid / action row in
 *   category.twig's inline <style>; they are removed at the source rather than overridden. No !important.
 *   position:absolute only for the ≤575 sort overlay and the count badge on the icon button (both stated in the
 *   CSS). Magic numbers from the approved design (44 / 48 / 36 rows, 200 / 220 sort). No setTimeout added.
 *
 * SEO
 *   The filter URL is still built by the module's unchanged script from its unchanged `action`; canonical and meta
 *   robots come from the controller (not touched) via header.twig (token change only). bs-seo-risk-gate result and
 *   the before/after evidence: diagnostics/UX-003_category-filter-C_report_20261004.md.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on the four targets (runner 6 output) and on column_right.twig → marker → prefix/suffix and
 *   script identity asserted → hazard scan → Twig parse gate on category.twig, filter.twig, header.twig → CSS
 *   balance gate → backup of the four targets → write → restore-all on failure.
 *   Idempotent: marker «UX-003-FILTER» in category.twig, filter.twig and boostershop-ds.css. Self-deletes.
 *   ROLLBACK: copy the four files back from _patch_backups/UX-003_category-filter-C_20261004-<ts>/, refresh the
 *   theme cache, Ctrl+F5.
 *   TRIGGER: the filter does not apply, products disappear, canonical/URL changed, PHP/Twig error on a category.
 * =============================================================================
 */

const PATCH_ID = 'UX-003_category-filter-C_20261004';
const MARKER   = 'UX-003-FILTER';
const TOKEN    = 'ux003filter-20261004';
const CATEGORY = 'catalog/view/template/product/category.twig';
const FILTER   = 'extension/opencart/catalog/view/template/module/filter.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';
const COLRIGHT = 'catalog/view/template/common/column_right.twig';
const ASIDE    = '<aside id="column-right" class="col-3 d-none d-md-block">';

const EXPECTED_SHA = array(
    CATEGORY => '@@SHA:catalog/view/template/product/category.twig@@',
    FILTER   => '@@SHA:extension/opencart/catalog/view/template/module/filter.twig@@',
    DSCSS    => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER   => '@@SHA:catalog/view/template/common/header.twig@@',
);
const EXPECTED_SHA_READONLY = '@@SHA:catalog/view/template/common/column_right.twig@@';

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(CATEGORY, FILTER, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(CATEGORY, FILTER, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

$colRightPath = $root . '/' . COLRIGHT;
if (!is_file($colRightPath)) fail('dependency_not_found=' . COLRIGHT);
$colRight = file_get_contents($colRightPath);
if (!is_string($colRight)) fail('dependency_read_failed=' . COLRIGHT);
if (hash('sha256', $colRight) !== EXPECTED_SHA_READONLY) fail('sha256_mismatch=' . COLRIGHT . ' (read-only dependency) — the panel relies on its aside line. Nothing was written.');
count_exact($colRight, ASIDE, 1, 'colright_aside');
count_exact($colRight, '</aside>', 1, 'colright_aside_end');
out('sha256_guard=ok:' . COLRIGHT . ' (read-only)');

/* ---- category.twig -------------------------------------------------------- */
$old = $files[CATEGORY]['text'];
$category = <<<'BS_CAT_TWIG'
@@FILE:category.twig@@
BS_CAT_TWIG;
$category .= "\n";
$headEnd = "        {% if sub_categories|length %}\n          {# Desktop: integrated toolbar";
count_exact($old, $headEnd, 1, 'old_head_end');
$head = substr($old, 0, strpos($old, $headEnd));
if (strncmp($category, $head, strlen($head)) !== 0) fail('category_head_not_identical');
$tailStart = "<script type=\"application/ld+json\">\n";
count_exact($old, $tailStart, 1, 'old_tail_start');
count_exact($category, $tailStart, 1, 'new_tail_start');
$tail = substr($old, strpos($old, $tailStart));
if (substr($category, -strlen($tail)) !== $tail) fail('category_tail_not_identical');
foreach (array('{{ header }}', '{{ footer }}', '{{ column_left }}', '{{ content_top }}', '{{ content_bottom }}', '{{ description }}',
    '<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">', '{{ pagination }}',
    'id="bs-load-more-btn"', '{% if active_filters|length %}', 'onclick="location=\'{{ f.remove_url }}\'"', 'onclick="location=\'{{ reset_url }}\'"',
    'id="bs-ff-toggle"', 'id="bs-ff-panel"', "{{ column_right|replace({'" . ASIDE . "': '<div class=\"bs-ff-modules\">', '</aside>': '</div>'}) }}",
    'onchange="location = this.value;"') as $probe) {
    count_exact($category, $probe, 1, 'cat_probe');
}
foreach (array('mobileFilterToggle', 'bs-action-row', 'bs-segmented', 'bs-subcat-tab', 'bs-cat-header__toolbar', '--bs-sh-sm:') as $gone) {
    count_exact($category, $gone, 0, 'cat_removed');
}
count_exact($category, '{{ column_right }}', 0, 'cat_no_side_column');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $category), explode("\n", $old))), CATEGORY);

/* ---- filter.twig ---------------------------------------------------------- */
$oldFilter = $files[FILTER]['text'];
count_exact($oldFilter, "<script>\n", 1, 'filter_script_start');
$filterScript = substr($oldFilter, strpos($oldFilter, "<script>\n"));
foreach (array("var button = document.getElementById('button-filter');", "applyTimer = setTimeout(applyFilters, 250);",
    "url.searchParams.set('filter', filters.join(','));", "var url = new URL('{{ action|escape('js') }}');") as $probe) {
    count_exact($filterScript, $probe, 1, 'filter_script_probe');
}
$filterMarkup = <<<'BS_FILTER_TWIG'
@@FILE:filter_markup.twig@@
BS_FILTER_TWIG;
$filter = $filterMarkup . "\n" . $filterScript;
foreach (array('name="filter[]" value="{{ filter.filter_id }}" id="input-filter-{{ filter.filter_id }}"', '{% if filter.filter_id in filter_category %} checked{% endif %}',
    'id="filter-group-{{ filter_group.filter_group_id }}"', 'id="button-filter"', '{{ button_filter }}', '{{ heading_title }}') as $probe) {
    count_exact($filter, $probe, 1, 'filter_probe');
}
twig_hazard_gate($filterMarkup, FILTER);

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];
$ds = replace_one($ds,
    ".bs-cat-header__toolbar {\n  padding: 12px 22px;\n  border-top: 1px solid var(--bs-line-2);\n  background: var(--bs-bg);\n"
    . "  display: flex; align-items: center; gap: 12px; flex-wrap: wrap;\n}\n"
    . ".bs-cat-header__toolbar .bs-chip-row { flex: 0 0 auto; }\n.bs-cat-header__toolbar .bs-filter-chips { margin-left: auto; }\n\n",
    "", 'ds_toolbar_rules');
$ds = replace_one($ds, "  .bs-cat-header__toolbar { overflow-x: auto; flex-wrap: nowrap; }\n", "", 'ds_toolbar_mobile');
count_exact($ds, 'bs-cat-header__toolbar', 0, 'ds_toolbar_gone');
$section = <<<'BS_FF_CSS'
@@FILE:section.css@@
BS_FF_CSS;
$anchor = "/* === /UX-003-GRID === */\n";
$ds = replace_one($ds, $anchor, $anchor . "\n" . $section . "\n", 'ds_after_grid');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token --------------------------------------------- */
$h = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(
    CATEGORY => array($old, $category),
    FILTER   => array($oldFilter, $filter),
    HEADER   => array($files[HEADER]['text'], $h),
));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    CATEGORY => encode_text($files[CATEGORY], $category),
    FILTER   => encode_text($files[FILTER], $filter),
    DSCSS    => encode_text($files[DSCSS], $ds),
    HEADER   => encode_text($files[HEADER], $h),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-003_category-filter-C_report_20261004.md');
