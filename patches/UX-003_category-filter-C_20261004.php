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
    CATEGORY => 'ac4f9d52fd000e10b3d9873b7fbe8387545fd324e472b9b0635fe6118ddb0497',
    FILTER   => 'bfa1648f6cfce814ba4b2c737099a632b44e9db19dc5675d3a4941eac87cdc54',
    DSCSS    => 'b921a1ebb9d4356bdc54d676c29da36a4af8e9b8db0449f8061ad7ef27ad2bcd',
    HEADER   => 'f820b6d37ccd5c66376a00166234293c0d01eaa11791afc2563566f20675d505',
);
const EXPECTED_SHA_READONLY = '0ff9302db636de24ddfb6baedf427d6a9f90854bb07ed7f2217ebbda114fa2b3';


/* ---- shared runner library (identical in every runner of the 2026-10-04 RD/UX chain) ---- */

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $reason): void { throw new RuntimeException($reason); }

/** Raw bytes plus an LF view. Mixed or bare-CR endings abort: re-encoding would rewrite lines this patch does not own. */
function load_text(string $root, string $rel): array
{
    $path = $root . '/' . $rel;
    if (!is_file($path)) fail('target_not_found=' . $rel);
    if (!is_readable($path) || !is_writable($path)) fail('target_not_writable=' . $rel);
    $raw = file_get_contents($path);
    if (!is_string($raw)) fail('target_read_failed=' . $rel);
    $crlf = substr_count($raw, "\r\n");
    if ($crlf !== 0 && $crlf !== substr_count($raw, "\n")) fail('mixed_line_endings=' . $rel);
    if ($crlf === 0 && strpos($raw, "\r") !== false) fail('bare_cr_line_endings=' . $rel);
    return array('raw' => $raw, 'eol' => $crlf !== 0 ? "\r\n" : "\n", 'text' => str_replace("\r\n", "\n", $raw));
}

function encode_text(array $file, string $text): string
{
    return $file['eol'] === "\n" ? $text : str_replace("\n", "\r\n", $text);
}

/** Chain guard: every target must be byte-identical to the state the previous runner leaves behind. */
function sha_guard(array $files, array $expected): void
{
    foreach ($expected as $rel => $sha) {
        $actual = hash('sha256', $files[$rel]['raw']);
        if ($actual !== $sha) {
            fail('sha256_mismatch=' . $rel . ' actual=' . $actual . ' expected=' . $sha
                . ' — the file is not the state this runner was built on (previous runner not applied, or production changed since the 2026-10-04 pull). Nothing was written.');
        }
        out('sha256_guard=ok:' . $rel);
    }
}

function count_exact(string $haystack, string $needle, int $expected, string $label): void
{
    $actual = substr_count($haystack, $needle);
    if ($actual !== $expected) fail('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
}

function replace_one(string $haystack, string $old, string $new, string $label): string
{
    count_exact($haystack, $old, 1, $label);
    return str_replace($old, $new, $haystack);
}

/** Replaces the text between two unique markers (markers included) after checking the old block's hash. */
function replace_block(string $haystack, string $start, string $end, string $oldSha, string $new, string $label): string
{
    count_exact($haystack, $start, 1, $label . '_start');
    count_exact($haystack, $end, 1, $label . '_end');
    $a = strpos($haystack, $start);
    $b = strpos($haystack, $end);
    if ($b < $a) fail('block_order_' . $label);
    $b += strlen($end);
    $old = substr($haystack, $a, $b - $a);
    if (hash('sha256', $old) !== $oldSha) fail('block_sha256_mismatch_' . $label);
    return substr($haystack, 0, $a) . $new . substr($haystack, $b);
}

/** Convention 8: find the reference by its path, accept only a well-formed token (or none), replace it wholesale. */
function bust_token(string $text, string $assetPath, string $token, string $label): string
{
    $pattern = '~(' . preg_quote($assetPath, '~') . ')(\?v=[A-Za-z0-9._-]+)?(?=["\'])~';
    $found = preg_match_all($pattern, $text);
    if ($found !== 1) fail('anchor_count_token_' . $label . '=' . (string)$found . ',expected=1');
    $result = preg_replace($pattern, '$1?v=' . $token, $text, 1);
    if (!is_string($result)) fail('token_replace_failed_' . $label);
    return $result;
}

function css_balance_gate(string $css, string $label): void
{
    $plain = preg_replace('~/\*.*?\*/~s', '', $css);
    if (!is_string($plain)) fail('css_strip_failed=' . $label);
    if (substr_count($plain, '{') !== substr_count($plain, '}')) fail('css_brace_unbalanced=' . $label);
    if (substr_count($css, '/*') !== substr_count($css, '*/')) fail('css_comment_unbalanced=' . $label);
    out('css_gate=passed:' . $label);
}

/** RD-11/RD-12 outage hazards (2026-09-22): a literal "{#" inside inline CSS/JS opens a Twig comment.
 *  Only applied to the text this runner adds. */
function twig_hazard_gate(string $added, string $label): void
{
    if (preg_match_all('~\{#~', $added) !== preg_match_all('~#\}~', $added)) fail('twig_hazard_comment_unbalanced=' . $label);
    if (preg_match('~\{#[^ \n]~', $added)) fail('twig_hazard_hash_brace=' . $label);
    out('twig_hazard_gate=passed:' . $label);
}

/** Parses templates with the site's own Twig source (never Composer's autoloader: its platform check wants
 *  PHP 8.1, production CLI is 8.0). The unmodified file is parsed first as a control. */
function twig_gate(string $root, array $templates): void
{
    $config = @file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        fail('twig_gate_dir_storage_not_found');
    }
    $src = rtrim($match[1], '/\\') . '/vendor/twig/twig/src/';
    if (!is_file($src . 'Environment.php')) fail('twig_gate_twig_source_not_found');
    spl_autoload_register(static function (string $class) use ($src): void {
        if (strncmp($class, 'Twig\\', 5) !== 0) return;
        $file = $src . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($file)) require_once $file;
    });
    foreach (array('core.php', 'debug.php', 'escaper.php', 'string_loader.php') as $resource) {
        if (is_file($src . 'Resources/' . $resource)) require_once $src . 'Resources/' . $resource;
    }
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(array()), array('cache' => false, 'autoescape' => false, 'debug' => true));
    $twig->addExtension(new \Twig\Extension\DebugExtension());
    foreach ($templates as $rel => $pair) {
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($pair[0], $rel . '#unmodified')));
        } catch (Throwable $error) {
            fail('twig_gate_control_failed=' . $rel . ':' . $error->getMessage());
        }
        try {
            $twig->parse($twig->tokenize(new \Twig\Source($pair[1], $rel)));
        } catch (Throwable $error) {
            fail('twig_gate_failed=' . $rel . ':' . $error->getMessage());
        }
        out('twig_gate=passed(parse):' . $rel);
    }
}

