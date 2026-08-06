<?php
/**
 * TECH-013 — WP1: render-blocking head assets
 * =============================================================================
 * Task      : TECH-013 (BS-SPEED-1), Stage 1, work package 1 of 4
 * Handoff   : handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md (Rev. 2026-08-05)
 * Author    : Claude Code (authorised patch author, AGENTS.md amendment 2026-08-05)
 * Date      : 2026-08-05
 * Risk      : MEDIUM — global head template, renders on every route incl. checkout
 * DB changes: NONE
 *
 * RUN FROM  : ~/public_html    ->    php TECH-013_wp1-render-blocking_20260805.php
 *
 * -----------------------------------------------------------------------------
 * WHAT THIS CHANGES (4 changes, all in the <head>)
 * -----------------------------------------------------------------------------
 *
 * 1. HOIST THE MANROPE @import  [the named root cause, largest single lever]
 *    Root cause: catalog/view/stylesheet/boostershop-ds.css line 13 contains
 *        @import url('https://fonts.googleapis.com/css2?family=Manrope:...');
 *    An @import is invisible to the browser preload scanner. It cannot start until
 *    boostershop-ds.css (158,573 bytes) has fully downloaded AND been parsed, which
 *    serialises a four-hop critical chain:
 *        header.twig -> boostershop-ds.css -> fonts.googleapis.com -> fonts.gstatic.com
 *    Manrope is the design-system body font (boostershop-ds.css:79, 344 elements per
 *    the owner's §5C measurement), so that chain sits directly on the render path.
 *    Fix: delete the @import; add an equivalent <link rel="stylesheet"> in the head
 *    immediately after the existing font preconnects, so the request starts at once.
 *
 *    NOTE: boostershop-ds.css is served with Cache-Control: public, max-age=604800
 *    (verified 2026-08-05, diagnostics/TECH-013_header-baselines_20260805.md). Editing
 *    its contents without changing the ?v= string would leave returning visitors on the
 *    cached copy — still carrying the @import — for up to 7 days, producing a DUPLICATE
 *    Manrope request and no speed gain. This patch therefore also bumps the version
 *    string to ?v=tech013-wp1-20260805. header.twig is the only file that references
 *    this stylesheet (verified), so one bump is sufficient.
 *
 * 2. REMOVE THE UNUSED Inter <link>
 *    Owner measurement §5C: Inter appears in ZERO computed font-family values and ZERO
 *    entries of the document.fonts loaded set. It is a fully unused render-blocking
 *    request to fonts.googleapis.com.
 *
 * 3. DE-DUPLICATE THE PRECONNECT HINTS
 *    header.twig lines 45/46 are repeated verbatim at 51/52 — four hints where two are
 *    needed. The duplicate pair is removed; lines 45/46 are kept.
 *    The gstatic preconnect is DELIBERATELY KEPT (handoff §5A.4): all Google Fonts
 *    families serve their .woff2 from fonts.gstatic.com, so that origin is genuinely
 *    used. Removing it would be a regression, not an optimisation.
 *
 * 4. DEFER THE REMAINING SYNCHRONOUS HEAD JS — NON-CHECKOUT ROUTES ONLY
 *    Deferred: common.js, booster-product-polish.js, and everything emitted by
 *    getScripts('header') (ps-enhanced-measurement.js, ps_live_search.js, and on
 *    product routes jquery.magnific-popup.min.js).
 *    jQuery STAYS SYNCHRONOUS on every route — see the dependency proof below.
 *    bs-faq.js and patch-mobile-search-menu-redesign.js already carry defer; untouched.
 *
 * -----------------------------------------------------------------------------
 * WHAT THIS DELIBERATELY DOES **NOT** CHANGE
 * -----------------------------------------------------------------------------
 *
 * - The JetBrains Mono <link> (header.twig:54) is KEPT. Handoff §5E required a mobile
 *   menu screenshot check before removal, because §5C was captured with the menu
 *   closed. That check was run on 2026-08-05 at 390x844/dpr2 and it FAILED:
 *   opening the burger menu DOES load JetBrains Mono 600, and .bs-menu__label glyph
 *   advances change measurably when the link is removed —
 *       "Каталог"    51.453px -> 47.766px  (-7.2%), line-height 14px -> 13px
 *       "Інформація" 73.500px -> 68.234px  (-7.2%), line-height 14px -> 13px
 *   Re-adding the link restored both to the original values exactly, confirming the
 *   font — not measurement drift — is the cause. Per §5E ("If not, keep the link and
 *   record why") the removal is dropped. Evidence:
 *   diagnostics/TECH-013_wp1-jetbrains-mono-check_20260805.md
 *
 * - No async/print-onload CSS loading. Proposed separately for owner decision; not
 *   shipped blind into a global template per AGENTS.md UI/CSS patch discipline.
 *
 * - Nothing in handoff §5 or §5B. No .htaccess, robots, sitemap, canonical, schema,
 *   checkout/payment logic, or image asset is touched.
 *
 * -----------------------------------------------------------------------------
 * DEFER DEPENDENCY PROOF (AGENTS.md: prove it, do not guess)
 * -----------------------------------------------------------------------------
 * defer => script executes after parsing, in document order, BEFORE DOMContentLoaded.
 *
 *  - jQuery: left SYNCHRONOUS. The inline live-search initialiser at the end of
 *    header.twig opens with `if (!window.jQuery) return;` DURING parse. Deferring
 *    jQuery would make that guard fail and silently kill live search.
 *  - ps_live_search.js defines $.fn.pslivesearch. Its only consumer is that same
 *    inline block, whose body is wrapped in `window.jQuery(function ($) { ... })`
 *    — a jQuery ready callback, which fires on DOMContentLoaded, i.e. AFTER all
 *    deferred scripts. Safe.
 *  - common.js defines the globals getURLVar() and chain (var chain = new Chain()).
 *    Scanned the rendered HTML of /, /catalog/Pokemon, the Mega-Symphonia product page
 *    and the cart page: ZERO parse-time references to either. The only consumers of
 *    `chain` are catalog/view/template/checkout/checkout.twig:1225,1297 and
 *    catalog/view/javascript/checkout-reskin.js:439 — all inside function bodies that
 *    run on user interaction, all guarded by `if (window.chain && ...)` with a direct
 *    send() fallback. Safe under defer; and checkout is excluded anyway (below).
 *  - ps-enhanced-measurement.js top level is one delegated binding,
 *    $(document).on('click', '[data-ps-track-event]', ...). Delegated on document, so
 *    DOM readiness is irrelevant; needs only jQuery, which stays sync. Safe.
 *  - booster-product-polish.js is a self-contained IIFE with its own ready() helper
 *    that tests document.readyState. Safe by construction.
 *  - jquery.magnific-popup.min.js (product routes only): its consumer is inside
 *    $(document).ready(...) on the product page. Safe.
 *  - No document.write anywhere in the rendered pages or in any affected script —
 *    verified. document.write is the classic defer-breaker.
 *  - Relative execution order among the deferred files is preserved (document order).
 *    One intentional ordering change: bs-faq.js, already deferred, previously ran
 *    after the then-synchronous ps_live_search.js; it now runs before it. bs-faq.js is
 *    a self-contained FAQ accordion with no dependency on live search. Harmless.
 *
 * WHY CHECKOUT IS EXCLUDED
 *    Handoff §4 WP1: "Scope defer changes to catalog/home/product rendering paths. Do
 *    not defer anything the checkout flow loads synchronously without first proving the
 *    dependency." The real checkout route could not be captured for inspection — it
 *    302-redirects for a guest with an empty cart, and populating a production cart to
 *    force it is a state-changing action this executor will not take. getScripts('header')
 *    is route-dependent (the product route already proves it varies), so a module script
 *    unique to the checkout route cannot be ruled out from the evidence available.
 *    Rather than defer an unproven set on the single most protected surface in this
 *    project, the defer attribute is emitted only when the route is NOT checkout.
 *    On checkout routes the rendered <head> JS is byte-identical to today.
 *    Changes 1-3 (fonts/preconnect) still apply everywhere — they are proven safe and
 *    Manrope is the body font on checkout too, so checkout benefits from the hoist.
 *
 * -----------------------------------------------------------------------------
 * SAFETY / ROLLBACK
 * -----------------------------------------------------------------------------
 * - Every target is verified to exist and every anchor is counted BEFORE any write.
 *   Any count mismatch aborts with a clear message and writes nothing.
 * - Originals are copied to _patch_backups/<patch>-<timestamp>/ preserving paths.
 * - Syntax gate: the modified header.twig is parsed with the site's own Twig library
 *   (DIR_STORAGE/vendor/autoload.php). php -l is not meaningful for .twig/.css, so a
 *   real Twig parse replaces it; a control parse of the ORIGINAL file runs first so a
 *   quirk of the environment cannot produce a false abort. CSS is brace-balance checked.
 *   Any failure restores every file from the backup.
 * - Idempotent: a second run detects the marker and exits with already_applied=yes.
 * - Self-deletes after a successful fresh apply.
 *
 * ROLLBACK: copy the files from _patch_backups/<patch>-<timestamp>/ back over
 *           catalog/view/template/common/header.twig
 *           catalog/view/stylesheet/boostershop-ds.css
 *           No DB change is made, so a file restore is a complete rollback.
 * =============================================================================
 */

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
}

