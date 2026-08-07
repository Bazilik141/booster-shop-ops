<?php
/**
 * TECH-013 — WP3: static asset cache policy in .htaccess  (HIGH RISK, LAST OF STAGE 1)
 * =============================================================================
 * Task      : TECH-013 (BS-SPEED-1), Stage 1, work package 4 of 4
 * Handoff   : handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md §4 WP3, §2.2, §5A, §5B
 * Author    : Claude Code (authorised patch author, AGENTS.md amendment 2026-08-05)
 * Date      : 2026-08-06
 * Risk      : **HIGH** — .htaccess is a protected zone. A malformed .htaccess returns
 *             HTTP 500 for the ENTIRE site. There is no staging. Requires explicit
 *             owner approval before deploy.
 * DB changes: NONE
 *
 * RUN FROM  : ~/public_html    ->    php TECH-013_wp3-cache-ttl-htaccess_20260806.php
 *
 * PHP BINARY: no Twig, no Composer, no vendor tree is loaded by this patch, so the
 *             account's default CLI php 8.0.30 is correct. /opt/alt/php81/usr/bin/php
 *             also works. There is no Twig gate here — nothing this patch writes is
 *             PHP or Twig.
 *
 * -----------------------------------------------------------------------------
 * WHAT IT DOES
 * -----------------------------------------------------------------------------
 * Appends ONE clearly delimited block at the very end of ~/public_html/.htaccess:
 *   # BEGIN BS-SPEED-1 cache  ...  # END BS-SPEED-1 cache
 * Nothing above that block is touched. The runner proves this: after building the new
 * content it asserts that the first strlen(original) bytes are byte-identical to the
 * original, so the append cannot have perturbed §5B's frozen regions.
 *
 * -----------------------------------------------------------------------------
 * MEASURED "BEFORE" STATE — verified live 2026-08-06, not assumed
 * -----------------------------------------------------------------------------
 *   asset class                     current Cache-Control          this block
 *   ------------------------------  -----------------------------  ---------------
 *   /sitemap-full.xml               (none)                         NEVER MATCHED
 *   /robots.txt                     (none)                         NEVER MATCHED
 *   .css  (bootstrap, ds, all.min)  public, max-age=604800  (7d)   OVERRIDE -> 30d
 *   .js   (jquery, bootstrap.bundle,
 *          common, product-polish)  (none)                         CREATE   -> 30d
 *   .woff2 (fa-solid-900)           (none)                         CREATE   -> 30d
 *   images (.png/.webp, incl.
 *           image/cache thumbs)     public, max-age=604800  (7d)   RESTATE  -> 7d
 *   HTML (/, category, product)     no-store, no-cache, ...        NEVER MATCHED
 *
 * So: this block OVERRIDES an existing server-level value for **CSS**, CREATES one for
 * **JS and fonts** (which is where the entire 281-344 KiB opportunity lives — every
 * asset PSI names is JS or the webfont), and RESTATES today's value for **images**.
 * The 7-day CSS/image value comes from LiteSpeed/cPanel config, NOT from .htaccess —
 * .htaccess contains no cache or compression policy at all (handoff §5A.3).
 *
 * **`.txt` IS DELIBERATELY EXCLUDED.** robots.txt is a .txt file and currently sends no
 * Cache-Control. Including txt in the extension list would have changed a protected
 * file's headers and tripped the revert condition. Same for xml.
 *
 * -----------------------------------------------------------------------------
 * THE (a)/(b) DECISION — **(a), with one correction to how it is expressed**
 * -----------------------------------------------------------------------------
 * Chosen: **(a) — no long/immutable TTL on unversioned paths.** (b) is rejected: adding
 * cache-busting to the remaining image URLs would mean versioning the resizer output
 * (image/cache/<name>-250x250.png), whose filename is regenerated unchanged whenever an
 * admin replaces a product photo. Doing that properly means an mtime/hash query emitted
 * by catalog/model/tool/image.php — a shared model change, well outside "cache TTL in
 * .htaccess", and it would have to ship BEFORE the TTL to be safe. Not a WP3 change.
 *
 * **Correction to (a) as worded.** The brief said "long immutable TTL only for paths
 * carrying ?v=, short TTL (<=7 days) for the rest". Implemented literally that FAILS
 * this task's own acceptance criterion: jquery-3.7.1.min.js, bootstrap.css and
 * fa-solid-900.woff2 are all **unversioned**, so "the rest" would cap them at 7 days,
 * while acceptance requires max-age >= 2592000 on exactly those three files.
 * It is also unimplementable with <FilesMatch>, which matches the filename only and
 * never sees the query string; detecting ?v= needs mod_rewrite + an env var, i.e. new
 * rewrite logic inside a protected file with no staging to test it.
 *
 * So the tier is by **mutability class**, which is what (a) is actually protecting
 * against, and no query-string detection is needed:
 *
 *   Tier 1 — code (css, js, fonts): max-age=2592000 (30 days).
 *     These change only when a patch changes them, and this project's convention is to
 *     bump a ?v= in the template at the same time (WP1 did it for boostershop-ds.css,
 *     WP4 for all.min.css, WP2 for the logo). 30 days rather than a year precisely
 *     because bootstrap.css, jquery and the webfont are NOT versioned: 30 days meets
 *     acceptance, and any mistake self-heals in a month instead of being pinned.
 *     No `immutable` anywhere — `immutable` tells the browser not to revalidate even on
 *     reload, which is only safe on genuinely content-addressed URLs. None of these are.
 *
 *   Tier 2 — images (png, jpg, gif, webp, avif, svg, ico): max-age=604800 (7 days).
 *     This is the short tier (a) asks for, and it equals today's value, so images see
 *     no behavioural change at all. They are the genuinely unversioned class: the logo
 *     and the Pokemon tile carry ?v= only because WP1/WP2 put it in the TEMPLATE, and
 *     product thumbnails from image/cache/ have no version anywhere. A future image
 *     swap therefore propagates in at most 7 days.
 *
 * Net: nothing anywhere gets a one-year or immutable TTL. The 281-344 KiB repeat-visit
 * opportunity is fully captured, because all of it is JS + the webfont.
 *
 * -----------------------------------------------------------------------------
 * WP3 WILL NOT IMPROVE FIRST-LOAD PSI SCORES — SAY THIS OUT LOUD AT QA
 * -----------------------------------------------------------------------------
 * Cache headers only affect REPEAT visits. PSI lab runs are cold-cache first loads, so
 * LCP/FCP/SI will be unchanged. The only PSI item that should move is "Serve static
 * assets with an efficient cache policy" (281-344 KiB), which should drop to ~0.
 * A flat performance score after WP3 is the expected result, not a failure.
 *
 * Related correction carried into the record: the earlier "render-blocking 3,080 ->
 * ~480 ms" figure was desktop. Mobile render-blocking today is 2,710 ms (home),
 * 2,680 ms (category), 1,580 ms (product) — WP1 moved mobile about -12%, not -84%.
 * Tracked as TECH-045 (Stage 2). No acceptance table in this patch uses the old number.
 *
 * -----------------------------------------------------------------------------
 * FROZEN ZONE (handoff §5B) — asserted before AND after, never edited
 * -----------------------------------------------------------------------------
 *   :2-5    <FilesMatch "sitemap.*\.xml$"> ForceType / Header set Content-Type
 *   :6-15   the ten blank lines (footprint of the deleted sitemap-no-compression block)
 *   :16-45  # BEGIN LSCACHE ... # END LSCACHE, incl. CacheDisable public /sitemap*.xml
 *   :57-59  HTTPS + non-www 301 canonical redirect
 *   :61-72  # BEGIN legacy-404-301 20260702 (10 product 301s)
 *   :74,76,77  uk-ua rewrites + the commented sitemap rewrite
 *   :80-82  OpenCart SEO front-controller rewrite
 * The runner counts each of these and refuses to proceed if any count is off, then
 * re-counts them after writing.
 *
 * -----------------------------------------------------------------------------
 * SAFETY — this patch does more than the usual runner, because .htaccess can 500 the site
 * -----------------------------------------------------------------------------
 * 1. Anchor + frozen-zone pre-check before any write.
 * 2. Backup to _patch_backups/<patch>-<ts>/ AND to .htaccess.bak-tech013-20260806
 *    (the filename handoff §10 names for rollback).
 * 3. Append-only, proven by a byte-identical-prefix assertion.
 * 4. Delimiter balance check on the appended block.
 * 5. **LIVE POST-WRITE SELF-CHECK.** Immediately after writing, the runner requests
 *    /robots.txt, /sitemap-full.xml and / from the public hostname and AUTO-RESTORES
 *    if the site 5xx's or if either protected file has gained a Cache-Control header.
 *    This is the closest thing to a syntax gate .htaccess allows.
 *    If the HTTP check cannot run at all (no outbound network from CLI) it warns
 *    loudly and leaves the change in place for manual verification — it does not
 *    restore on an inconclusive result.
 * 6. Idempotent; self-deletes after a successful fresh apply.
 *
 * ROLLBACK: delete the # BEGIN BS-SPEED-1 cache ... # END block, or
 *           cp .htaccess.bak-tech013-20260806 .htaccess
 *           Never hand-reconstruct .htaccess.
 * =============================================================================
 */

