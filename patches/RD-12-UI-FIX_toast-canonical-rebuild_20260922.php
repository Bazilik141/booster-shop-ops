<?php
declare(strict_types=1);

/*
 * RD-12 UI follow-up (2026-09-22).
 * Rebuilds the one RD-12 CSS source block and routes all product-page cart
 * success/retry notifications through the already-deployed window.bsToast.
 * No checkout/payment decision, price, controller, database, or AJAX endpoint changes.
 */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function fail12c(string $message): void { fwrite(STDERR, "error=$message\n"); exit(1); }
function replaceExpected12c(string $source, string $old, string $new, string $name, int $expected = 1): string {
    $count = substr_count($source, $old);
    if ($count !== $expected) { fail12c("anchor_count name=$name expected=$expected actual=$count"); }
    return str_replace($old, $new, $source);
}
function lint12c(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) { fail12c('php_l_failed ' . implode(' | ', $output)); }
}
function twigStorage12c(string $root): string {
    $config = file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) { fail12c('dir_storage_missing_in_config'); }
    return rtrim($match[1], '/\\');
}
function twigCompile12c(string $root, array $templates): void {
    $twigRoot = twigStorage12c($root) . '/vendor/twig/twig/src/';
    if (!is_file($twigRoot . 'Environment.php')) { fail12c('twig_source_missing'); }
    spl_autoload_register(static function (string $class) use ($twigRoot): void {
        if (strncmp($class, 'Twig\\', 5) !== 0) { return; }
        $file = $twigRoot . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($file)) { require_once $file; }
    });
    foreach (['Resources/core.php', 'Resources/debug.php', 'Resources/escaper.php', 'Resources/string_loader.php'] as $resource) {
        $file = $twigRoot . $resource;
        if (is_file($file)) { require_once $file; }
    }
    if (!class_exists('Twig\\Environment')) { fail12c('twig_autoload_missing'); }
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), ['cache' => false, 'autoescape' => false, 'debug' => true, 'auto_reload' => true]);
    foreach ($templates as $path => $template) {
        try { $twig->parse($twig->tokenize(new \Twig\Source($template, $path))); }
        catch (\Twig\Error\SyntaxError $error) { fail12c('twig_compile_failed file=' . $path . ' line=' . $error->getTemplateLine() . ' message=' . $error->getRawMessage()); }
    }
    echo 'twig_compile=ok files=' . count($templates) . "\n";
}

lint12c(__FILE__);
if (!is_file($root . '/config.php')) { fail12c('run_from_opencart_root_config_missing'); }

$productPath = 'catalog/view/template/product/product.twig';
$cartPath = 'catalog/view/template/common/cart.twig';
$jsPath = 'catalog/view/javascript/common.js';
$cssPath = 'catalog/view/stylesheet/boostershop-ds.css';
foreach ([$productPath, $cartPath, $jsPath, $cssPath] as $path) { if (!is_file($root . '/' . $path)) { fail12c('target_missing file=' . $path); } }
echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$product = file_get_contents($root . '/' . $productPath);
$cart = file_get_contents($root . '/' . $cartPath);
$js = file_get_contents($root . '/' . $jsPath);
$css = file_get_contents($root . '/' . $cssPath);
$requiredMarker = 'RD-12 toast layout and swipe fix 2026-09-22';
$marker = 'RD-12 canonical toast rebuild 2026-09-22';

if (str_contains($product, $marker) && str_contains($js, $marker) && str_contains($css, $marker)) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}
if (!str_contains($cart, $requiredMarker) || !str_contains($js, $requiredMarker) || !str_contains($css, $requiredMarker)) { fail12c('required_rd12_toast_fix_missing'); }
if (str_contains($product, $marker) || str_contains($js, $marker) || str_contains($css, $marker)) { fail12c('partial_marker_detected'); }

