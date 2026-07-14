<?php
/**
 * ACC-001 — show exactly one account menu per breakpoint and add logout to
 * the mobile menu.
 *
 * No DB changes. Rollback: restore the backup path printed by this runner.
 */
declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$dryRun = in_array('--dry-run', $argv, true);
$targets = [
    'catalog/controller/account/account.php',
    'catalog/view/template/account/account.twig',
];
$marker = 'ACC-001: mobile account menu logout and breakpoint parity';
$files = [];
$backupDir = '';

function fail(string $message): void {
    throw new RuntimeException($message);
}

function eol(string $text, string $lineEnding): string {
    return str_replace("\n", $lineEnding, $text);
}

function replaceOnce(string $source, string $find, string $replace, string $path): string {
    $lineEnding = str_contains($source, "\r\n") ? "\r\n" : "\n";
    $find = eol($find, $lineEnding);
    $replace = eol($replace, $lineEnding);

    if (substr_count($source, $find) !== 1) {
        fail('anchor_count_error path=' . $path . ' expected=1');
    }

    return str_replace($find, $replace, $source);
}

function restoreFiles(array $files, string $backupDir): void {
    if ($backupDir === '') {
        return;
    }

    foreach ($files as $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $file['relative'];

        if (is_file($backup)) {
            @copy($backup, $file['path']);
        }
    }
}

function lintPhp(string $path): void {
    if (!function_exists('exec')) {
        fail('php_l_unavailable exec_disabled=yes');
    }

    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

    if ($status !== 0) {
        fail('php_l_failed path=' . $path . ' output=' . implode(' | ', $output));
    }
}

try {
    foreach ($targets as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . $relative;

        if (!is_file($path)) {
            fail('target_missing path=' . $relative);
        }

        $source = file_get_contents($path);

        if ($source === false) {
            fail('read_failed path=' . $relative);
        }

        $files[$relative] = ['relative' => $relative, 'path' => $path, 'source' => $source, 'new' => $source];
    }

    $markers = 0;
    foreach ($files as $file) {
        if (str_contains($file['source'], $marker)) {
            $markers++;
        }
    }

    if ($markers === count($files)) {
        echo "already_applied=yes patch={$patch}\n";
        @unlink(__FILE__);
        exit(0);
    }

    if ($markers !== 0) {
        fail('partial_marker_state=yes; restore both target files from the same backup before retrying');
    }

    $controller = replaceOnce(
        $files['catalog/controller/account/account.php']['new'],
        <<<'OLD'
		$data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		$data['subscription'] = $this->url->link('account/subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
OLD,
        <<<'NEW'
		$data['order'] = $this->url->link('account/order', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
		// ACC-001: mobile account menu logout and breakpoint parity.
		$data['logout'] = $this->url->link('account/logout', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);

		$data['subscription'] = $this->url->link('account/subscription', 'language=' . $this->config->get('config_language') . '&customer_token=' . $this->session->data['customer_token']);
NEW,
        'catalog/controller/account/account.php'
    );
    $files['catalog/controller/account/account.php']['new'] = $controller;

    $twig = replaceOnce(
        $files['catalog/view/template/account/account.twig']['new'],
        <<<'OLD'
      <div class="account-mobile-menu d-lg-none">
OLD,
        <<<'NEW'
      {# ACC-001: mobile account menu logout and breakpoint parity. #}
      <div class="account-mobile-menu d-md-none">
NEW,
        'catalog/view/template/account/account.twig'
    );

    $twig = replaceOnce(
        $twig,
        <<<'OLD'
        <a href="{{ order }}" class="account-mobile-menu-item">
          <i class="fa-solid fa-cart-shopping"></i>
          <span>{{ text_order }}</span>
        </a>

        {% if affiliate %}
OLD,
        <<<'OLD'
        <a href="{{ order }}" class="account-mobile-menu-item">
          <i class="fa-solid fa-cart-shopping"></i>
          <span>{{ text_order }}</span>
        </a>

        <a href="{{ logout }}" class="account-mobile-menu-item">
          <i class="fa-solid fa-right-from-bracket"></i>
          <span>{{ text_logout }}</span>
        </a>

        {% if affiliate %}
OLD,
        'catalog/view/template/account/account.twig'
    );
    $files['catalog/view/template/account/account.twig']['new'] = $twig;

    foreach ($files as $relative => $file) {
        if (!str_contains($file['new'], $marker)) {
            fail('postcheck_marker_missing path=' . $relative);
        }
    }

    if ($dryRun) {
        echo "dry_run=ok patch={$patch} files=" . count($files) . "\n";
        exit(0);
    }

    $backupDir = $root . DIRECTORY_SEPARATOR . '_patch_backups' . DIRECTORY_SEPARATOR . $patch . '-' . date('Ymd_His');
    foreach ($files as $file) {
        $backup = $backupDir . DIRECTORY_SEPARATOR . $file['relative'];
        $dir = dirname($backup);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            fail('backup_dir_create_failed path=' . $dir);
        }

        if (!copy($file['path'], $backup)) {
            fail('backup_failed path=' . $file['relative']);
        }
    }

    foreach ($files as $file) {
        if (file_put_contents($file['path'], $file['new']) === false) {
            fail('write_failed path=' . $file['relative']);
        }
    }

    lintPhp($files['catalog/controller/account/account.php']['path']);

    foreach ($files as $file) {
        $written = file_get_contents($file['path']);
        if ($written === false || !str_contains($written, $marker)) {
            fail('postwrite_verify_failed path=' . $file['relative']);
        }
    }

    echo "done=ok patch={$patch} files=" . count($files) . " php_l=ok backup={$backupDir}\n";
    @unlink(__FILE__);
} catch (Throwable $e) {
    restoreFiles($files, $backupDir);
    echo 'done=error patch=' . $patch . ' message=' . $e->getMessage() . "\n";
    exit(1);
}
