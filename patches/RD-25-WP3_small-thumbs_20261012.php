<?php
/**
 * RD-25-WP3_small-thumbs_20261012: 52x65 mini-cart/cart fit thumbnails; commerce controls unchanged.
 * Approved Phase 0 20261012; review C1-C3 and owner go WP2-WP3.
 * Source: RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz + fresh GA4 model.
 * Root causes: gallery square/grey and RD-10 repeated height caps; cart cover squares.
 * Edit original gallery / RD-12 mini-cart / RD-11 cart CSS, no override stack.
 * Reuses unchanged WP1 v4 mediaInfo/fit-v2 resizeFit, admission budget and LOCK_NB.
 * All GA4 anchors/counts pinned; commerce fields, controls, JSON-LD, popup/og unchanged.
 * No DB writes, new classifier, media-cache deletion, vendor or checkout-summary rewrite.
 * CLI PHP >=8.0. All targets/gates preflight; backups precede writes; atomic readback;
 * PHP candidate+target lint; failure restores originals. Repeat preserves later asset token.
 * Usage: php RD-25-WP3_small-thumbs_20261012.php [--dry-run]
 * Rollback: re-upload SAME runner; php RD-25-WP3_small-thumbs_20261012.php --rollback=_patch_backups/<printed-directory>
 * Rollback order WP3, then WP2. Each receipt refuses changed targets or mixed states.
 * Apply/repeat/rollback self-delete; dry-run/failure retain runner. Clear compiled Twig
 * cache after both applies or rollback (owner command in report); keep rd25 fit/metadata.
 * Owner QA: gallery at 1440/1024/390/360; bs-checkout-smoke steps 1-4, no order submit.
 */
declare(strict_types=1);
const RD25_TASK = 'RD-25-WP3_small-thumbs_20261012';
const RD25_MARKER = 'RD-25-WP3 20261012';
const RD25_TOKEN = 'rd25-wp3-20261012';
const RD25_PREFIX = 'catalog/view/stylesheet/boostershop-ds.css?v=';
const RD25_HEADER = 'catalog/view/template/common/header.twig';
const RD25_CSS = 'catalog/view/stylesheet/boostershop-ds.css';