if (PHP_SAPI !== 'cli') { header('Content-Type: text/plain; charset=UTF-8'); }
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo 'This patch needs PHP 7.0 or newer. Running PHP ' . PHP_VERSION . PHP_EOL; exit(1);
}

$PATCH  = 'TECH-013_wp3-cache-ttl-htaccess_20260806';
$ROOT   = __DIR__;
$TARGET = '.htaccess';
$BAKNAME = '.htaccess.bak-tech013-20260806';
$HOST   = 'https://boostershop.website';

$out  = static function ($m = '') { echo $m . PHP_EOL; };
$fail = static function ($m) use ($out) {
    $out(''); $out('[ABORT] ' . $m); $out('Nothing was written. The site is unchanged.'); exit(1);
};

$out('=============================================================');
$out(' ' . $PATCH);
$out(' TECH-013 WP3 — static asset cache policy (.htaccess)');
$out(' HIGH RISK — protected zone, no staging');
$out('=============================================================');
$out('PHP ' . PHP_VERSION);
$out('Working directory: ' . $ROOT);
$out('');

if (!is_file($ROOT . '/index.php') || !is_dir($ROOT . '/catalog/controller/common')) {
    $fail('This does not look like the OpenCart web root. Upload to ~/public_html and run it there.');
}

/* -------------------------------------------------------------------------
 * 1. Target checks
 * ---------------------------------------------------------------------- */
