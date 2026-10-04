<?php
declare(strict_types=1);

/**
 * UX-009 — live search suggestions
 * =============================================================================
 * Chain     : runner 4. Requires runners 1–3 and 3b (RD-UX-qa-followups_20261004) applied first.
 * Handoff   : handoffs/handoff_UX-009_live-search_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - макети.html», screen «Живий пошук» (ux-b-search.jsx LiveList /
 *             MobileOverlay, ux-b.css .ub-ls-*), states focus / loading / results / none / error.
 * Author    : Claude Code · 2026-10-04
 * Risk      : low — every page's header search. No module file, controller, URL or form markup touched.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-009_live-search_20261004.php
 *
 * WHAT CHANGES
 *   boostershop-ds.css
 *     - new section «UX-009-LS» after the mobile dropdown block: compact rows (56px thumbnail | name clamped to 2
 *       lines over the price; discounted: new price --bs-danger + struck-through old 13px), no description
 *       (owner-confirmed fix for the over-tall mobile list), group headings 11.5px uppercase, category rows 44px
 *       with a chevron, «Усі результати» as a 44px bordered row with a right arrow (the module's Font Awesome
 *       caret hidden), CSS spinner on .ps-live-search-item-loading (its Font Awesome <i> hidden), no-results
 *       and error state rows. Desktop: dropdown 6px under the field, radius --bs-r-lg, shadow
 *       0 12px 32px rgba(17,24,39,0.16), scrolls inside itself below the sticky header.
 *       All selectors are scoped to #ps-live-search: the module's stylesheet loads after ds.css.
 *     - «A — MOBILE: ps-live-search dropdown position override» (source rule): box-shadow → none, border 0,
 *       padding 0 0 12px — full-width list under the field without frame or shadow.
 *   common/header.twig — the pslivesearch init only (the module file is not changed):
 *     - $.ajax gets timeout 8000 and an error handler. jQuery 'abort' is ignored, and a response or error is
 *       rendered only while the dropdown is open and the input still holds the query it was made for, so a late
 *       reply never overwrites newer results.
 *     - error / timeout / unusable JSON (the module throws on it) → one row, role="alert": «Не вдалося
 *       завантажити підказки» + secondary button «Шукати на сторінці результатів» that submits the existing
 *       search form (action index.php + hidden route/language + search). mousedown is cancelled on it so the
 *       input's focusout does not close the list before the click lands (Safari does not focus buttons on click).
 *     - no item at all → the whole list becomes one row: «Нічого не знайдено за «…»» (query inserted with
 *       .text(), never as HTML) + «Перевірте написання або спробуйте коротший запит.»; the three per-section
 *       «Нічого не знайдено» and «Усі результати» are gone with it.
 *     - after a render: product rows get bs-ls-product, other rows bs-ls-link + chevron; «Усі результати» gets
 *       the arrow. The loading row gets role="status" and a visually hidden «Завантаження…».
 *   ds.css ?v= token in header.twig (convention 8).
 *   Hooks kept (asserted): #ps-live-search-input, #ps-live-search, .ps-live-search-container,
 *   data-live-search-target, form action + hidden route/language, #bs-msearch, [data-bs-search-clear].
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: the module's vendor CSS (Bootstrap variables, column layout with the description
 *   and a right-hand price column). It is not editable here (module files are out of scope), so the DS section
 *   restyles it by specificity. No !important. Mobile edit made at the existing source rule. position values
 *   are the existing ones (absolute from the module, fixed in the mobile block); only top on desktop moves 6px.
 *   Magic numbers come from the approved design (56 thumb, 68 row, 44 rows/buttons, 8000ms timeout per handoff).
 *   No setTimeout added.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on both targets (3b output) → marker → anchors → hooks asserted → hazard scan → Twig parse gate
 *   on header.twig → CSS balance gate → backup → write → restore-all on failure.
 *   Idempotent: marker «UX-009-LS» in boostershop-ds.css and header.twig. Self-deletes.
 *   ROLLBACK: copy both files back from _patch_backups/UX-009_live-search_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runners 5–7 are not applied.
 *   TRIGGER: suggestions do not appear, a click on a suggestion does nothing, JS error from the header init.
 * =============================================================================
 */

