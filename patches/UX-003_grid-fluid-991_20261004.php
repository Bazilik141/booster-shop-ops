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
    DSCSS  => 'e88e4155f14bc63624282908eab4a6f7ddfb3f54fc4e71d38d6cc08338245cc3',
    HEADER => '21b8141efec2278d034b0774b7d30d341f6729f9fcc12da763ac6835b8dc7f19',
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
if (marker_state($files, array(DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

$section = <<<'BS_GRID_CSS'
/* === UX-003-GRID: page container fluid from 576 to 991px (2026-10-04) ====== */
/* Bootstrap caps .container at 540px (576–767) and 720px (768–991) while the header runs full width, so the
   content was narrower than the header. Below 992 the page wrapper (every template's top-level
   <div id="…" class="container"> directly inside <main>) now runs full width with the header's own side
   gutters: 10px up to 768 (body.bs .bs-header, RD-01-02-03 hotfix, max-width 768px) and 32px from 769
   (body.bs .bs-header 12px 32px). Below 576 nothing changes (Bootstrap is already full width there, 12px).
   Scoped to `main > .container`, so the cookie bar, the stock #menu and any .container inside a component
   are not touched. ≥992 is unchanged. Page-specific wrapper rules still win where they exist
   (#checkout-checkout.bs-co 12px ≤640, #information-information.bs-cp-page padding 0, #common-home ≤768). */
@media (min-width: 576px) and (max-width: 991.98px) {
  main > .container {
    max-width: none;
    padding-left: 10px;
    padding-right: 10px;
  }
}
/* Up to 768 the page row's gutter follows the 10px padding: with Bootstrap's -12px row margins the row would be
   4px wider than the viewport, and R07MOB5's `#content { max-width: 100vw !important }` (≤768) then clamps the
   column on one side only — content 10px from the left edge but 14px from the right. */
@media (min-width: 576px) and (max-width: 768px) {
  main > .container > .row {
    --bs-gutter-x: 20px;
  }
}
@media (min-width: 769px) and (max-width: 991.98px) {
  main > .container {
    padding-left: 32px;
    padding-right: 32px;
  }
}
/* === /UX-003-GRID === */
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
