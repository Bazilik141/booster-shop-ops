<?php
declare(strict_types=1);
/**
 * UX-003_runner9_filters-no-reload_20261006: Category fetch/history/chips; exact server robots and pagination metadata.
 * PHP 8.0 compatible. Owner execution only, from ~/public_html.
 * Source: ux003-r9-bug004a-live-20261006-223210.tar.gz, post-runner-8b.
 * Order: BUG-004A first, UX-003 runner 9 second.
 * No DB, checkout/payment, server SEO policy, schema, product-card or drawer changes.
 * Backup: _patch_backups/UX-003_runner9_filters-no-reload_20261006-<timestamp>/, before any target write.
 * --dry-run validates without changes; --rollback surgically undoes owned edits,
 * preserves sibling patch edits and refreshes the current CSS token independently.
 * Rollback trigger: broken category/history/cart UI or new JS/Twig errors.
 * Existing !important cart declarations are consolidated at their source; no new override layer.
 */
const PATCH_ID = 'UX-003_runner9_filters-no-reload_20261006';
const MARKER = 'UX-003-R9';
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
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_ECA3351CF578E8A2'
            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}
PAYLOAD_ECA3351CF578E8A2, <<<'PAYLOAD_F2F2C5E4C4FAC53B'
            </div>
            <div class="bs-r9-sticky">
              <button type="button" class="bs-r9-show" data-bs-r9-show>Показати {{ product_total }} {{ products_total_label }}</button>
              <div class="bs-r9-zero" data-bs-r9-zero hidden><span>0 товарів з цими фільтрами</span><button type="button" class="bs-r9-reset" data-bs-r9-reset>Скинути</button></div>
            </div>
          </div>
        {% endif %}

        {% if active_filters|length %}
PAYLOAD_F2F2C5E4C4FAC53B),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_3791EB229E7ACB94'
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
PAYLOAD_3791EB229E7ACB94, <<<'PAYLOAD_EDDEA70ED93A68E0'
{# UX-003-R9: filter panel and request lifecycle live in bs-category-results.js. #}
PAYLOAD_EDDEA70ED93A68E0),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_C7E829D9DD835E43'
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
PAYLOAD_C7E829D9DD835E43, <<<'PAYLOAD_944F27A3A7929C52'
<script src="catalog/view/javascript/bs-category-results.js?v=ux003-r9-20261006" defer></script>
PAYLOAD_944F27A3A7929C52),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_033F590AD154931C'
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
PAYLOAD_033F590AD154931C, <<<'PAYLOAD_6DEAB52862B6B2CE'
        <div class="bs-r9-chips" data-bs-active-filters hidden></div>
        <div class="visually-hidden" role="status" aria-live="polite" data-bs-category-status></div>
PAYLOAD_6DEAB52862B6B2CE),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_133087B3429810A9'
<button type="button" class="bs-ff-reset" data-bs-ff-reset hidden>Скинути</button>
PAYLOAD_133087B3429810A9, <<<'PAYLOAD_4073DDAE2EEB5C6F'
<span data-bs-r9-found>Знайдено {{ product_total }} {{ products_total_label }}</span>
PAYLOAD_4073DDAE2EEB5C6F),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_47B1B98E1B3ADF9B'
<div id="bs-load-more-wrap" aria-live="polite" style="display:none">
PAYLOAD_47B1B98E1B3ADF9B, <<<'PAYLOAD_5014FC98704B22F1'
<div id="bs-load-more-wrap" style="display:none">
PAYLOAD_5014FC98704B22F1),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_F0BA907A5E421D18'
      {% if products %}
        <div id="product-list"
PAYLOAD_F0BA907A5E421D18, <<<'PAYLOAD_496A0A1E4C89F086'
      <div id="bs-category-results" data-total="{{ product_total }}" data-page-size="{{ products|length }}" data-category="{{ reset_url }}" tabindex="-1" aria-label="Товари" aria-busy="false">
      {% if products %}
        <div id="product-list"
