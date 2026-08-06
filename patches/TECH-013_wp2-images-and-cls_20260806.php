<?php
/**
 * TECH-013 — WP2: image payload + header-logo space reservation + LCP card
 * =============================================================================
 * Task      : TECH-013 (BS-SPEED-1), Stage 1, work package 3 of 4
 *             (revised order WP1 -> WP4 -> WP2 -> WP3, handoff §5A)
 * Handoff   : handoffs/handoff_TECH-013_mobile-cwv-stage1_20260804.md, §4 WP2 + §5D
 * Author    : Claude Code (authorised patch author, AGENTS.md amendment 2026-08-05)
 * Date      : 2026-08-06
 * Risk      : LOW-MEDIUM — global header template, shared DS stylesheet, home and
 *             category templates. No checkout logic, no DB, no .htaccess.
 * DB changes: NONE
 *
 * RUN FROM  : ~/public_html    ->    php TECH-013_wp2-images-and-cls_20260806.php
 *
 * !! THE TWO RE-EXPORTED IMAGES MUST BE UPLOADED **BEFORE** RUNNING THIS PATCH.  !!
 *    See "IMAGE UPLOAD" below. The runner verifies they are in place and refuses
 *    to run if they are not, because the markup it writes declares the new sizes.
 *
 * -----------------------------------------------------------------------------
 * PHP VERSION
 * -----------------------------------------------------------------------------
 * The account's default CLI php is 8.0.30; the site's Composer vendor tree needs 8.1.
 * This patch edits THREE Twig templates, so the Twig syntax gate is genuinely useful
 * here. It is therefore best run as:
 *
 *     /opt/alt/php81/usr/bin/php TECH-013_wp2-images-and-cls_20260806.php
 *
 * Run under the default 8.0 CLI it still works: the runner DETECTS the version,
 * prints a loud notice naming the php81 path, SKIPS the Twig gate rather than
 * exploding inside Composer, and falls back to structural checks. It never emits a
 * raw Composer error. Every template change below was already verified locally
 * against the site's own Twig 3.18.0 before delivery.
 *
 * -----------------------------------------------------------------------------
 * WHY THE HEADER LOGO CLS DIAGNOSIS CHANGED — READ BEFORE JUDGING THE RESULT
 * -----------------------------------------------------------------------------
 * The prep note (diagnostics/TECH-013_wp2-logo-cls-prep_20260806.md) named
 * boostershop-ds.css:834 as the winning rule. **That was wrong, and so was the
 * earlier `img-fluid` theory.** Measured in-browser on 2026-08-06, seven rules match
 * `.bs-header__logo img`; the two that actually win are:
 *
 *   boostershop-ds.css:2292  body.bs .bs-header__logo img { height:42px !important;
 *                            max-width:180px !important; width:auto !important }
 *   boostershop-ds.css:2375  @media (max-width:768px) { …same selector… height:32px
 *                            !important; max-width:128px !important }
 *
 * Both carry !important AND higher specificity than :834 (`body.bs` adds a class and
 * an element), so :834's 46px and :839's 34px are DEAD CODE. This matches the owner's
 * live captures exactly: 42 px painted at 1411 px, 32 px at 390 px. Mystery resolved.
 *
 * HONEST FINDING — this patch will probably NOT move desktop category CLS to 0:
 *   - Measured directly: the logo box is 135.3x42 BEFORE the image bytes arrive and
 *     135.3x42 after. Space is already reserved; the width/height attributes supply a
 *     working aspect-ratio hint (computed `aspect-ratio: auto 1498 / 465`).
 *   - The logo is identical on all three pages, yet home = 0.003 and product = 0.003
 *     while category = 0.25. A shared element cannot explain a category-only shift.
 *   - Every image on the category page already carries width and height (0 without).
 *   - The cookie banner is position:fixed, so it cannot push content.
 * PSI's "image elements do not have an explicit width and height" audit (which does
 * flag this logo, because CSS overrides the attributes) is a DIFFERENT audit from the
 * layout-shift attribution. They appear to have been conflated. The category-only
 * shift is most likely the JS-driven "load more" widget (.bs-btn-load-more /
 * .bs-load-more-progress) or the smart-filter panel — both category-only and both
 * rendered late. That needs a throttled cold run to confirm; the PSI API quota was
 * exhausted when this was written.
 *
 * So: the reservation work below is still correct and REQUIRED — after the re-export
 * the old 1498x465 attributes would no longer describe the file, and an explicit
 * aspect-ratio stops the reservation depending on those attributes surviving future
 * edits. But please do not read "category CLS still 0.25" as WP2 having failed. The
 * payload numbers are WP2's real acceptance test.
 *
 * -----------------------------------------------------------------------------
 * IMAGE UPLOAD — do this first (binaries cannot travel inside a PHP runner)
 * -----------------------------------------------------------------------------
 * Repo source                                                  -> server destination
 *   patches/assets/TECH-013_wp2/image/catalog/One Piece/BS Big logo.png
 *       ->  ~/public_html/image/catalog/One Piece/BS Big logo.png     (overwrite)
 *   patches/assets/TECH-013_wp2/image/catalog/Pokemon/PokemonC.png
 *       ->  ~/public_html/image/catalog/Pokemon/PokemonC.png          (overwrite)
 *
 *   BS Big logo.png : 1498x465, 394.0 KB  ->  270x84,  20.7 KB   (-94.7%)
 *   PokemonC.png    : 1500x585, 196.0 KB  ->  452x176, 48.4 KB   (-75.3%)
 *   Combined        : 590.0 KB -> 69.1 KB  =  520.9 KB saved (acceptance bar: >=500 KB)
 *   Header logo 20.7 KB is inside the <=40 KiB acceptance bar.
 *
 * Both were downscaled with GD imagecopyresampled at full quality, alpha preserved,
 * PNG compression level 9. Sizes come from handoff §5D and were re-verified against
 * live measurements on 2026-08-06: logo painted 135.3x42 desktop / 103.1x32 mobile
 * (=> 271x84 at dpr2, rounded to the §5D target 270x84); PokemonC content box
 * 226x88 desktop (=> 452x176 at dpr2).
 *
 * The runner BACKS UP the two images it finds on the server before doing anything, so
 * uploading first is still safe — you can restore the originals from _patch_backups/.
 *
 * -----------------------------------------------------------------------------
 * WHAT THIS PATCH CHANGES (4 files)
 * -----------------------------------------------------------------------------
 * 1. header.twig  — logo <img>: width/height 1498x465 -> 270x84 so the attributes
 *    describe the new file, and src gains ?v=tech013-wp2-20260806.
 *    THE CACHE-BUSTER IS NOT OPTIONAL: measured live 2026-08-06, both images are
 *    served with `Cache-Control: public, max-age=604800`. Overwriting them in place
 *    without a new URL would leave returning visitors on the 394 KB logo for up to a
 *    week — the whole payload win would miss them. The logo path comes from the DB
 *    (config_logo) and this task allows no DB changes, so the version is appended in
 *    the template instead of renaming the file.
 *
 * 2. boostershop-ds.css:2292 — add `aspect-ratio: 270 / 84` to the winning rule.
 *    ROOT CAUSE / OVERRIDE HISTORY (AGENTS.md UI/CSS discipline):
 *      - This selector is ALREADY an override block; every declaration in it carries
 *        !important. It is at least the third pass over logo sizing in this file
 *        (:692 base 36px, :834 "R-05-FIX: logo size override after Bootstrap/img-fluid"
 *        46px !important, :2292 body.bs 42px !important) plus two media variants
 *        (:749 28px, :839 34px !important, :2375 32px !important).
 *      - The rule edited is :2292 — the one that actually applies. :834 and :839 are
 *        dead and are LEFT ALONE: removing them is a real cleanup but it is not this
 *        task's scope, and deleting live CSS to tidy up is exactly the kind of change
 *        that should not ride along inside a performance patch. Flagged for Stage 2.
 *      - NO new `!important` is introduced. Nothing else in any stylesheet declares
 *        aspect-ratio for this element (verified), so a plain declaration wins.
 *      - 270/84 is chosen so three things state the same ratio: the exported pixels
 *        (270x84), this CSS aspect-ratio, and the header.twig attributes.
 *    DS css version is bumped to ?v=tech013-wp2-20260806 in header.twig — same 7-day
 *    max-age reasoning as WP1.
 *
 * 3. home.twig — PokemonC.png src gains ?v=tech013-wp2-20260806 (same cache reason).
 *    The declared width="168" height="168" attributes are LEFT ALONE on purpose: per
 *    §5D the tile img is `object-fit: contain` inside a CSS-sized box, so the box —
 *    not the attributes — drives layout, which is why mobile CLS is 0 today. Changing
 *    them is unnecessary risk against a metric that is currently perfect.
 *    One Piece-Photoroom.png is NOT touched (463x111 vs a 452x108 requirement — already
 *    correctly sized, per §5D and re-verified).
 *
 * 4. category.twig — the FIRST product card only becomes
 *    loading="eager" fetchpriority="high".
 *    WHY: measured on mobile 390x844, the largest above-the-fold element on
 *    /catalog/Pokemon is the first card image at 340x240 = 81,600 px^2 — roughly 2.3x
 *    the next candidate and 3.5x the cookie-banner text that was July's LCP element.
 *    It is the LCP element, and it was `loading="lazy"`, which defers the very image
 *    LCP is waiting on. Only the first card is promoted: on mobile the grid is
 *    row-cols-1, so exactly one card is above the fold, and eagerly fetching a whole
 *    4-wide desktop row would put three more large images in competition with the LCP
 *    image on throttled 4G — the opposite of the goal. Desktop LCP is already 1.0-1.6 s.
 *    Implemented with `product|replace({...})`, which is the established idiom in this
 *    codebase — header.twig already does `{{ cart|replace({...}) }}` — because each
 *    card is pre-rendered to an HTML string by the product/thumb controller, so
 *    thumb.twig itself has no access to a loop index.
 *
 * -----------------------------------------------------------------------------
 * SCOPE ITEM 4 (product-card WebP) — BLOCKED, NOT DONE. Reason:
 * -----------------------------------------------------------------------------
 * Card thumbnails are emitted by the OpenCart resizer as image/cache/<name>-250x250.<ext>
 * and the extension follows the SOURCE image, whose path is stored in oc_product.image.
 * 4 of the 15 cards on /catalog/Pokemon are already WebP (their sources are .webp);
 * the other 11 are PNG. Converting them therefore requires either DB writes to
 * oc_product.image — which handoff §9.6 forbids in this task — or a <picture> element
 * in thumb.twig pointing at a parallel .webp cache path that the resizer does not
 * generate. Both are out of WP2's scope as written. Recommendation: handle it as its
 * own task with explicit owner approval for the DB image-field update.
 * Related, also not acted on: config_image_product_width/height produce 250x250
 * thumbnails while the card renders 340x240 CSS px (680x480 at dpr2), so cards are
 * upscaled and soft. That is an admin setting, read-only per handoff §6.
 *
 * -----------------------------------------------------------------------------
 * SAFETY / ROLLBACK
 * -----------------------------------------------------------------------------
 * - Existence, writability and anchor counts are all checked BEFORE any write.
 * - The two images are backed up too, so a full restore includes them.
 * - Twig gate on all three templates when PHP >= 8.1 and the vendor tree loads;
 *   loud, explicit skip naming /opt/alt/php81/usr/bin/php otherwise. A control parse
 *   of each ORIGINAL file runs first so an environment quirk cannot cause a false abort.
 * - CSS brace balance checked. Any failure restores every file from the backup.
 * - Idempotent; self-deletes after a successful fresh apply.
 *
 * ROLLBACK: restore the files from _patch_backups/<patch>-<timestamp>/ (this includes
 *           the two original images). No DB change, so a file restore is complete.
 * =============================================================================
 */