/* Product page bypasses common.js with three local success paths and three retry warnings. */
$product = replaceExpected12c(
    $product,
    "$('#alert').prepend('<div class=\"alert alert-success alert-dismissible\"><i class=\"fa-solid fa-circle-check\"></i> ' + json['success'] + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');",
    "window.bsToast('success', json['success']);",
    'product_standard_success'
);
$product = replaceExpected12c(
    $product,
    "$('#alert').prepend('<div class=\"alert alert-success alert-dismissible\"><i class=\"fa-solid fa-circle-check\"></i> ' + json.success + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');",
    "window.bsToast('success', json.success);",
    'product_credit_success',
    2
);
$product = replaceExpected12c(
    $product,
    "$('#alert').prepend('<div class=\"alert alert-warning alert-dismissible bs-cart-add-timeout\"><i class=\"fa-solid fa-circle-exclamation\"></i> ' + message + ' <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>');",
    "window.bsToast('danger', message);",
    'product_retry_warning',
    3
);
$product = replaceExpected12c(
    $product,
    '// ST-2a.9: cold-session add-to-cart UX guard.',
    '// RD-12 canonical toast rebuild 2026-09-22: every product cart result uses window.bsToast.' . "\n" . '// ST-2a.9: cold-session add-to-cart UX guard.',
    'product_toast_marker'
);

/* Existing global component is the canonical markup; this marker makes the product dependency explicit. */
$js = replaceExpected12c(
    $js,
    '// RD-12 toast layout and swipe fix 2026-09-22: actions are one component row, never grid columns beside copy.',
    '// RD-12 toast layout and swipe fix 2026-09-22: actions are one component row, never grid columns beside copy.' . "\n    // $marker: product page uses this same component for every cart result.",
    'common_toast_marker'
);

/* Replace the complete final RD-12 section, rather than adding another earlier override. */
$sectionStart = '/* RD-12 mini-cart drawer and toast */';
$sectionEnd = '.bs-toast--error .bs-btn-primary{color:var(--bs-danger)}}';
if (substr_count($css, $sectionStart) !== 1 || substr_count($css, $sectionEnd) !== 1) { fail12c('rd12_css_section_shape_missing'); }
$start = strpos($css, $sectionStart);
$end = strrpos($css, $sectionEnd);
if ($start === false || $end === false || $end < $start || trim(substr($css, $end + strlen($sectionEnd))) !== '') { fail12c('rd12_css_section_not_terminal'); }