const PATCH_ID = 'UX-009_live-search_20261004';
const MARKER   = 'UX-009-LS';
const TOKEN    = 'ux009ls-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    DSCSS  => 'c0feabc61b0f04407c178e0d6c87ca20343dfeb714a8e92fc4385bd6c9b62ee6',
    HEADER => '8443fdc87ea0410f229dcab606361e86aed6e5920d2774f9a0f6c7d3bf4c22b3',
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
foreach (array(DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS, HEADER), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- header.twig: init ---------------------------------------------------- */
$h = $files[HEADER]['text'];
$hooks = array('id="ps-live-search-input"', 'id="ps-live-search"', 'ps-live-search-container', 'data-live-search-target="ps-live-search"',
    'action="index.php" method="get" role="search"', '<input type="hidden" name="route" value="product/search">',
    '<input type="hidden" name="language" value="{{ lang }}">', 'name="search"', 'id="bs-msearch"', 'data-bs-search-clear',
    "text_no_results: 'Нічого не знайдено'", "text_all_product_results: 'Усі результати'");
$hookCounts = array();
foreach ($hooks as $hook) $hookCounts[$hook] = substr_count($h, $hook);
foreach ($hookCounts as $hook => $n) if ($n < 1) fail('hook_missing=' . $hook);

$h = replace_one($h,
    "    \$input.data('bs-ps-live-search-ready', true);\n"
    . "    \$input.pslivesearch({\n"
    . "      source: function (request, response) {\n"
    . "        \$.ajax({\n"
    . "          url: 'index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=' + encodeURIComponent(request),\n"
    . "          dataType: 'json',\n"
    . "          success: function (json) { response(json || {}); }\n"
    . "        });\n"
    . "      },\n",
    <<<'JS'
    $input.data('bs-ps-live-search-ready', true);

    // UX-009-LS (2026-10-04): loading / no-results / error states and row classes on top of the module's render.
    // ps_live_search.js is not changed. A reply is used only while the list is open and the input still holds the
    // query it was made for, so a late reply never overwrites newer results.
    var $lsList = $('#ps-live-search');
    var lsArrow = '<svg class="bs-ls-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var lsChevron = '<svg class="bs-ls-chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var lsIcons = {
      none: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M14 14l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
      error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4.5M12 17.5v0.01"/></svg>'
    };

    function lsIsCurrent(request) {
      return $lsList.hasClass('show') && $input.val() === request;
    }

    function lsState(kind, request) {
      var $row = $('<li class="bs-ls-state"></li>').addClass('bs-ls-state--' + kind).attr('role', kind === 'error' ? 'alert' : 'status');
      $row.append($('<span class="bs-ls-state__ic"></span>').html(lsIcons[kind]));
      if (kind === 'error') {
        $row.append($('<strong></strong>').text('Не вдалося завантажити підказки'));
        $row.append($('<button type="button" class="bs-btn bs-btn-secondary bs-ls-state__btn" tabindex="0"></button>').text('Шукати на сторінці результатів').append(lsArrow));
      } else {
        $row.append($('<strong></strong>').text('Нічого не знайдено за «' + request + '»'));
        $row.append($('<p></p>').text('Перевірте написання або спробуйте коротший запит.'));
      }
      $lsList.empty().append($row);
    }

    function lsDecorate(request) {
      var $items = $lsList.find('.ps-live-search-item');
      if (!$items.length) {
        lsState('none', request);
        return;
      }
      $items.each(function () {
        var $item = $(this);
        if ($item.find('strong.name').length) $item.addClass('bs-ls-product');
        else $item.addClass('bs-ls-link').append(lsChevron);
      });
      $lsList.find('a.ps-live-search-more').append(lsArrow);
    }

    $lsList.on('mousedown', '.bs-ls-state__btn', function (event) {
      event.preventDefault();
    });
    $lsList.on('click', '.bs-ls-state__btn', function () {
      var form = $input.closest('form').get(0);
      if (!form) return;
      if (form.requestSubmit) form.requestSubmit();
      else form.submit();
    });

    $input.pslivesearch({
      source: function (request, response) {
        $lsList.find('.ps-live-search-item-loading').attr('role', 'status').append('<span class="visually-hidden">Завантаження…</span>');
        $.ajax({
          url: 'index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=' + encodeURIComponent(request),
          dataType: 'json',
          timeout: 8000,
          success: function (json) {
            if (!lsIsCurrent(request)) return;
            try {
              response(json || {});
            } catch (error) {
              lsState('error', request);
              return;
            }
            lsDecorate(request);
          },
          error: function (xhr, status) {
            if (status === 'abort' || !lsIsCurrent(request)) return;
            lsState('error', request);
          }
        });
      },

JS
    , 'hdr_ls_init');
$h = bust_token($h, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
foreach ($hookCounts as $hook => $n) count_exact($h, $hook, $n, 'hdr_hook_kept');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $h), explode("\n", $files[HEADER]['text']))), HEADER);

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];
count_exact($ds, 'UX-009-LS', 0, 'ds_no_section_yet');
$ds = replace_one($ds,
    "    max-height: calc(100dvh - var(--bs-header-h, 63px));\n"
    . "    overflow-y: auto;\n"
    . "    border-radius: 0;\n"
    . "    z-index: 60;\n"
    . "    box-shadow: var(--bs-sh-pop);\n"
    . "  }\n"
    . "}\n",
    "    max-height: calc(100dvh - var(--bs-header-h, 63px));\n"
    . "    overflow-y: auto;\n"
    . "    border-radius: 0;\n"
    . "    z-index: 60;\n"
    . "    /* UX-009-LS: full-width list under the field, no frame or shadow (was box-shadow: var(--bs-sh-pop)). */\n"
    . "    border: 0;\n"
    . "    box-shadow: none;\n"
    . "    padding: 0 0 12px;\n"
    . "  }\n"
    . "}\n",
    'ds_mobile_dropdown');
