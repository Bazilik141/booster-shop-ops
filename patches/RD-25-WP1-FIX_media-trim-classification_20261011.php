<?php
/**
 * RD-25 WP1-FIX: measured classification and alpha-preserving fit-only trimming.
 * Fresh 2026-10-11 export; three exact SHA-256 targets, all gated before writes.
 * Phase 0 thresholds unchanged: alpha >16, min line count 2, pad 2px,
 * white >=250, corner span <=8, median background distance >12,
 * cover band .70..1.45, both slot windows with 2% safety margin.
 * Metadata v4 / fit key v2. Original sources and old cache files retained.
 * Twig/CSS, resize(), crop(), lightbox and JSON-LD paths unchanged.
 * Owner read-only #222 lookup 2026-10-11: twelve public DB fields, zero references.
 * PHP 8.0 compatible; CLI PHP_BINARY -l gates candidates AND written files.
 * No DB writes, cache deletion, commit, push or deployment.
 * Usage: php RD-25-WP1-FIX_media-trim-classification_20261011.php [--dry-run]
 * Rollback: re-upload runner; --rollback=_patch_backups/<printed-directory>.
 * Exact backup/readback, atomic writes, restore on failure, idempotency,
 * success self-delete. Independent of the Featured F6 companion.
 */
declare(strict_types=1);

