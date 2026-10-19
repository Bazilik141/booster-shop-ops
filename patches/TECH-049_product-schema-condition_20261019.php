<?php
declare(strict_types=1);

/*
 * TECH-049 WP1 — product JSON-LD offer itemCondition for non-sealed Bulk cards.
 *
 * Upload to ~/public_html and run: php TECH-049_product-schema-condition_20261019.php
 * Target: catalog/view/template/product/product.twig (product JSON-LD block only).
 *
 * Change: the offer-level "itemCondition" becomes UsedCondition when the product
 * model contains "-BULK-" (case-insensitive), otherwise NewCondition. Same rule as
 * TECH-049 WP2 in ~/merchant-feed-build.php. The hasMerchantReturnPolicy
 * itemCondition, price, availability, shippingDetails and every other line stay as
 * they are. Sealed products render byte-identical JSON-LD. No DB write.
 *
 * Twig gate: RD-12-UI-FIX_toast-canonical-rebuild_20260922 precedent — the site's
 * own Twig from DIR_STORAGE/vendor/twig/twig/src tokenizes and parses the template,
 * and additionally renders the changed line for a Bulk and a sealed model.
 * Compiled-template cache: OpenCart's Twig runs with auto_reload => true
 * (system/library/template/twig.php:109, ACC-003 handoff), so the rewritten file's
 * new mtime triggers recompilation; no cache file is deleted.
 *
 * Rollback: copy the printed backup over catalog/view/template/product/product.twig.
 */

const PATCH_ID = 'TECH-049_product-schema-condition_20261019';
const TARGET_REL = 'catalog/view/template/product/product.twig';
const OLD_LINE = '    "itemCondition": "https://schema.org/NewCondition",';
const NEW_LINE = '    "itemCondition": "https://schema.org/{% if \'-BULK-\' in (model|default(\'\')|upper) %}UsedCondition{% else %}NewCondition{% endif %}",';
const NEXT_LINE = '    "hasMerchantReturnPolicy": {';

function t49_fail(string $message): void {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit(1);
}

function t49_log(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function t49_lint_self(): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        t49_fail('php_l_failed ' . implode(' | ', $output));
    }
}

function t49_twig_root(string $root): string {
    $config = file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        t49_fail('dir_storage_missing_in_config');
    }
    $twigRoot = rtrim($match[1], '/\\') . '/vendor/twig/twig/src/';
    if (!is_file($twigRoot . 'Environment.php')) {
        t49_fail('twig_source_missing');
    }
    return $twigRoot;
}

