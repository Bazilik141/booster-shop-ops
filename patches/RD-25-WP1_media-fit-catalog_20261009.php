<?php
/**
 * RD-25 WP1 — non-padding listing media, cut/photo framing and mobile grids.
 * Revision 3 (2026-10-10): F5 outside 0.70..1.45 is cut; F1 retained; metadata v3.
 * PHP 8.0 compatible. CLI only. No DB writes. No source-image or existing-cache edits.
 * Inputs: RD-25-live-20261009.tar.gz (post RD-26) and RD-25-WP1-followup-20261009.tar.gz.
 * Root cause: Image::resize() pads to WxH; UI-PCARD-D uses 331/240 and centered contain;
 * listing templates start at row-cols-1. The existing resize() path stays unchanged.
 * WP2 product gallery and WP3 small thumbs are excluded.
 *
 * Usage: php RD-25-WP1_media-fit-catalog_20261009.php [--dry-run]
 * Rollback: re-upload this file, then pass --rollback=_patch_backups/<printed-directory>.
 * Rollback refuses newer changes; roll back WP2/WP3 first if they were deployed.
 * Backups: _patch_backups/RD-25-WP1_media-fit-catalog_20261009-<UTC>-<random>/original/.
 * All anchors/files are checked before backup/write. Any failure restores every written file.
 * Successful apply/repeat/rollback self-deletes. --dry-run keeps the runner.
 * New fit images and rd25 metadata/locks remain unused after rollback; old cache is retained.
 */
declare(strict_types=1);

const RD25_MARKER = 'RD-25-WP1 20261009';
const RD25_TOKEN = 'rd25-wp1-media-fit-r3-20261010';
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

