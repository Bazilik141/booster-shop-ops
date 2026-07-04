<?php
declare(strict_types=1);

/**
 * CAT-002-5 + CAT-002-5b
 *
 * Scope:
 * - catalog/view/template/common/home.twig
 * - catalog/view/template/common/header.twig
 * - catalog/view/stylesheet/boostershop-ds.css
 * - DB_PREFIX . category
 * - DB_PREFIX . category_description
 * - DB_PREFIX . category_to_store
 * - DB_PREFIX . seo_url
 *
 * DB warning:
 * The owner explicitly approved creation of the "Аксесуари" category.
 *
 * Rollback SQL (replace <PREFIX> and <ACC_CATEGORY_ID> from patch output):
 * DELETE FROM `<PREFIX>seo_url`
 *   WHERE `key`='path' AND `value`='<ACC_CATEGORY_ID>' AND `keyword`='accessories';
 * DELETE FROM `<PREFIX>category_to_store` WHERE `category_id`=<ACC_CATEGORY_ID>;
 * DELETE FROM `<PREFIX>category_description` WHERE `category_id`=<ACC_CATEGORY_ID>;
 * DELETE FROM `<PREFIX>category` WHERE `category_id`=<ACC_CATEGORY_ID>;
 *
 * The patch also writes the executable rollback SQL and a DB pre-change snapshot
 * to _patch_backups/<patch>-<timestamp>/.
 */

const CAT002_PATCH_ID = 'cat002_5_5b_tiles_burger_20260628';
const CAT002_HOME_MARKER = 'CAT-002-5 · Claude Design secondary tiles';
const CAT002_CSS_MARKER = 'CAT-002-5 · Claude Design secondary category tiles';
const CAT002_MENU_MARKER = 'CAT-002-5b · burger categories';

$args = $argv ?? [];
$dryRun = in_array('--dry-run', $args, true);
$skipDb = in_array('--skip-db', $args, true);
$root = rtrim((string)(getenv('BS_PATCH_ROOT') ?: (getcwd() ?: __DIR__)), "/\\");
$timestamp = date('Ymd-His');
$backupDir = $root . '/_patch_backups/' . CAT002_PATCH_ID . '-' . $timestamp;

$files = [
    'home' => 'catalog/view/template/common/home.twig',
    'header' => 'catalog/view/template/common/header.twig',
    'css' => 'catalog/view/stylesheet/boostershop-ds.css',
];

function cat002_out(string $message): void
{
    echo $message . PHP_EOL;
}

function cat002_fail(string $message): never
{
    throw new RuntimeException($message);
}

function cat002_count(string $haystack, string $needle): int
{
    return substr_count($haystack, $needle);
}

function cat002_normalize(string $content): string
{
    return str_replace(["\r\n", "\r"], "\n", $content);
}

function cat002_assert_count(string $content, string $needle, int $expected, string $label): void
{
    $actual = cat002_count($content, $needle);

    if ($actual !== $expected) {
        cat002_fail("anchor_count_{$label}={$actual},expected={$expected}");
    }
}

function cat002_replace_once(string $content, string $needle, string $replacement, string $label): string
{
    cat002_assert_count($content, $needle, 1, $label);

    return str_replace($needle, $replacement, $content);
}

function cat002_sql_value(mysqli $db, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string)$value;
    }

    return "'" . $db->real_escape_string((string)$value) . "'";
}

function cat002_query(mysqli $db, string $sql): mysqli_result
{
    $result = $db->query($sql);

    if (!$result instanceof mysqli_result) {
        cat002_fail('db_query_failed=' . $db->error);
    }

    return $result;
}

function cat002_rows(mysqli $db, string $sql): array
{
    $result = cat002_query($db, $sql);
    $rows = [];

    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    $result->free();

    return $rows;
}

function cat002_scalar(mysqli $db, string $sql): int
{
    $result = cat002_query($db, $sql);
    $row = $result->fetch_row();
    $result->free();

    return (int)($row[0] ?? 0);
}

function cat002_columns(mysqli $db, string $table): array
{
    $rows = cat002_rows($db, 'SHOW COLUMNS FROM `' . $table . '`');
    $columns = [];

    foreach ($rows as $row) {
        $columns[(string)$row['Field']] = true;
    }

    return $columns;
}

