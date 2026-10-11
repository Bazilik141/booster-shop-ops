<?php
/**
 * RD-25 WP1-FIX-PHOTO: complete product photographs, contain + bottom.
 * Owner: whole product visible; empty space acceptable (2026-10-11).
 * Fresh six-file owner export RD-25-WP1-FIX-photo-check-20261011.tar.gz.
 * Root cause: boostershop-ds.css:276 photo rule uses cover in 1:1 / 4:5 slots.
 * Edit that source rule directly; no override layer, !important, JS or new positioning.
 * Prior selector writer: RD-25-WP1. RD-26 amber preorder rules stay byte-identical.
 * Only CSS + header token are written. Header is hash-pinned with its validated
 * current token normalized; token is read by path prefix and replaced wholesale.
 * CSS marker alone controls repeat: later header tokens are never overwritten.
 * Four PHP/Thumb context files are read/hash-gated before first apply, never written.
 * r2 trim/classification/G1 budget, fit caches, source images and commerce unchanged.
 * All card photos (including Mystery Box on mobile) gain possible empty space.
 * PHP >=8.0, CLI only; runner self-lint before writes. CSS/Twig are not PHP files.
 * Backup/readback, atomic writes, restore-on-failure, exact rollback receipt.
 * Usage: php RD-25-WP1-FIX_photo-contain_20261011.php [--dry-run]
 * Rollback: re-upload runner; --rollback=_patch_backups/<printed-directory>.
 * Successful apply/repeat/rollback self-deletes; dry-run does not.
 * No DB changes or media cache deletion. Clear only compiled Twig cache after apply.
 */
declare(strict_types=1);

const RD25_MARKER = 'RD-25-WP1-FIX-PHOTO 20261011';
const RD25_TOKEN = 'rd25-wp1-photo-contain-20261011';
const RD25_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';

function rd25Out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function rd25Fail(string $message): void { throw new RuntimeException($message); }
function rd25Read(string $path): string {
	$content = @file_get_contents($path);
	if ($content === false) { rd25Fail('file_read_failed:' . $path); }
	return $content;
}
function rd25Normalize(string $path, string $content): string {
	if ($path !== 'catalog/view/template/common/header.twig') { return $content; }
	$pattern = '~(' . preg_quote(RD25_PREFIX, '~') . ')([A-Za-z0-9._-]{1,64})(?=["\'])~';
	$count = preg_match_all($pattern, $content, $matches);
	if ($count !== 1 || substr_count($content, RD25_PREFIX) !== 1) { rd25Fail('header_cache_token_anchor_invalid'); }
	return preg_replace($pattern, '${1}__RD25_TOKEN__', $content, 1);
}
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

$context = [
'catalog/model/tool/image.php' => '85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71',
'system/library/image.php' => '7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9',
'catalog/controller/product/thumb.php' => 'cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1',
'catalog/view/template/product/thumb.twig' => 'd9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6',
];

$targets = [
'catalog/view/stylesheet/boostershop-ds.css' => ['before_sha256' => 'edc5913817da258374c26aa03cb172eca07c2ee11e76a25402e46e7472440a53', 'after_sha256' => 'c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9', 'replacements' => [
['before' => <<<'RD25_PHOTO_B_0_0'
.bs-pcard__media--photo img {
  inset: 0; width: 100%; height: 100%; object-fit: cover;
  border-radius: var(--bs-r);
}
RD25_PHOTO_B_0_0, 'after' => <<<'RD25_PHOTO_A_0_0'
/* RD-25-WP1-FIX-PHOTO 20261011: whole photo, bottom aligned; empty space allowed. */
.bs-pcard__media--photo img {
  inset: 0; width: 100%; height: 100%; object-fit: contain;
  object-position: center bottom;
  border-radius: var(--bs-r);
}
RD25_PHOTO_A_0_0],
['before' => <<<'RD25_PHOTO_B_0_1'
Geometry below is the approved catalog framing: cut 8%/6%/84%/94%, photo full bleed.
RD25_PHOTO_B_0_1, 'after' => <<<'RD25_PHOTO_A_0_1'
Geometry below keeps cut 8%/6%/84%/94%; photos preserve their full source.
RD25_PHOTO_A_0_1],
]],
'catalog/view/template/common/header.twig' => ['before_sha256' => 'eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee', 'after_sha256' => 'eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee', 'replacements' => [
['before' => <<<'RD25_PHOTO_B_1_0'
catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__
RD25_PHOTO_B_1_0, 'after' => <<<'RD25_PHOTO_A_1_0'
catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__
RD25_PHOTO_A_1_0],
]],
];

$originals = [];
$written = [];
$backup = '';
$lock = null;

try {
	if (PHP_VERSION_ID < 80000) { rd25Fail('php_8_0_required'); }
	rd25Out('php_version', PHP_VERSION);
	if (PHP_SAPI !== 'cli') { rd25Fail('cli_only'); }
	if (!is_callable('exec') || !is_file(PHP_BINARY)) { rd25Fail('php_lint_unavailable'); }
	rd25Lint(__FILE__);
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
		if (!$base || !$backup_path || strpos(str_replace('\\', '/', $backup_path), str_replace('\\', '/', $base) . '/RD-25-WP1-FIX_photo-contain_20261011-') !== 0) {
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

	$marked = substr_count($originals['catalog/view/stylesheet/boostershop-ds.css'], RD25_MARKER);
	if ($marked > 1) { rd25Fail('duplicate_marker'); }
	if ($marked === 1) {
		foreach ($targets['catalog/view/stylesheet/boostershop-ds.css']['replacements'] as $replace) {
			if (substr_count($originals['catalog/view/stylesheet/boostershop-ds.css'], $replace['after']) !== 1) { rd25Fail('applied_content_changed'); }
		}
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('already_applied', 'yes');
		if ($dry_run) { rd25Out('done', 'ok'); } else { rd25Finish(); }
		exit(0);
	}
	foreach ($context as $relative => $expected_hash) {
		$path = $root . '/' . $relative;
		if (!is_file($path) || hash('sha256', rd25Read($path)) !== $expected_hash) { rd25Fail('context_sha256_mismatch:' . $relative); }
		rd25Out('context_file', $relative);
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
		$candidates[$relative] = $relative === 'catalog/view/template/common/header.twig' ? str_replace('__RD25_TOKEN__', RD25_TOKEN, $candidate) : $candidate;
		rd25Out('preflight_file', $relative);
	}
	if ($dry_run) {
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('dry_run', 'yes'); rd25Out('target_writes', '0'); rd25Out('done', 'ok'); exit(0);
	}

	$backup = $root . '/_patch_backups/RD-25-WP1-FIX_photo-contain_20261011-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
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
