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
    SEARCH => '04d2bad20afe6f1f5f72b774fc5a3fdd45f3b8c997a5784a67c27708c2732cb5',
    DSCSS  => '04b82391310bd504121897bbbcc39a10950e6fc4929479455287d78d5e211a53',
    HEADER => 'c3da822af55bde668cb88fd0c299375ecaef9c1dcc568d032bdce1b86b8e2b21',
);


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
{{ header }}
{# UX-009-SP (2026-10-04): search results page. The form sits in «Змінити пошук» (open when nothing was found),
   «Порівняння товарів» is gone, list/grid/sort/limit are DS controls, and an empty result offers next steps.
   Every stock id and the inline script at the bottom are unchanged; styles live in boostershop-ds.css
   (section UX-009-SP). #}
<div id="product-search" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
    {% endfor %}
  </ul>
  <div class="row">
    {{ column_left }}
    <div id="content" class="col">
      {{ content_top }}
      <h1>{{ heading_title }}</h1>
      <details class="bs-sp-refine"{% if not products %} open{% endif %}>
        <summary class="bs-btn bs-btn-secondary bs-sp-refine__btn">
          <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M2 4h7M12 4h2M2 12h2M7 12h7M9 2.5v3M5 10.5v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
          <span>Змінити пошук</span>
          <svg class="bs-sp-refine__chev" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="m4 6 4 4 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </summary>
        <div class="bs-sp-form">
          <div class="bs-sp-form__group">
            <label for="input-search" class="bs-sp-label">{{ entry_search|trim(':') }}</label>
            <input type="text" name="search" value="{{ search }}" placeholder="{{ text_keyword }}" id="input-search" class="form-control"/>
            <div class="form-check">
              <input type="checkbox" name="description" value="1" id="input-description" class="form-check-input"{% if description %} checked{% endif %}/>
              <label for="input-description" class="form-check-label">{{ entry_description }}</label>
            </div>
          </div>
          <div class="bs-sp-form__group">
            <label for="input-category" class="bs-sp-label">Категорія</label>
            <select name="category_id" id="input-category" class="form-select">
              <option value="0">{{ text_category }}</option>
              {% for category_1 in categories %}
                <option value="{{ category_1.category_id }}"{% if category_1.category_id == category_id %} selected{% endif %}>{{ category_1.name }}</option>
                {% for category_2 in category_1.children %}
                  <option value="{{ category_2.category_id }}"{% if category_2.category_id == category_id %} selected{% endif %}>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ category_2.name }}</option>
                  {% for category_3 in category_2.children %}
                    <option value="{{ category_3.category_id }}"{% if category_3.category_id == category_id %} selected{% endif %}>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ category_3.name }}</option>
                  {% endfor %}
                {% endfor %}
              {% endfor %}
            </select>
            <div class="form-check">
              <input type="checkbox" name="sub_category" value="1" id="input-sub-category" class="form-check-input"{% if sub_category %} checked{% endif %}/>
              <label for="input-sub-category" class="form-check-label">{{ text_sub_category }}</label>
            </div>
          </div>
          <div class="bs-sp-form__act">
            <button type="button" id="button-search" class="bs-btn bs-sp-submit">{{ button_search }}</button>
          </div>
        </div>
      </details>
      <h2 class="visually-hidden">{{ text_search }}</h2>
      {% if products %}
        <div id="display-control" class="bs-sp-dc">
          <div class="bs-sp-view" role="group" aria-label="Вигляд списку">
            <button type="button" id="button-list" class="bs-sp-vbtn" data-bs-toggle="tooltip" title="{{ button_list }}" aria-label="{{ button_list }}"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><path d="M6.5 4.5h9M6.5 9h9M6.5 13.5h9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><path d="M2.5 4.5h1M2.5 9h1M2.5 13.5h1" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
            <button type="button" id="button-grid" class="bs-sp-vbtn" data-bs-toggle="tooltip" title="{{ button_grid }}" aria-label="{{ button_grid }}"><svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true" focusable="false"><rect x="2.5" y="2.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6"/><rect x="10.5" y="2.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6"/><rect x="2.5" y="10.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6"/><rect x="10.5" y="10.5" width="5" height="5" rx="1" stroke="currentColor" stroke-width="1.6"/></svg></button>
          </div>
          <div class="bs-sp-sel">
            <label for="input-sort">{{ text_sort|trim(':') }}</label>
            <select id="input-sort" class="form-select" onchange="location = this.value;">
              {% for sorts in sorts %}
                <option value="{{ sorts.href }}"{% if sorts.value == '%s-%s'|format(sort, order) %} selected{% endif %}>{{ sorts.text }}</option>
              {% endfor %}
            </select>
          </div>
          <div class="bs-sp-sel bs-sp-sel--limit">
            <label for="input-limit">{{ text_limit|trim(':') }}</label>
            <select id="input-limit" class="form-select" onchange="location = this.value;">
              {% for limits in limits %}
                <option value="{{ limits.href }}"{% if limits.value == limit %} selected{% endif %}>{{ limits.text }}</option>
              {% endfor %}
            </select>
          </div>
        </div>
        <div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">
          {% for product in products %}
            <div class="col mb-3">{{ product }}</div>
          {% endfor %}
        </div>
        <div class="row">
          <div class="col-sm-6 text-start">{{ pagination }}</div>
          <div class="col-sm-6 text-end">{{ results }}</div>
        </div>
      {% else %}
        <div class="bs-empty" role="status">
          <div class="bs-empty__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none">
              <path d="M10.8 17.4a6.4 6.4 0 1 1 0-12.8 6.4 6.4 0 0 1 0 12.8Z" stroke-width="1.7"/>
              <path d="M15.5 15.5 20 20" stroke-width="1.7" stroke-linecap="round"/>
            </svg>
          </div>
          <p class="bs-empty__title">{{ text_no_results }}</p>
        </div>
        <section class="bs-sp-next" aria-labelledby="bs-sp-next-title">
          <h3 id="bs-sp-next-title" class="bs-sp-next__title">Подивіться розділи</h3>
          <div class="bs-sp-next__list">
            <a href="/catalog/pokemon" class="bs-sp-next__link"><span class="bs-sp-next__dot" style="background:#C68A00" aria-hidden="true"></span>Pokémon TCG<svg class="bs-sp-next__chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            <a href="/catalog/one-piece" class="bs-sp-next__link"><span class="bs-sp-next__dot" style="background:#1E40AF" aria-hidden="true"></span>One Piece Card Game<svg class="bs-sp-next__chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            <a href="/catalog/more-tcg" class="bs-sp-next__link"><span class="bs-sp-next__dot" style="background:var(--bs-other-tcg)" aria-hidden="true"></span>Інші TCG<svg class="bs-sp-next__chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
            <a href="/catalog/acsesuary" class="bs-sp-next__link"><span class="bs-sp-next__dot" style="background:var(--bs-accessories)" aria-hidden="true"></span>Аксесуари<svg class="bs-sp-next__chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
          </div>
          <div class="bs-sp-next__tg">
            <p>Не знайшли? Напишіть у Telegram — привеземо під замовлення</p>
            <a href="https://telegram.me/BoosterShop_Support_bot" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary"><svg width="17" height="17" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 0.4-1 1.7 0 2l4.5 1.5 1.7 5.3c0.2 0.7 1.1 0.9 1.6 0.4l2.6-2.4 4.7 3.4c0.6 0.4 1.4 0.1 1.6-0.6l3-14.6c0.2-1-0.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-0.3 3-1.2-3.9 9-6.4c0.3-0.2 0.6 0.1 0.4 0.2Z"/></svg> Написати в Telegram</a>
          </div>
        </section>
      {% endif %}
      {{ content_bottom }}
    </div>
    {{ column_right }}
  </div>