$PATCH = 'TECH-013_wp1-render-blocking_20260805';
$ROOT  = __DIR__;

$out = static function ($msg = '') { echo $msg . PHP_EOL; };
$fail = static function ($msg) use ($out) {
    $out('');
    $out('[ABORT] ' . $msg);
    $out('Nothing was written. The site is unchanged.');
    exit(1);
};

$out('=============================================================');
$out(' ' . $PATCH);
$out(' TECH-013 WP1 — render-blocking head assets');
$out('=============================================================');
$out('Working directory: ' . $ROOT);
$out('');

/* -------------------------------------------------------------------------
 * 0. Sanity: are we in ~/public_html?
 * ---------------------------------------------------------------------- */
if (!is_file($ROOT . '/index.php') || !is_dir($ROOT . '/catalog/view/template/common')) {
    $fail('This does not look like the OpenCart web root. Upload the patch to ~/public_html and run it from there.');
}

$HEADER = 'catalog/view/template/common/header.twig';
$DSCSS  = 'catalog/view/stylesheet/boostershop-ds.css';

/* -------------------------------------------------------------------------
 * 1. File-exists + writability check
 * ---------------------------------------------------------------------- */
$out('[1/7] Checking target files ...');
foreach ([$HEADER, $DSCSS] as $rel) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs)) {
        $fail('Target file not found: ' . $rel);
    }
    if (!is_readable($abs) || !is_writable($abs)) {
        $fail('Target file is not readable/writable: ' . $rel);
    }
    $out('      OK  ' . $rel . '  (' . filesize($abs) . ' bytes)');
}

