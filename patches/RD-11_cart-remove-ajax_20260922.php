<?php
declare(strict_types=1);

/* RD-11 cart removal follow-up. Changes only the delegated click selector in checkout/cart.twig. */
$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function rd11RemoveFail(string $message): void {
    fwrite(STDERR, 'error=' . $message . "\n");
    exit(1);
}

function rd11RemoveTwigCompile(string $root, string $relativePath, string $source): void {
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

    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), [
        'cache' => false,
        'autoescape' => false,
        'debug' => true,
        'auto_reload' => true,
    ]);
    try {
        $twig->parse($twig->tokenize(new \Twig\Source($source, $relativePath)));
    } catch (\Twig\Error\SyntaxError $exception) {
        throw new RuntimeException('twig_compile_failed file=' . $relativePath . ' line=' . $exception->getTemplateLine() . ' message=' . $exception->getRawMessage());
    }
}

exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintExit);
if ($lintExit !== 0) {
    rd11RemoveFail('php_l_failed output=' . implode(' | ', $lintOutput));
}

if (!is_file($root . '/config.php')) {
    rd11RemoveFail('run_from_opencart_root_config_missing');
}

$relativePath = 'catalog/view/template/checkout/cart.twig';
$targetPath = $root . '/' . $relativePath;
$listPath = $root . '/catalog/view/template/checkout/cart_list.twig';
if (!is_file($targetPath) || !is_file($listPath)) {
    rd11RemoveFail('target_missing file=' . (!is_file($targetPath) ? $relativePath : 'catalog/view/template/checkout/cart_list.twig'));
}

echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$source = file_get_contents($targetPath);
$list = file_get_contents($listPath);
if (!is_string($source) || !is_string($list)) {
    rd11RemoveFail('target_read_failed');
}

$listAnchor = '<a href="{{ product.remove }}" class="bs-cart-remove">';
if (substr_count($list, $listAnchor) !== 1) {
    rd11RemoveFail('anchor_count name=cart_remove_link expected=1 actual=' . substr_count($list, $listAnchor));
}

$marker = '// RD-11 cart remove AJAX selector';
$oldHandler = "    \$('#shopping-cart').on('click', '.btn-danger', function(e) {";
$newHandler = "    \$('#shopping-cart').on('click', '.bs-cart-remove', function(e) {";
if (str_contains($source, $marker)) {
    if (substr_count($source, $marker) !== 1 || substr_count($source, $newHandler) !== 1 || str_contains($source, $oldHandler)) {
        rd11RemoveFail('idempotency_marker_conflict');
    }
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}

$count = substr_count($source, $oldHandler);
if ($count !== 1) {
    rd11RemoveFail('anchor_count name=cart_remove_handler expected=1 actual=' . $count);
}

$newline = str_contains($source, "\r\n") ? "\r\n" : "\n";
$updated = str_replace($oldHandler, '    ' . $marker . $newline . $newHandler, $source);
try {
    rd11RemoveTwigCompile($root, $relativePath, $updated);
} catch (Throwable $exception) {
    rd11RemoveFail($exception->getMessage());
}
echo "twig_compile=ok files=1 stage=prewrite\n";

$backup = $root . '/_patch_backups/' . $patchId . '-' . date('Ymd-His');
$backupPath = $backup . '/' . $relativePath;
if (!is_dir(dirname($backupPath)) && !mkdir(dirname($backupPath), 0755, true)) {
    rd11RemoveFail('backup_dir_failed');
}
if (!copy($targetPath, $backupPath)) {
    rd11RemoveFail('backup_failed file=' . $relativePath);
}
echo 'backup=' . $backup . "\n";

try {
    if (file_put_contents($targetPath, $updated, LOCK_EX) !== strlen($updated)) {
        throw new RuntimeException('write_failed file=' . $relativePath);
    }
    $written = file_get_contents($targetPath);
    if ($written !== $updated) {
        throw new RuntimeException('write_verification_failed file=' . $relativePath);
    }
    rd11RemoveTwigCompile($root, $relativePath, $written);
} catch (Throwable $exception) {
    $restored = copy($backupPath, $targetPath) ? 'yes' : 'no';
    rd11RemoveFail($exception->getMessage() . ' restored=' . $restored);
}

echo "twig_compile=ok files=1 stage=postwrite\n";
echo 'changed_file=' . $relativePath . "\n";
echo 'php_l=ok file=' . basename(__FILE__) . "\n";
echo "done=ok\n";
@unlink(__FILE__);