/** php -l on candidate bytes before anything is written. */
function php_lint_bytes(string $root, string $rel, string $bytes): void
{
    if (!function_exists('exec')) fail('php_lint_unavailable(exec disabled)=' . $rel);
    $tmp = $root . '/' . $rel . '.' . PATCH_ID . '.lint.php';
    if (file_put_contents($tmp, $bytes, LOCK_EX) !== strlen($bytes)) { @unlink($tmp); fail('php_lint_temp_write_failed=' . $rel); }
    $lines = array();
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lines, $code);
    @unlink($tmp);
    if ($code !== 0) fail('php_lint_failed=' . $rel . ':' . implode(' | ', $lines));
    out('php_lint=passed:' . $rel);
}

function php_lint_file(string $path): void
{
    $lines = array();
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $lines, $code);
    if ($code !== 0) fail('php_lint_postwrite_failed=' . $path . ':' . implode(' | ', $lines));
}

function write_checked(string $path, string $bytes): void
{
    $temp = $path . '.' . PATCH_ID . '.tmp';
    if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)) { @unlink($temp); fail('temp_write_failed=' . $path); }
    if (!@rename($temp, $path)) {
        $copied = @copy($temp, $path);
        @unlink($temp);
        if (!$copied) fail('target_write_failed=' . $path);
    }
    if (file_get_contents($path) !== $bytes) fail('postwrite_verify_failed=' . $path);
}

/** Backs up every target (header.twig always included), writes, re-lints PHP targets; any failure restores all. */
function commit_files(string $root, array $files, array $updated, array $phpTargets): void
{
    $backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
    foreach ($files as $rel => $file) {
        $dest = $backupDir . '/' . $rel;
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) fail('backup_dir_failed=' . dirname($dest));
        if (file_put_contents($dest, $file['raw'], LOCK_EX) !== strlen($file['raw'])) fail('backup_write_failed=' . $rel);
        out('backup=' . substr($dest, strlen($root) + 1));
    }
    try {
        foreach ($updated as $rel => $bytes) {
            write_checked($root . '/' . $rel, $bytes);
            out('changed=' . $rel);
        }
        foreach ($phpTargets as $rel) {
            php_lint_file($root . '/' . $rel);
            out('php_lint_postwrite=passed:' . $rel);
        }
    } catch (Throwable $error) {
        $restored = true;
        foreach ($updated as $rel => $bytes) {
            if (file_put_contents($root . '/' . $rel, $files[$rel]['raw'], LOCK_EX) !== strlen($files[$rel]['raw'])) $restored = false;
        }
        out('restore=' . ($restored ? 'ok' : 'FAILED — copy the files back from ' . substr($backupDir, strlen($root) + 1)));
        throw $error;
    }
    out('backup_dir=' . substr($backupDir, strlen($root) + 1));
}

function start_runner(): string
{
    set_exception_handler(static function (Throwable $error): void {
        out('error=' . $error->getMessage());
        out('done=failed');
        exit(1);
    });
    if (PHP_VERSION_ID < 80000) fail('php_8_0_required');
    $root = rtrim((string)(getcwd() ?: __DIR__), '/\\');
    out('patch=' . PATCH_ID);
    out('cwd=' . $root);
    out('time=' . date('c'));
    out('php=' . PHP_VERSION);
    out('db_changes=none');
    if (!is_file($root . '/index.php') || !is_file($root . '/config.php') || !is_dir($root . '/catalog/view/template/common')) {
        fail('not_opencart_webroot — upload to ~/public_html and run from there');
    }
    return $root;
}

/** Marker state across the files this runner adds content to: all present = already applied, some = abort. */
function marker_state(array $files, array $markerFiles, string $marker): bool
{
    $hits = 0;
    foreach ($markerFiles as $rel) if (strpos($files[$rel]['text'], $marker) !== false) $hits++;
    if ($hits !== 0 && $hits !== count($markerFiles)) fail('partial_marker_state=' . $marker . ' — restore the files from the earlier run\'s backup before retrying');
    return $hits === count($markerFiles);
}

