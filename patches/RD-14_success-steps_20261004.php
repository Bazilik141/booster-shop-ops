<?php
declare(strict_types=1);

/**
 * RD-14 — checkout success page «Замовлення прийнято», direction «Кроки»
 * =============================================================================
 * Chain     : runner 1 of 7 (RD-14 → RD-15 → UX-003/005 header → UX-009 live search → UX-009 search page
 *             → UX-003 grid → UX-003 category filter). Deploy strictly in this order.
 * Handoff   : handoffs/handoff_RD-14_success_claude-code_20261004.md (+ INDEX, Claude review 2026-10-04)
 * Design    : handoffs/design_20261004_rd14-15_ux003-005-009/RD-14 RD-15 - фінал.html (states a–e, First15 A)
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout-adjacent (template rendered at the end of checkout). Template + CSS only.
 * DB changes: NONE.  Controller: not touched.  Language files: not touched.
 * RUN FROM  : ~/public_html    ->    php RD-14_success-steps_20261004.php
 * Live base : rd-ux-batch-live2-20261004-1036.tar.gz — every target is SHA-256-guarded against it.
 *
 * WHAT CHANGES
 *   catalog/view/template/checkout/success.twig — rewritten markup:
 *     hero (SVG check in a 56px --bs-buy circle) → First15 (variant A) → IBAN card → «Що далі» card →
 *     «Ваше замовлення» (delivery/payment above the table; below 1024px collapsed to delivery, payment and
 *     «Сума» behind «Показати товари (N)» / «Сховати товари»; open without JS) → actions (both secondary) →
 *     footer («Вдалого анпакінгу», Telegram link). Fallback (no order number): text_message unchanged +
 *     «На головну» + «напишіть у Telegram». Last breadcrumb rendered without a link. All emoji → inline SVG,
 *     aria-hidden. The inline <style> (r11 / st2b2 / st2b3) is removed; its rules are superseded.
 *     New copy lives in the template, not the language file: «Що далі», «Фіскальний чек», «Без зайвих
 *     дзвінків», «Показати товари (N)», «Сховати товари».
 *   Unchanged, asserted on the new text: {% if ga4_purchase_payload %} block after both branches (TECH-015);
 *     data-checkout008-copy-requisites / -copy-status hooks and the inline copy script, byte-identical;
 *     conditions show_first15_offer, is_iban_bank_transfer, is_hutko, is_cod, `is_logged and history_url`;
 *     the requisite values.
 *   catalog/view/stylesheet/boostershop-ds.css —
 *     the R-11 «Checkout Success» section (the source rules of this page) is replaced wholesale by the RD-14
 *     section; global breadcrumb fallback: `> span` added beside `> a` in the pill rule and the last-item rule,
 *     so a current page rendered without a link keeps the pill (no template on the site used a span there).
 *   catalog/view/template/common/header.twig — boostershop-ds.css ?v= token only (convention 8).
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: R-11 section (ds.css «=== R-11: Checkout Success ===») + inline <style> in
 *   success.twig. Both are replaced at the source — nothing is stacked on top. No !important added; the
 *   inline `#content { padding-bottom: 8px !important }` is dropped and the global R07MOB2 40px is accepted.
 *   Override history checked: TECH-045-WPE (a.bs-btn-primary, success «Переглянути замовлення» — now
 *   secondary, so `.bs a` is answered by a scoped `#checkout-success .bs-btn-secondary` colour rule),
 *   RD-10F breadcrumb fallback (extended, not overridden). No position:absolute/fixed, no setTimeout.
 *   Magic numbers come from the approved design: 620 column, 56 hero circle, 36 step icon, 160 dt column.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all three targets → marker check → anchors → Twig compile gate (control parse first) on
 *   success.twig and header.twig → Twig-hazard scan of the new template → CSS balance gate → backup of all
 *   three to _patch_backups/<patch>-<ts>/ → write → any failure restores all. Idempotent: marker «RD-14» in
 *   success.twig and boostershop-ds.css → already_applied=yes. Self-deletes after success.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the theme cache, Ctrl+F5.
 *   Roll back only this runner, and only while no later runner of the chain is applied.
 *   TRIGGER: GA4 purchase missing or doubled, «Скопіювати реквізити» stops working, a payment branch missing,
 *   any PHP/Twig error on checkout/success.
 * =============================================================================
 */

