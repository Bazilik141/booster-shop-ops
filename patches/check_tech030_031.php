<?php
/**
 * TECH-030 + TECH-031 — Post-deploy verification
 * Run from public_html root: php check_tech030_031.php
 * Deletes itself after completion.
 */
declare(strict_types=1);

function chk(string $label, bool $pass, string $detail = ''): void
{
    $icon = $pass ? 'OK' : 'FAIL';
    echo str_pad("[$icon]", 6) . ' ' . $label . ($detail ? '  → ' . $detail : '') . PHP_EOL;
}

echo PHP_EOL . '=== TECH-030 + TECH-031 Post-deploy check ===' . PHP_EOL;
echo 'time=' . date('c') . PHP_EOL;
echo 'cwd=' . getcwd() . PHP_EOL . PHP_EOL;

// ── Fix A: category.php ─────────────────────────────────────────────────────
$controller = __DIR__ . '/catalog/controller/product/category.php';
echo '--- Fix A: noindex patch (category.php) ---' . PHP_EOL;

if (!is_file($controller)) {
    chk('controller file exists', false, $controller);
} else {
    $src = file_get_contents($controller);

    // PASS: page removed
    $no_page = strpos($src, "isset(\$this->request->get['page'])") === false;
    chk('page removed from $has_duplicate_params', $no_page,
        $no_page ? 'not found (correct)' : 'STILL PRESENT — patch not applied');

    // PASS: filter/sort/order/limit still there
    $still_has_filter = strpos($src, "isset(\$this->request->get['filter'])") !== false;
    chk('filter still in $has_duplicate_params', $still_has_filter);

    $still_has_sort = strpos($src, "isset(\$this->request->get['sort'])") !== false;
    chk('sort still in $has_duplicate_params', $still_has_sort);

    // PASS: pagination_link_url present (scope creep, expected)
    $has_link_url = strpos($src, '$pagination_link_url') !== false;
    chk('pagination_link_url present (scope creep, acceptable)', $has_link_url);
}

echo PHP_EOL;

// ── Fix B: category.twig ────────────────────────────────────────────────────
$twig = __DIR__ . '/catalog/view/template/product/category.twig';
echo '--- Fix B: Load More (category.twig) ---' . PHP_EOL;

if (!is_file($twig)) {
    chk('twig file exists', false, $twig);
} else {
    $src = file_get_contents($twig);

    $has_loadmore_wrap = strpos($src, 'id="bs-load-more-wrap"') !== false;
    chk('Load More wrapper present', $has_loadmore_wrap);

    $has_noscript_pagination = strpos($src, '<noscript>') !== false
        && strpos($src, '{{ pagination }}') !== false;
    chk('Pagination in <noscript> fallback', $has_noscript_pagination);

    $has_results_visible = strpos($src, 'bs-results-count') !== false;
    chk('Results count always visible', $has_results_visible);

    $has_css = strpos($src, '.bs-btn-load-more') !== false;
    chk('Load More CSS present', $has_css);

    $has_js = strpos($src, 'getNextUrl') !== false;
    chk('Load More JS present', $has_js);

    $has_domparser = strpos($src, 'DOMParser') !== false;
    chk('DOMParser fetch logic present', $has_domparser);

    // Old bare pagination block must be gone
    $old_pagination_bare = strpos($src, '<div class="col-sm-6 text-start">{{ pagination }}</div>') !== false
        && strpos($src, '<noscript>') === false;
    chk('Old bare pagination block removed', !$old_pagination_bare,
        $old_pagination_bare ? 'still present outside noscript' : 'ok');
}

echo PHP_EOL;

// ── Fix C: One Piece image (DB) ─────────────────────────────────────────────
echo '--- Fix C: One Piece 404 image (DB check) ---' . PHP_EOL;

$config_file = __DIR__ . '/config.php';
if (!is_file($config_file)) {
    chk('config.php found', false);
} else {
    require_once $config_file;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOSTNAME . ';dbname=' . DB_DATABASE . ';charset=utf8',
            DB_USERNAME,
            DB_PASSWORD,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $prefix = defined('DB_PREFIX') ? DB_PREFIX : 'oc_';

        // Check wrong filename still in DB
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$prefix}category` WHERE `image` LIKE '%One PieceC.png'"
        );
        $stmt->execute();
        $bad_count = (int)$stmt->fetchColumn();
        chk('Old One PieceC.png removed from DB', $bad_count === 0,
            $bad_count > 0 ? "still $bad_count row(s) with old filename — run Fix C SQL" : 'ok');

        // Check correct filename present
        $stmt2 = $pdo->prepare(
            "SELECT COUNT(*) FROM `{$prefix}category` WHERE `image` LIKE '%One Piece-Photoroom.png'"
        );
        $stmt2->execute();
        $good_count = (int)$stmt2->fetchColumn();
        chk('Correct One Piece-Photoroom.png in DB', $good_count > 0,
            $good_count > 0 ? "$good_count row(s) correct" : 'not found — Fix C not applied yet');

    } catch (\PDOException $e) {
        chk('DB connection', false, $e->getMessage());
    }
}

echo PHP_EOL . '=== Done ===' . PHP_EOL;
@unlink(__FILE__);
