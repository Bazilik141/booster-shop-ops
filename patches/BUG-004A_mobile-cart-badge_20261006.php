<?php
declare(strict_types=1);
/**
 * BUG-004A_mobile-cart-badge_20261006: Mobile cart count, variant A; independent surgical rollback.
 * PHP 8.0 compatible. Owner execution only, from ~/public_html.
 * Source: ux003-r9-bug004a-live-20261006-223210.tar.gz, post-runner-8b.
 * Order: BUG-004A first, UX-003 runner 9 second.
 * No DB, checkout/payment, server SEO policy, schema, product-card or drawer changes.
 * Backup: _patch_backups/BUG-004A_mobile-cart-badge_20261006-<timestamp>/, before any target write.
 * --dry-run validates without changes; --rollback surgically undoes owned edits,
 * preserves sibling patch edits and refreshes the current CSS token independently.
 * Rollback trigger: broken category/history/cart UI or new JS/Twig errors.
 * Existing !important cart declarations are consolidated at their source; no new override layer.
 */
const PATCH_ID = 'BUG-004A_mobile-cart-badge_20261006';
const MARKER = 'BUG-004A';
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


function transact(string $root, array $files, array $updated, bool $dry): void {
    if ($dry) { out('dry_run=yes'); return; }
    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($backup, 0755, true)) fail('backup_directory_failed');
    $manifest = array();
    foreach ($files as $rel => $file) {
        $manifest[$rel] = $file['exists'];
        if (!$file['exists']) continue;
        $dest = $backup . '/' . $rel;
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true)) fail('backup_directory_failed=' . $rel);
        if (file_put_contents($dest, $file['raw'], LOCK_EX) !== strlen($file['raw'])) fail('backup_failed=' . $rel);
        if (file_get_contents($dest) !== $file['raw']) fail('backup_verify_failed=' . $rel);
        out('backup=' . substr($dest, strlen($root) + 1));
    }
    file_put_contents($backup . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));
    try {
        foreach ($updated as $rel => $bytes) {
            if ($bytes === null) { if (!unlink($root . '/' . $rel)) fail('remove_failed=' . $rel); }
            else write_checked($root . '/' . $rel, $bytes);
            out('changed=' . $rel);
        }
        // Lint the exact runner used for this transaction, after writes as well as before.
        php_lint_file(__FILE__);
        out('php_lint_postwrite=passed:runner');
    } catch (Throwable $error) {
        $ok = true;
        foreach ($updated as $rel => $bytes) {
            if ($files[$rel]['exists']) {
                if (file_put_contents($root . '/' . $rel, $files[$rel]['raw'], LOCK_EX) !== strlen($files[$rel]['raw'])) $ok = false;
            } elseif (is_file($root . '/' . $rel) && !unlink($root . '/' . $rel)) $ok = false;
        }
        out('restore=' . ($ok ? 'ok' : 'FAILED:' . $backup)); throw $error;
    }
    out('backup_dir=' . substr($backup, strlen($root) + 1));
}
$root = start_runner();
$dry = in_array('--dry-run', $argv, true);
$rollback = in_array('--rollback', $argv, true);
foreach (array_slice($argv, 1) as $arg) if (!in_array($arg, array('--dry-run', '--rollback'), true)) fail('unknown_argument');
$ops = array(
array("catalog/view/template/common/cart.twig", <<<'PAYLOAD_A3DEF777BA4069CB'
  <button type="button" class="bs-btn bs-btn-primary bs-btn-sm mini-cart-trigger" data-bs-mini-cart-open aria-label="{{ text_items }}" aria-expanded="false"><svg class="bs-cart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z" stroke="currentColor" stroke-width="1.6"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="1.6"/></svg><span class="bs-btn-label">{% if bs_cart_label|length == 2 %}<span class="bs-btn-label__lead">{{ bs_cart_label[0] }} - </span>{{ bs_cart_label[1] }}{% else %}{{ text_items }}{% endif %}</span></button>
PAYLOAD_A3DEF777BA4069CB, <<<'PAYLOAD_AA66A4153FC6F5A9'
  <button type="button" class="bs-btn bs-btn-primary bs-btn-sm mini-cart-trigger" data-bs-mini-cart-open data-bs-cart-qty="{{ bs_cart_qty }}" data-bs-cart-total="{{ (bs_cart_label|length == 2 ? bs_cart_label[1] : '')|escape('html_attr') }}" data-bs-cart-label="{{ text_items|escape('html_attr') }}" aria-label="{{ text_items }}" aria-expanded="false"><span class="bs-cart-symbol"><svg class="bs-cart-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4H6Z" stroke="currentColor" stroke-width="1.6"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0" stroke="currentColor" stroke-width="1.6"/></svg><span class="bs-cart-badge" aria-hidden="true"{% if not bs_cart_qty %} hidden{% endif %}>{{ bs_cart_qty >= 10 ? '9+' : bs_cart_qty }}</span></span><span class="bs-btn-label">{% if bs_cart_label|length == 2 %}<span class="bs-btn-label__lead">{{ bs_cart_label[0] }} - </span>{{ bs_cart_label[1] }}{% else %}{{ text_items }}{% endif %}</span></button>
PAYLOAD_AA66A4153FC6F5A9),
array("catalog/view/template/common/cart.twig", <<<'PAYLOAD_58873D50BFD56775'
{% set bs_cart_label = text_items|split(' - ', 2) %}
PAYLOAD_58873D50BFD56775, <<<'PAYLOAD_B10BED44374F4932'
{% set bs_cart_label = text_items|split(' - ', 2) %}
{# BUG-004A: server quantity is the sum above; fragment loads rerun the updater. #}
<script>
(function () {
  if (!window.bsCartBadgeUpdate) {
    window.bsCartBadgeUpdate = function () {
      var button = document.querySelector('#cart [data-bs-cart-qty]'); if (!button) return;
      var n = Number(button.dataset.bsCartQty), mobile = matchMedia('(max-width:991.98px)').matches;
      var noun = n % 10 === 1 && n % 100 !== 11 ? 'товар' : n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? 'товари' : 'товарів';
      var label = n ? 'Кошик: ' + n + ' ' + noun : 'Кошик порожній';
      if (n && matchMedia('(min-width:576px) and (max-width:991.98px)').matches && button.dataset.bsCartTotal) label += ', ' + button.dataset.bsCartTotal;
      button.setAttribute('aria-label', mobile ? label : button.dataset.bsCartLabel);
    };
    ['(max-width:575.98px)', '(max-width:991.98px)'].forEach(function (query) {
      var media = matchMedia(query);
      if (media.addEventListener) media.addEventListener('change', window.bsCartBadgeUpdate);
      else media.addListener(window.bsCartBadgeUpdate);
    });
    if (window.jQuery) window.jQuery(document).on('bs:cart-updated.bug004a', window.bsCartBadgeUpdate);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', window.bsCartBadgeUpdate, { once: true });
  }
  window.bsCartBadgeUpdate();
})();
</script>
PAYLOAD_B10BED44374F4932),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_83C10155D0CA7460'

#cart.bs-header__cart .mini-cart-trigger {
  display: inline-flex !important;
  align-items: center !important;
  justify-content: center !important;
  height: 40px !important;
  min-height: 40px !important;
  margin: 0 !important;
  padding: 0 16px !important;
  line-height: 1 !important;
  font-size: 16.8px !important;
  white-space: nowrap !important;
}
PAYLOAD_83C10155D0CA7460, <<<'PAYLOAD_8F1E0A388A94772A'

/* BUG-004A moved cart rule 2 into its canonical source. */

PAYLOAD_8F1E0A388A94772A),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_5C58D245341F6820'