const PATCH_ID = 'RD-14_success-steps_20261004';
const MARKER   = 'RD-14';
const TOKEN    = 'rd14-20261004';
const SUCCESS  = 'catalog/view/template/checkout/success.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    SUCCESS => '4b61912437fcb79520425d8520453deb1ff1663bd86443ef045aef7971833252',
    DSCSS   => '5f3e406956999e3743dcb92fca3c0372e34fc04610f7e51a5406bde15ae14e50',
    HEADER  => 'f1513b41057ac873efc530b79bc786205d8b562964c128ebc4e995caa6eb270e',
);
const R11_START = "/* === R-11: Checkout Success === */\n";
const R11_END   = "/* === /R-11: Checkout Success === */\n";
const R11_SHA   = 'b671cee3f7c841b96ce61b9bb5a3c633507d6b9cee61c2f39fb281c48e8def42';


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
foreach (array(SUCCESS, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(SUCCESS, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- success.twig ------------------------------------------------------- */
$success = <<<'BS_RD14_TWIG'
{{ header }}
{# RD-14 (2026-10-04): «Кроки» layout. Order data, branch conditions, the CHECKOUT-008 copy hooks and script,
   and the TECH-015 GA4 block are unchanged; styles live in boostershop-ds.css (section RD-14). #}
<div id="checkout-success" class="container bs bs-cp-page">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      {% if loop.last %}
        <li class="breadcrumb-item" aria-current="page"><span>{{ breadcrumb.text }}</span></li>
      {% else %}
        <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
      {% endif %}
    {% endfor %}
  </ul>

  <div class="row">{{ column_left }}
    <main id="content" class="col">{{ content_top }}
      <div class="bs-success-wrap">
      <section class="bs-success-hero">
        <div class="bs-success-icon"><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg></div>
        {% if order_data.order_id is defined and order_data.order_id %}
          <h1>Замовлення #{{ order_data.order_id }} прийнято</h1>
          <p class="bs-success-subtitle">Замовлення в грі — ми вже збираємо ваш лут <svg class="bs-success-ic-inl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="8" y="3" width="12" height="16" rx="2"/><path d="M5 7v12a2 2 0 0 0 2 2h9"/></svg></p>
        {% else %}
          <h1>{{ heading_title }}</h1>
        {% endif %}
      </section>

      {% if order_data.show_first15_offer|default(false) %}
        {# CHECKOUT-007: First15 will be applied automatically on the next order. #}
        <section class="bs-success-f15" role="status">
          <span class="bs-success-f15-ic"><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 12V4h8l10 10-8 8Z"/><path d="M7.5 7.5h.01"/></svg></span>
          <div>
            <strong class="bs-success-f15-k">Дякуємо за реєстрацію!</strong>
            <p class="bs-success-f15-main">На ваше наступне замовлення ми автоматично застосуємо знижку <mark>15%</mark>.</p>
          </div>
        </section>
      {% endif %}

      {% if order_data.order_id is defined and order_data.order_id %}
        {% if order_data.is_iban_bank_transfer|default(false) %}
          {# CHECKOUT-008: copyable IBAN requisites for the just-created order. #}
          <section class="bs-success-card bs-success-iban" aria-labelledby="checkout008-iban-title">
            <h2 id="checkout008-iban-title" class="bs-success-section-title"><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M3 10h18L12 4Z"/><path d="M5 10v8M10 10v8M14 10v8M19 10v8M3 20h18"/></svg> Реквізити для оплати</h2>
            <dl class="bs-success-req">
              <div class="bs-success-req-row"><dt>Отримувач</dt><dd>ФОП Леусенко Євгеній Андрійович</dd></div>
              <div class="bs-success-req-row"><dt>ЄДРПОУ</dt><dd>3485903435</dd></div>
              <div class="bs-success-req-row is-iban"><dt>IBAN</dt><dd>UA063348510000000026003285008</dd></div>
              <div class="bs-success-req-row"><dt>МФО</dt><dd>334851</dd></div>
              <div class="bs-success-req-row"><dt>Банк</dt><dd>АТ «ПУМБ»</dd></div>
              <div class="bs-success-req-row"><dt>Призначення платежу</dt><dd>оплата за товар</dd></div>
            </dl>
            <div class="bs-success-iban-act">
              <button type="button" class="bs-btn bs-btn-secondary" data-checkout008-copy-requisites><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"/></svg> Скопіювати реквізити</button>
              <span class="bs-success-copy-status" data-checkout008-copy-status role="status" aria-live="polite" hidden></span>
            </div>
          </section>
          <script>
          (function () {
            var button = document.querySelector('[data-checkout008-copy-requisites]');
            var status = document.querySelector('[data-checkout008-copy-status]');
            var requisites = 'Реквізити для оплати:\nОтримувач: ФОП Леусенко Євгеній Андрійович\nЄДРПОУ: 3485903435\nIBAN: UA063348510000000026003285008\nМФО: 334851\nБанк: АТ «ПУМБ»\nПризначення платежу: оплата за товар';

            if (!button || !status) {
              return;
            }

            function setStatus(message) {
              status.textContent = message;
              status.hidden = false;
            }

            function fallbackCopy() {
              var textarea = document.createElement('textarea');
              textarea.value = requisites;
              textarea.setAttribute('readonly', '');
              // A fixed invisible textarea prevents a visual jump while supporting browsers without Clipboard API.
              textarea.style.position = 'fixed';
              textarea.style.opacity = '0';
              document.body.appendChild(textarea);
              textarea.select();
              var copied = document.execCommand('copy');
              document.body.removeChild(textarea);
              return copied;
            }

            button.addEventListener('click', function () {
              if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(requisites).then(function () {
                  setStatus('Реквізити скопійовано.');
                }).catch(function () {
                  setStatus(fallbackCopy() ? 'Реквізити скопійовано.' : 'Не вдалося скопіювати реквізити.');
                });
              } else {
                setStatus(fallbackCopy() ? 'Реквізити скопійовано.' : 'Не вдалося скопіювати реквізити.');
              }
            });
          }());
          </script>
        {% endif %}

        <section class="bs-success-card bs-success-steps" aria-labelledby="bs-success-next-title">
          <h2 id="bs-success-next-title" class="bs-success-section-title">Що далі</h2>
          <ol class="bs-success-steplist">
            <li>
              <span class="bs-success-step-ic"><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2Z"/><path d="M9 8h6M9 12h6"/></svg></span>
              <div>
                <strong>Фіскальний чек</strong>
                {% if order_data.is_hutko|default(false) %}
                  <p>Фіскальний чек відправлено на ваш номер або на E-mail.</p>
                {% elseif order_data.is_cod|default(false) %}
                  <p>Фіскальний чек буде відправлено на ваш номер або на E-mail в день отримання замовлення.</p>
                {% else %}
                  <p>Фіскальний чек буде відправлено на ваш номер або на E-mail при відправці замовлення.</p>
                {% endif %}
              </div>
            </li>
            <li>
              <span class="bs-success-step-ic"><svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="6.5" y="2" width="11" height="20" rx="2.5"/><path d="M10.5 18.5h3"/></svg></span>
              <div>
                <strong>Без зайвих дзвінків</strong>
                <p>Ми не телефонуємо і не пишемо для підтвердження без потреби. Якщо всі дані заповнені коректно — просто тихо і швидко відправляємо <svg class="bs-success-ic-inl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M8.5 14s1.3 2 3.5 2 3.5-2 3.5-2"/><path d="M9 9.5h.01M15 9.5h.01"/></svg></p>
              </div>
            </li>
          </ol>
        </section>

        {% if order_items or order_data.shipping_method or order_data.payment_method %}
          <section class="bs-success-card bs-success-items" aria-labelledby="bs-success-items-title" data-bs-success-items>
            <h2 id="bs-success-items-title" class="bs-success-section-title">Ваше замовлення</h2>
            {% if order_data.shipping_method or order_data.payment_method or order_totals %}
              <div class="bs-success-meta">
                {% if order_data.shipping_method %}
                  <div class="bs-success-meta-row">
                    <span class="bs-label">Доставка</span>
                    <span>{{ order_data.shipping_method }}{% if order_data.shipping_display_text %} · {{ order_data.shipping_display_text }}{% endif %}</span>
                  </div>
                {% endif %}
                {% if order_data.payment_method %}
                  <div class="bs-success-meta-row">
                    <span class="bs-label">Оплата</span>
                    <span>{{ order_data.payment_method }}</span>
                  </div>
                {% endif %}
                {% if order_items and order_totals %}
                  <div class="bs-success-meta-row bs-success-meta-sum">
                    <span class="bs-label">Сума</span>
                    <span>{{ (order_totals|last).text }}</span>
                  </div>
                {% endif %}
              </div>
            {% endif %}
            {% if order_items %}
              <div class="bs-success-table-wrap" id="bs-success-items-table">
                <table class="bs-success-table">
                  <tbody>
                    {% for item in order_items %}
                      <tr>
                        <td class="bs-success-item-name"><span class="bs-success-qty">{{ item.quantity }}×</span> {{ item.name }}</td>
                        <td class="bs-success-item-price">{{ item.total }}</td>
                      </tr>
                    {% endfor %}
                  </tbody>
                  {% if order_totals %}
                    <tfoot>
                      {% for total in order_totals %}
                        <tr class="{% if loop.last %}bs-success-grand-total{% endif %}">
                          <td>{{ total.title }}</td>
                          <td class="bs-success-item-price">{{ total.text }}</td>
                        </tr>
                      {% endfor %}
                    </tfoot>
                  {% endif %}
                </table>
              </div>
              <button type="button" class="bs-success-toggle" aria-expanded="false" aria-controls="bs-success-items-table" data-bs-success-toggle data-label-show="Показати товари ({{ order_items|length }})" hidden><span data-bs-success-toggle-label>Показати товари ({{ order_items|length }})</span> <svg class="bs-success-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg></button>
              <script>
              (function () {
                /* RD-14: below 1024px the item table collapses behind a toggle. Without JS, or at 1024px and up,
                   the table stays open and the toggle stays hidden. */
                var card = document.querySelector('[data-bs-success-items]');
                var toggle = card ? card.querySelector('[data-bs-success-toggle]') : null;
                if (!toggle || !window.matchMedia) return;
                var label = toggle.querySelector('[data-bs-success-toggle-label]');
                var narrow = window.matchMedia('(max-width: 1023.98px)');
                var open = false;
                function render() {
                  var collapsible = narrow.matches;
                  card.classList.toggle('is-collapsible', collapsible);
                  card.classList.toggle('is-open', !collapsible || open);
                  toggle.hidden = !collapsible;
                  toggle.setAttribute('aria-expanded', collapsible && open ? 'true' : 'false');
                  label.textContent = open ? 'Сховати товари' : toggle.getAttribute('data-label-show');
                }
                toggle.addEventListener('click', function () { open = !open; render(); });
                if (narrow.addEventListener) narrow.addEventListener('change', render);
                else if (narrow.addListener) narrow.addListener(render);
                render();
              }());
              </script>
            {% endif %}
          </section>
        {% endif %}

        <nav class="bs-success-actions" aria-label="Дії після замовлення">
          <a href="{{ continue }}" class="bs-btn bs-btn-secondary">На головну</a>
          {% if is_logged and history_url %}
            <a href="{{ history_url }}" class="bs-btn bs-btn-secondary">Переглянути замовлення</a>
          {% endif %}
        </nav>

        <section class="bs-success-footer-msg">
          <p class="bs-success-bye"><strong>Вдалого анпакінгу <svg class="bs-success-ic-inl" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M19 12v9H5v-9"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg></strong></p>
          <p>Якщо є питання — <a href="https://telegram.me/boostershop_tcg" target="_blank" rel="noopener" class="bs-success-tg-link"><svg class="bs-success-tg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 0.4-1 1.7 0 2l4.5 1.5 1.7 5.3c0.2 0.7 1.1 0.9 1.6 0.4l2.6-2.4 4.7 3.4c0.6 0.4 1.4 0.1 1.6-0.6l3-14.6c0.2-1-0.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-0.3 3-1.2-3.9 9-6.4c0.3-0.2 0.6 0.1 0.4 0.2Z"/></svg> напишіть у Telegram</a>.</p>
        </section>
      {% else %}
        <section class="bs-success-card bs-success-fallback">
          <div class="bs-success-msg">{{ text_message }}</div>
          <div class="bs-success-actions bs-success-fb-act">
            <a href="{{ continue }}" class="bs-btn bs-btn-secondary">На головну</a>
            <a href="https://telegram.me/boostershop_tcg" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary bs-success-tg-btn"><svg class="bs-success-tg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 0.4-1 1.7 0 2l4.5 1.5 1.7 5.3c0.2 0.7 1.1 0.9 1.6 0.4l2.6-2.4 4.7 3.4c0.6 0.4 1.4 0.1 1.6-0.6l3-14.6c0.2-1-0.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-0.3 3-1.2-3.9 9-6.4c0.3-0.2 0.6 0.1 0.4 0.2Z"/></svg> напишіть у Telegram</a>
          </div>
        </section>
      {% endif %}
      </div>

      {% if ga4_purchase_payload %}
        {# TECH-015-WP1: server-side session dedup permits this purchase payload only once per order. #}
        <script>ps_dataLayer.pushEventData('purchase', {{ ga4_purchase_payload|raw }});</script>
      {% endif %}

      {{ content_bottom }}</main>
    {{ column_right }}</div>
</div>

{{ footer }}
BS_RD14_TWIG;
$success .= "\n";

// Must-survive assertions on the new template (handoff «Claude review», RD-14).
count_exact($success, '{% if ga4_purchase_payload %}', 1, 'new_ga4_block');
count_exact($success, "<script>ps_dataLayer.pushEventData('purchase', {{ ga4_purchase_payload|raw }});</script>", 1, 'new_ga4_push');
count_exact($success, 'data-checkout008-copy-requisites', 2, 'new_copy_hook');
count_exact($success, 'data-checkout008-copy-status', 2, 'new_status_hook');
count_exact($success, 'order_data.show_first15_offer|default(false)', 1, 'new_first15');
count_exact($success, 'order_data.is_iban_bank_transfer|default(false)', 1, 'new_iban');
count_exact($success, 'order_data.is_hutko|default(false)', 1, 'new_hutko');
count_exact($success, 'order_data.is_cod|default(false)', 1, 'new_cod');
count_exact($success, '{% if is_logged and history_url %}', 1, 'new_history');
count_exact($success, 'UA063348510000000026003285008', 2, 'new_iban_value');
// The CHECKOUT-008 copy script must be byte-identical to the live one.
$old = $files[SUCCESS]['text'];
$scriptStart = "          <script>\n          (function () {\n            var button = document.querySelector('[data-checkout008-copy-requisites]');";
$scriptEnd = "          }());\n          </script>\n";
count_exact($old, $scriptStart, 1, 'old_copy_script');
count_exact($success, $scriptStart, 1, 'new_copy_script');
$oldScript = substr($old, strpos($old, $scriptStart), strpos($old, $scriptEnd, strpos($old, $scriptStart)) + strlen($scriptEnd) - strpos($old, $scriptStart));
$newScript = substr($success, strpos($success, $scriptStart), strpos($success, $scriptEnd, strpos($success, $scriptStart)) + strlen($scriptEnd) - strpos($success, $scriptStart));
if ($oldScript !== $newScript) fail('checkout008_copy_script_changed');
out('checkout008_copy_script=identical');
foreach (array('✓', '🎴', '🙂', '🎁', '⚠') as $emoji) count_exact($success, $emoji, 0, 'new_emoji');
count_exact($success, '<style', 0, 'new_inline_style');
twig_hazard_gate($success, SUCCESS);

/* ---- boostershop-ds.css ------------------------------------------------- */
$ds = $files[DSCSS]['text'];
$section = <<<'BS_RD14_CSS'
/* === RD-14: Checkout Success — «Кроки» (2026-10-04) ===
   Replaces the R-11 section that stood here and the inline <style> of success.twig (r11-spacing-fix,
   st2b2-success-spacing, st2b3-success-actions-compact). One centred 620px column, SVG icons, IBAN card,
   «Що далі» card, item table collapsed below 1024px (JS in success.twig; without JS it stays open).
   No !important: the global `#content { padding-bottom: 40px !important }` (R07MOB2) is accepted as the
   page's bottom spacing instead of being overridden again. */
#checkout-success {
  padding-bottom: 8px;
}

#checkout-success #content {
  min-width: 0;
}

#checkout-success .bs-success-wrap {
  max-width: 620px;
  margin: 0 auto;
}

#checkout-success .bs-success-ic {
  width: 18px;
  height: 18px;
  flex: 0 0 auto;
}

#checkout-success .bs-success-ic-inl {
  width: 1.05em;
  height: 1.05em;
  vertical-align: -0.17em;
  color: var(--bs-ink-3);
}

#checkout-success .bs-success-tg {
  width: 17px;
  height: 17px;
  flex: 0 0 auto;
}