if (PHP_SAPI !== 'cli') { header('Content-Type: text/plain; charset=UTF-8'); }
if (version_compare(PHP_VERSION, '7.0.0', '<')) {
    echo 'This patch needs PHP 7.0 or newer. Running PHP ' . PHP_VERSION . PHP_EOL; exit(1);
}

$PATCH = 'TECH-013_wp2-images-and-cls_20260806';
$ROOT  = __DIR__;
$VER   = 'tech013-wp2-20260806';

$out  = static function ($m = '') { echo $m . PHP_EOL; };
$fail = static function ($m) use ($out) {
    $out(''); $out('[ABORT] ' . $m); $out('Nothing was written. The site is unchanged.'); exit(1);
};

$out('=============================================================');
$out(' ' . $PATCH);
$out(' TECH-013 WP2 — images, logo reservation, LCP card');
$out('=============================================================');
$out('PHP ' . PHP_VERSION . ' (' . PHP_BINARY . ')');
$out('Working directory: ' . $ROOT);
$out('');

if (!is_file($ROOT . '/index.php') || !is_dir($ROOT . '/catalog/view/template/common')) {
    $fail('This does not look like the OpenCart web root. Upload to ~/public_html and run it there.');
}

$HEADER = 'catalog/view/template/common/header.twig';
$DSCSS  = 'catalog/view/stylesheet/boostershop-ds.css';
$HOME   = 'catalog/view/template/common/home.twig';
$CAT    = 'catalog/view/template/product/category.twig';
$IMG_LOGO = 'image/catalog/One Piece/BS Big logo.png';
$IMG_TILE = 'image/catalog/Pokemon/PokemonC.png';