$out('[1/8] Checking ' . $TARGET . ' ...');
$abs = $ROOT . '/' . $TARGET;
if (!is_file($abs))                           { $fail($TARGET . ' not found in ' . $ROOT); }
if (!is_readable($abs) || !is_writable($abs)) { $fail($TARGET . ' is not readable/writable'); }
$orig = file_get_contents($abs);
if ($orig === false || $orig === '')          { $fail('Could not read ' . $TARGET . ', or it is empty. Refusing to touch it.'); }
$out('      OK  ' . $TARGET . '  (' . strlen($orig) . ' bytes, ' . substr_count($orig, "\n") . ' lines)');

/* -------------------------------------------------------------------------
 * 2. Idempotency
 * ---------------------------------------------------------------------- */
if (strpos($orig, '# BEGIN BS-SPEED-1 cache') !== false) {
    $out('');
    $out('already_applied=yes');
    $out('.htaccess already contains the # BEGIN BS-SPEED-1 cache block. Nothing to do.');
    $out('This patch file was NOT deleted — remove it manually if you are done.');
    exit(0);
}

/* -------------------------------------------------------------------------
 * 3. Frozen-zone (§5B) integrity pre-check
 * ---------------------------------------------------------------------- */
$out('');
$out('[2/8] Frozen-zone (§5B) integrity check ...');
$frozen = [
    'sitemap <FilesMatch>'          => ['<FilesMatch "sitemap.*\.xml$">', 1],
    'sitemap ForceType'             => ['ForceType application/xml', 1],
    'ten blank lines footprint'     => ["</FilesMatch>\n\n\n\n\n\n\n\n\n\n\n# BEGIN LSCACHE", 1],
    '# BEGIN LSCACHE'               => ['# BEGIN LSCACHE', 1],
    '# END LSCACHE'                 => ['# END LSCACHE', 1],
    'CacheDisable sitemap.xml'      => ['CacheDisable public /sitemap.xml', 1],
    'https/non-www 301'             => ['RewriteRule ^(.*)$ https://boostershop.website/$1 [R=301,L]', 1],
    'legacy-404-301 BEGIN'          => ['# BEGIN legacy-404-301 20260702', 1],
    'legacy-404-301 END'            => ['# END legacy-404-301 20260702', 1],
    'uk-ua sitemap rewrite'         => ['RewriteRule ^uk-ua/sitemap.xml$', 1],
    'OpenCart front controller'     => ['RewriteRule ^([^?]*) index.php?_route_=$1 [L,QSA]', 1],
];
$bad = [];
foreach ($frozen as $label => $f) {
    $n = substr_count($orig, $f[0]);
    $out(sprintf('      %-30s expected %d, found %d', $label, $f[1], $n));
    if ($n !== $f[1]) { $bad[] = $label . ' (expected ' . $f[1] . ', found ' . $n . ')'; }
}
if ($bad) {
    $out('');
    foreach ($bad as $b) { $out('  MISMATCH: ' . $b); }
    $fail('.htaccess does not match the structure this patch was built against (2026-08-05 backup). '
        . 'Do NOT force it. Ask for a fresh .htaccess export and rebuild the block.');
}
$out('      Frozen zone intact.');