$section = <<<'BS_LS_CSS'
/* === UX-009-LS: live search suggestions (2026-10-04) ====================== */
/* The module's own stylesheet (extension/ps_live_search/…/ps_live_search.css) loads after this file and styles
   the list on Bootstrap variables. These rules are scoped to #ps-live-search so they win on specificity — no
   !important. bs-ls-* classes and the state rows are added by the init in common/header.twig after the module
   has rendered; ps_live_search.js itself is not changed. Mobile (≤768) placement stays in the «A — MOBILE:
   ps-live-search dropdown position override» block above. */
#ps-live-search.ps-live-search-list {
  padding: 6px 0;
  color: var(--bs-ink);
  background: #fff;
  font-size: 14px;
  line-height: 1.4;
}
@media (min-width: 769px) {
  #ps-live-search.ps-live-search-list {
    top: calc(100% + 6px);
    border: 1px solid var(--bs-line);
    border-radius: var(--bs-r-lg);
    box-shadow: 0 12px 32px rgba(17, 24, 39, 0.16);
    max-height: calc(100vh - var(--bs-header-sticky-h, 69px) - 24px);
    overflow-y: auto;
    overscroll-behavior: contain;
  }
}
#ps-live-search .ps-live-search-subheader {
  padding: 10px 16px;
  font-size: 13px;
  color: var(--bs-ink-3);
  background: none;
  border-bottom: 1px solid var(--bs-line-2);
}
#ps-live-search .ps-live-search-subheader > output {
  color: var(--bs-ink);
  font-weight: 700;
}
#ps-live-search .ps-live-search-header {
  margin: 0;
  padding: 12px 16px 4px;
  border: 0;
  font-size: 11.5px;
  font-weight: 700;
  line-height: 1.4;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--bs-ink-3);
}

/* Category / manufacturer / information rows: 44px, chevron appended by the init. */
#ps-live-search .ps-live-search-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 44px;
  padding: 0 16px;
  color: var(--bs-ink-2);
  font-weight: 600;
  text-decoration: none;
}
#ps-live-search .ps-live-search-item:is(:hover, :focus) {
  background: var(--bs-line-2);
}
#ps-live-search .ps-live-search-item:focus-visible {
  outline: 2px solid var(--bs-blue);
  outline-offset: -2px;
}
#ps-live-search .bs-ls-link .thumb {
  display: none;
}
#ps-live-search .bs-ls-chev {
  flex: 0 0 auto;
  color: var(--bs-ink-4);
}

