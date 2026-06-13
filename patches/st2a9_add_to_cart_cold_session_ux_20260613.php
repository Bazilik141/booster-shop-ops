<?php
declare(strict_types=1);

/**
 * ST-2a.9 B1 - cold-session add-to-cart UX safeguard.
 *
 * Scope: catalog/view/template/product/product.twig client JS only.
 * No cart logic, session handling, cookie banner, analytics, or DB changes.
 */

$patch = 'st2a9_add_to_cart_cold_session_ux_20260613';
$root = getcwd() ?: __DIR__;
$backupDir = $root . '/_patch_backups/' . $patch . '-' . date('Ymd-His');
$file = 'catalog/view/template/product/product.twig';
$path = $root . '/' . $file;
$marker = 'ST-2a.9: cold-session add-to-cart UX guard';

function out(string $message): void {
    echo '[' . date('c') . '] ' . $message . PHP_EOL;
}

function fail(string $message): void {
    out('error=' . $message);
    out('done=failed');
    exit(1);
}

out('patch=' . $patch);
out('cwd=' . $root);
out('scope=product.twig add-to-cart AJAX timeout/in-flight UX guard; no DB');
out('target=' . $file);

if (!is_file($path)) {
    fail('missing ' . $file . '; upload patch to OpenCart public_html root');
}

$content = file_get_contents($path);
if ($content === false) {
    fail('cannot read ' . $file);
}

if (strpos($content, $marker) !== false) {
    out('already_applied=yes');
    out('changed=none');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

$old = <<<'TWIG'
$('#form-product').on('submit', function(e) {
    e.preventDefault();

    $.ajax({
        url: 'index.php?route=checkout/cart.add&language={{ language }}',
        type: 'post',
        data: $('#form-product').serialize(),
        dataType: 'json',
        contentType: 'application/x-www-form-urlencoded',
        cache: false,
        processData: false,
        beforeSend: function() {
            $('#button-cart').button('loading');
        },
        complete: function() {
            $('#button-cart').button('reset');
        },
        success: function(json) {
            console.log(json);

            $('#form-product').find('.is-invalid').removeClass('is-invalid');
            $('#form-product').find('.invalid-feedback').removeClass('d-block');

            if (json['error']) {
                for (key in json['error']) {
                    $('#input-' + key.replaceAll('_', '-')).addClass('is-invalid').find('.form-control, .form-select, .form-check-input, .form-check-label').addClass('is-invalid');
                    $('#error-' + key.replaceAll('_', '-')).html(json['error'][key]).addClass('d-block');
                }
            }

            if (json['success']) {
                $('#alert').prepend('<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');

                $('#cart').load('index.php?route=common/cart.info&language={{ language }}');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    });
});
TWIG;

$new = <<<'TWIG'
// ST-2a.9: cold-session add-to-cart UX guard.
$('#form-product').on('submit', function(e) {
    e.preventDefault();

    var form = $(this);
    var button = $('#button-cart');

    if (form.data('bsCartAddPending')) {
        return;
    }

    form.data('bsCartAddPending', true);

    if (button.data('bsOriginalHtml') === undefined) {
        button.data('bsOriginalHtml', button.html());
    }

    function resetCartButton() {
        form.data('bsCartAddPending', false);
        button.prop('disabled', false).removeClass('disabled');
        button.button('reset');

        if (button.data('bsOriginalHtml') !== undefined) {
            button.html(button.data('bsOriginalHtml'));
        }
    }

    function showCartRetry(message) {
        $('#alert .bs-cart-add-timeout').remove();
        $('#alert').prepend('<div class="alert alert-warning alert-dismissible bs-cart-add-timeout"><i class="fa-solid fa-circle-exclamation"></i> ' + message + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');
    }

    $.ajax({
        url: 'index.php?route=checkout/cart.add&language={{ language }}',
        type: 'post',
        timeout: 12000,
        data: form.serialize(),
        dataType: 'json',
        contentType: 'application/x-www-form-urlencoded',
        cache: false,
        processData: false,
        beforeSend: function() {
            button.button('loading');
            button.prop('disabled', true).addClass('disabled').html('Додаємо у кошик...');
        },
        complete: function() {
            resetCartButton();
        },
        success: function(json) {
            console.log(json);

            $('#form-product').find('.is-invalid').removeClass('is-invalid');
            $('#form-product').find('.invalid-feedback').removeClass('d-block');

            if (json['error']) {
                for (key in json['error']) {
                    $('#input-' + key.replaceAll('_', '-')).addClass('is-invalid').find('.form-control, .form-select, .form-check-input, .form-check-label').addClass('is-invalid');
                    $('#error-' + key.replaceAll('_', '-')).html(json['error'][key]).addClass('d-block');
                }
            }

            if (json['success']) {
                $('#alert .bs-cart-add-timeout').remove();
                $('#alert').prepend('<div class="alert alert-success alert-dismissible"><i class="fa-solid fa-circle-check"></i> ' + json['success'] + ' <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>');

                $('#cart').load('index.php?route=common/cart.info&language={{ language }}');
            }
        },
        error: function(xhr, ajaxOptions, thrownError) {
            var message = ajaxOptions === 'timeout'
                ? 'Додавання зайняло більше часу, ніж очікувалось. Перевірте кошик або спробуйте ще раз.'
                : 'Не вдалося додати товар у кошик. Спробуйте ще раз.';

            showCartRetry(message);
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        }
    });
});
TWIG;

$count = substr_count($content, $old);
if ($count !== 1) {
    fail('pre-check failed: expected 1 product add-to-cart ajax block, got ' . $count);
}

$backupFile = $backupDir . '/' . $file;
if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
    fail('cannot create backup dir');
}
if (!copy($path, $backupFile)) {
    fail('cannot backup ' . $file);
}
out('backup=' . str_replace($root . '/', '', $backupFile));

$patched = str_replace($old, $new, $content);
if (file_put_contents($path, $patched) === false) {
    fail('cannot write ' . $file);
}
out('changed=' . $file);
out('php_modified=none');
out('rollback=restore ' . str_replace($root . '/', '', $backupFile));
out('done=ok');

@unlink(__FILE__);
out('self_delete=' . (file_exists(__FILE__) ? 'failed' : 'ok'));
