<?php
declare(strict_types=1);

/**
 * UX-003 / UX-005 — header and burger menu
 * =============================================================================
 * Chain     : runner 3 of 7. Requires runners 1–2 (RD-14, RD-15) applied first.
 * Handoff   : handoffs/handoff_UX-003-005_header-burger_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - макети.html» (Шапка, Бургер), «… - етап 3.html» (Бренд у бургері, A)
 * Author    : Claude Code · 2026-10-04
 * Risk      : every page, including checkout (visual only — no form markup, no checkout JS touched).
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003-005_header-burger_20261004.php
 *
 * WHAT CHANGES
 *   1 Sticky header. `.bs-header` (ds.css R-05 base rule) gets position:sticky; top:0;
 *     z-index:var(--bs-z-overlay) (300 — Claude review: at --bs-z-sticky 200 the product-page buy bar,
 *     also 200 and later in the DOM, would paint over the mini-cart drawer that renders inside the header's
 *     stacking context). Shadow --bs-sh-sm via `.is-scrolled`, toggled by a passive scroll listener.
 *     ROOT CAUSE that would have defeated sticky on ≤768: R07MOB5 set overflow-x:hidden on html AND body
 *     AND #container. With html non-visible, body no longer propagates its overflow to the viewport and
 *     becomes a scroll container; #container (the header's parent) was one too. Fixed at that source rule:
 *     html leaves the pair (body alone still clamps horizontal overflow — it now propagates to the
 *     viewport), #container moves to `overflow-x: clip` (with `hidden` first as the fallback for browsers
 *     without clip). main / #common-home / #content keep `hidden` (not header ancestors).
 *     Header height → --bs-header-sticky-h (JS, ResizeObserver; CSS fallback 69px / 57px ≤768). Used by the
 *     sticky elements that would slide under the header: .bs-co-aside (checkout summary, was top:16px),
 *     .bs-cp-toc and .bs-cp-page .bs-cp-toc (content pages, were top:24px); content-page anchors and the
 *     product review tabs (.nav-tabs, scrollIntoView target) get scroll-margin-top. The outranked
 *     `.bs-cp-toc { top: 24px }` in content-pages.css is left as is (ds.css .bs-cp-page .bs-cp-toc wins).
 *   2 «Каталог» on the burger at ≥1024 (700, 14.5px, padding 0 14px 0 12px, height 44). Below 1024 the label
 *     is visually hidden, so the button's accessible name is «Каталог» everywhere (the aria-label «Меню» is
 *     dropped — a visible label that differs from the accessible name fails WCAG 2.5.3). Behaviour unchanged.
 *   3 Placeholder «Пошук» at ≤768 (JS, follows resize); desktop keeps the current text.
 *   4 Native search × hidden (::-webkit-search-cancel-button, ::-ms-clear); [data-bs-search-clear] gets a
 *     44×44 touch area with the visible 24px circle drawn inside it.
 *   5 Burger: «Фігурки та декор» last under Pokémon TCG (/catalog/Pokemon/figurky-ta-dekor-pokemon) and One
 *     Piece Card Game (/catalog/One-Piece/figurky-ta-dekor-one-piece), class bs-menu__sub, root-relative.
 *   6 Burger brand: the header logo image ({{ logo }}, same ?v=), 110px wide, link to home; close unchanged.
 *   Cache-bust: boostershop-ds.css and patch-mobile-search-menu-redesign.js tokens (convention 8).
 *   Hooks kept: #ps-live-search-input, #ps-live-search, .ps-live-search-container, data-live-search-target,
 *   #bs-menu-open, #bs-menu, [data-bs-menu-close], [data-bs-accordion], .bs-menu__subs, #bs-msearch,
 *   [data-bs-search-close], [data-bs-search-clear], #cart, #alert (asserted).
 *
 * UI/CSS DISCIPLINE
 *   Edited at the source rules: .bs-header (R-05), R07MOB5 overflow block, .bs-cp-toc ×2, .bs-co-aside,
 *   .bs-burger, .bs-menu__brand, .bs-msearch__clear, .bs-msearch__input. Override history checked:
 *   RD-01-02-03 hotfix `body.bs .bs-header` (!important background/border/padding only — no position, so
 *   no conflict), RD-04f mobile-search pointer-events (untouched), CAT-004 sticky-atc layer (z 200, root).
 *   !important added only on #container's overflow-x: the declaration it replaces in R07MOB5 is
 *   !important and must keep beating stylesheet.css's #container rules. position:sticky is the feature.
 *   No setTimeout added. Magic numbers: 69/57 are measured header heights (fallback only); 44 = touch size.
 *
 * KNOWN, NOT CHANGED
 *   While the mini-cart drawer or the burger is open, the site locks scrolling (body position:fixed /
 *   overflow:hidden). With the mini-cart lock the page — header included — is shifted up under the scrim
 *   until the drawer closes. Nothing is covered; noted for QA.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on all three targets (RD-15 output for ds.css/header.twig) → marker → anchors → Twig parse
 *   gate on header.twig → hazard scan → CSS balance gate → backup → write → restore-all on failure.
 *   Idempotent: marker «UX-003-HEADER» in boostershop-ds.css, header.twig and the JS. Self-deletes.
 *   ROLLBACK: copy the three files back from the backup folder, refresh the theme cache, Ctrl+F5 — only while
 *   runners 4–7 are not applied.
 *   TRIGGER: an overlay covered (burger, mobile search, mini-cart, toast), burger or search won't open, the
 *   header covers checkout fields or buttons, JS error from patch-mobile-search-menu-redesign.js.
 * =============================================================================
 */

