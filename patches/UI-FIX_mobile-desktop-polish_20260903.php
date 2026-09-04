<?php
declare(strict_types=1);

/**
 * UI-FIX — mobile/desktop polish batch. Templates, CSS and one new image asset.
 * No database writes. Handoff: handoffs/handoff_UI-FIX_codex-handoff_20260902.md
 *
 * Tasks in this patch
 * -------------------
 * T1  guarantee/content pages — vertical rhythm around <h2> on mobile.
 *     Root cause: the `PAY-001-INFO-1-20260726` block at the end of
 *     content-pages.css re-declares `.bs-cp-main h2 { margin:44px 0 18px;
 *     font-size:22px }` AFTER the `@media (max-width:768px)` rule that sets
 *     28px/20px, so the mobile values have been dead since 2026-07-26.
 *     Fix at the source: the desktop rhythm moves into the base rule and the
 *     PAY-001 block keeps only what it is for (the h2 icon flex row).
 *     No !important, no new override.
 *
 * T2  account/login — блоки міняються місцями (Увійти вище), новий текст
 *     реєстрації. Nothing touching the form, its action, or login/register
 *     tokens is modified (ACC-003 is a known live defect on this page).
 *
 * T3b product page — real OLX brand mark instead of the hand-drawn orange
 *     "OLX" SVG. Source asset: `Logo OLX.png` (repo root, owner-supplied
 *     2026-09-02, 1024x605). Cropped to the wordmark and padded to 1:1 on the
 *     logo's own mint background, exported 120x120 PNG (2 KB) and embedded
 *     below, so the patch needs no image library on the server.
 *     The icon tile's previous `#fff3e0` orange tint goes with it — an orange
 *     tint behind a teal mark would read as a bug.
 *
 * T4  product page — "Відгуки про нас →" no longer leaves the site. It now
 *     activates the on-page "Відгуки" tab and scrolls to the tab row, so the
 *     customer picks Telegram or OLX there.
 *
 * T6  breadcrumb current chip — long product names spilled past the pill.
 *     Root cause: `.bs-crumb__current` is `display:inline-flex`, and
 *     `text-overflow:ellipsis` has no effect on a flex container; the
 *     max-width and overflow:hidden already present were doing nothing
 *     visible. Verified on production 2026-09-03 at 375px: computed
 *     max-width 232.5px, text still overflowing. Fix: inline-block.
 *     No prior patch in patches/ touches this selector (checked per UI/CSS
 *     discipline #2).
 *
 * T7  credit modal actions (Component A) — buttons side by side at every
 *     width, min-height 48px, 12px between them, 16px above.
 *     Presentation only: no text, order, handler or provider logic touched.
 *     Colours are deliberately left exactly as they are on production —
 *     see the diagnostic note about the spec's "colors unchanged" line.
 *
 * T9  category subcategory navigation, mobile (Component C · direction C3).
 *     The count-keyed selectors (`:has(> :nth-child(4):last-child)` and
 *     `--count-1..6`) are removed and replaced by one count-agnostic
 *     2-column grid with an odd-last-child full-width rule. Desktop
 *     (`.bs-segmented`) is untouched; the whole component is display:none
 *     above 991.98px.
 *
 * T10 Component D — preorder delivery ETA on the product page. Constant text
 *     "орієнтовно 3–4 тижні", rendered only for preorder status, product page
 *     only, nothing on product cards. Per the owner's simplified v2 spec
 *     (`D - термін доставки передзамовлення v2 ФІНАЛ.html`) this needs no new
 *     table, no admin field and no DB write.
 *
 * FAQ CSS for the homepage accordion also ships here; its markup lives in the
 * "Головна SEO" HTML module and is installed by
 * patches/UI-FIX_cms-content_20260903.php.
 *
 * Rollback: restore the files from
 * _patch_backups/UI-FIX_mobile-desktop-polish_20260903-<timestamp>/ and delete
 * image/catalog/reviews/olx-review-icon-120.png.
 *
 * Usage (from ~/public_html):
 *   php UI-FIX_mobile-desktop-polish_20260903.php --dry-run
 *   php UI-FIX_mobile-desktop-polish_20260903.php
 */

const PATCH_ID = 'UI-FIX_mobile-desktop-polish_20260903';
const MARKER = 'UI-FIX-20260903';
const ICON_RELATIVE = 'image/catalog/reviews/olx-review-icon-120.png';
const ICON_SHA256 = '014a369b38c6ef1ad6128da470a2c855e9107318aa0b15ba2ebd0fc6ec41b5bd';

const FILES = [
    'content_css' => 'catalog/view/stylesheet/content-pages.css',
    'type_css' => 'catalog/view/stylesheet/booster-typography.css',
    'ds_css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'login_twig' => 'catalog/view/template/account/login.twig',
    'product_twig' => 'catalog/view/template/product/product.twig',
    'category_twig' => 'catalog/view/template/product/category.twig',
];

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message) {
    throw new RuntimeException($message);
}