function cat002_insert(mysqli $db, string $table, array $data, array $availableColumns): void
{
    $filtered = [];

    foreach ($data as $column => $value) {
        if (isset($availableColumns[$column])) {
            $filtered[$column] = $value;
        }
    }

    if ($filtered === []) {
        cat002_fail('no_compatible_columns_for=' . $table);
    }

    $columns = array_map(
        static fn(string $column): string => '`' . $column . '`',
        array_keys($filtered)
    );
    $values = array_map(
        static fn(mixed $value): string => cat002_sql_value($db, $value),
        array_values($filtered)
    );
    $sql = 'INSERT INTO `' . $table . '` (' . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ')';

    if (!$db->query($sql)) {
        cat002_fail('db_insert_failed=' . $table . ':' . $db->error);
    }
}

function cat002_backup_file(string $root, string $backupDir, string $relative): void
{
    $source = $root . '/' . $relative;
    $target = $backupDir . '/' . $relative;

    if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0775, true) && !is_dir(dirname($target))) {
        cat002_fail('backup_dir_create_failed=' . dirname($target));
    }

    if (!copy($source, $target)) {
        cat002_fail('backup_copy_failed=' . $relative);
    }

    cat002_out('backup=' . str_replace($root . '/', '', $target));
}

function cat002_write_file(string $path, string $content): void
{
    $temporary = $path . '.cat002.tmp.' . getmypid();

    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        cat002_fail('temporary_write_failed=' . $path);
    }

    if (!rename($temporary, $path)) {
        @unlink($temporary);
        cat002_fail('atomic_replace_failed=' . $path);
    }
}

function cat002_restore_files(string $root, string $backupDir, array $files): void
{
    foreach ($files as $relative) {
        $backup = $backupDir . '/' . $relative;
        $target = $root . '/' . $relative;

        if (is_file($backup)) {
            @copy($backup, $target);
        }
    }
}

set_exception_handler(static function (Throwable $error): void {
    cat002_out('error=' . $error->getMessage());
    cat002_out('done=failed');
    exit(1);
});

function cat002_assert_file_state(string $home, string $header, string $css): void
{
    cat002_assert_count($home, CAT002_HOME_MARKER, 1, 'home_marker_final');
    cat002_assert_count($home, 'class="bs-subtile"', 2, 'home_subtiles_final');
    cat002_assert_count($home, 'href="/catalog/more-tcg"', 1, 'home_more_tcg_url_final');
    cat002_assert_count($home, 'href="/catalog/accessories"', 1, 'home_accessories_url_final');

    cat002_assert_count($css, CAT002_CSS_MARKER, 1, 'css_marker_final');
    foreach ([
        '--bs-other-tcg:   #065F46;',
        '--bs-accessories: #0D9488;',
        '--bs-yugioh:      #7C3AED;',
        '--bs-mtg:         #B45309;',
    ] as $token) {
        cat002_assert_count($css, $token, 1, 'css_' . substr(sha1($token), 0, 8));
    }
    cat002_assert_count($css, '.bs-subtile {', 2, 'css_subtile_component_final');

    cat002_assert_count($header, CAT002_MENU_MARKER, 1, 'menu_marker_final');
    foreach ([
        '/catalog/pokemon',
        '/catalog/one-piece',
        '/catalog/more-tcg',
        '/catalog/more-tcg/Yu-Gi-Oh',
        '/catalog/more-tcg/magic-the-gathering',
        '/catalog/accessories',
    ] as $url) {
        if (!str_contains($header, 'href="' . $url . '"')) {
            cat002_fail('menu_url_missing=' . $url);
        }
    }
    foreach (['59_61', '59_62', '59_64', '60_63'] as $pathValue) {
        cat002_assert_count(
            $header,
            'href="index.php?route=product/category&path=' . $pathValue . '"',
            1,
            'menu_path_preserved_' . $pathValue
        );
    }
    cat002_assert_count($header, 'index.php?route=product/special', 1, 'menu_special_preserved');
}