const PATCH_ID = 'UX-003-005_header-burger_20261004';
const MARKER   = 'UX-003-HEADER';
const TOKEN    = 'ux003hdr-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';
const MENUJS   = 'catalog/view/javascript/patch-mobile-search-menu-redesign.js';

const EXPECTED_SHA = array(
    DSCSS  => '5ba5157c98dde2f9d472aa71740b5366154a91778666a285990e67887f732d2b',
    HEADER => '9c2569524212001498dc7cf72d1283402cd155db4da84d7e18e495c5c6dd5e3d',
    MENUJS => 'feeabb5033bf5a893737058936e61019ef103c6251be990a1a401de552ee9b2a',
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
foreach (array(DSCSS, HEADER, MENUJS) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS, HEADER, MENUJS), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- header.twig ---------------------------------------------------------- */
$h = $files[HEADER]['text'];
$hooks = array('id="ps-live-search-input"', 'id="ps-live-search"', 'ps-live-search-container', 'data-live-search-target="ps-live-search"',
    'id="bs-menu-open"', 'id="bs-menu"', 'data-bs-menu-close', 'data-bs-accordion', 'class="bs-menu__subs"', 'id="bs-msearch"',
    'data-bs-search-close', 'data-bs-search-clear', 'id="cart"', 'id="alert"');
$hookCounts = array();
foreach ($hooks as $hook) $hookCounts[$hook] = substr_count($h, $hook);

$h = replace_one($h,
    "    <button type=\"button\" class=\"bs-burger\" id=\"bs-menu-open\" aria-label=\"Меню\" aria-controls=\"bs-menu\" aria-expanded=\"false\">\n"
    . "      <svg width=\"20\" height=\"20\" viewBox=\"0 0 18 18\" fill=\"none\" aria-hidden=\"true\">\n"
    . "        <path d=\"M2 4.5h14M2 9h14M2 13.5h14\" stroke=\"currentColor\" stroke-width=\"1.7\" stroke-linecap=\"round\"/>\n"
    . "      </svg>\n"
    . "    </button>\n",
    "    {# UX-003-HEADER (2026-10-04): visible «Каталог» from 1024px, visually hidden below — it is the button's name. #}\n"
    . "    <button type=\"button\" class=\"bs-burger\" id=\"bs-menu-open\" aria-controls=\"bs-menu\" aria-expanded=\"false\">\n"
    . "      <svg width=\"20\" height=\"20\" viewBox=\"0 0 18 18\" fill=\"none\" aria-hidden=\"true\">\n"
    . "        <path d=\"M2 4.5h14M2 9h14M2 13.5h14\" stroke=\"currentColor\" stroke-width=\"1.7\" stroke-linecap=\"round\"/>\n"
    . "      </svg>\n"
    . "      <span class=\"bs-burger__label\">Каталог</span>\n"
    . "    </button>\n",
    'hdr_burger_button');
$h = replace_one($h,
    "        <a href=\"{{ home }}\" class=\"bs-menu__brand\">\n"
    . "          Booster&nbsp;Shop\n"
    . "          <svg aria-hidden=\"true\" width=\"12\" height=\"18\" viewBox=\"0 0 12 18\" fill=\"none\">\n"
    . "            <polygon points=\"7.2,0 12,0 4.8,9 10.8,9 1.8,18 6.6,9.9 0,9.9\" fill=\"var(--bs-blue)\"/>\n"
    . "          </svg>\n"
    . "        </a>\n",
    "        {# UX-003-HEADER / UX-005 (2026-10-04): brand = the header logo image (stage 3, variant A). #}\n"
    . "        <a href=\"{{ home }}\" class=\"bs-menu__brand\" aria-label=\"Booster Shop — на головну\">\n"
    . "          {% if logo %}\n"
    . "            <img src=\"{{ logo }}?v=tech013-wp2-20260806\" alt=\"{{ name }}\" width=\"110\" height=\"34\" decoding=\"async\"/>\n"
    . "          {% else %}\n"
    . "            <span>{{ name }}</span>\n"
    . "          {% endif %}\n"
    . "        </a>\n",
    'hdr_menu_brand');
$h = replace_one($h,
    "          <a href=\"/catalog/pokemon-tcg-nabory\" class=\"bs-menu__sub\">Набори</a>\n",
    "          <a href=\"/catalog/pokemon-tcg-nabory\" class=\"bs-menu__sub\">Набори</a>\n"
    . "          <a href=\"/catalog/Pokemon/figurky-ta-dekor-pokemon\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    'hdr_pokemon_figures');
$h = replace_one($h,
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n",
    "          <a href=\"/catalog/One-Piece-Boosters\" class=\"bs-menu__sub\">Бустери One Piece</a>\n"
    . "          <a href=\"/catalog/One-Piece/figurky-ta-dekor-one-piece\" class=\"bs-menu__sub\">Фігурки та декор</a>\n",
    'hdr_onepiece_figures');
$h = bust_token($h, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
$h = bust_token($h, 'catalog/view/javascript/patch-mobile-search-menu-redesign.js', TOKEN, 'menu_js');
foreach ($hookCounts as $hook => $n) count_exact($h, $hook, $n, 'hdr_hook_kept');
foreach (array('UX-003-HEADER (2026-10-04): visible', 'UX-003-HEADER / UX-005') as $probe) count_exact($h, $probe, 1, 'hdr_comment');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $h), explode("\n", $files[HEADER]['text']))), HEADER);