/* -------------------------------------------------------------------------
 * 1. Files exist / writable
 * ---------------------------------------------------------------------- */
$out('[1/8] Checking target files ...');
foreach ([$HEADER, $DSCSS, $HOME, $CAT, $IMG_LOGO, $IMG_TILE] as $rel) {
    $abs = $ROOT . '/' . $rel;
    if (!is_file($abs))                           { $fail('Target not found: ' . $rel); }
    if (!is_readable($abs) || !is_writable($abs)) { $fail('Not readable/writable: ' . $rel); }
    $out('      OK  ' . $rel . '  (' . number_format(filesize($abs)) . ' B)');
}

/* -------------------------------------------------------------------------
 * 2. The re-exported images must already be uploaded
 * ---------------------------------------------------------------------- */
$out('');
$out('[2/8] Verifying the re-exported images are in place ...');
$imgExpect = [
    $IMG_LOGO => [270, 84],
    $IMG_TILE => [452, 176],
];
$imgProblems = [];
foreach ($imgExpect as $rel => $wh) {
    $info = @getimagesize($ROOT . '/' . $rel);
    if ($info === false) { $imgProblems[] = $rel . ' — not a readable image'; continue; }
    $out(sprintf('      %-44s %4dx%-4d  expected %4dx%-4d  %s',
        basename($rel), $info[0], $info[1], $wh[0], $wh[1],
        ($info[0] === $wh[0] && $info[1] === $wh[1]) ? 'OK' : 'MISMATCH'));
    if ($info[0] !== $wh[0] || $info[1] !== $wh[1]) {
        $imgProblems[] = $rel . ' is ' . $info[0] . 'x' . $info[1] . ', expected ' . $wh[0] . 'x' . $wh[1];
    }
}
if ($imgProblems) {
    $out('');
    foreach ($imgProblems as $p) { $out('  ' . $p); }
    $fail('Upload the two re-exported images from patches/assets/TECH-013_wp2/ FIRST, '
        . 'then re-run. This patch writes markup that declares the new dimensions, so it '
        . 'must not run against the old files.');
}