/* Hero: white check on --bs-buy (4.55:1). */
#checkout-success .bs-success-hero {
  padding: 24px 0 20px;
  text-align: center;
}

#checkout-success .bs-success-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 56px;
  height: 56px;
  margin-bottom: 14px;
  border-radius: 50%;
  background: var(--bs-buy);
  color: #fff;
}

#checkout-success .bs-success-icon .bs-success-ic {
  width: 28px;
  height: 28px;
}

#checkout-success .bs-success-hero h1 {
  margin: 0 0 6px;
  color: var(--bs-ink);
  font-size: 23px;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: 0;
  text-wrap: balance;
}

#checkout-success .bs-success-subtitle {
  margin: 0;
  color: var(--bs-ink-3);
  font-size: 15px;
  line-height: 1.5;
  text-wrap: pretty;
}

/* Cards */
#checkout-success .bs-success-card {
  margin: 0 0 12px;
  padding: 16px;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r-lg);
  background: var(--bs-paper);
}

#checkout-success .bs-success-section-title {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px;
  margin: 0 0 12px;
  color: var(--bs-ink);
  font-size: 16px;
  font-weight: 800;
  line-height: 1.35;
  letter-spacing: 0;
}

#checkout-success .bs-success-section-title .bs-success-ic {
  color: var(--bs-blue);
}