</div>
<script type="text/javascript"><!--
$('#button-search').bind('click', function() {
    url = 'index.php?route=product/search&language={{ language }}';

    var search = $('#input-search').val();

    if (search) {
        url += '&search=' + encodeURIComponent(search);
    }

    var category_id = $('#input-category').prop('value');

    if (category_id > 0) {
        url += '&category_id=' + encodeURIComponent(category_id);
    }

    var sub_category = $('#input-sub-category:checked').prop('value');

    if (sub_category) {
        url += '&sub_category=1';
    }

    var description = $('#input-description:checked').prop('value');

    if (description) {
        url += '&description=1';
    }

    location = url;
});

$('#input-search').bind('keydown', function(e) {
    if (e.keyCode == 13) {
        $('#button-search').trigger('click');
    }
});

$('#input-category').on('change', function() {
    $('#input-sub-category').prop('disabled', (this.value == '0' ? true : false));
});

$('#input-category').trigger('change');
//--></script>
{{ footer }}
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
/* === UX-009-SP: search results page (2026-10-04) ========================== */
/* product/search.twig. The stock fields keep their ids and the template's inline script; they sit in
   <details class="bs-sp-refine">, open when nothing was found. Scoped to #product-search where a rule has to
   beat Bootstrap's form classes or `.bs a` (0,1,1), which otherwise paints links navy and underlines them. */
#product-search h1 {
  margin: 0 0 12px;
  font-size: 24px;
  line-height: 1.2;
}
@media (min-width: 769px) {
  #product-search h1 {
    font-size: 30px;
  }
}