$headerOrig = file_get_contents($ROOT . '/' . $HEADER);
$dsOrig     = file_get_contents($ROOT . '/' . $DSCSS);
$homeOrig   = file_get_contents($ROOT . '/' . $HOME);
$catOrig    = file_get_contents($ROOT . '/' . $CAT);

/* -------------------------------------------------------------------------
 * 3. Idempotency
 * ---------------------------------------------------------------------- */
if (strpos($headerOrig, $VER) !== false) {
    $out('');
    $out('already_applied=yes');
    $out('header.twig already carries ?v=' . $VER . '. Nothing to do.');
    $out('This patch file was NOT deleted — remove it manually if you are done.');
    exit(0);
}

/* -------------------------------------------------------------------------
 * 4. Anchor pre-check
 * ---------------------------------------------------------------------- */
$out('');
$out('[3/8] Anchor pre-check ...');

$A_LOGO_FIND = <<<'ANCHOR'
        <img src="{{ logo }}" title="{{ name }}" alt="{{ name }}" class="img-fluid" width="1498" height="465"/>
ANCHOR;
$A_LOGO_REPL = <<<'ANCHOR'
        {# TECH-013 WP2: re-exported to 270x84 (was 1498x465, 394 KB). The attributes and
           the aspect-ratio in boostershop-ds.css must stay in step with the file. The ?v=
           is required because images are served with max-age=604800; the path itself comes
           from config_logo in the DB and this task makes no DB changes. #}
        <img src="{{ logo }}?v=tech013-wp2-20260806" title="{{ name }}" alt="{{ name }}" class="img-fluid" width="270" height="84"/>
ANCHOR;

$A_DSVER_FIND = 'boostershop-ds.css?v=tech013-wp1-20260805';
$A_DSVER_REPL = 'boostershop-ds.css?v=tech013-wp2-20260806';

$A_CSS_FIND = <<<'ANCHOR'
body.bs .bs-header__logo img {
  display: block !important;
  height: 42px !important;
  max-width: 180px !important;
  width: auto !important;
}
ANCHOR;
$A_CSS_REPL = <<<'ANCHOR'
body.bs .bs-header__logo img {
  display: block !important;
  height: 42px !important;
  max-width: 180px !important;
  width: auto !important;
  /* TECH-013 WP2: explicit ratio so the box is reserved from the exported file's own
     dimensions (270x84) instead of relying on the width/height attributes in
     header.twig surviving future edits. This is the rule that actually applies to the
     logo — the 46px block near line 834 and its 34px media variant are outranked by
     this selector (same !important, higher specificity) and are dead. No new
     !important is added here: nothing else declares aspect-ratio for this element. */
  aspect-ratio: 270 / 84;
}
ANCHOR;

$A_HOME_FIND = <<<'ANCHOR'
            <img src="{{ base }}image/catalog/Pokemon/PokemonC.png" alt="Pokémon TCG" loading="eager" width="168" height="168">
ANCHOR;
$A_HOME_REPL = <<<'ANCHOR'
            <img src="{{ base }}image/catalog/Pokemon/PokemonC.png?v=tech013-wp2-20260806" alt="Pokémon TCG" loading="eager" width="168" height="168">
ANCHOR;

$A_CAT_FIND = <<<'ANCHOR'
            <div class="col mb-3">{{ product }}</div>
ANCHOR;
$A_CAT_REPL = <<<'ANCHOR'
            {# TECH-013 WP2: the first card is the LCP element on mobile (measured
               340x240 = 81,600 px^2 above the fold at 390x844, the largest element on
               the page) and thumb.twig marks every card loading="lazy", which defers
               exactly the image LCP waits for. Promote the first card only — the grid is
               row-cols-1 on mobile, so one card is above the fold, and eager-loading a
               full desktop row would compete for bandwidth on throttled 4G. The replace
               filter is used because product/thumb pre-renders each card to an HTML
               string, so thumb.twig has no loop index; header.twig uses the same
               `|replace` idiom for the cart block. #}
            <div class="col mb-3">{{ loop.first ? product|replace({'loading="lazy"': 'loading="eager" fetchpriority="high"'}) : product }}</div>
ANCHOR;

$anchors = [
    [$HEADER, 'A1 logo <img> tag',            $headerOrig, $A_LOGO_FIND,  1],
    [$HEADER, 'A2 DS stylesheet version',     $headerOrig, $A_DSVER_FIND, 1],
    [$DSCSS,  'A3 winning logo rule (:2292)', $dsOrig,     $A_CSS_FIND,   1],
    [$DSCSS,  'A3b no aspect-ratio yet here', $dsOrig,     'aspect-ratio: 270 / 84', 0],
    [$HOME,   'A4 PokemonC tile <img>',       $homeOrig,   $A_HOME_FIND,  1],
    [$CAT,    'A5 product grid loop body',    $catOrig,    $A_CAT_FIND,   1],
];
$problems = [];
foreach ($anchors as $a) {
    $n = substr_count($a[2], $a[3]);
    $out(sprintf('      %-32s expected %d, found %d', $a[1], $a[4], $n));
    if ($n !== $a[4]) { $problems[] = $a[0] . ' :: ' . $a[1] . ' (expected ' . $a[4] . ', found ' . $n . ')'; }
}
if ($problems) {
    $out('');
    foreach ($problems as $p) { $out('  MISMATCH: ' . $p); }
    $fail('Anchor pre-check failed. Live files differ from what this patch was built against. '
        . 'Note A2 expects WP1 to be deployed (?v=tech013-wp1-20260805). Re-export and rebuild.');
}
$out('      All anchors matched exactly.');

/* -------------------------------------------------------------------------
 * 5. Build in memory
 * ---------------------------------------------------------------------- */
$out('');
$out('[4/8] Building modified content in memory ...');

$headerNew = str_replace([$A_LOGO_FIND, $A_DSVER_FIND], [$A_LOGO_REPL, $A_DSVER_REPL], $headerOrig);
$dsNew     = str_replace($A_CSS_FIND,  $A_CSS_REPL,  $dsOrig);
$homeNew   = str_replace($A_HOME_FIND, $A_HOME_REPL, $homeOrig);
$catNew    = str_replace($A_CAT_FIND,  $A_CAT_REPL,  $catOrig);

$expect = [
    'header: logo attrs now 270x84'   => (substr_count($headerNew, 'width="270" height="84"') === 1),
    'header: old 1498x465 gone'       => (strpos($headerNew, 'width="1498" height="465"') === false),
    'header: logo src versioned'      => (substr_count($headerNew, '{{ logo }}?v=' . $VER) === 1),
    'header: DS css versioned'        => (substr_count($headerNew, $A_DSVER_REPL) === 1),
    'header: jQuery still sync'       => (strpos($headerNew, '<script src="{{ jquery }}" type="text/javascript"></script>') !== false),
    'header: WP1 defer flag intact'   => (substr_count($headerNew, '{{ bs_wp1_defer }}') === 3),
    'ds: aspect-ratio added once'     => (substr_count($dsNew, 'aspect-ratio: 270 / 84') === 1),
    // Count real declarations ('!important;'), not the bare word — the explanatory
    // comment added above legitimately mentions !important while declaring none.
    'ds: no new !important decl'      => (substr_count($dsNew, '!important;') === substr_count($dsOrig, '!important;')),
    'ds: brace balance preserved'     => (substr_count($dsNew, '{') === substr_count($dsOrig, '{')
                                          && substr_count($dsNew, '}') === substr_count($dsOrig, '}')),
    'home: PokemonC versioned'        => (substr_count($homeNew, 'PokemonC.png?v=' . $VER) === 1),
    'home: tile attrs untouched'      => (substr_count($homeNew, 'width="168" height="168"') === substr_count($homeOrig, 'width="168" height="168"')),
    'home: One Piece tile untouched'  => (substr_count($homeNew, 'One%20Piece-Photoroom.png"') === substr_count($homeOrig, 'One%20Piece-Photoroom.png"')),
    'cat: loop.first promotion added' => (substr_count($catNew, 'loop.first ? product|replace') === 1),
    'cat: single grid loop body'      => (substr_count($catNew, '{{ loop.first ? product|replace') === 1),
];
$bad = [];
foreach ($expect as $l => $ok) { $out(sprintf('      %-34s %s', $l, $ok ? 'OK' : 'FAILED')); if (!$ok) { $bad[] = $l; } }
if ($bad) { $fail('Post-replacement structural check failed: ' . implode('; ', $bad)); }

/* -------------------------------------------------------------------------
 * 6. Twig syntax gate (PHP 8.1 required for the vendor tree)
 * ---------------------------------------------------------------------- */
$out('');
$out('[5/8] Twig syntax gate ...');

$PHP81 = '/opt/alt/php81/usr/bin/php';
$twigVerdict = 'skipped';

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    $out('      +---------------------------------------------------------------+');
    $out('      | TWIG GATE SKIPPED — running PHP ' . str_pad(PHP_VERSION, 29) . '|');
    $out('      | The site vendor tree requires PHP 8.1, so loading it here      |');
    $out('      | would raise a raw Composer error. Skipped deliberately.        |');
    $out('      |                                                               |');
    $out('      | For full template validation, run instead:                     |');
    $out('      |   ' . str_pad($PHP81 . ' \\', 60) . '|');
    $out('      |     ' . str_pad(basename(__FILE__), 58) . '|');
    $out('      |                                                               |');
    $out('      | Structural checks above still applied. All three template      |');
    $out('      | edits were verified against Twig 3.18.0 before delivery.       |');
    $out('      +---------------------------------------------------------------+');
    $twigVerdict = 'skipped (PHP < 8.1)';
} else {
    $autoload = null;
    if (is_file($ROOT . '/config.php')) {
        include_once $ROOT . '/config.php';
        if (defined('DIR_STORAGE') && is_file(DIR_STORAGE . 'vendor/autoload.php')) {
            $autoload = DIR_STORAGE . 'vendor/autoload.php';
        }
    }
    if ($autoload === null) {
        $out('      Skipped — could not locate DIR_STORAGE/vendor/autoload.php');
        $twigVerdict = 'skipped (no autoloader)';
    } else {
        require_once $autoload;
        if (!class_exists('\\Twig\\Environment')) {
            $out('      Skipped — Twig classes not found');
            $twigVerdict = 'skipped (no Twig)';
        } else {
            $env = new \Twig\Environment(new \Twig\Loader\ArrayLoader([]));
            $pairs = [
                ['header.twig',   $headerOrig, $headerNew],
                ['home.twig',     $homeOrig,   $homeNew],
                ['category.twig', $catOrig,    $catNew],
            ];
            $allOk = true;
            foreach ($pairs as $p) {
                $controlOk = true;
                try { $env->parse($env->tokenize(new \Twig\Source($p[1], $p[0] . '.orig'))); }
                catch (\Throwable $e) { $controlOk = false; }
                if (!$controlOk) {
                    $out('      ' . $p[0] . ': control parse of the ORIGINAL failed — gate unreliable, skipping');
                    continue;
                }
                try {
                    $env->parse($env->tokenize(new \Twig\Source($p[2], $p[0])));
                    $out('      ' . $p[0] . ': PASSED');
                } catch (\Throwable $e) {
                    $out('      ' . $p[0] . ': FAILED — ' . $e->getMessage());
                    $allOk = false;
                }
            }
            if (!$allOk) { $fail('A modified template failed Twig parsing — nothing was written.'); }
            $twigVerdict = 'passed';
        }
    }
}