/* First15 — variant A «рамка» */
#checkout-success .bs-success-f15 {
  display: flex;
  align-items: flex-start;
  gap: 14px;
  margin: 0 0 12px;
  padding: 16px;
  border: 2px solid var(--bs-buy);
  border-radius: var(--bs-r-lg);
  background: var(--bs-green-soft);
}

#checkout-success .bs-success-f15-ic {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: var(--bs-buy);
  color: #fff;
}

#checkout-success .bs-success-f15-ic .bs-success-ic {
  width: 20px;
  height: 20px;
}

#checkout-success .bs-success-f15-k {
  display: block;
  margin-bottom: 2px;
  color: var(--bs-buy);
  font-size: 13px;
  font-weight: 700;
}

#checkout-success .bs-success-f15-main {
  margin: 0;
  color: var(--bs-ink);
  font-size: 17px;
  font-weight: 800;
  line-height: 1.4;
  text-wrap: pretty;
}

#checkout-success .bs-success-f15-main mark {
  padding: 0;
  background: none;
  color: var(--bs-buy);
  font-size: 1.25em;
  font-weight: 800;
}

/* IBAN requisites (CHECKOUT-008 hooks and script unchanged) */
#checkout-success .bs-success-req {
  display: grid;
  margin: 0 0 14px;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  overflow: hidden;
}

