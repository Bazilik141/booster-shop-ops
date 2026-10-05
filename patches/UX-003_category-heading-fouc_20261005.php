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
    CATEGORY => 'd4462c7554ecb880100dcfe9e71c71ec6b3ec862add2e4eee8eefe105a17b977',
);
const BLOCK_SHA = '5088a2f52650ad348c0d0b83ecf086d8ccc62c5e742468650b31e6b5761183a7';


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