/* -------------------------------------------------------------------------
 * 7. Backup, then write
 * ---------------------------------------------------------------------- */
$out('');
$out('[6/8] Backing up originals (templates, CSS and both images) ...');
$stamp     = date('Ymd-His');
$backupDir = $ROOT . '/_patch_backups/' . $PATCH . '-' . $stamp;
$backupSet = [$HEADER, $DSCSS, $HOME, $CAT, $IMG_LOGO, $IMG_TILE];
foreach ($backupSet as $rel) {
    $dest = $backupDir . '/' . $rel;
    if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0755, true) && !is_dir(dirname($dest))) {
        $fail('Could not create backup directory: ' . dirname($dest));
    }
    if (!copy($ROOT . '/' . $rel, $dest)) { $fail('Could not back up ' . $rel); }
    $out('      ' . $rel);
}
$out('      -> _patch_backups/' . $PATCH . '-' . $stamp . '/');

$restore = static function () use ($backupDir, $ROOT, $backupSet, $out) {
    foreach ($backupSet as $rel) {
        if (is_file($backupDir . '/' . $rel)) { copy($backupDir . '/' . $rel, $ROOT . '/' . $rel); }
    }
    $out('      Files restored from backup.');
};

$out('');
$out('[7/8] Writing changes ...');
$writes = [[$HEADER, $headerNew], [$DSCSS, $dsNew], [$HOME, $homeNew], [$CAT, $catNew]];
$ok = true;
foreach ($writes as $w) {
    $b = file_put_contents($ROOT . '/' . $w[0], $w[1]);
    if ($b === false || $b !== strlen($w[1])) { $ok = false; $out('      WRITE FAILED: ' . $w[0]); break; }
    $out('      wrote ' . $w[0] . '  (' . number_format($b) . ' B)');
}
if (!$ok) { $out(''); $out('[ROLLBACK] restoring.'); $restore(); $fail('Write failed. Restored from ' . $backupDir); }