#checkout-success .bs-success-req-row {
  display: grid;
  gap: 1px;
  padding: 9px 12px;
  border-bottom: 1px solid var(--bs-line-2);
}

#checkout-success .bs-success-req-row:last-child {
  border-bottom: 0;
}

#checkout-success .bs-success-req dt {
  color: var(--bs-ink-3);
  font-size: 12px;
  font-weight: 600;
}

#checkout-success .bs-success-req dd {
  margin: 0;
  color: var(--bs-ink);
  font-weight: 600;
  overflow-wrap: anywhere;
  font-variant-numeric: tabular-nums;
}

#checkout-success .bs-success-req-row.is-iban {
  background: var(--bs-blue-soft);
}

#checkout-success .bs-success-req-row.is-iban dd {
  color: var(--bs-blue);
  font-size: 15px;
  font-weight: 800;
  letter-spacing: 0.02em;
}

#checkout-success .bs-success-iban-act {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 14px;
}

#checkout-success .bs-success-iban-act .bs-btn {
  width: 100%;
}

/* The CHECKOUT-008 script writes both the success and the failure message into this element, so it carries
   no success colour or check icon of its own. */
#checkout-success .bs-success-copy-status {
  color: var(--bs-ink-2);
  font-size: 13px;
  font-weight: 700;
}

