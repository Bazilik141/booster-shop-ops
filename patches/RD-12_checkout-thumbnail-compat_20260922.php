<?php
declare(strict_types=1);

/*
 * RD-12 checkout thumbnail compatibility (2026-09-22)
 *
 * Root cause: checkout-reskin.js read thumbnails from the legacy mini-cart DOM
 * (`.mini-cart-thumb` / `.img-thumbnail`). RD-12 replaced that markup with
 * `.bs-mini-cart__row`, so the checkout summary no longer found an image.
 *
 * Scope: presentation-only selector compatibility and its cache token. No
 * controller, cart data, prices, totals, payment, order or database changes.
 */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();
$marker = 'RD-12 checkout thumbnail compatibility 2026-09-22';
$jsPath = 'catalog/view/javascript/checkout-reskin.js';
$twigPath = 'catalog/view/template/checkout/checkout.twig';

function fail12thumb(string $message): void {
    fwrite(STDERR, "error={$message}\n");
    exit(1);
}

function lint12thumb(string $file): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        fail12thumb('php_l_failed ' . implode(' | ', $output));
    }
}

function replaceOne12thumb(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);
    if ($count !== 1) {
        fail12thumb("anchor_count name={$name} expected=1 actual={$count}");
    }
    return str_replace($old, $new, $source);
}

lint12thumb(__FILE__);
if (!is_file($root . '/config.php')) {
    fail12thumb('run_from_opencart_root_config_missing');
}
foreach ([$jsPath, $twigPath] as $path) {
    if (!is_file($root . '/' . $path)) {
        fail12thumb('target_missing file=' . $path);
    }
}

echo 'cwd=' . $root . "\n";
echo 'time=' . date(DATE_ATOM) . "\n";

$js = file_get_contents($root . '/' . $jsPath);
$twig = file_get_contents($root . '/' . $twigPath);
if ($js === false || $twig === false) {
    fail12thumb('read_failed');
}

$jsMarked = strpos($js, $marker) !== false;
$twigMarked = strpos($twig, $marker) !== false;
if ($jsMarked && $twigMarked) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit(0);
}
if ($jsMarked || $twigMarked) {
    fail12thumb('partial_marker_detected');
}

$oldSelector = "var images = document.querySelectorAll('#cart .mini-cart-thumb img, #cart img.img-thumbnail');";
$newSelector = "// RD-12 checkout thumbnail compatibility 2026-09-22: include the redesigned drawer rows.\n" .
    "    var images = document.querySelectorAll('#cart .mini-cart-thumb img, #cart img.img-thumbnail, #cart .bs-mini-cart__row > a > img');";
$js = replaceOne12thumb($js, $oldSelector, $newSelector, 'checkout_thumbnail_selector');

/* Read and validate the live cache token, then replace it wholesale. */
$assetPattern = '~<script\\s+src="catalog/view/javascript/checkout-reskin\\.js\\?v=([A-Za-z0-9][A-Za-z0-9._-]*)"></script>~';
$assetMatches = [];
$assetCount = preg_match_all($assetPattern, $twig, $assetMatches, PREG_SET_ORDER);
if ($assetCount !== 1) {
    fail12thumb('asset_reference_count expected=1 actual=' . (string) $assetCount);
}
$oldToken = $assetMatches[0][1];
$newToken = 'rd12checkoutthumb-20260922';
$replacement = '<!-- ' . $marker . ': cache token refreshed for checkout-reskin.js. -->' . "\n" .
    '<script src="catalog/view/javascript/checkout-reskin.js?v=' . $newToken . '"></script>';
$twig = preg_replace($assetPattern, $replacement, $twig, 1, $replacedCount);
if ($twig === null || $replacedCount !== 1) {
    fail12thumb('asset_reference_replace_failed');
}

$files = [$jsPath => $js, $twigPath => $twig];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
foreach ($files as $path => $_contents) {
    $backupFile = $backup . '/' . $path;
    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) {
        fail12thumb('backup_dir_failed file=' . $path);
    }
    if (!copy($root . '/' . $path, $backupFile)) {
        fail12thumb('backup_failed file=' . $path);
    }
}
echo "backup={$backup}\n";

$written = [];
try {
    foreach ($files as $path => $contents) {
        $bytes = file_put_contents($root . '/' . $path, $contents, LOCK_EX);
        if ($bytes === false || $bytes !== strlen($contents)) {
            throw new RuntimeException('write_failed file=' . $path);
        }
        $written[] = $path;
    }

    $writtenJs = file_get_contents($root . '/' . $jsPath);
    $writtenTwig = file_get_contents($root . '/' . $twigPath);
    if ($writtenJs === false || $writtenTwig === false ||
        strpos($writtenJs, $marker) === false ||
        strpos($writtenJs, '#cart .bs-mini-cart__row > a > img') === false ||
        strpos($writtenTwig, $marker) === false ||
        strpos($writtenTwig, 'checkout-reskin.js?v=' . $newToken) === false) {
        throw new RuntimeException('post_write_verification_failed');
    }
} catch (Throwable $error) {
    foreach ($written as $path) {
        @copy($backup . '/' . $path, $root . '/' . $path);
    }
    fail12thumb($error->getMessage() . ' restored=yes');
}

foreach (array_keys($files) as $path) {
    echo "changed_file={$path}\n";
}
echo "asset_token_old={$oldToken}\n";
echo "asset_token_new={$newToken}\n";
echo 'php_l=ok file=' . basename(__FILE__) . "\n";
echo "done=ok\n";
@unlink(__FILE__);