function cat002_db_state(mysqli $db, string $prefix, array $activeLanguages): array
{
    $categoryTable = $prefix . 'category';
    $descriptionTable = $prefix . 'category_description';
    $storeTable = $prefix . 'category_to_store';
    $seoTable = $prefix . 'seo_url';
    $name = $db->real_escape_string('Аксесуари');

    $categoryIds = cat002_rows(
        $db,
        "SELECT DISTINCT c.`category_id`
         FROM `{$categoryTable}` c
         INNER JOIN `{$descriptionTable}` cd ON cd.`category_id`=c.`category_id`
         WHERE cd.`name`='{$name}'"
    );

    if (count($categoryIds) > 1) {
        cat002_fail('db_duplicate_accessories_categories=' . count($categoryIds));
    }

    $categoryId = isset($categoryIds[0]['category_id']) ? (int)$categoryIds[0]['category_id'] : 0;
    $seoKeywordCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$seoTable}` WHERE `keyword`='accessories'"
    );

    if ($categoryId === 0 && $seoKeywordCount > 0) {
        cat002_fail('db_accessories_keyword_already_used_without_category');
    }

    if ($categoryId === 0) {
        return [
            'complete' => false,
            'category_id' => 0,
            'description_count' => 0,
            'store_count' => 0,
            'seo_count' => 0,
        ];
    }

    $languageIds = implode(',', array_map('intval', $activeLanguages));
    $descriptionCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$descriptionTable}`
         WHERE `category_id`={$categoryId} AND `language_id` IN ({$languageIds}) AND `name`='{$name}'"
    );
    $storeCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$storeTable}` WHERE `category_id`={$categoryId} AND `store_id`=0"
    );
    $seoCount = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM `{$seoTable}`
         WHERE `store_id`=0 AND `language_id` IN ({$languageIds})
           AND `key`='path' AND `value`='{$categoryId}' AND `keyword`='accessories'"
    );
    $complete = $descriptionCount === count($activeLanguages)
        && $storeCount === 1
        && $seoCount === count($activeLanguages)
        && $seoKeywordCount === count($activeLanguages);

    if (!$complete) {
        cat002_fail(
            "db_partial_accessories_state=id:{$categoryId},descriptions:{$descriptionCount},store:{$storeCount},seo:{$seoCount}"
        );
    }

    return [
        'complete' => true,
        'category_id' => $categoryId,
        'description_count' => $descriptionCount,
        'store_count' => $storeCount,
        'seo_count' => $seoCount,
    ];
}

function cat002_assert_existing_categories(mysqli $db, string $prefix): array
{
    $categoryTable = $prefix . 'category';
    $descriptionTable = $prefix . 'category_description';
    $seoTable = $prefix . 'seo_url';
    $categoryNames = [
        'yugioh' => 'Yu-Gi-Oh!',
        'other_tcg' => 'Інші TCG',
        'mtg' => 'Magic: The Gathering',
    ];
    $categoryIds = [];

    foreach ($categoryNames as $key => $name) {
        $escaped = $db->real_escape_string($name);
        $rows = cat002_rows(
            $db,
            "SELECT DISTINCT `category_id` FROM `{$descriptionTable}` WHERE `name`='{$escaped}'"
        );
        if (count($rows) !== 1) {
            cat002_fail("db_category_name_not_unique={$name}:count=" . count($rows));
        }
        $categoryIds[$key] = (int)$rows[0]['category_id'];
    }

    $otherTcgId = $categoryIds['other_tcg'];
    foreach (['yugioh', 'mtg'] as $childKey) {
        $childId = $categoryIds[$childKey];
        $parentCount = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$categoryTable}`
             WHERE `category_id`={$childId} AND `parent_id`={$otherTcgId} AND `status`=1"
        );
        if ($parentCount !== 1) {
            cat002_fail("db_category_parent_mismatch={$childKey}:id={$childId}:expected_parent={$otherTcgId}");
        }
    }

    $expectedSeo = [
        '59' => 'pokemon',
        '60' => 'one-piece',
        (string)$otherTcgId => 'more-tcg',
        $otherTcgId . '_' . $categoryIds['yugioh'] => 'more-tcg/yu-gi-oh',
        $otherTcgId . '_' . $categoryIds['mtg'] => 'more-tcg/magic-the-gathering',
    ];

    foreach ($expectedSeo as $value => $keyword) {
        $escapedValue = $db->real_escape_string((string)$value);
        $escapedKeyword = $db->real_escape_string((string)$keyword);
        $count = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$seoTable}`
             WHERE `key`='path' AND `value`='{$escapedValue}'
               AND LOWER(`keyword`)=LOWER('{$escapedKeyword}')"
        );
        if ($count < 1) {
            cat002_fail("db_seo_anchor_missing={$value}:{$keyword}");
        }
    }

    return $categoryIds;
}

cat002_out('patch=' . CAT002_PATCH_ID);
cat002_out('cwd=' . $root);
cat002_out('time=' . date('c'));
cat002_out('dry_run=' . ($dryRun ? 'yes' : 'no'));
cat002_out('skip_db=' . ($skipDb ? 'yes' : 'no'));
cat002_out('warning_db_changes=owner_approved');
cat002_out('db_tables=category,category_description,category_to_store,seo_url');

$lintOutput = [];
$lintCode = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(__FILE__) . ' 2>&1', $lintOutput, $lintCode);

if ($lintCode !== 0 || !str_contains(implode("\n", $lintOutput), 'No syntax errors detected')) {
    cat002_fail('php_lint_failed=' . implode(' | ', $lintOutput));
}
cat002_out('php_lint=ok');

$original = [];
foreach ($files as $key => $relative) {
    $path = $root . '/' . $relative;

    if (!is_file($path)) {
        cat002_fail('target_not_found=' . $relative);
    }
    if (!$dryRun && !is_writable($path)) {
        cat002_fail('target_not_writable=' . $relative);
    }

    $content = file_get_contents($path);
    if (!is_string($content)) {
        cat002_fail('target_read_failed=' . $relative);
    }

    $original[$key] = cat002_normalize($content);
    cat002_out('file_preflight=ok:' . $relative);
}

$homeApplied = cat002_count($original['home'], CAT002_HOME_MARKER) === 1;
$cssApplied = cat002_count($original['css'], CAT002_CSS_MARKER) === 1;
$menuApplied = cat002_count($original['header'], CAT002_MENU_MARKER) === 1;

if (cat002_count($original['home'], CAT002_HOME_MARKER) > 1
    || cat002_count($original['css'], CAT002_CSS_MARKER) > 1
    || cat002_count($original['header'], CAT002_MENU_MARKER) > 1) {
    cat002_fail('duplicate_patch_markers_detected');
}

if (!$homeApplied && (
    str_contains($original['home'], 'class="bs-subtile"')
    || str_contains($original['home'], 'href="/catalog/accessories"')
)) {
    cat002_fail('partial_home_state_without_marker');
}
if (!$cssApplied && (
    str_contains($original['css'], '--bs-other-tcg:')
    || str_contains($original['css'], '.bs-subtile {')
)) {
    cat002_fail('partial_css_state_without_marker');
}
if (!$menuApplied && (
    str_contains($original['header'], 'class="bs-menu__sub bs-menu__sub--accent"')
    || str_contains($original['header'], 'href="/catalog/accessories"')
)) {
    cat002_fail('partial_menu_state_without_marker');
}

$updated = $original;

if (!$homeApplied) {
    $homeAnchor = <<<'TWIG'
      </section>
      
      {{ content_top }}
TWIG;
    $homeReplacement = <<<'TWIG'
      </section>

      {# CAT-002-5 · Claude Design secondary tiles #}
      <section class="bs-home-tiles bs-subtiles" aria-label="Інші категорії">
        <a class="bs-subtile" href="/catalog/more-tcg" style="--accent: var(--bs-other-tcg);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="4" y="3" width="13" height="18" rx="2"/>
              <path d="M20 7v14a2 2 0 0 1-2 2H8"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Інші TCG</span>
            <span class="bs-subtile__hint">Yu-Gi-Oh! · Magic: The Gathering</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>

        <a class="bs-subtile" href="/catalog/accessories" style="--accent: var(--bs-accessories);">
          <span class="bs-subtile__glyph" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="5" y="4" width="12" height="16" rx="1.5"/>
              <path d="M19 5.5l.7 1.6 1.8.3-1.4 1.2.4 1.8-1.5-.9-1.5.9.4-1.8L16.5 7.4l1.8-.3z" fill="currentColor" fill-opacity=".15"/>
            </svg>
          </span>
          <span class="bs-subtile__body">
            <span class="bs-subtile__title">Аксесуари</span>
            <span class="bs-subtile__hint">Sleeves, deck boxes, playmats, binders</span>
          </span>
          <svg class="bs-subtile__chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <polyline points="9 6 15 12 9 18"/>
          </svg>
        </a>
      </section>
      
      {{ content_top }}
TWIG;
    $updated['home'] = cat002_replace_once(
        $updated['home'],
        $homeAnchor,
        $homeReplacement,
        'home_after_catcards'
    );
}

if (!$cssApplied) {
    foreach (['yugioh', 'mtg'] as $gameToken) {
        $pattern = '/^[ \t]*--bs-' . preg_quote($gameToken, '/') . ':\s*#[0-9A-Fa-f]{6};[ \t]*\n/m';
        $matchCount = preg_match_all($pattern, $updated['css']);
        if ($matchCount === false || $matchCount > 1) {
            cat002_fail("css_existing_{$gameToken}_token_count=" . ($matchCount === false ? 'regex_error' : $matchCount));
        }
        if ($matchCount === 1) {
            $cleanedCss = preg_replace($pattern, '', $updated['css'], 1, $replaceCount);
            if (!is_string($cleanedCss) || $replaceCount !== 1) {
                cat002_fail("css_existing_{$gameToken}_token_remove_failed");
            }
            $updated['css'] = $cleanedCss;
        }
    }

    $tokenAnchor = <<<'CSS'
  --bs-pokemon:    #C68A00;
  --bs-onepiece:   #1E40AF;
CSS;
    $tokenReplacement = <<<'CSS'
  --bs-pokemon:     #C68A00;
  --bs-onepiece:    #1E40AF;
  --bs-other-tcg:   #065F46;
  --bs-accessories: #0D9488;
  --bs-yugioh:      #7C3AED;
  --bs-mtg:         #B45309;
CSS;
    $updated['css'] = cat002_replace_once(
        $updated['css'],
        $tokenAnchor,
        $tokenReplacement,
        'css_category_tokens'
    );

    $cssEndAnchor = '/* ==== /RD-10D3 ==== */';
    $cssComponent = <<<'CSS'
/* ==== /RD-10D3 ==== */

/* ==== CAT-002-5 · Claude Design secondary category tiles ==== */
.bs-subtiles {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 18px;
  margin-top: 18px;
}
.bs-subtile {
  position: relative;
  display: flex;
  align-items: center;
  gap: 14px;
  height: 84px;
  padding: 0 18px 0 20px;
  background: #fff;
  border: 1px solid var(--bs-line);
  border-radius: var(--bs-r);
  color: var(--bs-ink);
  overflow: hidden;
  text-decoration: none;
  transition: border-color .15s, box-shadow .15s;
}
.bs-subtile:hover {
  border-color: color-mix(in oklab, var(--accent) 35%, var(--bs-line));
  box-shadow: var(--bs-sh-sm);
  text-decoration: none;
}
.bs-subtile::before {
  content: "";
  position: absolute;
  left: 0;
  top: 14px;
  bottom: 14px;
  width: 3px;
  background: var(--accent);
  border-radius: 0 3px 3px 0;
}
.bs-subtile__glyph {
  width: 38px;
  height: 38px;
  flex: 0 0 38px;
  border-radius: 8px;
  display: grid;
  place-items: center;
  background: color-mix(in oklab, var(--accent) 12%, white);
  color: var(--accent);
}
.bs-subtile__glyph svg { width: 20px; height: 20px; display: block; }
.bs-subtile__body { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.bs-subtile__title {
  font-size: 15px;
  font-weight: 700;
  letter-spacing: -0.005em;
  color: var(--bs-ink);
}
.bs-subtile__hint {
  font-size: 12.5px;
  color: var(--bs-ink-3);
  margin-top: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.bs-subtile__chev { color: var(--bs-ink-4); flex: 0 0 auto; }

.bs-menu__sub--accent {
  display: flex;
  align-items: center;
  gap: 9px;
}
.bs-menu__sub--accent .bs-menu__dot {
  width: 6px;
  height: 6px;
  flex: 0 0 6px;
}

@media (max-width: 768px) {
  .bs-subtiles { grid-template-columns: 1fr; gap: 10px; margin-top: 10px; }
  .bs-subtile { height: 72px; padding: 0 14px 0 18px; gap: 12px; }
  .bs-subtile__glyph { width: 34px; height: 34px; flex-basis: 34px; border-radius: 7px; }
  .bs-subtile__glyph svg { width: 18px; height: 18px; }
  .bs-subtile__title { font-size: 14px; }
  .bs-subtile__hint { font-size: 12px; }
}

@supports not (background: color-mix(in oklab, red, white)) {
  .bs-subtile__glyph { background: rgba(0,0,0,0.04); }
  .bs-subtile:hover { border-color: var(--bs-line); }
}
/* ==== /CAT-002-5 ==== */
CSS;
    $updated['css'] = cat002_replace_once(
        $updated['css'],
        $cssEndAnchor,
        $cssComponent,
        'css_eof_component'
    );
}

if (!$menuApplied) {
    $menuUrlReplacements = [
        'href="index.php?route=product/category&path=59"' => 'href="/catalog/pokemon"',
        'href="index.php?route=product/category&path=60"' => 'href="/catalog/one-piece"',
    ];

    foreach ($menuUrlReplacements as $old => $new) {
        $updated['header'] = cat002_replace_once(
            $updated['header'],
            $old,
            $new,
            'menu_' . substr(sha1($old), 0, 8)
        );
    }

    $saleAnchor = <<<'TWIG'
      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
TWIG;
    $newMenu = <<<'TWIG'
      {# CAT-002-5b · burger categories #}
      <div class="bs-menu__cat">
        <button type="button" class="bs-menu__cat-row" data-bs-accordion>
          <span class="bs-menu__dot" style="background:var(--bs-other-tcg)"></span>
          <span class="bs-menu__cat-name">Інші TCG</span>
          <span class="bs-menu__chev bs-menu__chev--acc">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </button>
        <div class="bs-menu__subs" hidden>
          <a href="/catalog/more-tcg" class="bs-menu__sub">Усі Інші TCG</a>
          <a href="/catalog/more-tcg/Yu-Gi-Oh" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-yugioh)"></span>
            <span>Yu-Gi-Oh! OCG</span>
          </a>
          <a href="/catalog/more-tcg/magic-the-gathering" class="bs-menu__sub bs-menu__sub--accent">
            <span class="bs-menu__dot" style="background:var(--bs-mtg)"></span>
            <span>Magic: The Gathering</span>
          </a>
        </div>
      </div>

      <div class="bs-menu__cat">
        <a href="/catalog/accessories" class="bs-menu__cat-row">
          <span class="bs-menu__dot" style="background:var(--bs-accessories)"></span>
          <span class="bs-menu__cat-name">Аксесуари</span>
          <span class="bs-menu__chev">
            <svg width="13" height="13" viewBox="0 0 14 14" fill="none" aria-hidden="true">
              <path d="M4 2.5L9 7l-5 4.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
        </a>
      </div>

      <div class="bs-menu__cat">
        <a href="index.php?route=product/special" class="bs-menu__cat-row is-sale">
TWIG;
    $updated['header'] = cat002_replace_once(
        $updated['header'],
        $saleAnchor,
        $newMenu,
        'menu_before_special'
    );
}

cat002_assert_file_state($updated['home'], $updated['header'], $updated['css']);
cat002_out('assert=files_transformed:ok');

$db = null;
$dbTransaction = false;
$dbCommitted = false;
$dbState = ['complete' => false, 'category_id' => 0];
$activeLanguages = [];
$prefix = '';
$dbSnapshot = [];
$verifiedCategoryIds = [];

if (!$skipDb) {
    $configPath = $root . '/config.php';
    if (!is_file($configPath)) {
        cat002_fail('config_not_found=' . $configPath);
    }
    require_once $configPath;

    foreach (['DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE', 'DB_PREFIX'] as $constant) {
        if (!defined($constant)) {
            cat002_fail('db_constant_missing=' . $constant);
        }
    }

    $dbPort = defined('DB_PORT') ? (int)DB_PORT : 3306;
    $db = @new mysqli(
        (string)DB_HOSTNAME,
        (string)DB_USERNAME,
        (string)DB_PASSWORD,
        (string)DB_DATABASE,
        $dbPort
    );
    if ($db->connect_errno) {
        cat002_fail('db_connect_failed=' . $db->connect_error);
    }
    if (!$db->set_charset('utf8mb4')) {
        cat002_fail('db_charset_failed=' . $db->error);
    }

    $prefix = (string)DB_PREFIX;
    if (!preg_match('/^[A-Za-z0-9_]+$/', $prefix)) {
        cat002_fail('unsafe_db_prefix');
    }

    foreach (['category', 'category_description', 'category_to_store', 'seo_url', 'language'] as $suffix) {
        $table = $prefix . $suffix;
        if (cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($table) . "'"
        ) !== 1) {
            cat002_fail('db_table_missing=' . $table);
        }
    }

    $themeTable = $prefix . 'theme';
    $themeTableExists = cat002_scalar(
        $db,
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='" . $db->real_escape_string($themeTable) . "'"
    ) === 1;
    if ($themeTableExists) {
        $themeOverrideCount = cat002_scalar(
            $db,
            "SELECT COUNT(*) FROM `{$themeTable}`
             WHERE `store_id`=0 AND `route` IN ('common/home','common/header')"
        );
        if ($themeOverrideCount !== 0) {
            cat002_fail('db_theme_override_masks_twig=' . $themeOverrideCount);
        }
    }
    cat002_out('assert=db_theme_overrides_clear:ok');

    $languageRows = cat002_rows(
        $db,
        "SELECT `language_id` FROM `{$prefix}language` WHERE `status`=1 ORDER BY `language_id`"
    );
    $activeLanguages = array_map(
        static fn(array $row): int => (int)$row['language_id'],
        $languageRows
    );
    if ($activeLanguages === []) {
        cat002_fail('db_no_active_languages');
    }

    $verifiedCategoryIds = cat002_assert_existing_categories($db, $prefix);
    cat002_out('category_id_other_tcg=' . $verifiedCategoryIds['other_tcg']);
    cat002_out('category_id_yugioh=' . $verifiedCategoryIds['yugioh']);
    cat002_out('category_id_mtg=' . $verifiedCategoryIds['mtg']);
    cat002_out('assert=db_existing_category_ids_and_seo:ok');

    $dbState = cat002_db_state($db, $prefix, $activeLanguages);
    $dbSnapshot = [
        'captured_at' => date('c'),
        'prefix' => $prefix,
        'active_language_ids' => $activeLanguages,
        'verified_category_ids' => $verifiedCategoryIds,
        'accessories_pre_state' => $dbState,
        'matching_seo_rows' => cat002_rows(
            $db,
            "SELECT `seo_url_id`,`store_id`,`language_id`,`key`,`value`,`keyword`,`sort_order`
             FROM `{$prefix}seo_url`
             WHERE `keyword`='accessories' OR (`key`='path' AND `value` IN ('59','60','66','66_65','66_67'))
             ORDER BY `seo_url_id`"
        ),
    ];
    cat002_out('db_preflight=ok');
} else {
    cat002_out('db_preflight=skipped_local_file_validation');
}

$allFilesApplied = $homeApplied && $cssApplied && $menuApplied;
$dbComplete = $skipDb ? false : (bool)$dbState['complete'];

if (!$dryRun && !$skipDb && $allFilesApplied && $dbComplete) {
    cat002_out('already_applied=yes');
    cat002_out('accessories_category_id=' . (int)$dbState['category_id']);
    cat002_out('run_url=https://boostershop.website/catalog/accessories');
    cat002_out('done=ok');
    $db?->close();
    @unlink(__FILE__);
    exit(0);
}

if ($dryRun) {
    cat002_out('would_change_home=' . ($homeApplied ? 'no' : 'yes'));
    cat002_out('would_change_css=' . ($cssApplied ? 'no' : 'yes'));
    cat002_out('would_change_header=' . ($menuApplied ? 'no' : 'yes'));
    cat002_out('would_create_accessories_category=' . ($skipDb ? 'not_checked' : ($dbComplete ? 'no' : 'yes')));
    cat002_out('assert=dry_run:ok');
    cat002_out('done=ok');
    $db?->close();
    exit(0);
}

if ($skipDb) {
    cat002_fail('skip_db_is_only_allowed_with_dry_run');
}

try {
    if (!is_dir($backupDir) && !mkdir($backupDir, 0775, true) && !is_dir($backupDir)) {
        cat002_fail('backup_root_create_failed=' . $backupDir);
    }

    foreach ($files as $relative) {
        cat002_backup_file($root, $backupDir, $relative);
    }

    $snapshotJson = json_encode($dbSnapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($snapshotJson)
        || file_put_contents($backupDir . '/db-prechange.json', $snapshotJson . PHP_EOL, LOCK_EX) === false) {
        cat002_fail('db_snapshot_write_failed');
    }
    cat002_out('backup=' . str_replace($root . '/', '', $backupDir . '/db-prechange.json'));
    cat002_out('assert=backup_before_changes:ok');

    if (!$db instanceof mysqli || !$db->begin_transaction()) {
        cat002_fail('db_transaction_begin_failed=' . ($db instanceof mysqli ? $db->error : 'no_connection'));
    }
    $dbTransaction = true;

    $accessoriesId = (int)$dbState['category_id'];
    if (!$dbComplete) {
        $now = date('Y-m-d H:i:s');
        $categoryTable = $prefix . 'category';
        $descriptionTable = $prefix . 'category_description';
        $storeTable = $prefix . 'category_to_store';
        $seoTable = $prefix . 'seo_url';

        cat002_insert(
            $db,
            $categoryTable,
            [
                'parent_id' => 0,
                'image' => '',
                'top' => 1,
                'column' => 1,
                'sort_order' => 4,
                'status' => 1,
                'date_added' => $now,
                'date_modified' => $now,
            ],
            cat002_columns($db, $categoryTable)
        );
        $accessoriesId = (int)$db->insert_id;
        if ($accessoriesId < 1) {
            cat002_fail('db_accessories_category_id_invalid');
        }
        cat002_out('accessories_category_id=' . $accessoriesId);
        cat002_out('assert=db_category_insert:ok');

        $descriptionColumns = cat002_columns($db, $descriptionTable);
        foreach ($activeLanguages as $languageId) {
            cat002_insert(
                $db,
                $descriptionTable,
                [
                    'category_id' => $accessoriesId,
                    'language_id' => $languageId,
                    'name' => 'Аксесуари',
                    'description' => 'Протектори, топлоадери, магнітні кейси та аркуші для колекціонерів і гравців TCG.',
                    'meta_title' => 'Аксесуари для карток — купити в Україні | Booster Shop',
                    'meta_description' => 'Протектори, топлоадери, магнітні кейси для колекційних карток. Доставка по Україні.',
                    'meta_keyword' => 'протектори для карток, топлоадери, магнітний кейс, аксесуари tcg',
                ],
                $descriptionColumns
            );
        }
        cat002_out('assert=db_category_description_insert:ok');

        cat002_insert(
            $db,
            $storeTable,
            ['category_id' => $accessoriesId, 'store_id' => 0],
            cat002_columns($db, $storeTable)
        );
        cat002_out('assert=db_category_to_store_insert:ok');

        $seoColumns = cat002_columns($db, $seoTable);
        foreach ($activeLanguages as $languageId) {
            cat002_insert(
                $db,
                $seoTable,
                [
                    'store_id' => 0,
                    'language_id' => $languageId,
                    'key' => 'path',
                    'value' => (string)$accessoriesId,
                    'keyword' => 'accessories',
                    'sort_order' => 0,
                ],
                $seoColumns
            );
        }
        cat002_out('assert=db_seo_url_insert:ok');
    } else {
        cat002_out('accessories_category_id=' . $accessoriesId);
        cat002_out('assert=db_accessories_already_complete:ok');
    }

    $postDbState = cat002_db_state($db, $prefix, $activeLanguages);
    if (!(bool)$postDbState['complete'] || (int)$postDbState['category_id'] !== $accessoriesId) {
        cat002_fail('db_post_insert_assert_failed');
    }
    cat002_out('assert=db_accessories_complete:ok');

    $rollbackSql = "-- CAT-002-5/CAT-002-5b rollback\n"
        . "START TRANSACTION;\n"
        . "DELETE FROM `{$prefix}seo_url` WHERE `key`='path' AND `value`='{$accessoriesId}' AND `keyword`='accessories';\n"
        . "DELETE FROM `{$prefix}category_to_store` WHERE `category_id`={$accessoriesId};\n"
        . "DELETE FROM `{$prefix}category_description` WHERE `category_id`={$accessoriesId};\n"
        . "DELETE FROM `{$prefix}category` WHERE `category_id`={$accessoriesId};\n"
        . "COMMIT;\n";
    if (file_put_contents($backupDir . '/rollback.sql', $rollbackSql, LOCK_EX) === false) {
        cat002_fail('rollback_sql_write_failed');
    }
    cat002_out('backup=' . str_replace($root . '/', '', $backupDir . '/rollback.sql'));

    foreach (['home', 'css', 'header'] as $key) {
        if ($updated[$key] === $original[$key]) {
            cat002_out('changed=none:' . $files[$key]);
            continue;
        }

        cat002_write_file($root . '/' . $files[$key], $updated[$key]);
        $written = file_get_contents($root . '/' . $files[$key]);
        if (!is_string($written) || cat002_normalize($written) !== $updated[$key]) {
            cat002_fail('file_post_write_assert_failed=' . $files[$key]);
        }
        cat002_out('changed=' . $files[$key]);
        cat002_out('assert=file_write_' . $key . ':ok');
    }

    $finalHome = cat002_normalize((string)file_get_contents($root . '/' . $files['home']));
    $finalHeader = cat002_normalize((string)file_get_contents($root . '/' . $files['header']));
    $finalCss = cat002_normalize((string)file_get_contents($root . '/' . $files['css']));
    cat002_assert_file_state($finalHome, $finalHeader, $finalCss);
    cat002_out('assert=all_file_steps:ok');

    if (!$db->commit()) {
        cat002_fail('db_commit_failed=' . $db->error);
    }
    $dbTransaction = false;
    $dbCommitted = true;
    cat002_out('assert=db_commit:ok');

    $finalDbState = cat002_db_state($db, $prefix, $activeLanguages);
    if (!(bool)$finalDbState['complete'] || (int)$finalDbState['category_id'] !== $accessoriesId) {
        cat002_fail('db_final_assert_failed');
    }
    cat002_out('assert=final_state:ok');
    cat002_out('run_url=https://boostershop.website/catalog/accessories');
    cat002_out('qa_url_more_tcg=https://boostershop.website/catalog/more-tcg');
    cat002_out('qa_url_pokemon=https://boostershop.website/catalog/pokemon');
    cat002_out('qa_url_one_piece=https://boostershop.website/catalog/one-piece');
    cat002_out('done=ok');
    $db->close();
    @unlink(__FILE__);
} catch (Throwable $error) {
    if ($db instanceof mysqli && $dbTransaction) {
        @$db->rollback();
    }

    if (!$dbCommitted && is_dir($backupDir)) {
        cat002_restore_files($root, $backupDir, array_values($files));
    }

    cat002_out('rollback=' . ($dbCommitted ? 'not_attempted_after_commit' : 'db_transaction_and_file_backups'));
    cat002_out('error=' . $error->getMessage());
    cat002_out('done=failed');
    $db?->close();
    exit(1);
}