function finish_already_applied(): void
{
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

function finish_ok(string $next): void
{
    out('already_applied=no');
    out('done=ok');
    out('next=' . $next);
    @unlink(__FILE__);
}

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
{{ header }}
<div id="product-category" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>

  <div class="row">
    {{ column_left }}

    <div id="content" class="col">
      {{ content_top }}

      {# CAT-002-5c · category accent class #}
      <div class="bs-cat-header{% if category_code %} bs-cat-header--{{ category_code }}{% endif %}{% if category_is_subcategory %} bs-cat-header--subcategory{% endif %}">
        <div class="bs-cat-header__strip"></div>

        <div class="bs-cat-header__hero">
          <div>
            <div class="bs-cat-header__title">
              <h1>
                <span class="bs-heading-full">{{ heading_title }}</span>
                <span class="bs-heading-mobile">{% if category_heading_short %}{{ category_heading_short }}{% else %}{{ heading_title }}{% endif %}</span>
              </h1>
              <span class="bs-count">{{ product_total }} {{ products_total_label }}</span>
            </div>
          </div>

        </div>

        {# UX-003-FILTER (2026-10-04): one row — subcategories | «Фільтр» | sort — and the filter panel under it
           (design «етап 3», «Фільтр категорії», variant C). Replaces the desktop toolbar, the mobile subcategory grid
           and the mobile action row. The filter module (column_right) renders inside the panel instead of the side
           column; its aside wrapper is swapped because stylesheet.css hides #column-right below 992px. #}
        <div class="bs-ff-row">
          {% if sub_categories|length %}
            <nav class="bs-ff-chips" aria-label="Підкатегорії">
              {% for sub in sub_categories %}
                <a href="{{ sub.href }}" class="bs-ff-chip{% if sub.active %} is-active{% endif %}"{% if sub.active %} aria-current="page"{% endif %}>{{ sub.name }}{% if sub.product_count %}<span class="bs-ff-chip__count">{{ sub.product_count }}</span>{% endif %}</a>
              {% endfor %}
            </nav>
          {% else %}
            <div class="bs-ff-chips"></div>
          {% endif %}
          <div class="bs-ff-tools">
            {% if column_right %}
              <button type="button" class="bs-ff-btn" id="bs-ff-toggle" aria-expanded="false" aria-controls="bs-ff-panel">
                <svg width="17" height="17" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M2 4h7M12 4h2M2 12h2M7 12h7M9 2.5v3M5 10.5v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
                <span class="bs-ff-btn__label">Фільтр</span>
                <span class="bs-ff-count" hidden>0</span>
                <svg class="bs-ff-btn__chev" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
              </button>
            {% endif %}
            <div class="bs-ff-sort">
              <span class="bs-ff-sort__ic" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3"/></svg></span>
              <select class="bs-select" aria-label="{{ text_sort }}" onchange="location = this.value;">
                {% for sort_item in sorts %}
                  <option value="{{ sort_item.href }}"{% if sort_item.value == current_sort %} selected{% endif %}>{{ sort_item.text }}</option>
                {% endfor %}
              </select>
            </div>
          </div>
        </div>
        {% if column_right %}
          <div class="bs-ff-panel" id="bs-ff-panel" hidden>
            {{ column_right|replace({'<aside id="column-right" class="col-3 d-none d-md-block">': '<div class="bs-ff-modules">', '</aside>': '</div>'}) }}
            <div class="bs-ff-foot">
              <button type="button" class="bs-ff-reset" data-bs-ff-reset hidden>Скинути</button>
              <button type="button" class="bs-ff-close" data-bs-ff-close>Згорнути <svg class="bs-ff-close__chev" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}
          <div class="bs-cat-header__filters">
            <span class="bs-cat-header__filters-label">Фільтр:</span>
            {% for f in active_filters %}
              <span class="bs-filter-chip">
                {{ f.label }}
                <button class="bs-filter-chip__remove" type="button" aria-label="Прибрати {{ f.label }}" onclick="location='{{ f.remove_url }}'">x</button>
              </span>
            {% endfor %}
            <button class="bs-filter-reset" type="button" onclick="location='{{ reset_url }}'">Скинути все</button>
          </div>
        {% endif %}
      </div>

      {% if products %}
        <div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">
          {% for product in products %}
            {# TECH-013 WP2: the first card is the LCP element on mobile (measured
               340x240 = 81,600 px^2 above the fold at 390x844, the largest element on
               the page) and thumb.twig marks every card loading="lazy", which defers
               exactly the image LCP waits for. Promote the first card only — the grid is
               row-cols-1 on mobile, so one card is above the fold, and eager-loading a
               full desktop row would compete for bandwidth on throttled 4G. The replace
               filter is used because product/thumb pre-renders each card to an HTML
               string, so thumb.twig has no loop index; header.twig uses the same
               `|replace` idiom for the cart block. #}
            <div class="col mb-3">{{ loop.first ? product|replace({'loading="lazy"': 'loading="eager" fetchpriority="high"'}) : product }}</div>
          {% endfor %}
        </div>

        {# SEO / no-JS fallback: keeps rel=prev/next pager available to crawlers #}
        <noscript>
          <div class="row">
            <div class="col-sm-6 text-start">{{ pagination }}</div>
            <div class="col-sm-6 text-end">{{ results }}</div>
          </div>
        </noscript>

        {# Results count - always visible #}
        <div class="text-end bs-results-count mb-2">{{ results }}</div>

        {# Load-More block - JS reveals it based on rel=next in <head> #}
        <div id="bs-load-more-wrap" aria-live="polite" style="display:none">
          <div class="bs-load-more-progress"
               id="bs-load-more-progress"
               data-shown="0"
               data-total="{{ product_total|default(0) }}">
            <div class="bs-load-more-progress__label">
              Ви переглянули <b class="bs-lm-shown">0</b>
              із <b class="bs-lm-total">{{ product_total|default(0) }}</b> товарів
            </div>
            <div class="bs-load-more-progress__track">
              <div class="bs-load-more-progress__fill" style="width: 0%"></div>
            </div>
          </div>

          <button id="bs-load-more-btn" type="button" class="bs-btn-load-more">
            <span class="bs-load-more-label">Показати ще</span>
            <span class="bs-load-more-count" id="bs-load-more-count"></span>
            <svg class="bs-load-more-chev" viewBox="0 0 16 16" fill="none" aria-hidden="true">
              <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span class="bs-load-more-spinner" aria-hidden="true"></span>
          </button>
        </div>
      {% endif %}

      {% if not categories and not products %}
        <div class="bs-empty" role="status">
          <div class="bs-empty__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M6 7h12l-1 12H7L6 7Z" stroke-width="1.7" stroke-linejoin="round"/>
              <path d="M9 7a3 3 0 0 1 6 0" stroke-width="1.7" stroke-linecap="round"/>
              <path d="M9.5 13h5" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
          </div>
          <p class="bs-empty__title">{{ heading_title }}</p>
          <p class="bs-empty__text">{{ text_no_results }}</p>
          <a href="{{ continue }}" class="bs-btn bs-btn-primary bs-empty__cta">{{ button_continue }}</a>
        </div>
      {% endif %}

      <div class="category-intro category-seo-panel{% if heading_title == 'Pokémon' or heading_title == 'Pokemon' %} category-seo-pokemon{% elseif heading_title == 'One Piece' %} category-seo-onepiece{% endif %}">
                        {#
        {% if image %}
          <div class="category-thumb-wrap">
            <div class="category-thumb">
              <img src="{{ image }}" alt="{{ heading_title }}" title="{{ heading_title }}" class="category-thumb-img"/>
            </div>
          </div>
        {% endif %}
        #}



        {% if description %}
          <div class="category-description">
            {{ description }}
          </div>
        {% endif %}
      </div>

      {{ content_bottom }}
    </div>
  </div>
</div>
<style>
  .bs-subcategory-nav {
    --bs-subcategory-accent: #1E3A8A;
    --bs-subcategory-accent-soft: #EFF6FF;
    --bs-subcategory-card-bg: #FAFBFC;
    --bs-subcategory-border: #D8DEE8;
    --bs-subcategory-hover: #F8FAFC;
    margin: 8px 0 18px;
  }

  .bs-subcategory-nav--pokemon {
    --bs-subcategory-accent: #C68A00;
    --bs-subcategory-accent-soft: #FFF3D2;
    --bs-subcategory-card-bg: #FFFDF7;
    --bs-subcategory-border: #DCD6C8;
    --bs-subcategory-hover: #FFF8E8;
  }

  .bs-subcategory-nav--onepiece {
    --bs-subcategory-accent: #1E40AF;
    --bs-subcategory-accent-soft: #EFF6FF;
    --bs-subcategory-card-bg: #FAFCFF;
    --bs-subcategory-border: #D8DEE8;
    --bs-subcategory-hover: #F8FAFC;
  }

  .bs-subcategory-nav--yugioh {
    --bs-subcategory-accent: #1E3A8A;
    --bs-subcategory-accent-soft: #EFF6FF;
    --bs-subcategory-card-bg: #FAFBFC;
    --bs-subcategory-border: #D8DEE8;
    --bs-subcategory-hover: #F8FAFC;
  }

  .bs-subcategory-list {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    max-width: 760px;
  }

  .bs-subcategory-card {
    min-height: 68px;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    border: 1px solid var(--bs-subcategory-border);
    border-radius: 8px;
    background: var(--bs-subcategory-card-bg);
    color: #111827;
    text-decoration: none;
    box-shadow: 0 1px 2px rgba(31, 41, 55, 0.04);
    transition: background-color 0.16s ease, border-color 0.16s ease, box-shadow 0.16s ease, transform 0.16s ease;
  }

  .bs-subcategory-card:hover,
  .bs-subcategory-card:focus {
    color: #111827;
    text-decoration: none;
    background: var(--bs-subcategory-hover);
    border-color: var(--bs-subcategory-accent);
    box-shadow: 0 10px 22px rgba(31, 41, 55, 0.08);
    transform: translateY(-1px);
  }

  .bs-subcategory-card:focus {
    outline: 2px solid rgba(30, 58, 138, 0.18);
    outline-offset: 2px;
  }

  .bs-subcategory-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: var(--bs-subcategory-accent-soft);
    color: var(--bs-subcategory-accent);
    border: 1px solid rgba(31, 41, 55, 0.06);
  }

  .bs-subcategory-icon i {
    font-size: 19px;
    line-height: 1;
  }

  .bs-subcategory-name {
    min-width: 0;
    color: #111827;
    font-size: 17px;
    font-weight: 800;
    line-height: 1.2;
  }

  .category-sort-group .input-group-text,
  .category-limit-group .input-group-text {
    min-width: 44px;
    justify-content: center;
    padding-left: 0.75rem;
    padding-right: 0.75rem;
  }

  .category-sort-group .input-group-text i,
  .category-limit-group .input-group-text i {
    font-size: 0.95rem;
  }

  @media (max-width: 991.98px) {
    .bs-subcategory-nav {
      margin: 4px 0 12px;
    }

    .bs-subcategory-list {
      grid-template-columns: 1fr;
      gap: 8px;
      max-width: none;
    }

    .bs-subcategory-card {
      min-height: 56px;
      padding: 9px 12px;
      gap: 10px;
    }

    .bs-subcategory-icon {
      width: 36px;
      height: 36px;
      flex-basis: 36px;
    }

    .bs-subcategory-icon i {
      font-size: 17px;
    }

    .bs-subcategory-name {
      font-size: 15.5px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

       .category-controls-row {
      display: flex;
      flex-wrap: nowrap;
      gap: 0px;
    }


    .category-controls-row > div:first-child {
      width: 62%;
      flex: 0 0 62%;
    }

    .category-controls-row > div:last-child {
      width: 38%;
      flex: 0 0 38%;
    }

    .category-sort-group .input-group-text,
    .category-limit-group .input-group-text {
      min-width: 40px;
      padding-left: 0.5rem;
      padding-right: 0.5rem;
    }

    .category-sort-group .form-select,
    .category-limit-group .form-select {
      font-size: 0.875rem;
      padding-left: 0.45rem;
      padding-right: 1.8rem;
    }
  }
    .category-intro {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-top: 16px;
    margin-bottom: 8px;
  }

  .category-thumb-wrap {
    flex: 0 0 220px;
    max-width: 220px;
  }

  .category-thumb {
    width: 220px;
    height: 140px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
  }

  .category-thumb-bg {
    width: 100%;
    height: 100%;
    background-repeat: no-repeat;
    background-position: center center;
    background-size: contain;
  }

  .category-description {
    flex: 1 1 auto;
  }

  @media (max-width: 991.98px) {
    .category-intro {
      align-items: flex-start;
      gap: 14px;
      margin-top: 12px;
      margin-bottom: 4px;
    }

    .category-thumb-wrap {
      flex: 0 0 120px;
      max-width: 120px;
    }

    .category-thumb {
      width: 120px;
      height: 60px;
    }
  }


  /* R-03 final · segmented chips (desktop) + subcat-tabs (mobile) + action-row */

  .bs-cat-header { margin-bottom: 0; }

  /* UI-CAT-GAP · desktop toolbar-to-grid spacing */
  @media (min-width: 992px) {
    #product-category .bs-cat-header { margin-bottom: 16px; }
  }
  .bs-cat-header__hero { align-items: flex-start; }
  .bs-cat-header__title { flex-wrap: wrap; }

  /* Active filter chips — separate row inside the header card. */
  .bs-cat-header__filters {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 10px 22px;
    border-top: 1px solid var(--bs-line-2);
    background: #fff;
  }
  .bs-cat-header__filters-label {
    font-size: 12.5px; color: var(--bs-ink-3);
  }

  @media (max-width: 991.98px) {
    .bs-cat-header__filters { padding: 10px 14px; }
  }

  /* R-03 visual fix - owner mobile reference */
  @media (max-width: 991.98px) {
    .bs-cat-header__hero {
      padding: 18px 16px 14px;
    }

    .bs-cat-header__title h1 {
      font-size: 24px;
      line-height: 1.12;
    }

  }

  /* R-03 mobile subcategory heading cleanup */
  .bs-heading-mobile {
    display: none;
  }

  @media (max-width: 991.98px) {
    .bs-heading-full {
      display: none;
    }

    .bs-heading-mobile {
      display: inline;
    }

    .bs-cat-header--subcategory .bs-cat-header__title {
      align-items: flex-start;
    }

    .bs-cat-header--subcategory .bs-cat-header__title .bs-count {
      display: none;
    }
  }

  /* UI-FIX-20260903 T9 · C3 inversion. At phone widths the category name drops
     to a caption line above the subcategory grid, which becomes the page's
     primary navigation. The element stays a real <h1> — this is visual only,
     and the name is still in the breadcrumbs. Placed after the 991.98px block
     on purpose: it has to win at 640px and below. */
  @media (max-width: 640px) {
    .bs-cat-header__hero {
      padding: 14px 14px 4px;
    }

    .bs-cat-header__title {
      align-items: baseline;
      gap: 5px;
      margin: 0 2px 2px;
    }

    .bs-cat-header__title h1 {
      font-size: 12px;
      font-weight: 700;
      line-height: 1.3;
      color: var(--bs-ink);
    }

    .bs-cat-header__title .bs-count {
      font-size: 12px;
      font-weight: 500;
      color: var(--bs-ink-4);
    }

    .bs-cat-header__title .bs-count::before {
      content: "· ";
    }

  }

  /* Load More button - visual handoff */
  :root {
    --bs-blue: #1E3A8A;
    --bs-blue-soft: #E8EEFB;
    --bs-line: #E5E7EB;
    --bs-ink: #111827;
    --bs-ink-3: #6B7280;
    --bs-paper: #FFFFFF;
    --bs-r-pill: 999px;
  }

  #bs-load-more-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    padding: 26px 0 16px;
  }

  .bs-results-count {
    font-size: 13px;
    color: var(--bs-ink-3);
    text-align: right;
    margin: 22px 2px 0;
  }
  .bs-results-count b { color: var(--bs-ink); font-weight: 700; }

  .bs-load-more-progress {
    width: 300px;
    max-width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
  }
  .bs-load-more-progress__label {
    font-size: 12.5px;
    color: var(--bs-ink-3);
    font-weight: 500;
    white-space: nowrap;
  }
  .bs-load-more-progress__label b { color: var(--bs-ink); font-weight: 700; }
  .bs-load-more-progress__track {
    width: 100%;
    height: 5px;
    border-radius: 999px;
    background: var(--bs-line);
    overflow: hidden;
  }
  .bs-load-more-progress__fill {
    height: 100%;
    border-radius: 999px;
    background: var(--bs-blue);
    transition: width .4s ease;
  }

  .bs-btn-load-more {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 300px;
    max-width: 100%;
    min-height: 50px;
    padding: 0 28px;
    font-family: inherit;
    font-size: 14.5px;
    font-weight: 700;
    letter-spacing: .01em;
    white-space: nowrap;
    border-radius: var(--bs-r-pill);
    cursor: pointer;
    background: var(--bs-paper);
    border: 1.5px solid var(--bs-blue);
    color: var(--bs-blue);
    box-shadow: 0 1px 0 rgba(17,24,39,0.03); /* UX-003-FILTER (review N3): was var(--bs-sh-sm), which the :root above redefined page-wide */
    transition: background .18s ease, border-color .18s ease, color .18s ease, transform .1s ease;
  }
  .bs-btn-load-more:hover:not(:disabled) { background: var(--bs-blue-soft); }
  .bs-btn-load-more:active { transform: translateY(1px); }
  .bs-btn-load-more:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgba(30,58,138,0.18);
  }
  .bs-btn-load-more:disabled { cursor: wait; }

  .bs-load-more-count {
    font-weight: 700;
    font-size: 12px;
    line-height: 1;
    padding: 3px 8px;
    border-radius: 999px;
    background: var(--bs-blue-soft);
    color: var(--bs-blue);
  }

  .bs-load-more-chev {
    width: 16px;
    height: 16px;
    flex: 0 0 auto;
    transition: transform .18s ease;
  }
  .bs-btn-load-more:hover .bs-load-more-chev { transform: translateY(2px); }

  .bs-load-more-spinner {
    display: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid currentColor;
    border-right-color: transparent;
    animation: bs-spin .6s linear infinite;
  }
  .bs-btn-load-more.loading { pointer-events: none; }
  .bs-btn-load-more.loading .bs-load-more-chev { display: none; }
  .bs-btn-load-more.loading .bs-load-more-spinner { display: inline-block; }
  .bs-btn-load-more.loading .bs-load-more-label { opacity: .65; }
  @keyframes bs-spin { to { transform: rotate(360deg); } }

  @media (max-width: 760px) {
    .bs-results-count { text-align: center; }
    .bs-load-more-progress,
    .bs-btn-load-more { width: 100%; max-width: 360px; }
    .bs-btn-load-more { min-height: 48px; }
  }

  @media (prefers-reduced-motion: reduce) {
    .bs-load-more-progress__fill,
    .bs-load-more-chev,
    .bs-btn-load-more { transition: none; }
    .bs-load-more-spinner { animation-duration: 1.2s; }
  }
</style>




<script>
document.addEventListener('DOMContentLoaded', function () {
  var content = document.getElementById('content');


  function setMobileView(mode) {
    if (!content) {
      return;
    }

    if (mode === 'list') {
      content.classList.add('mobile-force-list');
      content.classList.remove('mobile-force-grid');
      localStorage.setItem('mobileCatalogView', 'list');
    } else {
      content.classList.add('mobile-force-grid');
      content.classList.remove('mobile-force-list');
      localStorage.setItem('mobileCatalogView', 'grid');
    }

    syncMobileButtons();
  }

  function syncMobileButtons() {
    if (!mobileListBtn || !mobileGridBtn || !content) {
      return;
    }

    var isList = content.classList.contains('mobile-force-list');

    mobileListBtn.classList.toggle('active', isList);
    mobileGridBtn.classList.toggle('active', !isList);
  }

 

    if (content) {
    content.classList.remove('mobile-force-list');
    content.classList.remove('mobile-force-grid');
  }


    window.addEventListener('resize', function () {
    if (!content) {
      return;
    }

    content.classList.remove('mobile-force-list');
    content.classList.remove('mobile-force-grid');
  });

});
</script>
<script>
/* UX-003-FILTER (2026-10-04): filter panel — open/close, per-group collapse, selection counters, «Скинути».
   Applying a filter stays with the filter module's own script (a checkbox change applies it after 250 ms). */
(function () {
  var toggle = document.getElementById('bs-ff-toggle');
  var panel = document.getElementById('bs-ff-panel');
  if (!toggle || !panel) return;

  var closeButton = panel.querySelector('[data-bs-ff-close]');
  var resetButton = panel.querySelector('[data-bs-ff-reset]');
  var groups = panel.querySelectorAll('[data-bs-ff-group]');
  var narrow = window.matchMedia ? window.matchMedia('(max-width: 575.98px)').matches : false;

  function setPanel(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function setGroup(button, body, open) {
    button.setAttribute('aria-expanded', open ? 'true' : 'false');
    body.hidden = !open;
  }

  function checked(scope) {
    return scope.querySelectorAll('input[name="filter[]"]:checked');
  }

  function syncCounts() {
    Array.prototype.forEach.call(groups, function (group) {
      var badge = group.querySelector('.bs-ff-gt .bs-ff-count');
      var n = checked(group).length;
      if (badge) {
        badge.textContent = String(n);
        badge.hidden = n === 0;
      }
    });
    var total = checked(panel).length;
    var mainBadge = toggle.querySelector('.bs-ff-count');
    if (mainBadge) {
      mainBadge.textContent = String(total);
      mainBadge.hidden = total === 0;
    }
    if (resetButton) resetButton.hidden = total === 0;
  }

  Array.prototype.forEach.call(groups, function (group, index) {
    var button = group.querySelector('.bs-ff-gt');
    var body = group.querySelector('.bs-ff-checks');
    if (!button || !body) return;
    setGroup(button, body, !narrow || index === 0);
    button.addEventListener('click', function () {
      setGroup(button, body, body.hidden);
    });
  });

  toggle.addEventListener('click', function () {
    setPanel(panel.hidden);
  });

  if (closeButton) {
    closeButton.addEventListener('click', function () {
      setPanel(false);
      toggle.focus();
    });
  }

  if (resetButton) {
    resetButton.addEventListener('click', function () {
      Array.prototype.forEach.call(checked(panel), function (input) {
        input.checked = false;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  }

  panel.addEventListener('change', syncCounts);
  syncCounts();
})();
</script>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    {% for breadcrumb in breadcrumbs %}
    {
      "@type": "ListItem",
      "position": {{ loop.index }},
      {% if loop.first %}
      "name": "Головна",
{% else %}
      "name": {{ breadcrumb.text|striptags|json_encode|raw }},
{% endif %}

      "item": {{ breadcrumb.href|replace({'&amp;': '&'})|json_encode|raw }}
    }{% if not loop.last %},{% endif %}
    {% endfor %}
  ]
}
</script>

<style>
  .bs-faq-accordion {
    margin: 24px 0;
    border: 1px solid #d7dce4;
    border-radius: 8px;
    background: #ffffff;
  }

  .bs-faq-title {
    margin: 0;
    padding: 16px 18px 8px;
    color: #111827;
    font-size: 1.25rem;
    line-height: 1.25;
  }

  .bs-faq-item {
    border-top: 1px solid #e5e7eb;
  }

  .bs-faq-question {
    margin: 0;
    font-size: 1rem;
    line-height: 1.35;
  }

  .bs-faq-toggle {
    width: 100%;
    min-height: 52px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    padding: 13px 18px;
    border: 0;
    background: #f8fafc;
    color: #111827;
    font: inherit;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
  }

  .bs-faq-toggle:hover,
  .bs-faq-toggle:focus {
    background: #eef2f7;
    color: #111827;
  }

  .bs-faq-toggle:focus {
    outline: 2px solid #64748b;
    outline-offset: -2px;
  }

  .bs-faq-icon {
    width: 18px;
    height: 18px;
    flex: 0 0 18px;
    position: relative;
  }

  .bs-faq-icon::before,
  .bs-faq-icon::after {
    content: "";
    position: absolute;
    background: #334155;
    border-radius: 2px;
  }

  .bs-faq-icon::before {
    left: 2px;
    right: 2px;
    top: 8px;
    height: 2px;
  }

  .bs-faq-icon::after {
    left: 8px;
    top: 2px;
    bottom: 2px;
    width: 2px;
  }

  .bs-faq-toggle[aria-expanded="true"] .bs-faq-icon::after {
    display: none;
  }

  .bs-faq-panel {
    padding: 14px 18px 16px;
    color: #374151;
    background: #ffffff;
  }

  .bs-faq-panel p:last-child {
    margin-bottom: 0;
  }

  .bs-special-seo {
    margin: 0 0 22px;
  }

  @media (max-width: 767.98px) {
    .bs-faq-accordion {
      margin: 18px 0;
    }

    .bs-faq-title {
      padding: 14px 14px 7px;
      font-size: 1.15rem;
    }

    .bs-faq-toggle {
      min-height: 50px;
      padding: 12px 14px;
    }

    .bs-faq-panel {
      padding: 12px 14px 14px;
    }
  }
</style>

<script>
(function () {
  if (window.bsFaqAccordionReady) {
    return;
  }

  window.bsFaqAccordionReady = true;

  function setExpanded(button, expanded) {
    var panelId = button.getAttribute('aria-controls');
    var panel = panelId ? document.getElementById(panelId) : null;

    button.setAttribute('aria-expanded', expanded ? 'true' : 'false');

    if (panel) {
      panel.hidden = !expanded;
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-bs-faq-toggle]');

    if (!button) {
      return;
    }

    setExpanded(button, button.getAttribute('aria-expanded') !== 'true');
  });

  document.addEventListener('keydown', function (event) {
    var button = event.target.closest('[data-bs-faq-toggle]');

    if (!button || (event.key !== 'Enter' && event.key !== ' ')) {
      return;
    }

    event.preventDefault();
    button.click();
  });
})();
</script>


<script>
(function () {
  'use strict';

  var wrap = document.getElementById('bs-load-more-wrap');
  var btn = document.getElementById('bs-load-more-btn');
  var list = document.getElementById('product-list');

  if (!wrap || !btn || !list) {
    return;
  }

  var progress = document.getElementById('bs-load-more-progress');
  var fill = wrap.querySelector('.bs-load-more-progress__fill');
  var shownEl = wrap.querySelector('.bs-lm-shown');
  var totalEl = wrap.querySelector('.bs-lm-total');
  var countEl = document.getElementById('bs-load-more-count');

  function countCards() {
    return list.querySelectorAll('.col').length;
  }

  function parseTotalFromResults() {
    var resultsEl = document.querySelector('.bs-results-count');
    var text = resultsEl ? resultsEl.textContent.replace(/\u00a0/g, ' ') : '';
    var match = text.match(/із\s+(\d+)/i) || text.match(/of\s+(\d+)/i) || text.match(/(\d+)\s*$/);
    return match ? parseInt(match[1], 10) : 0;
  }

  var total = progress ? parseInt(progress.getAttribute('data-total'), 10) : 0;
  if (!total) {
    total = parseTotalFromResults();
  }

  var perPage = countCards() || 15;

  function getNextUrl(doc) {
    var el = (doc || document).querySelector('link[rel="next"]');
    return el ? el.href : null;
  }

  function setNextUrl(url) {
    var el = document.querySelector('link[rel="next"]');

    if (url) {
      if (el) {
        el.href = url;
      } else {
        var link = document.createElement('link');
        link.rel = 'next';
        link.href = url;
        document.head.appendChild(link);
      }
    } else if (el) {
      el.parentNode.removeChild(el);
    }
  }

  function refreshUI() {
    var shown = countCards();

    if (!total) {
      total = parseTotalFromResults();
    }

    if (shownEl) {
      shownEl.textContent = shown;
    }

    if (totalEl && total) {
      totalEl.textContent = total;
    }

    if (fill && total) {
      fill.style.width = Math.min(100, Math.round((shown / total) * 100)) + '%';
    }

    if (countEl) {
      var remaining = total ? Math.max(0, total - shown) : 0;
      var next = Math.min(perPage, remaining);

      if (next > 0) {
        countEl.textContent = '+' + next;
        countEl.style.display = '';
      } else {
        countEl.style.display = 'none';
      }
    }
  }

  var nextUrl = getNextUrl(document);

  if (nextUrl) {
    wrap.style.display = '';
    refreshUI();
  }

  btn.addEventListener('click', function () {
    if (!nextUrl || btn.disabled) {
      return;
    }

    btn.disabled = true;
    btn.classList.add('loading');

    fetch(nextUrl, { credentials: 'same-origin' })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('HTTP ' + response.status);
        }

        return response.text();
      })
      .then(function (html) {
        var parser = new DOMParser();
        var doc = parser.parseFromString(html, 'text/html');
        var newCols = doc.querySelectorAll('#product-list > .col');

        if (newCols.length) {
          perPage = newCols.length;
        }

        newCols.forEach(function (col) {
          list.appendChild(col);
        });

        nextUrl = getNextUrl(doc);
        setNextUrl(nextUrl);

        btn.disabled = false;
        btn.classList.remove('loading');
        refreshUI();

        if (!nextUrl) {
          wrap.style.display = 'none';
        }
      })
      .catch(function (error) {
        console.error('[bs-load-more] fetch error:', error);
        btn.disabled = false;
        btn.classList.remove('loading');
      });
  });
})();
</script>
{{ footer }}
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
{# UX-003-FILTER (2026-10-04): groups as collapsible blocks for the category filter panel (variant C). Field names,
   values and ids are unchanged, and so is the script below: a checkbox change applies the filter after 250 ms.
   #button-filter stays in the DOM, hidden — the apply-on-change already covers it. #}
<div class="bs-ff-module">
  <h2 class="visually-hidden">{{ heading_title }}</h2>
  <div class="bs-ff-groups">
    {% for filter_group in filter_groups %}
      {% set bs_ff_selected = 0 %}
      {% for filter in filter_group.filter %}
        {% if filter.filter_id in filter_category %}{% set bs_ff_selected = bs_ff_selected + 1 %}{% endif %}
      {% endfor %}
      <div class="bs-ff-g" data-bs-ff-group>
        <button type="button" class="bs-ff-gt" aria-expanded="true" aria-controls="filter-group-{{ filter_group.filter_group_id }}">
          <span>{{ filter_group.name }}</span>
          <span class="bs-ff-count"{% if not bs_ff_selected %} hidden{% endif %}>{{ bs_ff_selected }}</span>
          <svg class="bs-ff-gt__chev" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <div id="filter-group-{{ filter_group.filter_group_id }}" class="bs-ff-checks">
          {% for filter in filter_group.filter %}
            <label class="bs-ff-ck" for="input-filter-{{ filter.filter_id }}">
              <input type="checkbox" name="filter[]" value="{{ filter.filter_id }}" id="input-filter-{{ filter.filter_id }}"{% if filter.filter_id in filter_category %} checked{% endif %}/>
              <span>{{ filter.name }}</span>
            </label>
          {% endfor %}
        </div>
      </div>
    {% endfor %}
  </div>
  <button type="button" id="button-filter" class="btn btn-primary" hidden>{{ button_filter }}</button>
</div>
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
/* === UX-003-FILTER: category row + filter panel, variant C (2026-10-04) ===== */
/* product/category.twig (row, panel, panel script) + extension/opencart/…/module/filter.twig (groups). One row in
   the category header card: subcategory chips | «Фільтр» | sort; the panel opens under it with every filter group,
   each collapsible. Replaces the desktop toolbar (.bs-cat-header__toolbar / .bs-segmented), the mobile
   subcategory grid (.bs-subcat-tabs), the mobile action row and the side filter column on category pages.
   Side padding follows .bs-cat-header__hero: 14px ≤640, 16px to 991, 22px from 992. */
.bs-ff-row {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 0 14px 14px;
}
.bs-ff-chips {
  display: flex;
  flex: 1 1 auto;
  gap: 6px;
  min-width: 0;
  padding: 2px 0;
  overflow-x: auto;
  scrollbar-width: none;
}
.bs-ff-chips::-webkit-scrollbar {
  display: none;
}
#product-category .bs-ff-chip {
  display: inline-flex;
  flex: 0 0 auto;
  align-items: center;
  gap: 8px;
  min-height: 44px;
  padding: 0 12px;
  border: 1px solid var(--bs-line);
  border-radius: 10px;
  background: #fff;
  color: var(--bs-blue);
  font-size: 14px;
  font-weight: 700;
  text-decoration: none;
  white-space: nowrap;
}
#product-category .bs-ff-chip:hover {
  text-decoration: none;
}
#product-category .bs-ff-chip.is-active {
  border-color: var(--bs-blue);
  box-shadow: inset 0 0 0 1px var(--bs-blue);
  color: var(--bs-ink);
}
.bs-ff-chip__count {
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--bs-gold-soft);
  color: var(--bs-warning-fg);
  font-size: 11.5px;
  font-weight: 700;
}
.bs-cat-header--onepiece .bs-ff-chip__count {
  background: var(--bs-blue-soft);
  color: var(--bs-onepiece);
}
.bs-ff-tools {
  display: flex;
  flex: 0 0 auto;
  gap: 8px;
  margin-left: auto;
}