function normalize(string $text): string {
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function assert_count(string $haystack, string $needle, int $expected, string $label): void {
    $actual = substr_count($haystack, normalize($needle));
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function replace_once(string $content, string $needle, string $replacement, string $label): string {
    $needle = normalize($needle);
    assert_count($content, $needle, 1, $label);

    return str_replace($needle, normalize($replacement), $content);
}

function write_atomic(string $path, string $content, bool $crlf): void {
    if ($crlf) {
        $content = str_replace("\n", "\r\n", $content);
    }
    $temporary = $path . '.uifix.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . $path);
    }
    if (!@rename($temporary, $path)) {
        if (!copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . $path);
        }
        @unlink($temporary);
    }
}

function backup_file(string $root, string $backupDir, string $relative): void {
    $target = $backupDir . '/' . $relative;
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fail('backup_dir_create_failed=' . $directory);
    }
    if (!copy($root . '/' . $relative, $target)) {
        fail('backup_copy_failed=' . $relative);
    }
    out('backup=' . $relative);
}

function restore_all(string $root, string $backupDir): void {
    foreach (FILES as $relative) {
        $backup = $backupDir . '/' . $relative;
        if (is_file($backup)) {
            @copy($backup, $root . '/' . $relative);
        }
    }
}

function restore_files_and_fail(string $root, string $backupDir, Throwable $error) {
    restore_all($root, $backupDir);
    fwrite(STDERR, 'ERROR=' . $error->getMessage() . ' (files restored from ' . $backupDir . ')' . PHP_EOL);
    exit(1);
}

function self_lint(): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail('php_lint_failed=' . implode(' | ', $output));
    }
    out('php_lint=ok');
}

function olx_icon_bytes(): string {
    $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAHgAAAB4CAIAAAC2BqGFAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAHhklEQVR42u3de1BUVRwH8HPuY3fZB/tygVUggkJH'
        . 'LaUmswwLSyclQ6ykxLJGnfKfZqzUmdLKdGqcLHsYOvbQXv5hhdH4SO0BljGapqmTJpbSKiC4uzz2fR+nP8BHQrBn994V8Pf9E+7ee/nsvb97z+/cXXBO'
        . 'XQ2CqB8GCAAaoCEADdAADQFogIYANEADNASgARoC0AAN0BCABmgIQAM0QEMAGqAhAA3QAA0BaICGADRAAzQEoAEaQhOuj+439ge8m7YSQrpbhmWTS4oY'
        . 'lgXoOM5Ef9Czobx7aEbDmx68D/UOaCgdAA01OoYQScaRCJZkwmBZo2FYFmGAVoaWSK664IHDoaM1kVMu4ZxbDoeJLGPMMDzPWs18Zrp2cI5u5DBtbg7u'
        . 'HWW0j0ETXyDwXVXr9spgravzxYogSZYksT4Urj/r27Mff4I4h91YMMY06R4+1QHQ0SUcadu0zVu+WWjzRfuuICQ0ub0bv2nZtM04fqx1xoOc1QLQ3UU4'
        . 'fLTxrbWhuobYXi4LQuvW7/27qm2zZ5gm3IUxBujOSMS3saLxsy+JJMW5JskXaHprbfDgEcfTc5gkHUBfElFqfud9984qBffJV/mL2NCY9vJ81pwM99Ht'
        . 'R6DsfWO1ssrtCR07Uf/Cq1LUtb6fQ7d9uMFTuVulPQv/Vduw7E1ZEK720hGprG76equqOxc6dNT90QbHkzMVWRsmpOml1wVf57MEG8aPTZ54dxcvEUXP'
        . 'qnWhf1ydf6W7Jc/6SLH60Oc8DWUfdd/TUSRtFdv1o24y5N2gADRC4eN/hVpaO/8q+OcJIsvmwvH/WV4Q3ctXeXfv6boUpDsTUTqaP/hcSEgBJYS4y9ap'
        . 'XUCILLvL1rVs2RmlcoJqNKk56dlVnbDqJpyub/32R9XfUZlcsFZcOcbS0fzFNwkoGpempXyLeeI4zHHqW6/HoiQePqascizQjNvbXL0vwZdssaExsP93'
        . 'w603q39cy01rPu4Vt3eBn/bIoki9GYZJuibDmHejITeH1WhiuSr+uBv15VAf0YG9B+iu9SxrnjzBPLWQc9g7fuQPBHZUndvwleDzU9zqHTgiCwLD81fF'
        . 'gIWNCL7jJyiUNZrUF5+1P/nYRWWEkEGvL56YsXKpzjGAoqHS2hY55YqrLCCsy8pUCk5LuSo6aKn+rOgPRL+8fc4Mw6i8rt+DdOfARfOin6ImCIX/ro0L'
        . 'GiP74nmmoYPjV7aUFFmKJ6kILdSdjf5ug093djncumidm22783aKS2KsPdhLT6aUVxbEaW0pKbLPLEGUjVw6aOJtjn5hY/5ozPawfuOdt1FAe5oVOOfj'
        . 's7ZMi0WZGloOhiiusxmDej7qMwZhdbauhrVlWpH98YdRTJMSfetxA8VGSVijoW12Y4w5hy3myXs6aKpZD9F1psdlIq46QrH1JIUG9aJ7+bvN1b9Sd11W'
        . 'f9yyeUdCoGlmTn0/7yGy3P0yfpqeCWdTYN4WC4Jn+bve3XtjGze6V6+/tPekFjTnTI3+1BFcda3f/tBdza056amiGO9xA1PjVm7vFu2NeQ0dvafNO9WF'
        . 'Zp2pnF4f/fLutZ/69h3sWvlMQ/2ylbIY7XwuRkibnRWXMkKeFWXxKF+0Xr2+9bsqFaElLW/MzaHYp3CkcckK9/ufiW7vhVpMgiF/xfbT8xaHGpsodjTZ'
        . 'qMnKiAuakMDvfyhzUZbl4CG6VVH3OvSjbvIePEyxT6LUXL6lpWKbNjODtVuRzx86WSuFI7Tb1Y0crkSjI6Hd3bhu7/T5tzL0fWEiyaGTtf59B/3HamJQ'
        . 'RgiZCu5AfTnU0PIAq1X9vvDl512aQ3/ziMRsyzq10DRs8JWHRgiZp92f4Ee2zMWFmE/EE8aWkim22aUpSxaYhg258tA4N9uWPzphyvygtOSJ4xKjbJ85'
        . 'DWGMDPqUJfOVtY5xCG6ZXcobDQn44zHGA+Y+noB+v6WkqEP5Qj9kyQJFeqox3nV0xGFPm/vE6RXvqT1La5o8QcHqrLs+m+niAZrzT8NcVg8NSSlLF7Jv'
        . 'rom4PZ1fonGmJgQaIU3BGMfxvxsrVHxYSTd8yIBZpUqtTcbYvnQh5T1Wkn3RvCtZOtpHWslzptvG3q6SsvbazLTFz2BNX50kVA4aIcKytufm2sflK38s'
        . '52Y7X32eTTah/pJ4+9GE56zPPpU24yHMKNbaNo4d7XxtEWsxo34UBW5OCcMYSqdmDh/S+PbaYP3ZeFbFGvS2WaXJ9xYg+GjF/65oxNCBZct95Vs9m7ZQ'
        . 'PbDRcWbxnGlcvuXRhzi7FfXHKDrc0mmN04uNkyf4d1S27qgMus70ePOHEeLsVmPBGNOk8bwzBfXfqDCuNRkMDxQapk6Sas8EfzsUPlYTPuUSz3mkSITI'
        . 'MsKY5XnWYuYzB2lzc5LyhmsGX4c5+EBnHEM6NivdmJVubK/jooTD4faPKBOtBnNc/6vCVwj6MnaORZyenC8XV2Hg2w0AGkpHbwjRJ9lLphDUw1f99J7v'
        . 'TcDwX5ShdAA0BKABGqAhAA3QEIAGaICGADRAQwAaoAEaAtAADQFogAZoCEADNASgARqgIQAN0BCABmiAhgA0QEMAuhfmX+kvtKAwbn0uAAAAAElFTkSu'
        . 'QmCC';
    $bytes = base64_decode($base64, true);
    if ($bytes === false || hash('sha256', $bytes) !== ICON_SHA256) {
        fail('icon_payload_integrity_failed');
    }

    return $bytes;
}