$headerOrig = file_get_contents($ROOT . '/' . $HEADER);
$dsOrig     = file_get_contents($ROOT . '/' . $DSCSS);
if ($headerOrig === false || $dsOrig === false) {
    $fail('Could not read one of the target files.');
}

/* -------------------------------------------------------------------------
 * 2. Idempotency marker
 * ---------------------------------------------------------------------- */
$MARKER = 'bs_wp1_defer';
if (strpos($headerOrig, $MARKER) !== false) {
    $out('');
    $out('already_applied=yes');
    $out('header.twig already contains the TECH-013 WP1 marker (' . $MARKER . ').');
    $out('No changes made. This patch file was NOT deleted — remove it manually if you are done.');
    exit(0);
}

/* -------------------------------------------------------------------------
 * 3. Anchor pre-check — exact counts, before any write
 * ---------------------------------------------------------------------- */
$out('');
$out('[2/7] Anchor pre-check ...');

/* --- header.twig anchors ------------------------------------------------ */

// A1: duplicate preconnect pair + unused Inter link. JetBrains line is included in the
//     anchor purely to make the match unambiguous; it is re-emitted unchanged.
$A1_FIND = <<<'ANCHOR'
  <link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
ANCHOR;

$A1_REPL = <<<'ANCHOR'
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
ANCHOR;

