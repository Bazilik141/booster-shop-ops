<?php
/**
 * RD-25 WP1 F6: featured passes its already-loaded original image to product/thumb.
 * Source: RD-25-featured-check-20261010.tar.gz; one target, two added lines only.
 * PHP 8.0 compatible. No DB writes. Existing getProduct/resize/commerce logic retained.
 * Companion to the approved RD-25 WP1 r3; either apply order is supported.
 * Original empty-image fallback remains; nonempty originals avoid the duplicate lookup.
 * Usage: php RD-25-WP1_featured-image-payload_20261010.php [--dry-run]
 * Rollback: re-upload the same runner, pass --rollback=_patch_backups/<printed-directory>.
 * File/hash/anchor preflight, verified backup, candidate lint before target write,
 * post-write lint/readback, restoration on failure, idempotency, self-delete on success.
 * PHP controller only: no Twig, CSS, metadata-version or asset-token changes.
 */
declare(strict_types=1);

const RD25_MARKER = 'RD-25-WP1-F6 20261010';

function rd25Out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function rd25Fail(string $message): void { throw new RuntimeException($message); }
function rd25Read(string $path): string {
	$content = @file_get_contents($path);
	if ($content === false) { rd25Fail('file_read_failed:' . $path); }
	return $content;
}
function rd25Normalize(string $path, string $content): string { return $content; }
function rd25Mkdir(string $path): void {
	if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) { rd25Fail('mkdir_failed:' . $path); }
}
function rd25Write(string $path, string $content): void {
	$mode = is_file($path) ? (fileperms($path) & 0777) : 0644;
	$temp = @tempnam(dirname($path), '.rd25-');
	if (!$temp) { rd25Fail('tempfile_failed:' . $path); }
	if (@file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !@chmod($temp, $mode) || !@rename($temp, $path)) {
		@unlink($temp);
		rd25Fail('file_write_failed:' . $path);
	}
}
function rd25Lint(string $path): void {
	if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') { return; }
	$output = [];
	$code = 0;
	exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
	if ($code !== 0) { rd25Fail('php_lint_failed:' . $path . ':' . implode(' ', $output)); }
	rd25Out('php_lint', 'ok:' . $path);
}
function rd25Finish(): void {
	rd25Out('done', 'ok');
	rd25Out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed');
}

