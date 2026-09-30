<?php
declare(strict_types=1);

/**
 * TECH-044 — WP1: the storefront image resizer writes WebP
 * =============================================================================
 * Task      : TECH-044 WP1 (single patch; WP2 card dimensions is out of scope)
 * Handoff   : handoffs/handoff_TECH-044_webp-resizer_20260929.md §4 WP1
 * Evidence  : diagnostics/TECH-044_wp0-baseline_report_20260929.md (baseline, quality choice,
 *             consumers); diagnostics/TECH-044_wp1-resizer-webp_report_20260929.md (local tests)
 * Author    : Claude Code · 2026-09-29
 * Risk      : MEDIUM — one shared core model. Every storefront thumbnail, the Product JSON-LD
 *             `image` and the image sitemap's <image:loc> take the URL it returns. No template,
 *             CSS, checkout, payment, feed or DB code is touched.
 * DB changes: NONE
 * RUN FROM  : ~/public_html    ->    php TECH-044_wp1-resizer-webp_20260929.php
 * Live base : backup-9.24.2026_16-35-03_boosters.tar.gz — target must still be that exact file
 *             (sha1 d3acd6ea7c40731444308d0342c3ff1d0eb9f720, stock OpenCart); anything else aborts.
 *
 * WHAT CHANGES — catalog/model/tool/image.php only
 *   resize() first calls a new private resizeWebp(). For a PNG, JPEG or WebP source, when GD can
 *   write WebP (function_exists('imagewebp') and imagetypes() & IMG_WEBP), it returns
 *   <config_url>image/cache/<name>-WxH.webp. Whatever it declines comes back as '' and resize()
 *   continues with its original code, which this patch leaves byte for byte as it was. So:
 *     - no WebP in GD            -> today's output exactly (source extension, copy() branch included);
 *     - GIF inside the file      -> today's output (animation would be lost), decided by content;
 *     - X.png next to X.webp     -> today's output for both (they would share one .webp name);
 *     - encode produced no file  -> today's output, and an empty .webp is removed.
 *   A PNG or JPEG is re-encoded even when it already has the target size — the old copy() branch
 *   would otherwise put PNG/JPEG bytes under a .webp name. A WebP source at the target size is
 *   still copied, exactly as today (same name, same bytes).
 *
 * QUALITY 80 — chosen from the WP0 measurement (14 real sources x 250/500 px, q70-q90, SSIM and
 *   zoomed visual check). Note: system/library/image.php Image::save() does NOT pass $quality on to
 *   imagewebp(); PHP's own imagewebp() default is 80. The patch passes 80 explicitly, so the chosen
 *   value and the encoder agree; the library is not edited. Production evidence: existing
 *   -250x250.webp cache files on live match local library encodes to within 26 bytes.
 *
 * NOT CHANGED: system/library/image.php, admin, templates (thumb/header/product), CSS, .htaccess,
 *   sitemap files, merchant feed, source images, image/cache (no purge — old .png/.jpg cache files
 *   stay on disk, which is what makes rollback instant), database.
 *
 * SAFETY
 *   1. Target exists, readable, writable; exact audited sha1; two anchors counted (1 each).
 *   2. New file linted with this host's PHP (PHP_BINARY -l) from a temp copy BEFORE any write.
 *   3. Self-test on this host's PHP + GD, BEFORE any write: the new class is loaded from the temp
 *      copy and runs resize() on generated images in a temp directory (transparent PNG, JPEG at
 *      target size, upper-case .JPG, GIF, WebP at target size, X.png + X.webp twin, space in name).
 *      Any PHP warning or notice fails it — the storefront error handler shows warnings to
 *      customers (config_error_display = 1), so the new code must not emit any.
 *   4. Backup to _patch_backups/<patch>-<ts>/, atomic write, read-back verify, php -l on the
 *      target; any failure restores the original.
 *   5. Idempotent: marker TECH-044-WEBP -> already_applied=yes. 6. No DB. 7. Self-deletes.
 *
 * ROLLBACK
 *   cp _patch_backups/TECH-044_wp1-resizer-webp_20260929-<ts>/catalog/model/tool/image.php \
 *      catalog/model/tool/image.php
 *   Effect is immediate: resize() returns the old .png/.jpg cache files again. The .webp files it
 *   generated are inert afterwards and may stay.
 * =============================================================================
 */