/* -------------------------------------------------------------------------
 * 4. Build the appended block
 * ---------------------------------------------------------------------- */
$out('');
$out('[3/8] Building the cache block ...');

$BLOCK = <<<'HTACCESS'
# BEGIN BS-SPEED-1 cache
# TECH-013 WP3 (2026-08-06) — Booster Shop static asset cache policy.
# Appended at end of file. Nothing above this line was modified.
#
# SCOPE: css, js, fonts and images ONLY.
# This block must NEVER match *.xml or *.txt. /sitemap-full.xml and /robots.txt are
# protected (TECH-005-DEEP) and send no Cache-Control today; that must stay true.
# robots.txt is a .txt file — that is why txt is absent from the list below.
#
# Tier 1, code, 30 days: overrides an existing server-level 7-day value for CSS and
# creates one for JS and fonts, which send no Cache-Control at all today. Deliberately
# NOT one year and NOT `immutable`: bootstrap.css, jquery-3.7.1.min.js and
# fa-solid-900.woff2 are unversioned, so a mistake must be able to self-heal.
#
# Tier 2, images, 7 days: equals today's value, so no behavioural change. Images are
# the genuinely unversioned class — image/cache/<name>-250x250.png keeps its filename
# when an admin replaces a product photo — so they must never get a long or immutable
# TTL. A future image swap propagates within 7 days.
#
# ROLLBACK: delete this block (BEGIN..END inclusive), or restore
#           .htaccess.bak-tech013-20260806
<IfModule mod_headers.c>
  <FilesMatch "\.(css|js|mjs|woff2|woff|ttf|otf|eot)$">
    Header set Cache-Control "public, max-age=2592000"
    Header unset Expires
  </FilesMatch>

  <FilesMatch "\.(png|jpe?g|gif|webp|avif|svg|ico)$">
    Header set Cache-Control "public, max-age=604800"
  </FilesMatch>
</IfModule>
# END BS-SPEED-1 cache
HTACCESS;

$new = $orig . $BLOCK . "\n";