function t49_twig(string $twigRoot): \Twig\Environment {
    spl_autoload_register(static function (string $class) use ($twigRoot): void {
        if (strncmp($class, 'Twig\\', 5) !== 0) {
            return;
        }
        $file = $twigRoot . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
    foreach (['Resources/core.php', 'Resources/debug.php', 'Resources/escaper.php', 'Resources/string_loader.php'] as $resource) {
        if (is_file($twigRoot . $resource)) {
            require_once $twigRoot . $resource;
        }
    }
    if (!class_exists('Twig\\Environment')) {
        t49_fail('twig_autoload_missing');
    }
    return new \Twig\Environment(new \Twig\Loader\ArrayLoader(), ['cache' => false, 'autoescape' => false, 'debug' => true, 'auto_reload' => true]);
}

// Parses the whole template and renders the changed line for one Bulk and one sealed model.
function t49_twig_gate(\Twig\Environment $twig, string $template, string $eol): void {
    try {
        $twig->parse($twig->tokenize(new \Twig\Source($template, TARGET_REL)));
    } catch (\Twig\Error\SyntaxError $error) {
        throw new RuntimeException('twig_compile_failed line=' . $error->getTemplateLine() . ' message=' . $error->getRawMessage());
    }
    $probe = $twig->createTemplate(NEW_LINE . $eol);
    $cases = [
        'pkm-jp-bulk-100' => '    "itemCondition": "https://schema.org/UsedCondition",',
        'OP-JP-OP01-RPK' => '    "itemCondition": "https://schema.org/NewCondition",',
        '' => OLD_LINE,
    ];
    foreach ($cases as $model => $expected) {
        if ($probe->render(['model' => $model]) !== $expected . $eol) {
            throw new RuntimeException('twig_render_check_failed model=' . ($model === '' ? '<empty>' : $model));
        }
    }
    echo 'twig_compile=ok render_check=ok' . PHP_EOL;
}

t49_lint_self();

$root = getcwd();
if (!is_file($root . '/config.php')) {
    t49_fail('run_from_opencart_root_config_missing');
}
$target = $root . '/' . TARGET_REL;
t49_log('cwd', $root);
t49_log('time', date(DATE_ATOM));
t49_log('target', $target);
if (!is_file($target) || !is_readable($target) || !is_writable($target)) {
    t49_fail('target_missing_or_not_writable file=' . TARGET_REL);
}

$source = file_get_contents($target);
if (!is_string($source)) {
    t49_fail('target_read_failed');
}
t49_log('sha256_before', hash('sha256', $source));

$eol = strpos($source, "\r\n") !== false ? "\r\n" : "\n";
$newCount = substr_count($source, NEW_LINE);
$oldAnchor = OLD_LINE . $eol . NEXT_LINE . $eol;
$oldCount = substr_count($source, $oldAnchor);

if ($newCount === 1 && $oldCount === 0) {
    t49_log('already_applied', 'yes');
    @unlink(__FILE__);
    exit(0);
}
if ($newCount !== 0) {
    t49_fail('marker_count expected=0_or_1 actual=' . $newCount);
}
if ($oldCount !== 1) {
    t49_fail('anchor_count name=offer_item_condition expected=1 actual=' . $oldCount);
}
// The policy-level line has deeper indentation and must stay exactly once.
$policyLine = '      "itemCondition": "https://schema.org/NewCondition",';
if (substr_count($source, $policyLine) !== 1) {
    t49_fail('anchor_count name=policy_item_condition expected=1 actual=' . substr_count($source, $policyLine));
}

$updated = str_replace($oldAnchor, NEW_LINE . $eol . NEXT_LINE . $eol, $source);
if (str_replace(NEW_LINE, OLD_LINE, $updated) !== $source || substr_count($updated, $policyLine) !== 1) {
    t49_fail('preservation_check_failed');
}

$twig = t49_twig(t49_twig_root($root));
try {
    t49_twig_gate($twig, $updated, $eol);
} catch (Throwable $error) {
    t49_fail($error->getMessage());
}

$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$backup = $backupDir . '/' . TARGET_REL;
if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true)) {
    t49_fail('backup_dir_failed');
}
if (!copy($target, $backup) || hash_file('sha256', $backup) !== hash('sha256', $source)) {
    t49_fail('backup_failed');
}
t49_log('backup', $backup);

$restore = static function (string $reason) use ($backup, $target): void {
    if (!copy($backup, $target)) {
        t49_fail($reason . ' restore_failed restore_manually_from=' . $backup);
    }
    t49_fail($reason . ' restored=yes');
};

if (file_put_contents($target, $updated, LOCK_EX) !== strlen($updated)) {
    $restore('write_failed');
}
clearstatcache(true, $target);
$written = file_get_contents($target);
if (!is_string($written) || $written !== $updated) {
    $restore('readback_mismatch');
}
try {
    $twig->parse($twig->tokenize(new \Twig\Source($written, TARGET_REL)));
} catch (Throwable $error) {
    $restore('twig_compile_after_write_failed');
}

t49_log('sha256_after', hash('sha256', $written));
t49_log('changed_file', TARGET_REL);
t49_log('cache_note', 'twig auto_reload recompiles on the new mtime; if the page still shows NewCondition for a Bulk card, check a theme DB override before any other change');
t49_log('done', 'ok');
@unlink(__FILE__);
