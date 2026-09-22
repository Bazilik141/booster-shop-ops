<?php
declare(strict_types=1);

/* RD-11 follow-up: hide cart-only recommendation module output. No DB, checkout, payment, delivery or price changes. */
$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function failRd11Recommendations(string $message): void {
    fwrite(STDERR, "error={$message}\n");
    exit(1);
}

function lintRd11Recommendations(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        failRd11Recommendations('php_l_failed output=' . implode(' | ', $output));
    }
}

function twigCompileRd11Recommendations(string $root, string $templatePath, string $template): void {
    $config = file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        throw new RuntimeException('dir_storage_missing_in_config');
    }

    $twigRoot = rtrim($match[1], '/\\') . '/vendor/twig/twig/src/';
    if (!is_file($twigRoot . 'Environment.php')) {
        throw new RuntimeException('twig_source_missing');
    }

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
        $file = $twigRoot . $resource;
        if (is_file($file)) {
            require_once $file;
        }
    }

    if (!class_exists('Twig\\Environment')) {
        throw new RuntimeException('twig_autoload_missing');
    }

    try {
        $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), [
            'cache' => false,
            'autoescape' => false,
            'debug' => true,
            'auto_reload' => true,
        ]);
        $twig->parse($twig->tokenize(new \Twig\Source($template, $templatePath)));
    } catch (\Twig\Error\SyntaxError $exception) {
        throw new RuntimeException('twig_compile_failed file=' . $templatePath . ' line=' . $exception->getTemplateLine() . ' message=' . $exception->getRawMessage());
    }

    echo "twig_compile=ok files=1\n";
}

lintRd11Recommendations(__FILE__);

if (!is_file($root . '/config.php')) {
    failRd11Recommendations('run_from_opencart_root_config_missing');
}

$relativePath = 'catalog/view/template/checkout/cart_list.twig';
$targetPath = $root . '/' . $relativePath;
if (!is_file($targetPath)) {
    failRd11Recommendations('target_missing file=' . $relativePath);
}

echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$source = file_get_contents($targetPath);
if (!is_string($source)) {
    failRd11Recommendations('target_read_failed file=' . $relativePath);
}

$marker = '{# RD-11 cart recommendations disabled #}';
if (str_contains($source, $marker)) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}

$old = '  {% if modules %}<section class="bs-cart-recommendations"><h2>Часто беруть разом</h2>{% for module in modules %}{{ module }}{% endfor %}</section>{% endif %}';
$count = substr_count($source, $old);
if ($count !== 1) {
    failRd11Recommendations('anchor_count name=cart_recommendations expected=1 actual=' . $count);
}

$updated = str_replace($old, '  ' . $marker, $source);
try {
    twigCompileRd11Recommendations($root, $relativePath, $updated);
} catch (Throwable $exception) {
    failRd11Recommendations($exception->getMessage());
}

$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
$backupPath = $backup . '/' . $relativePath;
if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0755, true)) {
    failRd11Recommendations('backup_dir_failed');
}
if (!copy($targetPath, $backupPath)) {
    failRd11Recommendations('backup_failed file=' . $relativePath);
}

echo 'backup=' . $backup . "\n";

$written = file_put_contents($targetPath, $updated, LOCK_EX);
if ($written !== strlen($updated)) {
    @copy($backupPath, $targetPath);
    failRd11Recommendations('write_failed file=' . $relativePath . ' restored=yes');
}

try {
    twigCompileRd11Recommendations($root, $relativePath, $updated);
} catch (Throwable $exception) {
    @copy($backupPath, $targetPath);
    failRd11Recommendations($exception->getMessage() . ' restored=yes');
}

echo 'changed_file=' . $relativePath . "\n";
echo 'php_l=ok file=' . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