/* Reviewed transformations, kept readable for independent review. */
$targets = [
	'system/library/image.php' => [
		'before_sha256' => '84e064697d81b06397d39fce6e35037d1c5f8d8b3d17195daa462c5189062e54',
		'after_sha256' => 'b032345fa128dc867a55dbe4bf49f4070a34a2efa952525a69006fdb706875d9',
		'replacements' => [
			['before' => <<<'RD25_0_0_BEFORE'
	/**
	 * Watermark

RD25_0_0_BEFORE,
'after' => <<<'RD25_0_0_AFTER'
	/**
	 * RD-25-WP1 20261009: fit inside a box without adding a padded canvas.
	 * Keep resize() unchanged: popup/schema/feed callers retain their old URLs.
	 * Smaller originals are not enlarged; alpha and the source aspect are preserved.
	 */
	public function resizeFit(int $width, int $height): void {
		if ($width < 1 || $height < 1 || !$this->width || !$this->height) {
			throw new \InvalidArgumentException('RD-25: invalid fit dimensions');
		}

		$scale = min(1.0, $width / $this->width, $height / $this->height);
		$new_width = max(1, (int)floor($this->width * $scale));
		$new_height = max(1, (int)floor($this->height * $scale));

		if ($new_width === $this->width && $new_height === $this->height) {
			return;
		}

		$image_new = imagecreatetruecolor($new_width, $new_height);

		if (!$image_new) {
			throw new \RuntimeException('RD-25: cannot allocate fit image');
		}

		if ($this->mime === 'image/png' || $this->mime === 'image/webp') {
			imagealphablending($image_new, false);
			imagesavealpha($image_new, true);
			$background = imagecolorallocatealpha($image_new, 255, 255, 255, 127);
		} else {
			$background = imagecolorallocate($image_new, 255, 255, 255);
		}

		imagefill($image_new, 0, 0, $background);

		if (!imagecopyresampled($image_new, $this->image, 0, 0, 0, 0, $new_width, $new_height, $this->width, $this->height)) {
			imagedestroy($image_new);
			throw new \RuntimeException('RD-25: fit resampling failed');
		}

		imagedestroy($this->image);
		$this->image = $image_new;
		$this->width = $new_width;
		$this->height = $new_height;
	}

	/**
	 * Watermark

RD25_0_0_AFTER],
		]
	],
	'catalog/model/tool/image.php' => [
		'before_sha256' => '8ccf26a263a854004e488d9810a133b85ea8f180fe06c453ae4b285857c65bc5',
		'after_sha256' => 'a42869efe054311be56f1c37e56969e8e1318331de55b6ca3e8aa4b83b0a4ddd',
		'replacements' => [
			['before' => <<<'RD25_1_0_BEFORE'
	/**
	 * TECH-044-WEBP: WebP quality
RD25_1_0_BEFORE,
'after' => <<<'RD25_1_0_AFTER'
	// RD-25-WP1 20261009: isolated fit cache and source classification.
	private const RD25_MEDIA_VERSION = 3;

	/** Source metadata is cached per canonical path + mtime + size, not per view. */
	public function mediaInfo(string $filename): array {
		$source = $this->rd25Source($filename);

		if (!$source) {
			return [];
		}

		$cache_file = DIR_CACHE . 'rd25-media-v' . self::RD25_MEDIA_VERSION . '-' . hash('sha256', $source['relative']) . '.json';
		$cached = $this->rd25ReadMedia($cache_file, $source);

		if ($cached) {
			return $cached;
		}

		// On unavailable persistent storage use the original thumb; do not decode on each view.
		if (!is_dir(DIR_CACHE) || !is_writable(DIR_CACHE)) {
			return [];
		}

		$lock = @fopen($cache_file . '.lock', 'c');

		if (!$lock) {
			return [];
		}

		try {
			if (!flock($lock, LOCK_EX)) {
				return [];
			}

			$cached = $this->rd25ReadMedia($cache_file, $source);

			if ($cached) {
				return $cached;
			}

			$dimensions = @getimagesize($source['path']);

			if (!$dimensions || !in_array($dimensions[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
				return [];
			}

			$image = new \Opencart\System\Library\Image($source['path']);
			$gd = $image->getImage();

			if (!$gd) {
				return [];
			}

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
			$json = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
			$temp = @tempnam(DIR_CACHE, 'rd25-meta-');

			if ($json === false || !$temp) {
				return [];
			}

			if (@file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !@rename($temp, $cache_file)) {
				@unlink($temp);
				return [];
			}

			return $metadata;
		} catch (\Throwable $error) {
			return [];
		} finally {
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	/** New API only: no existing resize() call or cache file is changed. */
	public function resizeFit(string $filename, int $width, int $height): string {
		if ($width < 1 || $height < 1 || $width > 4096 || $height > 4096) {
			return '';
		}

		$metadata = $this->mediaInfo($filename);

		if (!$metadata) {
			return '';
		}

		$source = $this->rd25Source($filename);

		if (!$source || $source['mtime'] !== $metadata['mtime'] || $source['size'] !== $metadata['size']) {
			return '';
		}

		$extensions = [IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp'];
		$extension = $this->webpSupported() ? 'webp' : $extensions[$metadata['type']];
		// Source format/path/stat are part of the key: same-stem PNG/JPEG never collide.
		$key = hash('sha256', 'rd25-fit-v1:' . $source['relative'] . ':' . $source['mtime'] . ':' . $source['size']);
		$image_new = 'cache/rd25-fit/' . $key . '-' . $width . 'x' . $height . '-fit.' . $extension;
		$destination = DIR_IMAGE . $image_new;

		if (is_file($destination) && filesize($destination) > 0) {
			return $this->config->get('config_url') . 'image/' . $image_new;
		}

		$lock = @fopen(DIR_CACHE . 'rd25-fit-' . hash('sha256', $image_new) . '.lock', 'c');

		if (!$lock) {
			return '';
		}

		$temp = '';

		try {
			if (!flock($lock, LOCK_EX)) {
				return '';
			}

			clearstatcache(true, $destination);

			if (is_file($destination) && filesize($destination) > 0) {
				return $this->config->get('config_url') . 'image/' . $image_new;
			}

			$directory = dirname($destination);

			if (!is_dir($directory) && !@mkdir($directory, 0777, true) && !is_dir($directory)) {
				return '';
			}

			$temp_base = @tempnam($directory, 'rd25-fit-');

			if (!$temp_base) {
				return '';
			}

			$temp = $temp_base . '.' . $extension;

			if (!@rename($temp_base, $temp)) {
				@unlink($temp_base);
				return '';
			}

			$image = new \Opencart\System\Library\Image($source['path']);
			$image->resizeFit($width, $height);
			$expected_width = $image->getWidth();
			$expected_height = $image->getHeight();
			$image->save($temp, self::WEBP_QUALITY);
			$output = @getimagesize($temp);
			$expected_type = $extension === 'webp' ? IMAGETYPE_WEBP : $metadata['type'];

			if (!$output || $output[0] !== $expected_width || $output[1] !== $expected_height || $output[2] !== $expected_type || !@chmod($temp, 0644) || !@rename($temp, $destination)) {
				return '';
			}

			$temp = '';
			return $this->config->get('config_url') . 'image/' . $image_new;
		} catch (\Throwable $error) {
			return '';
		} finally {
			if ($temp !== '' && is_file($temp)) {
				@unlink($temp);
			}
			flock($lock, LOCK_UN);
			fclose($lock);
		}
	}

	private function rd25Source(string $filename): array {
		$filename = html_entity_decode($filename, ENT_QUOTES, 'UTF-8');
		$root = realpath(DIR_IMAGE);
		$path = realpath(DIR_IMAGE . $filename);

		if (!$root || !$path || !is_file($path)) {
			return [];
		}

		$root = rtrim(str_replace('\\', '/', $root), '/') . '/';
		$path = str_replace('\\', '/', $path);

		if (strpos($path, $root) !== 0) {
			return [];
		}

		clearstatcache(true, $path);
		$stat = @stat($path);

		return $stat ? ['path' => $path, 'relative' => substr($path, strlen($root)), 'mtime' => (int)$stat['mtime'], 'size' => (int)$stat['size']] : [];
	}

	private function rd25ReadMedia(string $cache_file, array $source): array {
		$json = @file_get_contents($cache_file);
		$metadata = $json !== false ? json_decode($json, true) : null;

		if (!is_array($metadata) || ($metadata['version'] ?? null) !== self::RD25_MEDIA_VERSION
			|| ($metadata['source'] ?? null) !== $source['relative'] || ($metadata['mtime'] ?? null) !== $source['mtime']
			|| ($metadata['size'] ?? null) !== $source['size'] || !in_array($metadata['kind'] ?? '', ['cut', 'photo'], true)
			|| !is_int($metadata['width'] ?? null) || $metadata['width'] < 1 || !is_int($metadata['height'] ?? null) || $metadata['height'] < 1
			|| !in_array($metadata['type'] ?? null, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
			return [];
		}

		return $metadata;
	}
	/**
	 * TECH-044-WEBP: WebP quality
RD25_1_0_AFTER],
		]
	],
	'catalog/controller/product/thumb.php' => [
		'before_sha256' => '1f003f688fdd199ea3cf84b8a1703a0ae319734fb7a32e8849c8c1f053708a83',
		'after_sha256' => '47e56e45e134359074aecdd3a1a50a068e8b19766b40589cb8688e077df5d2eb',
		'replacements' => [
			['before' => <<<'RD25_2_0_BEFORE'
		$this->load->language('product/thumb');
RD25_2_0_BEFORE,
'after' => <<<'RD25_2_0_AFTER'
		// RD-25-WP1 20261009: original image comes from the caller's product row.
		// All existing caller resize() expressions remain byte-identical.
		$data['bs_media_kind'] = '';
		$data['bs_fit_thumb'] = '';
		$bs_source = isset($data['image']) && is_string($data['image']) ? $data['image'] : '';

		// Third-party callers may provide only product_id/thumb. Read the original once
		// per product within this request rather than reverse-parsing a cache URL.
		if ($bs_source === '' && !empty($data['product_id'])) {
			static $bs_source_cache = [];
			$bs_product_id = (int)$data['product_id'];

			if (!array_key_exists($bs_product_id, $bs_source_cache)) {
				$this->load->model('catalog/product');
				$bs_product = $this->model_catalog_product->getProduct($bs_product_id);
				$bs_source_cache[$bs_product_id] = $bs_product['image'] ?? '';
			}

			$bs_source = (string)$bs_source_cache[$bs_product_id];
		}

		if ($bs_source !== '' && strpos(strtolower($bs_source), 'placeholder') === false && strpos(strtolower($bs_source), 'no_image') === false) {
			$this->load->model('tool/image');
			$bs_fit_url = $this->model_tool_image->resizeFit($bs_source, 700, 875);

			if ($bs_fit_url !== '' && ($bs_media = $this->model_tool_image->mediaInfo($bs_source))) {
				$bs_scale = min(1.0, 700 / $bs_media['width'], 875 / $bs_media['height']);
				$data['bs_fit_thumb'] = $bs_fit_url;
				$data['bs_media_kind'] = $bs_media['kind'];
				$data['bs_media_width'] = max(1, (int)floor($bs_media['width'] * $bs_scale));
				$data['bs_media_height'] = max(1, (int)floor($bs_media['height'] * $bs_scale));
			}
		}
		$this->load->language('product/thumb');
RD25_2_0_AFTER],
		]
	],
	'catalog/view/template/product/thumb.twig' => [
		'before_sha256' => '1e7658cddb25720a06e44f9f4c8ea989d3a410d61a22e3425498611a7a9c3a5c',
		'after_sha256' => 'd9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6',
		'replacements' => [
			['before' => <<<'RD25_3_0_BEFORE'
{# RD-04 Product card system:
RD25_3_0_BEFORE,
'after' => <<<'RD25_3_0_AFTER'
{# RD-25-WP1 20261009: fitted listing image and source kind. #}
{# RD-04 Product card system:
RD25_3_0_AFTER],
			['before' => <<<'RD25_3_1_BEFORE'
{% set bs_thumb = thumb|default('') %}
RD25_3_1_BEFORE,
'after' => <<<'RD25_3_1_AFTER'
{% set bs_thumb = bs_fit_thumb|default(thumb|default('')) %}
RD25_3_1_AFTER],
			['before' => <<<'RD25_3_2_BEFORE'
<a class="bs-pcard__media"
RD25_3_2_BEFORE,
'after' => <<<'RD25_3_2_AFTER'
<a class="bs-pcard__media{% if bs_media_kind|default('') in ['cut', 'photo'] %} bs-pcard__media--{{ bs_media_kind }}{% endif %}"
RD25_3_2_AFTER],
			['before' => <<<'RD25_3_3_BEFORE'
<img src="{{ thumb }}" alt="{{ name }}" loading="lazy" width="240" height="240">
RD25_3_3_BEFORE,
'after' => <<<'RD25_3_3_AFTER'
<img src="{{ bs_thumb }}" alt="{{ name }}" loading="lazy" width="{{ bs_media_width|default(240) }}" height="{{ bs_media_height|default(240) }}">
RD25_3_3_AFTER],
		]
	],
	'catalog/view/stylesheet/boostershop-ds.css' => [
		'before_sha256' => '91bf663f5555b498f865b96288551bdb99dfd8606558330c5bab40972bc45188',
		'after_sha256' => 'edc5913817da258374c26aa03cb172eca07c2ee11e76a25402e46e7472440a53',
		'replacements' => [
			['before' => <<<'RD25_4_0_BEFORE'
.bs-pcard__media {
  position: relative; aspect-ratio: 331/240;
}
.bs-pcard__media img {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: contain;
}
RD25_4_0_BEFORE,
'after' => <<<'RD25_4_0_AFTER'
/* RD-25-WP1 20261009: edit UI-PCARD-D source rules; no backdrop or override layer.
   Geometry below is the approved catalog framing: cut 8%/6%/84%/94%, photo full bleed. */
.bs-pcard__media {
  position: relative; aspect-ratio: 1 / 1; background: transparent;
}
.bs-pcard__media img {
  position: absolute; inset: 0;
  width: 100%; height: 100%; object-fit: contain;
}
.bs-pcard__media--cut img {
  inset: auto; left: 8%; top: 6%; width: 84%; height: 94%;
  object-fit: contain; object-position: center bottom;
}
.bs-pcard__media--photo img {
  inset: 0; width: 100%; height: 100%; object-fit: cover;
  border-radius: var(--bs-r);
}
@media (max-width: 767.98px) {
  .bs-pcard__media { aspect-ratio: 4 / 5; }
}
RD25_4_0_AFTER],
			['before' => <<<'RD25_4_1_BEFORE'
/* TECH-045-WPE: 16px/700 at every breakpoint — narrowest live card button is 291px, the longest label
   («Передзамовити») needs 159px incl. padding, so one size stays on one line everywhere. */
RD25_4_1_BEFORE,
'after' => <<<'RD25_4_1_AFTER'
/* TECH-045-WPE desktop size; RD-25 mobile typography is defined with the component below. */
RD25_4_1_AFTER],
			['before' => <<<'RD25_4_2_BEFORE'
.bs-pcard__img-placeholder {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  background: transparent;
}
RD25_4_2_BEFORE,
'after' => <<<'RD25_4_2_AFTER'
.bs-pcard__img-placeholder {
  position: absolute; inset: 0;
  width: 100%; height: 100%;
  background: transparent;
}
/* RD-25 grids: data attribute survives stock common.js rewriting row classes.
   Related products remain their existing mobile swipe rail; vertical modules are excluded. */
.bs-media-grid__item { margin-bottom: 16px; } /* replaces Bootstrap mb-3 on desktop */
@media (max-width: 575.98px) {
  [data-bs-media-grid] {
    display: grid; grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px 14px; /* approved row/column gaps */
    margin-left: 0; margin-right: 0;
  }
  [data-bs-media-grid] > .bs-media-grid__item {
    width: auto; max-width: none; min-width: 0; padding: 0; margin-bottom: 0;
  }
  .bs-pcard__title { font-size: 13px; line-height: 1.35; min-height: 35px; overflow-wrap: anywhere; }
  .bs-pcard__price-new,
  .bs-pcard__price-row--sale .bs-pcard__price-new { font-size: 15px; font-weight: 800; }
  .bs-pcard__price-row { flex-wrap: wrap; row-gap: 4px; }
  /* 8px side padding keeps the longest preorder label inside a 360px two-column layout. */
  .bs-pcard__buy-btn { height: 44px; font-size: 15px; font-weight: 700; padding: 0 8px; }
}

RD25_4_2_AFTER],
		]
	],
	'catalog/view/template/product/category.twig' => [
		'before_sha256' => '1241f8163bc16f5ebec936fd39444ca6daa78420f753d805cfb3be37dde230d2',
		'after_sha256' => 'f93313c689cd7ddc3e4d2750d34a0c365f3cd3de2304456f512817c7147da187',
		'replacements' => [
			['before' => <<<'RD25_5_0_BEFORE'
<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">
RD25_5_0_BEFORE,
'after' => <<<'RD25_5_0_AFTER'
{# RD-25-WP1 20261009: two-column mobile product grid. #}
        <div id="product-list" class="row row-cols-2 row-cols-sm-2 row-cols-md-2 row-cols-lg-4" data-bs-media-grid="RD-25-WP1">
RD25_5_0_AFTER],
			['before' => <<<'RD25_5_1_BEFORE'
<div class="col mb-3">
RD25_5_1_BEFORE,
'after' => <<<'RD25_5_1_AFTER'
<div class="col bs-media-grid__item">
RD25_5_1_AFTER],
			['before' => <<<'RD25_5_2_BEFORE'
row-cols-1 on mobile, so one card is above the fold, and eager-loading a
RD25_5_2_BEFORE,
'after' => <<<'RD25_5_2_AFTER'
two columns on mobile; only the primary card is high priority, and eager-loading a
RD25_5_2_AFTER],
		]
	],
	'catalog/view/template/product/search.twig' => [
		'before_sha256' => '107869d094103b8f3fbe140dc621e4d4a2e1182c4f9eb529c4439b2946a4b0b5',
		'after_sha256' => 'dce45b48efaf87d6b2e554d2dab710a8e4cb446d09d11a91b8174ac5ff3589d0',
		'replacements' => [
			['before' => <<<'RD25_6_0_BEFORE'
<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">
RD25_6_0_BEFORE,
'after' => <<<'RD25_6_0_AFTER'
{# RD-25-WP1 20261009: two-column mobile product grid. #}
        <div id="product-list" class="row row-cols-2 row-cols-sm-2 row-cols-md-2 row-cols-lg-4" data-bs-media-grid="RD-25-WP1">
RD25_6_0_AFTER],
			['before' => <<<'RD25_6_1_BEFORE'
<div class="col mb-3">
RD25_6_1_BEFORE,
'after' => <<<'RD25_6_1_AFTER'
<div class="col bs-media-grid__item">
RD25_6_1_AFTER],
		]
	],
	'catalog/view/template/product/special.twig' => [
		'before_sha256' => 'eb7d1aa4860d2df2e4b27907d0db7f9997f0cbaf8e5b37ad3865ac1a0b841e53',
		'after_sha256' => '7e63d7648ec6f73dc206244b1d7c02b7c0d1b64f79157eae20e53773d389b565',
		'replacements' => [
			['before' => <<<'RD25_7_0_BEFORE'
<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4 row-cols-xl-4">
RD25_7_0_BEFORE,
'after' => <<<'RD25_7_0_AFTER'
{# RD-25-WP1 20261009: two-column mobile product grid. #}
        <div id="product-list" class="row row-cols-2 row-cols-sm-2 row-cols-md-2 row-cols-lg-4 row-cols-xl-4" data-bs-media-grid="RD-25-WP1">
RD25_7_0_AFTER],
			['before' => <<<'RD25_7_1_BEFORE'
<div class="col mb-3">
RD25_7_1_BEFORE,
'after' => <<<'RD25_7_1_AFTER'
<div class="col bs-media-grid__item">
RD25_7_1_AFTER],
		]
	],
	'catalog/view/template/product/manufacturer_info.twig' => [
		'before_sha256' => 'c3a1e512514cd5ed23453ce3b8936642b2822a6f945ccbb04968740f872266aa',
		'after_sha256' => '56acc02f18d20d8189028ac31d10002d87d4deb6c7c57c5b8fe8dadc4c66cbf1',
		'replacements' => [
			['before' => <<<'RD25_8_0_BEFORE'
<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">
RD25_8_0_BEFORE,
'after' => <<<'RD25_8_0_AFTER'
{# RD-25-WP1 20261009: two-column mobile product grid. #}
        <div id="product-list" class="row row-cols-2 row-cols-sm-2 row-cols-md-2 row-cols-lg-4" data-bs-media-grid="RD-25-WP1">
RD25_8_0_AFTER],
			['before' => <<<'RD25_8_1_BEFORE'
<div class="col mb-3">
RD25_8_1_BEFORE,
'after' => <<<'RD25_8_1_AFTER'
<div class="col bs-media-grid__item">
RD25_8_1_AFTER],
		]
	],
	'extension/opencart/catalog/view/template/module/featured.twig' => [
		'before_sha256' => 'a8dcc48823c41a75693f3740a35c222a8da937d7bcc0482bcb5968459945502f',
		'after_sha256' => 'b669587152de72aa038f690e218cd0ce8cc1e8877d0445cbd67a32cfab500d86',
		'replacements' => [
			['before' => <<<'RD25_9_0_BEFORE'
<h3>{{ heading_title }}</h3>
RD25_9_0_BEFORE,
'after' => <<<'RD25_9_0_AFTER'
{# RD-25-WP1 20261009: horizontal module grid only; vertical widgets keep their layout. #}
<h3>{{ heading_title }}</h3>
RD25_9_0_AFTER],
			['before' => <<<'RD25_9_1_BEFORE'
<div class="row{% if axis == 'horizontal' %} row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4{% endif %}">
RD25_9_1_BEFORE,
'after' => <<<'RD25_9_1_AFTER'
<div class="row{% if axis == 'horizontal' %} row-cols-2 row-cols-sm-2 row-cols-md-3 row-cols-xl-4{% endif %}"{% if axis == 'horizontal' %} data-bs-media-grid="RD-25-WP1"{% endif %}>
RD25_9_1_AFTER],
			['before' => <<<'RD25_9_2_BEFORE'
<div class="col mb-3">
RD25_9_2_BEFORE,
'after' => <<<'RD25_9_2_AFTER'
<div class="col {% if axis == 'horizontal' %}bs-media-grid__item{% else %}mb-3{% endif %}">
RD25_9_2_AFTER],
		]
	],
	'catalog/view/template/common/header.twig' => [
		'before_sha256' => 'fcc16bc7c02b7524d241f0f124da794c0ecc56c3f706898a16fb7618573ac111',
		'after_sha256' => 'eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee',
		'replacements' => [
			['before' => <<<'RD25_10_0_BEFORE'
  <link href="catalog/view/stylesheet/boostershop-ds.css?v=
RD25_10_0_BEFORE,
'after' => <<<'RD25_10_0_AFTER'
  {# RD-25-WP1 20261009: fit-media stylesheet version. #}
  <link href="catalog/view/stylesheet/boostershop-ds.css?v=
RD25_10_0_AFTER],
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
		if (!$base || !$backup_path || strpos(str_replace('\\', '/', $backup_path), str_replace('\\', '/', $base) . '/RD-25-WP1_media-fit-catalog_20261009-') !== 0) {
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
		$candidates[$relative] = $relative === 'catalog/view/template/common/header.twig' ? str_replace('__RD25_TOKEN__', RD25_TOKEN, $candidate) : $candidate;
		rd25Out('preflight_file', $relative);
	}
	if ($dry_run) {
		flock($lock, LOCK_UN); fclose($lock); $lock = null;
		rd25Out('dry_run', 'yes'); rd25Out('target_writes', '0'); rd25Out('done', 'ok'); exit(0);
	}

	$backup = $root . '/_patch_backups/RD-25-WP1_media-fit-catalog_20261009-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
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