$arguments = array_slice($argv ?? [], 1);
if ($arguments !== [] && $arguments !== ['--dry-run']) {
    fail('usage:php ' . basename(__FILE__) . ' [--dry-run]');
}
$dryRun = $arguments === ['--dry-run'];

self_lint();

$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
if (!is_file($root . '/config.php')) {
    fail('run_from_public_html_required');
}

$original = [];
$crlf = [];
foreach (FILES as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('missing_file=' . $relative);
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        fail('read_failed=' . $relative);
    }
    $crlf[$key] = str_contains($raw, "\r\n");
    $original[$key] = normalize($raw);
}

$iconPath = $root . '/' . ICON_RELATIVE;
$alreadyApplied = substr_count($original['ds_css'], MARKER) > 0
    && substr_count($original['product_twig'], MARKER) > 0
    && is_file($iconPath);

if ($alreadyApplied && !$dryRun) {
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}
if ($alreadyApplied) {
    out('already_applied=yes');
    out('dry_run=ok');
    exit(0);
}

$content = $original;

/* ------------------------------------------------------------------ T1 --- */

$content['content_css'] = replace_once(
    $content['content_css'],
    ".bs-cp-main h2 {\n  margin: 32px 0 14px;\n  padding-bottom: 10px;",
    ".bs-cp-main h2 {\n  /* " . MARKER . " T1: the desktop rhythm lives here now. It used to be\n"
    . "     re-declared by the PAY-001-INFO-1 block at the end of this file, which\n"
    . "     sits after the mobile media query and silently killed it. */\n"
    . "  margin: 44px 0 18px;\n  padding-bottom: 10px;",
    't1_base_h2'
);

