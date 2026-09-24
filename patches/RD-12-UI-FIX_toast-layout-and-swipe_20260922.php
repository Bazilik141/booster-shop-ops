<?php
declare(strict_types=1);

/*
 * RD-12 UI follow-up (2026-09-22): repair the toast component layout and
 * mobile bottom-sheet swipe dismissal. No checkout/payment/price/controller/DB changes.
 */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function fail12t(string $message): void { fwrite(STDERR, "error=$message\n"); exit(1); }
function replaceOne12t(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);
    if ($count !== 1) { fail12t("anchor_count name=$name expected=1 actual=$count"); }
    return str_replace($old, $new, $source);
}
function lint12t(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) { fail12t('php_l_failed ' . implode(' | ', $output)); }
}
function twigStorage12t(string $root): string {
    $config = file_get_contents($root . '/config.php');
    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) { fail12t('dir_storage_missing_in_config'); }
    return rtrim($match[1], '/\\');
}
function twigCompile12t(string $root, string $templatePath, string $template): void {
    $twigRoot = twigStorage12t($root) . '/vendor/twig/twig/src/';
    if (!is_file($twigRoot . 'Environment.php')) { fail12t('twig_source_missing'); }
    spl_autoload_register(static function (string $class) use ($twigRoot): void {
        if (strncmp($class, 'Twig\\', 5) !== 0) { return; }
        $file = $twigRoot . str_replace('\\', '/', substr($class, 5)) . '.php';
        if (is_file($file)) { require_once $file; }
    });
    foreach (['Resources/core.php', 'Resources/debug.php', 'Resources/escaper.php', 'Resources/string_loader.php'] as $resource) {
        $file = $twigRoot . $resource;
        if (is_file($file)) { require_once $file; }
    }
    if (!class_exists('Twig\\Environment')) { fail12t('twig_autoload_missing'); }
    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), ['cache' => false, 'autoescape' => false, 'debug' => true, 'auto_reload' => true]);
    try { $twig->parse($twig->tokenize(new \Twig\Source($template, $templatePath))); }
    catch (\Twig\Error\SyntaxError $error) { fail12t('twig_compile_failed file=' . $templatePath . ' line=' . $error->getTemplateLine() . ' message=' . $error->getRawMessage()); }
    echo "twig_compile=ok files=1\n";
}

lint12t(__FILE__);
if (!is_file($root . '/config.php')) { fail12t('run_from_opencart_root_config_missing'); }

$twigPath = 'catalog/view/template/common/cart.twig';
$jsPath = 'catalog/view/javascript/common.js';
$cssPath = 'catalog/view/stylesheet/boostershop-ds.css';
foreach ([$twigPath, $jsPath, $cssPath] as $path) { if (!is_file($root . '/' . $path)) { fail12t('target_missing file=' . $path); } }
echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$twig = file_get_contents($root . '/' . $twigPath);
$js = file_get_contents($root . '/' . $jsPath);
$css = file_get_contents($root . '/' . $cssPath);
$requiredTwigMarker = '{# RD-12 mobile sheet interaction fix 2026-09-22. #}';
$requiredCssMarker = '/* RD-12 mobile sheet interaction fix 2026-09-22. */';
$marker = 'RD-12 toast layout and swipe fix 2026-09-22';

if (str_contains($twig, $marker) && str_contains($js, $marker) && str_contains($css, $marker)) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}
if (!str_contains($twig, $requiredTwigMarker) || !str_contains($css, $requiredCssMarker)) { fail12t('required_rd12_mobile_fix_missing'); }
if (str_contains($twig, $marker) || str_contains($js, $marker) || str_contains($css, $marker)) { fail12t('partial_marker_detected'); }

$twig = replaceOne12t($twig, $requiredTwigMarker, $requiredTwigMarker . "\n{# $marker. #}", 'toast_swipe_marker');

$oldSwipe = <<<'JS'
var miniCartSwipeStartY = null;
$(document).off('touchstart.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__handle, [data-bs-mini-cart] .bs-mini-cart__head').on('touchstart.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__handle, [data-bs-mini-cart] .bs-mini-cart__head', function(event) {
    var touch = event.originalEvent.touches[0];
    miniCartSwipeStartY = touch ? touch.clientY : null;
});
$(document).off('touchend.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__handle, [data-bs-mini-cart] .bs-mini-cart__head').on('touchend.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__handle, [data-bs-mini-cart] .bs-mini-cart__head', function(event) {
    var touch = event.originalEvent.changedTouches[0];
    var movedDown = touch && miniCartSwipeStartY !== null && touch.clientY - miniCartSwipeStartY > 72;
    miniCartSwipeStartY = null;

    if (movedDown && window.matchMedia('(max-width: 767.98px)').matches) {
        setMiniCartDrawerState(false);
    }
});
JS;