/* «Фільтр» */
.bs-ff-btn {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  height: 44px;
  min-width: 44px;
  padding: 0 14px;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  background: #fff;
  color: var(--bs-ink);
  font: inherit;
  font-size: 14.5px;
  font-weight: 700;
  cursor: pointer;
}
.bs-ff-btn:hover {
  background: var(--bs-line-2);
}
.bs-ff-btn[aria-expanded="true"] {
  border-color: var(--bs-blue);
  background: var(--bs-blue-soft);
  color: var(--bs-blue);
}
.bs-ff-btn:focus-visible,
.bs-ff-gt:focus-visible,
.bs-ff-close:focus-visible,
.bs-ff-reset:focus-visible {
  outline: 2px solid var(--bs-blue);
  outline-offset: 2px;
}
.bs-ff-btn__chev,
.bs-ff-gt__chev,
.bs-ff-close__chev {
  flex: 0 0 auto;
  transition: transform 0.15s;
}
.bs-ff-btn[aria-expanded="true"] .bs-ff-btn__chev,
.bs-ff-gt[aria-expanded="true"] .bs-ff-gt__chev,
.bs-ff-close__chev {
  transform: rotate(180deg);
}
.bs-ff-count {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 6px;
  border-radius: 999px;
  background: var(--bs-blue);
  color: #fff;
  font-size: 11.5px;
  font-weight: 800;
  line-height: 1;
}
.bs-ff-count[hidden] {
  display: none;
}