#cart.bs-header__cart .mini-cart-trigger {
  gap: 8px;
  position: relative;
}
PAYLOAD_5C58D245341F6820, <<<'PAYLOAD_51756CC17BFBDB48'

/* BUG-004A moved cart rule 3 into its canonical source. */

PAYLOAD_51756CC17BFBDB48),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_8EF99E33B5D79295'

#cart.bs-header__cart .mini-cart-trigger .bs-cart-icon {
  flex: 0 0 auto;
}
PAYLOAD_8EF99E33B5D79295, <<<'PAYLOAD_43CF693449C68A9A'

/* BUG-004A moved cart rule 4 into its canonical source. */

PAYLOAD_43CF693449C68A9A),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_1B1DCBDCE7FA09F8'

  .bs-header .bs-btn-ghost,
  #cart.bs-header__cart .mini-cart-trigger {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 36px !important;
    min-height: 36px !important;
    width: 36px !important;
    min-width: 36px !important;
    flex: 0 0 36px !important;
    padding: 0 !important;
    position: relative !important;
    border-radius: var(--bs-r-sm) !important;
    line-height: 1 !important;
    white-space: nowrap !important;
  }
PAYLOAD_1B1DCBDCE7FA09F8, <<<'PAYLOAD_B7893A3832641800'

  /* BUG-004A moved cart rule 5 into its canonical source. */
