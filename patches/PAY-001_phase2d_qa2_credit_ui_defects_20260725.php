<?php
declare(strict_types=1);

/*
 * PAY-001 Phase 2d QA2 — receiver phone display and preferred credit term.
 *
 * Scope:
 * - catalog/view/template/checkout/payment_method.twig only
 * - no DB/settings/session/order-write/API/Nova Poshta changes
 *
 * Rollback:
 * restore the target from the printed _patch_backups directory, then clear
 * OpenCart template/cache files with the owner command supplied with this patch.
 */

$patchId = pathinfo(__FILE__, PATHINFO_FILENAME);
$root = getcwd();
$relative = 'catalog/view/template/checkout/payment_method.twig';
$target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
$oldSha256 = '8d3010310f3b2efef48f58a7f7716acd75ffb4b629c762ffb2ed67a1813279f9';
$newSha256 = 'd62fd2269d892f7c735cd815f21ab9cf949864d389775c0df42cb44509d753e5';

function pay001Qa2Fail(string $message, int $code = 1): never {
    fwrite(STDERR, 'error=' . $message . PHP_EOL);
    exit($code);
}

function pay001Qa2Lint(string $file): void {
    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $code);

    if ($code !== 0) {
        pay001Qa2Fail('php_l_failed file=' . $file . ' output=' . implode(' | ', $output));
    }
}

pay001Qa2Lint(__FILE__);

if (!is_file($root . DIRECTORY_SEPARATOR . 'config.php')) {
    pay001Qa2Fail('run_from_opencart_root_config_missing');
}

if (!is_file($target)) {
    pay001Qa2Fail('target_missing file=' . $relative);
}

$currentSha256 = hash_file('sha256', $target);

if (hash_equals($newSha256, $currentSha256)) {
    echo 'cwd=' . $root . PHP_EOL;
    echo 'time=' . date(DATE_ATOM) . PHP_EOL;
    echo 'already_applied=yes' . PHP_EOL;
    @unlink(__FILE__);
    exit(0);
}

if (!hash_equals($oldSha256, $currentSha256)) {
    pay001Qa2Fail(
        'source_sha256_mismatch file=' . $relative .
        ' expected=' . $oldSha256 .
        ' actual=' . $currentSha256
    );
}

$source = file_get_contents($target);

if ($source === false) {
    pay001Qa2Fail('read_failed file=' . $relative);
}