/* Sort: the native select, unchanged behaviour (onchange → location). */
.bs-ff-sort {
  position: relative;
  flex: 0 0 auto;
  width: 200px;
}
.bs-ff-sort .bs-select {
  height: 44px;
  padding-block: 0;
  color: var(--bs-ink-2);
  font-size: 14px;
  font-weight: 600;
}
.bs-ff-sort__ic {
  display: none;
}

/* Panel */
.bs-ff-panel {
  margin: 0 14px;
  padding: 4px 0 14px;
  border-top: 1px solid var(--bs-line);
}
.bs-ff-panel[hidden] {
  display: none;
}
.bs-ff-groups {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
}
.bs-ff-g {
  border-bottom: 1px solid var(--bs-line-2);
}
.bs-ff-gt {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 48px;
  padding: 0 4px;
  border: 0;
  background: none;
  color: var(--bs-ink);
  font: inherit;
  font-size: 14.5px;
  font-weight: 700;
  text-align: left;
  cursor: pointer;
}
.bs-ff-gt__chev {
  margin-left: auto;
  color: var(--bs-ink-3);
}
.bs-ff-checks {
  padding: 0 4px 10px;
}
.bs-ff-checks[hidden] {
  display: none;
}
.bs-ff-ck {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 36px;
  color: var(--bs-ink-2);
  font-size: 14px;
  cursor: pointer;
}
.bs-ff-ck input {
  appearance: none;
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  margin: 0;
  border: 1.5px solid var(--bs-ink-4);
  border-radius: 4px;
  background: #fff;
  cursor: pointer;
}
.bs-ff-ck input:checked {
  border-color: var(--bs-blue);
  background: var(--bs-blue) url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'><path d='M2.5 6.2 5 8.5l4.5-5' stroke='white' stroke-width='2' fill='none' stroke-linecap='round' stroke-linejoin='round'/></svg>") center / 12px 12px no-repeat;
}
.bs-ff-ck input:focus-visible {
  outline: 2px solid var(--bs-blue);
  outline-offset: 2px;
}
.bs-ff-foot {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  padding-top: 12px;
}
.bs-ff-reset {
  min-height: 44px;
  padding: 0 4px;
  border: 0;
  background: transparent;
  color: var(--bs-ink-3);
  font: inherit;
  font-size: 13.5px;
  font-weight: 600;
  text-decoration: underline;
  text-underline-offset: 3px;
  cursor: pointer;
}
.bs-ff-close {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  min-height: 44px;
  margin-left: auto;
  padding: 0 12px;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  background: #fff;
  color: var(--bs-ink-2);
  font: inherit;
  font-size: 13.5px;
  font-weight: 700;
  cursor: pointer;
}