/* «Змінити пошук» */
.bs-sp-refine > summary {
  list-style: none;
}
.bs-sp-refine > summary::-webkit-details-marker {
  display: none;
}
.bs-sp-refine__btn {
  width: 100%;
  user-select: none;
}
.bs-sp-refine__btn:focus-visible {
  outline: 2px solid var(--bs-blue);
  outline-offset: 2px;
}
.bs-sp-refine__chev {
  transition: transform 0.15s;
}
.bs-sp-refine[open] .bs-sp-refine__chev {
  transform: rotate(180deg);
}
.bs-sp-form {
  display: grid;
  gap: 14px;
  margin-top: 10px;
  padding: 16px;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-lg);
}
.bs-sp-label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: var(--bs-ink-2);
}
#product-search :is(.bs-sp-form, .bs-sp-sel) :is(.form-control, .form-select) {
  height: 44px;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  color: var(--bs-ink);
  font-size: 15px;
}
#product-search :is(.bs-sp-form, .bs-sp-sel) :is(.form-control, .form-select):focus {
  border-color: var(--bs-blue);
  box-shadow: 0 0 0 3px var(--bs-blue-soft);
}
#product-search .bs-sp-form .form-check {
  display: flex;
  align-items: center;
  gap: 8px;
  min-height: 36px;
  margin: 6px 0 0;
  padding-left: 0;
  font-size: 13.5px;
  color: var(--bs-ink-2);
}
#product-search .bs-sp-form .form-check-input {
  float: none;
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
  margin: 0;
  border: 1.5px solid var(--bs-ink-4);
  border-radius: 4px;
}
#product-search .bs-sp-form .form-check-input:checked {
  background-color: var(--bs-blue);
  border-color: var(--bs-blue);
}
.bs-sp-form__act {
  display: flex;
}
.bs-sp-submit {
  flex: 1 1 auto;
  background: var(--bs-blue);
  color: #fff;
}
.bs-sp-submit:hover,
.bs-sp-submit:focus-visible {
  background: #16245C;
  color: #fff;
}
@media (min-width: 769px) {
  .bs-sp-refine__btn {
    width: auto;
  }
  .bs-sp-form {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 16px 20px;
    padding: 20px 22px;
  }
  .bs-sp-form__act {
    grid-column: 1 / -1;
    justify-content: flex-end;
  }
  .bs-sp-submit {
    flex: 0 0 auto;
    min-width: 160px;
  }
}

/* List / grid, sort, limit */
.bs-sp-dc {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 10px;
  margin: 16px 0;
}
.bs-sp-view {
  display: none;
}
.bs-sp-sel {
  display: grid;
  gap: 4px;
  min-width: 0;
}
.bs-sp-sel > label {
  font-size: 12.5px;
  font-weight: 600;
  color: var(--bs-ink-3);
}
.bs-sp-vbtn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 1px solid var(--bs-line);
  background: #fff;
  color: var(--bs-ink-3);
  cursor: pointer;
}
.bs-sp-vbtn:first-child {
  border-radius: 8px 0 0 8px;
}
.bs-sp-vbtn:last-child {
  border-left: 0;
  border-radius: 0 8px 8px 0;
}
.bs-sp-vbtn:hover {
  color: var(--bs-ink);
}
.bs-sp-vbtn.active {
  background: var(--bs-blue-soft);
  color: var(--bs-blue);
}
.bs-sp-vbtn:focus-visible {
  outline: 2px solid var(--bs-blue);
  outline-offset: 2px;
}
@media (min-width: 769px) {
  .bs-sp-dc {
    display: flex;
    align-items: flex-end;
    gap: 12px;
  }
  .bs-sp-view {
    display: flex;
    margin-right: auto;
  }
  .bs-sp-sel {
    width: 260px;
  }
  .bs-sp-sel--limit {
    width: 130px;
  }
}

/* Nothing found: next steps under the RD-06 empty state */
.bs-sp-next {
  margin-top: 4px;
}
.bs-sp-next__title {
  margin: 0 0 10px;
  font-size: 15px;
  font-weight: 800;
  color: var(--bs-ink);
}
.bs-sp-next__list {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 8px;
}
#product-search .bs-sp-next__link {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 0 14px;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: 10px;
  color: var(--bs-ink);
  font-weight: 700;
  text-decoration: none;
}
#product-search .bs-sp-next__link:hover {
  border-color: var(--bs-blue);
  color: var(--bs-blue);
}
.bs-sp-next__dot {
  flex: 0 0 auto;
  width: 9px;
  height: 9px;
  border-radius: 2px;
}
.bs-sp-next__chev {
  flex: 0 0 auto;
  margin-left: auto;
  color: var(--bs-ink-4);
}
.bs-sp-next__tg {
  display: grid;
  gap: 12px;
  margin-top: 16px;
  padding: 16px;
  background: var(--bs-blue-soft);
  border-radius: var(--bs-r-lg);
}
.bs-sp-next__tg p {
  margin: 0;
  font-weight: 600;
  color: var(--bs-ink);
}
#product-search .bs-sp-next__tg .bs-btn-secondary {
  color: var(--bs-ink);
  text-decoration: none;
}
@media (min-width: 576px) {
  .bs-sp-next__list {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 769px) {
  .bs-sp-next__tg {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: center;
    padding: 18px 22px;
  }
}
@media (min-width: 1024px) {
  .bs-sp-next__list {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
}
/* === /UX-009-SP === */
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
