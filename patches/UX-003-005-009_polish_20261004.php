<?php
declare(strict_types=1);

/**
 * UX-003 / UX-005 / UX-009 — layout polish (runner 8)
 * =============================================================================
 * Chain     : runner 8. Requires runners 1, 2, 3, 3b, 4, 5, 6 and 7 applied first (the deployed post-runner-7 state).
 * Handoff   : handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md, section «Deploy log runners
 *             3b–7 and runner 8»; review diagnostics/RD-UX-batch_runners-3b-7_review_20261004.md (R2, R4, R6).
 * Author    : Claude Code · 2026-10-04 (built 2026-10-05)
 * Risk      : every page (header), category pages (header card), live search (error state). CSS + Twig markup
 *             only. No PHP, no DB, no JS, no URL/canonical/meta change.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003-005-009_polish_20261004.php
 *
 * WHAT CHANGES
 *   1. Header fits at 769–959px (owner decision, review R4). Measured on the post-runner-7 state: «Увійти»/«Акаунт»
 *      + «Telegram» + the cart label overflow the viewport from 769px up to 895px with «Мій кошик - 0.00₴», and up
 *      to 945px with a six-digit total while logged in. In 769–959px only:
 *        - boostershop-ds.css, section C (.bs-ghost, the source rule of both links): 44×44 icon-only; the visible
 *          text is hidden, the existing aria-label keeps the name;
 *        - common/cart.twig: the label «Мій кошик - <total>» is split at its first « - » into a lead and the total,
 *          so the lead can be hidden there: icon + total. Any other text_items shape renders unchanged. The
 *          button's aria-label (full text_items) is unchanged. Every cart refresh re-renders cart.twig
 *          (common/cart.info), so the split survives AJAX updates.
 *      ≤768px and ≥960px: unchanged.
 *   2. Category header card below 992px: two rows, no swipe (owner decision). Row 1: subcategory chips wrap, counts
 *      kept. Row 2: «Фільтр» and sort side by side, equal width, text labels. The ≤575 icon-only squares (rules,
 *      the sort-icon overlay and its markup) are removed; the ≤575 block now only tightens the sort's inline
 *      padding so «За замовчуванням» fits at 390px (ellipsis below). ≥992px unchanged.
 *   3. Card-to-grid gap: one rule, 16px at every width. Before: 16px from 992 (UI-CAT-GAP, category.twig inline
 *      style) and 14px below it (runner 7, boostershop-ds.css). The runner-7 rule is removed and UI-CAT-GAP loses
 *      its media query. See the report for what the rest of the perceived gap is.
 *   4. Review R2: the empty .bs-ff-chips placeholder (8×8 grey dot at ≥992 on categories without sub-/sibling
 *      categories) is no longer rendered; the tools keep margin-left:auto and stay on the right.
 *   5. Review R6: the live-search error button wraps (white-space normal, height auto, max-width 100%) at its
 *      source rule.
 *   common/header.twig: ds.css ?v= token only.
 *
 * UI/CSS DISCIPLINE
 *   Root causes: (1) .bs-ghost white-space:nowrap labels + the cart label next to the search's 220px minimum
 *   (.bs-msearch / body.bs .bs-search min-width); (2) UX-003-FILTER .bs-ff-row (nowrap) + .bs-ff-chips
 *   overflow-x:auto + the ≤575 icon block; (3) runner-7 margin-bottom 14px vs UI-CAT-GAP 16px; (4) the template's
 *   empty placeholder getting the ≥992 segment padding + background; (5) .bs-btn white-space:nowrap / height:44px
 *   under #ps-live-search .bs-ls-state__btn. All edited at those rules. No !important, no position:absolute/fixed,
 *   no setTimeout added. Breakpoint 959/960 is the measured fit bound (stated in the CSS).
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on the four targets (runner 7 output) → marker → anchored, count-checked edits → hazard scan →
 *   Twig parse gate on cart.twig, category.twig, header.twig → CSS balance gate → backup of the four targets →
 *   write → restore-all on failure. Idempotent: marker «UX-003-POLISH» in boostershop-ds.css, cart.twig and
 *   category.twig. Self-deletes.
 *   ROLLBACK: cd ~/public_html && copy the four files back from _patch_backups/UX-003-005-009_polish_20261004-<ts>/,
 *   refresh the theme cache, Ctrl+F5.
 *   TRIGGER: header wraps or overflows, cart button missing or empty, category header broken, Twig error.
 * =============================================================================
 */