/* ≤575: «Фільтр» and sort become 44×44 icon buttons. The sort icon sits under a transparent native select, so the
   select keeps its own picker and its onchange; position:absolute is that overlay. */
@media (max-width: 575.98px) {
  .bs-ff-btn {
    width: 44px;
    padding: 0;
  }
  .bs-ff-btn__label {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
  }
  .bs-ff-btn__chev {
    display: none;
  }
  .bs-ff-btn .bs-ff-count {
    position: absolute;
    top: -6px;
    right: -6px;
  }
  .bs-ff-sort {
    width: 44px;
    height: 44px;
  }
  .bs-ff-sort__ic {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--bs-line);
    border-radius: 8px;
    background: #fff;
    color: var(--bs-ink);
    pointer-events: none;
  }
  .bs-ff-sort .bs-select {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }
  .bs-ff-sort:focus-within .bs-ff-sort__ic {
    outline: 2px solid var(--bs-blue);
    outline-offset: 2px;
  }
}
@media (min-width: 641px) {
  .bs-ff-row {
    padding: 0 16px 16px;
  }
  .bs-ff-panel {
    margin: 0 16px;
  }
}
@media (min-width: 768px) {
  .bs-ff-groups {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 24px;
  }
}
/* Below 992 the removed mobile action row used to hold the grid 14px off the header card (≥992 has UI-CAT-GAP). */
@media (max-width: 991.98px) {
  #product-category .bs-cat-header {
    margin-bottom: 14px;
  }
}
/* ≥992: four product columns now that the side column is gone. Stock common.js rewrites #product-list's classes
   to row-cols-lg-3 on every load (its list/grid localStorage switch, which has no buttons on this page), so the
   template's row-cols-lg-4 never survives; the column width is set for that grid class instead. List mode
   (.product-list) is untouched; the card rules keyed on .row-cols-lg-3 in stylesheet.css keep applying. */