.bs-header .bs-btn-ghost {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    height: 36px !important;
    min-height: 36px !important;
    width: 36px !important;
    min-width: 36px !important;
    flex: 0 0 36px !important;
    padding: 0 !important;
    position: relative !important;
    border-radius: var(--bs-r-sm) !important;
    line-height: 1 !important;
    white-space: nowrap !important;
  }
PAYLOAD_B7893A3832641800),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_38523D91B7C8D015'

  .bs-header .bs-btn-ghost .bs-btn-label,
  #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
    display: none !important;
  }
PAYLOAD_38523D91B7C8D015, <<<'PAYLOAD_39BB2BC4193782B1'

  /* BUG-004A moved cart rule 6 into its canonical source. */
.bs-header .bs-btn-ghost .bs-btn-label {
    display: none !important;
  }
PAYLOAD_39BB2BC4193782B1),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_D705AE9130811A4F'

  .bs-header .bs-btn-ghost svg,
  #cart.bs-header__cart .mini-cart-trigger svg {
    width: 20px !important;
    height: 20px !important;
    flex: 0 0 auto !important;
  }
PAYLOAD_D705AE9130811A4F, <<<'PAYLOAD_EDD2FB46D6CAB032'

  /* BUG-004A moved cart rule 7 into its canonical source. */
.bs-header .bs-btn-ghost svg {
    width: 20px !important;
    height: 20px !important;
    flex: 0 0 auto !important;
  }
PAYLOAD_EDD2FB46D6CAB032),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_9F969D7EB9387CA7'

  #cart.bs-header__cart .mini-cart-trigger {
    display:     inline-flex  !important;
    align-items: center       !important;
    width:       36px         !important;
    min-width:   36px         !important;
    height:      36px         !important;
    padding:     0            !important;
  }
PAYLOAD_9F969D7EB9387CA7, <<<'PAYLOAD_DD869A884B3CEBAC'

  /* BUG-004A moved cart rule 8 into its canonical source. */

PAYLOAD_DD869A884B3CEBAC),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_08F051107376C9AE'

  /* d-block from Bootstrap sets display:block !important.
     Our selector has higher specificity (1 ID + 2 classes) so we win. */
  #cart.bs-header__cart .mini-cart-trigger.d-block {
    display: inline-flex !important;
  }
PAYLOAD_08F051107376C9AE, <<<'PAYLOAD_B949651DB77D0B00'

  /* d-block from Bootstrap sets display:block !important.
     Our selector has higher specificity (1 ID + 2 classes) so we win. */
  /* BUG-004A moved cart rule 9 into its canonical source. */