$replacements = [
    [
        'name' => 'preferred_credit_code',
        'old' => <<<'TWIG'
          monoOptions.sort(function(left, right) { return left.count - right.count; });
          if (monoOptions.length) {
            options.push({
              code: monoOptions[0].code,
              label: 'Оплатити частинами',
              id: 'mono_chast',
              pay001Credit: true,
              preferred: Number(group.pay001_preferred) || monoOptions[0].count,
              fromModal: !!group.pay001_from_modal,
              total: Number(group.pay001_total) || 0,
              monoOptions: monoOptions
            });
TWIG,
        'new' => <<<'TWIG'
          monoOptions.sort(function(left, right) { return left.count - right.count; });
          if (monoOptions.length) {
            var preferred = Number(group.pay001_preferred) || monoOptions[0].count;
            var preferredOption = monoOptions[0];
            $.each(monoOptions, function(_, item) {
              if (item.count === preferred) {
                preferredOption = item;
                return false;
              }
            });
            options.push({
              code: preferredOption.code,
              label: 'Оплатити частинами',
              id: 'mono_chast',
              pay001Credit: true,
              preferred: preferredOption.count,
              fromModal: !!group.pay001_from_modal,
              total: Number(group.pay001_total) || 0,
              monoOptions: monoOptions
            });
TWIG
    ],
    [
        'name' => 'receiver_phone_helpers',
        'old' => <<<'TWIG'
    return '';
  }
  function pay001Drawer(option, selectedCode) {
TWIG,
        'new' => <<<'TWIG'
    return '';
  }
  function pay001PhoneValue() {
    var $receiverPhone = $('#bs-co-recv-telephone').first();
    if ($receiverPhone.length) {
      return String($receiverPhone.val() || '').trim();
    }
    var $standardPhone = $('#input-telephone').first();
    if ($standardPhone.length) {
      return String($standardPhone.val() || '').trim();
    }
    return String($('#checkout-checkout').attr('data-bs-receiver-telephone') || '').trim();
  }
  function pay001SyncPhone(scope) {
    var phone = pay001PhoneValue() || 'вказаний у формі';
    $(scope || document).find('[data-pay001-phone]').text(phone);
  }
  function pay001Drawer(option, selectedCode) {
TWIG
    ],
    [
        'name' => 'remove_wrong_phone_selector',
        'old' => <<<'TWIG'
    var phone = $('input[name="telephone"]').first().val() || 'вказаний у формі';
TWIG
        . "\n",
        'new' => ''
    ],
    [
        'name' => 'drawer_phone_sync',
        'old' => <<<'TWIG'
    $drawer.find('[data-pay001-phone]').text(phone);
TWIG,
        'new' => <<<'TWIG'
    pay001SyncPhone($drawer);
TWIG
    ],
    [
        'name' => 'live_phone_sync',
        'old' => <<<'TWIG'
    savePayment(code, 'Оплатити частинами', false, null, window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined);
  });

  $(document).on('change', 'input[name="agree"]', function() {
TWIG,
        'new' => <<<'TWIG'
    savePayment(code, 'Оплатити частинами', false, null, window.bsCheckoutState ? window.bsCheckoutState.currentRevision() : undefined);
  });
  $(document).on('input change', '#bs-co-recv-telephone, #input-telephone', function() {
    pay001SyncPhone(document);
  });

  $(document).on('change', 'input[name="agree"]', function() {
TWIG
    ]
];

foreach ($replacements as $replacement) {
    $count = substr_count($source, $replacement['old']);

    if ($count !== 1) {
        pay001Qa2Fail(
            'anchor_count name=' . $replacement['name'] .
            ' expected=1 actual=' . $count
        );
    }
}

$patched = $source;

foreach ($replacements as $replacement) {
    $patched = str_replace($replacement['old'], $replacement['new'], $patched, $count);

    if ($count !== 1) {
        pay001Qa2Fail(
            'replace_count name=' . $replacement['name'] .
            ' expected=1 actual=' . $count
        );
    }
}

$generatedSha256 = hash('sha256', $patched);

if (!hash_equals($newSha256, $generatedSha256)) {
    pay001Qa2Fail(
        'generated_sha256_mismatch file=' . $relative .
        ' expected=' . $newSha256 .
        ' actual=' . $generatedSha256
    );
}

$timestamp = date('Ymd-His');
$backupRoot = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patchId . '-' . $timestamp;
$backupFile = $backupRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

if (!is_dir(dirname($backupFile)) && !mkdir(dirname($backupFile), 0755, true) && !is_dir(dirname($backupFile))) {
    pay001Qa2Fail('backup_dir_create_failed path=' . dirname($backupFile));
}

if (!copy($target, $backupFile)) {
    pay001Qa2Fail('backup_failed file=' . $relative);
}

$permissions = fileperms($target);
$written = false;

echo 'cwd=' . $root . PHP_EOL;
echo 'time=' . date(DATE_ATOM) . PHP_EOL;
echo 'backup=' . $backupRoot . PHP_EOL;

try {
    if (file_put_contents($target, $patched, LOCK_EX) !== strlen($patched)) {
        throw new RuntimeException('write_failed file=' . $relative);
    }

    $written = true;

    if ($permissions !== false) {
        @chmod($target, $permissions & 0777);
    }

    if (!hash_equals($newSha256, hash_file('sha256', $target))) {
        throw new RuntimeException('post_write_sha256_mismatch file=' . $relative);
    }
} catch (Throwable $error) {
    if ($written || is_file($backupFile)) {
        @copy($backupFile, $target);

        if ($permissions !== false) {
            @chmod($target, $permissions & 0777);
        }
    }

    pay001Qa2Fail($error->getMessage() . ' restored=yes');
}

echo 'changed_file=' . $relative . PHP_EOL;
echo 'php_l=ok file=' . basename(__FILE__) . PHP_EOL;
echo 'done=ok' . PHP_EOL;

@unlink(__FILE__);