/* ---- patch-mobile-search-menu-redesign.js --------------------------------- */
$js = $files[MENUJS]['text'];
$js = replace_one($js,
    "  /* ================================================================\n     A — MOBILE SEARCH EXPAND CONTROLLER\n",
    <<<'JS'
  /* ================================================================
     C — STICKY HEADER (UX-003-HEADER, 2026-10-04)
     Shadow after the page scrolls; the header height for sticky offsets
     (--bs-header-sticky-h, read by boostershop-ds.css).
     ================================================================ */
  var stickyHeader = document.querySelector('.bs-header');

  if (stickyHeader) {
    var shadowQueued = false;

    function syncHeaderShadow() {
      shadowQueued = false;
      var y = window.pageYOffset || document.documentElement.scrollTop || 0;
      stickyHeader.classList.toggle('is-scrolled', y > 0);
    }

    function syncHeaderHeight() {
      document.documentElement.style.setProperty('--bs-header-sticky-h', stickyHeader.offsetHeight + 'px');
    }

    window.addEventListener('scroll', function () {
      if (shadowQueued) return;
      shadowQueued = true;
      requestAnimationFrame(syncHeaderShadow);
    }, { passive: true });
    window.addEventListener('resize', syncHeaderHeight);
    if (window.ResizeObserver) new ResizeObserver(syncHeaderHeight).observe(stickyHeader);
    syncHeaderShadow();
    syncHeaderHeight();
  }


  /* ================================================================
     A — MOBILE SEARCH EXPAND CONTROLLER

JS
    , 'js_sticky_section');
$js = replace_one($js,
    "  // Expand на фокус (мобілі тільки)\n  if (psInput) {\n",
    <<<'JS'
  // UX-003-HEADER: short placeholder at ≤768 (the 15-character one is cut off there); desktop keeps it.
  if (psInput && window.matchMedia) {
    var fullPlaceholder = psInput.getAttribute('placeholder') || '';
    var narrowSearch = window.matchMedia('(max-width: 768px)');
    var syncPlaceholder = function () {
      psInput.setAttribute('placeholder', narrowSearch.matches ? 'Пошук' : fullPlaceholder);
    };
    if (narrowSearch.addEventListener) narrowSearch.addEventListener('change', syncPlaceholder);
    else if (narrowSearch.addListener) narrowSearch.addListener(syncPlaceholder);
    syncPlaceholder();
  }

  // Expand на фокус (мобілі тільки)
  if (psInput) {

JS
    , 'js_placeholder');

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];
$ds = replace_one($ds,
    "/* -- Header (Phase 5.1 / R-05) ----------------------------------------- */\n.bs-header {\n  background: #fff;\n  border-bottom: 1px solid var(--bs-line);\n  padding: 14px 32px;\n}\n",
    <<<'CSS'
/* -- Header (Phase 5.1 / R-05) ----------------------------------------- */
/* UX-003-HEADER (2026-10-04): sticky on every page, checkout included. z-index 300 (--bs-z-overlay), not
   --bs-z-sticky: sticky makes the header a stacking context, and the mini-cart drawer renders inside it —
   at 200 the product-page buy bar (.bs-sticky-atc, also 200, later in the DOM) would paint over the drawer.
   --bs-header-sticky-h is measured by patch-mobile-search-menu-redesign.js; these are its fallbacks. */
:root {
  --bs-header-sticky-h: 69px;
}
.bs-header {
  position: sticky;
  top: 0;
  z-index: var(--bs-z-overlay);
  background: #fff;
  border-bottom: 1px solid var(--bs-line);
  padding: 14px 32px;
  transition: box-shadow 0.2s;
}
.bs-header.is-scrolled {
  box-shadow: var(--bs-sh-sm);
}
/* Anchor targets land below the sticky header: content-page TOC links, product review tabs (scrollIntoView). */
.bs-cp-page .bs-cp-main [id],
.nav-tabs {
  scroll-margin-top: calc(var(--bs-header-sticky-h, 69px) + 16px);
}
@media (max-width: 768px) {
  :root {
    --bs-header-sticky-h: 57px;
  }
}

CSS
    , 'ds_header_base');
$ds = replace_one($ds,
    "@media (max-width: 768px) {\n  html,\n  body {\n    max-width: 100% !important;\n    overflow-x: hidden !important;\n  }\n\n"
    . "  #container,\n  main,\n  #common-home,\n  #content {\n    max-width: 100vw !important;\n    overflow-x: hidden !important;\n  }\n",
    <<<'CSS'
@media (max-width: 768px) {
  html,
  body {
    max-width: 100% !important;
  }

  /* UX-003-HEADER: overflow-x on body only. With html non-visible too, body stopped propagating its overflow
     to the viewport and became a scroll container, so the sticky header inside it never stuck. Body alone
     still clamps horizontal overflow (it now propagates to the viewport). */
  body {
    overflow-x: hidden !important;
  }

  main,
  #common-home,
  #content {
    max-width: 100vw !important;
    overflow-x: hidden !important;
  }

  /* UX-003-HEADER: #container is the sticky header's parent. `clip` clamps the same overflow without making
     it a scroll container; `hidden` stays first as the fallback where `clip` is unsupported. */
  #container {
    max-width: 100vw !important;
    overflow-x: hidden !important;
    overflow-x: clip !important;
  }

CSS
    , 'ds_r07mob5_overflow');
