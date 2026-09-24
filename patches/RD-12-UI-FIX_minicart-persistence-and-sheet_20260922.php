<?php
declare(strict_types=1);

/*
 * RD-12 UI source fix (2026-09-22).
 * Fixes drawer persistence through the existing AJAX fragment refresh and removes
 * the accidental collision with the legacy global `footer` selector. No controller,
 * checkout, payment, price calculation, AJAX endpoint, or database changes.
 */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();

function fail12u(string $message): void {
    fwrite(STDERR, "error=$message\n");
    exit(1);
}

function replaceOne12u(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);

    if ($count !== 1) {
        fail12u("anchor_count name=$name expected=1 actual=$count");
    }

    return str_replace($old, $new, $source);
}

function lint12u(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        fail12u('php_l_failed ' . implode(' | ', $output));
    }
}

function twigStorage12u(string $root): string {
    $config = file_get_contents($root . '/config.php');

    if (!is_string($config) || !preg_match("/define\\(\\s*['\"]DIR_STORAGE['\"]\\s*,\\s*['\"]([^'\"]+)['\"]\\s*\\)/", $config, $match)) {
        fail12u('dir_storage_missing_in_config');
    }

    return rtrim($match[1], '/\\');
}

function twigCompile12u(string $root, string $templatePath, string $template): void {
    $twigRoot = twigStorage12u($root) . '/vendor/twig/twig/src/';

    if (!is_file($twigRoot . 'Environment.php')) {
        fail12u('twig_source_missing');
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
        fail12u('twig_autoload_missing');
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
        fail12u('twig_compile_failed file=' . $templatePath . ' line=' . $error->getTemplateLine() . ' message=' . $error->getRawMessage());
    }

    echo "twig_compile=ok files=1\n";
}

lint12u(__FILE__);

if (!is_file($root . '/config.php')) {
    fail12u('run_from_opencart_root_config_missing');
}

$twigPath = 'catalog/view/template/common/cart.twig';
$cssPath = 'catalog/view/stylesheet/boostershop-ds.css';

foreach ([$twigPath, $cssPath] as $path) {
    if (!is_file($root . '/' . $path)) {
        fail12u('target_missing file=' . $path);
    }
}

echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$twig = file_get_contents($root . '/' . $twigPath);
$css = file_get_contents($root . '/' . $cssPath);
$twigMarker = '{# RD-12 UI source fix 2026-09-22: preserve drawer state and avoid global footer collision. #}';
$cssMarker = '/* RD-12 UI source fix 2026-09-22: approved drawer state and footer layout. */';

if (str_contains($twig, $twigMarker) && str_contains($css, $cssMarker)) {
    echo "already_applied=yes\n";
    @unlink(__FILE__);
    exit;
}

if (str_contains($twig, $twigMarker) || str_contains($css, $cssMarker)) {
    fail12u('partial_marker_detected');
}

/* The legacy stylesheet applies absolute black-site-footer styling to every footer tag. */
$twig = replaceOne12u(
    $twig,
    '{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}',
    '{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}' . "\n" . $twigMarker,
    'rd12_drawer_marker'
);
$twig = replaceOne12u($twig, '<footer class="bs-mini-cart__foot">', '<div class="bs-mini-cart__foot">', 'mini_cart_footer_open');
$twig = replaceOne12u($twig, '</footer>{% else %}', '</div>{% else %}', 'mini_cart_footer_close');

$oldReload = <<<'JS'
function reloadMiniCartFragments(cartChanged) {
    $('#cart').load('index.php?route=common/cart.info&language={{ language }}', function() {
        $('#shopping-cart').load('index.php?route=checkout/cart.list&language={{ language }}');

        if (cartChanged) {
            $(document).trigger('bs:cart-updated');
        }
    });
}
JS;

$newReload = <<<'JS'
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

function reloadMiniCartFragments(cartChanged) {
    var reopenDrawer = $('#cart [data-bs-mini-cart]').hasClass('is-open');

    $('#cart').load('index.php?route=common/cart.info&language={{ language }}', function() {
        if (reopenDrawer) {
            setMiniCartDrawerState(true);
        }

        $('#shopping-cart').load('index.php?route=checkout/cart.list&language={{ language }}');

        if (cartChanged) {
            $(document).trigger('bs:cart-updated');
        }
    });
}
JS;
$twig = replaceOne12u($twig, $oldReload, $newReload, 'mini_cart_fragment_reload');

