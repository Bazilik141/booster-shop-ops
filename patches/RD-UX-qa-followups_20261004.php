<?php
declare(strict_types=1);

/**
 * RD-14 / RD-15 / UX-003-005 — owner-QA follow-ups after the 2026-10-04 deploy
 * =============================================================================
 * Chain     : runner 3b. Requires runners 1–3 (RD-14, RD-15, UX-003-005 header) applied — they are live.
 *             Runners 4–7 are built on this runner's output.
 * Handoff   : handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md,
 *             section «Deploy log and runner 3b (owner QA, 2026-10-04)» + review note N2
 *             (diagnostics/RD-UX-batch_runners-1-3_review_20261004.md)
 * Author    : Claude Code · 2026-10-04
 * Risk      : checkout success/failure pages (links only), header burger (one link), mini-cart drawer (one
 *             button's behaviour). No checkout logic, form markup, payment or order code touched.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php RD-UX-qa-followups_20261004.php
 *
 * WHAT CHANGES
 *   1 Support links → bot (owner decision 2026-10-04). https://telegram.me/boostershop_tcg →
 *     https://telegram.me/BoosterShop_Support_bot in:
 *       checkout/success.twig — RD-14 footer link «напишіть у Telegram» and the fallback-state button (2×);
 *       checkout/failure.twig — RD-15 button «напишіть у Telegram» (1×);
 *       extension/ukrainian/.../uk-ua/checkout/failure.php — the Telegram link inside text_message (1×);
 *         php -l before and after the write, and the new text must load and survive the controller's sprintf().
 *     Channel links (header Telegram icon, burger «Наш Telegram-канал», footer) stay on boostershop_tcg.
 *   2 Burger: «Набори та бокси One Piece» → /catalog/One-Piece/one-piece-nabory-ta-boksy, between «Бустери One
 *     Piece» and «Фігурки та декор». Root-relative, class bs-menu__sub (SEO path 60_68, verified by Claude in
 *     the 2026-09-24 backup).
 *   3 Mini-cart empty state «До каталогу»: keeps data-bs-mini-cart-close (the drawer still closes through the
 *     existing handler) and gains data-bs-mini-cart-catalog. A new delegated handler in cart.twig's own script
 *     makes sure the drawer is closed — setMiniCartDrawerState(false) restores body position/top and scrolls
 *     back synchronously, i.e. the drawer's scroll lock is released — then moves focus to #bs-menu-open and
 *     clicks it on the next animation frame, so the burger's own lock (body.bs-menu-lock) starts on a clean
 *     body and closing the burger returns focus to «Каталог». No #bs-menu-open on the page → close only.
 *   4 Review N2: #checkout-success .bs-success-f15-k colour --bs-buy → --bs-buy-hover (#15803D). 13px bold on
 *     --bs-green-soft was 4.32:1; same hue, now above AA. Only ds.css change → ds.css ?v= token in header.twig.
 *
 * UI/CSS DISCIPLINE
 *   N2 edits the source rule (RD-14 section of boostershop-ds.css). No !important, no new override, no
 *   position rules, no magic numbers. JS: requestAnimationFrame (not setTimeout) to open the burger after the
 *   close has run.
 *
 * NOT CHANGED
 *   cart.twig is not touched by runners 4–7; BUG-004 (mobile badge, swipe-down reload) builds on this output.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all six targets (the deployed post-runner-3 state) → marker → anchors → php -l + load +
 *   sprintf check on the language file → hazard scan on added template text → Twig parse gate on success.twig,
 *   failure.twig, header.twig, cart.twig → CSS balance gate → backup of all six → write → post-write php -l →
 *   any failure restores all six.
 *   Idempotent: marker «RD-UX-QA-3B» in all six files. Self-deletes on success.
 *   ROLLBACK: copy the six files back from _patch_backups/RD-UX-qa-followups_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runners 4–7 are not applied.
 *   TRIGGER: PHP error or blank checkout/success or checkout/failure page; burger or mini-cart won't open or
 *   close; page stays unscrollable after «До каталогу»; JS error from cart.twig's script.
 * =============================================================================
 */

