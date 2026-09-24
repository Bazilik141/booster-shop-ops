<?php
/**
 * PAY-002 cart UX: explain and prevent a checkout redirect caused by stock or minimum quantity.
 * File-only. Run an uploaded copy from ~/public_html. No database operations.
 * Rollback: restore both files from the printed _patch_backups directory, then clear template cache.
 */
declare(strict_types=1);

const PATCH_ID = 'PAY-002_cart-stock-checkout-hint_20260831';
const BEFORE_SHA256 = [
    'catalog/controller/checkout/cart.php' => 'ba9384d9390370bd2ac1e329338b223742dff4e618620aa545085529ac06f71b',
    'catalog/view/template/checkout/cart_list.twig' => '3b7d4f617f36a005dd5c92fb1e72cacca403d3e6b602d018db7565b399f5bc13'
];
const AFTER_SHA256 = [
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
    foreach ($files as $rel) need(is_file($root . '/' . $rel), 'missing target: ' . $rel);

    $before = [];
    foreach ($files as $rel) {
        $before[$rel] = hash_file('sha256', $root . '/' . $rel);
        need(is_string($before[$rel]), 'cannot hash target: ' . $rel);
        if ($before[$rel] === AFTER_SHA256[$rel] && AFTER_SHA256[$rel] !== '') continue;
        need(hash_equals(BEFORE_SHA256[$rel], $before[$rel]), 'source SHA mismatch for ' . $rel . ' expected=' . BEFORE_SHA256[$rel] . ' actual=' . $before[$rel] . '; write skipped');
    }
    if ($before['catalog/controller/checkout/cart.php'] === AFTER_SHA256['catalog/controller/checkout/cart.php'] && $before['catalog/view/template/checkout/cart_list.twig'] === AFTER_SHA256['catalog/view/template/checkout/cart_list.twig']) {
        out('already_applied=yes');
        if (!@unlink(__FILE__)) out('self_delete=failed remove_uploaded_patch_manually=yes'); else out('self_delete=ok');
        exit(0);
    }

    $controllerPath = $root . '/catalog/controller/checkout/cart.php';
    $templatePath = $root . '/catalog/view/template/checkout/cart_list.twig';
    $controller = file_get_contents($controllerPath);
    $template = file_get_contents($templatePath);
    need(is_string($controller) && is_string($template), 'cannot read target');
    $eol = str_contains($controller, "\r\n") ? "\r\n" : "\n";

    $stockAnchor = <<<'PHP'
		if ($has_stock_error && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
			$data['error_stock'] = $this->language->get('error_stock');
		} else {
			$data['error_stock'] = '';
		}
PHP;
    $stockAddition = <<<'PHP'
		// PAY-002: checkout itself redirects for these two cart conditions.
		$data['pay002_cart_stock_checkout_blocked'] = $has_stock_error && !$this->config->get('config_stock_checkout');
		$data['pay002_cart_minimum_checkout_blocked'] = false;
PHP;
    $stockReplacement = $stockAnchor . $eol . str_replace("\n", $eol, $stockAddition);
    $controller = replaceOnce($controller, $stockAnchor, $stockReplacement, 'cart checkout stock state');
    $minimumAnchor = <<<'PHP'
		foreach ($products as $product) {
PHP;
    $minimumReplacement = <<<'PHP'
		foreach ($products as $product) {
			if ($product['minimum_status']) $data['pay002_cart_minimum_checkout_blocked'] = true;
PHP;
    $controller = replaceOnce($controller, $minimumAnchor, str_replace("\n", $eol, $minimumReplacement), 'cart minimum state');

    $stockAlert = <<<'TWIG'
  {% if error_stock %}
    <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_stock }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  {% endif %}
TWIG;
    $hint = <<<'TWIG'
  {% if error_stock %}
    <div class="alert alert-danger alert-dismissible"><i class="fa-solid fa-circle-exclamation"></i> {{ error_stock }} <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
  {% endif %}
  {% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}
    <div class="alert alert-warning" role="alert" data-pay002-cart-checkout-hint>
      <i class="fa-solid fa-circle-info"></i> <strong>Поки що не можна перейти до оформлення.</strong>
      {% if pay002_cart_stock_checkout_blocked %} Кількість одного або кількох товарів перевищує доступний залишок — зменште кількість або приберіть позицію.{% endif %}
      {% if pay002_cart_minimum_checkout_blocked %} Для одного або кількох товарів не досягнуто мінімальну кількість — збільшіть кількість або приберіть позицію.{% endif %}
    </div>
  {% endif %}
TWIG;
    $template = replaceOnce($template, $stockAlert, $hint, 'visible cart checkout hint');
    $cta = '    <div class="col text-end"><a href="{{ checkout }}" class="btn bs-cart-checkout">Продовжити</a></div>';
    $ctaReplacement = <<<'TWIG'
    <div class="col text-end">
      {% if pay002_cart_stock_checkout_blocked or pay002_cart_minimum_checkout_blocked %}
        <span class="btn bs-cart-checkout disabled" aria-disabled="true" title="Виправте кількість товарів у кошику">Виправте кошик для оформлення</span>
      {% else %}
        <a href="{{ checkout }}" class="btn bs-cart-checkout">Продовжити</a>
      {% endif %}
    </div>
TWIG;
    $template = replaceOnce($template, $cta, $ctaReplacement, 'cart checkout CTA');

    $changed = [
        'catalog/controller/checkout/cart.php' => $controller,
        'catalog/view/template/checkout/cart_list.twig' => $template
    ];
    foreach ($files as $rel) {
        $actual = hash('sha256', $changed[$rel]);
        need(AFTER_SHA256[$rel] === '' || hash_equals(AFTER_SHA256[$rel], $actual), 'generated SHA mismatch for ' . $rel . ' actual=' . $actual . '; write skipped');
        out('candidate_sha256=' . $rel . ':' . $actual);
    }
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
        foreach ($files as $rel) {
            $actual = hash_file('sha256', $root . '/' . $rel);
            need(AFTER_SHA256[$rel] === '' || hash_equals(AFTER_SHA256[$rel], $actual), 'written SHA mismatch: ' . $rel);
            out('after_sha256=' . $rel . ':' . $actual);
        }
        need(substr_count($controller, 'pay002_cart_stock_checkout_blocked') === 1, 'stock-state assertion failed');
        need(substr_count($controller, 'pay002_cart_minimum_checkout_blocked') === 2, 'minimum-state assertion failed');
        need(substr_count($template, 'data-pay002-cart-checkout-hint') === 1, 'hint assertion failed');
        need(substr_count($template, 'Виправте кошик для оформлення') === 1, 'blocked CTA assertion failed');
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