$oldHandlers = <<<'JS'
$(document).off('click.miniCartDrawer', '[data-bs-mini-cart-open]').on('click.miniCartDrawer', '[data-bs-mini-cart-open]', function() {
    var cart=$(this).closest('[data-bs-mini-cart]'); cart.addClass('is-open'); $(this).attr('aria-expanded','true'); $('body').addClass('bs-mini-cart-open');
});
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    var cart=$(this).closest('[data-bs-mini-cart]'); if(!cart.length)cart=$('[data-bs-mini-cart]'); cart.removeClass('is-open').find('[data-bs-mini-cart-open]').attr('aria-expanded','false'); $('body').removeClass('bs-mini-cart-open');
});
JS;

$newHandlers = <<<'JS'
$(document).off('click.miniCartDrawer', '[data-bs-mini-cart-open]').on('click.miniCartDrawer', '[data-bs-mini-cart-open]', function() {
    setMiniCartDrawerState(true);
});
$(document).off('click.miniCartDrawerClose', '[data-bs-mini-cart-close]').on('click.miniCartDrawerClose', '[data-bs-mini-cart-close]', function() {
    setMiniCartDrawerState(false);
});
JS;
$twig = replaceOne12u($twig, $oldHandlers, $newHandlers, 'mini_cart_drawer_handlers');

/* Edit RD-12's own source rules; do not add a late CSS override. */
$css = replaceOne12u(
    $css,
    '/* RD-12 mini-cart drawer and toast */',
    "/* RD-12 mini-cart drawer and toast */\n" . $cssMarker . "\nbody.bs #cart.bs-header__cart .mini-cart-trigger{position:relative}\nbody.bs #cart.bs-header__cart .mini-cart-trigger .bs-cart-qty{position:absolute;top:-6px;right:-6px;display:grid;min-width:20px;height:20px;padding:0 5px;place-items:center;border:2px solid #fff;border-radius:999px;background:var(--bs-ink);color:#fff;font-size:11.5px;font-weight:700;line-height:1}\n",
    'rd12_css_source_marker'
);
$css = replaceOne12u(
    $css,
    '.bs-mini-cart__body{flex:1;overflow:auto;padding:0 16px}',
    '.bs-mini-cart__body{flex:1 1 auto;min-height:0;overflow-y:auto;padding:0 16px}',
    'mini_cart_body_flex'
);
$css = replaceOne12u(
    $css,
    '.bs-mini-cart__foot{display:grid;gap:12px;padding:14px 16px 16px;border-top:1px solid var(--bs-line)}',
    '.bs-mini-cart__foot{display:grid;flex:0 0 auto;gap:12px;padding:14px 16px 16px;border-top:1px solid var(--bs-line);background:var(--bs-paper)}',
    'mini_cart_footer_flex'
);
$oldMobilePanel = '.bs-mini-cart__panel{top:auto;left:0;width:auto;max-height:86%;border-radius:var(--bs-r-lg) var(--bs-r-lg) 0 0;transform:translateY(100%)}';
$newMobilePanel = '.bs-mini-cart__panel{top:auto;right:0;bottom:0;left:0;width:auto;max-height:86%;border-radius:var(--bs-r-lg) var(--bs-r-lg) 0 0;transform:translateY(100%)}.bs-mini-cart__head{padding:10px 16px 12px}.bs-mini-cart__body{min-height:120px}.bs-mini-cart__foot{padding:14px 16px 16px}';
$css = replaceOne12u($css, $oldMobilePanel, $newMobilePanel, 'mini_cart_mobile_sheet');

/* Preserve the existing mobile badge rule's importance, but correct its approved design at the source. */
$oldBadgeColors = <<<CSS
background: #fff !important;
    color: #16A34A !important;
    outline: 1.5px solid #16A34A !important;
CSS;
$newBadgeColors = <<<CSS
background: var(--bs-ink) !important;
    color: #fff !important;
    outline: 2px solid #fff !important;
CSS;
$css = replaceOne12u($css, $oldBadgeColors, $newBadgeColors, 'mini_cart_mobile_badge_colors');

twigCompile12u($root, $twigPath, $twig);

$files = [$twigPath => $twig, $cssPath => $css];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');

foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;

    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) {
        fail12u('backup_dir_failed');
    }

    if (!copy($root . '/' . $path, $backupFile)) {
        fail12u('backup_failed file=' . $path);
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

    fail12u($error->getMessage() . ' restored=yes');
}

foreach (array_keys($files) as $path) {
    echo "changed_file=$path\n";
}

echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
