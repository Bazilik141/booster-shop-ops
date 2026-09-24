<?php
declare(strict_types=1);

/*
 * RD-12 UI follow-up (2026-09-22).
 * Requires the preceding RD-12-UI-FIX_minicart-persistence-and-sheet runner.
 * No controllers, checkout/payment, prices, database, or AJAX endpoints change.
 */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function fail12m(string $message): void {
    fwrite(STDERR, "error=$message\n");
    exit(1);
}

function replaceOne12m(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        fail12m("anchor_count name=$name expected=1 actual=$count");
    }

    return str_replace($old, $new, $source);
}

function lint12m(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        fail12m('php_l_failed ' . implode(' | ', $output));
    }
}

function twigStorage12m(string $root): string {
    $config = file_get_contents($root . '/config.php');

    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        fail12m('dir_storage_missing_in_config');
    }

    return rtrim($match[1], '/\\');
}

function twigCompile12m(string $root, string $templatePath, string $template): void {
    $twigRoot = twigStorage12m($root) . '/vendor/twig/twig/src/';

    if (!is_file($twigRoot . 'Environment.php')) {
        fail12m('twig_source_missing');
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
        fail12m('twig_autoload_missing');
    }

    $twig = new \Twig\Environment(new \Twig\Loader\ArrayLoader(), [
        'cache' => false,
        'autoescape' => false,
        'debug' => true,
        'auto_reload' => true
    ]);

    try {
        $twig->parse($twig->tokenize(new \Twig\Source($template, $templatePath)));
    } catch (\Twig\Error\SyntaxError $error) {
        fail12m('twig_compile_failed file=' . $templatePath . ' line=' . $error->getTemplateLine() . ' message=' . $error->getRawMessage());
    }

    echo "twig_compile=ok files=1\n";
}

lint12m(__FILE__);

if (!is_file($root . '/config.php')) {
    fail12m('run_from_opencart_root_config_missing');
}

$twigPath = 'catalog/view/template/common/cart.twig';
$cssPath = 'catalog/view/stylesheet/boostershop-ds.css';

foreach ([$twigPath, $cssPath] as $path) {
    if (!is_file($root . '/' . $path)) {
        fail12m('target_missing file=' . $path);
    }
}

echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$twig = file_get_contents($root . '/' . $twigPath);
$css = file_get_contents($root . '/' . $cssPath);
$requiredTwigMarker = '{# RD-12 UI source fix 2026-09-22: preserve drawer state and avoid global footer collision. #}';
$requiredCssMarker = '/* RD-12 UI source fix 2026-09-22: approved drawer state and footer layout. */';
$twigMarker = '{# RD-12 mobile sheet interaction fix 2026-09-22. #}';
$cssMarker = '/* RD-12 mobile sheet interaction fix 2026-09-22. */';

if (str_contains($twig, $twigMarker) && str_contains($css, $cssMarker)) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}

if (!str_contains($twig, $requiredTwigMarker) || !str_contains($css, $requiredCssMarker)) {
    fail12m('required_rd12_ui_fix_missing');
}

if (str_contains($twig, $twigMarker) || str_contains($css, $cssMarker)) {
    fail12m('partial_marker_detected');
}

/* Remove the count badge from the header markup at every breakpoint. */
$twig = replaceOne12m(
    $twig,
    $requiredTwigMarker,
    $requiredTwigMarker . "\n" . $twigMarker,
    'rd12_mobile_marker'
);
$twig = replaceOne12m(
    $twig,
    '{% if bs_cart_qty > 0 %}<span class="bs-cart-qty">{{ bs_cart_qty }}</span>{% endif %}',
    '',
    'mini_cart_trigger_badge'
);

$oldState = <<<'JS'
function setMiniCartDrawerState(open) {
    var cart = $('#cart [data-bs-mini-cart]');

    if (!cart.length) {
        return;
    }

    cart.toggleClass('is-open', open)
        .find('[data-bs-mini-cart-open]')
        .attr('aria-expanded', open ? 'true' : 'false');
    $('body').toggleClass('bs-mini-cart-open', open);
}
JS;

$newState = <<<'JS'
function setMiniCartDrawerState(open) {
    var cart = $('#cart [data-bs-mini-cart]');
    var body = $('body');
    var locked = body.hasClass('bs-mini-cart-open');

    if (!cart.length) {
        return;
    }

    if (open && !locked) {
        window.bsMiniCartScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        body.css({ position: 'fixed', top: '-' + window.bsMiniCartScrollY + 'px', width: '100%' });
    }

    cart.toggleClass('is-open', open)
        .find('[data-bs-mini-cart-open]')
        .attr('aria-expanded', open ? 'true' : 'false');
    body.toggleClass('bs-mini-cart-open', open);

    if (!open && locked) {
        var scrollY = Number(window.bsMiniCartScrollY || 0);
        body.css({ position: '', top: '', width: '' });
        window.scrollTo(0, scrollY);
    }
}
JS;
$twig = replaceOne12m($twig, $oldState, $newState, 'mini_cart_scroll_lock');

$oldHandlers = <<<'JS'
$(document).off('click.miniCartDrawer', '[data-bs-mini-cart-open]').on('click.miniCartDrawer', '[data-bs-mini-cart-open]', function() {
    setMiniCartDrawerState(true);
});
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    setMiniCartDrawerState(false);
});
JS;

$newHandlers = <<<'JS'
$(document).off('click.miniCartDrawer', '[data-bs-mini-cart-open]').on('click.miniCartDrawer', '[data-bs-mini-cart-open]', function() {
    setMiniCartDrawerState(true);
});
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    setMiniCartDrawerState(false);
});

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
$twig = replaceOne12m($twig, $oldHandlers, $newHandlers, 'mini_cart_swipe_handlers');

/* Amend RD-12 source styles rather than adding a late overriding stylesheet. */
$css = replaceOne12m(
    $css,
    $requiredCssMarker,
    $requiredCssMarker . "\n" . $cssMarker . "\n.bs-mini-cart__panel{overscroll-behavior:contain}\n.bs-mini-cart__body{overscroll-behavior:contain;-webkit-overflow-scrolling:touch}\n.bs .bs-mini-cart__foot>a.bs-btn-primary{color:#fff}\n.bs-mini-cart__stepper input[type=number]{-moz-appearance:textfield}\n.bs-mini-cart__stepper input[type=number]::-webkit-outer-spin-button,.bs-mini-cart__stepper input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}\n",
    'rd12_mobile_css_marker'
);

twigCompile12m($root, $twigPath, $twig);

$files = [$twigPath => $twig, $cssPath => $css];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');

foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;

    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) {
        fail12m('backup_dir_failed');
    }

    if (!copy($root . '/' . $path, $backupFile)) {
        fail12m('backup_failed file=' . $path);
    }
}

echo "backup=$backup\n";
$written = [];

try {
    foreach ($files as $path => $contents) {
        if (file_put_contents($root . '/' . $path, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('write_failed file=' . $path);
        }

        $written[] = $path;
    }
} catch (Throwable $error) {
    foreach ($written as $path) {
        @copy($backup . '/' . $path, $root . '/' . $path);
    }

    fail12m($error->getMessage() . ' restored=yes');
}

foreach (array_keys($files) as $path) {
    echo "changed_file=$path\n";
}

echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