/* «Що далі» */
#checkout-success .bs-success-steplist {
  display: grid;
  margin: 0;
  padding: 0;
  list-style: none;
}

#checkout-success .bs-success-steplist li {
  display: grid;
  grid-template-columns: 36px minmax(0, 1fr);
  gap: 12px;
  padding: 12px 0;
  border-top: 1px solid var(--bs-line-2);
}

#checkout-success .bs-success-steplist li:first-child {
  padding-top: 2px;
  border-top: 0;
}

#checkout-success .bs-success-step-ic {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--bs-blue-soft);
  color: var(--bs-blue);
}

#checkout-success .bs-success-steplist strong {
  display: block;
  margin: 6px 0 2px;
  color: var(--bs-ink);
  font-size: 15px;
}

#checkout-success .bs-success-steplist p {
  margin: 0;
  color: var(--bs-ink-2);
  font-size: 14px;
  line-height: 1.5;
}

/* «Ваше замовлення» */
#checkout-success .bs-success-meta {
  display: grid;
  gap: 10px;
  margin: 0 0 4px;
  padding: 0 0 12px;
  border-bottom: 1px solid var(--bs-line);
}

#checkout-success .bs-success-meta-row {
  display: grid;
  align-content: start;
  gap: 2px;
  color: var(--bs-ink);
  font-size: 14px;
  line-height: 1.5;
}