PAYLOAD_B949651DB77D0B00),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_47FD00D2274DD3D8'


/* TECH-045-WPE: values only (purchase green tokens); the !important predates TECH-045. */
body.bs #cart.bs-header__cart .mini-cart-trigger {
  background: var(--bs-buy) !important;
  border-color: var(--bs-buy) !important;
  border-radius: 8px !important;
  box-shadow: none !important;
  font-size: 13px !important;
  height: 36px !important;
  min-height: 36px !important;
  padding: 0 12px !important;
}
PAYLOAD_47FD00D2274DD3D8, <<<'PAYLOAD_D06999CC3C807552'


/* TECH-045-WPE: values only (purchase green tokens); the !important predates TECH-045. */
/* BUG-004A moved cart rule 10 into its canonical source. */

PAYLOAD_D06999CC3C807552),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_4B632C2BF411BAB9'

body.bs #cart.bs-header__cart .mini-cart-trigger:hover,
body.bs #cart.bs-header__cart .mini-cart-trigger:focus,
body.bs #cart.bs-header__cart .mini-cart-trigger.show {
  background: var(--bs-buy-hover) !important;
  border-color: var(--bs-buy-hover) !important;
  color: #fff !important;
}
PAYLOAD_4B632C2BF411BAB9, <<<'PAYLOAD_15A4530401A3A550'

/* BUG-004A moved cart rule 11 into its canonical source. */

PAYLOAD_15A4530401A3A550),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_107B012342D2E211'

body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-qty {
  font-weight: 700;
}
PAYLOAD_107B012342D2E211, <<<'PAYLOAD_66D405CB6B093E05'

/* BUG-004A moved cart rule 12 into its canonical source. */

PAYLOAD_66D405CB6B093E05),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_944076DA5C967D31'

body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
  font-size: 12px;
  font-weight: 500;
  opacity: .88;
}
PAYLOAD_944076DA5C967D31, <<<'PAYLOAD_CFAC5A610F57327C'

/* BUG-004A moved cart rule 13 into its canonical source. */

PAYLOAD_CFAC5A610F57327C),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_3468EEFF38EBD374'

body.bs #cart.bs-header__cart .mini-cart-trigger {
  align-items: center !important;
  border-radius: 8px !important;
  display: inline-flex !important;
  gap: 8px !important;
  height: 38px !important;
  min-height: 38px !important;
  padding: 0 13px !important;
  white-space: nowrap !important;
}
PAYLOAD_3468EEFF38EBD374, <<<'PAYLOAD_324C7EA8DF0F950A'

/* BUG-004A moved cart rule 14 into its canonical source. */

PAYLOAD_324C7EA8DF0F950A),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_9542971BC0AF914A'

body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon {
  height: 22px !important;
  width: 22px !important;
}
PAYLOAD_9542971BC0AF914A, <<<'PAYLOAD_5EAE771ECC93CAA8'

/* BUG-004A moved cart rule 15 into its canonical source. */

PAYLOAD_5EAE771ECC93CAA8),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_F9FD1F261168924D'

  body.bs .bs-header .bs-btn-ghost,
  body.bs #cart.bs-header__cart .mini-cart-trigger {
    border-radius: 8px !important;
    display: inline-flex !important;
    flex: 0 0 40px !important;
    height: 40px !important;
    min-height: 40px !important;
    min-width: 40px !important;
    padding: 0 !important;
    width: 40px !important;
  }
PAYLOAD_F9FD1F261168924D, <<<'PAYLOAD_A46A992D8D53FE98'

  /* BUG-004A moved cart rule 16 into its canonical source. */
body.bs .bs-header .bs-btn-ghost {
    border-radius: 8px !important;
    display: inline-flex !important;
    flex: 0 0 40px !important;
    height: 40px !important;
    min-height: 40px !important;
    min-width: 40px !important;
    padding: 0 !important;
    width: 40px !important;
  }