/* Reviewed transformations, kept readable for independent review. */
$targets = [
	'extension/opencart/catalog/controller/module/featured.php' => [
		'before_sha256' => 'aa9bd528b74256883224d5406366edc5d171000d7fdd8ae61006068398b9f839',
		'after_sha256' => 'b981a76485faf267c9362240dc50f972adb15379dffaf3a0ce52a93d3e1deaae',
		'replacements' => [
			['before' => <<<'RD25_F6_BEFORE'
				$product_data = [
					'product_id'  => $product['product_id'],
RD25_F6_BEFORE,
'after' => <<<'RD25_F6_AFTER'
				// RD-25-WP1-F6 20261010: reuse the loaded original image in product/thumb.
				$product_data = [
					'product_id'  => $product['product_id'],
					'image'       => $product['image'],
RD25_F6_AFTER],
		]
	],
];

$originals = [];
$written = [];
$backup = '';
$lock = null;

try {
	if (PHP_SAPI !== 'cli') { rd25Fail('cli_only'); }
	if (!is_callable('exec') || !is_file(PHP_BINARY)) { rd25Fail('php_lint_unavailable'); }
	$root = realpath(getcwd());
	if (!$root) { rd25Fail('cwd_unavailable'); }
	rd25Out('cwd', $root);
	rd25Out('time', gmdate('c'));
	$arguments = array_slice($argv, 1);
	$dry_run = in_array('--dry-run', $arguments, true);
	$rollback = '';
	foreach ($arguments as $argument) {
		if (strpos($argument, '--rollback=') === 0) { $rollback = substr($argument, 11); }
		elseif ($argument !== '--dry-run') { rd25Fail('unknown_argument'); }
	}
	if ($rollback !== '' && $dry_run) { rd25Fail('rollback_dry_run_combination_not_supported'); }
	$lock = @fopen(__FILE__, 'r');
	if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { rd25Fail('another_runner_is_active'); }
	foreach ($targets as $relative => $target) {
		$path = $root . '/' . $relative;
		if (!is_file($path) || !is_writable($path) || !is_writable(dirname($path)) || realpath($path) !== $path && str_replace('\\', '/', (string)realpath($path)) !== str_replace('\\', '/', $path)) {
			rd25Fail('target_missing_unwritable_or_symlink:' . $relative);
		}
		$originals[$relative] = rd25Read($path);
	}

	if ($rollback !== '') {
		$base = realpath($root . '/_patch_backups');
		$backup_path = realpath($root . '/' . $rollback);
		if (!$base || !$backup_path || strpos(str_replace('\\', '/', $backup_path), str_replace('\\', '/', $base) . '/RD-25-WP1_featured-image-payload_20261010-') !== 0) {
			rd25Fail('rollback_directory_outside_task_backup');
		}
		$receipt = json_decode(rd25Read($backup_path . '/manifest.json'), true);
		if (!is_array($receipt) || ($receipt['task'] ?? '') !== RD25_MARKER || ($receipt['state'] ?? '') !== 'APPLIED' || array_keys($receipt['files'] ?? []) !== array_keys($targets)) {
			rd25Fail('rollback_manifest_invalid');
		}
		$restore = [];
		foreach ($targets as $relative => $target) {
			$entry = $receipt['files'][$relative];
			$restore[$relative] = rd25Read($backup_path . '/original/' . $relative);
			if (hash('sha256', $originals[$relative]) !== ($entry['after_sha256'] ?? '') || hash('sha256', $restore[$relative]) !== ($entry['before_sha256'] ?? '')) {
				rd25Fail('rollback_source_changed:' . $relative);
			}
			if (hash('sha256', rd25Normalize($relative, $restore[$relative])) !== $target['before_sha256']) { rd25Fail('rollback_backup_not_from_this_source:' . $relative); }
		}
		foreach ($restore as $relative => $content) {
			rd25Write($root . '/' . $relative, $content);
			$written[] = $relative;
			rd25Lint($root . '/' . $relative);
			if (rd25Read($root . '/' . $relative) !== $content) { rd25Fail('rollback_readback_failed:' . $relative); }
			rd25Out('restored_file', $relative);
		}
		$receipt['state'] = 'ROLLED_BACK';
		rd25Write($backup_path . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('rollback', 'ok'); rd25Finish(); exit(0);
	}

	$marked = 0;
	foreach ($originals as $content) { $marked += strpos($content, RD25_MARKER) !== false ? 1 : 0; }
	if ($marked !== 0 && $marked !== count($targets)) { rd25Fail('partial_marker_state'); }
	if ($marked === count($targets)) {
		foreach ($targets as $relative => $target) {
			foreach ($target['replacements'] as $replace) {
				if (substr_count(rd25Normalize($relative, $originals[$relative]), $replace['after']) !== 1) { rd25Fail('applied_content_changed:' . $relative); }
			}
		}
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('already_applied', 'yes');
		if ($dry_run) { rd25Out('done', 'ok'); } else { rd25Finish(); }
		exit(0);
	}

	$candidates = [];
	foreach ($targets as $relative => $target) {
		$normalized = rd25Normalize($relative, $originals[$relative]);
		if (hash('sha256', $normalized) !== $target['before_sha256']) { rd25Fail('source_sha256_mismatch:' . $relative); }
		$candidate = $normalized;
		foreach ($target['replacements'] as $replace) {
			if (substr_count($candidate, $replace['before']) !== 1) { rd25Fail('anchor_count_invalid:' . $relative); }
			$candidate = str_replace($replace['before'], $replace['after'], $candidate);
		}
		if (hash('sha256', $candidate) !== $target['after_sha256']) { rd25Fail('candidate_sha256_mismatch:' . $relative); }
		$candidates[$relative] = $candidate;
		rd25Out('preflight_file', $relative);
	}
	if ($dry_run) {
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('dry_run', 'yes'); rd25Out('target_writes', '0'); rd25Out('done', 'ok'); exit(0);
	}

	$backup = $root . '/_patch_backups/RD-25-WP1_featured-image-payload_20261010-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
	$receipt = ['task' => RD25_MARKER, 'state' => 'PREPARED', 'created_at' => gmdate('c'), 'files' => []];
	foreach ($targets as $relative => $target) {
		rd25Mkdir(dirname($backup . '/original/' . $relative));
		rd25Write($backup . '/original/' . $relative, $originals[$relative]);
		if (rd25Read($backup . '/original/' . $relative) !== $originals[$relative]) { rd25Fail('backup_verify_failed:' . $relative); }
		rd25Mkdir(dirname($backup . '/candidate/' . $relative));
		rd25Write($backup . '/candidate/' . $relative, $candidates[$relative]);
		rd25Lint($backup . '/candidate/' . $relative);
		$receipt['files'][$relative] = ['before_sha256' => hash('sha256', $originals[$relative]), 'after_sha256' => hash('sha256', $candidates[$relative])];
	}
	rd25Write($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
	rd25Out('backup', $backup);
	foreach ($originals as $relative => $content) {
		if (rd25Read($root . '/' . $relative) !== $content) { rd25Fail('source_changed_after_preflight:' . $relative); }
	}
	foreach ($candidates as $relative => $content) {
		rd25Write($root . '/' . $relative, $content);
		$written[] = $relative;
		rd25Lint($root . '/' . $relative);
		if (rd25Read($root . '/' . $relative) !== $content) { rd25Fail('write_readback_failed:' . $relative); }
		rd25Out('changed_file', $relative);
	}
	$receipt['state'] = 'APPLIED';
	rd25Write($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
	flock($lock, LOCK_UN); fclose($lock); $lock = null;
	rd25Out('changed_files', (string)count($targets)); rd25Finish();
} catch (Throwable $error) {
	$restore_failed = false;
	foreach (array_reverse(array_unique($written)) as $relative) {
		try {
			rd25Write(getcwd() . '/' . $relative, $originals[$relative]);
			if (rd25Read(getcwd() . '/' . $relative) !== $originals[$relative]) { rd25Fail('restore_readback_failed:' . $relative); }
			rd25Lint(getcwd() . '/' . $relative);
			rd25Out('restored_after_failure', $relative);
		}
		catch (Throwable $restore_error) { $restore_failed = true; rd25Out('restore_error', $relative); }
	}
	if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
	rd25Out('error', $error->getMessage());
	if ($backup !== '') { rd25Out('backup', $backup); }
	rd25Out('restore', $restore_failed ? 'FAILED_OWNER_ACTION_REQUIRED' : ($written ? 'ok' : 'not_needed'));
	exit(1);
}
