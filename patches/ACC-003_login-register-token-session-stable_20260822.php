<?php
/**
 * ACC-003 — login and registration silently bounce back to their own form.
 *
 * Root cause: account/login.php and account/register.php mint a fresh
 * single-value form token on EVERY render. Any second render of the page while
 * the form is open (speculative prefetch, second tab, a background tag
 * re-requesting the URL, back/forward) overwrites the token in the session and
 * kills the form the customer is looking at. The submit then fails the token
 * comparison and the controller answers with a bare $json['redirect'] back to
 * the same form, which common.js:127-128 follows — empty form, no message,
 * still logged out.
 *
 * This patch does two things in both controllers:
 *   1. index() — mint the form token only when the session does not already
 *      hold one, so the token stays valid for as long as the customer has the
 *      form open. login()/register() still unset it on success, so the next
 *      render issues a fresh one and the token still rotates once per session.
 *   2. login()/register() — on a genuine token mismatch return
 *      $json['error']['warning'] = error_token instead of $json['redirect'],
 *      so the customer keeps their input and sees a Ukrainian reason.
 *
 * Language keys already exist and are NOT created here:
 *   extension/ukrainian/catalog/language/uk-ua/account/login.php    error_token
 *   extension/ukrainian/catalog/language/uk-ua/account/register.php error_token
 *
 * No DB changes. Nothing to undo but the two files.
 * Rollback: copy login.php and register.php from the backup path printed by
 * this runner back over catalog/controller/account/.
 *
 * Run from ~/public_html:
 *   php ACC-003_login-register-token-session-stable_20260822.php
 * Read-only check first:
 *   php ACC-003_login-register-token-session-stable_20260822.php --dry-run
 */
declare(strict_types=1);

$patch = basename(__FILE__, '.php');
$root = __DIR__;
$dryRun = in_array('--dry-run', $argv, true);
$targets = [
    'catalog/controller/account/login.php',
    'catalog/controller/account/register.php',
];
$marker = 'ACC-003: session-stable form token';
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
    $found = substr_count($source, $find);

    if ($found !== 1) {
        fail('anchor_count_error path=' . $path . ' expected=1 found=' . $found);
    }

    return str_replace($find, $replace, $source);
}

/**
 * Restore every backed-up target and report what could NOT be restored.
 *
 * Returns the relative paths still holding patched content. The caller must
 * name them in the output: C4 requires restore-on-fail with no silent failure,
 * and a suppressed copy() here would leave both controllers modified on
 * production behind a bare `done=error` line.
 */
function restoreFiles(array $files, string $backupDir): array {
    $unrestored = [];

    foreach ($files as $file) {
        $current = is_file($file['path']) ? file_get_contents($file['path']) : false;

        // Already byte-identical to what this runner read at the start, so this
        // target was never written and there is nothing to undo. Compared
        // against the in-memory original rather than assumed from how far the
        // run got, so the report below names only files that really are changed.
        if ($current === $file['source']) {
            continue;
        }

        $backup = $backupDir . DIRECTORY_SEPARATOR . $file['relative'];

        if ($backupDir === '' || !is_file($backup)) {
            $unrestored[] = $file['relative'];
            continue;
        }

        if (!copy($backup, $file['path'])) {
            $unrestored[] = $file['relative'];
            continue;
        }

        // A copy that returns true but does not land is still a failed restore.
        if (file_get_contents($file['path']) !== $file['source']) {
            $unrestored[] = $file['relative'];
        }
    }

    return $unrestored;
}

/**
 * Can this host actually run `php -l`?
 *
 * function_exists() alone is not enough: some SAPIs keep exec() defined while
 * disable_functions forbids it, so the call fails only at runtime.
 */
function phpLintCapability(): array {
    if (!function_exists('exec')) {
        return [false, 'exec_disabled=yes'];
    }

    foreach (explode(',', (string)ini_get('disable_functions')) as $disabled) {
        if (strcasecmp(trim($disabled), 'exec') === 0) {
            return [false, 'exec_disabled=yes'];
        }
    }

    return [true, ''];
}

function lintPhp(string $path): void {
    [$available, $reason] = phpLintCapability();

    if (!$available) {
        fail('php_l_unavailable ' . $reason);
    }

    $output = [];
    $status = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $status);

    if ($status !== 0) {
        fail('php_l_failed path=' . $path . ' output=' . implode(' | ', $output));
    }
}

