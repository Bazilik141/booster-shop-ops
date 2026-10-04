<?php
declare(strict_types=1);

/**
 * RD-15 — checkout failure page «Оплата не пройшла», direction «Дві колонки»
 * =============================================================================
 * Chain     : runner 2 of 7. Requires runner 1 (RD-14_success-steps_20261004) applied first.
 * Handoff   : handoffs/handoff_RD-15_failure_claude-code_20261004.md (+ INDEX, Claude review 2026-10-04)
 * Design    : handoffs/design_20261004_rd14-15_ux003-005-009/RD-14 RD-15 - фінал.html, state «RD-15 failure»
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout-adjacent. Template + CSS + three uk-ua language strings. Routing to the page,
 *             Hutko, cart clearing and fiscalisation are not touched.
 * DB changes: NONE.  Controller: not touched.
 * RUN FROM  : ~/public_html    ->    php RD-15_failure-two-columns_20261004.php
 *
 * WHAT CHANGES
 *   extension/ukrainian/catalog/language/uk-ua/checkout/failure.php (the Ukrainian language-pack extension —
 *     reinstalling that pack would revert these three strings):
 *       heading_title  'Помилка оплати!'  → 'Оплата не пройшла'   (also the <title>, via setTitle)
 *       text_failure   'Помилка оплати'   → 'Оплата не пройшла'   (last breadcrumb; Claude review overrides §5)
 *       text_message   → the owner's two paragraphs, verbatim, with links Telegram / mailto / tel. The
 *                        controller passes it through sprintf(): the new text has no '%' at all, and the
 *                        dropped '%s' only means the contact-URL argument is ignored.
 *     text_basket, text_checkout and every other language are untouched.
 *   catalog/view/template/checkout/failure.twig — DS card: SVG alert icon (⚠️ removed), H1, message, actions
 *     «На головну» (no «←») + «напишіть у Telegram», both secondary; last breadcrumb without a link.
 *     The inline R-11b <style> is removed (superseded).
 *   catalog/view/stylesheet/boostershop-ds.css — new section «RD-15: Checkout Failure» right after RD-14.
 *   catalog/view/template/common/header.twig — boostershop-ds.css ?v= token only.
 *
 * NOTE   The same page is also the redirect target of the COD / bank-transfer / cheque / free-checkout confirm
 *        actions when the session has lost its order (stock OpenCart). Those paths now also read «Оплата не
 *        пройшла». Routing is out of scope (CHECKOUT-012).
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: the inline R-11b <style> in failure.twig; replaced at the source, nothing
 *   stacked. No !important. Breadcrumb pill for the non-link last item comes from RD-14's extension of the
 *   global fallback rule. Magic numbers from the approved design: 600 card, 48 icon column, 4px strip.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all four targets (the RD-14 output for ds.css/header.twig) → marker check → anchors →
 *   php -l on the new language file (before writing, and again after) → Twig parse gate on failure.twig and
 *   header.twig → hazard scan → CSS balance gate → backup of all four → write → any failure restores all.
 *   Idempotent: marker «RD-15» in failure.twig, failure.php and boostershop-ds.css. Self-deletes on success.
 *   ROLLBACK: copy the four files back from the backup folder, refresh the theme cache, Ctrl+F5. Only while
 *   no later runner of the chain is applied.
 *   TRIGGER: PHP error or blank page on checkout/failure, wrong/garbled text, a payment flow no longer
 *   reaching success.
 * =============================================================================
 */