function out(string $key, string $value): void { echo $key . '=' . $value . PHP_EOL; }
function fail(string $message): void { throw new RuntimeException($message); }
function readFileSafe(string $path): string {
    $content = @file_get_contents($path);
    if ($content === false) { fail('file_read_failed:' . $path); }
    return $content;
}
function normalize(string $path, string $content): string {
    if ($path !== RD25_HEADER) { return $content; }
    $pattern = '~(' . preg_quote(RD25_PREFIX, '~') . ')([A-Za-z0-9._-]{1,64})(?=["\'])~';
    if (preg_match_all($pattern, $content) !== 1 || substr_count($content, RD25_PREFIX) !== 1) { fail('header_token_anchor_invalid'); }
    return preg_replace($pattern, '${1}__RD25_TOKEN__', $content, 1);
}
function mkdirSafe(string $path): void {
    if (!is_dir($path) && !@mkdir($path, 0755, true) && !is_dir($path)) { fail('mkdir_failed:' . $path); }
}
function writeAtomic(string $path, string $content): void {
    $mode = is_file($path) ? (fileperms($path) & 0777) : 0644;
    $temp = @tempnam(dirname($path), 'rd25-write-');
    if (!$temp) { fail('temp_failed:' . $path); }
    if (@file_put_contents($temp, $content, LOCK_EX) !== strlen($content) || !@chmod($temp, $mode) || !@rename($temp, $path)) {
        @unlink($temp); fail('file_write_failed:' . $path);
    }
}
function lint(string $path): void {
    if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') { return; }
    $output = []; $exit = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $exit);
    if ($exit !== 0) { fail('php_lint_failed:' . $path); }
    out('php_lint', 'ok:' . $path);
}
function finish(bool $delete): void {
    out('done', 'ok');
    if ($delete) { out('self_delete', @unlink(__FILE__) ? 'ok' : 'failed'); }
}
function checkAnchors(string $root, array $anchors, array $overrides = []): void {
    foreach ($anchors as $anchor) {
        $code = $overrides[$anchor['template']] ?? readFileSafe($root . '/' . $anchor['template']);
        if (substr_count($code, $anchor['search']) !== $anchor['count']) { fail('ga4_anchor_count:' . $anchor['template'] . ':' . $anchor['index']); }
    }
}
// Readable JSON stores exact replacement bytes and pinned SHA256 values; no external dependency.
$spec = json_decode(<<<'RD25_SPEC'
{
  "targets": {
    "catalog/controller/common/cart.php": {
      "before_sha256": "0f42ca6f73bb776c1fd1ea6f904d06e875a613ccc29c58a09bae2f9f048f78c8",
      "replacements": [
        {
          "before": "\t\t\t$data['products'][] = [\n\t\t\t\t'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),",
          "after": "\t\t\t// RD-25-WP3 20261012: use the existing row source; LOCK_NB/budget refusal keeps legacy thumb.\n\t\t\t$bs_small = ['bs_fit_thumb' => '', 'bs_media_kind' => 'legacy', 'bs_media_width' => 120, 'bs_media_height' => 120];\n\t\t\t$bs_fit = $this->model_tool_image->resizeFit($product['image'], 104, 130);\n\t\t\tif ($bs_fit !== '' && ($bs_media = $this->model_tool_image->mediaInfo($product['image']))) {\n\t\t\t\t$bs_scale = min(1.0, 104 / $bs_media['crop_width'], 130 / $bs_media['crop_height']);\n\t\t\t\t$bs_small = ['bs_fit_thumb' => $bs_fit, 'bs_media_kind' => $bs_media['kind'],\n\t\t\t\t\t'bs_media_width' => max(1, (int)floor($bs_media['crop_width'] * $bs_scale)),\n\t\t\t\t\t'bs_media_height' => max(1, (int)floor($bs_media['crop_height'] * $bs_scale))];\n\t\t\t}\n\n\t\t\t$data['products'][] = [\n\t\t\t\t'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),"
        },
        {
          "before": "\t\t\t] + $product;",
          "after": "\t\t\t] + $bs_small + $product;"
        }
      ],
      "after_sha256": "91f8be5d269bc86adcc76a3a58d76b93a9c74698429ad9cbdb2bc24e7000ab8e"
    },
    "catalog/controller/checkout/cart.php": {
      "before_sha256": "ec527cb02545378297df1b637a0c5647fef6e4e5b8f1255ae1aa75a793b06f10",
      "replacements": [
        {
          "before": "\t\t\t$data['products'][] = [\n\t\t\t\t'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),",
          "after": "\t\t\t// RD-25-WP3 20261012: use the existing row source; LOCK_NB/budget refusal keeps legacy thumb.\n\t\t\t$bs_small = ['bs_fit_thumb' => '', 'bs_media_kind' => 'legacy', 'bs_media_width' => 120, 'bs_media_height' => 120];\n\t\t\t$bs_fit = $this->model_tool_image->resizeFit($product['image'], 104, 130);\n\t\t\tif ($bs_fit !== '' && ($bs_media = $this->model_tool_image->mediaInfo($product['image']))) {\n\t\t\t\t$bs_scale = min(1.0, 104 / $bs_media['crop_width'], 130 / $bs_media['crop_height']);\n\t\t\t\t$bs_small = ['bs_fit_thumb' => $bs_fit, 'bs_media_kind' => $bs_media['kind'],\n\t\t\t\t\t'bs_media_width' => max(1, (int)floor($bs_media['crop_width'] * $bs_scale)),\n\t\t\t\t\t'bs_media_height' => max(1, (int)floor($bs_media['crop_height'] * $bs_scale))];\n\t\t\t}\n\n\t\t\t$data['products'][] = [\n\t\t\t\t'thumb'        => $this->model_tool_image->resize($product['image'], $this->config->get('config_image_cart_width'), $this->config->get('config_image_cart_height')),"
        },
        {
          "before": "\t\t\t] + $product;",
          "after": "\t\t\t] + $bs_small + $product;"
        }
      ],
      "after_sha256": "d4b240b0987fac525a2e51bb59b0500a5779594afe3bcfde95ae634aeb515753"
    },
    "catalog/view/template/common/cart.twig": {
      "before_sha256": "511214cad1d69a218ae47b4d1c0226620f97336ff0d7a6af4e258b1019dcf0dc",
      "replacements": [
        {
          "before": "{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}",
          "after": "{# RD-25-WP3 20261012: fit thumbnails; direct row > a > img retained for checkout DOM copy. #}\n{# RD-12 mini-cart drawer; AJAX URLs and controller values are unchanged. #}"
        },
        {
          "before": "<article class=\"bs-mini-cart__row\">",
          "after": "<article class=\"bs-mini-cart__row\" data-bs-media-kind=\"{{ product.bs_media_kind }}\">"
        },
        {
          "before": "<img src=\"{{ product.thumb }}\" alt=\"{{ product.name }}\"/>",
          "after": "<img src=\"{{ product.bs_fit_thumb ?: product.thumb }}\" alt=\"{{ product.name }}\" width=\"{{ product.bs_media_width }}\" height=\"{{ product.bs_media_height }}\" loading=\"lazy\" decoding=\"async\"/>"
        }
      ],
      "after_sha256": "0f2cd5891b519b9f516335cb957713cd0295ecf06fe17ff2eccbe8be2794a2fc"
    },
    "catalog/view/template/checkout/cart_list.twig": {
      "before_sha256": "cfe6d57209ca0938e1044679b8e66379bd1e46a47528d2a120618a49e4d950b7",
      "replacements": [
        {
          "before": "{# RD-11 cart list: controller-supplied values only. #}",
          "after": "{# RD-25-WP3 20261012: image display only; quantity and remove controls unchanged. #}\n  {# RD-11 cart list: controller-supplied values only. #}"
        },
        {
          "before": "<a class=\"bs-cart-row__thumb\" href=\"{{ product.href }}\">",
          "after": "<a class=\"bs-cart-row__thumb\" href=\"{{ product.href }}\" data-bs-media-kind=\"{{ product.bs_media_kind }}\">"
        },
        {
          "before": "<img src=\"{{ product.thumb }}\" alt=\"{{ product.name }}\" title=\"{{ product.name }}\"/>",
          "after": "<img src=\"{{ product.bs_fit_thumb ?: product.thumb }}\" alt=\"{{ product.name }}\" title=\"{{ product.name }}\" width=\"{{ product.bs_media_width }}\" height=\"{{ product.bs_media_height }}\" loading=\"lazy\" decoding=\"async\"/>"
        }
      ],
      "after_sha256": "a99e4108d7819640410ddce3522c874da73907d270a72e3bf29c210e49b09eb2"
    },
    "catalog/view/stylesheet/boostershop-ds.css": {
      "before_sha256": "265f69431271fb921d6529c4909d5eee40b8566c4e70374f4132e90bde212a44",
      "replacements": [
        {
          "before": ".bs-mini-cart__row{display:grid;grid-template-columns:56px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}.bs-mini-cart__row img{width:56px;height:56px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}",
          "after": "/* RD-25-WP3 20261012: 52x65 slots; cut inset left8/top6/width84/height94%; photos full-slot. */\n.bs-mini-cart__row{display:grid;grid-template-columns:52px minmax(0,1fr);gap:12px;padding:14px 0;border-bottom:1px solid var(--bs-line-2)}\n.bs-mini-cart__row > a{display:grid;box-sizing:border-box;width:52px;height:65px;grid-template-columns:1fr;grid-template-rows:1fr}\n.bs-mini-cart__row[data-bs-media-kind=\"cut\"] > a{grid-template-columns:8% 84% 8%;grid-template-rows:6% 94%}\n.bs-mini-cart__row[data-bs-media-kind=\"cut\"] > a > img{grid-column:2;grid-row:2}\n.bs-mini-cart__row img{display:block;width:100%;height:100%;min-width:0;min-height:0;object-fit:contain;object-position:center bottom;border-radius:var(--bs-r-sm)}"
        }
      ],
      "after_sha256": "213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef"
    },
    "catalog/view/template/checkout/cart.twig": {
      "before_sha256": "847d9356bb49c3c2b471bd1119f7621d5c9c2a641264570f2b5890945886eb48",
      "replacements": [
        {
          "before": "/* RD-11 cart page */",
          "after": "/* RD-25-WP3 20261012: 52x65 image slots; cut inset left8/top6/width84/height94%; photos full-slot. */\n/* RD-11 cart page */"
        },
        {
          "before": ".bs-cart-row{display:grid;grid-template-columns:96px minmax(0,1fr);gap:16px;padding:18px 0;border-bottom:1px solid var(--bs-line-2)}.bs-cart-row__thumb img{display:block;width:96px;height:96px;object-fit:cover;border:1px solid var(--bs-line);border-radius:var(--bs-r-sm)}",
          "after": ".bs-cart-row{display:grid;grid-template-columns:52px minmax(0,1fr);gap:16px;padding:18px 0;border-bottom:1px solid var(--bs-line-2)}.bs-cart-row__thumb{display:grid;box-sizing:border-box;width:52px;height:65px;grid-template-columns:1fr;grid-template-rows:1fr}.bs-cart-row__thumb[data-bs-media-kind=\"cut\"]{grid-template-columns:8% 84% 8%;grid-template-rows:6% 94%}.bs-cart-row__thumb[data-bs-media-kind=\"cut\"] img{grid-column:2;grid-row:2}.bs-cart-row__thumb img{display:block;width:100%;height:100%;min-width:0;min-height:0;object-fit:contain;object-position:center bottom;border-radius:var(--bs-r-sm)}"
        },
        {
          "before": ".bs-cart-row{grid-template-columns:72px minmax(0,1fr);gap:12px;padding:14px 0}.bs-cart-row__thumb img{width:72px;height:72px}",
          "after": ".bs-cart-row{grid-template-columns:52px minmax(0,1fr);gap:12px;padding:14px 0}"
        }
      ],
      "after_sha256": "633bb4f7e0a0a62e82bd6561343d2b819bde41171868284ad3f4bfa0dc4d9252"
    },
    "catalog/view/template/common/header.twig": {
      "before_sha256": "eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee",
      "after_sha256": "eec16fbe81c72332d44ab2ecc7ee9f8a613a39206a21ab40ee04d7e2b7007cee",
      "replacements": [
        {
          "before": "catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__",
          "after": "catalog/view/stylesheet/boostershop-ds.css?v=__RD25_TOKEN__"
        }
      ]
    }
  },
  "context": {
    "catalog/model/tool/image.php": "85cf9489244c48fe915bc25f370cdfdb2b39c9c6d6a42219cec7f84a9e375e71",
    "system/library/image.php": "7f81dcbb8b5d2d032005ae28ce01cea5ba7542f7334fd9909e538f6952adeff9",
    "catalog/controller/product/thumb.php": "cf4f8e354b18a4318af159c34bf3c083d0991007b42d03e2c57aa4963c1ed3b1",
    "catalog/view/template/product/thumb.twig": "d9237b2c9c0cc21cda73a86ba6cf1ff66b7ad956f0da3c24a8e1add6518037d6",
    "extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php": "eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4",
    "catalog/controller/product/product.php": "74b937525c579a56f67fb62d23cf75f0456e0bc5f034479e600de38e843bb84a",
    "catalog/view/template/product/product.twig": "abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec"
  },
  "ga4_anchors": [
    {
      "template": "catalog/view/template/common/header.twig",
      "search": "<body>",
      "index": 0,
      "count": 0
    },
    {
      "template": "catalog/view/template/common/header.twig",
      "search": "</head>",
      "index": 2,
      "count": 1
    },
    {
      "template": "catalog/view/template/product/product.twig",
      "search": "<button type=\"submit\" id=\"button-cart\"",
      "index": 0,
      "count": 2
    },
    {
      "template": "catalog/view/template/product/product.twig",
      "search": "<button type=\"submit\" formaction=\"{{ add_to_wishlist }}\"",
      "index": 1,
      "count": 0
    },
    {
      "template": "catalog/view/template/product/product.twig",
      "search": "if (json['success']) {",
      "index": 2,
      "count": 1
    },
    {
      "template": "catalog/view/template/product/product.twig",
      "search": "{{ footer }}",
      "index": 3,
      "count": 1
    },
    {
      "template": "catalog/view/template/common/cart.twig",
      "search": "<a href=\"{{ product.href }}\">",
      "index": 0,
      "count": 2
    },
    {
      "template": "catalog/view/template/common/cart.twig",
      "search": "<button type=\"submit\" data-bs-toggle=\"tooltip\" title=\"{{ button_remove }}\"",
      "index": 1,
      "count": 0
    },
    {
      "template": "catalog/view/template/common/cart.twig",
      "search": "<button type=\"button\" data-bs-toggle=\"dropdown\"",
      "index": 2,
      "count": 0
    },
    {
      "template": "catalog/view/template/checkout/cart.twig",
      "search": "<div id=\"shopping-cart\">{{ list }}</div>",
      "index": 0,
      "count": 1
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<a href=\"{{ product.href }}\">",
      "index": 0,
      "count": 0
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<input type=\"text\" name=\"quantity\"",
      "index": 1,
      "count": 1
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<button type=\"submit\" formaction=\"{{ product_edit }}\"",
      "index": 2,
      "count": 0
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<button type=\"submit\" formaction=\"{{ product_remove }}\"",
      "index": 3,
      "count": 0
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<table class=\"table table-bordered\">",
      "index": 4,
      "count": 0
    },
    {
      "template": "catalog/view/template/product/product.twig",
      "search": "<button type=\"submit\" formaction=\"{{ wishlist_add }}\"",
      "index": 1,
      "count": 0
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<button type=\"submit\" formaction=\"{{ edit }}\"",
      "index": 2,
      "count": 1
    },
    {
      "template": "catalog/view/template/checkout/cart_list.twig",
      "search": "<a href=\"{{ product.remove }}\"",
      "index": 3,
      "count": 1
    }
  ],
  "repeat_hashes": {
    "catalog/controller/common/cart.php": [
      "91f8be5d269bc86adcc76a3a58d76b93a9c74698429ad9cbdb2bc24e7000ab8e"
    ],
    "catalog/controller/checkout/cart.php": [
      "d4b240b0987fac525a2e51bb59b0500a5779594afe3bcfde95ae634aeb515753"
    ],
    "catalog/view/template/common/cart.twig": [
      "0f2cd5891b519b9f516335cb957713cd0295ecf06fe17ff2eccbe8be2794a2fc"
    ],
    "catalog/view/template/checkout/cart_list.twig": [
      "a99e4108d7819640410ddce3522c874da73907d270a72e3bf29c210e49b09eb2"
    ],
    "catalog/view/stylesheet/boostershop-ds.css": [
      "213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef"
    ],
    "catalog/view/template/checkout/cart.twig": [
      "633bb4f7e0a0a62e82bd6561343d2b819bde41171868284ad3f4bfa0dc4d9252"
    ]
  }
}
RD25_SPEC, true, 512, JSON_THROW_ON_ERROR);
$targets = $spec['targets'];
$originals = []; $written = []; $backup = ''; $lock = null;
try {
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000 || !function_exists('exec')) { fail('requires_cli_php80_and_exec'); }
    lint(__FILE__);
    $root = str_replace('\\', '/', (string)realpath(getcwd()));
    out('task', RD25_TASK); out('cwd', $root); out('time', gmdate('c'));
    if (!is_file($root . '/catalog/model/tool/image.php')) { fail('run_from_public_html'); }
    $dry = false; $rollback = '';
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--dry-run') { $dry = true; }
        elseif (strpos($arg, '--rollback=') === 0) { $rollback = substr($arg, 11); }
        else { fail('unknown_argument'); }
    }
    if ($dry && $rollback !== '') { fail('rollback_dry_run_not_supported'); }
    $lock = @fopen(__FILE__, 'r');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { fail('another_runner_is_active'); }
    foreach ($targets as $relative => $target) {
        $path = $root . '/' . $relative;
        if (!is_file($path) || !is_writable($path) || !is_writable(dirname($path)) || str_replace('\\', '/', (string)realpath($path)) !== $path) { fail('target_missing_unwritable_or_symlink:' . $relative); }
        $originals[$relative] = readFileSafe($path);
    }
    if ($rollback !== '') {
        if (!preg_match('~\A_patch_backups/' . preg_quote(RD25_TASK, '~') . '-[0-9]{8}_[0-9]{6}-[a-f0-9]{6}\z~', $rollback)) { fail('rollback_path_invalid'); }
        $base = realpath($root . '/_patch_backups'); $folder = realpath($root . '/' . $rollback);
        if (!$base || !$folder || str_replace('\\', '/', $folder) !== $root . '/' . $rollback || is_link($folder)) { fail('rollback_outside_task_backup'); }
        $receipt = json_decode(readFileSafe($folder . '/manifest.json'), true);
        if (!is_array($receipt) || ($receipt['task'] ?? '') !== RD25_TASK || ($receipt['state'] ?? '') !== 'APPLIED' || array_keys($receipt['files'] ?? []) !== array_keys($targets)) { fail('rollback_manifest_invalid'); }
        $restore = [];
        foreach ($targets as $p => $target) {
            $restore[$p] = readFileSafe($folder . '/original/' . $p); $entry = $receipt['files'][$p];
            if (hash('sha256', $originals[$p]) !== ($entry['after_sha256'] ?? '') || hash('sha256', $restore[$p]) !== ($entry['before_sha256'] ?? '') || hash('sha256', normalize($p, $restore[$p])) !== $target['before_sha256']) { fail('rollback_source_changed:' . $p); }
            lint($folder . '/original/' . $p);
        }
        foreach ($restore as $p => $content) {
            $written[] = $p; writeAtomic($root . '/' . $p, $content); lint($root . '/' . $p);
            if (readFileSafe($root . '/' . $p) !== $content) { fail('rollback_readback_failed:' . $p); }
            out('restored_file', $p);
        }
        $receipt['state'] = 'ROLLED_BACK'; writeAtomic($folder . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        flock($lock, LOCK_UN); fclose($lock); $lock = null; out('rollback', 'ok'); finish(true); exit(0);
    }
    foreach ($spec['context'] as $p => $hash) {
        if (!is_file($root . '/' . $p) || hash('sha256', readFileSafe($root . '/' . $p)) !== $hash) { fail('context_sha256_mismatch:' . $p); }
        out('context_file', $p);
    }
    checkAnchors($root, $spec['ga4_anchors']);
    $marked = [];
    foreach ($targets as $p => $target) {
        if ($p === RD25_HEADER) { continue; }
        $count = substr_count($originals[$p], RD25_MARKER);
        if ($count > 1) { fail('duplicate_marker:' . $p); }
        $marked[] = $count;
    }
    if (array_sum($marked) !== 0 && array_sum($marked) !== count($marked)) { fail('partial_or_mixed_markers'); }
    if (array_sum($marked) === count($marked)) {
        foreach ($targets as $p => $target) {
            if ($p === RD25_HEADER) {
                if (hash('sha256', normalize($p, $originals[$p])) !== $target['after_sha256']) { fail('applied_header_changed'); }
                continue;
            }
            if (!in_array(hash('sha256', $originals[$p]), $spec['repeat_hashes'][$p], true)) { fail('applied_content_changed:' . $p); }
        }
        // WP2 repeat after WP3 also verifies the other WP3 files, rejecting a partial later stage.
        if (isset($spec['later_css_hash']) && hash('sha256', $originals[RD25_CSS]) === $spec['later_css_hash']) {
            foreach ($spec['later_files'] as $p => $hash) {
                if (hash('sha256', readFileSafe($root . '/' . $p)) !== $hash) { fail('later_stage_partial:' . $p); }
            }
        }
        flock($lock, LOCK_UN); fclose($lock); $lock = null; out('already_applied', 'yes'); finish(!$dry); exit(0);
    }
    $candidates = [];
    foreach ($targets as $p => $target) {
        $code = normalize($p, $originals[$p]);
        if (hash('sha256', $code) !== $target['before_sha256']) { fail('source_sha256_mismatch:' . $p); }
        foreach ($target['replacements'] as $index => $replace) {
            if (substr_count($code, $replace['before']) !== 1) { fail('anchor_count_invalid:' . $p . ':' . $index); }
            $code = str_replace($replace['before'], $replace['after'], $code);
        }
        if (hash('sha256', $code) !== $target['after_sha256']) { fail('candidate_sha256_mismatch:' . $p); }
        $candidates[$p] = $p === RD25_HEADER ? str_replace('__RD25_TOKEN__', RD25_TOKEN, $code) : $code;
        out('preflight_file', $p);
    }
    checkAnchors($root, $spec['ga4_anchors'], $candidates);
    if ($dry) { flock($lock, LOCK_UN); fclose($lock); $lock = null; out('dry_run', 'yes'); out('target_writes', '0'); finish(false); exit(0); }
    mkdirSafe($root . '/_patch_backups');
    if (str_replace('\\', '/', (string)realpath($root . '/_patch_backups')) !== $root . '/_patch_backups') { fail('unsafe_backup_root'); }
    $backup = $root . '/_patch_backups/' . RD25_TASK . '-' . gmdate('Ymd_His') . '-' . bin2hex(random_bytes(3));
    $receipt = ['task' => RD25_TASK, 'state' => 'PREPARED', 'created_at' => gmdate('c'), 'files' => []];
    foreach ($targets as $p => $target) {
        mkdirSafe(dirname($backup . '/original/' . $p)); writeAtomic($backup . '/original/' . $p, $originals[$p]);
        if (readFileSafe($backup . '/original/' . $p) !== $originals[$p]) { fail('backup_verify_failed:' . $p); }
        mkdirSafe(dirname($backup . '/candidate/' . $p)); writeAtomic($backup . '/candidate/' . $p, $candidates[$p]); lint($backup . '/candidate/' . $p);
        $receipt['files'][$p] = ['before_sha256' => hash('sha256', $originals[$p]), 'after_sha256' => hash('sha256', $candidates[$p])];
    }
    writeAtomic($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"); out('backup', $backup);
    foreach ($originals as $p => $content) { if (readFileSafe($root . '/' . $p) !== $content) { fail('source_changed_after_preflight:' . $p); } }
    foreach ($candidates as $p => $content) {
        $written[] = $p; writeAtomic($root . '/' . $p, $content); lint($root . '/' . $p);
        if (readFileSafe($root . '/' . $p) !== $content) { fail('write_readback_failed:' . $p); }
        out('changed_file', $p);
    }
    $receipt['state'] = 'APPLIED'; writeAtomic($backup . '/manifest.json', json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    flock($lock, LOCK_UN); fclose($lock); $lock = null; out('changed_files', (string)count($targets)); finish(true);
} catch (Throwable $error) {
    $failed = false;
    foreach (array_reverse(array_unique($written)) as $p) {
        try { writeAtomic(getcwd() . '/' . $p, $originals[$p]); if (readFileSafe(getcwd() . '/' . $p) !== $originals[$p]) { fail('restore_readback'); } lint(getcwd() . '/' . $p); out('restored_after_failure', $p); }
        catch (Throwable $restore_error) { $failed = true; out('restore_error', $p); }
    }
    if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); }
    out('error', $error->getMessage()); if ($backup !== '') { out('backup', $backup); }
    out('restore', $failed ? 'FAILED_OWNER_ACTION_REQUIRED' : ($written ? 'ok' : 'not_needed')); exit(1);
}