@media (min-width: 992px) {
  #product-category #product-list.row-cols-lg-3 > * {
    flex: 0 0 auto;
    width: 25%;
  }
  .bs-ff-row {
    padding: 0 22px 18px;
  }
  .bs-ff-panel {
    margin: 0 22px;
    padding-bottom: 18px;
  }
  .bs-ff-chips {
    flex: 0 1 auto;
    flex-wrap: wrap;
    padding: 4px;
    overflow: visible;
    border-radius: 10px;
    background: var(--bs-line-2);
  }
  #product-category .bs-ff-chip {
    min-height: 40px;
    border: 0;
    background: transparent;
  }
  #product-category .bs-ff-chip:hover {
    background: #fff;
  }
  #product-category .bs-ff-chip.is-active {
    background: #fff;
    box-shadow: 0 1px 2px rgba(17, 24, 39, 0.08), 0 0 0 1px var(--bs-line);
    color: var(--bs-ink);
  }
  .bs-ff-sort {
    width: 220px;
  }
  .bs-ff-groups {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .bs-ff-g {
    border-bottom: 0;
  }
  .bs-ff-gt {
    padding: 0;
  }
  .bs-ff-checks {
    padding: 0 0 8px;
  }
  .bs-ff-foot {
    margin-top: 4px;
    border-top: 1px solid var(--bs-line-2);
  }
}
/* === /UX-003-FILTER === */
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
