<?php
declare(strict_types=1);

/* RD-12 desktop toast fit (2026-09-22). CSS-only; no controller, cart, checkout, payment, price, or database changes. */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();
function fail12d(string $message): void { fwrite(STDERR, "error=$message\n"); exit(1); }
function replaceOne12d(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);
    if ($count !== 1) { fail12d("anchor_count name=$name expected=1 actual=$count"); }
    return str_replace($old, $new, $source);
}
function lint12d(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) { fail12d('php_l_failed ' . implode(' | ', $output)); }
}

lint12d(__FILE__);
if (!is_file($root . '/config.php')) { fail12d('run_from_opencart_root_config_missing'); }
$baseCssPath = 'catalog/view/stylesheet/stylesheet.css';
$dsCssPath = 'catalog/view/stylesheet/boostershop-ds.css';
foreach ([$baseCssPath, $dsCssPath] as $path) { if (!is_file($root . '/' . $path)) { fail12d('target_missing file=' . $path); } }
echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$baseCss = file_get_contents($root . '/' . $baseCssPath);
$dsCss = file_get_contents($root . '/' . $dsCssPath);
$requiredMarker = 'RD-12 toast polish 2026-09-22';
$marker = 'RD-12 desktop toast fit 2026-09-22';
if (str_contains($baseCss, $marker) && str_contains($dsCss, $marker)) { echo "already_applied=yes\n"; @unlink(__FILE__); exit; }
if (!str_contains($dsCss, $requiredMarker)) { fail12d('required_toast_polish_missing'); }
if (str_contains($baseCss, $marker) || str_contains($dsCss, $marker)) { fail12d('partial_marker_detected'); }

/* The page stylesheet loads after DS and was keeping the alert at 300px. Edit that source rule. */
$baseCss = replaceOne12d(
    $baseCss,
    '#alert { position:fixed; z-index:var(--bs-z-toast); top:74px; right:24px; width:300px; }',
    '/* RD-12 desktop toast fit 2026-09-22: room for both desktop actions. */' . "\n" . '#alert { position:fixed; z-index:var(--bs-z-toast); top:74px; right:24px; width:480px; }',
    'alert_desktop_width'
);

$oldDesktopRule = '@media(min-width:768px){#alert{width:360px}.bs-toast__actions{grid-template-columns:minmax(145px,1fr) minmax(160px,1fr)}.bs-toast__actions .bs-btn{height:40px;padding:0 14px}.bs-toast__actions .bs-btn-primary{color:#fff}.bs-toast__actions .bs-toast__checkout{border:2px solid var(--bs-green);background:#fff;color:var(--bs-ink)}}';
$newDesktopRule = '/* RD-12 desktop toast fit 2026-09-22: 480px source width lives in stylesheet.css. */' . "\n" . '@media(min-width:768px){.bs-toast__actions{grid-template-columns:minmax(165px,1fr) minmax(180px,1fr)}.bs-toast__actions .bs-btn{height:40px;padding:0 16px}.bs-toast__actions .bs-btn-primary{border:2px solid var(--bs-ink);background:#fff;color:var(--bs-ink)}.bs-toast__actions .bs-toast__checkout{border:2px solid var(--bs-green);background:#fff;color:var(--bs-ink)}}';
$dsCss = replaceOne12d($dsCss, $oldDesktopRule, $newDesktopRule, 'toast_desktop_actions');

$files = [$baseCssPath => $baseCss, $dsCssPath => $dsCss];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;
    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) { fail12d('backup_dir_failed'); }
    if (!copy($root . '/' . $path, $backupFile)) { fail12d('backup_failed file=' . $path); }
}
echo "backup=$backup\n";
$written = [];
try {
    foreach ($files as $path => $contents) {
        if (file_put_contents($root . '/' . $path, $contents, LOCK_EX) !== strlen($contents)) { throw new RuntimeException('write_failed file=' . $path); }
        $written[] = $path;
    }
} catch (Throwable $error) {
    foreach ($written as $path) { @copy($backup . '/' . $path, $root . '/' . $path); }
    fail12d($error->getMessage() . ' restored=yes');
}
foreach (array_keys($files) as $path) { echo "changed_file=$path\n"; }
echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