try {
    // C1 — file exists check, never blind-edit.
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

    // C5 — idempotent marker.
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

    // C4 preflight — refuse before any write if this host cannot run `php -l`.
    // --dry-run reaches this too, so a host with exec disabled is caught on the
    // read-only pass instead of after both controllers have been written.
    // Placed after the idempotency check so an already-applied re-run still
    // reports already_applied=yes rather than a capability error it does not need.
    [$lintAvailable, $lintReason] = phpLintCapability();

    if (!$lintAvailable) {
        fail('php_l_unavailable ' . $lintReason);
    }

    // C2 — anchor pre-check, expected count 1 for each of the four anchors.

    // login.php 1/2 — mint the token only when the session does not hold one.
    $login = replaceOnce(
        $files['catalog/controller/account/login.php']['new'],
        <<<'OLD'
		$this->session->data['login_token'] = oc_token(26);
OLD,
        <<<'NEW'
		// ACC-003: session-stable form token. Minting on every render let any second
		// render of this page (prefetch, second tab, a background request) invalidate
		// the form the customer already has open. login() still unsets the token on
		// success, so it continues to rotate once per session.
		if (empty($this->session->data['login_token'])) {
			$this->session->data['login_token'] = oc_token(26);
		}
NEW,
        'catalog/controller/account/login.php'
    );

    // login.php 2/2 — say why instead of bouncing the customer to a blank form.
    $login = replaceOnce(
        $login,
        <<<'OLD'
		if (!isset($this->request->get['login_token']) || !isset($this->session->data['login_token']) || ($this->request->get['login_token'] != $this->session->data['login_token'])) {
			$json['redirect'] = $this->url->link('account/login', 'language=' . $this->config->get('config_language'), true);
		}
OLD,
        <<<'NEW'
		if (!isset($this->request->get['login_token']) || !isset($this->session->data['login_token']) || ($this->request->get['login_token'] != $this->session->data['login_token'])) {
			// ACC-003: session-stable form token. A bare redirect here reloaded an empty
			// form with no message, and the customer read that as a wrong password.
			$json['error']['warning'] = $this->language->get('error_token');
		}
NEW,
        'catalog/controller/account/login.php'
    );
    $files['catalog/controller/account/login.php']['new'] = $login;

    // register.php 1/2 — mint the token only when the session does not hold one.
    $register = replaceOnce(
        $files['catalog/controller/account/register.php']['new'],
        <<<'OLD'
		// Create form token
		$this->session->data['register_token'] = oc_token(26);
OLD,
        <<<'NEW'
		// Create form token
		// ACC-003: session-stable form token. Minting on every render let any second
		// render of this page (prefetch, second tab, a background request) invalidate
		// the form the customer already has open. register() still unsets the token on
		// success, so it continues to rotate once per session.
		if (empty($this->session->data['register_token'])) {
			$this->session->data['register_token'] = oc_token(26);
		}
NEW,
        'catalog/controller/account/register.php'
    );

    // register.php 2/2 — say why instead of bouncing the customer to a blank form.
    $register = replaceOnce(
        $register,
        <<<'OLD'
		if (!isset($this->request->get['register_token']) || !isset($this->session->data['register_token']) || ($this->session->data['register_token'] != $this->request->get['register_token'])) {
			$json['redirect'] = $this->url->link('account/register', 'language=' . $this->config->get('config_language'), true);
		}
OLD,
        <<<'NEW'
		if (!isset($this->request->get['register_token']) || !isset($this->session->data['register_token']) || ($this->session->data['register_token'] != $this->request->get['register_token'])) {
			// ACC-003: session-stable form token. A bare redirect here reloaded an empty
			// form with no message, and the customer read that as a wrong password.
			$json['error']['warning'] = $this->language->get('error_token');
		}
NEW,
        'catalog/controller/account/register.php'
    );
    $files['catalog/controller/account/register.php']['new'] = $register;

    foreach ($files as $relative => $file) {
        if (!str_contains($file['new'], $marker)) {
            fail('postcheck_marker_missing path=' . $relative);
        }
    }

    if ($dryRun) {
        echo "dry_run=ok patch={$patch} files=" . count($files) . " anchors=4 php_l=available\n";
        exit(0);
    }

    // C3 — backup before any write.
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

    // C4 — php -l gate on both files; the catch block restores on failure.
    foreach ($files as $file) {
        lintPhp($file['path']);
    }

    foreach ($files as $file) {
        $written = file_get_contents($file['path']);

        if ($written === false || !str_contains($written, $marker)) {
            fail('postwrite_verify_failed path=' . $file['relative']);
        }
    }

    echo "done=ok patch={$patch} files=" . count($files) . " anchors=4 php_l=ok backup={$backupDir}\n";

    // C7 — self-delete after success.
    @unlink(__FILE__);
} catch (Throwable $e) {
    $unrestored = restoreFiles($files, $backupDir);

    // The restore itself failed. Say so plainly and name what is still modified
    // on production — do not retry, the owner needs the truth, not a loop.
    if ($unrestored) {
        echo 'restore_failed=yes patch=' . $patch
            . ' files_left_modified=' . implode(',', $unrestored)
            . ' restore_by_hand_from=' . $backupDir . "\n";
    }

    echo 'done=error patch=' . $patch . ' message=' . $e->getMessage() . "\n";
    exit(1);
}
