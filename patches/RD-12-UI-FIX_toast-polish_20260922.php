<?php
declare(strict_types=1);

/* RD-12 toast polish (2026-09-22). Requires the canonical toast rebuild. No business logic, DB, checkout, payment, or price changes. */

$id = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();
function fail12p(string $message): void { fwrite(STDERR, "error=$message\n"); exit(1); }
function replaceOne12p(string $source, string $old, string $new, string $name): string {
    $count = substr_count($source, $old);
    if ($count !== 1) { fail12p("anchor_count name=$name expected=1 actual=$count"); }
    return str_replace($old, $new, $source);
}
function lint12p(string $file): void {
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);
    if ($code !== 0) { fail12p('php_l_failed ' . implode(' | ', $output)); }
}

lint12p(__FILE__);
if (!is_file($root . '/config.php')) { fail12p('run_from_opencart_root_config_missing'); }
$jsPath = 'catalog/view/javascript/common.js';
$cssPath = 'catalog/view/stylesheet/boostershop-ds.css';
foreach ([$jsPath, $cssPath] as $path) { if (!is_file($root . '/' . $path)) { fail12p('target_missing file=' . $path); } }
echo 'cwd=' . $root . "\ntime=" . date(DATE_ATOM) . "\n";

$js = file_get_contents($root . '/' . $jsPath);
$css = file_get_contents($root . '/' . $cssPath);
$requiredMarker = 'RD-12 canonical toast rebuild 2026-09-22';
$marker = 'RD-12 toast polish 2026-09-22';
if (str_contains($js, $marker) && str_contains($css, $marker)) { echo "already_applied=yes\n"; @unlink(__FILE__); exit; }
if (!str_contains($js, $requiredMarker) || !str_contains($css, $requiredMarker)) { fail12p('required_canonical_toast_rebuild_missing'); }
if (str_contains($js, $marker) || str_contains($css, $marker)) { fail12p('partial_marker_detected'); }

$oldToast = <<<'JS'
    // RD-12 toast layout and swipe fix 2026-09-22: actions are one component row, never grid columns beside copy.
    // RD-12 canonical toast rebuild 2026-09-22: product page uses this same component for every cart result.
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

$newToast = <<<'JS'
    // RD-12 toast polish 2026-09-22: no server-supplied links in copy; actions are breakpoint-specific.
    function bsToastPosition() {
        var alert = $('#alert');
        var mobile = window.matchMedia('(max-width: 767.98px)').matches;

        if (!mobile) {
            alert.css('top', '');
            return;
        }

        var header = document.querySelector('.bs-header');
        var headerBottom = header ? header.getBoundingClientRect().bottom : 0;
        alert.css('top', Math.max(0, Math.round(headerBottom)) + 'px');
    }

    window.bsToast = function(type, message) {
        var ok = type === 'success';
        var mobile = window.matchMedia('(max-width: 767.98px)').matches;
        var title = ok ? 'Товар у кошику' : 'Не вдалося додати';
        var state = ok ? 'success' : 'error';
        var plainMessage = $('<div>').html(String(message || '')).text();
        var primaryLabel = ok && mobile ? 'Оформити замовлення' : (ok ? 'Переглянути кошик' : 'Відкрити кошик');
        var primaryHref = ok && mobile ? 'index.php?route=checkout/checkout' : 'index.php?route=checkout/cart';
        var toast = $('<div>', {
            'class': 'bs-toast bs-toast--' + state,
            role: ok ? 'status' : 'alert',
            'aria-live': ok ? 'polite' : 'assertive'
        });
        var copy = $('<div>', { 'class': 'bs-toast__copy' });
        var actions = $('<div>', { 'class': 'bs-toast__actions' });

        copy.append($('<strong>').text(title)).append($('<span>').text(plainMessage));
        actions.append($('<a>', { href: primaryHref, 'class': 'bs-btn bs-btn-primary' }).text(primaryLabel));

        if (ok && !mobile) {
            actions.append($('<a>', { href: 'index.php?route=checkout/checkout', 'class': 'bs-btn bs-btn-secondary bs-toast__checkout' }).text('Оформити замовлення'));
        } else {
            actions.append($('<button>', { type: 'button', 'class': 'bs-btn bs-btn-secondary', 'data-bs-toast-close': '' }).text('Продовжити'));
        }

        toast.append($('<div>', { 'class': 'bs-toast__icon' }).text(ok ? '✓' : '!')).append(copy).append(actions);
        $('#alert').empty().append(toast);
        bsToastPosition();

        if (ok) {
            window.clearTimeout(window.bsToastTimer);
            window.bsToastTimer = window.setTimeout(function() { $('#alert').empty(); }, 4000);
        }
    };
    $(window).off('scroll.bsToastPosition resize.bsToastPosition').on('scroll.bsToastPosition resize.bsToastPosition', function() {
        if ($('#alert .bs-toast').length) { bsToastPosition(); }
    });
JS;
$js = replaceOne12p($js, $oldToast, $newToast, 'toast_markup_and_position');

$oldCssEnd = '.bs-toast--error .bs-btn-primary{color:#fff}}';
$newCssEnd = <<<'CSS'
.bs-toast--error .bs-btn-primary{color:#fff}}
/* RD-12 toast polish 2026-09-22. */
@media(min-width:768px){#alert{width:360px}.bs-toast__actions{grid-template-columns:minmax(145px,1fr) minmax(160px,1fr)}.bs-toast__actions .bs-btn{height:40px;padding:0 14px}.bs-toast__actions .bs-btn-primary{color:#fff}.bs-toast__actions .bs-toast__checkout{border:2px solid var(--bs-green);background:#fff;color:var(--bs-ink)}}
CSS;
$css = replaceOne12p($css, $oldCssEnd, $newCssEnd, 'canonical_toast_css_end');

$files = [$jsPath => $js, $cssPath => $css];
$backup = $root . '/_patch_backups/' . $id . '-' . date('Ymd-His');
foreach ($files as $path => $_) {
    $backupFile = $backup . '/' . $path;
    if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true)) { fail12p('backup_dir_failed'); }
    if (!copy($root . '/' . $path, $backupFile)) { fail12p('backup_failed file=' . $path); }
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
    fail12p($error->getMessage() . ' restored=yes');
}
foreach (array_keys($files) as $path) { echo "changed_file=$path\n"; }
echo "php_l=ok file=" . basename(__FILE__) . "\ndone=ok\n";
@unlink(__FILE__);