PAYLOAD_496A0A1E4C89F086),
array("catalog/view/template/product/category.twig", <<<'PAYLOAD_8A3EBFD29DFE2A73'
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
PAYLOAD_8A3EBFD29DFE2A73, <<<'PAYLOAD_BFCC27A3A965D0D1'
      {% if not products %}
        <div class="bs-empty bs-r9-empty">
          <p class="bs-empty__title">Нічого не знайдено з цими фільтрами</p>
          <p class="bs-empty__text">Приберіть один із фільтрів або скиньте всі.</p>
          {% if column_right %}<button type="button" class="bs-r9-reset" data-bs-r9-reset>Скинути фільтри</button>{% endif %}
        </div>
      {% endif %}
      </div>
PAYLOAD_BFCC27A3A965D0D1),
array("extension/opencart/catalog/view/template/module/filter.twig", <<<'PAYLOAD_9FAC8C689A19D96E'
<div class="bs-ff-module">
PAYLOAD_9FAC8C689A19D96E, <<<'PAYLOAD_9271EC665F9550F9'
<div class="bs-ff-module" data-bs-filter-action="{{ action }}">
PAYLOAD_9271EC665F9550F9),
array("extension/opencart/catalog/view/template/module/filter.twig", <<<'PAYLOAD_E65B50D39ADA5974'
    var url = new URL('{{ action|escape('js') }}');
PAYLOAD_E65B50D39ADA5974, <<<'PAYLOAD_A55CCF9843C43480'
    var module = button && button.closest('[data-bs-filter-action]');
    var url = new URL(module ? module.dataset.bsFilterAction : '{{ action|escape('js') }}');
PAYLOAD_A55CCF9843C43480),
array("extension/opencart/catalog/view/template/module/filter.twig", <<<'PAYLOAD_0F687154B785F96E'
    window.location.href = url.toString();
PAYLOAD_0F687154B785F96E, <<<'PAYLOAD_AC6F122D2CA2360E'
    if (window.bsCategoryNavigate) window.bsCategoryNavigate(url.toString());
    else window.location.href = url.toString();
PAYLOAD_AC6F122D2CA2360E),
array("extension/opencart/catalog/view/template/module/filter.twig", <<<'PAYLOAD_35760438CB94A35A'
  var applyTimer = null;
PAYLOAD_35760438CB94A35A, <<<'PAYLOAD_2B4D154E76F7256C'
  var applyTimer = null;
  var module = button && button.closest('[data-bs-filter-action]');
  if (module) module.addEventListener('bs:filter-cancel', function () { clearTimeout(applyTimer); });
  /* UX-003-R9: the original server action and debounce retain a navigation fallback. */
PAYLOAD_2B4D154E76F7256C),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_53083B02CFAFC573'
.bs-cat-header {
  background: var(--bs-paper);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  overflow: hidden;
PAYLOAD_53083B02CFAFC573, <<<'PAYLOAD_8F67B4A8667C8A4A'
.bs-cat-header {
  background: var(--bs-paper);
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  overflow: clip; /* UX-003-R9: preserve rounded clipping without creating a scroll container that traps the sticky CTA. */
PAYLOAD_8F67B4A8667C8A4A),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_8D19D4DD9B944E49'
  main,
  #common-home,
  #content {
    max-width: 100vw !important;
    overflow-x: hidden !important;
  }