const RD25_MARKER = 'RD-25-WP1-FIX 20261011';

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
'catalog/model/tool/image.php' => ['before_sha256' => '9d6aaec56eaa44be072e67957e43b7365ca8f115283988d0ab7a3227db885285', 'after_sha256' => 'ea17a200ecd251954ccf907586fad234ee10e796a8b642abd394339b3bb8ace3', 'replacements' => [
['before' => <<<'RD25_B_0_0'
private const RD25_MEDIA_VERSION = 3;
RD25_B_0_0, 'after' => <<<'RD25_A_0_0'
private const RD25_MEDIA_VERSION = 4;
RD25_A_0_0],
['before' => <<<'RD25_B_0_1'
			$corner_alpha = [];
			$corner_rgb = [];
			$transparent_cut = $dimensions[2] !== IMAGETYPE_JPEG;
			$white_cut = true;

			foreach ([[0, 0], [$dimensions[0] - 1, 0], [0, $dimensions[1] - 1], [$dimensions[0] - 1, $dimensions[1] - 1]] as $corner) {
				$color = imagecolorsforindex($gd, imagecolorat($gd, $corner[0], $corner[1]));
				// GD: 127 = transparent. Normalize to 0..255, where 0 = transparent.
				$alpha = (int)round(255 * (127 - (int)$color['alpha']) / 127);
				$corner_alpha[] = $alpha;
				$corner_rgb[] = [(int)$color['red'], (int)$color['green'], (int)$color['blue']];
				// Near-transparent (1..2) edge pixels exist in the supplied PNGs.
				$transparent_cut = $transparent_cut && $alpha <= 16;
				// F1 / owner 2026-10-10: opaque white-background pack art is a cutout.
				// Use the original audit's exact near-white test: every RGB channel >= 240.
				$white_cut = $white_cut && $alpha === 255
					&& $color['red'] >= 240 && $color['green'] >= 240 && $color['blue'] >= 240;
			}

			imagedestroy($gd);
			// F5 / owner 2026-10-10: inclusive 0.70..1.45 cover band.
			// Integer cross-products preserve exact boundary decisions; source pixels stay intact.
			$aspect_cut = $dimensions[0] * 100 < $dimensions[1] * 70
				|| $dimensions[0] * 100 > $dimensions[1] * 145;
			$metadata = [
				'version' => self::RD25_MEDIA_VERSION,
				'source' => $source['relative'],
				'mtime' => $source['mtime'],
				'size' => $source['size'],
				'width' => (int)$dimensions[0],
				'height' => (int)$dimensions[1],
				'type' => (int)$dimensions[2],
				'kind' => ($aspect_cut || $transparent_cut || $white_cut) ? 'cut' : 'photo',
				'corner_alpha' => $corner_alpha,
				'corner_rgb' => $corner_rgb,
				'all_near_white_corners' => $white_cut,
				'class_reason' => $aspect_cut ? 'aspect_outside_cover_band' : ($transparent_cut ? 'transparent_corners' : ($white_cut ? 'opaque_near_white_corners' : 'non_white_or_mixed_corners'))
			];

RD25_B_0_1, 'after' => <<<'RD25_A_0_1'
			$measurement = $this->rd25Measure($gd, (int)$dimensions[0], (int)$dimensions[1]);
			imagedestroy($gd);
			$metadata = array_merge([
				'version' => self::RD25_MEDIA_VERSION, 'source' => $source['relative'],
				'mtime' => $source['mtime'], 'size' => $source['size'],
				'width' => (int)$dimensions[0], 'height' => (int)$dimensions[1], 'type' => (int)$dimensions[2]
			], $measurement);

RD25_A_0_1],
['before' => <<<'RD25_B_0_2'
'rd25-fit-v1:'
RD25_B_0_2, 'after' => <<<'RD25_A_0_2'
'rd25-fit-v2:'
RD25_A_0_2],
['before' => <<<'RD25_B_0_3'
			$image->resizeFit($width, $height);
RD25_B_0_3, 'after' => <<<'RD25_A_0_3'
			if ($metadata['kind'] === 'cut') {
				$image->cropFit(...$metadata['crop_box_exclusive']);
			}
			$image->resizeFit($width, $height);
RD25_A_0_3],
['before' => <<<'RD25_B_0_4'
	private function rd25Source(string $filename): array {
RD25_B_0_4, 'after' => <<<'RD25_A_0_4'
	// RD-25-WP1-FIX 20261011: measured Phase 0 thresholds; exclusive source bounds.
	private function rd25Measure($gd, int $width, int $height): array {
		$corners = []; $white = []; $transparent = []; $span = 0; $background = [];
		foreach ([[0, 0], [$width - 1, 0], [0, $height - 1], [$width - 1, $height - 1]] as $point) {
			$c = imagecolorsforindex($gd, imagecolorat($gd, $point[0], $point[1]));
			$a = (int)round(255 * (127 - $c['alpha']) / 127);
			$corners[] = [$c['red'], $c['green'], $c['blue'], $a];
			$white[] = $a === 255 && min($c['red'], $c['green'], $c['blue']) >= 250;
			$transparent[] = $a <= 16;
		}
		for ($channel = 0; $channel < 3; $channel++) {
			$values = array_column($corners, $channel); sort($values);
			$span = max($span, $values[3] - $values[0]);
			$background[] = ($values[1] + $values[2]) / 2;
		}
		$rows = [array_fill(0, $height, 0), array_fill(0, $height, 0)];
		$cols = [array_fill(0, $width, 0), array_fill(0, $width, 0)];
		$opaque = true; $has_transparent = false;
		for ($y = 0; $y < $height; $y++) {
			for ($x = 0; $x < $width; $x++) {
				$c = imagecolorsforindex($gd, imagecolorat($gd, $x, $y));
				// GD alpha is integral; opacity > 16 is exactly GD alpha < 119.
				$opaque = $opaque && $c['alpha'] === 0;
				$has_transparent = $has_transparent || $c['alpha'] >= 119;
				if ($c['alpha'] < 119) {
					$rows[0][$y]++; $cols[0][$x]++;
					if (max(abs($c['red'] - $background[0]), abs($c['green'] - $background[1]), abs($c['blue'] - $background[2])) > 12) {
						$rows[1][$y]++; $cols[1][$x]++;
					}
				}
			}
		}
		$alpha_box = $this->rd25Bounds($rows[0], $cols[0], 1);
		$background_box = $this->rd25Bounds($rows[1], $cols[1], 1);
		$cover_box = $this->rd25Expand($background_box, $width, $height);
		$cover = [];
		foreach (['desktop' => 1.0, 'mobile' => 0.8] as $slot => $ratio) {
			$cw = $width / $height >= $ratio ? $ratio * $height : $width;
			$ch = $width / $height >= $ratio ? $height : $width / $ratio;
			$window = [($width - $cw) / 2, ($height - $ch) / 2, ($width + $cw) / 2, ($height + $ch) / 2];
			$inner = [$window[0] + $cw * .02, $window[1] + $ch * .02, $window[2] - $cw * .02, $window[3] - $ch * .02];
			$cover[$slot] = ['window' => $window, 'safe_window' => $inner, 'content_box' => $cover_box,
				'passes' => $cover_box[0] >= $inner[0] && $cover_box[1] >= $inner[1] && $cover_box[2] <= $inner[2] && $cover_box[3] <= $inner[3]];
		}
		$background_corners = 0;
		for ($i = 0; $i < 4; $i++) { $background_corners += $white[$i] || $transparent[$i] ? 1 : 0; }
		$uniform = $span <= 8;
		$outside = $width * 100 < $height * 70 || $width * 100 > $height * 145;
		$kind = 'cut';
		if ($background_corners > 0 && $background_corners < 4) { $reason = 'mixed_page_white_or_transparent_corners'; }
		elseif (!in_array(false, $transparent, true)) { $reason = 'transparent_corners'; }
		elseif (!in_array(false, $white, true) && $uniform) { $reason = 'opaque_page_white_uniform_corners'; }
		elseif ($outside) {
			if ($opaque && $background_corners === 0 && $uniform && $background_box && $cover['desktop']['passes'] && $cover['mobile']['passes']) {
				$kind = 'photo'; $reason = 'uniform_non_white_safe_cover_both_slots';
			} else { $reason = 'aspect_outside_cover_band_unsafe_cover'; }
		} else { $kind = 'photo'; $reason = 'non_white_or_mixed_non_white_corners'; }
		$crop = [0, 0, $width, $height]; $method = 'none';
		if ($kind === 'cut') {
			$mask = null;
			if ($has_transparent) { $mask = 0; $method = 'alpha'; }
			elseif ($uniform && (!in_array(false, $white, true) || ($opaque && $outside))) { $mask = 1; $method = 'background_distance'; }
			if ($mask !== null) {
				$strict = $mask === 0 ? $alpha_box : $background_box;
				$crop = $this->rd25Expand($this->rd25Bounds($rows[$mask], $cols[$mask], 2), $width, $height);
				// Containment of strict extents proves no above-threshold pixel is discarded.
				if ($strict && ($crop[0] > $strict[0] || $crop[1] > $strict[1] || $crop[2] < $strict[2] || $crop[3] < $strict[3])) {
					$crop = $this->rd25Expand($strict, $width, $height); $method .= ':strict_bbox_fallback';
				}
			}
		}
		return ['kind' => $kind, 'class_reason' => $reason, 'corner_alpha' => array_column($corners, 3),
			'corner_rgb' => array_map(static function(array $c): array { return array_slice($c, 0, 3); }, $corners),
			'all_near_white_corners' => !in_array(false, $white, true), 'corner_span' => $span,
			'background_rgb' => $background, 'alpha_bbox_16' => $alpha_box, 'background_bbox_12' => $background_box,
			'cover_check' => $cover, 'trim_method' => $method, 'crop_box_exclusive' => $crop,
			'crop_width' => $crop[2] - $crop[0], 'crop_height' => $crop[3] - $crop[1]];
	}

	private function rd25Bounds(array $rows, array $cols, int $minimum): ?array {
		$ys = []; $xs = [];
		foreach ($rows as $i => $count) { if ($count >= $minimum) { $ys[] = $i; } }
		foreach ($cols as $i => $count) { if ($count >= $minimum) { $xs[] = $i; } }
		return $ys && $xs ? [$xs[0], $ys[0], $xs[count($xs) - 1] + 1, $ys[count($ys) - 1] + 1] : null;
	}

	private function rd25Expand(?array $box, int $width, int $height): array {
		return $box ? [max(0, $box[0] - 2), max(0, $box[1] - 2), min($width, $box[2] + 2), min($height, $box[3] + 2)] : [0, 0, $width, $height];
	}


	private function rd25Source(string $filename): array {
RD25_A_0_4],
['before' => <<<'RD25_B_0_5'
		return $metadata;
	}
	/**
RD25_B_0_5, 'after' => <<<'RD25_A_0_5'
		$box = $metadata['crop_box_exclusive'] ?? null;
		if (!is_array($box) || array_keys($box) !== [0, 1, 2, 3]) { return []; }
		foreach ($box as $value) { if (!is_int($value)) { return []; } }
		if ($box[0] < 0 || $box[1] < 0 || $box[2] > $metadata['width'] || $box[3] > $metadata['height']
			|| $box[2] <= $box[0] || $box[3] <= $box[1]
			|| ($metadata['crop_width'] ?? null) !== $box[2] - $box[0] || ($metadata['crop_height'] ?? null) !== $box[3] - $box[1]
			|| ($metadata['kind'] === 'photo' && $box !== [0, 0, $metadata['width'], $metadata['height']])) { return []; }
		return $metadata;
	}
	/**
RD25_A_0_5]
]],
'system/library/image.php' => ['before_sha256' => 'b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9', 'after_sha256' => '7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9', 'replacements' => [
['before' => <<<'RD25_B_1_0'
	public function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {
RD25_B_1_0, 'after' => <<<'RD25_A_1_0'
	/** RD-25-WP1-FIX 20261011: crop exclusive bounds without flattening source alpha. */
	public function cropFit(int $left, int $top, int $right, int $bottom): void {
		if ($left < 0 || $top < 0 || $right > $this->width || $bottom > $this->height || $right <= $left || $bottom <= $top) {
			throw new \InvalidArgumentException('Invalid fit crop bounds');
		}
		if ($left === 0 && $top === 0 && $right === $this->width && $bottom === $this->height) { return; }
		$width = $right - $left; $height = $bottom - $top;
		$next = imagecreatetruecolor($width, $height);
		if (!$next) { throw new \RuntimeException('Fit crop allocation failed'); }
		if ($this->mime === 'image/png' || $this->mime === 'image/webp') {
			imagealphablending($next, false); imagesavealpha($next, true);
			$background = imagecolorallocatealpha($next, 255, 255, 255, 127);
		} else { $background = imagecolorallocate($next, 255, 255, 255); }
		imagefilledrectangle($next, 0, 0, $width - 1, $height - 1, $background);
		if (!imagecopy($next, $this->image, 0, 0, $left, $top, $width, $height)) {
			imagedestroy($next); throw new \RuntimeException('Fit crop copy failed');
		}
		imagedestroy($this->image); $this->image = $next;
		$this->width = $width; $this->height = $height;
	}


	public function crop(int $top_x, int $top_y, int $bottom_x, int $bottom_y): void {
RD25_A_1_0]
]],
'catalog/controller/product/thumb.php' => ['before_sha256' => '47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb', 'after_sha256' => 'cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1', 'replacements' => [
['before' => <<<'RD25_B_2_0'
				$bs_scale = min(1.0, 700 / $bs_media['width'], 875 / $bs_media['height']);
RD25_B_2_0, 'after' => <<<'RD25_A_2_0'
				// RD-25-WP1-FIX 20261011: match the generated crop + fit dimensions.
				$bs_scale = min(1.0, 700 / $bs_media['crop_width'], 875 / $bs_media['crop_height']);
RD25_A_2_0],
['before' => <<<'RD25_B_2_1'
floor($bs_media['width'] * $bs_scale)
RD25_B_2_1, 'after' => <<<'RD25_A_2_1'
floor($bs_media['crop_width'] * $bs_scale)
RD25_A_2_1],
['before' => <<<'RD25_B_2_2'
floor($bs_media['height'] * $bs_scale)
RD25_B_2_2, 'after' => <<<'RD25_A_2_2'
floor($bs_media['crop_height'] * $bs_scale)
RD25_A_2_2]
]]
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
		if (!$base || !$backup_path || strpos(str_replace('\\', '/', $backup_path), str_replace('\\', '/', $base) . '/RD-25-WP1-FIX_media-trim-classification_20261011-') !== 0) {
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
			if (hash('sha256', $originals[$relative]) !== $target['after_sha256']) { rd25Fail('applied_source_changed:' . $relative); }
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

	$backup = $root . '/_patch_backups/RD-25-WP1-FIX_media-trim-classification_20261011-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
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
