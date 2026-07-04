<?php
declare(strict_types=1);

/**
 * CAT-002-5c — category accents, mobile trust/footer polish, breadcrumb path fix.
 *
 * Files only; no DB writes.
 * Rollback: restore files from
 * _patch_backups/cat002_5c_mobile_visual_breadcrumb_20260630-<timestamp>/.
 */

const PATCH_ID = 'cat002_5c_mobile_visual_breadcrumb_20260630';
const CSS_MARKER = 'CAT-002-5c · mobile visual and category accents';
const CATEGORY_CONTROLLER_MARKER = 'CAT-002-5c: category accent routing';
const PRODUCT_CONTROLLER_MARKER = 'CAT-002-5c: rebuild full parent category path';
const CATEGORY_TWIG_MARKER = 'CAT-002-5c · category accent class';
const FOOTER_TWIG_MARKER = 'CAT-002-5c · mobile contacts';
const CACHE_VERSION = 'cat002-5c-20260630';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$timestamp = date('Ymd-His');
$backupDir = $root . '/_patch_backups/' . PATCH_ID . '-' . $timestamp;

$files = [
    'header' => 'catalog/view/template/common/header.twig',
    'footer' => 'catalog/view/template/common/footer.twig',
    'category_twig' => 'catalog/view/template/product/category.twig',
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
    'category_php' => 'catalog/controller/product/category.php',
    'product_php' => 'catalog/controller/product/product.php',
];

function out(string $message): void {
    echo $message . PHP_EOL;
}

function fail(string $message): never {
    throw new RuntimeException($message);
}

function normalize(string $content): string {
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function assert_count(string $content, string $needle, int $expected, string $label): void {
    $actual = substr_count($content, $needle);
    if ($actual !== $expected) {
        fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function replace_once(string $content, string $needle, string $replacement, string $label): string {
    $needle = normalize($needle);
    $replacement = normalize($replacement);
    assert_count($content, $needle, 1, $label);

    return str_replace($needle, $replacement, $content);
}

function write_atomic(string $path, string $content): void {
    $temporary = $path . '.cat0025c.tmp.' . getmypid();
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        fail('temporary_write_failed=' . $path);
    }
    if (!@rename($temporary, $path)) {
        // Windows cannot rename over an existing file; Linux hosting uses the
        // atomic path above. The backup already exists before this fallback.
        if (!copy($temporary, $path)) {
            @unlink($temporary);
            fail('file_replace_failed=' . $path);
        }
        @unlink($temporary);
    }
}

function backup_file(string $root, string $backupDir, string $relative): void {
    $source = $root . '/' . $relative;
    $target = $backupDir . '/' . $relative;
    if (!is_dir(dirname($target))
        && !mkdir(dirname($target), 0775, true)
        && !is_dir(dirname($target))) {
        fail('backup_dir_create_failed=' . dirname($target));
    }
    if (!copy($source, $target)) {
        fail('backup_copy_failed=' . $relative);
    }
    out('backup=' . str_replace($root . '/', '', $target));
}

function restore_files(string $root, string $backupDir, array $files): void {
    foreach ($files as $relative) {
        $backup = $backupDir . '/' . $relative;
        if (is_file($backup)) {
            @copy($backup, $root . '/' . $relative);
        }
    }
}

function php_lint(string $path, string $label): void {
    $output = [];
    $code = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0 || !str_contains(implode("\n", $output), 'No syntax errors detected')) {
        fail('php_lint_failed=' . $label . ':' . implode(' | ', $output));
    }
    out('php_lint=ok:' . $label);
}

function assert_final(array $content): void {
    assert_count($content['css'], CSS_MARKER, 1, 'css_marker_final');
    assert_count(
        $content['css'],
        '#content > .bs-trust-strip { border-bottom: 0 !important; }',
        1,
        'mobile_trust_border_final'
    );
    foreach ([
        'pokemon' => 'var(--bs-pokemon)',
        'onepiece' => 'var(--bs-onepiece)',
        'other-tcg' => 'var(--bs-other-tcg)',
        'accessories' => 'var(--bs-accessories)',
        'yugioh' => 'var(--bs-yugioh)',
        'mtg' => 'var(--bs-mtg)',
    ] as $class => $color) {
        assert_count(
            $content['css'],
            ".bs-cat-header--{$class} .bs-cat-header__strip { background: {$color}; }",
            1,
            'category_accent_' . $class
        );
    }
    assert_count($content['css'], '.bs-footer__col--contacts {', 1, 'footer_contacts_css_final');

    assert_count($content['category_php'], CATEGORY_CONTROLLER_MARKER, 1, 'category_controller_marker_final');
    foreach (["'yugioh'", "'mtg'", "'accessories'", "'other-tcg'"] as $code) {
        if (!str_contains($content['category_php'], "\$data['category_code'] = {$code};")) {
            fail('category_code_missing=' . $code);
        }
    }

    assert_count($content['product_php'], PRODUCT_CONTROLLER_MARKER, 1, 'product_controller_marker_final');
    assert_count($content['product_php'], '$breadcrumb_path = implode(\'_\', $breadcrumb_category_ids);', 1, 'breadcrumb_parent_path_final');

    assert_count($content['category_twig'], CATEGORY_TWIG_MARKER, 1, 'category_twig_marker_final');
    assert_count(
        $content['category_twig'],
        "{% if category_code %} bs-cat-header--{{ category_code }}{% endif %}",
        1,
        'category_class_final'
    );

    assert_count($content['footer'], FOOTER_TWIG_MARKER, 1, 'footer_twig_marker_final');
    assert_count($content['footer'], 'bs-footer__col bs-footer__col--contacts', 1, 'footer_contact_class_final');

    assert_count($content['header'], 'boostershop-ds.css?v=' . CACHE_VERSION, 1, 'cache_version_final');
}

set_exception_handler(static function (Throwable $error): void {
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
});

out('patch=' . PATCH_ID);
out('cwd=' . $root);
out('time=' . date('c'));
out('dry_run=' . ($dryRun ? 'yes' : 'no'));
out('db_changes=none');

php_lint(__FILE__, basename(__FILE__));

$original = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;
    if (!is_file($path)) {
        fail('target_not_found=' . $relative);
    }
    if (!$dryRun && !is_writable($path)) {
        fail('target_not_writable=' . $relative);
    }
    $value = file_get_contents($path);
    if (!is_string($value)) {
        fail('target_read_failed=' . $relative);
    }
    $original[$key] = normalize($value);
    out('file_preflight=ok:' . $relative);
}