PAYLOAD_A46A992D8D53FE98),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_62AC20B07F20525D'

  body.bs .bs-header .bs-btn-label,
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
    display: none !important;
  }
PAYLOAD_62AC20B07F20525D, <<<'PAYLOAD_085D11E835A588D7'

  /* BUG-004A moved cart rule 17 into its canonical source. */
body.bs .bs-header .bs-btn-label {
    display: none !important;
  }
PAYLOAD_085D11E835A588D7),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_A54DD7B917411A4B'

  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon {
    height: 23px !important;
    width: 23px !important;
  }
PAYLOAD_A54DD7B917411A4B, <<<'PAYLOAD_137D8DE131D1BA46'

  /* BUG-004A moved cart rule 18 into its canonical source. */

PAYLOAD_137D8DE131D1BA46),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_A02686047DA6976F'

  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-qty {
    font-size: 10px !important;
    min-width: 16px !important;
    position: absolute !important;
    right: -2px !important;
    top: -2px !important;
  }
PAYLOAD_A02686047DA6976F, <<<'PAYLOAD_AF4A0723609958AD'

  /* BUG-004A moved cart rule 19 into its canonical source. */

PAYLOAD_AF4A0723609958AD),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_BC1F63A00196DEA1'


/* Mini-cart trigger font */
body.bs #cart.bs-header__cart .mini-cart-trigger {
  font-size: 14px !important;
}
PAYLOAD_BC1F63A00196DEA1, <<<'PAYLOAD_F72A59E5EADFFE21'


/* Mini-cart trigger font */
/* BUG-004A moved cart rule 20 into its canonical source. */

PAYLOAD_F72A59E5EADFFE21),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_2F16D45BA6A8F1FA'

body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
  font-size: 13px !important;
}
PAYLOAD_2F16D45BA6A8F1FA, <<<'PAYLOAD_DC4A730F8934AB09'

/* BUG-004A moved cart rule 21 into its canonical source. */

PAYLOAD_DC4A730F8934AB09),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_6C95A480F9B3DEDF'


/* 2. Mini-cart: +~15% size, +25-30% font */
body.bs #cart.bs-header__cart .mini-cart-trigger {
  height: 44px !important;
  min-height: 44px !important;
  padding: 0 16px !important;
  font-size: 15px !important;
}
PAYLOAD_6C95A480F9B3DEDF, <<<'PAYLOAD_00D0A7C7D81E332E'


/* 2. Mini-cart: +~15% size, +25-30% font */
/* BUG-004A moved cart rule 22 into its canonical source. */

PAYLOAD_00D0A7C7D81E332E),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_70F0CE16336C9E99'

body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
  font-size: 16px !important;
  font-weight: 600 !important;
  opacity: 1 !important;
}
PAYLOAD_70F0CE16336C9E99, <<<'PAYLOAD_6B267A1B608CA36B'

/* BUG-004A moved cart rule 23 into its canonical source. */

PAYLOAD_6B267A1B608CA36B),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_A5B655E71FD050F7'


  /* Mini-cart: компактна кнопка + badge в куті */
  body.bs #cart.bs-header__cart .mini-cart-trigger {
    position: relative !important;
    flex: 0 0 44px !important;
    width: 44px !important;
    height: 44px !important;
    padding: 0 !important;
    justify-content: center !important;
  }
PAYLOAD_A5B655E71FD050F7, <<<'PAYLOAD_085DAF15C207F07D'


  /* Mini-cart: компактна кнопка + badge в куті */
  /* BUG-004A moved cart rule 24 into its canonical source. */

PAYLOAD_085DAF15C207F07D),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_BA12134446C411E4'

  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
    display: none !important;
  }
PAYLOAD_BA12134446C411E4, <<<'PAYLOAD_4954E9DB255A5B6C'

  /* BUG-004A moved cart rule 25 into its canonical source. */