$content['content_css'] = replace_once(
    $content['content_css'],
    '.bs-cp-main h2{display:flex;align-items:center;gap:12px;margin:44px 0 18px;font-size:22px}',
    '/* ' . MARKER . ' T1: margin and font-size removed — they belong to the base rule'
    . " and to the mobile media query above. */\n"
    . '.bs-cp-main h2{display:flex;align-items:center;gap:12px}',
    't1_pay001_h2'
);

/* --------------------------------------------------- homepage FAQ CSS --- */

$content['type_css'] = replace_once(
    $content['type_css'],
    ".home-seo-panel p:last-child {\n  color: #6b7280;\n  margin-bottom: 0;\n}",
    "/* " . MARKER . ": direct children only, so the muted last line keeps its styling\n"
    . "   once the FAQ block is appended after it, and answer paragraphs do not\n"
    . "   pick it up. */\n"
    . ".home-seo-panel > p:last-of-type {\n  color: #6b7280;\n  margin-bottom: 0;\n}\n"
    . "\n"
    . "/* " . MARKER . " · homepage FAQ accordion. Markup lives in the \"Головна SEO\"\n"
    . "   HTML module and is installed by UI-FIX_cms-content_20260903.php.\n"
    . "   Native <details>, no JavaScript, so every answer is in the served HTML. */\n"
    . ".bs-home-faq {\n  margin: 20px 0 0;\n  padding-top: 4px;\n  border-top: 1px solid #e5e7eb;\n}\n"
    . ".bs-home-faq__item {\n  border-bottom: 1px solid #eef0f2;\n}\n"
    . ".bs-home-faq__item:last-child {\n  border-bottom: 0;\n}\n"
    . ".bs-home-faq__item summary {\n"
    . "  display: flex;\n  align-items: center;\n  justify-content: space-between;\n  gap: 12px;\n"
    . "  padding: 13px 2px;\n  list-style: none;\n  cursor: pointer;\n"
    . "  color: #111827;\n  font-size: 15px;\n  font-weight: 700;\n  line-height: 1.4;\n}\n"
    . ".bs-home-faq__item summary::-webkit-details-marker {\n  display: none;\n}\n"
    . ".bs-home-faq__item summary::after {\n"
    . "  content: \"\";\n  flex: 0 0 auto;\n  width: 8px;\n  height: 8px;\n  margin-right: 4px;\n"
    . "  border-right: 2px solid #6b7280;\n  border-bottom: 2px solid #6b7280;\n"
    . "  transform: translateY(-2px) rotate(45deg);\n  transition: transform 0.18s ease;\n}\n"
    . ".bs-home-faq__item[open] summary::after {\n  transform: translateY(2px) rotate(225deg);\n}\n"
    . ".bs-home-faq__item summary:focus-visible {\n"
    . "  outline: 2px solid #1e3a8a;\n  outline-offset: 3px;\n  border-radius: 4px;\n}\n"
    . ".bs-home-faq__answer {\n  padding: 0 2px 14px;\n}\n"
    . ".bs-home-faq__answer p {\n  margin: 0 0 10px;\n}\n"
    . ".bs-home-faq__answer p:last-child {\n  margin-bottom: 0;\n}\n"
    . "@media (prefers-reduced-motion: reduce) {\n"
    . "  .bs-home-faq__item summary::after {\n    transition: none;\n  }\n}\n"
    . "@media (max-width: 768px) {\n"
    . "  .bs-home-faq__item summary {\n    font-size: 14px;\n    padding: 12px 2px;\n  }\n}",
    'faq_css'
);

/* ------------------------------------------------------------------ T6 --- */

$content['ds_css'] = replace_once(
    $content['ds_css'],
    ".bs-crumb__current {\n  display: inline-flex; align-items: center;\n",
    ".bs-crumb__current {\n"
    . "  /* " . MARKER . " T6: inline-block, not flex. text-overflow:ellipsis below has\n"
    . "     no effect on a flex container, so long product names ran past the pill\n"
    . "     even though max-width and overflow:hidden were already set. */\n"
    . "  display: inline-block; vertical-align: middle;\n",
    't6_current_display'
);

$content['ds_css'] = replace_once(
    $content['ds_css'],
    ".bs-crumb__link,\n.bs-crumb__current {\n  min-height: 26px;\n  line-height: 1.2;\n}",
    ".bs-crumb__link,\n.bs-crumb__current {\n  min-height: 26px;\n}\n"
    . ".bs-crumb__link { line-height: 1.2; }\n"
    . "/* " . MARKER . " T6: 26px box − 2px border − 8px padding = 16px content box, so\n"
    . "   the single truncated line stays centred without flex. The last crumb is\n"
    . "   also allowed to shrink, so the pill's own right edge stays on screen\n"
    . "   instead of running under the scroll container's edge — no magic width,\n"
    . "   it adapts to however long the ancestor crumbs are. */\n"
    . ".bs-crumb__current { line-height: 16px; }\n"
    . ".bs-crumb__item:last-child { flex: 0 1 auto; min-width: 0; }",
    't6_current_line_height'
);

/* ----------------------------------------------------------------- T3b --- */