$markers = [
    'css' => CSS_MARKER,
    'category_php' => CATEGORY_CONTROLLER_MARKER,
    'product_php' => PRODUCT_CONTROLLER_MARKER,
    'category_twig' => CATEGORY_TWIG_MARKER,
    'footer' => FOOTER_TWIG_MARKER,
];
$applied = [];
foreach ($markers as $key => $marker) {
    $count = substr_count($original[$key], $marker);
    if ($count > 1) {
        fail('duplicate_marker=' . $marker);
    }
    $applied[$key] = $count === 1;
}
$applied['header'] = str_contains($original['header'], 'boostershop-ds.css?v=' . CACHE_VERSION);

$appliedCount = count(array_filter($applied));
if ($appliedCount > 0 && $appliedCount < count($applied)) {
    fail('partial_patch_state_detected');
}
if ($appliedCount === count($applied)) {
    assert_final($original);
    out('already_applied=yes');
    out('run_url=https://boostershop.website/');
    out('qa_url_yugioh=https://boostershop.website/catalog/more-tcg/Yu-Gi-Oh');
    out('done=ok');
    if (!$dryRun) {
        @unlink(__FILE__);
    }
    exit(0);
}

$updated = $original;

$categoryCodeOld = <<<'PHP'
			$category_code_source = strtolower($category_info['name'] . ' ' . $path . ' ' . urldecode($_SERVER['REQUEST_URI'] ?? ''));

			if (strpos($category_code_source, 'pokemon') !== false || strpos($category_code_source, 'pokémon') !== false) {
				$data['category_code'] = 'pokemon';
			} elseif (strpos($category_code_source, 'one-piece') !== false || strpos($category_code_source, 'onepiece') !== false || strpos($category_code_source, 'one piece') !== false) {
				$data['category_code'] = 'one-piece';
			} else {
				$data['category_code'] = '';
			}