const PATCH_ID    = 'TECH-044_wp1-resizer-webp_20260929';
const MARKER      = 'TECH-044-WEBP';
const TARGET      = 'catalog/model/tool/image.php';
const BASE_SHA1   = 'd3acd6ea7c40731444308d0342c3ff1d0eb9f720';
const SELFTEST_URL = 'https://selftest.invalid/';

function out(string $line): void { echo $line . PHP_EOL; }
function fail(string $reason): void { throw new RuntimeException($reason); }

/** The inserted code is written with 4-space indents below for readability; the target uses tabs. */
function tabs(string $code): string
{
    $result = preg_replace_callback('/^((?: {4})+)/m', static function (array $m): string {
        return str_repeat("\t", intdiv(strlen($m[1]), 4));
    }, $code);
    if (!is_string($result)) fail('indent_conversion_failed');
    return $result;
}

function count_exact(string $haystack, string $needle, int $expected, string $label): void
{
    $actual = substr_count($haystack, $needle);
    if ($actual !== $expected) fail('anchor_count_' . $label . '=' . $actual . ',expected=' . $expected);
}

function lint(string $path, string $label): void
{
    if (!function_exists('exec')) fail('php_l_unavailable(exec disabled)');
    $output = array();
    $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    out('php_l:' . $label . '=' . ($code === 0 ? 'ok' : 'failed'));
    if ($code !== 0) fail('php_l_failed:' . $label . ':' . implode(' ', $output));
}

function write_checked(string $path, string $bytes): void
{
    $temp = $path . '.' . PATCH_ID . '.tmp';
    if (file_put_contents($temp, $bytes, LOCK_EX) !== strlen($bytes)) { @unlink($temp); fail('temp_write_failed=' . $path); }
    if (!@rename($temp, $path)) {
        $copied = @copy($temp, $path);
        @unlink($temp);
        if (!$copied) fail('target_write_failed=' . $path);
    }
    clearstatcache(true, $path);
    if (file_get_contents($path) !== $bytes) fail('postwrite_verify_failed=' . $path);
}

function remove_tree(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($dir);
}

/* ---- the code this patch inserts ------------------------------------------ */

$callBlock = tabs(<<<'PHP'
        // TECH-044-WEBP: PNG, JPEG and WebP sources are served as WebP. Whatever resizeWebp()
        // declines comes back as '' and continues below unchanged, so without WebP support in GD
        // this method returns exactly what it returned before TECH-044.
        $webp = $this->resizeWebp($filename, $extension, $width, $height, $default);

        if ($webp) {
            return $webp;
        }


PHP);