$ds = replace_one($ds,
    ".bs-cp-toc {\n  flex: 0 0 220px;\n  width: 220px;\n  position: sticky;\n  top: 24px;\n",
    ".bs-cp-toc {\n  flex: 0 0 220px;\n  width: 220px;\n  position: sticky;\n  top: calc(var(--bs-header-sticky-h, 69px) + 24px); /* UX-003-HEADER: below the sticky header */\n",
    'ds_cp_toc');
$ds = replace_one($ds,
    ".bs-cp-page .bs-cp-toc {\n  position: sticky;\n  top: 24px;\n",
    ".bs-cp-page .bs-cp-toc {\n  position: sticky;\n  top: calc(var(--bs-header-sticky-h, 69px) + 24px); /* UX-003-HEADER: below the sticky header */\n",
    'ds_cp_page_toc');
$ds = replace_one($ds,
    ".bs-co-aside {\n  position: sticky;\n  top: 16px;\n}\n",
    ".bs-co-aside {\n  position: sticky;\n  top: calc(var(--bs-header-sticky-h, 69px) + 16px); /* UX-003-HEADER: below the sticky header */\n}\n",
    'ds_co_aside');
$ds = replace_one($ds,
    ".bs-burger {\n  width: 42px; height: 42px; flex: 0 0 auto;\n  display: inline-flex; align-items: center; justify-content: center;\n",
    ".bs-burger {\n  width: 42px; height: 42px; flex: 0 0 auto;\n  display: inline-flex; align-items: center; justify-content: center; gap: 8px;\n",
    'ds_burger');