$content['ds_css'] = replace_once(
    $content['ds_css'],
    '.bs-review-card--olx .bs-review-card__icon { background: #fff3e0; }',
    '/* ' . MARKER . " T3: the real OLX mark carries the brand's own mint ground, so\n"
    . "   the old orange tint goes with the old hand-drawn SVG. */\n"
    . '.bs-review-card--olx .bs-review-card__icon { background: #23E6DB; overflow: hidden; }' . "\n"
    . '.bs-review-card__icon img { display: block; width: 100%; height: 100%; object-fit: cover; }' . "\n"
    . "/* The guarantee page renders this same component inside .bs-cp-main, whose\n"
    . "   generic `a` rule (0,2,0) outranks .bs-review-card (0,1,0) and would paint\n"
    . "   the cards blue and underlined. Restore the component's own treatment\n"
    . "   there instead of weakening the content-page rule for every link. */\n"
    . '.bs-cp-main a.bs-review-card,' . "\n"
    . '.bs-cp-main a.bs-review-card:hover { color: var(--bs-ink, #111827); text-decoration: none; }',
    't3_olx_icon_css'
);

/* ------------------------------------------------------------------ T7 --- */

$content['ds_css'] = replace_once(
    $content['ds_css'],
    '.pay001-modal__actions { display: flex; gap: 10px; margin-top: 14px; }',
    '/* ' . MARKER . ' T7 (Component A): side by side at every width, 48px tall. */' . "\n"
    . '.pay001-modal__actions { display: flex; flex-direction: row; gap: 12px; margin-top: 16px; }',
    't7_actions_row'
);

$content['ds_css'] = replace_once(
    $content['ds_css'],
    '.pay001-modal__actions .bs-btn { flex: 1 1 0; min-width: 0; }',
    ".pay001-modal__actions .bs-btn {\n"
    . "  flex: 1 1 0; min-width: 0;\n"
    . "  display: inline-flex; align-items: center; justify-content: center;\n"
    . "  min-height: 48px; padding: 0 12px;\n"
    . "  white-space: normal; text-align: center; line-height: 1.25;\n}",
    't7_actions_button'
);

$content['ds_css'] = replace_once(
    $content['ds_css'],
    "  .pay001-summary strong { font-size: 12px; }\n  .pay001-modal__actions { flex-direction: column; }\n",
    "  .pay001-summary strong { font-size: 12px; }\n"
    . '  /* ' . MARKER . " T7: the mobile-only column override is gone — owner decision\n"
    . "     is one row at every width. The order rules below keep the DOM order. */\n",
    't7_mobile_column'
);

/* ----------------------------------------------------- T10 Component D --- */

$content['ds_css'] = rtrim($content['ds_css'], "\n") . "\n\n"
    . '/* ' . MARKER . " · Component D — preorder delivery ETA. Product page only;\n"
    . "   product cards never render it. */\n"
    . ".bs-pp-eta__val { color: var(--bs-ink-2); font-weight: 700; font-size: 12.5px; line-height: 1.35; }\n"
    . ".bs-pp-eta__val em { font-style: normal; font-weight: 500; color: var(--bs-ink-3); }\n"
    . '/* /' . MARKER . " */\n";

/* ------------------------------------------------------------------ T2 --- */