$methodBlock = tabs(<<<'PHP'

    /**
     * TECH-044-WEBP: WebP quality, chosen from measurement in
     * diagnostics/TECH-044_wp0-baseline_report_20260929.md. Image::save() does not pass $quality
     * on to imagewebp(), whose own default is also 80; change both together or neither.
     */
    private const WEBP_QUALITY = 80;

    /**
     * Resize WebP (TECH-044-WEBP)
     *
     * Writes image/cache/<name>-WxH.webp for a PNG, JPEG or WebP source. Returns '' to hand the
     * request back to resize() when GD cannot write WebP, the file is not PNG/JPEG/WebP inside
     * (GIF animation would be lost), another source differs from this one only by extension
     * (both would share one .webp name) or no usable file came out.
     *
     * @param string $filename
     * @param string $extension
     * @param int    $width
     * @param int    $height
     * @param string $default
     *
     * @return string
     */
    private function resizeWebp(string $filename, string $extension, int $width, int $height, string $default): string {
        $types = ['png', 'jpg', 'jpeg', 'webp'];

        if (!in_array(strtolower($extension), $types) || !$this->webpSupported()) {
            return '';
        }

        $name = oc_substr($filename, 0, oc_strrpos($filename, '.'));

        foreach (array_merge($types, array_map('strtoupper', $types)) as $sibling) {
            if (strtolower($sibling) != strtolower($extension) && is_file(DIR_IMAGE . $name . '.' . $sibling)) {
                return '';
            }
        }

        $image_new = 'cache/' . $name . '-' . (int)$width . 'x' . (int)$height . '.webp';

        if (!is_file(DIR_IMAGE . $image_new) || (filemtime(DIR_IMAGE . $filename) > filemtime(DIR_IMAGE . $image_new))) {
            [$width_orig, $height_orig, $image_type] = getimagesize(DIR_IMAGE . $filename);

            if (!in_array($image_type, [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP])) {
                return '';
            }

            $path = '';

            foreach (explode('/', dirname($image_new)) as $directory) {
                $path = $path ? $path . '/' . $directory : $directory;

                if (!is_dir(DIR_IMAGE . $path)) {
                    @mkdir(DIR_IMAGE . $path, 0777);
                }
            }

            if ($image_type == IMAGETYPE_WEBP && $width_orig == $width && $height_orig == $height) {
                copy(DIR_IMAGE . $filename, DIR_IMAGE . $image_new);
            } else {
                // PNG and JPEG are re-encoded even at the target size: a .webp name never holds PNG or JPEG bytes.
                $image = new \Opencart\System\Library\Image(DIR_IMAGE . $filename);
                $image->resize($width, $height, $default);
                $image->save(DIR_IMAGE . $image_new, self::WEBP_QUALITY);
            }

            clearstatcache(true, DIR_IMAGE . $image_new);

            if (!is_file(DIR_IMAGE . $image_new) || !filesize(DIR_IMAGE . $image_new)) {
                if (is_file(DIR_IMAGE . $image_new)) {
                    unlink(DIR_IMAGE . $image_new);
                }

                return '';
            }
        }

        return $this->config->get('config_url') . 'image/' . str_replace(' ', '%20', $image_new);
    }

    /**
     * WebP Supported (TECH-044-WEBP)
     *
     * @return bool
     */
    private function webpSupported(): bool {
        static $supported = null;

        if ($supported === null) {
            $supported = function_exists('imagewebp') && function_exists('imagetypes') && defined('IMG_WEBP') && (imagetypes() & IMG_WEBP);
        }

        return $supported;
    }

PHP);

$anchorCall = "\t\t\$extension = pathinfo(\$filename, PATHINFO_EXTENSION);\n\n\t\t\$image_old = \$filename;\n";
$anchorTail = "\t\treturn \$this->config->get('config_url') . 'image/' . \$image_new;\n\t}\n}\n";

/* ---- self-test: the new class on this host's PHP + GD, in a temp directory -- */

/**
 * @return array<string, string> check => result
 */