// A2: hoist Manrope in front of the DS stylesheet, and bump the DS cache-buster.
$A2_FIND = <<<'ANCHOR'
  <link href="catalog/view/stylesheet/boostershop-ds.css?v=toc003-pay001-phase2c-20260725" type="text/css" rel="stylesheet"/>
ANCHOR;

$A2_REPL = <<<'ANCHOR'
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
  <link href="catalog/view/stylesheet/boostershop-ds.css?v=tech013-wp1-20260805" type="text/css" rel="stylesheet"/>
ANCHOR;

// A3: jQuery stays sync; define the route-scoped defer flag right after it.
$A3_FIND = <<<'ANCHOR'
  <script src="{{ jquery }}" type="text/javascript"></script>
ANCHOR;

$A3_REPL = <<<'ANCHOR'
  {# TECH-013 WP1 (2026-08-05): jQuery MUST stay synchronous on every route. The inline
     live-search initialiser near the end of this file runs during parse and opens with
     `if (!window.jQuery) return;`, and common.js publishes the `chain` global that
     checkout relies on. Do not add defer to the tag below. #}
  <script src="{{ jquery }}" type="text/javascript"></script>
  {# TECH-013 WP1: defer non-critical head JS on catalog/home/product routes only.
     Checkout keeps today's synchronous behaviour — handoff §4 WP1 forbids deferring
     anything the checkout flow loads synchronously without a proven dependency, and the
     checkout route's getScripts('header') set could not be captured for inspection. #}
  {% set bs_wp1_route = route is defined ? route : '' %}
  {% set bs_wp1_defer = (bs_wp1_route starts with 'checkout/' or bs_wp1_route starts with 'extension/SimpleCheckout') ? '' : ' defer' %}
ANCHOR;

// A4: defer the two hard-coded head scripts.
$A4_FIND = <<<'ANCHOR'
  <script src="catalog/view/javascript/common.js?v=cartjslogs-20260526" type="text/javascript"></script>
  <script src="catalog/view/javascript/booster-product-polish.js" type="text/javascript"></script>
ANCHOR;

$A4_REPL = <<<'ANCHOR'
  <script src="catalog/view/javascript/common.js?v=cartjslogs-20260526" type="text/javascript"{{ bs_wp1_defer }}></script>
  <script src="catalog/view/javascript/booster-product-polish.js" type="text/javascript"{{ bs_wp1_defer }}></script>
ANCHOR;

// A5: defer the module scripts emitted by getScripts('header').
$A5_FIND = <<<'ANCHOR'
    <script src="{{ script.href }}" type="text/javascript"></script>
ANCHOR;

$A5_REPL = <<<'ANCHOR'
    <script src="{{ script.href }}" type="text/javascript"{{ bs_wp1_defer }}></script>
ANCHOR;

/* --- boostershop-ds.css anchor ------------------------------------------ */

$A6_FIND = <<<'ANCHOR'
/* -- Fonts --------------------------------------------------------------- */
@import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
ANCHOR;

$A6_REPL = <<<'ANCHOR'
/* -- Fonts --------------------------------------------------------------- */
/* TECH-013 WP1 (2026-08-05): the Manrope @import that lived here was hoisted to a
   <link rel="stylesheet"> in catalog/view/template/common/header.twig.
   An @import is invisible to the browser preload scanner: it could not begin until this
   stylesheet had fully downloaded AND parsed, serialising
   header.twig -> boostershop-ds.css -> fonts.googleapis.com -> fonts.gstatic.com.
   Manrope is the DS body font, so that chain sat on the critical render path.
   DO NOT re-add the @import here — add/adjust the <link> in header.twig instead. */
ANCHOR;

$headerAnchors = [
    'A1 duplicate preconnect pair + unused Inter link' => [$A1_FIND, $A1_REPL, 1],
    'A2 Manrope hoist + DS cache-buster bump'          => [$A2_FIND, $A2_REPL, 1],
    'A3 jQuery stays sync + defer flag'                => [$A3_FIND, $A3_REPL, 1],
    'A4 common.js + booster-product-polish.js defer'   => [$A4_FIND, $A4_REPL, 1],
    'A5 getScripts(header) loop defer'                 => [$A5_FIND, $A5_REPL, 1],
];
$cssAnchors = [
    'A6 Manrope @import removal' => [$A6_FIND, $A6_REPL, 1],
];

$problems = [];
foreach ($headerAnchors as $label => $a) {
    $n = substr_count($headerOrig, $a[0]);
    $out(sprintf('      %-52s expected %d, found %d', $label, $a[2], $n));
    if ($n !== $a[2]) {
        $problems[] = $HEADER . ' :: ' . $label . ' (expected ' . $a[2] . ', found ' . $n . ')';
    }
}
foreach ($cssAnchors as $label => $a) {
    $n = substr_count($dsOrig, $a[0]);
    $out(sprintf('      %-52s expected %d, found %d', $label, $a[2], $n));
    if ($n !== $a[2]) {
        $problems[] = $DSCSS . ' :: ' . $label . ' (expected ' . $a[2] . ', found ' . $n . ')';
    }
}

if ($problems) {
    $out('');
    foreach ($problems as $p) {
        $out('  MISMATCH: ' . $p);
    }
    $fail('Anchor pre-check failed. The live files differ from the 2026-08-05 backup this '
        . 'patch was built against. Do not force it — re-export the files and rebuild the patch.');
}
$out('      All anchors matched exactly.');

/* -------------------------------------------------------------------------
 * 4. Build the modified content in memory
 * ---------------------------------------------------------------------- */
$out('');
$out('[3/7] Building modified content in memory ...');

$headerNew = $headerOrig;
foreach ($headerAnchors as $label => $a) {
    $headerNew = str_replace($a[0], $a[1], $headerNew);
}
$dsNew = str_replace($A6_FIND, $A6_REPL, $dsOrig);

if ($headerNew === $headerOrig) {
    $fail('header.twig content did not change after replacement — internal error, aborting.');
}
if ($dsNew === $dsOrig) {
    $fail('boostershop-ds.css content did not change after replacement — internal error, aborting.');
}

/* Structural expectations on the result. */
$expect = [
    'header still closes <head>'          => (strpos($headerNew, '</head>') !== false),
    'header still opens <body class="bs">'=> (strpos($headerNew, '<body class="bs">') !== false),
    'jQuery tag still has no defer'       => (strpos($headerNew, '<script src="{{ jquery }}" type="text/javascript"></script>') !== false),
    'Manrope <link> present'              => (substr_count($headerNew, 'css2?family=Manrope') === 1),
    'Inter <link> gone'                   => (strpos($headerNew, 'css2?family=Inter') === false),
    'JetBrains Mono <link> KEPT'          => (substr_count($headerNew, 'css2?family=JetBrains') === 1),
    'IBM Plex <link> kept'                => (substr_count($headerNew, 'css2?family=IBM+Plex') === 1),
    'googleapis preconnect exactly once'  => (substr_count($headerNew, 'rel="preconnect" href="https://fonts.googleapis.com"') === 1),
    'gstatic preconnect exactly once'     => (substr_count($headerNew, 'rel="preconnect" href="https://fonts.gstatic.com"') === 1),
    'defer flag defined once'             => (substr_count($headerNew, '{% set bs_wp1_defer') === 1),
    'defer flag used three times'         => (substr_count($headerNew, '{{ bs_wp1_defer }}') === 3),
    'DS cache-buster bumped'              => (strpos($headerNew, 'boostershop-ds.css?v=tech013-wp1-20260805') !== false),
    'old DS cache-buster gone'            => (strpos($headerNew, 'v=toc003-pay001-phase2c-20260725') === false),
    'CSS: Manrope @import removed'        => (strpos($dsNew, "@import url('https://fonts.googleapis.com/css2?family=Manrope") === false),
    // Must test for a real statement, not the bare word: the replacement comment above
    // legitimately contains the text "@import" while declaring none.
    'CSS: no @import statement left'      => (strpos($dsNew, '@import url(') === false),
    'CSS: brace balance preserved'        => (substr_count($dsNew, '{') === substr_count($dsOrig, '{')
                                              && substr_count($dsNew, '}') === substr_count($dsOrig, '}')),
];
$bad = [];
foreach ($expect as $label => $ok) {
    $out(sprintf('      %-38s %s', $label, $ok ? 'OK' : 'FAILED'));
    if (!$ok) { $bad[] = $label; }
}
if ($bad) {
    $fail('Post-replacement structural check failed: ' . implode('; ', $bad));
}

/* -------------------------------------------------------------------------
 * 5. Twig syntax gate (replaces php -l, which is meaningless for .twig/.css)
 * ---------------------------------------------------------------------- */
$out('');
$out('[4/7] Twig syntax gate ...');

$twigVerdict = 'skipped';
$twigError   = '';

$autoload = null;
if (is_file($ROOT . '/config.php')) {
    // config.php only defines constants; it opens no connection.
    include_once $ROOT . '/config.php';
    if (defined('DIR_STORAGE') && is_file(DIR_STORAGE . 'vendor/autoload.php')) {
        $autoload = DIR_STORAGE . 'vendor/autoload.php';
    }
}

if ($autoload !== null) {
    require_once $autoload;
    if (class_exists('\\Twig\\Environment') && class_exists('\\Twig\\Source')) {
        try {
            $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader([]));

            // Control parse of the ORIGINAL file first. If the original does not parse
            // in a vanilla environment (custom tags/extensions), the gate is unreliable
            // and must be skipped rather than produce a false abort.
            $controlOk = true;
            try {
                $env->parse($env->tokenize(new \Twig\Source($headerOrig, 'header.twig.orig')));
            } catch (\Throwable $e) {
                $controlOk = false;
                $twigError = 'control parse of the unmodified file failed: ' . $e->getMessage();
            }

            if (!$controlOk) {
                $twigVerdict = 'skipped';
                $out('      Skipped — ' . $twigError);
                $out('      (structural checks in step [3/7] still applied)');
            } else {
                $env->parse($env->tokenize(new \Twig\Source($headerNew, 'header.twig')));
                $twigVerdict = 'passed';
                $out('      PASSED — modified header.twig parses cleanly with the site Twig library.');
            }
        } catch (\Throwable $e) {
            $twigVerdict = 'failed';
            $twigError   = $e->getMessage();
        }
    } else {
        $out('      Skipped — Twig classes not found via ' . $autoload);
    }
} else {
    $out('      Skipped — could not locate DIR_STORAGE/vendor/autoload.php');
}

if ($twigVerdict === 'failed') {
    $fail('Modified header.twig FAILED Twig parsing: ' . $twigError . ' — nothing was written.');
}

/* -------------------------------------------------------------------------
 * 6. Backup, then write
 * ---------------------------------------------------------------------- */
$out('');
$out('[5/7] Backing up originals ...');

$stamp     = date('Ymd-His');
$backupDir = $ROOT . '/_patch_backups/' . $PATCH . '-' . $stamp;
foreach ([$HEADER, $DSCSS] as $rel) {
    $dest = $backupDir . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
        $fail('Could not create backup directory: ' . dirname($dest));
    }
    if (!copy($ROOT . '/' . $rel, $dest)) {
        $fail('Could not back up ' . $rel);
    }
    $out('      ' . $rel . '  ->  _patch_backups/' . $PATCH . '-' . $stamp . '/' . $rel);
}

