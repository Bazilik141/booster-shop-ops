<?php
/**
 * PAY-002 correction: show an excess-stock warning without misclassifying it as a minimum-quantity block.
 * File-only. Run an uploaded copy from ~/public_html. No database operations.
 * Rollback: restore both files from the printed _patch_backups directory, then clear template cache.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_cart-stock-hint-correction_20260831';
const BEFORE_SHA256 = [
    'catalog/controller/checkout/cart.php' => '4901f911fe6ecd59ab764725b393842f477517480cfa1954529d00b1a0839a7b',
    'catalog/view/template/checkout/cart_list.twig' => '97802adf60179d1e631d3119611b48c7b772f8ed3279005b99be92e8294e67ac'
];

function out(string $message): void { echo $message . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException('ERROR: ' . $message); }
function need(bool $condition, string $message): void { if (!$condition) fail($message); }
function replaceOnce(string $source, string $anchor, string $replacement, string $label): string {
    $count = substr_count($source, $anchor);
    need($count === 1, 'anchor count for ' . $label . ' is ' . $count . ', expected 1');
    return str_replace($anchor, $replacement, $source);
}
function copyVerified(string $from, string $to, string $expected, string $label): void {
    need(copy($from, $to), $label . ' copy failed');
    need(hash_file('sha256', $to) === $expected, $label . ' hash verification failed');
}
function lint(string $path): void {
    $output = []; $exitCode = 0;
    exec('php -l ' . escapeshellarg($path) . ' 2>&1', $output, $exitCode);
    need($exitCode === 0, 'php -l failed: ' . implode(' | ', $output));
    out('php_l=ok file=' . $path);
}

try {
    $root = getcwd() ?: '';
    need(PHP_SAPI === 'cli' && realpath($root) === realpath(__DIR__) && is_file($root . '/config.php'), 'Run an uploaded copy from OpenCart public_html');
    $files = array_keys(BEFORE_SHA256);
    $sourceHashes = [];
    foreach ($files as $rel) {
        need(is_file($root . '/' . $rel), 'missing target: ' . $rel);
        $actual = hash_file('sha256', $root . '/' . $rel);
        need(is_string($actual), 'cannot hash target: ' . $rel);
        $sourceHashes[$rel] = $actual;
    }

    $controllerPath = $root . '/catalog/controller/checkout/cart.php';
    $templatePath = $root . '/catalog/view/template/checkout/cart_list.twig';
    $controller = file_get_contents($controllerPath);
    $template = file_get_contents($templatePath);
    need(is_string($controller) && is_string($template), 'cannot read target');
    $alreadyApplied = str_contains($controller, "\t\t\$data['pay002_cart_stock_warning'] = \$has_stock_error;")
        && !str_contains($controller, 'pay002_cart_minimum_checkout_blocked')
        && str_contains($template, 'data-pay002-cart-stock-hint')
        && !str_contains($template, 'мінімальну кількість')
        && str_contains($template, '>Виправте кількість товарів</span>');
    if ($alreadyApplied) {
        out('already_applied=yes');
        if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes'); else out('self_delete=ok');
        exit(0);
    }
    foreach ($files as $rel) {
        need(hash_equals(BEFORE_SHA256[$rel], $sourceHashes[$rel]), 'source SHA mismatch for ' . $rel . ' expected=' . BEFORE_SHA256[$rel] . ' actual=' . $sourceHashes[$rel] . '; write skipped');
    }
    $eol = str_contains($controller, "\r\n") ? "\r\n" : "\n";

    $wrongControllerState = <<<'PHP'
		// PAY-002: checkout itself redirects for these two cart conditions.
		$data['pay002_cart_stock_checkout_blocked'] = $has_stock_error && !$this->config->get('config_stock_checkout');
		$data['pay002_cart_minimum_checkout_blocked'] = false;
PHP;
    $correctControllerState = <<<'PHP'
		// PAY-002: preserve checkout stock policy; the cart warning is independent of it.
		$data['pay002_cart_stock_warning'] = $has_stock_error;
		$data['pay002_cart_stock_checkout_blocked'] = $has_stock_error && !$this->config->get('config_stock_checkout');
PHP;
    $controller = replaceOnce($controller, $wrongControllerState, str_replace("\n", $eol, $correctControllerState), 'incorrect PAY-002 stock state');
    $wrongMinimumLine = "\t\t\tif (\$product['minimum_status']) \$data['pay002_cart_minimum_checkout_blocked'] = true;";
    $controller = replaceOnce($controller, $wrongMinimumLine, '', 'incorrect minimum-quantity state');

    $wrongHint = <<<'TWIG'
  {% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}
    <div class="alert alert-warning" role="alert" data-pay002-cart-checkout-hint>
      <i class="fa-solid fa-circle-info"></i> <strong>Поки що не можна перейти до оформлення.</strong>
      {% if pay002_cart_stock_checkout_blocked %} Кількість одного або кількох товарів перевищує доступний залишок — зменште кількість або приберіть позицію.{% endif %}
      {% if pay002_cart_minimum_checkout_blocked %} Для одного або кількох товарів не досягнуто мінімальну кількість — збільшіть кількість або приберіть позицію.{% endif %}
    </div>
  {% endif %}
TWIG;
    $correctHint = <<<'TWIG'
  {% if pay002_cart_stock_warning %}
    <div class="alert alert-warning" role="alert" data-pay002-cart-stock-hint>
      <i class="fa-solid fa-circle-info"></i> <strong>Перевірте кількість товарів у кошику.</strong>
      Для одного або кількох товарів обрана кількість перевищує доступний залишок — зменште кількість або приберіть позицію.
    </div>
  {% endif %}
TWIG;
    $template = replaceOnce($template, $wrongHint, $correctHint, 'incorrect cart warning');
    $wrongCta = <<<'TWIG'
    <div class="col text-end">
      {% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}
        <span class="btn bs-cart-checkout disabled" aria-disabled="true" title="Виправте кількість товарів у кошику">Виправте кошик для оформлення</span>
      {% else %}
        <a href="{{ checkout }}" class="btn bs-cart-checkout">Продовжити</a>
      {% endif %}
    </div>
TWIG;
    $correctCta = <<<'TWIG'
    <div class="col text-end">
      {% if pay002_cart_stock_checkout_blocked %}
        <span class="btn bs-cart-checkout disabled" aria-disabled="true" title="Виправте кількість товарів у кошику">Виправте кількість товарів</span>
      {% else %}
        <a href="{{ checkout }}" class="btn bs-cart-checkout">Продовжити</a>
      {% endif %}
    </div>
TWIG;
    $template = replaceOnce($template, $wrongCta, $correctCta, 'incorrect cart checkout CTA');

    $changed = [
        'catalog/controller/checkout/cart.php' => $controller,
        'catalog/view/template/checkout/cart_list.twig' => $template
    ];
    foreach ($files as $rel) out('candidate_sha256=' . $rel . ':' . hash('sha256', $changed[$rel]));
    need(substr_count($controller, 'pay002_cart_stock_warning') === 1, 'stock-warning assertion failed');
    need(substr_count($controller, 'pay002_cart_stock_checkout_blocked') === 1, 'stock-policy assertion failed');
    need(!str_contains($controller, 'pay002_cart_minimum_checkout_blocked'), 'obsolete minimum state remains in controller');
    need(substr_count($template, 'data-pay002-cart-stock-hint') === 1, 'stock-hint assertion failed');
    need(!str_contains($template, 'мінімальну кількість'), 'incorrect minimum copy remains in template');
    need(substr_count($template, '>Виправте кількість товарів</span>') === 1, 'blocked CTA assertion failed');

    $backup = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
    foreach ($files as $rel) {
        $destination = $backup . '/' . $rel;
        if (!is_dir(dirname($destination))) need(mkdir(dirname($destination), 0755, true) || is_dir(dirname($destination)), 'cannot create backup directory');
        copyVerified($root . '/' . $rel, $destination, BEFORE_SHA256[$rel], 'backup ' . $rel);
    }
    try {
        foreach ($changed as $rel => $contents) {
            $written = file_put_contents($root . '/' . $rel, $contents, LOCK_EX);
            need($written === strlen($contents), 'write failed: ' . $rel);
        }
        lint($controllerPath);
        foreach ($files as $rel) out('after_sha256=' . $rel . ':' . hash_file('sha256', $root . '/' . $rel));
        out('assertions=ok');
        out('cwd=' . $root);
        out('time=' . date('c'));
        out('backup=' . $backup);
        out('changed=' . implode(',', $files));
        out('database_touched=no');
        out('done=ok');
    } catch (Throwable $error) {
        foreach ($files as $rel) copyVerified($backup . '/' . $rel, $root . '/' . $rel, BEFORE_SHA256[$rel], 'restore ' . $rel);
        fail('source restored: ' . $error->getMessage());
    }
    if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes'); else out('self_delete=ok');
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