/* Append-only proof + block sanity. */
$checks = [
    'append-only: prefix byte-identical' => (strncmp($new, $orig, strlen($orig)) === 0 && strlen($new) > strlen($orig)),
    'original bytes fully preserved'     => (substr($new, 0, strlen($orig)) === $orig),
    'BEGIN marker exactly once'          => (substr_count($new, '# BEGIN BS-SPEED-1 cache') === 1),
    'END marker exactly once'            => (substr_count($new, '# END BS-SPEED-1 cache') === 1),
    'IfModule balanced'                  => (substr_count($BLOCK, '<IfModule') === substr_count($BLOCK, '</IfModule>')),
    'FilesMatch balanced'                => (substr_count($BLOCK, '<FilesMatch') === substr_count($BLOCK, '</FilesMatch>')),
    /* Parse the actual FilesMatch patterns rather than scanning the whole block —
       the comments above legitimately mention xml and txt while matching neither. */
    'FilesMatch patterns exclude xml'    => (static function () use ($BLOCK) {
            preg_match_all('/<FilesMatch\s+"([^"]+)"/', $BLOCK, $m);
            foreach ($m[1] as $pat) { if (stripos($pat, 'xml') !== false) { return false; } }
            return count($m[1]) === 2;
        })(),
    'FilesMatch patterns exclude txt'    => (static function () use ($BLOCK) {
            preg_match_all('/<FilesMatch\s+"([^"]+)"/', $BLOCK, $m);
            foreach ($m[1] as $pat) { if (stripos($pat, 'txt') !== false) { return false; } }
            return count($m[1]) === 2;
        })(),
    'every max-age <= 30 days'           => (static function () use ($BLOCK) {
            preg_match_all('/max-age=(\d+)/', $BLOCK, $m);
            if (!$m[1]) { return false; }
            foreach ($m[1] as $v) { if ((int) $v > 2592000) { return false; } }
            return true;
        })(),
    /* Again: check the emitted header VALUES, not the prose. The comment above
       legitimately explains why `immutable` is not used. */
    'no immutable in any header value'   => (static function () use ($BLOCK) {
            preg_match_all('/Header\s+set\s+Cache-Control\s+"([^"]+)"/i', $BLOCK, $m);
            if (!$m[1]) { return false; }
            foreach ($m[1] as $v) { if (stripos($v, 'immutable') !== false) { return false; } }
            return count($m[1]) === 2;
        })(),
    'frozen zone still intact'           => true,
];
foreach ($frozen as $label => $f) {
    if (substr_count($new, $f[0]) !== $f[1]) { $checks['frozen zone still intact'] = false; }
}
$bad = [];
foreach ($checks as $l => $ok) { $out(sprintf('      %-36s %s', $l, $ok ? 'OK' : 'FAILED')); if (!$ok) { $bad[] = $l; } }
if ($bad) { $fail('Block sanity check failed: ' . implode('; ', $bad)); }
$out('      block is ' . strlen($BLOCK) . ' bytes; .htaccess ' . strlen($orig) . ' -> ' . strlen($new) . ' bytes');

/* -------------------------------------------------------------------------
 * 5. Baseline the protected headers BEFORE writing
 * ---------------------------------------------------------------------- */
$out('');
$out('[4/8] Capturing live protected-file headers BEFORE the change ...');

$fetch = static function (string $url) {
    $status = null; $headers = [];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_HEADER => true, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_USERAGENT => 'BS-TECH013-WP3-selfcheck',
        ]);
        $raw = curl_exec($ch);
        if ($raw !== false) {
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            foreach (explode("\n", $raw) as $line) {
                if (strpos($line, ':') !== false) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }
            }
        }
        curl_close($ch);
        if ($status !== null) { return ['status' => $status, 'headers' => $headers]; }
    }
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 15, 'ignore_errors' => true]]);
    $h = @get_headers($url, true, $ctx);
    if ($h === false) { return null; }
    foreach ($h as $k => $v) {
        if (is_int($k)) { if (preg_match('#HTTP/\S+\s+(\d{3})#', $v, $m)) { $status = (int) $m[1]; } continue; }
        $headers[strtolower($k)] = is_array($v) ? end($v) : $v;
    }
    return $status === null ? null : ['status' => $status, 'headers' => $headers];
};

$protected = ['/sitemap-full.xml', '/robots.txt'];
$before = [];
$netOk = true;
foreach ($protected as $p) {
    $r = $fetch($HOST . $p);
    if ($r === null) { $netOk = false; $out('      ' . $p . ' — request FAILED (no outbound network from CLI?)'); continue; }
    $before[$p] = $r;
    $out(sprintf('      %-22s HTTP %d   Cache-Control: %s', $p, $r['status'],
        isset($r['headers']['cache-control']) ? $r['headers']['cache-control'] : '(none)'));
}
if (!$netOk) {
    $out('      NOTE: the live self-check will be skipped. You MUST verify manually after this run.');
}

/* -------------------------------------------------------------------------
 * 6. Backup (twice), then write
 * ---------------------------------------------------------------------- */