PHP;
$categoryCodeNew = <<<'PHP'
			// CAT-002-5c: category accent routing
			$category_code_source = strtolower($category_info['name'] . ' ' . $path . ' ' . urldecode($_SERVER['REQUEST_URI'] ?? ''));

			if ($category_id === 65 || strpos($category_code_source, 'yu-gi-oh') !== false || strpos($category_code_source, 'yugioh') !== false) {
				$data['category_code'] = 'yugioh';
			} elseif ($category_id === 67 || strpos($category_code_source, 'magic') !== false || strpos($category_code_source, 'mtg') !== false) {
				$data['category_code'] = 'mtg';
			} elseif ($category_id === 70 || strpos($category_code_source, 'acsesuary') !== false || strpos($category_code_source, 'accessories') !== false) {
				$data['category_code'] = 'accessories';
			} elseif ($category_id === 66 || strpos($category_code_source, 'more-tcg') !== false) {
				$data['category_code'] = 'other-tcg';
			} elseif (strpos($category_code_source, 'pokemon') !== false || strpos($category_code_source, 'pokémon') !== false) {
				$data['category_code'] = 'pokemon';
			} elseif (strpos($category_code_source, 'one-piece') !== false || strpos($category_code_source, 'onepiece') !== false || strpos($category_code_source, 'one piece') !== false) {
				$data['category_code'] = 'onepiece';
			} else {
				$data['category_code'] = '';
			}
PHP;
$updated['category_php'] = replace_once(
    $updated['category_php'],
    $categoryCodeOld,
    $categoryCodeNew,
    'category_controller_routing'
);

$productPathOld = <<<'PHP'
	if ($product_categories) {
		$breadcrumb_path = (string)$product_categories[0]['category_id'];
	}
PHP;
$productPathNew = <<<'PHP'
	if ($product_categories) {
		// CAT-002-5c: rebuild full parent category path for direct product URLs.
		$breadcrumb_category_ids = [];
		$breadcrumb_category_id = (int)$product_categories[0]['category_id'];
		$breadcrumb_seen = [];

		while ($breadcrumb_category_id > 0 && !isset($breadcrumb_seen[$breadcrumb_category_id])) {
			$breadcrumb_seen[$breadcrumb_category_id] = true;
			array_unshift($breadcrumb_category_ids, $breadcrumb_category_id);

			$breadcrumb_category_info = $this->model_catalog_category->getCategory($breadcrumb_category_id);
			if (!$breadcrumb_category_info) {
				break;
			}

			$breadcrumb_category_id = (int)($breadcrumb_category_info['parent_id'] ?? 0);
		}

		$breadcrumb_path = implode('_', $breadcrumb_category_ids);
	}
PHP;
$updated['product_php'] = replace_once(
    $updated['product_php'],
    $productPathOld,
    $productPathNew,
    'product_breadcrumb_parent_path'
);

$categoryTwigOld = <<<'TWIG'
      <div class="bs-cat-header{% if category_code == 'one-piece' %} bs-cat-header--onepiece{% endif %}{% if category_is_subcategory %} bs-cat-header--subcategory{% endif %}">
