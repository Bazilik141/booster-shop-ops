<?php
declare(strict_types=1);

$patch = 'st2a10_gtag_guard_20260613';
$root = getcwd() ?: __DIR__;
$time = gmdate('Ymd-His');
$stamp = gmdate('c');
$targetRel = 'extension/Ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$fallbackRel = 'extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php';
$backupRoot = '_patch_backups/' . $patch . '-' . $time;

function st2a10_log(string $message): void
{
    echo '[' . gmdate('c') . '] ' . $message . PHP_EOL;
}

function st2a10_fail(string $message, int $code = 1): void
{
    st2a10_log('error=' . $message);
    st2a10_log('done=failed');
    exit($code);
}

function st2a10_path_join(string $base, string $rel): string
{
    return rtrim($base, "\\/") . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
}

function st2a10_replace_once(string $content, string $search, string $replace, string $label): string
{
    $count = substr_count($content, $search);

    if ($count !== 1) {
        st2a10_fail('precheck failed: expected exactly 1 match for ' . $label . ', got ' . $count);
    }

    return str_replace($search, $replace, $content);
}

function st2a10_php_lint(string $path): bool
{
    $cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path);
    $output = [];
    $status = 0;
    exec($cmd, $output, $status);

    foreach ($output as $line) {
        st2a10_log('php_lint=' . $line);
    }

    return $status === 0;
}

st2a10_log('patch=' . $patch);
st2a10_log('cwd=' . $root);
st2a10_log('time=' . $stamp);
st2a10_log('scope=ST-2a.10 gtag/dataLayer guard in ps_enhanced_measurement model only; no DB, no cart/checkout business logic');
st2a10_log('db_schema_changes=none');

$target = st2a10_path_join($root, $fallbackRel);
$targetUsedRel = $fallbackRel;

if (!is_file($target)) {
    $target = st2a10_path_join($root, $targetRel);
    $targetUsedRel = $targetRel;
}

if (!is_file($target)) {
    st2a10_fail('target file not found: ' . $fallbackRel);
}

$content = file_get_contents($target);
if ($content === false) {
    st2a10_fail('cannot read target file: ' . $targetUsedRel);
}

$marker = 'ST-2a.10: keep analytics from breaking storefront flows before consent/bootstrap.';

if (strpos($content, $marker) !== false) {
    st2a10_log('already_applied=yes');
    if (!st2a10_php_lint($target)) {
        st2a10_fail('php syntax check failed for already patched target: ' . $targetUsedRel);
    }
    st2a10_log('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$oldHead = <<<'TXT'
            <script>
                var ps_dataLayer = {
TXT;

$newHead = <<<'TXT'
            <script>
                // ST-2a.10: keep analytics from breaking storefront flows before consent/bootstrap.
                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };

                var ps_dataLayer = {
TXT;

$oldGa4 = <<<'TXT'
                            {% if ps_enhanced_measurement_implementation == 'gtag' %}
                            if (data.hasOwnProperty('ecommerce')) {
                                gtag('event', eventName, data.ecommerce);
                            } else {
                                gtag('event', eventName, data);
                            }
                            {% elseif ps_enhanced_measurement_implementation == 'gtm' %}
TXT;

$newGa4 = <<<'TXT'
                            {% if ps_enhanced_measurement_implementation == 'gtag' %}
                            if (typeof window.gtag === 'function') {
                                if (data.hasOwnProperty('ecommerce')) {
                                    window.gtag('event', eventName, data.ecommerce);
                                } else {
                                    window.gtag('event', eventName, data);
                                }
                            }
                            {% elseif ps_enhanced_measurement_implementation == 'gtm' %}
TXT;

$oldAdwordsIf = <<<'TXT'
                        if (this.adwords_tracking.hasOwnProperty(eventName)) {
TXT;

$newAdwordsIf = <<<'TXT'
                        if (this.adwords_tracking.hasOwnProperty(eventName) && typeof window.gtag === 'function') {
TXT;

$oldAdwordsSet = <<<'TXT'
                            gtag('set', 'user_data', this.adwords_user_data);
TXT;

$newAdwordsSet = <<<'TXT'
                            window.gtag('set', 'user_data', this.adwords_user_data);
TXT;

$oldAdwordsEvent = <<<'TXT'
                        gtag('event', 'conversion', conversion_data);
TXT;

$newAdwordsEvent = <<<'TXT'
                        window.gtag('event', 'conversion', conversion_data);
TXT;

$patched = $content;
$patched = st2a10_replace_once($patched, $oldHead, $newHead, 'gtag stub insertion point');
$patched = st2a10_replace_once($patched, $oldGa4, $newGa4, 'GA4 gtag event guard');
$patched = st2a10_replace_once($patched, $oldAdwordsIf, $newAdwordsIf, 'AdWords gtag guard');
$patched = st2a10_replace_once($patched, $oldAdwordsSet, $newAdwordsSet, 'AdWords user_data gtag call');
$patched = st2a10_replace_once($patched, $oldAdwordsEvent, $newAdwordsEvent, 'AdWords conversion gtag call');

if (substr_count($patched, 'window.gtag = window.gtag || function(){ window.dataLayer.push(arguments); };') !== 1) {
    st2a10_fail('postcheck failed: expected exactly one gtag stub');
}

$dangerousCalls = [
    "                                gtag('event', eventName, data.ecommerce);",
    "                                gtag('event', eventName, data);",
    "                            gtag('set', 'user_data', this.adwords_user_data);",
    "                        gtag('event', 'conversion', conversion_data);",
];

foreach ($dangerousCalls as $call) {
    if (strpos($patched, $call) !== false) {
        st2a10_fail('postcheck failed: old unguarded gtag call remains');
    }
}

$backupPath = st2a10_path_join($root, $backupRoot . '/' . $targetUsedRel);
$backupDir = dirname($backupPath);

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
    st2a10_fail('cannot create backup dir: ' . $backupDir);
}

if (!copy($target, $backupPath)) {
    st2a10_fail('cannot create backup: ' . $backupPath);
}

st2a10_log('backup=' . str_replace($root . DIRECTORY_SEPARATOR, '', $backupPath));

if (file_put_contents($target, $patched, LOCK_EX) === false) {
    @copy($backupPath, $target);
    st2a10_fail('cannot write target; backup restored');
}

st2a10_log('changed=' . $targetUsedRel);

if (!st2a10_php_lint($target)) {
    @copy($backupPath, $target);
    st2a10_fail('php syntax check failed; backup restored for ' . $targetUsedRel);
}

st2a10_log('rollback=restore ' . str_replace($root . DIRECTORY_SEPARATOR, '', $backupPath) . ' and clear OpenCart cache');
st2a10_log('done=ok');
@unlink(__FILE__);