$out('');
$out('[5/8] Backing up .htaccess ...');
$stamp     = date('Ymd-His');
$backupDir = $ROOT . '/_patch_backups/' . $PATCH . '-' . $stamp;
if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
    $fail('Could not create backup directory: ' . $backupDir);
}
if (!copy($abs, $backupDir . '/.htaccess'))     { $fail('Could not back up .htaccess to _patch_backups/'); }
if (!copy($abs, $ROOT . '/' . $BAKNAME))        { $fail('Could not create ' . $BAKNAME); }
$out('      _patch_backups/' . $PATCH . '-' . $stamp . '/.htaccess');
$out('      ' . $BAKNAME . '   <- the rollback file named in handoff §10');

$restore = static function () use ($backupDir, $abs, $out) {
    if (is_file($backupDir . '/.htaccess') && copy($backupDir . '/.htaccess', $abs)) {
        $out('      .htaccess RESTORED from backup.');
        return true;
    }
    $out('      !! AUTOMATIC RESTORE FAILED — restore manually right now:');
    $out('         cp .htaccess.bak-tech013-20260806 .htaccess');
    return false;
};

$out('');
$out('[6/8] Writing .htaccess (append-only) ...');
$bytes = file_put_contents($abs, $new);
if ($bytes === false || $bytes !== strlen($new)) {
    $out('      WRITE FAILED.');
    $restore();
    $fail('Write failed; .htaccess restored from ' . $backupDir);
}
$out('      wrote ' . $bytes . ' bytes');

$onDisk = file_get_contents($abs);
if ($onDisk !== $new) {
    $out('      Content on disk does not match what was written.');
    $restore();
    $fail('Post-write verification failed; .htaccess restored.');
}
$out('      verified byte-for-byte on disk');

/* -------------------------------------------------------------------------
 * 7. LIVE SELF-CHECK — the closest thing to a syntax gate .htaccess allows
 * ---------------------------------------------------------------------- */
$out('');
$out('[7/8] Live self-check against ' . $HOST . ' ...');

$selfCheck = 'skipped';
if (!$netOk) {
    $out('      SKIPPED — no outbound HTTP from this CLI.');
    $out('      >>> VERIFY MANUALLY NOW, BEFORE LEAVING THIS TERMINAL:');
    $out('      >>>   curl -sI ' . $HOST . '/robots.txt');
    $out('      >>>   curl -sI ' . $HOST . '/sitemap-full.xml');
    $out('      >>>   curl -sI ' . $HOST . '/');
    $out('      >>> If anything 500s or a protected file gained Cache-Control:');
    $out('      >>>   cp ' . $BAKNAME . ' .htaccess');
    $selfCheck = 'skipped (no network)';
} else {
    $problems = [];
    // Give the server a moment to pick up the new .htaccess.
    sleep(2);

    foreach ($protected as $p) {
        $r = $fetch($HOST . $p);
        if ($r === null) { $problems[] = $p . ' — request failed after the change'; continue; }
        $cc = isset($r['headers']['cache-control']) ? $r['headers']['cache-control'] : null;
        $ccBefore = isset($before[$p]['headers']['cache-control']) ? $before[$p]['headers']['cache-control'] : null;
        $out(sprintf('      %-22s HTTP %d   Cache-Control: %s', $p, $r['status'], $cc === null ? '(none)' : $cc));
        if ($r['status'] !== 200)  { $problems[] = $p . ' returned HTTP ' . $r['status']; }
        if ($cc !== $ccBefore)     { $problems[] = $p . ' Cache-Control changed: ' . var_export($ccBefore, true) . ' -> ' . var_export($cc, true) . ' — SCOPE LEAK'; }
    }

    $home = $fetch($HOST . '/');
    if ($home === null) { $problems[] = 'home page request failed after the change'; }
    else {
        $out(sprintf('      %-22s HTTP %d', '/', $home['status']));
        if ($home['status'] >= 500) { $problems[] = 'home page returned HTTP ' . $home['status']; }
    }

    if ($problems) {
        $out('');
        foreach ($problems as $p) { $out('      PROBLEM: ' . $p); }
        $out('');
        $out('[AUTO-ROLLBACK] The live check failed. Reverting .htaccess now.');
        $restore();
        $out('');
        $out('WP3 was NOT applied. Report the problems above before retrying.');
        exit(1);
    }

    // Informational: did the intended assets actually pick up the new value?
    $out('');
    $out('      Intended effect (informational — CDN/edge may lag a few seconds):');
    foreach ([
        '/catalog/view/javascript/jquery/jquery-3.7.1.min.js' => 'jquery-3.7.1.min.js',
        '/catalog/view/stylesheet/bootstrap.css'              => 'bootstrap.css',
        '/catalog/view/stylesheet/fonts/fontawesome/webfonts/fa-solid-900.woff2' => 'fa-solid-900.woff2',
    ] as $path => $label) {
        $r = $fetch($HOST . $path);
        $cc = ($r && isset($r['headers']['cache-control'])) ? $r['headers']['cache-control'] : '(none)';
        $okMark = (strpos($cc, 'max-age=2592000') !== false) ? 'OK' : 'check manually';
        $out(sprintf('        %-24s %-42s %s', $label, $cc, $okMark));
    }
    $selfCheck = 'passed';
}

