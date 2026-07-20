<?php
// RD-13.1A_guest_np_payload_sync_20260711.php
//
// Scope: stock guest checkout Nova Poshta autosave only.
// Changes catalog/view/template/checkout/checkout.twig so every duplicate
// form-associated shipping input receives the same synced NP value before a
// register.save payload is serialized. No controller, order, DB, payment,
// Hutko, fiscalization, customer-profile, or address-book code is changed.
//
// Rollback: restore the single backup file printed by this runner.

declare(strict_types=1);

$root = __DIR__;
$dryRun = in_array('--dry-run', $argv ?? [], true);
$stamp = date('Ymd_His');
$backupRoot = $root . '/_patch_backups/RD-13.1A_guest_np_payload_sync_20260711_' . $stamp;
$twigRelative = 'catalog/view/template/checkout/checkout.twig';
$twigPath = $root . '/' . $twigRelative;

function rd13a_log(string $message): void {
    echo '[RD-13.1A] ' . $message . PHP_EOL;
}

function rd13a_fail(string $message): void {
    rd13a_log('error: ' . $message);
    exit(1);
}

function rd13a_read(string $path): string {
    if (!is_file($path)) {
        rd13a_fail('missing file: ' . $path);
    }

    $content = file_get_contents($path);

    if ($content === false) {
        rd13a_fail('cannot read: ' . $path);
    }

    return $content;
}

function rd13a_write(string $path, string $content): void {
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        rd13a_fail('cannot write: ' . $path);
    }
}

function rd13a_backup(string $root, string $backupRoot, string $relative): string {
    $source = $root . '/' . $relative;
    $target = $backupRoot . '/' . $relative;
    $targetDir = dirname($target);

    if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
        rd13a_fail('cannot create backup directory: ' . $targetDir);
    }

    if (!copy($source, $target)) {
        rd13a_fail('cannot back up: ' . $relative);
    }

    return $target;
}

function rd13a_count(string $content, string $needle): int {
    return substr_count($content, $needle);
}

function rd13a_lintSelf(): void {
    $output = [];
    $exitCode = 0;
    exec(escapeshellarg(PHP_BINARY ?: 'php') . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $output, $exitCode);
    rd13a_log('php -l patch: ' . implode(' ', $output));

    if ($exitCode !== 0) {
        rd13a_fail('php -l failed for patch');
    }
}

rd13a_log('cwd=' . $root);
rd13a_log('time=' . date(DATE_ATOM));
rd13a_lintSelf();

$twig = rd13a_read($twigPath);
$markerHelper = 'RD-13.1A: synchronize every duplicate form-associated NP field.';
$markerSubmit = 'RD-13.1A: capture-phase sync runs before any submit serializer.';

$oldHelper = <<<'JS'
  function bsSetLastField(form, name, value) {
    var target = form.find('[name="' + name + '"]').last();

    if (target.length) {
      target.val(value);
    }
  }
JS;

$newHelper = <<<'JS'
  // RD-13.1A: synchronize every duplicate form-associated NP field.
  // jQuery form.serialize() includes controls linked through form="form-register"
  // as well as descendants. Setting one duplicate allowed a later empty value to
  // win in the POST body, so all matching controls must receive the same value.
  function bsSetLastField(form, name, value) {
    if (!form || !form.length) {
      return;
    }

    var formId = form.attr('id');
    var selector = '[name="' + name + '"]';
    var targets = form.find(selector);

    if (formId) {
      targets = targets.add($(selector + '[form="' + formId + '"]'));
    }

    targets.val(value);
  }
JS;

$submitAnchor = "  $(document).on('change', '#input-bs-recipient-other', updateRecipientMode);";
$submitInsert = <<<'JS'
  // RD-13.1A: capture-phase sync runs before any submit serializer.
  document.addEventListener('submit', function(event) {
    if (event.target && event.target.id === 'form-register') {
      window.bsCheckoutSyncNpFields();
    }
  }, true);

  $(document).on('change', '#input-bs-recipient-other', updateRecipientMode);
JS;

$helperAlready = strpos($twig, $markerHelper) !== false;
$submitAlready = strpos($twig, $markerSubmit) !== false;

if ($helperAlready && $submitAlready) {
    rd13a_log('already_applied=yes');
    exit(0);
}

if ($helperAlready || $submitAlready) {
    rd13a_fail('partial RD-13.1A marker state detected; restore the newest backup before retrying');
}

if (rd13a_count($twig, $oldHelper) !== 1) {
    rd13a_fail('helper anchor count is not 1; checkout.twig shape changed');
}

if (rd13a_count($twig, $submitAnchor) !== 1) {
    rd13a_fail('submit anchor count is not 1; checkout.twig shape changed');
}

$updatedTwig = str_replace($oldHelper, $newHelper, $twig);
$updatedTwig = str_replace($submitAnchor, $submitInsert, $updatedTwig);

if (strpos($updatedTwig, $markerHelper) === false || strpos($updatedTwig, $markerSubmit) === false) {
    rd13a_fail('in-memory post-replace verification failed');
}

if ($dryRun) {
    rd13a_log('dry_run=ok');
    rd13a_log('would_change=' . $twigRelative);
    exit(0);
}

$backup = rd13a_backup($root, $backupRoot, $twigRelative);
rd13a_log('backup=' . $backup);
rd13a_write($twigPath, $updatedTwig);

if (strpos(rd13a_read($twigPath), $markerHelper) === false || strpos(rd13a_read($twigPath), $markerSubmit) === false) {
    @copy($backup, $twigPath);
    rd13a_fail('post-write verification failed; restored checkout.twig');
}

rd13a_log('changed=' . $twigRelative);
rd13a_log('done=ok');
rd13a_log('smoke_test=guest NP: select city and warehouse; inspect register.save payload for non-empty shipping_address_1 and shipping_city; then place one test order');

if (@unlink(__FILE__)) {
    rd13a_log('self_delete=ok');
} else {
    rd13a_log('self_delete=skipped');
}
