<?php
/**
 * TECH-013 — WP4: font-display swap on the self-hosted FontAwesome faces
 * =============================================================================
 * Task      : TECH-013 (BS-SPEED-1), Stage 1, work package 2 of 4 in the revised
 *             order WP1 -> WP4 -> WP2 -> WP3 (handoff §5A, owner approved 2026-08-05)
 * Handoff   : handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md (Rev. 2026-08-05), §4 WP4
 * Author    : Claude Code (authorised patch author, AGENTS.md amendment 2026-08-05)
 * Date      : 2026-08-06
 * Risk      : LOW — one vendor CSS file (10 string replacements) + one string literal
 *             in catalog/controller/common/header.php. No logic, no markup, no DB.
 * DB changes: NONE
 *
 * RUN FROM  : ~/public_html    ->    php TECH-013_wp4-font-display-swap_20260806.php
 *
 * -----------------------------------------------------------------------------
 * PHP VERSION — READ THIS FIRST
 * -----------------------------------------------------------------------------
 * The account's default CLI php is 8.0.30 while the site's Composer vendor tree
 * requires 8.1, which is why WP1's Twig gate had to run under
 *     /opt/alt/php81/usr/bin/php
 * and why a raw Composer error appeared when it was run with the default binary.
 *
 * THIS PATCH DOES NOT NEED PHP 8.1. It never loads config.php, never touches the
 * Composer autoloader and never loads Twig — it edits one CSS file and one string
 * literal in a PHP file. Plain `php <file>` on the default 8.0.30 CLI is correct.
 * If it is run under /opt/alt/php81/usr/bin/php that is also fine; both work.
 * The runner refuses to start on anything below PHP 7.0 and says so.
 *
 * -----------------------------------------------------------------------------
 * WHAT THIS CHANGES (2 files)
 * -----------------------------------------------------------------------------
 *
 * 1. catalog/view/stylesheet/fonts/fontawesome/css/all.min.css
 *    Replace `font-display:block` with `font-display:swap` in all TEN @font-face
 *    declarations. Verified fresh against the LIVE file on 2026-08-06 (downloaded and
 *    compared byte-for-byte with the 2026-08-05 backup copy — identical, 104,502 bytes):
 *        font-display:block .............. 10
 *        font-display:swap ............... 0
 *        @font-face blocks ............... 10
 *        occurrences outside @font-face .. 0   (every one is inside a block)
 *    The ten faces are: "Font Awesome 6 Brands" 400, "Font Awesome 6 Free" 400 and 900,
 *    "Font Awesome 5 Brands", "Font Awesome 5 Free" x2, and four "FontAwesome" v4
 *    compatibility faces. Only "Font Awesome 6 Free" 900 (fa-solid-900.woff2, 158,224 B)
 *    is actually loaded on the site per the owner's §5C measurement.
 *
 * 2. catalog/controller/common/header.php  — CACHE-BUSTER (see reasoning below)
 *        $data['icons'] = '...all.min.css';
 *    becomes
 *        $data['icons'] = '...all.min.css?v=tech013-wp4-20260806';
 *
 * -----------------------------------------------------------------------------
 * WHY THE CACHE-BUSTER IS REQUIRED (owner asked for an explicit decision)
 * -----------------------------------------------------------------------------
 * Measured live on 2026-08-06:
 *     curl -sI .../fonts/fontawesome/css/all.min.css
 *     -> Cache-Control: public, max-age=604800      (7 days)
 *        Expires: Thu, 13 Aug 2026 07:51:13 GMT
 *        Content-Length: 104502
 * and the head emits it as a BARE path with no version query, because
 * header.php sets $data['icons'] to a plain string.
 *
 * DECISION: bust it. Without a cache-buster every returning visitor keeps the old
 * all.min.css — still carrying font-display:block — for up to seven days, so the fix
 * would not reach exactly the audience that already has the 158 KB webfont cached.
 * This is the same failure mode handled in WP1 for boostershop-ds.css, and the same
 * remedy. The version string is bumped in the CONTROLLER rather than in header.twig
 * so that header.twig, which WP1 has just modified, is not touched again this round.
 *
 * Safe because:
 *   - `icons` has exactly one setter (header.php:45) and one consumer
 *     (header.twig:44 `<link href="{{ icons }}" ...>`). Verified across catalog/ and
 *     extension/ — no other reference to `icons` or to all.min.css anywhere.
 *   - A query string does not affect resolution of the relative font URLs inside the
 *     stylesheet (`url(../webfonts/fa-solid-900.woff2)` resolves against the path, not
 *     the query), so the webfont requests are unchanged.
 *   - The .woff2 files themselves are NOT modified and carry no Cache-Control at all
 *     (verified), so nothing needs busting there.
 *
 * -----------------------------------------------------------------------------
 * HONEST SCOPE NOTE — what this does and does not fix
 * -----------------------------------------------------------------------------
 * PSI names two CLS causes on desktop category (total 0.253): the header logo
 * (0.251) and fa-solid-900.woff2 (the remainder, ~0.002). This patch addresses only
 * the second, plus the ~40 ms font-display saving. The logo — the overwhelming
 * majority of the shift — is WP2 and is NOT touched here.
 *
 * One trade-off worth knowing, stated once and then executed as scoped: on an ICON
 * font, `swap` means the fallback renders the private-use codepoint briefly (usually a
 * blank or tofu box) before the icon appears, where `block` showed nothing at all for
 * up to ~3 s. The exposure here is small — §5C found exactly one element using
 * "Font Awesome 6 Free" — and `swap` is what the handoff scopes and what PSI asks for,
 * so that is what this patch does. If the flash is objectionable in QA, the follow-up
 * is a <link rel="preload"> for fa-solid-900.woff2 (out of scope for WP4).
 *
 * -----------------------------------------------------------------------------
 * NOT TOUCHED
 * -----------------------------------------------------------------------------
 * - The four Google Fonts <link> URLs — they already carry &display=swap (handoff §5A.6).
 * - The .woff2 / .ttf font binaries.
 * - header.twig, boostershop-ds.css, .htaccess, images — nothing from handoff §5 or §5B.
 *
 * -----------------------------------------------------------------------------
 * SAFETY / ROLLBACK
 * -----------------------------------------------------------------------------
 * - Both targets are existence- and writability-checked, and every anchor is counted,
 *   BEFORE anything is written. Any mismatch aborts having written nothing.
 * - Originals are copied to _patch_backups/<patch>-<timestamp>/ preserving paths.
 * - Syntax gates: header.php is validated in-process with token_get_all(TOKEN_PARSE)
 *   — which raises ParseError on invalid syntax and needs no Composer, no Twig and no
 *   PHP 8.1 — and additionally with `php -l` via PHP_BINARY when exec() is available.
 *   The CSS is checked for an exact, deterministic size delta (-10 bytes: ten times
 *   'block'->'swap') plus brace balance. Any failure restores both files from backup.
 * - Idempotent: a second run reports already_applied=yes and changes nothing.
 * - Self-deletes after a successful fresh apply.
 *
 * ROLLBACK: copy the two files back from _patch_backups/<patch>-<timestamp>/.
 *           No DB change is made, so a file restore is a complete rollback.
 * =============================================================================
 */

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo 'This patch needs PHP 7.0 or newer. Running PHP ' . PHP_VERSION . PHP_EOL;
    exit(1);
}