const PATCH_ID = 'RD-15_failure-two-columns_20261004';
const MARKER   = 'RD-15';
const TOKEN    = 'rd15-20261004';
const FAILURE  = 'catalog/view/template/checkout/failure.twig';
const LANG     = 'extension/ukrainian/catalog/language/uk-ua/checkout/failure.php';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    FAILURE => 'dde1c82625ce998a9ab3b6eba8bafac34f8dfdf3a50352d2e95f21cb250547aa',
    LANG    => 'beb30efb7180aec2a83c43c9880c50f55c6ebcd25576c7a7b2aed02e1fad6b32',
    DSCSS   => '43ea570f684809915d72caad83095121f1a4dcda6fa52a0218b50feb8d1254f1',
    HEADER  => '071ddd87e14a1974e85734681fdb3948d0d862c233a2024e67933316bef4552d',
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
foreach (array(FAILURE, LANG, DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(FAILURE, LANG, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- language file ------------------------------------------------------- */
$lang = $files[LANG]['text'];
$lang = replace_one($lang, "<?php\n// Heading\n", "<?php\n// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n// Heading\n", 'lang_marker');
$lang = replace_one($lang, "\$_['heading_title'] = 'Помилка оплати!';", "\$_['heading_title'] = 'Оплата не пройшла';", 'lang_heading');
$lang = replace_one($lang, "\$_['text_failure'] = 'Помилка оплати';", "\$_['text_failure'] = 'Оплата не пройшла';", 'lang_text_failure');
$newMessage = '<p>Платіж не було завершено. Так буває, коли банк відхилив операцію або сторінку оплати закрили трохи раніше, ніж потрібно.</p>'
    . '<p>Якщо кошти списались або ви не впевнені, чи створено замовлення, напишіть нам у <a href="https://telegram.me/boostershop_tcg" target="_blank" rel="noopener">Telegram</a>, '
    . 'на <a href="mailto:helpbs@boostershop.website">електронну пошту</a> або подзвоніть за номером <a href="tel:+380636743252" class="bs-failure-nowrap">+380636743252</a>.</p>';
if (strpos($newMessage, '%') !== false || strpos($newMessage, "'") !== false) fail('lang_new_message_unsafe');
$oldLine = "\$_['text_message'] = '<p>У процесі оплати виникла помилка.";
count_exact($lang, $oldLine, 1, 'lang_text_message');
$a = strpos($lang, $oldLine);
$b = strpos($lang, "';", $a + strlen($oldLine));
if ($b === false) fail('lang_text_message_end');
$lang = substr($lang, 0, $a) . "\$_['text_message'] = '" . $newMessage . substr($lang, $b);
count_exact($lang, "\$_['text_basket'] = 'Кошик покупок';", 1, 'lang_basket_kept');
count_exact($lang, "\$_['text_checkout'] = 'Оформлення замовлення';", 1, 'lang_checkout_kept');
$langBytes = encode_text($files[LANG], $lang);
php_lint_bytes($root, LANG, $langBytes);
// The new strings must load and survive the controller's sprintf() unchanged.
$_ = array();
$langTmp = $root . '/' . LANG . '.' . PATCH_ID . '.load.php';
if (file_put_contents($langTmp, $langBytes, LOCK_EX) !== strlen($langBytes)) fail('lang_load_temp_failed');
(static function () use ($langTmp, &$_): void { include $langTmp; })();
@unlink($langTmp);
if (($_['heading_title'] ?? '') !== 'Оплата не пройшла' || ($_['text_failure'] ?? '') !== 'Оплата не пройшла') fail('lang_values_check');
if (sprintf((string)($_['text_message'] ?? ''), 'https://example.invalid/contact') !== $newMessage) fail('lang_sprintf_check');
out('lang_values=ok sprintf=ok');

/* ---- failure.twig -------------------------------------------------------- */
$failure = <<<'BS_RD15_TWIG'
{{ header }}
{# RD-15 (2026-10-04): «Дві колонки». Copy comes from the uk-ua language file (heading_title, text_message);
   styles live in boostershop-ds.css (section RD-15). Routing to this page is unchanged (CHECKOUT-012). #}
<div id="checkout-failure" class="container">
  <ul class="breadcrumb">
    {% for breadcrumb in breadcrumbs %}
      {% if loop.last %}
        <li class="breadcrumb-item" aria-current="page"><span>{{ breadcrumb.text }}</span></li>
      {% else %}
        <li class="breadcrumb-item"><a href="{{ breadcrumb.href }}">{{ breadcrumb.text }}</a></li>
      {% endif %}
    {% endfor %}
  </ul>
  <div class="row">
    {{ column_left }}
    <div id="content" class="col">
      {{ content_top }}

      <section class="bs-failure-card">
        <div class="bs-failure-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5h.01"/></svg></div>
        <div class="bs-failure-body">
          <h1 class="bs-failure-title">{{ heading_title }}</h1>
          <div class="bs-failure-message">{{ text_message }}</div>
          <div class="bs-failure-actions">
            <a href="{{ continue }}" class="bs-btn bs-btn-secondary">На головну</a>
            <a href="https://telegram.me/boostershop_tcg" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary"><svg class="bs-failure-tg" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="#229ED9" d="M21.2 4.3 2.9 11.4c-1 0.4-1 1.7 0 2l4.5 1.5 1.7 5.3c0.2 0.7 1.1 0.9 1.6 0.4l2.6-2.4 4.7 3.4c0.6 0.4 1.4 0.1 1.6-0.6l3-14.6c0.2-1-0.6-1.5-1.4-1.1Zm-3.4 3.5-7.9 7.1-0.3 3-1.2-3.9 9-6.4c0.3-0.2 0.6 0.1 0.4 0.2Z"/></svg> напишіть у Telegram</a>
          </div>
        </div>
      </section>

      {{ content_bottom }}
    </div>
    {{ column_right }}
  </div>
</div>

{{ footer }}
BS_RD15_TWIG;
$failure .= "\n";
count_exact($failure, '{{ text_message }}', 1, 'new_text_message');
count_exact($failure, '{{ heading_title }}', 1, 'new_heading');
count_exact($failure, '{{ continue }}', 1, 'new_continue');
foreach (array('⚠', '←', '<style') as $gone) count_exact($failure, $gone, 0, 'new_removed');
twig_hazard_gate($failure, FAILURE);

/* ---- boostershop-ds.css -------------------------------------------------- */
$section = <<<'BS_RD15_CSS'
/* === RD-15: Checkout Failure — «Дві колонки» (2026-10-04) ===
   Replaces the inline R-11b <style> of failure.twig (the page's only source rules until now). 600px card,
   4px warning strip, SVG icon beside the text from 768px, above it below. No !important. */
#checkout-failure {
  padding-bottom: 8px;
}

#checkout-failure .bs-failure-card {
  max-width: 600px;
  margin: 20px auto 24px;
  padding: 20px 16px;
  border: 1px solid var(--bs-line);
  border-top: 4px solid var(--bs-warning-line);
  border-radius: var(--bs-r-lg);
  background: var(--bs-paper);
}

#checkout-failure .bs-failure-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  margin-bottom: 12px;
  border: 1px solid var(--bs-warning-line);
  border-radius: 50%;
  background: var(--bs-warning-bg);
  color: var(--bs-warning-fg);
}

#checkout-failure .bs-failure-icon svg {
  width: 24px;
  height: 24px;
}