const PATCH_ID = 'RD-UX-qa-followups_20261004';
const MARKER   = 'RD-UX-QA-3B';
const TOKEN    = 'rdux3b-20261004';
const SUCCESS  = 'catalog/view/template/checkout/success.twig';
const FAILURE  = 'catalog/view/template/checkout/failure.twig';
const LANG     = 'extension/ukrainian/catalog/language/uk-ua/checkout/failure.php';
const HEADER   = 'catalog/view/template/common/header.twig';
const CART     = 'catalog/view/template/common/cart.twig';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const TG_CHANNEL = 'https://telegram.me/boostershop_tcg';
const TG_BOT     = 'https://telegram.me/BoosterShop_Support_bot';

const EXPECTED_SHA = array(
    SUCCESS => '8e2f322379430c8997e78bf9cb3a8e12cacf0c9e43579acfffc5028fa5dba95d',
    FAILURE => 'baa1a0d31335e95b29de17768f7b777bb6c9d98e4105a096fbeec84060e99583',
    LANG    => 'd3a3f4a0b95669eba9e74f257a5530fd580b7e7245713109b9385d296b64fc21',
    HEADER  => '6fe54429ede0a524da90f1d62b0882503aa70a656ea7c62b894eae63c387b734',
    CART    => 'e3c3b270cce149207955c531f18246712b899410b0b25d5b8d62f3a6153f0032',
    DSCSS   => 'b45d9871d76f2f4a917c34ca29cb62e55fa1a9f8d24951b20400ea753289006b',
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
foreach (array(SUCCESS, FAILURE, LANG, HEADER, CART, DSCSS) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(SUCCESS, FAILURE, LANG, HEADER, CART, DSCSS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- 1a success.twig: footer link + fallback button ----------------------- */
$success = $files[SUCCESS]['text'];
$successMustSurvive = array('{{ text_message }}', 'dataLayer.push', "'purchase'", 'CHECKOUT-008', 'bs-success-tg-link', 'bs-success-tg-btn', 'bs-success-fallback');
$successCounts = array();
foreach ($successMustSurvive as $probe) $successCounts[$probe] = substr_count($success, $probe);
count_exact($success, TG_CHANNEL, 2, 'success_channel_links');
count_exact($success, TG_BOT, 0, 'success_bot_links_before');
$success = str_replace('href="' . TG_CHANNEL . '"', 'href="' . TG_BOT . '"', $success);
$success = replace_one($success,
    "        <section class=\"bs-success-footer-msg\">\n",
    "        {# RD-UX-QA-3B (2026-10-04): support links go to the support bot (owner decision); the channel stays in the header/footer. #}\n"
    . "        <section class=\"bs-success-footer-msg\">\n",
    'success_marker');
count_exact($success, TG_CHANNEL, 0, 'success_channel_links_after');
count_exact($success, 'href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-success-tg-link"', 1, 'success_bot_footer');
count_exact($success, 'href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary bs-success-tg-btn"', 1, 'success_bot_fallback');
foreach ($successCounts as $probe => $n) count_exact($success, $probe, $n, 'success_kept');

/* ---- 1b failure.twig: button ---------------------------------------------- */
$failure = $files[FAILURE]['text'];
count_exact($failure, TG_CHANNEL, 1, 'failure_channel_link');
$failure = replace_one($failure,
    '<a href="' . TG_CHANNEL . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary">',
    '<a href="' . TG_BOT . '" target="_blank" rel="noopener" class="bs-btn bs-btn-secondary">',
    'failure_button');
$failure = replace_one($failure,
    "   styles live in boostershop-ds.css (section RD-15). Routing to this page is unchanged (CHECKOUT-012). #}\n",
    "   styles live in boostershop-ds.css (section RD-15). Routing to this page is unchanged (CHECKOUT-012). #}\n"
    . "{# RD-UX-QA-3B (2026-10-04): the Telegram button goes to the support bot (owner decision). #}\n",
    'failure_marker');
count_exact($failure, TG_CHANNEL, 0, 'failure_channel_after');
foreach (array('{{ text_message }}', '{{ heading_title }}', '{{ continue }}') as $probe) count_exact($failure, $probe, 1, 'failure_kept');

/* ---- 1c language file: text_message link ---------------------------------- */
$lang = $files[LANG]['text'];
$lang = replace_one($lang,
    "// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n",
    "// RD-15 (2026-10-04): heading_title, text_failure and text_message rewritten (owner copy).\n"
    . "// RD-UX-QA-3B (2026-10-04): the Telegram link in text_message goes to the support bot (owner decision).\n",
    'lang_marker');
$oldMessageLink = 'напишіть нам у <a href="' . TG_CHANNEL . '" target="_blank" rel="noopener">Telegram</a>';
$newMessageLink = 'напишіть нам у <a href="' . TG_BOT . '" target="_blank" rel="noopener">Telegram</a>';
$lang = replace_one($lang, $oldMessageLink, $newMessageLink, 'lang_text_message_link');
count_exact($lang, TG_CHANNEL, 0, 'lang_channel_after');
$langBytes = encode_text($files[LANG], $lang);
php_lint_bytes($root, LANG, $langBytes);
// Expected strings: what the deployed file holds, with only the link swapped.
$_ = array();
$langTmp = $root . '/' . LANG . '.' . PATCH_ID . '.load.php';
if (file_put_contents($langTmp, $files[LANG]['raw'], LOCK_EX) !== strlen($files[LANG]['raw'])) fail('lang_load_temp_failed');
(static function () use ($langTmp, &$_): void { include $langTmp; })();
$before = $_;
$_ = array();
if (file_put_contents($langTmp, $langBytes, LOCK_EX) !== strlen($langBytes)) { @unlink($langTmp); fail('lang_load_temp_failed'); }
(static function () use ($langTmp, &$_): void { include $langTmp; })();
@unlink($langTmp);
$expectedMessage = str_replace($oldMessageLink, $newMessageLink, (string)($before['text_message'] ?? ''));
if (($_['text_message'] ?? null) !== $expectedMessage || strpos($expectedMessage, TG_BOT) === false) fail('lang_values_check');
if (array_keys($_) !== array_keys($before)) fail('lang_keys_changed');
foreach ($before as $key => $value) if ($key !== 'text_message' && $_[$key] !== $value) fail('lang_other_value_changed=' . $key);
if (sprintf((string)$_['text_message'], 'https://example.invalid/contact') !== $expectedMessage) fail('lang_sprintf_check');
out('lang_values=ok sprintf=ok');

/* ---- 2 header.twig: burger link + ds.css token ---------------------------- */
$h = $files[HEADER]['text'];
$hooks = array('id="ps-live-search-input"', 'id="ps-live-search"', 'id="bs-menu-open"', 'id="bs-menu"', 'data-bs-menu-close',
    'data-bs-accordion', 'class="bs-menu__subs"', 'id="bs-msearch"', 'id="cart"', 'id="alert"', TG_CHANNEL);
$hookCounts = array();
foreach ($hooks as $hook) $hookCounts[$hook] = substr_count($h, $hook);
$h = replace_one($h,
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n"
    . "          <a href=\"/catalog/One-Piece/figurky-ta-dekor-one-piece\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n"
    . "          {# RD-UX-QA-3B (2026-10-04): sets and boxes (SEO path 60_68). #}\n"
    . "          <a href=\"/catalog/One-Piece/one-piece-nabory-ta-boksy\" class=\"bs-menu__sub\">Набори та бокси One Piece</a>\n"
    . "          <a href=\"/catalog/One-Piece/figurky-ta-dekor-one-piece\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    'hdr_onepiece_sets');
$h = bust_token($h, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
foreach ($hookCounts as $hook => $n) count_exact($h, $hook, $n, 'hdr_hook_kept');
count_exact($h, TG_BOT, 0, 'hdr_no_bot');

/* ---- 3 cart.twig: «До каталогу» → close, then the burger ------------------ */
$cart = $files[CART]['text'];
$cartHooks = array('data-bs-mini-cart-open', 'data-bs-mini-cart-close', 'data-bs-mini-cart', 'function setMiniCartDrawerState(open)');
$cartCounts = array();
foreach ($cartHooks as $hook) $cartCounts[$hook] = substr_count($cart, $hook);
$cart = replace_one($cart,
    "   navigation, so it uses bs-btn-secondary (green is for purchase actions). #}\n",
    "   navigation, so it uses bs-btn-secondary (green is for purchase actions). #}\n"
    . "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n",
    'cart_marker');
$cart = replace_one($cart,
    '<button type="button" class="bs-btn bs-btn-secondary" data-bs-mini-cart-close>До каталогу</button>',
    '<button type="button" class="bs-btn bs-btn-secondary" data-bs-mini-cart-close data-bs-mini-cart-catalog>До каталогу</button>',
    'cart_catalog_button');
$cart = replace_one($cart,
    "\$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {\n"
    . "    setMiniCartDrawerState(false);\n"
    . "});\n",
    <<<'JS'
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    setMiniCartDrawerState(false);
});
// RD-UX-QA-3B (2026-10-04): «До каталогу» opens the burger catalogue once the drawer is closed. The close above
// restores body position/top and the scroll offset synchronously; the burger opens on the next frame, so its own
// lock (body.bs-menu-lock) starts from a clean body. Focus moves to «Каталог» first, so closing the burger
// returns focus there rather than into the closed drawer.
$(document).off('click.miniCartCatalog', '[data-bs-mini-cart-catalog]').on('click.miniCartCatalog', '[data-bs-mini-cart-catalog]', function() {
    var menuButton = document.getElementById('bs-menu-open');

    if ($('body').hasClass('bs-mini-cart-open')) {
        setMiniCartDrawerState(false);
    }

    if (!menuButton) {
        return;
    }

    menuButton.focus({ preventScroll: true });
    requestAnimationFrame(function() {
        menuButton.click();
    });
});

JS
    , 'cart_catalog_handler');
$cartCounts['data-bs-mini-cart'] += 3; // the probe is a prefix of data-bs-mini-cart-catalog: button attribute + selector ×2
foreach ($cartCounts as $hook => $n) count_exact($cart, $hook, $n, 'cart_hook_kept');
count_exact($cart, 'data-bs-mini-cart-catalog', 3, 'cart_catalog_refs');

/* ---- 4 boostershop-ds.css: review N2 -------------------------------------- */
$ds = replace_one($files[DSCSS]['text'],
    "#checkout-success .bs-success-f15-k {\n  display: block;\n  margin-bottom: 2px;\n  color: var(--bs-buy);\n",
    "#checkout-success .bs-success-f15-k {\n  display: block;\n  margin-bottom: 2px;\n"
    . "  color: var(--bs-buy-hover); /* RD-UX-QA-3B (review N2): 13px bold on --bs-green-soft — #15803D clears AA, same hue */\n",
    'ds_f15_colour');
count_exact($ds, '--bs-buy-hover:', 1, 'ds_token_defined');
css_balance_gate($ds, DSCSS);

/* ---- gates ---------------------------------------------------------------- */
foreach (array(SUCCESS => $success, FAILURE => $failure, HEADER => $h, CART => $cart) as $rel => $text) {
    twig_hazard_gate(implode("\n", array_diff(explode("\n", $text), explode("\n", $files[$rel]['text']))), $rel);
}
twig_gate($root, array(
    SUCCESS => array($files[SUCCESS]['text'], $success),
    FAILURE => array($files[FAILURE]['text'], $failure),
    HEADER  => array($files[HEADER]['text'], $h),
    CART    => array($files[CART]['text'], $cart),
));

commit_files($root, $files, array(
    SUCCESS => encode_text($files[SUCCESS], $success),
    FAILURE => encode_text($files[FAILURE], $failure),
    LANG    => $langBytes,
    HEADER  => encode_text($files[HEADER], $h),
    CART    => encode_text($files[CART], $cart),
    DSCSS   => encode_text($files[DSCSS], $ds),
), array(LANG));

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/RD-UX-qa-followups_report_20261004.md');