PAYLOAD_4954E9DB255A5B6C),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_676EE6D52A83F740'


  /* Badge: абсолютна позиція в правому верхньому куті */
  body.bs #cart.bs-header__cart .mini-cart-trigger .badge,
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-qty {
    position: absolute !important;
    top: 2px !important;
    right: 2px !important;
    min-width: 17px !important;
    height: 17px !important;
    padding: 0 3px !important;
    border-radius: 9px !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    line-height: 17px !important;
    background: var(--bs-ink) !important;
    color: #fff !important;
    outline: 2px solid #fff !important;
    text-align: center !important;
  }
PAYLOAD_676EE6D52A83F740, <<<'PAYLOAD_20A4015161286447'


  /* Badge: абсолютна позиція в правому верхньому куті */
  /* BUG-004A moved cart rule 26 into its canonical source. */

PAYLOAD_20A4015161286447),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_F5F5B741BBC87F67'

  #cart .bs-btn-label__lead { display: none; }
PAYLOAD_F5F5B741BBC87F67, <<<'PAYLOAD_FC9C856CA90047EA'

  /* BUG-004A moved cart rule 27 into its canonical source. */

PAYLOAD_FC9C856CA90047EA),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_E3B0C44298FC1C14'

PAYLOAD_E3B0C44298FC1C14, <<<'PAYLOAD_D8A80E7DE8390062'

/* BUG-004A: single cart-trigger source, consolidating the preceding historic rules.
   Existing !important declarations remain necessary against shared Bootstrap/header rules.
   44px is the approved tap target. Badge offsets and 16px size are design variant A. */
body.bs #cart.bs-header__cart .mini-cart-trigger {
  display: inline-flex !important; align-items: center !important; justify-content: center !important;
  position: relative; margin: 0 !important; height: 44px !important; min-height: 44px !important;
  padding: 0 16px !important; gap: 8px; line-height: 1 !important; white-space: nowrap !important;
  background: var(--bs-buy) !important; border-color: var(--bs-buy) !important;
  border-radius: 8px !important; box-shadow: none !important; font-size: 15px !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger:hover,
body.bs #cart.bs-header__cart .mini-cart-trigger:focus,
body.bs #cart.bs-header__cart .mini-cart-trigger.show {
  background: var(--bs-buy-hover) !important; border-color: var(--bs-buy-hover) !important; color: #fff !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label {
  display: inline !important; font-size: 16px !important; font-weight: 600 !important; opacity: 1 !important;
}
body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon { width: 22px !important; height: 22px !important; flex: 0 0 auto; }
.bs-cart-symbol { display: inline-flex; position: relative; flex: 0 0 auto; }
.bs-cart-badge { display: none; }
@media (max-width:991.98px) {
  .bs-cart-badge {
    display: inline-flex; align-items: center; justify-content: center; position: absolute;
    top: -7px; right: -9px; height: 16px; min-width: 16px; padding: 0 4px;
    border-radius: 999px; background: #fff; color: var(--bs-green-hover);
    font-size: 10.5px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums;
  }
  .bs-cart-badge[hidden] { display: none; }
  #cart .bs-btn-label__lead { display: none; }
}
@media (min-width:576px) and (max-width:991.98px) {
  body.bs #cart.bs-header__cart .mini-cart-trigger { flex: 0 0 auto !important; width: auto !important; min-width: 44px !important; padding: 0 12px !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label { max-width: 12ch; overflow: hidden; text-overflow: ellipsis; } /* Long totals retain their full accessible label. */
}
@media (max-width:575.98px) {
  body.bs #cart.bs-header__cart .mini-cart-trigger { flex: 0 0 44px !important; width: 44px !important; min-width: 44px !important; padding: 0 !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-btn-label { display: none !important; }
  body.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-icon { width: 23px !important; height: 23px !important; }
  .bs-cart-symbol { position: static; }
  .bs-cart-badge { top: 3px; right: 3px; }
}
/* /BUG-004A */