$PATCH = 'TECH-013_wp4-font-display-swap_20260806';
$ROOT  = __DIR__;
$VER   = 'tech013-wp4-20260806';

$out  = static function ($msg = '') { echo $msg . PHP_EOL; };
$fail = static function ($msg) use ($out) {
    $out('');
    $out('[ABORT] ' . $msg);
    $out('Nothing was written. The site is unchanged.');
    exit(1);
};

$out('=============================================================');
$out(' ' . $PATCH);
$out(' TECH-013 WP4 — FontAwesome font-display: block -> swap');
$out('=============================================================');
$out('PHP ' . PHP_VERSION . ' (' . PHP_BINARY . ')');
$out('Working directory: ' . $ROOT);
$out('');

/* -------------------------------------------------------------------------
 * 0. Sanity: are we in ~/public_html?
 * ---------------------------------------------------------------------- */
if (!is_file($ROOT . '/index.php') || !is_dir($ROOT . '/catalog/controller/common')) {
    $fail('This does not look like the OpenCart web root. Upload the patch to ~/public_html and run it from there.');
}

$CSS    = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
$HEADER = 'catalog/controller/common/header.php';

/* -------------------------------------------------------------------------
 * 1. File-exists + writability check
 * ---------------------------------------------------------------------- */
$out('[1/7] Checking target files ...');
foreach ([$CSS, $HEADER] as $rel) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs))                              { $fail('Target file not found: ' . $rel); }
    if (!is_readable($abs) || !is_writable($abs))    { $fail('Target file is not readable/writable: ' . $rel); }
    $out('      OK  ' . $rel . '  (' . filesize($abs) . ' bytes)');
}