$ds = replace_one($ds,
    ".bs-burger:hover { background: var(--bs-bg); }\n",
    <<<'CSS'
.bs-burger:hover { background: var(--bs-bg); }
/* UX-003-HEADER: «Каталог» shows from 1024px; below it is visually hidden and still names the button. */
.bs-burger__label { font-size: 14.5px; font-weight: 700; line-height: 1; color: var(--bs-ink); white-space: nowrap; }
@media (max-width: 1023.98px) {
  .bs-burger__label {
    position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden;
    clip: rect(0 0 0 0); clip-path: inset(50%); border: 0;
  }
}
@media (min-width: 1024px) {
  .bs-burger { width: auto; height: 44px; padding: 0 14px 0 12px; }
}

CSS
    , 'ds_burger_label');
$ds = replace_one($ds,
    ".bs-menu__brand {\n  display: inline-flex; align-items: center; gap: 7px;\n  font-size: 18px; font-weight: 900; letter-spacing: -0.035em;\n  color: var(--bs-pokemon); text-transform: uppercase; line-height: 1; text-decoration: none;\n}\n",
    ".bs-menu__brand {\n  display: inline-flex; align-items: center; line-height: 0; text-decoration: none;\n"
    . "  font-size: 18px; font-weight: 800; color: var(--bs-ink); /* UX-003-HEADER: text only when no logo is set */\n}\n"
    . ".bs-menu__brand img { display: block; width: 110px; height: auto; aspect-ratio: 270 / 84; } /* same file as the header logo */\n",
    'ds_menu_brand');
$ds = replace_one($ds,
    ".bs-msearch__input::placeholder { color: var(--bs-ink-4); }\n",
    ".bs-msearch__input::placeholder { color: var(--bs-ink-4); }\n"
    . "/* UX-003-HEADER: type=\"search\" draws its own ×; ours is [data-bs-search-clear]. */\n"
    . "#ps-live-search-input::-webkit-search-cancel-button { -webkit-appearance: none; appearance: none; display: none; }\n"
    . "#ps-live-search-input::-ms-clear { display: none; width: 0; height: 0; }\n",
    'ds_cancel_button');
$ds = replace_one($ds,
    ".bs-msearch__clear {\n  width: 24px; height: 24px; border: 0; border-radius: 50%;\n  background: var(--bs-line-2); color: var(--bs-ink-3); font-size: 11px;\n  cursor: pointer; flex: 0 0 auto;\n  display: inline-flex; align-items: center; justify-content: center;\n}\n",
    "/* UX-003-HEADER: 44×44 touch area, the visible 24px circle is drawn inside it. The negative margins let the\n"
    . "   44px box overhang the 40–42px field and its 12px right padding instead of growing the field. */\n"
    . ".bs-msearch__clear {\n  width: 44px; height: 44px; margin: -2px -12px -2px -10px; border: 0; border-radius: 50%;\n"
    . "  background: radial-gradient(circle, var(--bs-line-2) 0 11.5px, transparent 12px); color: var(--bs-ink-3); font-size: 11px;\n"
    . "  cursor: pointer; flex: 0 0 auto;\n  display: inline-flex; align-items: center; justify-content: center;\n}\n",
    'ds_clear_button');
css_balance_gate($ds, DSCSS);

twig_gate($root, array(HEADER => array($files[HEADER]['text'], $h)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS  => encode_text($files[DSCSS], $ds),
    HEADER => encode_text($files[HEADER], $h),
    MENUJS => encode_text($files[MENUJS], $js),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the UX-003/005 checks in diagnostics/UX-003-005_header-burger_report_20261004.md and steps 1–4 of bs-checkout-smoke');
