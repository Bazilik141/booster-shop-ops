<?php
declare(strict_types=1);

/**
 * CHECKOUT-001 Phase 1.2 — persist mandatory oferta agreement.
 *
 * Root cause:
 * payment_method.twig posts comment + agree to payment_method.comment, but the
 * controller persisted only comment. confirm.confirm therefore failed its
 * mandatory session.agree gate and returned no payment #button-confirm.
 *
 * Database changes: none.
 */

const CHECKOUT00112_PATCH_ID = 'CHECKOUT-001_phase1-2_agree-session-hotfix_20260705';
const CHECKOUT00112_TARGET = 'catalog/controller/checkout/payment_method.php';
const CHECKOUT00112_EXPECTED_SHA256 = 'b1b9be5e7fd45d37dba38fc5af7a74cb3df99475354e0f41efe262605525a251';
const CHECKOUT00112_MARKER = 'CHECKOUT-001 Phase 1.2: persist mandatory oferta agreement.';

function checkout00112_out(string $key, string $value): void {
    echo $key . '=' . $value . PHP_EOL;
}

function checkout00112_fail(string $message): void {
    throw new RuntimeException($message);
}

function checkout00112_path(string $root, string $relative): string {
    return rtrim($root, "/\\") . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function checkout00112_lint(string $path, string $label): void {
    if (!function_exists('exec')) {
        checkout00112_fail('php_lint_unavailable:exec_disabled:' . $label);
    }

    $output = [];
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    checkout00112_out('php_lint_' . $label, 'exit=' . $code . ';output=' . implode(' | ', $output));

    if ($code !== 0) {
        checkout00112_fail('php_lint_failed:' . $label);
    }
}

$root = getenv('BS_PATCH_ROOT') ?: getcwd();
$target = checkout00112_path($root, CHECKOUT00112_TARGET);
$backup = null;
$written = false;

try {
    checkout00112_out('patch', CHECKOUT00112_PATCH_ID);
    checkout00112_out('cwd', getcwd());
    checkout00112_out('root', $root);
    checkout00112_out('time', date('c'));
    checkout00112_out('scope', 'persist oferta agreement before confirm.confirm');
    checkout00112_out('db_schema_changes', 'none');
    checkout00112_out('account_creation_changes', 'none');
    checkout00112_out('payment_extension_changes', 'none');

    if (!is_file($target)) {
        checkout00112_fail('target_missing:' . CHECKOUT00112_TARGET);
    }

    $content = file_get_contents($target);

    if ($content === false) {
        checkout00112_fail('read_failed:' . CHECKOUT00112_TARGET);
    }

    if (strpos($content, CHECKOUT00112_MARKER) !== false) {
        checkout00112_lint(__FILE__, 'patch_self');
        checkout00112_lint($target, 'payment_controller');
        checkout00112_out('already_applied', 'yes');
        checkout00112_out('changed_files', '0');
        checkout00112_out('done', 'ok');
        @unlink(__FILE__);
        exit(0);
    }

    $actualHash = hash('sha256', $content);
    checkout00112_out('source_sha256', CHECKOUT00112_TARGET . ':' . $actualHash);

    if (!hash_equals(CHECKOUT00112_EXPECTED_SHA256, $actualHash)) {
        checkout00112_fail(
            'live_sha256_mismatch:' . CHECKOUT00112_TARGET .
            ':expected=' . CHECKOUT00112_EXPECTED_SHA256 .
            ':actual=' . $actualHash
        );
    }

    checkout00112_lint(__FILE__, 'patch_self');

    $old = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		if (array_key_exists('create_account_opt_in', $this->request->post)) {
PHP;

    $new = <<<'PHP'
		// ST-2b.1: comment can be saved in session before deferred order exists.
		$this->session->data['comment'] = $comment;

		// CHECKOUT-001 Phase 1.2: persist mandatory oferta agreement.
		if (array_key_exists('agree', $this->request->post)) {
			if ((string)$this->request->post['agree'] === '1') {
				$this->session->data['agree'] = 1;
			} else {
				unset($this->session->data['agree']);
			}
		}

		if (array_key_exists('create_account_opt_in', $this->request->post)) {
PHP;

    $count = substr_count($content, $old);

    if ($count !== 1) {
        checkout00112_fail('anchor_count_mismatch:comment_agree_persistence:expected=1:actual=' . $count);
    }

    $patched = str_replace($old, $new, $content);

    if (substr_count($patched, CHECKOUT00112_MARKER) !== 1) {
        checkout00112_fail('postbuild_marker_count_changed');
    }

    $backupRoot = checkout00112_path(
        $root,
        '_patch_backups/' . CHECKOUT00112_PATCH_ID . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4))
    );
    $backup = checkout00112_path($backupRoot, CHECKOUT00112_TARGET);
    $backupDirectory = dirname($backup);

    if (!is_dir($backupDirectory) && !mkdir($backupDirectory, 0755, true) && !is_dir($backupDirectory)) {
        checkout00112_fail('backup_mkdir_failed:' . CHECKOUT00112_TARGET);
    }

    if (!copy($target, $backup)) {
        checkout00112_fail('backup_copy_failed:' . CHECKOUT00112_TARGET);
    }

    checkout00112_out('backup', CHECKOUT00112_TARGET . ' -> ' . $backup);

    $bytes = file_put_contents($target, $patched, LOCK_EX);

    if ($bytes !== strlen($patched)) {
        checkout00112_fail('write_failed:' . CHECKOUT00112_TARGET);
    }

    $written = true;
    checkout00112_lint($target, 'payment_controller');

    $final = file_get_contents($target);

    if ($final === false || substr_count($final, CHECKOUT00112_MARKER) !== 1) {
        checkout00112_fail('postwrite_marker_failed:' . CHECKOUT00112_TARGET);
    }

    checkout00112_out('already_applied', 'no');
    checkout00112_out('changed_files', '1');
    checkout00112_out('changed_file', CHECKOUT00112_TARGET);
    checkout00112_out('agree_checked', 'session.agree=1');
    checkout00112_out('agree_unchecked', 'session.agree=unset');
    checkout00112_out('rollback_code', 'restore ' . CHECKOUT00112_TARGET . ' from ' . $backup . ';then clear template cache');
    checkout00112_out('done', 'ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    checkout00112_out('error', $error->getMessage());

    if ($written && $backup && is_file($backup)) {
        $restored = copy($backup, $target);
        checkout00112_out('restore_on_fail', $restored ? 'ok' : 'failed');
    } else {
        checkout00112_out('restore_on_fail', 'not_needed');
    }

    checkout00112_out('done', 'failed');
    exit(1);
}
