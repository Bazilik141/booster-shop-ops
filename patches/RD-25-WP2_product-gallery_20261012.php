<?php
/**
 * RD-25-WP2_product-gallery_20261012: 4:5 product gallery with keyboard selection and original lightbox URLs.
 * Approved Phase 0 20261012; review C1-C3 and owner go WP2-WP3.
 * Source: RD-25-WP2-WP3-check-20261012-094705-34448.tar.gz + fresh GA4 model.
 * Root causes: gallery square/grey and RD-10 repeated height caps; cart cover squares.
 * Edit original gallery / RD-12 mini-cart / RD-11 cart CSS, no override stack.
 * Reuses unchanged WP1 v4 mediaInfo/fit-v2 resizeFit, admission budget and LOCK_NB.
 * All GA4 anchors/counts pinned; commerce fields, controls, JSON-LD, popup/og unchanged.
 * No DB writes, new classifier, media-cache deletion, vendor or checkout-summary rewrite.
 * CLI PHP >=8.0. All targets/gates preflight; backups precede writes; atomic readback;
 * PHP candidate+target lint; failure restores originals. Repeat preserves later asset token.
 * Usage: php RD-25-WP2_product-gallery_20261012.php [--dry-run]
 * Rollback: re-upload SAME runner; php RD-25-WP2_product-gallery_20261012.php --rollback=_patch_backups/<printed-directory>
 * Rollback order WP3, then WP2. Each receipt refuses changed targets or mixed states.
 * Apply/repeat/rollback self-delete; dry-run/failure retain runner. Clear compiled Twig
 * cache after both applies or rollback (owner command in report); keep rd25 fit/metadata.
 * Owner QA: gallery at 1440/1024/390/360; bs-checkout-smoke steps 1-4, no order submit.
 */