function selftest(string $root, string $modelFile): array
{
    $base = realpath(sys_get_temp_dir());
    if ($base === false || !is_writable($base)) fail('selftest_temp_unavailable');
    $dir = str_replace('\\', '/', $base) . '/tech044-selftest-' . getmypid() . '-' . bin2hex(random_bytes(4));
    if (!mkdir($dir . '/catalog/selftest', 0777, true)) fail('selftest_temp_mkdir_failed');

    $results = array();
    set_error_handler(static function (int $no, string $str, string $file, int $line): bool {
        // Like the storefront handler, this ignores @: any warning or notice is a failure.
        throw new ErrorException($str, 0, $no, $file, $line);
    });
    try {
        define('DIR_IMAGE', $dir . '/');
        require_once $root . '/system/engine/registry.php';
        require_once $root . '/system/engine/model.php';
        require_once $root . '/system/library/image.php';
        require_once $root . '/system/helper/general.php';
        require_once $modelFile;

        $registry = new \Opencart\System\Engine\Registry();
        $registry->set('config', new class {
            public function get(string $key): string { return $key === 'config_url' ? SELFTEST_URL : ''; }
        });
        $model = new \Opencart\Catalog\Model\Tool\Image($registry);

        $src = DIR_IMAGE . 'catalog/selftest/';
        $img = imagecreatetruecolor(320, 200);           // transparent frame, opaque red centre
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagefilledrectangle($img, 110, 50, 210, 150, imagecolorallocatealpha($img, 220, 20, 20, 0));
        imagepng($img, $src . 'alpha box.png');
        imagepng($img, $src . 'twin.png');
        $sq = imagecreatetruecolor(250, 250);
        imagefill($sq, 0, 0, imagecolorallocate($sq, 30, 90, 200));
        imagejpeg($sq, $src . 'square.jpg', 90);
        imagejpeg($sq, $src . 'UPPER.JPG', 90);
        $gif = imagecreate(100, 100);
        imagecolorallocate($gif, 255, 200, 0);
        imagegif($gif, $src . 'anim.gif');

        $supported = function_exists('imagewebp') && function_exists('imagetypes') && defined('IMG_WEBP') && (imagetypes() & IMG_WEBP);
        $results['webp_supported'] = $supported ? 'yes' : 'no';
        if ($supported) {
            imagewebp($sq, $src . 'native.webp', 80);
            imagewebp($sq, $src . 'twin.webp', 80);
        }

        $url = static function (string $rel): string { return SELFTEST_URL . 'image/' . str_replace(' ', '%20', $rel); };
        $isWebp = static function (string $file): bool {
            $head = (string)file_get_contents($file, false, null, 0, 12);
            return substr($head, 0, 4) === 'RIFF' && substr($head, 8, 4) === 'WEBP';
        };
        $expect = static function (bool $ok, string $check) use (&$results): void {
            if (!$ok) fail('selftest_' . $check);
            $results[$check] = 'ok';
        };

        if ($supported) {
            $got = $model->resize('catalog/selftest/alpha box.png', 250, 250);
            $expect($got === $url('cache/catalog/selftest/alpha box-250x250.webp'), 'png_url');
            $file = DIR_IMAGE . 'cache/catalog/selftest/alpha box-250x250.webp';
            $expect($isWebp($file), 'png_is_webp');
            $out = imagecreatefromwebp($file);
            $expect(imagesx($out) === 250 && imagesy($out) === 250, 'png_size');
            $corner = imagecolorat($out, 2, 2);
            $centre = imagecolorat($out, 125, 125);
            $expect((($corner >> 24) & 0x7F) === 127, 'png_transparency_kept');
            $expect((($centre >> 24) & 0x7F) === 0 && (($centre >> 16) & 0xFF) > 180, 'png_opaque_centre_kept');
            $expect($model->resize('catalog/selftest/alpha box.png', 250, 250) === $got, 'cache_hit_same_url');

            $got = $model->resize('catalog/selftest/square.jpg', 250, 250);
            $expect($got === $url('cache/catalog/selftest/square-250x250.webp'), 'jpeg_equal_size_url');
            $expect($isWebp(DIR_IMAGE . 'cache/catalog/selftest/square-250x250.webp'), 'jpeg_equal_size_reencoded');

            $got = $model->resize('catalog/selftest/UPPER.JPG', 100, 100);
            $expect($got === $url('cache/catalog/selftest/UPPER-100x100.webp'), 'uppercase_jpg_url');

            $got = $model->resize('catalog/selftest/native.webp', 250, 250);
            $expect($got === $url('cache/catalog/selftest/native-250x250.webp'), 'webp_equal_size_url');
            $expect(md5_file(DIR_IMAGE . 'cache/catalog/selftest/native-250x250.webp') === md5_file($src . 'native.webp'), 'webp_equal_size_copied');

            $got = $model->resize('catalog/selftest/twin.png', 250, 250);
            $expect($got === $url('cache/catalog/selftest/twin-250x250.png'), 'twin_name_keeps_old_path');
        } else {
            $got = $model->resize('catalog/selftest/alpha box.png', 250, 250);
            $expect($got === $url('cache/catalog/selftest/alpha box-250x250.png'), 'no_webp_png_unchanged');
            $got = $model->resize('catalog/selftest/square.jpg', 250, 250);
            $expect($got === $url('cache/catalog/selftest/square-250x250.jpg'), 'no_webp_jpeg_unchanged');
        }

        $got = $model->resize('catalog/selftest/anim.gif', 50, 50);
        $expect($got === $url('cache/catalog/selftest/anim-50x50.gif'), 'gif_unchanged');
        $expect($model->resize('catalog/selftest/missing.png', 250, 250) === '', 'missing_file_empty');
    } catch (Throwable $error) {
        restore_error_handler();
        remove_tree($dir);
        throw $error instanceof RuntimeException ? $error : new RuntimeException('selftest_error:' . $error->getMessage());
    }
    restore_error_handler();
    remove_tree($dir);
    return $results;
}