$newSection = <<<'CSS'
/* RD-12 mini-cart drawer and toast */
/* RD-12 canonical toast rebuild 2026-09-22: this is the only final RD-12 CSS section. */
.bs-mini-cart__overlay{position:fixed;z-index:var(--bs-z-modal);inset:0;background:rgba(17,24,39,.45);opacity:0;pointer-events:none;transition:opacity .22s}
.bs-mini-cart__panel{position:fixed;z-index:var(--bs-z-modal);top:0;right:0;bottom:0;display:flex;width:380px;max-width:100%;flex-direction:column;overscroll-behavior:contain;background:var(--bs-paper);box-shadow:var(--bs-sh-pop);transform:translateX(100%);transition:transform .26s cubic-bezier(.22,.7,.3,1)}
.bs-mini-cart.is-open .bs-mini-cart__overlay{opacity:1;pointer-events:auto}.bs-mini-cart.is-open .bs-mini-cart__panel{transform:translateX(0)}.bs-mini-cart__handle{display:none}
.bs-mini-cart__head{display:flex;align-items:center;gap:10px;padding:16px;border-bottom:1px solid var(--bs-line)}.bs-mini-cart__head h2{flex:1;margin:0;font-size:16px}.bs-mini-cart__head h2 span{color:var(--bs-ink-3);font-weight:600}.bs-mini-cart__head button{width:44px;height:44px;border:0;background:transparent;font-size:27px}
.bs-mini-cart__body{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;padding:0 16px}.bs-mini-cart__row{display:grid;grid-template-columns:56px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}.bs-mini-cart__row img{width:56px;height:56px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}
.bs-mini-cart__rowhead{display:flex;gap:8px}.bs-mini-cart__rowhead>a{display:-webkit-box;flex:1;overflow:hidden;-webkit-box-orient:vertical;-webkit-line-clamp:2;color:var(--bs-ink);font-size:13.5px;font-weight:600;line-height:1.4}.bs-mini-cart__rowhead button{width:32px;height:32px;border:0;background:transparent;font-size:21px}.bs-mini-cart__line,.bs-mini-cart__sum,.bs-mini-cart__links{display:flex;align-items:center;justify-content:space-between;gap:10px}.bs-mini-cart__line{margin-top:8px}
.bs-mini-cart__stepper{display:flex;align-items:center;height:44px;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}.bs-mini-cart__stepper button{width:44px;height:44px;border:0;background:transparent}.bs-mini-cart__stepper input{width:28px;border:0;text-align:center;-moz-appearance:textfield}.bs-mini-cart__stepper input::-webkit-outer-spin-button,.bs-mini-cart__stepper input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.bs-mini-cart__foot{display:grid;flex:0 0 auto;gap:12px;padding:14px 16px 16px;border-top:1px solid var(--bs-line);background:var(--bs-paper)}.bs-mini-cart__shipping{display:grid;gap:6px;padding:10px 12px;border:1px solid var(--bs-line);border-radius:var(--bs-r);font-size:13px}.bs-mini-cart__shipping.is-free{background:var(--bs-green-soft);color:var(--bs-green-hover)}.bs-mini-cart__shipping div{height:6px;border-radius:999px;background:var(--bs-line);overflow:hidden}.bs-mini-cart__shipping i{display:block;height:100%;background:var(--bs-green)}.bs-mini-cart__shipping small{color:var(--bs-ink-3)}.bs-mini-cart__sum strong{font-size:22px}.bs .bs-mini-cart__foot>a.bs-btn-primary{height:48px;color:#fff}.bs-mini-cart__links button{border:0;background:transparent;color:var(--bs-blue);font-weight:600}.bs-mini-cart-open{overflow:hidden}
.bs-toast{display:grid;grid-template-columns:28px minmax(0,1fr);align-items:start;gap:10px;padding:14px;border:1px solid;border-radius:var(--bs-r);background:var(--bs-paper);box-shadow:var(--bs-sh-pop);animation:bsToastIn .2s ease-out}.bs-toast--success{border-color:#BBE7CC}.bs-toast--error{border-color:#F3C0C0}.bs-toast__icon{display:grid;width:28px;height:28px;place-items:center;border-radius:50%;font-weight:800}.bs-toast--success .bs-toast__icon{background:var(--bs-green-soft);color:var(--bs-green-hover)}.bs-toast--error .bs-toast__icon{background:#FDECEC;color:var(--bs-danger)}.bs-toast__copy{display:grid;gap:2px;font-size:12.5px;color:var(--bs-ink-3);line-height:1.45}.bs-toast__copy strong{font-size:13.5px;color:var(--bs-ink)}.bs-toast__actions{display:grid;grid-column:1/-1;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.bs-toast__actions .bs-btn{width:100%;min-width:0;height:38px;padding:0 10px;font-size:13px}@keyframes bsToastIn{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
@media(max-width:767.98px){.bs-mini-cart__panel{top:auto;right:0;bottom:0;left:0;width:auto;max-height:86%;border-radius:var(--bs-r-lg) var(--bs-r-lg) 0 0;transform:translateY(100%)}.bs-mini-cart__head{padding:10px 16px 12px}.bs-mini-cart__body{min-height:120px}.bs-mini-cart__foot{padding:14px 16px 16px}.bs-mini-cart__handle{display:block;width:40px;height:4px;margin:8px auto 0;border-radius:999px;background:var(--bs-line)}.bs-toast{grid-template-columns:48px minmax(0,1fr);gap:12px;padding:14px 16px;border:0;border-radius:0;background:var(--bs-green);color:#fff}.bs-toast--error{background:var(--bs-danger)}.bs-toast__icon{width:48px;height:48px;font-size:24px}.bs-toast__copy,.bs-toast__copy strong{color:#fff}.bs-toast__actions{grid-template-columns:minmax(0,1fr) 32px;align-items:center}.bs-toast__actions .bs-btn{grid-row:auto}.bs-toast__actions .bs-btn-primary{border:1px solid #fff;background:transparent;color:#fff}.bs-toast__actions .bs-btn-secondary{width:32px;height:32px;padding:0;border:0;background:transparent;color:#fff;font-size:0}.bs-toast__actions .bs-btn-secondary::after{content:'×';font-size:28px;line-height:1}.bs-toast--error .bs-btn-primary{color:#fff}}
CSS;
$css = substr($css, 0, $start) . $newSection . "\n";

twigCompile12c($root, [$productPath => $product, $cartPath => $cart]);
$files = [$productPath => $product, $jsPath => $js, $cssPath => $css];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;
    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) { fail12c('backup_dir_failed'); }
    if (!copy($root . '/' . $path, $backupFile)) { fail12c('backup_failed file=' . $path); }
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
    fail12c($error->getMessage() . ' restored=yes');
}
foreach (array_keys($files) as $path) { echo "changed_file=$path\n"; }
echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