declare(strict_types=1);
const RD25_TASK = 'RD-25-WP2_product-gallery_20261012';
const RD25_MARKER = 'RD-25-WP2 20261012';
const RD25_TOKEN = 'rd25-wp2-20261012';
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
    "catalog/controller/product/product.php": {
      "before_sha256": "4d9ee4e7fcb9042a46817801a6dfb973c471823751c87010cf70e0b07d7e345d",
      "replacements": [
        {
          "before": "\t\t\t$this->load->model('tool/image');",
          "after": "\t\t\t$this->load->model('tool/image');\n\t\t\t// RD-25-WP2 20261012: view-only fit payload; legacy popup/thumb/images stay intact.\n\t\t\t$data['bs_gallery'] = [];\n\t\t\t$bs_gallery_item = function (string $filename, string $popup, string $legacy, int $legacy_width, int $legacy_height): array {\n\t\t\t\t$item = ['popup' => $popup, 'main' => $legacy, 'small' => $legacy,\n\t\t\t\t\t'kind' => 'legacy', 'main_width' => $legacy_width, 'main_height' => $legacy_height,\n\t\t\t\t\t'small_width' => $legacy_width, 'small_height' => $legacy_height];\n\t\t\t\t$main = $this->model_tool_image->resizeFit($filename, 600, 750);\n\t\t\t\t$small = $this->model_tool_image->resizeFit($filename, 128, 160);\n\t\t\t\t$media = $this->model_tool_image->mediaInfo($filename);\n\t\t\t\tif ($media && ($main !== '' || $small !== '')) {\n\t\t\t\t\t$item['kind'] = $media['kind'];\n\t\t\t\t\tforeach (['main' => [600, 750, $main], 'small' => [128, 160, $small]] as $key => $fit) {\n\t\t\t\t\t\tif ($fit[2] === '') { continue; }\n\t\t\t\t\t\t$scale = min(1.0, $fit[0] / $media['crop_width'], $fit[1] / $media['crop_height']);\n\t\t\t\t\t\t$item[$key] = $fit[2];\n\t\t\t\t\t\t$item[$key . '_width'] = max(1, (int)floor($media['crop_width'] * $scale));\n\t\t\t\t\t\t$item[$key . '_height'] = max(1, (int)floor($media['crop_height'] * $scale));\n\t\t\t\t\t}\n\t\t\t\t}\n\t\t\t\treturn $item;\n\t\t\t};"
        },
        {
          "before": "\t\t\t$data['images'] = [];",
          "after": "\t\t\tif ($data['thumb'] !== '') {\n\t\t\t\t$data['bs_gallery'][] = $bs_gallery_item($product_info['image'], $data['popup'], $data['thumb'], (int)$this->config->get('config_image_thumb_width'), (int)$this->config->get('config_image_thumb_height'));\n\t\t\t}\n\n\t\t\t$data['images'] = [];"
        },
        {
          "before": "\t\t\t\t\t\t'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height'))\n\t\t\t\t\t];",
          "after": "\t\t\t\t\t\t'thumb' => $this->model_tool_image->resize($result['image'], $this->config->get('config_image_additional_width'), $this->config->get('config_image_additional_height'))\n\t\t\t\t\t];\n\t\t\t\t\t$bs_legacy_image = $data['images'][count($data['images']) - 1];\n\t\t\t\t\t$data['bs_gallery'][] = $bs_gallery_item($result['image'], $bs_legacy_image['popup'], $bs_legacy_image['thumb'], (int)$this->config->get('config_image_additional_width'), (int)$this->config->get('config_image_additional_height'));"
        }
      ],
      "after_sha256": "74b937525c579a56f67fb62d23cf75f0456e0bc5f034479e600de38e843bb84a"
    },
    "catalog/view/template/product/product.twig": {
      "before_sha256": "0f4f38030e4bf1e9d42260f2b26c6793907277f3fae5a70a18003a25ce63069e",
      "replacements": [
        {
          "before": "        <div class=\"bs-product__gallery magnific-popup\">\n          {% if thumb %}\n          <div class=\"bs-product__main-img\">\n            <a href=\"{{ popup }}\" title=\"{{ heading_title }}\">\n              <img src=\"{{ thumb }}\" title=\"{{ heading_title }}\" alt=\"{{ heading_title }}\" width=\"400\" height=\"400\" decoding=\"async\"/>\n            </a>\n          </div>\n          {% endif %}\n          {% if images %}\n          <div class=\"bs-product__thumbs\">\n            {% for image in images %}\n              <a href=\"{{ image.popup }}\" title=\"{{ heading_title }}\">\n                <img src=\"{{ image.thumb }}\" title=\"{{ heading_title }}\" alt=\"{{ heading_title }}\" width=\"72\" height=\"72\" loading=\"lazy\" decoding=\"async\"/>\n              </a>\n            {% endfor %}\n          </div>\n          {% endif %}\n        </div>\n        {% endif %}",
          "after": "        {# RD-25-WP2 20261012: gallery-only data; legacy images remains additional-only for JSON-LD. #}\n        {% if bs_gallery %}\n        {% set bs_main = bs_gallery|first %}\n        <div class=\"bs-product__gallery magnific-popup{% if bs_gallery|length > 1 %} bs-product__gallery--rail{% endif %}\" data-bs-gallery>\n          <div class=\"bs-product__main-img\">\n            <a href=\"{{ bs_main.popup }}\" title=\"{{ heading_title }}\" data-bs-gallery-main data-bs-media-kind=\"{{ bs_main.kind }}\">\n              <img src=\"{{ bs_main.main }}\" title=\"{{ heading_title }}\" alt=\"{{ heading_title }}\" width=\"{{ bs_main.main_width }}\" height=\"{{ bs_main.main_height }}\" decoding=\"async\"/>\n            </a>\n          </div>\n          {% if bs_gallery|length > 1 %}\n          <div class=\"bs-product__thumbs\" aria-label=\"Фотографії товару\">\n            {% for image in bs_gallery %}\n              <a href=\"{{ image.popup }}\" title=\"{{ heading_title }}\" data-bs-gallery-thumb data-bs-media-kind=\"{{ image.kind }}\" data-bs-gallery-src=\"{{ image.main|escape('html_attr') }}\" data-bs-gallery-width=\"{{ image.main_width }}\" data-bs-gallery-height=\"{{ image.main_height }}\" aria-label=\"Фото {{ loop.index }}: {{ heading_title|escape('html_attr') }}\"{% if loop.first %} aria-current=\"true\"{% endif %}>\n                <img src=\"{{ image.small }}\" title=\"{{ heading_title }}\" alt=\"{{ heading_title }}\" width=\"{{ image.small_width }}\" height=\"{{ image.small_height }}\" loading=\"lazy\" decoding=\"async\"/>\n              </a>\n            {% endfor %}\n          </div>\n          {% endif %}\n        </div>\n        {% endif %}\n        {% endif %}"
        },
        {
          "before": "    $('.magnific-popup').magnificPopup({\n        type: 'image',\n        delegate: 'a',\n        gallery: {\n            enabled: true\n        }\n    });",
          "after": "    // RD-25-WP2 gallery switcher; literal attributes preserve the existing 700x700 URLs.\n    $('[data-bs-gallery]').each(function() {\n        var gallery = this;\n        var main = gallery.querySelector('[data-bs-gallery-main]');\n        var thumbs = Array.prototype.slice.call(gallery.querySelectorAll('[data-bs-gallery-thumb]'));\n        var selected = 0;\n        if (!main) { return; }\n        $(gallery).on('click.rd25Gallery', '[data-bs-gallery-thumb]', function(event) {\n            event.preventDefault();\n            selected = thumbs.indexOf(this);\n            main.setAttribute('href', this.getAttribute('href'));\n            main.setAttribute('data-bs-media-kind', this.getAttribute('data-bs-media-kind'));\n            var image = main.querySelector('img');\n            image.setAttribute('src', this.getAttribute('data-bs-gallery-src'));\n            image.setAttribute('width', this.getAttribute('data-bs-gallery-width'));\n            image.setAttribute('height', this.getAttribute('data-bs-gallery-height'));\n            thumbs.forEach(function(thumb) { thumb.removeAttribute('aria-current'); });\n            this.setAttribute('aria-current', 'true');\n        });\n        $(main).on('click.rd25Gallery', function(event) {\n            event.preventDefault();\n            var links = thumbs.length ? thumbs : [main];\n            $.magnificPopup.open({\n                items: links.map(function(link) { return { src: link.getAttribute('href'), title: link.getAttribute('title') }; }),\n                type: 'image',\n                gallery: { enabled: links.length > 1 }\n            }, selected);\n        });\n    });"
        }
      ],
      "after_sha256": "abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec"
    },
    "catalog/view/stylesheet/boostershop-ds.css": {
      "before_sha256": "c140e25de36e40b89830972cbc270b35cdda017cac0cd64118df935312eaf0f9",
      "replacements": [
        {
          "before": "/* --- Gallery --- */\n.bs-product__gallery {}\n.bs-product__gallery .bs-product__main-img {\n  display: block;\n  width: 100%;\n  border-radius: var(--bs-radius-md, 10px);\n  overflow: hidden;\n  background: var(--bs-surface, #f8fafc);\n  margin-bottom: 10px;\n}\n.bs-product__gallery .bs-product__main-img img {\n  width: 100%;\n  height: auto;\n  aspect-ratio: 1;\n  object-fit: contain;\n  display: block;\n}\n.bs-product__thumbs {\n  display: flex;\n  gap: 8px;\n  flex-wrap: wrap;\n}\n.bs-product__thumbs a {\n  display: block;\n  width: 72px;\n  height: 72px;\n  border-radius: var(--bs-radius-sm, 6px);\n  overflow: hidden;\n  border: 1px solid var(--bs-line, #D8DEE8);\n  flex: 0 0 72px;\n}\n.bs-product__thumbs a:hover { border-color: var(--bs-gold, #C68A00); }\n.bs-product__thumbs img {\n  width: 100%;\n  height: 100%;\n  object-fit: contain;\n  display: block;\n}",
          "after": "/* --- Gallery: RD-25-WP2 20261012 --- */\n/* Approved 4:5 geometry: main max 480px, desktop rail 64x80 / 10px gap;\n   mobile rail 56x70. Cut inset is left 6%, top 4%, width 88%, height 96%.\n   Grid tracks express percentages of the slot without absolute positioning. */\n.bs-product__gallery { min-width: 0; align-self: start; }\n.bs-product__gallery .bs-product__main-img { width: 100%; max-width: 480px; aspect-ratio: 4 / 5; }\n.bs-product__main-img > a,\n.bs-product__thumbs > a { display: grid; grid-template-columns: 1fr; grid-template-rows: 1fr; }\n.bs-product__main-img > a { width: 100%; height: 100%; }\n.bs-product__main-img > a[data-bs-media-kind=\"cut\"],\n.bs-product__thumbs > a[data-bs-media-kind=\"cut\"] { grid-template-columns: 6% 88% 6%; grid-template-rows: 4% 96%; }\n.bs-product__main-img > a[data-bs-media-kind=\"cut\"] > img,\n.bs-product__thumbs > a[data-bs-media-kind=\"cut\"] > img { grid-column: 2; grid-row: 2; }\n.bs-product__gallery .bs-product__main-img img,\n.bs-product__thumbs img { display: block; width: 100%; height: 100%; min-width: 0; min-height: 0; object-fit: contain; object-position: center bottom; }\n.bs-product__main-img > a[data-bs-media-kind=\"photo\"] > img { border-radius: var(--bs-r); }\n.bs-product__thumbs { display: flex; gap: 10px; flex-wrap: nowrap; min-width: 0; margin-top: 10px; overflow-x: auto; padding: 3px; }\n.bs-product__thumbs > a { box-sizing: border-box; flex: 0 0 56px; width: 56px; height: 70px; border: 1px solid var(--bs-line); border-radius: var(--bs-r-sm); overflow: hidden; }\n.bs-product__thumbs > a:hover { border-color: var(--bs-gold); }\n.bs-product__thumbs > a[aria-current=\"true\"] { border: 2px solid var(--bs-blue); }\n.bs-product__thumbs > a:focus-visible,\n.bs-product__main-img > a:focus-visible { outline: 2px solid var(--bs-blue); outline-offset: 2px; }\n@media (min-width: 992px) {\n  .bs-product__gallery--rail { display: grid; grid-template-columns: 70px minmax(0, 480px); gap: 10px; }\n  .bs-product__gallery--rail .bs-product__main-img { grid-column: 2; grid-row: 1; }\n  /* Zero intrinsic height and percentage min-height bind the rail to the main row. */\n  .bs-product__gallery--rail .bs-product__thumbs { grid-column: 1; grid-row: 1; flex-direction: column; height: 0; min-height: 100%; overflow-y: auto; overflow-x: hidden; margin-top: 0; }\n  .bs-product__thumbs > a { flex: 0 0 80px; width: 64px; height: 80px; }\n}\n@media (max-width: 991.98px) {\n  .bs-product__gallery .bs-product__main-img { max-width: none; }\n}"
        },
        {
          "before": "/* -- Product page gallery: max-height constraint ----------------------- */\n.bs-product__gallery .bs-product__main-img {\n  max-height: 460px;\n  display: flex;\n  align-items: center;\n  justify-content: center;\n}\n.bs-product__gallery .bs-product__main-img img {\n  max-height: 460px;\n  aspect-ratio: auto;\n  width: auto;\n  max-width: 100%;\n  object-fit: contain;\n  display: block;\n  margin: 0 auto;\n}",
          "after": "/* RD-25-WP2: obsolete RD-10 gallery max-height layer retired at its source. */"
        },
        {
          "before": "  .bs-product__gallery .bs-product__main-img { max-height: 340px; }\n  .bs-product__gallery .bs-product__main-img img { max-height: 340px; }",
          "after": "  /* RD-25-WP2: 4:5 gallery replaces the obsolete mobile height cap. */"
        },
        {
          "before": "  .bs-product__gallery .bs-product__main-img {\n    max-height: 340px;\n  }\n  .bs-product__gallery .bs-product__main-img img {\n    max-height: 340px;\n    aspect-ratio: auto;\n  }",
          "after": "  /* RD-25-WP2: repeated RD-10D2 mobile gallery cap retired. */"
        }
      ],
      "after_sha256": "265f69431271fb921d6529c4909d5eee40b8566c4e70374f4132e90bde212a44"
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
    "extension/ps_enhanced_measurement/catalog/model/analytics/ps_enhanced_measurement.php": "eea58d1e247a16c2cc4bc1e61b8a5028e41dc7ed557e9c652d74382f7984b4b4"
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
    "catalog/controller/product/product.php": [
      "74b937525c579a56f67fb62d23cf75f0456e0bc5f034479e600de38e843bb84a"
    ],
    "catalog/view/template/product/product.twig": [
      "abfa0d62cc7542a34301f9ed8936203ea497a92a6e4deba52e785c1775ee3aec"
    ],
    "catalog/view/stylesheet/boostershop-ds.css": [
      "265f69431271fb921d6529c4909d5eee40b8566c4e70374f4132e90bde212a44",
      "213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef"
    ]
  },
  "later_css_hash": "213d4b48394644d04a464f497d68a0478af7d220142aa68fd3ce52eeafb9caef",
  "later_files": {
    "catalog/controller/common/cart.php": "91f8be5d269bc86adcc76a3a58d76b93a9c74698429ad9cbdb2bc24e7000ab8e",
    "catalog/controller/checkout/cart.php": "d4b240b0987fac525a2e51bb59b0500a5779594afe3bcfde95ae634aeb515753",
    "catalog/view/template/common/cart.twig": "0f2cd5891b519b9f516335cb957713cd0295ecf06fe17ff2eccbe8be2794a2fc",
    "catalog/view/template/checkout/cart_list.twig": "a99e4108d7819640410ddce3522c874da73907d270a72e3bf29c210e49b09eb2",
    "catalog/view/template/checkout/cart.twig": "633bb4f7e0a0a62e82bd6561343d2b819bde41171868284ad3f4bfa0dc4d9252"
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