$content['login_twig'] = replace_once(
    $content['login_twig'],
    <<<'TWIG'
        <div class="col mb-3">
          <div class="border rounded p-3 d-flex flex-column h-100">
            <h2>{{ text_new_customer }}</h2>
            <p><strong>{{ text_register }}</strong></p>
            <p>{{ text_register_account }}</p>
            <div class="text-end">
              <a href="{{ register }}" class="btn btn-primary">{{ button_continue }}</a>
            </div>
          </div>
        </div>
        <div class="col mb-3">
          <div class="border rounded p-3 d-flex flex-column h-100">
            <form id="form-login" action="{{ login }}" method="post" data-oc-toggle="ajax">
TWIG,
    <<<'TWIG'
        {# UI-FIX-20260903 T2: Увійти first, Реєстрація second. The login form,
           its action and the login/register tokens are untouched — ACC-003 is a
           known live defect on this page. #}
        <div class="col mb-3">
          <div class="border rounded p-3 d-flex flex-column h-100">
            <form id="form-login" action="{{ login }}" method="post" data-oc-toggle="ajax">
TWIG,
    't2_register_block'
);

$content['login_twig'] = replace_once(
    $content['login_twig'],
    <<<'TWIG'
              <div class="text-end">
                <button type="submit" class="btn btn-primary">{{ button_login }}</button>
              </div>
            </form>
          </div>
        </div>
      </div>
TWIG,
    <<<'TWIG'
              <div class="text-end">
                <button type="submit" class="btn btn-primary">{{ button_login }}</button>
              </div>
            </form>
          </div>
        </div>
        <div class="col mb-3">
          <div class="border rounded p-3 d-flex flex-column h-100">
            <h2>{{ text_register }}</h2>
            <p>Створіть акаунт, щоб купувати в один клік і не вводити дані щоразу.</p>
            <div class="text-end">
              <a href="{{ register }}" class="btn btn-primary">{{ button_continue }}</a>
            </div>
          </div>
        </div>
      </div>
TWIG,
    't2_login_block'
);

/* ------------------------------------------------------------- T4 + T10 --- */

$content['product_twig'] = replace_once(
    $content['product_twig'],
    '        <li class="nav-item"><a href="#tab-review" data-bs-toggle="tab" class="nav-link">Відгуки</a></li>',
    '        <li class="nav-item"><a href="#tab-review" id="bs-tab-review-link" data-bs-toggle="tab" class="nav-link">Відгуки</a></li>',
    't4_tab_id'
);

$content['product_twig'] = replace_once(
    $content['product_twig'],
    <<<'TWIG'
              <a href="https://www.olx.ua/uk/list/user/ubnF9/?tab=ratings" target="_blank" rel="noopener noreferrer" class="bs-pp-reviews__olx">
TWIG,
    <<<'TWIG'
              {# UI-FIX-20260903 T4: stays on the page — opens the "Відгуки" tab and
                 scrolls to it, so the customer picks Telegram or OLX there. #}
              <a href="#tab-review" class="bs-pp-reviews__olx" onclick="var n=document.getElementById('bs-tab-review-link');if(!n)return true;if(window.bootstrap&&window.bootstrap.Tab){window.bootstrap.Tab.getOrCreateInstance(n).show();}else if(window.jQuery){window.jQuery(n).tab('show');}var r=n.closest('.nav-tabs')||n;r.scrollIntoView({behavior:'smooth',block:'start'});return false;">
TWIG,
    't4_reviews_link'
);

$content['product_twig'] = replace_once(
    $content['product_twig'],
    <<<'TWIG'
                <span class="bs-pp-meta__val bs-pp-meta__stock">{{ stock }}{% if 'шт' not in _stock_lc and 'наяв' not in _stock_lc and _stock_lc != 'in stock' %} шт{% endif %}</span>
              {% endif %}
            </div>
          </div>
TWIG,
    <<<'TWIG'
                <span class="bs-pp-meta__val bs-pp-meta__stock">{{ stock }}{% if 'шт' not in _stock_lc and 'наяв' not in _stock_lc and _stock_lc != 'in stock' %} шт{% endif %}</span>
              {% endif %}
            </div>
            {# UI-FIX-20260903 Component D · preorder delivery ETA. Constant text,
               product page only, never on a product card. #}
            {% if _is_preorder %}
              <div class="bs-pp-meta__row">
                <span class="bs-pp-meta__label">Доставка</span>
                <span class="bs-pp-eta__val"><em>орієнтовно</em> 3–4 тижні</span>
              </div>
            {% endif %}
          </div>
TWIG,
    't10_delivery_row'
);

$content['product_twig'] = replace_once(
    $content['product_twig'],
    <<<'TWIG'
              <div class="bs-review-card__icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <rect width="24" height="24" rx="6" fill="#FF6B00"/>
                  <text x="4" y="17" font-family="Arial" font-size="11" font-weight="bold" fill="#fff">OLX</text>
                </svg>
              </div>
TWIG,
    <<<'TWIG'
              <div class="bs-review-card__icon">
                {# UI-FIX-20260903 T3: real OLX brand mark, from the owner's Logo OLX.png #}
                <img src="/image/catalog/reviews/olx-review-icon-120.png?v=uifix-20260903" alt="OLX" width="40" height="40" loading="lazy" decoding="async">
              </div>
TWIG,
    't3_olx_icon_markup'
);

/* ------------------------------------------------------------------ T9 --- */

$content['category_twig'] = replace_once(
    $content['category_twig'],
    <<<'CSS'
  /* Mobile M2 · Underlined text tabs */
  .bs-subcat-tabs {
    border-top: 1px solid var(--bs-line-2);
    padding: 0 8px;
    background: #fff;
  }
  .bs-subcat-tabs__row {
    display: flex;
    justify-content: center;
    gap: 2px;
    flex-wrap: wrap;
  }
  .bs-subcat-tab {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 11px 10px;
    font-size: 12.5px; font-weight: 600;
    color: var(--bs-ink-3);
    text-decoration: none;
    border-bottom: 2px solid transparent;
    line-height: 1.2;
    white-space: nowrap;
  }
  .bs-subcat-tab__count {
    font-size: 10.5px; font-weight: 600;
    color: var(--bs-ink-4);
  }
  .bs-subcat-tab.is-active {
    color: var(--bs-ink);
    border-bottom-color: var(--bs-pokemon);
  }
  .bs-subcat-tab.is-active .bs-subcat-tab__count { color: var(--bs-pokemon); }
  .bs-cat-header--onepiece .bs-subcat-tab.is-active { border-bottom-color: var(--bs-onepiece); }
  .bs-cat-header--onepiece .bs-subcat-tab.is-active .bs-subcat-tab__count { color: var(--bs-onepiece); }
CSS,
    <<<'CSS'
  /* UI-FIX-20260903 T9 (Component C · direction C3) — subcategories are the
     primary mobile navigation: a fixed 2-column card grid. Count-agnostic by
     construction, so 2, 3, 5, 7+ subcategories all hold with no CSS edit; the
     old count-keyed selectors that broke past 4 are gone. Names wrap in full
     and are never truncated. The whole component is display:none above
     991.98px, so desktop's segmented control is untouched. */
  .bs-subcat-tabs {
    border-top: 1px solid var(--bs-line-2);
    padding: 10px 12px 12px;
    background: #fff;
  }
  .bs-subcat-tabs__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
  }
  /* Odd total → the last card spans the full row. */
  .bs-subcat-tabs__row > :nth-child(odd):last-child {
    grid-column: 1 / -1;
  }
  .bs-subcat-tab {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    box-sizing: border-box;
    min-height: 40px;
    padding: 9px 12px;
    background: #fff;
    border: 1px solid var(--bs-line);
    border-radius: var(--bs-r, 10px);
    font-size: 12.5px; font-weight: 700;
    color: var(--bs-ink);
    text-decoration: none;
    line-height: 1.2;
    overflow-wrap: anywhere;
  }
  .bs-subcat-tab__count {
    flex: 0 0 auto;
    font-size: 11px; font-weight: 700;
    padding: 2px 7px;
    border-radius: var(--bs-r-pill, 999px);
    color: var(--bs-ink-3);
    background: var(--bs-line-2);
  }
  .bs-subcat-tab.is-active {
    border-color: var(--bs-ink-3);
    box-shadow: inset 0 0 0 1px var(--bs-ink-3);
  }
  .bs-cat-header--pokemon .bs-subcat-tab__count { color: var(--bs-pokemon); background: var(--bs-gold-soft); }
  .bs-cat-header--pokemon .bs-subcat-tab.is-active { border-color: var(--bs-pokemon); box-shadow: inset 0 0 0 1px var(--bs-pokemon); }
  .bs-cat-header--onepiece .bs-subcat-tab__count { color: var(--bs-onepiece); background: var(--bs-blue-soft); }
  .bs-cat-header--onepiece .bs-subcat-tab.is-active { border-color: var(--bs-onepiece); box-shadow: inset 0 0 0 1px var(--bs-onepiece); }
CSS,
    't9_card_grid'
);

$content['category_twig'] = replace_once(
    $content['category_twig'],
    '<nav class="bs-subcat-tabs__row bs-subcat-tabs__row--count-{{ sub_categories|length }}" aria-label="Підкатегорії">',
    '<nav class="bs-subcat-tabs__row" aria-label="Підкатегорії">',
    't9_drop_count_class'
);

$content['category_twig'] = replace_once(
    $content['category_twig'],
    <<<'CSS'
  /* Exactly 4 subcategories → 2x2 grid */
  .bs-subcat-tabs__row:has(> :nth-child(4):last-child) {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0;
  }
  .bs-subcat-tabs__row:has(> :nth-child(4):last-child) .bs-subcat-tab {
    display: flex; width: 100%;
  }
  /* Exactly 6 → 3x2 (defensive; 5/7+ falls back to flex wrap) */
  .bs-subcat-tabs__row:has(> :nth-child(6):last-child) {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0;
  }
  .bs-subcat-tabs__row:has(> :nth-child(6):last-child) .bs-subcat-tab {
    display: flex; width: 100%;
  }
CSS,
    <<<'CSS'
  /* UI-FIX-20260903 T9: the exact-count selectors are removed. Matching an
     exact child count is what broke this component when Pokémon gained a 4th
     subcategory; the grid above needs no count at all. */
CSS,
    't9_drop_has_rules'
);

$content['category_twig'] = replace_once(
    $content['category_twig'],
    <<<'CSS'
    .bs-subcat-tabs__row {
      width: 100%;
      align-items: stretch;
      row-gap: 0;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-1 {
      display: grid !important;
      grid-template-columns: 1fr !important;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-2 {
      display: grid !important;
      grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-3 {
      display: flex !important;
      flex-wrap: nowrap;
      justify-content: space-around;
      column-gap: 8px;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-4 {
      display: grid !important;
      grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-5,
    .bs-subcat-tabs .bs-subcat-tabs__row.bs-subcat-tabs__row--count-6 {
      display: grid !important;
      grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
    }

    .bs-subcat-tabs .bs-subcat-tabs__row--count-1 .bs-subcat-tab,
    .bs-subcat-tabs .bs-subcat-tabs__row--count-2 .bs-subcat-tab,
    .bs-subcat-tabs .bs-subcat-tabs__row--count-4 .bs-subcat-tab,
    .bs-subcat-tabs .bs-subcat-tabs__row--count-5 .bs-subcat-tab,
    .bs-subcat-tabs .bs-subcat-tabs__row--count-6 .bs-subcat-tab {
      display: flex;
      width: 100%;
    }

    .bs-subcat-tab {
      min-height: 40px;
      padding: 10px 6px;
      text-align: center;
      white-space: normal;
      overflow-wrap: anywhere;
    }
    .bs-subcat-tab.is-active { font-weight: 700; }

    .bs-subcat-tab__count {
      margin-left: 1px;
      flex: 0 0 auto;
      color: var(--bs-ink-4);
    }
CSS,
    <<<'CSS'
    .bs-subcat-tabs__row {
      width: 100%;
      align-items: stretch;
    }

    /* UI-FIX-20260903 T9: the --count-1..6 !important overrides are removed.
       They hardcoded a layout per subcategory count and had to be revisited
       every time a category gained or lost one. The base grid handles any
       count. */
CSS,
    't9_drop_count_rules'
);

$content['category_twig'] = replace_once(
    $content['category_twig'],
    <<<'CSS'
    .bs-cat-header--subcategory .bs-cat-header__title .bs-count {
      display: none;
    }
  }
CSS,
    <<<'CSS'
    .bs-cat-header--subcategory .bs-cat-header__title .bs-count {
      display: none;
    }
  }

  /* UI-FIX-20260903 T9 · C3 inversion. At phone widths the category name drops
     to a caption line above the subcategory grid, which becomes the page's
     primary navigation. The element stays a real <h1> — this is visual only,
     and the name is still in the breadcrumbs. Placed after the 991.98px block
     on purpose: it has to win at 640px and below. */
  @media (max-width: 640px) {
    .bs-cat-header__hero {
      padding: 14px 14px 4px;
    }

    .bs-cat-header__title {
      align-items: baseline;
      gap: 5px;
      margin: 0 2px 2px;
    }

    .bs-cat-header__title h1 {
      font-size: 12px;
      font-weight: 700;
      line-height: 1.3;
      color: var(--bs-ink);
    }

    .bs-cat-header__title .bs-count {
      font-size: 12px;
      font-weight: 500;
      color: var(--bs-ink-4);
    }

    .bs-cat-header__title .bs-count::before {
      content: "· ";
    }

    .bs-subcat-tabs {
      border-top: 0;
      padding-top: 0;
    }
  }
CSS,
    't9_caption'
);

/* ------------------------------------------------------------- verify --- */

foreach (FILES as $key => $relative) {
    if ($content[$key] === $original[$key]) {
        fail('no_change_produced=' . $relative);
    }
}

assert_count($content['ds_css'], MARKER, 7, 'ds_marker_final');
assert_count($content['product_twig'], MARKER, 3, 'product_marker_final');
assert_count($content['category_twig'], MARKER, 4, 'category_marker_final');
assert_count($content['content_css'], MARKER, 2, 'content_marker_final');
assert_count($content['type_css'], MARKER, 2, 'type_marker_final');
assert_count($content['login_twig'], MARKER, 1, 'login_marker_final');

assert_count($content['content_css'], 'margin:44px 0 18px', 0, 't1_no_stale_margin');
assert_count($content['category_twig'], ':nth-child(4):last-child', 0, 't9_no_has_rules');
assert_count($content['category_twig'], 'bs-subcat-tabs__row--count-', 0, 't9_no_count_rules');
assert_count($content['ds_css'], '.pay001-modal__actions { flex-direction: column; }', 0, 't7_no_column');
assert_count($content['product_twig'], 'olx.ua/uk/list/user/ubnF9/?tab=ratings', 1, 't4_olx_link_left_in_tab');
assert_count($content['login_twig'], '<div class="col mb-3">', 2, 't2_two_columns');

$icon = olx_icon_bytes();
out('icon_payload=ok:' . strlen($icon) . 'B');

if ($dryRun) {
    foreach (FILES as $key => $relative) {
        out(sprintf('plan=%s %d -> %d bytes', $relative, strlen($original[$key]), strlen($content[$key])));
    }
    out('plan=' . ICON_RELATIVE . ' new file ' . strlen($icon) . ' bytes');
    out('dry_run=ok');
    exit(0);
}

/* -------------------------------------------------------------- write --- */

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
if (file_exists($backupDir)) {
    fail('backup_path_exists');
}
if (!mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
    fail('backup_create_failed');
}

try {
    foreach (FILES as $relative) {
        backup_file($root, $backupDir, $relative);
    }

    foreach (FILES as $key => $relative) {
        write_atomic($root . '/' . $relative, $content[$key], $crlf[$key]);
        out('written=' . $relative);
    }

    $iconDirectory = dirname($iconPath);
    if (!is_dir($iconDirectory) && !mkdir($iconDirectory, 0755, true) && !is_dir($iconDirectory)) {
        fail('icon_dir_create_failed');
    }
    if (file_put_contents($iconPath, $icon, LOCK_EX) === false) {
        fail('icon_write_failed');
    }
    @chmod($iconPath, 0644);
    if (hash_file('sha256', $iconPath) !== ICON_SHA256) {
        fail('icon_verify_failed');
    }
    out('written=' . ICON_RELATIVE);

    // Twig has no lint binary here; PHP files are not touched by this patch, so
    // the syntax gate is the anchor and marker assertion set above plus a
    // re-read of every written file.
    foreach (FILES as $key => $relative) {
        $written = file_get_contents($root . '/' . $relative);
        if ($written === false || normalize($written) !== $content[$key]) {
            fail('readback_mismatch=' . $relative);
        }
    }
    out('readback=ok');
} catch (Throwable $error) {
    restore_files_and_fail($root, $backupDir, $error);
}

out('backup_dir=' . str_replace($root . '/', '', $backupDir));
out('done=ok');
@unlink(__FILE__);