const PATCH_ID = 'UX-003-005-009_polish_20261004';
const MARKER   = 'UX-003-POLISH';
const TOKEN    = 'ux003polish-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';
const CART     = 'catalog/view/template/common/cart.twig';
const CATEGORY = 'catalog/view/template/product/category.twig';

const EXPECTED_SHA = array(
    DSCSS    => 'bb40278e58261f78aa40477588ff8d038311668089be63166eecec2793622207',
    HEADER   => '0d96a9d56808550f1089a870d5a0f999cb194cc985bafa203916d9d5188e9bb4',
    CART     => '9505b7aa8c57fcac69debbe7637ce80538065809dd51a9e7c836498fcfc8821f',
    CATEGORY => '505404c282376a50fa7268088c5fa6fc6f588e17c61be62a112dc26ef2e4243d',
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
foreach (array(DSCSS, HEADER, CART, CATEGORY) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS, CART, CATEGORY), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];

// 1. Header 769–959: icon-only ghost links, cart icon + total.
$fold = "/* Fold ghost-links into burger on mobile */\n@media (max-width: 768px) {\n  .bs-ghost { display: none; }\n}\n";
$ds = replace_one($ds, $fold, $fold . <<<'CSS'

/* UX-003-POLISH (2026-10-04, owner decision, review R4): from 769px the full labels no longer fit next to the
   search's 220px minimum. Measured: «Увійти» + «Telegram» + «Мій кошик - 0.00₴» need 896px; a six-digit total
   while logged in needs 945px. Up to 959px both links are 44×44 icons (aria-label keeps the name) and the cart
   shows icon + total — cart.twig wraps the «Мій кошик - » part in .bs-btn-label__lead. */
@media (min-width: 769px) and (max-width: 959.98px) {
  .bs-ghost { justify-content: center; width: 44px; height: 44px; padding: 0; }
  .bs-ghost svg { width: 20px; height: 20px; }
  .bs-ghost > span { display: none; }
  #cart .bs-btn-label__lead { display: none; }
}

CSS, 'ds_ghost_fold');

// 2–4. Category header card.
$ds = replace_one($ds,
    "   Side padding follows .bs-cat-header__hero: 14px ≤640, 16px to 991, 22px from 992. */\n",
    "   Side padding follows .bs-cat-header__hero: 14px ≤640, 16px to 991, 22px from 992.\n"
    . "   UX-003-POLISH (2026-10-04, owner decision): below 992 the row wraps into two — the chips wrap on row 1 (no\n"
    . "   horizontal scroll), «Фільтр» and the sort share row 2 at equal width with text labels. ≥992 is one row. */\n",
    'ds_ff_intro');
$ds = replace_one($ds,
    ".bs-ff-row {\n  display: flex;\n  align-items: center;\n  gap: 8px;\n  min-width: 0;\n  padding: 0 14px 14px;\n}\n"
    . ".bs-ff-chips {\n  display: flex;\n  flex: 1 1 auto;\n  gap: 6px;\n  min-width: 0;\n  padding: 2px 0;\n  overflow-x: auto;\n  scrollbar-width: none;\n}\n"
    . ".bs-ff-chips::-webkit-scrollbar {\n  display: none;\n}\n",
    ".bs-ff-row {\n  display: flex;\n  flex-wrap: wrap;\n  align-items: center;\n  gap: 8px;\n  min-width: 0;\n  padding: 0 14px 14px;\n}\n"
    . ".bs-ff-chips {\n  display: flex;\n  flex: 1 1 100%;\n  flex-wrap: wrap;\n  gap: 6px;\n  min-width: 0;\n}\n",
    'ds_ff_row_chips');
$ds = replace_one($ds,
    "  font-weight: 700;\n  text-decoration: none;\n  white-space: nowrap;\n}\n#product-category .bs-ff-chip:hover {\n",
    "  font-weight: 700;\n  text-decoration: none;\n  max-width: 100%;\n}\n#product-category .bs-ff-chip:hover {\n",
    'ds_ff_chip_wrap');
$ds = replace_one($ds,
    ".bs-ff-tools {\n  display: flex;\n  flex: 0 0 auto;\n  gap: 8px;\n  margin-left: auto;\n}\n",
    ".bs-ff-tools {\n  display: flex;\n  flex: 1 1 100%;\n  gap: 8px;\n}\n",
    'ds_ff_tools');