/* Product rows: 56px thumbnail | name (2 lines) over price. The description is not shown (owner, 2026-10-04). */
#ps-live-search .description {
  display: none;
}
#ps-live-search .bs-ls-product {
  display: grid;
  grid-template-columns: 56px minmax(0, 1fr);
  grid-template-areas: "thumb info" "thumb prices";
  align-content: center;
  align-items: center;
  gap: 3px 12px;
  min-height: 68px;
  padding: 6px 16px;
  font-weight: 400;
}
#ps-live-search .bs-ls-product .thumb {
  grid-area: thumb;
  width: 56px;
  height: 56px;
  object-fit: contain;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  background: #fff;
}
#ps-live-search .bs-ls-product .info {
  grid-area: info;
  display: block;
  min-width: 0;
}
#ps-live-search .bs-ls-product .name {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  font-size: 14px;
  font-weight: 600;
  line-height: 1.35;
  color: var(--bs-ink);
}
#ps-live-search .bs-ls-product .prices {
  grid-area: prices;
  display: flex;
  flex-flow: row wrap;
  align-items: baseline;
  gap: 0 8px;
}
#ps-live-search .bs-ls-product .price-new {
  order: 1;
  font-size: 15px;
  font-weight: 800;
  color: var(--bs-ink);
}
#ps-live-search .bs-ls-product .price-old + .price-new {
  color: var(--bs-danger);
}
#ps-live-search .bs-ls-product .price-old {
  order: 2;
  font-size: 13px;
  color: var(--bs-ink-4);
  text-decoration: line-through;
}
#ps-live-search .bs-ls-product .price-tax {
  order: 3;
  font-size: 12px;
  color: var(--bs-ink-3);
}

/* A section with nothing in it while another has results (the all-empty case becomes one state row). */
#ps-live-search .ps-live-search-item-text {
  display: block;
  padding: 4px 16px 10px;
  font-size: 13.5px;
  color: var(--bs-ink-3);
}
#ps-live-search span.ps-live-search-more {
  display: none;
}
#ps-live-search a.ps-live-search-more {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  min-height: 44px;
  margin: 8px 12px 4px;
  padding: 0 16px;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  color: var(--bs-blue);
  font-weight: 700;
  text-decoration: none;
}
#ps-live-search a.ps-live-search-more:is(:hover, :focus-visible) {
  background: var(--bs-blue-soft);
}
#ps-live-search a.ps-live-search-more > i {
  display: none; /* the module's Font Awesome caret; the init appends an arrow */
}

/* Loading: the module renders a Font Awesome icon; the spinner is drawn on its container instead. */
#ps-live-search .ps-live-search-item-loading {
  display: flex;
  justify-content: center;
  padding: 28px 16px;
}
#ps-live-search .ps-live-search-item-loading > i {
  display: none;
}
#ps-live-search .ps-live-search-item-loading::before {
  content: "";
  box-sizing: border-box;
  width: 24px;
  height: 24px;
  border: 2.5px solid var(--bs-line);
  border-top-color: var(--bs-blue);
  border-radius: 50%;
  animation: bs-ls-spin 0.8s linear infinite;
}
@keyframes bs-ls-spin {
  to { transform: rotate(360deg); }
}
@media (prefers-reduced-motion: reduce) {
  #ps-live-search .ps-live-search-item-loading::before {
    animation-duration: 2.4s;
  }
}

/* No results / error: one centred row. */
#ps-live-search .bs-ls-state {
  display: grid;
  justify-items: center;
  gap: 8px;
  padding: 28px 20px;
  text-align: center;
}
#ps-live-search .bs-ls-state strong {
  max-width: 100%;
  overflow-wrap: anywhere;
  font-size: 15px;
  font-weight: 700;
  color: var(--bs-ink);
}
#ps-live-search .bs-ls-state p {
  max-width: 34ch;
  margin: 0;
  font-size: 13.5px;
  color: var(--bs-ink-3);
}
#ps-live-search .bs-ls-state__ic {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--bs-line-2);
  color: var(--bs-ink-3);
}
#ps-live-search .bs-ls-state--error .bs-ls-state__ic {
  background: var(--bs-warning-bg);
  border: 1px solid var(--bs-warning-line);
  color: var(--bs-warning-fg);
}
#ps-live-search .bs-ls-state__btn {
  gap: 6px;
  min-height: 44px;
  margin-top: 4px;
}
/* === /UX-009-LS === */
BS_LS_CSS;
$ds = replace_one($ds, "/* RD-01-02-03C fixes 20260531 */\n", $section . "\n\n/* RD-01-02-03C fixes 20260531 */\n", 'ds_section_anchor');
css_balance_gate($ds, DSCSS);
twig_hazard_gate($section, DSCSS . '(section)');

twig_gate($root, array(HEADER => array($files[HEADER]['text'], $h)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS  => encode_text($files[DSCSS], $ds),
    HEADER => encode_text($files[HEADER], $h),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-009_live-search_report_20261004.md');