TWIG;
$categoryTwigNew = <<<'TWIG'
      {# CAT-002-5c · category accent class #}
      <div class="bs-cat-header{% if category_code %} bs-cat-header--{{ category_code }}{% endif %}{% if category_is_subcategory %} bs-cat-header--subcategory{% endif %}">
TWIG;
$updated['category_twig'] = replace_once(
    $updated['category_twig'],
    $categoryTwigOld,
    $categoryTwigNew,
    'category_twig_accent_class'
);

$footerOld = <<<'TWIG'
    <div class="bs-footer__col">
      <h4>Контакти</h4>
TWIG;
$footerNew = <<<'TWIG'
    {# CAT-002-5c · mobile contacts #}
    <div class="bs-footer__col bs-footer__col--contacts">
      <h4>Контакти</h4>
TWIG;
$updated['footer'] = replace_once(
    $updated['footer'],
    $footerOld,
    $footerNew,
    'footer_contacts_class'
);

$cssAnchor = '/* ==== /CAT-002-5 ==== */';
$cssBlock = <<<'CSS'
/* ==== /CAT-002-5 ==== */

/* ==== CAT-002-5c · mobile visual and category accents ==== */
.bs-cat-header--pokemon .bs-cat-header__strip { background: var(--bs-pokemon); }
.bs-cat-header--other-tcg .bs-cat-header__strip { background: var(--bs-other-tcg); }
.bs-cat-header--accessories .bs-cat-header__strip { background: var(--bs-accessories); }
.bs-cat-header--yugioh .bs-cat-header__strip { background: var(--bs-yugioh); }
.bs-cat-header--mtg .bs-cat-header__strip { background: var(--bs-mtg); }

@media (max-width: 640px) {
  #content > .bs-trust-strip { border-bottom: 0 !important; }
}

@media (max-width: 480px) {
  body.bs .bs-footer__inner > .bs-footer__col--contacts {
    grid-column: 1 / -1 !important;
    min-width: 0;
  }
  body.bs .bs-footer__col--contacts .bs-footer__contact-link {
    display: grid !important;
    grid-template-columns: 16px minmax(0, 1fr);
    align-items: center;
    gap: 8px;
    white-space: nowrap;
  }
}
/* ==== /CAT-002-5c ==== */
CSS;
$updated['css'] = replace_once($updated['css'], $cssAnchor, $cssBlock, 'css_component');

$updated['header'] = replace_once(
    $updated['header'],
    'boostershop-ds.css?v=cat002-redo-20260629',
    'boostershop-ds.css?v=' . CACHE_VERSION,
    'header_cache_bust'
);

assert_final($updated);
out('assert=transformed_state:ok');

if ($dryRun) {
    foreach ($files as $key => $relative) {
        out('would_change=' . ($updated[$key] === $original[$key] ? 'no:' : 'yes:') . $relative);
    }
    out('done=ok');
    exit(0);
}

$writeStarted = false;
try {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        fail('backup_root_create_failed=' . $backupDir);
    }
    foreach ($files as $relative) {
        backup_file($root, $backupDir, $relative);
    }
    out('assert=backup_before_write:ok');

    foreach (['category_php', 'product_php'] as $key) {
        $lintPath = $backupDir . '/prospective-' . basename($files[$key]);
        if (file_put_contents($lintPath, $updated[$key], LOCK_EX) === false) {
            fail('prospective_lint_file_write_failed=' . $files[$key]);
        }
        php_lint($lintPath, $files[$key]);
    }
    out('assert=prospective_php_lint:ok');

    $writeStarted = true;
    foreach ($files as $key => $relative) {
        if ($updated[$key] === $original[$key]) {
            out('changed=none:' . $relative);
            continue;
        }
        write_atomic($root . '/' . $relative, $updated[$key]);
        out('changed=' . $relative);
    }

    $final = [];
    foreach ($files as $key => $relative) {
        $value = file_get_contents($root . '/' . $relative);
        if (!is_string($value)) {
            fail('post_write_read_failed=' . $relative);
        }
        $final[$key] = normalize($value);
    }
    assert_final($final);
    php_lint($root . '/' . $files['category_php'], $files['category_php']);
    php_lint($root . '/' . $files['product_php'], $files['product_php']);
    out('assert=final_state:ok');
    out('run_url=https://boostershop.website/');
    out('qa_url_accessories=https://boostershop.website/catalog/acsesuary');
    out('qa_url_other_tcg=https://boostershop.website/catalog/more-tcg');
    out('qa_url_yugioh=https://boostershop.website/catalog/more-tcg/Yu-Gi-Oh');
    out('qa_url_mtg=https://boostershop.website/catalog/more-tcg/magic-the-gathering');
    out('done=ok');
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($writeStarted) {
        restore_files($root, $backupDir, array_values($files));
    }
    out('rollback=' . ($writeStarted ? 'file_backups_restored' : 'no_write_started'));
    out('error=' . $error->getMessage());
    out('done=failed');
    exit(1);
}