/* ---- run ------------------------------------------------------------------ */

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

$root = rtrim(str_replace('\\', '/', (string)(getcwd() ?: __DIR__)), '/');
out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('php=' . PHP_VERSION);
out('db_changes=none');
if (!is_file($root . '/index.php') || !is_file($root . '/system/library/image.php') || !is_dir($root . '/catalog/model/tool')) {
    fail('not_opencart_webroot — upload to ~/public_html and run from there');
}

$path = $root . '/' . TARGET;
if (!is_file($path)) fail('target_not_found=' . TARGET);
if (!is_readable($path) || !is_writable($path)) fail('target_not_writable=' . TARGET);
$raw = file_get_contents($path);
if (!is_string($raw)) fail('target_read_failed=' . TARGET);

if (strpos($raw, MARKER) !== false) {
    count_exact($raw, 'private function resizeWebp(', 1, 'applied_method');
    count_exact($raw, '$webp = $this->resizeWebp(', 1, 'applied_call');
    out('already_applied=yes');
    out('done=ok');
    @unlink(__FILE__);
    exit(0);
}

out('target_sha1=' . sha1($raw));
if (sha1($raw) !== BASE_SHA1) fail('target_differs_from_audited_version — do not force; report the sha1 above');
count_exact($raw, $anchorCall, 1, 'call');
count_exact($raw, $anchorTail, 1, 'tail');
if (substr($raw, -strlen($anchorTail)) !== $anchorTail) fail('anchor_tail_not_at_end_of_file');

$new = str_replace($anchorCall, "\t\t\$extension = pathinfo(\$filename, PATHINFO_EXTENSION);\n\n" . $callBlock . "\t\t\$image_old = \$filename;\n", $raw);
$new = substr($new, 0, -strlen("}\n")) . $methodBlock . "}\n";
count_exact($new, MARKER, 4, 'marker_in_new_file');
if (strpos($new, substr($raw, strpos($raw, "\t\t\$image_old = \$filename;\n"), -strlen("}\n"))) === false) {
    fail('original_resize_body_not_preserved');
}

/* lint + self-test on a temp copy, before anything is written */
$tempModel = rtrim(str_replace('\\', '/', (string)realpath(sys_get_temp_dir())), '/') . '/' . PATCH_ID . '-' . getmypid() . '.php';
if (file_put_contents($tempModel, $new, LOCK_EX) !== strlen($new)) fail('temp_model_write_failed');
try {
    lint($tempModel, 'new_file');
    foreach (selftest($root, $tempModel) as $check => $result) out('selftest:' . $check . '=' . $result);
} finally {
    @unlink($tempModel);
}

/* backup, write, verify */
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . date('Ymd-His');
$backup = $backupDir . '/' . TARGET;
if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0755, true) && !is_dir(dirname($backup))) fail('backup_dir_failed');
if (file_put_contents($backup, $raw, LOCK_EX) !== strlen($raw) || sha1_file($backup) !== BASE_SHA1) fail('backup_write_failed');
out('backup=' . substr($backup, strlen($root) + 1));

try {
    write_checked($path, $new);
    lint($path, TARGET);
} catch (Throwable $error) {
    $restored = file_put_contents($path, $raw, LOCK_EX) === strlen($raw);
    clearstatcache(true, $path);
    out('restore=' . ($restored && sha1_file($path) === BASE_SHA1 ? 'ok' : 'FAILED — copy ' . substr($backup, strlen($root) + 1) . ' back to ' . TARGET));
    throw $error;
}

out('changed=' . TARGET . ' sha1=' . sha1($new));
out('already_applied=no');
out('done=ok');
out('next=open each Tier 1 page twice (first open generates the .webp files), then run the TECH-044 QA list');
@unlink(__FILE__);