#checkout-success .bs-label {
  color: var(--bs-ink-3);
  font-size: 13px;
}

#checkout-success .bs-success-meta-sum {
  display: none;
}

#checkout-success .bs-success-items.is-collapsible .bs-success-meta-sum {
  display: grid;
}

#checkout-success .bs-success-meta-sum span:last-child {
  font-size: 16px;
  font-weight: 800;
  font-variant-numeric: tabular-nums;
}

#checkout-success .bs-success-items.is-collapsible .bs-success-meta {
  padding-bottom: 4px;
  border-bottom: 0;
}

#checkout-success .bs-success-items.is-collapsible:not(.is-open) .bs-success-table-wrap {
  display: none;
}

#checkout-success .bs-success-items.is-collapsible .bs-success-table {
  margin-top: 8px;
  border-top: 1px solid var(--bs-line);
}

#checkout-success .bs-success-table {
  width: 100%;
  border-collapse: collapse;
  color: var(--bs-ink-2);
  font-size: 14px;
  line-height: 1.5;
}

#checkout-success .bs-success-table td {
  padding: 10px 0;
  border-bottom: 1px solid var(--bs-line-2);
  vertical-align: top;
}

#checkout-success .bs-success-table td + td {
  padding-left: 16px;
}

#checkout-success .bs-success-item-name {
  color: var(--bs-ink);
  overflow-wrap: anywhere;
  text-wrap: pretty;
}

#checkout-success .bs-success-qty {
  color: var(--bs-ink-3);
  font-weight: 700;
  font-variant-numeric: tabular-nums;
}

#checkout-success .bs-success-item-price {
  text-align: right;
  white-space: nowrap;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
}

#checkout-success .bs-success-table tfoot td {
  padding-top: 6px;
  padding-bottom: 6px;
  border-bottom: 0;
  color: var(--bs-ink-3);
}

#checkout-success .bs-success-table tfoot tr:first-child td {
  padding-top: 12px;
}

#checkout-success .bs-success-table tfoot .bs-success-item-price {
  color: var(--bs-ink-2);
}

#checkout-success .bs-success-table tfoot .bs-success-grand-total td {
  padding-top: 12px;
  border-top: 1px solid var(--bs-line);
  color: var(--bs-ink);
  font-size: 16px;
  font-weight: 800;
}

#checkout-success .bs-success-toggle {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: center;
  gap: 6px;
  width: 100%;
  min-height: 44px;
  margin-top: 10px;
  padding: 0 12px;
  border: 1px solid var(--bs-line);
  border-radius: 8px;
  background: #fff;
  color: var(--bs-blue);
  font: inherit;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
}

#checkout-success .bs-success-toggle[hidden] {
  display: none;
}

#checkout-success .bs-success-toggle:hover {
  background: var(--bs-line-2);
}

#checkout-success .bs-success-toggle .bs-success-ic {
  width: 16px;
  height: 16px;
  transition: transform 0.2s;
}

#checkout-success .bs-success-toggle[aria-expanded="true"] .bs-success-ic {
  transform: rotate(180deg);
}

/* Actions: both secondary. `.bs a` (0,1,1) would paint link buttons navy and underline them on hover. */
#checkout-success .bs-success-actions {
  display: grid;
  gap: 10px;
  margin: 16px 0;
}

#checkout-success .bs-success-actions .bs-btn {
  width: 100%;
}

#checkout-success .bs-btn-secondary,
#checkout-success .bs-btn-secondary:hover {
  color: var(--bs-ink);
  text-decoration: none;
}