$newSwipe = <<<'JS'
var miniCartSwipeStartY = null;
var miniCartSwipeCanClose = false;
$(document).off('touchstart.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel').on('touchstart.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel', function(event) {
    var touch = event.originalEvent.touches[0];
    var body = $(this).find('.bs-mini-cart__body');
    miniCartSwipeStartY = touch ? touch.clientY : null;
    miniCartSwipeCanClose = !$(event.target).closest('.bs-mini-cart__body').length || body.scrollTop() === 0;
});
$(document).off('touchmove.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel').on('touchmove.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel', function(event) {
    var touch = event.originalEvent.touches[0];

    if (miniCartSwipeCanClose && touch && miniCartSwipeStartY !== null && touch.clientY > miniCartSwipeStartY) {
        event.preventDefault();
    }
});
$(document).off('touchend.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel').on('touchend.miniCartDrawerSwipe', '[data-bs-mini-cart] .bs-mini-cart__panel', function(event) {
    var touch = event.originalEvent.changedTouches[0];
    var movedDown = touch && miniCartSwipeCanClose && miniCartSwipeStartY !== null && touch.clientY - miniCartSwipeStartY > 72;
    miniCartSwipeStartY = null;
    miniCartSwipeCanClose = false;

    if (movedDown && window.matchMedia('(max-width: 767.98px)').matches) {
        setMiniCartDrawerState(false);
    }
});
JS;
$twig = replaceOne12t($twig, $oldSwipe, $newSwipe, 'mini_cart_sheet_swipe');

$oldToast = <<<'JS'
    window.bsToast = function(type, message) {
        var ok=type==='success', role=ok?'status':'alert', title=ok?'Товар у кошику':'Не вдалося додати';
        var action=ok?'Переглянути кошик':'Відкрити кошик';
        $('#alert').html('<div class="bs-toast bs-toast--'+(ok?'success':'error')+'" role="'+role+'" aria-live="polite"><div class="bs-toast__icon">'+(ok?'✓':'!')+'</div><div class="bs-toast__copy"><strong>'+title+'</strong><span>'+message+'</span></div><a href="index.php?route=checkout/cart" class="bs-btn bs-btn-primary">'+action+'</a><button type="button" class="bs-btn bs-btn-secondary" data-bs-toast-close>Продовжити</button></div>');
        if(ok){window.clearTimeout(window.bsToastTimer);window.bsToastTimer=window.setTimeout(function(){$('#alert').empty();},4000);}
    };
JS;

$newToast = <<<'JS'
    // RD-12 toast layout and swipe fix 2026-09-22: actions are one component row, never grid columns beside copy.
    window.bsToast = function(type, message) {
        var ok = type === 'success';
        var role = ok ? 'status' : 'alert';
        var title = ok ? 'Товар у кошику' : 'Не вдалося додати';
        var action = ok ? 'Переглянути кошик' : 'Відкрити кошик';
        var state = ok ? 'success' : 'error';

        $('#alert').html('<div class="bs-toast bs-toast--' + state + '" role="' + role + '" aria-live="' + (ok ? 'polite' : 'assertive') + '"><div class="bs-toast__icon">' + (ok ? '✓' : '!') + '</div><div class="bs-toast__copy"><strong>' + title + '</strong><span>' + message + '</span></div><div class="bs-toast__actions"><a href="index.php?route=checkout/cart" class="bs-btn bs-btn-primary">' + action + '</a><button type="button" class="bs-btn bs-btn-secondary" data-bs-toast-close>Продовжити</button></div></div>');

        if (ok) {
            window.clearTimeout(window.bsToastTimer);
            window.bsToastTimer = window.setTimeout(function() { $('#alert').empty(); }, 4000);
        }
    };
JS;
$js = replaceOne12t($js, $oldToast, $newToast, 'toast_component_markup');

$css = replaceOne12t(
    $css,
    $requiredCssMarker,
    $requiredCssMarker . "\n/* $marker. */\n.bs-toast{grid-template-columns:28px minmax(0,1fr);align-items:start}\n.bs-toast__actions{display:grid;grid-column:1/-1;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}\n.bs-toast__actions .bs-btn{width:100%;min-width:0}\n@media(max-width:767.98px){.bs-toast{grid-template-columns:48px minmax(0,1fr);gap:12px;padding:14px 16px}.bs-toast__icon{width:48px;height:48px;font-size:24px}.bs-toast__actions{grid-template-columns:minmax(0,1fr) 32px;align-items:center}.bs-toast__actions .bs-btn{grid-row:auto}.bs-toast__actions .bs-btn-primary{border:1px solid #fff;background:transparent;color:#fff}.bs-toast__actions .bs-btn-secondary{width:32px;height:32px;padding:0;border:0;background:transparent;color:#fff;font-size:0}.bs-toast__actions .bs-btn-secondary::after{content:'×';font-size:28px;line-height:1}.bs-toast--error .bs-btn-primary{color:#fff}}\n",
    'toast_css_layout'
);

twigCompile12t($root, $twigPath, $twig);
$files = [$twigPath => $twig, $jsPath => $js, $cssPath => $css];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;
    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) { fail12t('backup_dir_failed'); }
    if (!copy($root . '/' . $path, $backupFile)) { fail12t('backup_failed file=' . $path); }
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
    fail12t($error->getMessage() . ' restored=yes');
}
foreach (array_keys($files) as $path) { echo "changed_file=$path\n"; }
echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
