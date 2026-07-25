<?php
declare(strict_types=1);

/*
 * TOC-003 — publish the already-correct desktop TOC CSS under a fresh URL.
 *
 * Root cause verified on 2026-07-24:
 * catalog/view/stylesheet/boostershop-ds.css no longer has the two inner-scroll
 * declarations, but the page still links it with the unchanged cache key
 * ?v=pay001-ui-20260721. Browsers can keep the pre-fix stylesheet for that URL.
 *
 * DB scope: none.
 * Rollback: restore the backed-up Twig template from _patch_backups/<patch>-<ts>/.
 */

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

const PATCH_NAME = 'TOC-003_force_fresh_toc_css_20260724';
const CSS_RELATIVE_PATH = 'catalog/view/stylesheet/boostershop-ds.css';
const OLD_CSS_URL = 'catalog/view/stylesheet/boostershop-ds.css?v=pay001-ui-20260721';
const NEW_CSS_URL = 'catalog/view/stylesheet/boostershop-ds.css?v=toc003-20260724';

function out(string $key, string $value = ''): void {
    echo $key . ($value === '' ? '' : '=' . $value) . PHP_EOL;
}

function fail_patch(string $message): void {
    throw new RuntimeException($message);
}

function lint_self(): void {
    if (!function_exists('exec')) {
        fail_patch('PHP exec() is unavailable; cannot pass mandatory php -l gate');
    }

    $output = [];
    $status = 1;
    @exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $status);

    if ($status !== 0) {
        fail_patch('php -l gate failed: ' . implode(' ', $output));
    }

    out('php_l', 'ok');
}

/**
 * Return only the desktop TOC block: sticky + 220px width distinguishes it from
 * the responsive @media block, which must remain unchanged.
 *
 * @return array<int, string>
 */
function desktop_toc_blocks(string $css): array {
    $matched = preg_match_all('/\.bs-cp-page\s+\.bs-cp-toc\s*\{([^{}]*)\}/s', $css, $matches);
    if ($matched === false) {
        fail_patch('Cannot scan .bs-cp-page .bs-cp-toc CSS blocks');
    }

    $desktop = [];
    foreach ($matches[0] as $index => $block) {
        $body = $matches[1][$index];
        $isSticky = preg_match('/\bposition\s*:\s*sticky\s*;/i', $body) === 1;
        $isDesktopWidth = preg_match('/\bwidth\s*:\s*220px\s*;/i', $body) === 1;
        $hasTop = preg_match('/\btop\s*:\s*24px\s*;/i', $body) === 1;

        if ($isSticky && $isDesktopWidth && $hasTop) {
            $desktop[] = $block;
        }
    }

    return $desktop;
}

/** @return array<int, array{path:string,relative:string,count:int}> */
function find_url_occurrences(string $catalogView, string $needle): array {
    if (!is_dir($catalogView)) {
        fail_patch('Template root not found: catalog/view');
    }

    $allowedExtensions = ['twig', 'php', 'tpl'];
    $hits = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($catalogView, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $item) {
        if (!$item->isFile()) {
            continue;
        }

        $extension = strtolower((string) pathinfo($item->getFilename(), PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            continue;
        }

        $path = $item->getPathname();
        $content = file_get_contents($path);
        if (!is_string($content)) {
            fail_patch('Cannot read candidate template: ' . $path);
        }

        $count = substr_count($content, $needle);
        if ($count > 0) {
            $hits[] = [
                'path' => $path,
                'relative' => str_replace('\\', '/', substr($path, strlen(dirname($catalogView, 2)) + 1)),
                'count' => $count,
            ];
        }
    }

    return $hits;
}

function total_occurrences(array $hits): int {
    $total = 0;
    foreach ($hits as $hit) {
        $total += $hit['count'];
    }
    return $total;
}

function run(): void {
    $cwd = getcwd();
    if (!is_string($cwd) || $cwd === '') {
        fail_patch('Cannot determine cwd');
    }

    out('patch', PATCH_NAME);
    out('cwd', $cwd);
    out('time', date('c'));
    lint_self();

    $cssPath = $cwd . '/' . CSS_RELATIVE_PATH;
    if (!is_file($cssPath)) {
        fail_patch('Target CSS file not found: ' . CSS_RELATIVE_PATH);
    }

    $css = file_get_contents($cssPath);
    if (!is_string($css)) {
        fail_patch('Cannot read target CSS file');
    }

    $desktopBlocks = desktop_toc_blocks($css);
    if (count($desktopBlocks) !== 1) {
        fail_patch('Expected exactly one sticky 220px desktop TOC block; found=' . count($desktopBlocks));
    }

    if (preg_match('/\bmax-height\s*:/i', $desktopBlocks[0]) === 1 || preg_match('/\boverflow-y\s*:/i', $desktopBlocks[0]) === 1) {
        fail_patch('Desktop TOC CSS still has an inner-scroll declaration; do not bump cache before applying the CSS fix');
    }

    out('desktop_toc_css', 'verified_without_inner_scroll');

    $catalogView = $cwd . '/catalog/view';
    $oldHits = find_url_occurrences($catalogView, OLD_CSS_URL);
    $newHits = find_url_occurrences($catalogView, NEW_CSS_URL);
    $oldCount = total_occurrences($oldHits);
    $newCount = total_occurrences($newHits);

    if ($oldCount === 0 && $newCount === 1) {
        out('already_applied', 'yes');
        out('done', 'ok');
        @unlink(__FILE__);
        out('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
        return;
    }

    if ($oldCount !== 1 || $newCount !== 0 || count($oldHits) !== 1) {
        fail_patch('Expected exactly one old stylesheet URL and no new URL; old=' . $oldCount . ', new=' . $newCount);
    }

    $templatePath = $oldHits[0]['path'];
    $templateRelative = $oldHits[0]['relative'];
    $template = file_get_contents($templatePath);
    if (!is_string($template)) {
        fail_patch('Cannot read stylesheet template: ' . $templateRelative);
    }

    if (substr_count($template, OLD_CSS_URL) !== 1) {
        fail_patch('Exact old stylesheet URL anchor is not unique in: ' . $templateRelative);
    }

    $updated = str_replace(OLD_CSS_URL, NEW_CSS_URL, $template, $replacementCount);
    if ($replacementCount !== 1) {
        fail_patch('Cannot replace the stylesheet cache-buster anchor');
    }

    $backupDir = $cwd . '/_patch_backups/' . PATCH_NAME . '-' . date('Ymd-His');
    $backupPath = $backupDir . '/' . $templateRelative . '.bak';
    if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0755, true)) {
        fail_patch('Cannot create backup directory');
    }

    if (!copy($templatePath, $backupPath)) {
        fail_patch('Cannot create template backup');
    }

    out('backup_file', $backupPath);

    if (file_put_contents($templatePath, $updated, LOCK_EX) === false) {
        fail_patch('Cannot write stylesheet template');
    }

    $verify = file_get_contents($templatePath);
    if (!is_string($verify) || substr_count($verify, OLD_CSS_URL) !== 0 || substr_count($verify, NEW_CSS_URL) !== 1) {
        @copy($backupPath, $templatePath);
        fail_patch('Template verification failed; backup restored');
    }

    out('changed_file', $templateRelative);
    out('stylesheet_cache_buster', 'toc003-20260724');
    out('cache_clear', 'required_for_compiled_template');
    out('done', 'ok');

    @unlink(__FILE__);
    out('self_delete', file_exists(__FILE__) ? 'failed' : 'ok');
}

try {
    run();
} catch (Throwable $e) {
    out('error', $e->getMessage());
    out('done', 'failed');
    exit(1);
}