$ds = replace_one($ds,
    "  gap: 8px;\n  height: 44px;\n  min-width: 44px;\n  padding: 0 14px;\n  border: 1px solid var(--bs-line);\n",
    "  flex: 1 1 50%; /* 50%, not 0: border-box floors a 0 basis at padding + border, 30px wider than the sort */\n  gap: 8px;\n  height: 44px;\n  min-width: 0;\n  padding: 0 14px;\n  border: 1px solid var(--bs-line);\n",
    'ds_ff_btn');
$ds = replace_one($ds,
    ".bs-ff-sort {\n  position: relative;\n  flex: 0 0 auto;\n  width: 200px;\n}\n",
    ".bs-ff-sort {\n  flex: 1 1 50%;\n  min-width: 0;\n}\n",
    'ds_ff_sort');
$ds = replace_one($ds, ".bs-ff-sort__ic {\n  display: none;\n}\n", '', 'ds_ff_sort_ic');
$ds = replace_one($ds, <<<'CSS'
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

CSS, <<<'CSS'
/* ≤575: the half-row sort keeps «За замовчуванням» whole down to 390px (tighter inline padding, 13.5px); narrower,
   it ends in an ellipsis instead of being cut mid-letter. */
@media (max-width: 575.98px) {
  .bs-ff-sort .bs-select {
    padding-inline: 10px 26px;
    font-size: 13.5px;
    background-position: right 8px center;
  }
}

CSS, 'ds_ff_icon_block');
$ds = replace_one($ds,
    ".bs-ff-sort .bs-select {\n  height: 44px;\n  padding-block: 0;\n  color: var(--bs-ink-2);\n  font-size: 14px;\n  font-weight: 600;\n}\n",
    ".bs-ff-sort .bs-select {\n  height: 44px;\n  padding-block: 0;\n  color: var(--bs-ink-2);\n  font-size: 14px;\n  font-weight: 600;\n  text-overflow: ellipsis;\n}\n",
    'ds_ff_select');
$ds = replace_one($ds,
    "/* Below 992 the removed mobile action row used to hold the grid 14px off the header card (≥992 has UI-CAT-GAP). */\n"
    . "@media (max-width: 991.98px) {\n  #product-category .bs-cat-header {\n    margin-bottom: 14px;\n  }\n}\n",
    '', 'ds_ff_gap14');
$ds = replace_one($ds,
    "  .bs-ff-row {\n    padding: 0 22px 18px;\n  }\n",
    "  .bs-ff-row {\n    flex-wrap: nowrap;\n    padding: 0 22px 18px;\n  }\n",
    'ds_ff_row_992');
$ds = replace_one($ds,
    "  .bs-ff-chips {\n    flex: 0 1 auto;\n    flex-wrap: wrap;\n    padding: 4px;\n    overflow: visible;\n    border-radius: 10px;\n    background: var(--bs-line-2);\n  }\n"
    . "  #product-category .bs-ff-chip {\n    min-height: 40px;\n    border: 0;\n    background: transparent;\n  }\n",
    "  .bs-ff-chips {\n    flex: 0 1 auto;\n    padding: 4px;\n    border-radius: 10px;\n    background: var(--bs-line-2);\n  }\n"
    . "  #product-category .bs-ff-chip {\n    min-height: 40px;\n    border: 0;\n    background: transparent;\n    white-space: nowrap;\n  }\n",
    'ds_ff_chips_992');
$ds = replace_one($ds,
    "  .bs-ff-sort {\n    width: 220px;\n  }\n",
    "  .bs-ff-tools {\n    flex: 0 0 auto;\n    margin-left: auto;\n  }\n  .bs-ff-btn {\n    flex: 0 0 auto;\n  }\n"
    . "  .bs-ff-sort {\n    flex: 0 0 auto;\n    width: 220px;\n  }\n",
    'ds_ff_sort_992');
count_exact($ds, 'bs-ff-sort__ic', 0, 'ds_sort_ic_gone');
count_exact($ds, 'margin-bottom: 14px;', substr_count($files[DSCSS]['text'], 'margin-bottom: 14px;') - 1, 'ds_gap14_gone');

// 5. Review R6: live-search error button wraps on 320px phones.
$ds = replace_one($ds,
    "#ps-live-search .bs-ls-state__btn {\n  gap: 6px;\n  min-height: 44px;\n  margin-top: 4px;\n}\n",
    "#ps-live-search .bs-ls-state__btn {\n  gap: 6px;\n  height: auto;\n  min-height: 44px;\n  max-width: 100%;\n  margin-top: 4px;\n"
    . "  padding-block: 8px;\n  white-space: normal; /* UX-003-POLISH (review R6): .bs-btn is nowrap; the label overflowed at 320px */\n}\n",
    'ds_ls_btn');