$cssOrig = file_get_contents($ROOT . '/' . $CSS);
$phpOrig = file_get_contents($ROOT . '/' . $HEADER);
if ($cssOrig === false || $phpOrig === false) {
    $fail('Could not read one of the target files.');
}

/* -------------------------------------------------------------------------
 * 2. Idempotency — including the partially-applied case
 * ---------------------------------------------------------------------- */
$cssDone = (strpos($cssOrig, 'font-display:block') === false)
           && (substr_count($cssOrig, 'font-display:swap') === 10);
$phpDone = (strpos($phpOrig, 'all.min.css?v=' . $VER) !== false);

if ($cssDone && $phpDone) {
    $out('');
    $out('already_applied=yes');
    $out('Both targets already carry the TECH-013 WP4 changes. Nothing to do.');
    $out('This patch file was NOT deleted — remove it manually if you are done.');
    exit(0);
}
if ($cssDone !== $phpDone) {
    $out('');
    $out('  all.min.css already converted : ' . ($cssDone ? 'yes' : 'no'));
    $out('  header.php cache-buster set   : ' . ($phpDone ? 'yes' : 'no'));
    $fail('Partially applied state detected. Restore both files from the relevant '
        . '_patch_backups/ folder before re-running, so the two stay in step.');
}

/* -------------------------------------------------------------------------
 * 3. Anchor pre-check — exact counts, before any write
 * ---------------------------------------------------------------------- */
$out('');
$out('[2/7] Anchor pre-check ...');

$CSS_FIND = 'font-display:block';
$CSS_REPL = 'font-display:swap';
$CSS_N    = 10;

$PHP_FIND = <<<'ANCHOR'
$data['icons'] = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css';
ANCHOR;

$PHP_REPL = <<<'ANCHOR'
$data['icons'] = 'catalog/view/stylesheet/fonts/fontawesome/css/all.min.css?v=tech013-wp4-20260806';
ANCHOR;

$checks = [
    [$CSS,    'A1 font-display:block in @font-face', $cssOrig, $CSS_FIND, $CSS_N],
    [$CSS,    'A1b @font-face block count',          $cssOrig, '@font-face', 10],
    [$CSS,    'A1c pre-existing font-display:swap',  $cssOrig, $CSS_REPL, 0],
    [$HEADER, 'A2 icons asset path literal',         $phpOrig, $PHP_FIND, 1],
];

$problems = [];
foreach ($checks as $c) {
    $n = substr_count($c[2], $c[3]);
    $out(sprintf('      %-40s expected %2d, found %2d', $c[1], $c[4], $n));
    if ($n !== $c[4]) {
        $problems[] = $c[0] . ' :: ' . $c[1] . ' (expected ' . $c[4] . ', found ' . $n . ')';
    }
}
if ($problems) {
    $out('');
    foreach ($problems as $p) { $out('  MISMATCH: ' . $p); }
    $fail('Anchor pre-check failed. The live files differ from what this patch was built '
        . 'against (all.min.css verified live 2026-08-06). Do not force it — re-export and rebuild.');
}

/* Every font-display must sit inside an @font-face block — verify, do not assume. */
$insideCount = 0;
if (preg_match_all('/@font-face\{[^}]*\}/', $cssOrig, $blocks)) {
    foreach ($blocks[0] as $b) { $insideCount += substr_count($b, $CSS_FIND); }
}
$out(sprintf('      %-40s expected %2d, found %2d', 'A1d occurrences inside @font-face', $CSS_N, $insideCount));
if ($insideCount !== $CSS_N) {
    $fail('Not every font-display:block sits inside an @font-face block (' . $insideCount . '/' . $CSS_N
        . '). A blind replace could touch an unintended declaration — aborting.');
}
$out('      All anchors matched exactly.');

/* -------------------------------------------------------------------------
 * 4. Build modified content in memory
 * ---------------------------------------------------------------------- */
$out('');
$out('[3/7] Building modified content in memory ...');

$cssNew = str_replace($CSS_FIND, $CSS_REPL, $cssOrig);
$phpNew = str_replace($PHP_FIND, $PHP_REPL, $phpOrig);