/* -------------------------------------------------------------------------
 * 8. Done
 * ---------------------------------------------------------------------- */
$out('');
$out('[8/8] Done.');
$out('');
$out('=============================================================');
$out(' already_applied=no');
$out(' result=SUCCESS');
$out(' live_self_check=' . $selfCheck);
$out(' backup=_patch_backups/' . $PATCH . '-' . $stamp . '/.htaccess');
$out(' rollback_file=' . $BAKNAME);
$out('=============================================================');
$out('');
$out('Applied: one appended block, # BEGIN BS-SPEED-1 cache ... # END BS-SPEED-1 cache');
$out('  code   (css, js, fonts)  -> public, max-age=2592000   (30 days)');
$out('  images (png/jpg/gif/webp/avif/svg/ico) -> public, max-age=604800  (7 days)');
$out('  xml and txt are NOT matched. HTML is NOT matched.');
$out('');
$out('!! READ THIS BEFORE LOOKING AT PSI !!');
$out('  WP3 changes REPEAT-VISIT behaviour only. PSI lab runs are cold-cache first');
$out('  loads, so LCP, FCP and Speed Index will NOT move, and the performance score');
$out('  will look flat. That is the expected result, not a failure. The only item that');
$out('  should change is "Serve static assets with an efficient cache policy"');
$out('  (281-344 KiB today) which should drop to roughly zero.');
$out('');
$out('OWNER QA:');
$out('  1. Acceptance — expect max-age=2592000 or longer on all three:');
$out('     curl -sI ' . $HOST . '/catalog/view/javascript/jquery/jquery-3.7.1.min.js');
$out('     curl -sI ' . $HOST . '/catalog/view/stylesheet/bootstrap.css');
$out('     curl -sI ' . $HOST . '/catalog/view/stylesheet/fonts/fontawesome/webfonts/fa-solid-900.woff2');
$out('  2. Protected files — must be byte-identical to');
$out('     diagnostics/TECH-013_header-baselines_20260805.md (ignore Date/Keep-Alive/alt-svc):');
$out('     curl -sI ' . $HOST . '/sitemap-full.xml');
$out('     curl -sI ' . $HOST . '/robots.txt');
$out('     ANY difference on these two = revert WP3 immediately.');
$out('  3. All three benchmark URLs render normally; no console errors.');
$out('  4. bs-checkout-smoke — full 11-step run.');
$out('  5. Note: CSS may still show an Expires header from server config. Cache-Control');
$out('     max-age wins over Expires per HTTP spec, so that is harmless.');
$out('');
$out('ROLLBACK (either one):');
$out('  delete the # BEGIN BS-SPEED-1 cache ... # END block, or');
$out('  cp ' . $BAKNAME . ' .htaccess');
$out('Never hand-reconstruct .htaccess.');
$out('');
$out('NOTE: ' . $BAKNAME . ' is left in ~/public_html on purpose as the named rollback');
$out('      file. Remove it once WP3 is signed off.');
$out('');

if (@unlink(__FILE__)) { $out('Patch file self-deleted.'); }
else { $out('NOTE: could not self-delete ' . basename(__FILE__) . ' — remove it manually.'); }
$out('');
exit(0);