$out('');
$out('[8/8] Verifying written files ...');
$verifyOk = file_get_contents($ROOT . '/' . $HEADER) === $headerNew
         && file_get_contents($ROOT . '/' . $DSCSS)  === $dsNew
         && file_get_contents($ROOT . '/' . $HOME)   === $homeNew
         && file_get_contents($ROOT . '/' . $CAT)    === $catNew;
if (!$verifyOk) { $out('      Mismatch on disk.'); $out(''); $out('[ROLLBACK] restoring.'); $restore(); $fail('Post-write verification failed. Restored from ' . $backupDir); }
$out('      All four files verified byte-for-byte on disk.');

/* -------------------------------------------------------------------------
 * Done
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
$out('  1. header.twig  — logo attrs 1498x465 -> 270x84, src ?v=' . $VER);
$out('  2. boostershop-ds.css:2292 — aspect-ratio: 270 / 84 (+ version bump in head)');
$out('  3. home.twig    — PokemonC.png ?v=' . $VER);
$out('  4. category.twig — first product card eager + fetchpriority="high"');
$out('');
$out('OWNER QA:');
$out('  1. Header logo sharp at 390 / 768 / 1411 px. It is now a 20.7 KB file — check');
$out('     for softness on a retina screen especially.');
$out('  2. Home Pokemon tile sharp at the same three widths.');
$out('  3. View source: logo and PokemonC srcs both end with ?v=' . $VER);
$out('  4. PSI mobile x3 URLs. EXPECT: image-delivery savings to fall well below the');
$out('     200 KiB bar, and mobile LCP on /catalog/Pokemon to improve most (the first');
$out('     card is no longer lazy). Re-identify the LCP element.');
$out('  5. MOBILE CLS MUST STAY 0 on all three pages. That is the revert trigger.');
$out('  6. Desktop category CLS: do NOT expect 0.25 -> 0. See the long note in this');
$out('     file\'s header — the logo already reserved its space, so the category-only');
$out('     shift is almost certainly the load-more / smart-filter widget. Please');
$out('     capture the PSI "Avoid large layout shifts" element list on the next run so');
$out('     we can aim Stage 2 at the real element. Three runs minimum before judging.');
$out('  7. bs-checkout-smoke — header.twig is global.');
$out('');
$out('Protected files are untouched; confirm anyway against');
$out('diagnostics/TECH-013_header-baselines_20260805.md:');
$out('  curl -sI https://boostershop.website/sitemap-full.xml');
$out('  curl -sI https://boostershop.website/robots.txt');
$out('');

if (@unlink(__FILE__)) { $out('Patch file self-deleted.'); }
else { $out('NOTE: could not self-delete ' . basename(__FILE__) . ' — remove it manually.'); }
$out('');
exit(0);