/* 'block' (5) -> 'swap' (4) = -1 byte per occurrence, exactly 10 occurrences. */
$expectedDelta = -1 * $CSS_N;
$actualDelta   = strlen($cssNew) - strlen($cssOrig);

$expect = [
    'CSS: 10 font-display:swap'        => (substr_count($cssNew, $CSS_REPL) === 10),
    'CSS: 0 font-display:block left'   => (strpos($cssNew, $CSS_FIND) === false),
    'CSS: @font-face count unchanged'  => (substr_count($cssNew, '@font-face') === 10),
    'CSS: size delta exactly -10 B'    => ($actualDelta === $expectedDelta),
    'CSS: brace balance preserved'     => (substr_count($cssNew, '{') === substr_count($cssOrig, '{')
                                           && substr_count($cssNew, '}') === substr_count($cssOrig, '}')),
    'CSS: webfont urls untouched'      => (substr_count($cssNew, 'fa-solid-900.woff2') === substr_count($cssOrig, 'fa-solid-900.woff2')),
    'PHP: cache-buster present'        => (substr_count($phpNew, 'all.min.css?v=' . $VER) === 1),
    'PHP: bare path gone'              => (substr_count($phpNew, "all.min.css';") === 0),
    'PHP: size delta as expected'      => (strlen($phpNew) - strlen($phpOrig) === strlen('?v=' . $VER)),
    'PHP: other asset lines untouched' => (substr_count($phpNew, "\$data['bootstrap']") === substr_count($phpOrig, "\$data['bootstrap']")
                                           && substr_count($phpNew, "\$data['jquery']") === substr_count($phpOrig, "\$data['jquery']")
                                           && substr_count($phpNew, "\$data['stylesheet']") === substr_count($phpOrig, "\$data['stylesheet']")),
];
$bad = [];
foreach ($expect as $label => $ok) {
    $out(sprintf('      %-36s %s', $label, $ok ? 'OK' : 'FAILED'));
    if (!$ok) { $bad[] = $label; }
}
$out('      css size ' . strlen($cssOrig) . ' -> ' . strlen($cssNew) . ' (delta ' . $actualDelta . ')');
if ($bad) {
    $fail('Post-replacement structural check failed: ' . implode('; ', $bad));
}

/* -------------------------------------------------------------------------
 * 5. PHP syntax gate — in-process, no Composer / Twig / PHP 8.1 needed
 * ---------------------------------------------------------------------- */
$out('');
$out('[4/7] PHP syntax gate on ' . $HEADER . ' ...');

/* Primary: token_get_all with TOKEN_PARSE throws ParseError on invalid syntax. */
try {
    token_get_all($phpNew, TOKEN_PARSE);
    $out('      token_get_all(TOKEN_PARSE)  PASSED');
} catch (\ParseError $e) {
    $fail('Modified header.php is not valid PHP: ' . $e->getMessage() . ' — nothing was written.');
} catch (\Throwable $e) {
    $fail('Unexpected error while parsing modified header.php: ' . $e->getMessage());
}

/* Secondary: real `php -l`, when the host allows exec(). */
$lintVerdict = 'skipped (exec disabled)';
if (function_exists('exec')) {
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    if (!in_array('exec', $disabled, true)) {
        $tmp = tempnam(sys_get_temp_dir(), 'wp4');
        if ($tmp !== false) {
            file_put_contents($tmp, $phpNew);
            $lintOut  = [];
            $lintCode = 0;
            @exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $lintOut, $lintCode);
            @unlink($tmp);
            if ($lintCode === 0) {
                $lintVerdict = 'passed';
            } else {
                $lintVerdict = 'FAILED';
                $out('      php -l output: ' . implode(' | ', $lintOut));
                $fail('`php -l` rejected the modified header.php — nothing was written.');
            }
        }
    }
}
$out('      php -l                      ' . strtoupper($lintVerdict));

/* -------------------------------------------------------------------------
 * 6. Backup, then write
 * ---------------------------------------------------------------------- */
$out('');
$out('[5/7] Backing up originals ...');

$stamp     = date('Ymd-His');
$backupDir = $ROOT . '/_patch_backups/' . $PATCH . '-' . $stamp;
foreach ([$CSS, $HEADER] as $rel) {
    $dest = $backupDir . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
        $fail('Could not create backup directory: ' . dirname($dest));
    }
    if (!copy($ROOT . '/' . $rel, $dest)) {
        $fail('Could not back up ' . $rel);
    }
    $out('      ' . $rel);
}
$out('      -> _patch_backups/' . $PATCH . '-' . $stamp . '/');