PAYLOAD_D8A80E7DE8390062),
array("catalog/view/template/common/header.twig", <<<'PAYLOAD_C77E5168DFFDA66B'
<!DOCTYPE html>
PAYLOAD_C77E5168DFFDA66B, <<<'PAYLOAD_C77E5168DFFDA66B'
<!DOCTYPE html>
PAYLOAD_C77E5168DFFDA66B)
);
$newFiles = array(

);
$expected = array("catalog/view/template/common/cart.twig" => "eeb1213cbc7b5890594a7b33cb8ee3463d16a7fdd3fa9a745f50bb12259dbf23", "catalog/view/stylesheet/boostershop-ds.css" => "70b897ed64083632d830fb54cea24c2162468796f2318e36e83812ec0d265805", "catalog/view/template/common/header.twig" => "28453cf86747e88bfdf48446ba26e354b345e6f4544b8a1fda4939bb82b40859");
$markerFiles = array("catalog/view/template/common/cart.twig", "catalog/view/stylesheet/boostershop-ds.css");

$files = array();
foreach ($expected as $rel => $sha) { $files[$rel] = load_text($root, $rel); $files[$rel]['exists'] = true; }
foreach ($newFiles as $rel => $text) {
    $files[$rel] = is_file($root . '/' . $rel) ? load_text($root, $rel) : array('raw'=>'', 'text'=>'', 'eol'=>"
");
    $files[$rel]['exists'] = is_file($root . '/' . $rel);
}
$applied = marker_state($files, $markerFiles, MARKER);
if (!$rollback && $applied) {
    out('already_applied=yes'); out('done=ok'); if (!$dry) @unlink(__FILE__); exit(0);
}
if ($rollback && !$applied) {
    out('already_rolled_back=yes'); out('done=ok'); if (!$dry) @unlink(__FILE__); exit(0);
}
if (!$rollback) {
    sha_guard($files, $expected);
    foreach ($newFiles as $rel => $text) if ($files[$rel]['exists']) fail('new_file_already_exists=' . $rel);
}
php_lint_file(__FILE__); out('php_lint_prewrite=passed:runner');
$candidate = array(); foreach ($files as $rel => $file) $candidate[$rel] = $file['text'];
$ordered = $rollback ? array_reverse($ops) : $ops;
foreach ($ordered as $index => $op) {
    list($rel, $before, $after) = $op;
    if (!$rollback && $before === '') $candidate[$rel] .= $after;
    else $candidate[$rel] = replace_one($candidate[$rel], $rollback ? $after : $before, $rollback ? $before : $after, $rel . ':' . $index);
}
foreach ($newFiles as $rel => $text) {
    if ($rollback) {
        if ($files[$rel]['raw'] !== $text) fail('rollback_new_file_modified=' . $rel);
        $candidate[$rel] = null;
    } else $candidate[$rel] = $text;
}
$header = 'catalog/view/template/common/header.twig';
$candidate[$header] = bust_token($candidate[$header], 'catalog/view/stylesheet/boostershop-ds.css', strtolower(PATCH_ID) . ($rollback ? '-rollback' : ''), 'ds_css');
$templates = array(); $updated = array();
foreach ($candidate as $rel => $text) {
    if ($text !== null && substr($rel, -5) === '.twig') {
        twig_hazard_gate($text, $rel); $templates[$rel] = array($files[$rel]['text'], $text);
    }
    if ($text !== null && substr($rel, -4) === '.css') css_balance_gate($text, $rel);
    $updated[$rel] = $text === null ? null : encode_text($files[$rel], $text);
}
twig_gate($root, $templates);
transact($root, $files, $updated, $dry);
out('operation=' . ($rollback ? 'rollback' : 'apply')); out('done=ok');
if (!$dry) @unlink(__FILE__);