#checkout-failure .bs-failure-body {
  min-width: 0;
}

#checkout-failure .bs-failure-title {
  margin: 0 0 12px;
  color: var(--bs-ink);
  font-size: 22px;
  font-weight: 800;
  line-height: 1.25;
  letter-spacing: 0;
}

#checkout-failure .bs-failure-message {
  color: var(--bs-ink-2);
  font-size: 15px;
  line-height: 1.65;
  text-wrap: pretty;
}

#checkout-failure .bs-failure-message p {
  margin: 0 0 10px;
}

#checkout-failure .bs-failure-message p:last-child {
  margin-bottom: 0;
}

#checkout-failure .bs-failure-message a {
  color: var(--bs-blue);
  text-decoration: underline;
  text-underline-offset: 3px;
}

#checkout-failure .bs-failure-nowrap {
  white-space: nowrap;
}

#checkout-failure .bs-failure-actions {
  display: grid;
  gap: 10px;
  margin: 20px 0 0;
}

#checkout-failure .bs-failure-actions .bs-btn {
  width: 100%;
}

/* `.bs a` (0,1,1) would paint both link buttons navy and underline them on hover. */
#checkout-failure .bs-btn-secondary,
#checkout-failure .bs-btn-secondary:hover {
  color: var(--bs-ink);
  text-decoration: none;
}

#checkout-failure .bs-failure-tg {
  width: 17px;
  height: 17px;
  flex: 0 0 auto;
}

@media (min-width: 768px) {
  #checkout-failure .bs-failure-card {
    display: grid;
    grid-template-columns: 48px minmax(0, 1fr);
    gap: 20px;
    padding: 28px;
  }

  #checkout-failure .bs-failure-icon {
    margin: 0;
  }

  #checkout-failure .bs-failure-actions {
    display: flex;
    justify-content: flex-start;
    gap: 12px;
  }

  #checkout-failure .bs-failure-actions .bs-btn {
    width: auto;
  }
}
/* === /RD-15: Checkout Failure === */
BS_RD15_CSS;
count_exact($files[DSCSS]['text'], '#checkout-failure', 0, 'ds_no_failure_rules_yet');
$anchor = "/* === /RD-14: Checkout Success === */\n";
$ds = replace_one($files[DSCSS]['text'], $anchor, $anchor . "\n" . $section . "\n", 'ds_after_rd14');
css_balance_gate($ds, DSCSS);

/* ---- header.twig: cache token -------------------------------------------- */
$header = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(FAILURE => array($files[FAILURE]['text'], $failure), HEADER => array($files[HEADER]['text'], $header)));

commit_files($root, $files, array(
    LANG    => $langBytes,
    FAILURE => encode_text($files[FAILURE], $failure),
    DSCSS   => encode_text($files[DSCSS], $ds),
    HEADER  => encode_text($files[HEADER], $header),
), array(LANG));

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, open index.php?route=checkout/failure at 390 / 768 / 1440');