PAYLOAD_8D19D4DD9B944E49, <<<'PAYLOAD_22692360DD8491D5'
  main,
  #common-home,
  #content {
    max-width: 100vw !important;
  }
  /* UX-003-R9: mutually exclusive source rules. Category ancestors clip horizontally
     without becoming scroll containers; other page types retain hidden overflow. */
  main:not(:has(> #product-category)),
  #common-home,
  #content:not(#product-category #content) {
    overflow-x: hidden !important;
  }
  main:has(> #product-category),
  #product-category #content {
    overflow-x: clip !important;
  }
PAYLOAD_22692360DD8491D5),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_2CE4796B9E9FBCCF'
.bs-ff-ck {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 36px;
PAYLOAD_2CE4796B9E9FBCCF, <<<'PAYLOAD_D8815D5FD96EA2A1'
.bs-ff-ck {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 44px;
PAYLOAD_D8815D5FD96EA2A1),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_B4C57ED376C8A9C3'
.bs-ff-ck input {
  appearance: none;
  flex: 0 0 auto;
  width: 18px;
  height: 18px;
PAYLOAD_B4C57ED376C8A9C3, <<<'PAYLOAD_F9E4AD8C5847DC34'
.bs-ff-ck input {
  appearance: none;
  flex: 0 0 auto;
  width: 20px;
  height: 20px;
PAYLOAD_F9E4AD8C5847DC34),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_C297CB1ECA7CDD1F'
@media (min-width: 768px) {
  .bs-ff-groups {
PAYLOAD_C297CB1ECA7CDD1F, <<<'PAYLOAD_2BC406D1D7CD33B6'
@media (min-width: 576px) {
  .bs-ff-groups {
PAYLOAD_2BC406D1D7CD33B6),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_38E3F83B1253CD3B'
.bs-ff-g {
  border-bottom:
PAYLOAD_38E3F83B1253CD3B, <<<'PAYLOAD_8E6A13EF75CB800D'
.bs-ff-g {
  min-width: 0;
  border-bottom:
PAYLOAD_8E6A13EF75CB800D),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_1D50F31A22506F4E'
.bs-ff-ck input {
PAYLOAD_1D50F31A22506F4E, <<<'PAYLOAD_31C135E114D85850'
.bs-ff-ck > span { min-width: 0; overflow-wrap: anywhere; } /* Long filter labels stay inside their column. */
.bs-ff-ck input {
PAYLOAD_31C135E114D85850),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_DC004904BD4EB914'
.bs-ff-foot {
  display: flex;
PAYLOAD_DC004904BD4EB914, <<<'PAYLOAD_CCE68662642A0314'
.bs-ff-foot {
  display: none;
PAYLOAD_CCE68662642A0314),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_2CFEE59474E0B04F'
  .bs-ff-foot {
    margin-top: 4px;
PAYLOAD_2CFEE59474E0B04F, <<<'PAYLOAD_73F54362143F8B4A'
  .bs-ff-foot {
    display: flex;
    justify-content: space-between;
    margin-top: 4px;
PAYLOAD_73F54362143F8B4A),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_31D0C1578B9B138E'
  .bs-ff-groups {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
PAYLOAD_31D0C1578B9B138E, <<<'PAYLOAD_E59B0AE158DF0757'
  .bs-ff-groups {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }
  .bs-ff-ck { min-height: 36px; }
  .bs-ff-ck input { width: 18px; height: 18px; }
PAYLOAD_E59B0AE158DF0757),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_C39DBCD5F591C8A2'
/* Sort: the native select, unchanged behaviour (onchange → location). */
PAYLOAD_C39DBCD5F591C8A2, <<<'PAYLOAD_9EDF7651AAFC4DF9'
/* Sort: native select; UX-003-R9 enhances navigation, inline onchange remains the no-enhancement fallback. */
PAYLOAD_9EDF7651AAFC4DF9),
array("catalog/view/stylesheet/boostershop-ds.css", <<<'PAYLOAD_E3B0C44298FC1C14'

PAYLOAD_E3B0C44298FC1C14, <<<'PAYLOAD_DDB00855E732399F'

/* UX-003-R9: approved chips/loading/empty states. 44px targets, 3px progress,
   48px sticky CTA and chip heights/offsets follow the runner 9 design note. */
.bs-r9-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 0 14px 16px; color: var(--bs-ink-3); font-size: 13px; }
.bs-r9-chips[hidden] { display: none; }
.bs-r9-chip { position: relative; display: inline-flex; align-items: center; min-width: 0; max-width: 100%; min-height: 36px; padding: 6px 10px; border: 1px solid var(--bs-blue); border-radius: var(--bs-r-sm); color: var(--bs-blue); background: var(--bs-blue-soft); font: inherit; overflow-wrap: anywhere; text-align: left; cursor: pointer; }
.bs-r9-chip::before { content: ''; position: absolute; inset: -4px 0; }
.bs-r9-reset { min-height: 44px; padding: 8px 12px; border: 1px solid var(--bs-blue); border-radius: var(--bs-r-sm); color: var(--bs-blue); background: var(--bs-paper); font: inherit; font-weight: 700; cursor: pointer; }
.bs-r9-chip:hover, .bs-r9-reset:hover { background: var(--bs-blue-soft); }
.bs-r9-chip:active, .bs-r9-reset:active { color: var(--bs-ink); }
.bs-r9-chip:focus-visible, .bs-r9-reset:focus-visible, .bs-r9-show:focus-visible, #bs-category-results:focus-visible, #product-list:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }
#bs-category-results { position: relative; }
#bs-category-results.is-loading > * { opacity: .45; pointer-events: none; }
#bs-category-results.is-loading::before { content: ''; position: absolute; top: 0; left: 0; width: 30%; height: 3px; background: var(--bs-blue); animation: bs-r9-progress 1s ease-in-out infinite alternate; z-index: 1; }
@keyframes bs-r9-progress { to { transform: translateX(230%); } }
.bs-r9-sticky { position: sticky; bottom: 0; z-index: 2; padding: 12px 0; border-top: 1px solid var(--bs-line); background: var(--bs-paper); }
.bs-r9-show { display: block; width: 100%; min-height: 48px; padding: 10px 14px; border: 0; border-radius: var(--bs-r-sm); color: var(--bs-paper); background: var(--bs-blue); font: inherit; font-size: 15px; font-weight: 800; cursor: pointer; }
.bs-r9-show[hidden], .bs-r9-zero[hidden] { display: none; }
.bs-r9-show[aria-disabled="true"] { cursor: wait; }
.bs-r9-show[aria-disabled="true"]::after { content: ''; display: inline-block; width: 14px; height: 14px; margin-left: 8px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: bs-r9-spin .8s linear infinite; }
@keyframes bs-r9-spin { to { transform: rotate(360deg); } }
.bs-r9-show:hover { filter: brightness(.95); }
.bs-r9-show:active { filter: brightness(.9); }
.bs-r9-zero { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
.bs-r9-empty { padding: 24px; border: 1px solid var(--bs-line); border-radius: var(--bs-r); background: var(--bs-paper); }
@media (min-width:641px) { .bs-r9-chips { padding-inline: 16px; } }
@media (min-width:992px) {
  .bs-r9-chips { padding-inline: 22px; }
  .bs-r9-chip { min-height: 32px; }
  .bs-r9-chip::before { display: none; }
  .bs-r9-sticky { display: none; }
}
@media (prefers-reduced-motion:reduce) { #bs-category-results.is-loading::before, .bs-r9-show[aria-disabled="true"]::after { animation: none; } }
/* /UX-003-R9 */

PAYLOAD_DDB00855E732399F),
array("catalog/view/template/common/header.twig", <<<'PAYLOAD_C77E5168DFFDA66B'
<!DOCTYPE html>
PAYLOAD_C77E5168DFFDA66B, <<<'PAYLOAD_C77E5168DFFDA66B'
<!DOCTYPE html>
PAYLOAD_C77E5168DFFDA66B)
);
$newFiles = array(
"catalog/view/javascript/bs-category-results.js" => <<<'PAYLOAD_3B31C563ACA5F207'
/* UX-003-R9: one server-backed category request lifecycle. No fetched scripts execute. */
(function () {
  'use strict';
  var host = document.getElementById('product-category');
  var region = document.getElementById('bs-category-results');
  if (!host || !region) return;
  var panel = host.querySelector('#bs-ff-panel');
  var toggle = host.querySelector('#bs-ff-toggle');
  var module = panel && panel.querySelector('[data-bs-filter-action]');
  var chips = host.querySelector('[data-bs-active-filters]');
  var status = host.querySelector('[data-bs-category-status]');
  var sort = host.querySelector('.bs-ff-sort select');
  var sequence = 0, controller = null, busy = false;
  var resultCount = Number(region.dataset.total);
  var pendingPrefix = '';
  function word(n) {
    return n % 10 === 1 && n % 100 !== 11 ? 'товар' :
      n % 10 >= 2 && n % 10 <= 4 && !(n % 100 >= 12 && n % 100 <= 14) ? 'товари' : 'товарів';
  }
  function numberText(n) { return n + ' ' + word(n); }
  function say(message) { if (status) status.textContent = message; }
  function selected() { return panel ? Array.from(panel.querySelectorAll('input[name="filter[]"]:checked')) : []; }
  function resultMessage() { return resultCount ? 'Знайдено ' + numberText(resultCount) + '.' : 'Нічого не знайдено з цими фільтрами.'; }
  function panelState(open) {
    if (!panel || !toggle) return;
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  }
  function focusGrid() {
    var target = region.querySelector('#product-list') || region;
    target.setAttribute('tabindex', '-1'); target.setAttribute('aria-label', 'Товари');
    target.focus({ preventScroll: true });
    window.scrollTo({ top: Math.max(0, target.getBoundingClientRect().top + window.scrollY - 12), behavior: 'auto' });
  }
  function countsAndChips(focusId) {
    if (!panel || !chips || !toggle) return;
    var values = selected();
    panel.querySelectorAll('[data-bs-ff-group]').forEach(function (group) {
      var badge = group.querySelector('.bs-ff-count');
      var n = group.querySelectorAll('input[name="filter[]"]:checked').length;
      if (badge) { badge.textContent = n; badge.hidden = !n; badge.setAttribute('aria-label', 'вибрано ' + n); }
    });
    var badge = toggle.querySelector('.bs-ff-count');
    if (badge) { badge.textContent = values.length; badge.hidden = !values.length; badge.setAttribute('aria-hidden', 'true'); }
    chips.replaceChildren(); chips.hidden = !values.length;
    if (values.length) {
      var label = document.createElement('span'); label.textContent = 'Фільтр:'; chips.appendChild(label);
      values.forEach(function (input) {
        var name = input.parentElement.querySelector('span').textContent.trim();
        var button = document.createElement('button'); button.type = 'button'; button.className = 'bs-r9-chip';
        button.dataset.removeFilter = input.value; button.setAttribute('aria-label', 'Прибрати фільтр «' + name + '»');
        button.textContent = name + ' ×'; chips.appendChild(button);
      });
      if (values.length >= 2) {
        var reset = document.createElement('button'); reset.type = 'button'; reset.dataset.bsR9Reset = '';
        reset.className = 'bs-r9-reset'; reset.textContent = 'Скинути все'; chips.appendChild(reset);
      }
    }
    if (focusId !== undefined) {
      var candidate = Array.from(chips.querySelectorAll('[data-remove-filter]')).find(function (b) { return b.dataset.removeFilter === focusId; });
      (candidate || toggle).focus();
    }
  }
  function renderResultsState() {
    host.querySelectorAll('[data-bs-r9-found]').forEach(function (el) {
      el.textContent = busy ? 'Оновлюємо…' : resultCount ? 'Знайдено ' + numberText(resultCount) : 'Нічого не знайдено';
    });
    var show = host.querySelector('[data-bs-r9-show]');
    var empty = host.querySelector('[data-bs-r9-zero]');
    if (show) {
      show.hidden = !busy && !resultCount;
      show.textContent = busy ? 'Оновлюємо…' : 'Показати ' + numberText(resultCount);
      show.disabled = busy; show.setAttribute('aria-disabled', String(busy));
    }
    if (empty) empty.hidden = busy || !!resultCount;
    var wrap = region.querySelector('#bs-load-more-wrap');
    if (wrap) {
      var next = document.querySelector('link[rel="next"]');
      wrap.style.display = next ? '' : 'none';
      var shown = region.querySelectorAll('#product-list > .col').length;
      var shownEl = wrap.querySelector('.bs-lm-shown');
      var totalEl = wrap.querySelector('.bs-lm-total');
      var fill = wrap.querySelector('.bs-load-more-progress__fill');
      var count = wrap.querySelector('#bs-load-more-count');
      if (shownEl) shownEl.textContent = shown;
      if (totalEl) totalEl.textContent = resultCount;
      if (fill) fill.style.width = (resultCount ? Math.min(100, shown / resultCount * 100) : 0) + '%';
      if (count) count.textContent = '+' + Math.min(Number(region.dataset.pageSize) || shown, Math.max(0, resultCount - shown));
      var button = wrap.querySelector('#bs-load-more-btn');
      if (button) { button.disabled = busy; button.classList.toggle('loading', busy); }
    }
  }
  function loading(on) {
    busy = on; region.classList.toggle('is-loading', on); region.setAttribute('aria-busy', String(on));
    region.style.minHeight = on ? region.getBoundingClientRect().height + 'px' : '';
    var grid = region.querySelector('#product-list'); if (grid) grid.setAttribute('aria-busy', String(on));
    renderResultsState();
  }
  function invalidate() {
    sequence++; if (controller) controller.abort(); controller = null;
  }
  function sameCategory(url) {
    var parsed = new URL(url, location.href);
    if (parsed.origin !== location.origin) throw new Error('Cross-origin response');
    return parsed;
  }
  function syncHead(doc, append) {
    // Load-more advances the existing next-page cursor; its address and prev/robots stay on the current page.
    (append ? ['link[rel="next"]'] : ['meta[name="robots"]', 'link[rel="prev"]', 'link[rel="next"]']).forEach(function (selector) {
      document.head.querySelectorAll(selector).forEach(function (node) { node.remove(); });
      doc.head.querySelectorAll(selector).forEach(function (node) { document.head.appendChild(document.importNode(node, true)); });
    });
  }
  function syncControls(doc) {
    var nextModule = doc.querySelector('[data-bs-filter-action]');
    if (module && nextModule) module.dataset.bsFilterAction = nextModule.dataset.bsFilterAction;
    if (panel) {
      var checkedValues = new Set(Array.from(doc.querySelectorAll('#bs-ff-panel input[name="filter[]"]:checked')).map(function (i) { return i.value; }));
      panel.querySelectorAll('input[name="filter[]"]').forEach(function (input) { input.checked = checkedValues.has(input.value); });
    }
    var nextSort = doc.querySelector('.bs-ff-sort select');
    if (sort && nextSort) {
      // Keep the live select, IDs, names and labels; server links carry the current filter.
      if (sort.options.length !== nextSort.options.length) throw new Error('Sort option mismatch');
      Array.from(sort.options).forEach(function (option, index) {
        option.value = nextSort.options[index].value; option.selected = nextSort.options[index].selected;
      });
    }
    countsAndChips();
  }
  function canonical(doc) { var node = doc.querySelector('link[rel="canonical"]'); return node ? node.href : ''; }
  async function navigate(url, options) {
    options = options || {};
    if (!window.fetch || !window.AbortController) { window.location.assign(url); return; }
    var prefix = pendingPrefix; pendingPrefix = '';
    invalidate(); var ticket = sequence; controller = new AbortController();
    loading(true);
    try {
      url = sameCategory(url).href;
      var response = await fetch(url, { credentials: 'same-origin', signal: controller.signal });
      if (response.status !== 200) throw new Error('HTTP ' + response.status);
      // A redirect to another page must use normal navigation, never a partial category swap.
      if (new URL(response.url).href !== url) throw new Error('Redirected category');
      var doc = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (ticket !== sequence) return;
      var next = doc.querySelector('#bs-category-results');
      if (!next || canonical(doc) !== canonical(document) || next.dataset.category !== region.dataset.category) throw new Error('Category response mismatch');
      var total = Number(next.dataset.total);
      if (!Number.isSafeInteger(total) || total < 0 || (total > 0 && !next.querySelector('#product-list'))) throw new Error('Missing results');
      if (!!module !== !!doc.querySelector('[data-bs-filter-action]')) throw new Error('Filter module mismatch');
      // Preserve scripts as inert data; no script from a fetch response is executed.
      next.querySelectorAll('script').forEach(function (node) { node.remove(); });
      if (options.append) {
        var grid = region.querySelector('#product-list');
        var cards = next.querySelectorAll('#product-list > .col');
        if (!grid || !cards.length || total !== resultCount) throw new Error('Invalid next page');
        cards.forEach(function (card) { grid.appendChild(document.importNode(card, true)); });
        // Appending leaves the address/current selected page unchanged, as before runner 9.
        syncHead(doc, true); renderResultsState(); say('Додано ' + numberText(cards.length) + '.');
      } else {
        var active = document.activeElement;
        var preserve = active && (panel && panel.contains(active) || active === toggle || active === sort || chips && chips.contains(active));
        var focusFilter = active && chips && chips.contains(active) ? active.dataset.removeFilter : undefined;
        syncControls(doc);
        region.replaceChildren.apply(region, Array.from(next.childNodes).map(function (node) { return document.importNode(node, true); }));
        region.dataset.total = next.dataset.total; region.dataset.pageSize = next.dataset.pageSize;
        resultCount = total; syncHead(doc);
        var headerCount = host.querySelector('.bs-cat-header__title .bs-count');
        if (headerCount) headerCount.textContent = numberText(resultCount);
        if (!options.pop) history.pushState(null, '', url);
        if (focusFilter !== undefined) countsAndChips(focusFilter);
        if (options.focusGrid) focusGrid();
        else if (!preserve && active && !document.contains(active)) (toggle || region).focus();
        say(prefix + (options.sortLabel ? 'Відсортовано: ' + options.sortLabel + '.' : resultMessage()));
      }
    } catch (error) {
      if (ticket !== sequence || error.name === 'AbortError') return;
      window.location.assign(url);
    } finally {
      if (ticket === sequence) { controller = null; loading(false); }
    }
  }
  window.bsCategoryNavigate = navigate;
  function filterUrl() {
    var url = new URL(module.dataset.bsFilterAction, location.href);
    var values = selected().map(function (input) { return input.value; });
    if (values.length) url.searchParams.set('filter', values.join(',')); else url.searchParams.delete('filter');
    url.searchParams.delete('page'); return url.href;
  }
  function reset() {
    if (!module) return;
    module.dispatchEvent(new Event('bs:filter-cancel'));
    panel.querySelectorAll('input[name="filter[]"]').forEach(function (input) { input.checked = false; });
    pendingPrefix = 'Фільтри скинуто. '; countsAndChips(); toggle.focus(); navigate(filterUrl());
  }
  if (panel && toggle) {
    panel.querySelectorAll('[data-bs-ff-group]').forEach(function (group, index) {
      var button = group.querySelector('.bs-ff-gt'); var body = group.querySelector('.bs-ff-checks');
      if (!button || !body) return;
      body.hidden = matchMedia('(max-width:575.98px)').matches && index > 0;
      button.setAttribute('aria-expanded', String(!body.hidden));
      button.addEventListener('click', function () { body.hidden = !body.hidden; button.setAttribute('aria-expanded', String(!body.hidden)); });
    });
    toggle.addEventListener('click', function () { panelState(panel.hidden); });
    panel.addEventListener('change', function (event) {
      if (!event.target.matches('input[name="filter[]"]')) return;
      // Abort immediately, before the module's existing 250 ms debounce expires.
      invalidate(); pendingPrefix = ''; countsAndChips(); loading(true);
    });
  }
  host.addEventListener('change', function (event) {
    if (event.target !== sort) return;
    event.stopImmediatePropagation();
    if (module) {
      // If a checkbox debounce is pending, include that selection in the sort link too.
      var url = new URL(sort.value, location.href), values = selected().map(function (i) { return i.value; });
      if (values.length) url.searchParams.set('filter', values.join(',')); else url.searchParams.delete('filter');
      var cancelEvent = new Event('bs:filter-cancel'); module.dispatchEvent(cancelEvent);
      navigate(url.href, { sortLabel: sort.options[sort.selectedIndex].text });
    } else navigate(sort.value, { sortLabel: sort.options[sort.selectedIndex].text });
  }, true);
  host.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (button && button.dataset.removeFilter !== undefined) {
      var values = selected(), index = values.findIndex(function (i) { return i.value === button.dataset.removeFilter; });
      var input = values[index]; if (!input) return;
      var name = input.parentElement.querySelector('span').textContent.trim(); input.checked = false;
      var next = values[index + 1] || values[index - 1]; countsAndChips(next ? next.value : '');
      pendingPrefix = 'Фільтр «' + name + '» прибрано. ';
      if (module) module.dispatchEvent(new Event('bs:filter-cancel'));
      navigate(filterUrl()); return;
    }
    if (button && button.matches('[data-bs-r9-reset]')) { reset(); return; }
    if (button && button.matches('[data-bs-ff-close]')) { panelState(false); toggle.focus(); return; }
    if (button && button.matches('[data-bs-r9-show]') && !busy) { panelState(false); focusGrid(); return; }
    if (button && button.id === 'bs-load-more-btn') {
      var next = document.querySelector('link[rel="next"]');
      if (next && !busy) navigate(next.href, { append: true }); return;
    }
    var link = event.target.closest('[data-bs-r9-pagination] a');
    if (link && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey && event.button === 0) {
      event.preventDefault(); navigate(link.href, { focusGrid: true });
    }
  });
  window.addEventListener('popstate', function () {
    if (module) module.dispatchEvent(new Event('bs:filter-cancel'));
    pendingPrefix = ''; navigate(location.href, { pop: true });
  });
  countsAndChips(); renderResultsState();
  var headerCount = host.querySelector('.bs-cat-header__title .bs-count');
  if (headerCount) headerCount.textContent = numberText(resultCount);
})();

PAYLOAD_3B31C563ACA5F207
);
$expected = array("catalog/view/template/product/category.twig" => "c7c658d677a120ab3895e37547de7f782b96b8ff42986db2309b177f1f50d54f", "extension/opencart/catalog/view/template/module/filter.twig" => "96ea7f7458683e175b33684a97faf6942f1b1b5b8e5ef3a75e023fd86475fa34", "catalog/view/stylesheet/boostershop-ds.css" => "07e570960a92a7c627329136084beccdb3eaae80a44844876b78d8f25c29cf3a", "catalog/view/template/common/header.twig" => "73d1e849d3838f2ce949d5bc5ae8053ff4e10cc83e785091b541439f64cc9b2c");
$markerFiles = array("catalog/view/template/product/category.twig", "extension/opencart/catalog/view/template/module/filter.twig", "catalog/view/stylesheet/boostershop-ds.css", "catalog/view/javascript/bs-category-results.js");

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