css_balance_gate($ds, DSCSS);
twig_hazard_gate(implode("\n", array_diff(explode("\n", $ds), explode("\n", $files[DSCSS]['text']))), DSCSS);

/* ---- common/cart.twig: label split (1) ------------------------------------ */
$oldCart = $files[CART]['text'];
$cart = replace_one($oldCart,
    "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n",
    "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n"
    . "{# UX-003-POLISH (2026-10-04): the trigger label «Мій кошик - <total>» is split at its first « - » so ds.css can show\n"
    . "   icon + total at 769–959px; any other text_items shape renders unchanged. aria-label keeps the full text. #}\n"
    . "{% set bs_cart_label = text_items|split(' - ', 2) %}\n",
    'cart_comment');
$cart = replace_one($cart,
    '<span class="bs-btn-label">{{ text_items }}</span></button>',
    '<span class="bs-btn-label">{% if bs_cart_label|length == 2 %}<span class="bs-btn-label__lead">{{ bs_cart_label[0] }} - </span>{{ bs_cart_label[1] }}{% else %}{{ text_items }}{% endif %}</span></button>',
    'cart_label');
count_exact($cart, 'aria-label="{{ text_items }}"', 1, 'cart_aria_label');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $cart), explode("\n", $oldCart))), CART);

/* ---- product/category.twig (2–4) ------------------------------------------ */
$oldCat = $files[CATEGORY]['text'];
$cat = replace_one($oldCat,
    "        <div class=\"bs-ff-row\">\n",
    "        {# UX-003-POLISH (2026-10-04): below 992 the row wraps — chips on row 1, «Фільтр» + sort on row 2 (ds.css). No\n"
    . "           empty chips placeholder any more (review R2); the tools keep margin-left:auto at ≥992. #}\n"
    . "        <div class=\"bs-ff-row\">\n",
    'cat_marker');
$cat = replace_one($cat,
    "            </nav>\n          {% else %}\n            <div class=\"bs-ff-chips\"></div>\n          {% endif %}\n",
    "            </nav>\n          {% endif %}\n",
    'cat_placeholder');
$cat = replace_one($cat,
    "              <span class=\"bs-ff-sort__ic\" aria-hidden=\"true\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\" focusable=\"false\"><path d=\"M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3\"/></svg></span>\n",
    '', 'cat_sort_icon');
$cat = replace_one($cat,
    "  /* UI-CAT-GAP · desktop toolbar-to-grid spacing */\n  @media (min-width: 992px) {\n    #product-category .bs-cat-header { margin-bottom: 16px; }\n  }\n",
    "  /* UI-CAT-GAP · header-card-to-grid spacing, every width (UX-003-POLISH: was ≥992 only; runner 7 gave 14px below) */\n"
    . "  #product-category .bs-cat-header { margin-bottom: 16px; }\n",
    'cat_gap');
foreach (array('<nav class="bs-ff-chips" aria-label="Підкатегорії">', 'id="bs-ff-toggle"', 'id="bs-ff-panel"', 'onchange="location = this.value;"',
    '{% if active_filters|length %}', '<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">', '{% if products %}') as $probe) {
    count_exact($cat, $probe, 1, 'cat_probe');
}
count_exact($cat, 'bs-ff-sort__ic', 0, 'cat_sort_ic_gone');
count_exact($cat, '<div class="bs-ff-chips"></div>', 0, 'cat_placeholder_gone');
$tailStart = "<script type=\"application/ld+json\">\n";
count_exact($oldCat, $tailStart, 1, 'cat_tail_start');
if (substr($cat, strpos($cat, $tailStart)) !== substr($oldCat, strpos($oldCat, $tailStart))) fail('category_tail_not_identical');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $cat), explode("\n", $oldCat))), CATEGORY);

/* ---- header.twig: cache token --------------------------------------------- */
$h = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(
    CART     => array($oldCart, $cart),
    CATEGORY => array($oldCat, $cat),
    HEADER   => array($files[HEADER]['text'], $h),
));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS    => encode_text($files[DSCSS], $ds),
    HEADER   => encode_text($files[HEADER], $h),
    CART     => encode_text($files[CART], $cart),
    CATEGORY => encode_text($files[CATEGORY], $cat),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-003-005-009_polish_report_20261004.md');