$restore = static function () use ($backupDir, $ROOT, $CSS, $HEADER, $out) {
    foreach ([$CSS, $HEADER] as $rel) {
        if (is_file($backupDir . '/' . $rel)) {
            copy($backupDir . '/' . $rel, $ROOT . '/' . $rel);
        }
    }
    $out('      Files restored from backup.');
};

$out('');
$out('[6/7] Writing changes ...');
$writeOk = true;
foreach ([[$CSS, $cssNew], [$HEADER, $phpNew]] as $pair) {
    $bytes = file_put_contents($ROOT . '/' . $pair[0], $pair[1]);
    if ($bytes === false || $bytes !== strlen($pair[1])) {
        $writeOk = false;
        $out('      WRITE FAILED: ' . $pair[0]);
        break;
    }
    $out('      wrote ' . $pair[0] . '  (' . $bytes . ' bytes)');
}
if (!$writeOk) {
    $out('');
    $out('[ROLLBACK] A write failed — restoring originals.');
    $restore();
    $fail('Write failed. Originals restored from ' . $backupDir);
}

/* -------------------------------------------------------------------------
 * 7. Verify what actually landed
 * ---------------------------------------------------------------------- */
$out('');
$out('[7/7] Verifying written files ...');
$cssCheck = file_get_contents($ROOT . '/' . $CSS);
$phpCheck = file_get_contents($ROOT . '/' . $HEADER);

if ($cssCheck !== $cssNew || $phpCheck !== $phpNew) {
    $out('      Content on disk does not match what was written.');
    $out('');
    $out('[ROLLBACK] Restoring originals.');
    $restore();
    $fail('Post-write verification failed. Originals restored from ' . $backupDir);
}
$out('      Both files verified byte-for-byte on disk.');
$out('      font-display:swap  = ' . substr_count($cssCheck, $CSS_REPL));
$out('      font-display:block = ' . substr_count($cssCheck, $CSS_FIND));

/* -------------------------------------------------------------------------
 * Done
 * ---------------------------------------------------------------------- */
$out('');
$out('=============================================================');
$out(' already_applied=no');
$out(' result=SUCCESS');
$out(' php_syntax_gate=token_get_all:passed, php -l:' . $lintVerdict);
$out(' backup=_patch_backups/' . $PATCH . '-' . $stamp . '/');
$out('=============================================================');
$out('');
$out('Applied:');
$out('  1. all.min.css — 10x font-display:block -> font-display:swap');
$out('  2. header.php  — icons path now carries ?v=' . $VER);
$out('     (all.min.css is served with max-age=604800, so without this the fix would');
$out('      not reach returning visitors for up to 7 days)');
$out('');
$out('OWNER QA — before deploying WP2:');
$out('  1. View source on the home page: the FontAwesome <link> must now end with');
$out('     ?v=' . $VER);
$out('  2. curl -sI "https://boostershop.website/catalog/view/stylesheet/fonts/fontawesome/css/all.min.css?v=' . $VER . '"');
$out('     -> expect HTTP 200 and Content-Length 104492 (was 104502).');
$out('  3. Icons still render: the back-to-top chevron (scroll down on any page) and');
$out('     the cart-shopping icon on the "У кошик" button of a product page.');
$out('  4. Watch for a brief blank/tofu box where an icon will appear — that is the');
$out('     expected swap behaviour. Report it if it looks wrong.');
$out('  5. No new console errors on all three benchmark URLs.');
$out('  6. bs-checkout-smoke — header.twig renders on checkout too.');
$out('  7. Re-run PSI mobile + desktop on all three URLs. Expect the font-display');
$out('     item to clear. NOTE: desktop category CLS will NOT drop much — 0.251 of');
$out('     the 0.253 comes from the header logo, which is WP2.');
$out('');
$out('Protected files are not touched by this patch; confirm anyway:');
$out('  curl -sI https://boostershop.website/sitemap-full.xml');
$out('  curl -sI https://boostershop.website/robots.txt');
$out('  Compare with diagnostics/TECH-013_header-baselines_20260805.md');
$out('  (ignore the Date, Keep-Alive and alt-svc lines).');
$out('');

if (@unlink(__FILE__)) {
    $out('Patch file self-deleted.');
} else {
    $out('NOTE: could not self-delete ' . basename(__FILE__) . ' — please remove it manually.');
}
$out('');
exit(0);