/* Footer note */
#checkout-success .bs-success-footer-msg {
  padding: 8px 0 4px;
  color: var(--bs-ink-3);
  font-size: 14px;
  line-height: 1.65;
  text-align: center;
  text-wrap: pretty;
}

#checkout-success .bs-success-footer-msg p {
  margin: 0 0 10px;
}

#checkout-success .bs-success-footer-msg p:last-child {
  margin: 0;
}

#checkout-success .bs-success-bye {
  color: var(--bs-ink);
}

#checkout-success .bs-success-tg-link {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  color: var(--bs-blue);
  font-weight: 700;
  text-decoration: underline;
  text-underline-offset: 3px;
}

/* Fallback (no order number): text_message unchanged. Its own Telegram link (`.bs-telegram-button` from the
   language file) becomes a plain underlined link — the old white-on-#229ED9 pill was 3.0:1. */
#checkout-success .bs-success-msg {
  color: var(--bs-ink-2);
  font-size: 14px;
  line-height: 1.65;
  text-wrap: pretty;
}

#checkout-success .bs-success-msg p {
  margin: 0 0 10px;
}

#checkout-success .bs-success-msg p:last-child {
  margin: 0;
}

#checkout-success .bs-success-msg a {
  color: var(--bs-blue);
  text-decoration: underline;
  text-underline-offset: 3px;
}

#checkout-success .bs-success-fb-act {
  margin: 16px 0 0;
}

@media (min-width: 768px) {
  #checkout-success .bs-success-hero {
    padding: 36px 0 28px;
  }

  #checkout-success .bs-success-hero h1 {
    font-size: 26px;
  }

  #checkout-success .bs-success-card {
    margin-bottom: 16px;
    padding: 20px 22px;
  }

  #checkout-success .bs-success-f15 {
    margin-bottom: 16px;
    padding: 18px 22px;
  }

  #checkout-success .bs-success-req-row {
    grid-template-columns: 160px minmax(0, 1fr);
    align-items: baseline;
    gap: 12px;
  }

  #checkout-success .bs-success-iban-act .bs-btn {
    width: auto;
  }

  #checkout-success .bs-success-meta {
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  #checkout-success .bs-success-actions {
    display: flex;
    justify-content: center;
    gap: 12px;
  }

  #checkout-success .bs-success-actions .bs-btn {
    width: auto;
    min-width: 220px;
  }

  #checkout-success .bs-success-fb-act {
    justify-content: flex-start;
  }

  #checkout-success .bs-success-fb-act .bs-btn {
    min-width: 0;
  }
}

@media (min-width: 1200px) {
  #checkout-success .bs-success-hero {
    padding: 48px 0 32px;
  }

  #checkout-success .bs-success-hero h1 {
    font-size: 28px;
  }
}
/* === /RD-14: Checkout Success === */
BS_RD14_CSS;
$ds = replace_block($ds, R11_START, R11_END, R11_SHA, $section . "\n", 'ds_r11_section');
$ds = replace_one(
    $ds,
    ".breadcrumb .breadcrumb-item > a {\n  display: inline-flex; align-items: center; justify-content: center;\n",
    "/* RD-14: `> span` is a current page rendered without a link (checkout success/failure). */\n"
    . ".breadcrumb .breadcrumb-item > a,\n.breadcrumb .breadcrumb-item > span {\n  display: inline-flex; align-items: center; justify-content: center;\n",
    'ds_crumb_pill'
);
$ds = replace_one(
    $ds,
    ".breadcrumb .breadcrumb-item:last-child > a,\n.breadcrumb .breadcrumb-item.active {\n",
    ".breadcrumb .breadcrumb-item:last-child > a,\n.breadcrumb .breadcrumb-item:last-child > span,\n.breadcrumb .breadcrumb-item.active {\n",
    'ds_crumb_current'
);
count_exact($ds, '#checkout-success', substr_count($section, '#checkout-success'), 'ds_success_rules_only_in_section');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token ------------------------------------------- */
$header = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(SUCCESS => array($files[SUCCESS]['text'], $success), HEADER => array($files[HEADER]['text'], $header)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    SUCCESS => encode_text($files[SUCCESS], $success),
    DSCSS   => encode_text($files[DSCSS], $ds),
    HEADER  => encode_text($files[HEADER], $header),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the RD-14 checks in diagnostics/RD-14_success-steps_report_20261004.md and bs-checkout-smoke');