$restore = static function () use ($backupDir, $ROOT, $HEADER, $DSCSS, $out) {
    foreach ([$HEADER, $DSCSS] as $rel) {
        if (is_file($backupDir . '/' . $rel)) {
            copy($backupDir . '/' . $rel, $ROOT . '/' . $rel);
        }
    }
    $out('      Files restored from backup.');
};

$out('');
$out('[6/7] Writing changes ...');

$written = [];
$writeOk = true;
foreach ([[$HEADER, $headerNew], [$DSCSS, $dsNew]] as $pair) {
    $bytes = file_put_contents($ROOT . '/' . $pair[0], $pair[1]);
    if ($bytes === false || $bytes !== strlen($pair[1])) {
        $writeOk = false;
        $out('      WRITE FAILED: ' . $pair[0]);
        break;
    }
    $written[] = $pair[0];
    $out('      wrote ' . $pair[0] . '  (' . $bytes . ' bytes)');
}

if (!$writeOk) {
    $out('');
    $out('[ROLLBACK] A write failed — restoring originals.');
    $restore();
    $fail('Write failed. Originals restored from ' . $backupDir);
}

/* Re-read from disk and confirm what actually landed. */
$out('');
$out('[7/7] Verifying written files ...');
$headerCheck = file_get_contents($ROOT . '/' . $HEADER);
$dsCheck     = file_get_contents($ROOT . '/' . $DSCSS);

$postOk = ($headerCheck === $headerNew) && ($dsCheck === $dsNew);
if (!$postOk) {
    $out('      Content on disk does not match what was written.');
    $out('');
    $out('[ROLLBACK] Restoring originals.');
    $restore();
    $fail('Post-write verification failed. Originals restored from ' . $backupDir);
}
$out('      Both files verified byte-for-byte on disk.');

/* -------------------------------------------------------------------------
 * 7. Done
 * ---------------------------------------------------------------------- */
$out('');
$out('=============================================================');
$out(' already_applied=no');
$out(' result=SUCCESS');
$out(' twig_syntax_gate=' . $twigVerdict);
$out(' backup=_patch_backups/' . $PATCH . '-' . $stamp . '/');
$out('=============================================================');
$out('');
$out('Applied:');
$out('  1. Manrope @import hoisted from boostershop-ds.css:13 to a <link> in the head');
$out('     (+ DS cache-buster bumped to ?v=tech013-wp1-20260805 so returning visitors');
$out('      do not keep the old @import for up to 7 days)');
$out('  2. Unused Inter <link> removed');
$out('  3. Duplicate preconnect pair removed (gstatic hint deliberately KEPT)');
$out('  4. common.js, booster-product-polish.js and getScripts(header) deferred');
$out('     on non-checkout routes only; jQuery left synchronous everywhere');
$out('');
$out('NOT changed: JetBrains Mono <link> kept — the §5E mobile-menu check failed');
$out('             (the font does load and does change .bs-menu__label metrics).');
$out('');
$out('OWNER QA — please check now, before deploying any further patch:');
$out('  1. Home, /catalog/Pokemon and the Mega-Symphonia product page: layout and');
$out('     fonts unchanged, no console errors.');
$out('  2. Open the mobile burger menu — the "Каталог" / "Інформація" labels must look');
$out('     exactly as before.');
$out('  3. Live search in the header returns suggestions.');
$out('  4. Add to cart, open the mini-cart, and open the login modal.');
$out('  5. bs-checkout-smoke — full 11-step run (header.twig renders on checkout too).');
$out('  6. Confirm Microsoft Clarity still fires.');
$out('  7. Re-run PSI (mobile + desktop) on all three benchmark URLs and re-identify');
$out('     the LCP element — it is expected to move off the cookie banner.');
$out('');
$out('Protected-file headers must be unchanged (they are not touched by this patch):');
$out('  curl -sI https://boostershop.website/sitemap-full.xml');
$out('  curl -sI https://boostershop.website/robots.txt');
$out('  Compare against diagnostics/TECH-013_header-baselines_20260805.md');
$out('  (ignore the Date, Keep-Alive and alt-svc lines).');
$out('');

if (@unlink(__FILE__)) {
    $out('Patch file self-deleted.');
} else {
    $out('NOTE: could not self-delete ' . basename(__FILE__) . ' — please remove it manually.');
}
$out('');
exit(0);
