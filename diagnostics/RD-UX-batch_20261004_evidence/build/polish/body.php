<?php
declare(strict_types=1);

/**
 * UX-003 / UX-005 / UX-009 — layout polish (runner 8)
 * =============================================================================
 * Chain     : runner 8. Requires runners 1, 2, 3, 3b, 4, 5, 6 and 7 applied first (the deployed post-runner-7 state).
 * Handoff   : handoffs/handoff_RD-14-15_UX-003-005-009_INDEX_claude-code_20261004.md, section «Deploy log runners
 *             3b–7 and runner 8»; review diagnostics/RD-UX-batch_runners-3b-7_review_20261004.md (R2, R4, R6).
 * Author    : Claude Code · 2026-10-04 (built 2026-10-05)
 * Risk      : every page (header), category pages (header card), live search (error state). CSS + Twig markup
 *             only. No PHP, no DB, no JS, no URL/canonical/meta change.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-003-005-009_polish_20261004.php
 *
 * WHAT CHANGES
 *   1. Header fits at 769–959px (owner decision, review R4). Measured on the post-runner-7 state: «Увійти»/«Акаунт»
 *      + «Telegram» + the cart label overflow the viewport from 769px up to 895px with «Мій кошик - 0.00₴», and up
 *      to 945px with a six-digit total while logged in. In 769–959px only:
 *        - boostershop-ds.css, section C (.bs-ghost, the source rule of both links): 44×44 icon-only; the visible
 *          text is hidden, the existing aria-label keeps the name;
 *        - common/cart.twig: the label «Мій кошик - <total>» is split at its first « - » into a lead and the total,
 *          so the lead can be hidden there: icon + total. Any other text_items shape renders unchanged. The
 *          button's aria-label (full text_items) is unchanged. Every cart refresh re-renders cart.twig
 *          (common/cart.info), so the split survives AJAX updates.
 *      ≤768px and ≥960px: unchanged.
 *   2. Category header card below 992px: two rows, no swipe (owner decision). Row 1: subcategory chips wrap, counts
 *      kept. Row 2: «Фільтр» and sort side by side, equal width, text labels. The ≤575 icon-only squares (rules,
 *      the sort-icon overlay and its markup) are removed; the ≤575 block now only tightens the sort's inline
 *      padding so «За замовчуванням» fits at 390px (ellipsis below). ≥992px unchanged.
 *   3. Card-to-grid gap: one rule, 16px at every width. Before: 16px from 992 (UI-CAT-GAP, category.twig inline
 *      style) and 14px below it (runner 7, boostershop-ds.css). The runner-7 rule is removed and UI-CAT-GAP loses
 *      its media query. See the report for what the rest of the perceived gap is.
 *   4. Review R2: the empty .bs-ff-chips placeholder (8×8 grey dot at ≥992 on categories without sub-/sibling
 *      categories) is no longer rendered; the tools keep margin-left:auto and stay on the right.
 *   5. Review R6: the live-search error button wraps (white-space normal, height auto, max-width 100%) at its
 *      source rule.
 *   common/header.twig: ds.css ?v= token only.
 *
 * UI/CSS DISCIPLINE
 *   Root causes: (1) .bs-ghost white-space:nowrap labels + the cart label next to the search's 220px minimum
 *   (.bs-msearch / body.bs .bs-search min-width); (2) UX-003-FILTER .bs-ff-row (nowrap) + .bs-ff-chips
 *   overflow-x:auto + the ≤575 icon block; (3) runner-7 margin-bottom 14px vs UI-CAT-GAP 16px; (4) the template's
 *   empty placeholder getting the ≥992 segment padding + background; (5) .bs-btn white-space:nowrap / height:44px
 *   under #ps-live-search .bs-ls-state__btn. All edited at those rules. No !important, no position:absolute/fixed,
 *   no setTimeout added. Breakpoint 959/960 is the measured fit bound (stated in the CSS).
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on the four targets (runner 7 output) → marker → anchored, count-checked edits → hazard scan →
 *   Twig parse gate on cart.twig, category.twig, header.twig → CSS balance gate → backup of the four targets →
 *   write → restore-all on failure. Idempotent: marker «UX-003-POLISH» in boostershop-ds.css, cart.twig and
 *   category.twig. Self-deletes.
 *   ROLLBACK: cd ~/public_html && copy the four files back from _patch_backups/UX-003-005-009_polish_20261004-<ts>/,
 *   refresh the theme cache, Ctrl+F5.
 *   TRIGGER: header wraps or overflows, cart button missing or empty, category header broken, Twig error.
 * =============================================================================
 */

const PATCH_ID = 'UX-003-005-009_polish_20261004';
const MARKER   = 'UX-003-POLISH';
const TOKEN    = 'ux003polish-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';
const CART     = 'catalog/view/template/common/cart.twig';
const CATEGORY = 'catalog/view/template/product/category.twig';

const EXPECTED_SHA = array(
    DSCSS    => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER   => '@@SHA:catalog/view/template/common/header.twig@@',
    CART     => '@@SHA:catalog/view/template/common/cart.twig@@',
    CATEGORY => '@@SHA:catalog/view/template/product/category.twig@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(DSCSS, HEADER, CART, CATEGORY) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS, CART, CATEGORY), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];

// 1. Header 769–959: icon-only ghost links, cart icon + total.
$fold = "/* Fold ghost-links into burger on mobile */\n@media (max-width: 768px) {\n  .bs-ghost { display: none; }\n}\n";
$ds = replace_one($ds, $fold, $fold . <<<'CSS'

/* UX-003-POLISH (2026-10-04, owner decision, review R4): from 769px the full labels no longer fit next to the
   search's 220px minimum. Measured: «Увійти» + «Telegram» + «Мій кошик - 0.00₴» need 896px; a six-digit total
   while logged in needs 945px. Up to 959px both links are 44×44 icons (aria-label keeps the name) and the cart
   shows icon + total — cart.twig wraps the «Мій кошик - » part in .bs-btn-label__lead. */
@media (min-width: 769px) and (max-width: 959.98px) {
  .bs-ghost { justify-content: center; width: 44px; height: 44px; padding: 0; }
  .bs-ghost svg { width: 20px; height: 20px; }
  .bs-ghost > span { display: none; }
  #cart .bs-btn-label__lead { display: none; }
}

CSS, 'ds_ghost_fold');

// 2–4. Category header card.
$ds = replace_one($ds,
    "   Side padding follows .bs-cat-header__hero: 14px ≤640, 16px to 991, 22px from 992. */\n",
    "   Side padding follows .bs-cat-header__hero: 14px ≤640, 16px to 991, 22px from 992.\n"
    . "   UX-003-POLISH (2026-10-04, owner decision): below 992 the row wraps into two — the chips wrap on row 1 (no\n"
    . "   horizontal scroll), «Фільтр» and the sort share row 2 at equal width with text labels. ≥992 is one row. */\n",
    'ds_ff_intro');
$ds = replace_one($ds,
    ".bs-ff-row {\n  display: flex;\n  align-items: center;\n  gap: 8px;\n  min-width: 0;\n  padding: 0 14px 14px;\n}\n"
    . ".bs-ff-chips {\n  display: flex;\n  flex: 1 1 auto;\n  gap: 6px;\n  min-width: 0;\n  padding: 2px 0;\n  overflow-x: auto;\n  scrollbar-width: none;\n}\n"
    . ".bs-ff-chips::-webkit-scrollbar {\n  display: none;\n}\n",
    ".bs-ff-row {\n  display: flex;\n  flex-wrap: wrap;\n  align-items: center;\n  gap: 8px;\n  min-width: 0;\n  padding: 0 14px 14px;\n}\n"
    . ".bs-ff-chips {\n  display: flex;\n  flex: 1 1 100%;\n  flex-wrap: wrap;\n  gap: 6px;\n  min-width: 0;\n}\n",
    'ds_ff_row_chips');
$ds = replace_one($ds,
    "  font-weight: 700;\n  text-decoration: none;\n  white-space: nowrap;\n}\n#product-category .bs-ff-chip:hover {\n",
    "  font-weight: 700;\n  text-decoration: none;\n  max-width: 100%;\n}\n#product-category .bs-ff-chip:hover {\n",
    'ds_ff_chip_wrap');
$ds = replace_one($ds,
    ".bs-ff-tools {\n  display: flex;\n  flex: 0 0 auto;\n  gap: 8px;\n  margin-left: auto;\n}\n",
    ".bs-ff-tools {\n  display: flex;\n  flex: 1 1 100%;\n  gap: 8px;\n}\n",
    'ds_ff_tools');
$ds = replace_one($ds,
    "  gap: 8px;\n  height: 44px;\n  min-width: 44px;\n  padding: 0 14px;\n  border: 1px solid var(--bs-line);\n",
    "  flex: 1 1 50%; /* 50%, not 0: border-box floors a 0 basis at padding + border, 30px wider than the sort */\n  gap: 8px;\n  height: 44px;\n  min-width: 0;\n  padding: 0 14px;\n  border: 1px solid var(--bs-line);\n",
    'ds_ff_btn');
$ds = replace_one($ds,
    ".bs-ff-sort {\n  position: relative;\n  flex: 0 0 auto;\n  width: 200px;\n}\n",
    ".bs-ff-sort {\n  flex: 1 1 50%;\n  min-width: 0;\n}\n",
    'ds_ff_sort');
$ds = replace_one($ds, ".bs-ff-sort__ic {\n  display: none;\n}\n", '', 'ds_ff_sort_ic');
$ds = replace_one($ds, <<<'CSS'
/* ≤575: «Фільтр» and sort become 44×44 icon buttons. The sort icon sits under a transparent native select, so the
   select keeps its own picker and its onchange; position:absolute is that overlay. */
@media (max-width: 575.98px) {
  .bs-ff-btn {
    width: 44px;
    padding: 0;
  }
  .bs-ff-btn__label {
    position: absolute;
    width: 1px;
    height: 1px;
    margin: -1px;
    overflow: hidden;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    white-space: nowrap;
  }
  .bs-ff-btn__chev {
    display: none;
  }
  .bs-ff-btn .bs-ff-count {
    position: absolute;
    top: -6px;
    right: -6px;
  }
  .bs-ff-sort {
    width: 44px;
    height: 44px;
  }
  .bs-ff-sort__ic {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--bs-line);
    border-radius: 8px;
    background: #fff;
    color: var(--bs-ink);
    pointer-events: none;
  }
  .bs-ff-sort .bs-select {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    cursor: pointer;
  }
  .bs-ff-sort:focus-within .bs-ff-sort__ic {
    outline: 2px solid var(--bs-blue);
    outline-offset: 2px;
  }
}

CSS, <<<'CSS'
/* ≤575: the half-row sort keeps «За замовчуванням» whole down to 390px (tighter inline padding, 13.5px); narrower,
   it ends in an ellipsis instead of being cut mid-letter. */
@media (max-width: 575.98px) {
  .bs-ff-sort .bs-select {
    padding-inline: 10px 26px;
    font-size: 13.5px;
    background-position: right 8px center;
  }
}

CSS, 'ds_ff_icon_block');
$ds = replace_one($ds,
    ".bs-ff-sort .bs-select {\n  height: 44px;\n  padding-block: 0;\n  color: var(--bs-ink-2);\n  font-size: 14px;\n  font-weight: 600;\n}\n",
    ".bs-ff-sort .bs-select {\n  height: 44px;\n  padding-block: 0;\n  color: var(--bs-ink-2);\n  font-size: 14px;\n  font-weight: 600;\n  text-overflow: ellipsis;\n}\n",
    'ds_ff_select');
$ds = replace_one($ds,
    "/* Below 992 the removed mobile action row used to hold the grid 14px off the header card (≥992 has UI-CAT-GAP). */\n"
    . "@media (max-width: 991.98px) {\n  #product-category .bs-cat-header {\n    margin-bottom: 14px;\n  }\n}\n",
    '', 'ds_ff_gap14');
$ds = replace_one($ds,
    "  .bs-ff-row {\n    padding: 0 22px 18px;\n  }\n",
    "  .bs-ff-row {\n    flex-wrap: nowrap;\n    padding: 0 22px 18px;\n  }\n",
    'ds_ff_row_992');
$ds = replace_one($ds,
    "  .bs-ff-chips {\n    flex: 0 1 auto;\n    flex-wrap: wrap;\n    padding: 4px;\n    overflow: visible;\n    border-radius: 10px;\n    background: var(--bs-line-2);\n  }\n"
    . "  #product-category .bs-ff-chip {\n    min-height: 40px;\n    border: 0;\n    background: transparent;\n  }\n",
    "  .bs-ff-chips {\n    flex: 0 1 auto;\n    padding: 4px;\n    border-radius: 10px;\n    background: var(--bs-line-2);\n  }\n"
    . "  #product-category .bs-ff-chip {\n    min-height: 40px;\n    border: 0;\n    background: transparent;\n    white-space: nowrap;\n  }\n",
    'ds_ff_chips_992');
$ds = replace_one($ds,
    "  .bs-ff-sort {\n    width: 220px;\n  }\n",
    "  .bs-ff-tools {\n    flex: 0 0 auto;\n    margin-left: auto;\n  }\n  .bs-ff-btn {\n    flex: 0 0 auto;\n  }\n"
    . "  .bs-ff-sort {\n    flex: 0 0 auto;\n    width: 220px;\n  }\n",
    'ds_ff_sort_992');
count_exact($ds, 'bs-ff-sort__ic', 0, 'ds_sort_ic_gone');
count_exact($ds, 'margin-bottom: 14px;', substr_count($files[DSCSS]['text'], 'margin-bottom: 14px;') - 1, 'ds_gap14_gone');

// 5. Review R6: live-search error button wraps on 320px phones.
$ds = replace_one($ds,
    "#ps-live-search .bs-ls-state__btn {\n  gap: 6px;\n  min-height: 44px;\n  margin-top: 4px;\n}\n",
    "#ps-live-search .bs-ls-state__btn {\n  gap: 6px;\n  height: auto;\n  min-height: 44px;\n  max-width: 100%;\n  margin-top: 4px;\n"
    . "  padding-block: 8px;\n  white-space: normal; /* UX-003-POLISH (review R6): .bs-btn is nowrap; the label overflowed at 320px */\n}\n",
    'ds_ls_btn');
css_balance_gate($ds, DSCSS);
twig_hazard_gate(implode("\n", array_diff(explode("\n", $ds), explode("\n", $files[DSCSS]['text']))), DSCSS);

/* ---- common/cart.twig: label split (1) ------------------------------------ */
$oldCart = $files[CART]['text'];
$cart = replace_one($oldCart,
    "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n",
    "{# RD-UX-QA-3B (2026-10-04): «До каталогу» closes the drawer, then opens the burger catalogue (owner decision). #}\n"
    . "{# UX-003-POLISH (2026-10-04): the trigger label «Мій кошик - <total>» is split at its first « - » so ds.css can show\n"
    . "   icon + total at 769–959px; any other text_items shape renders unchanged. aria-label keeps the full text. #}\n"
    . "{% set bs_cart_label = text_items|split(' - ', 2) %}\n",
    'cart_comment');
$cart = replace_one($cart,
    '<span class="bs-btn-label">{{ text_items }}</span></button>',
    '<span class="bs-btn-label">{% if bs_cart_label|length == 2 %}<span class="bs-btn-label__lead">{{ bs_cart_label[0] }} - </span>{{ bs_cart_label[1] }}{% else %}{{ text_items }}{% endif %}</span></button>',
    'cart_label');
count_exact($cart, 'aria-label="{{ text_items }}"', 1, 'cart_aria_label');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $cart), explode("\n", $oldCart))), CART);

/* ---- product/category.twig (2–4) ------------------------------------------ */
$oldCat = $files[CATEGORY]['text'];
$cat = replace_one($oldCat,
    "        <div class=\"bs-ff-row\">\n",
    "        {# UX-003-POLISH (2026-10-04): below 992 the row wraps — chips on row 1, «Фільтр» + sort on row 2 (ds.css). No\n"
    . "           empty chips placeholder any more (review R2); the tools keep margin-left:auto at ≥992. #}\n"
    . "        <div class=\"bs-ff-row\">\n",
    'cat_marker');
$cat = replace_one($cat,
    "            </nav>\n          {% else %}\n            <div class=\"bs-ff-chips\"></div>\n          {% endif %}\n",
    "            </nav>\n          {% endif %}\n",
    'cat_placeholder');
$cat = replace_one($cat,
    "              <span class=\"bs-ff-sort__ic\" aria-hidden=\"true\"><svg width=\"18\" height=\"18\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\" aria-hidden=\"true\" focusable=\"false\"><path d=\"M7 4v16m0 0-3-3m3 3 3-3M17 20V4m0 0-3 3m3-3 3 3\"/></svg></span>\n",
    '', 'cat_sort_icon');
$cat = replace_one($cat,
    "  /* UI-CAT-GAP · desktop toolbar-to-grid spacing */\n  @media (min-width: 992px) {\n    #product-category .bs-cat-header { margin-bottom: 16px; }\n  }\n",
    "  /* UI-CAT-GAP · header-card-to-grid spacing, every width (UX-003-POLISH: was ≥992 only; runner 7 gave 14px below) */\n"
    . "  #product-category .bs-cat-header { margin-bottom: 16px; }\n",
    'cat_gap');
foreach (array('<nav class="bs-ff-chips" aria-label="Підкатегорії">', 'id="bs-ff-toggle"', 'id="bs-ff-panel"', 'onchange="location = this.value;"',
    '{% if active_filters|length %}', '<div id="product-list" class="row row-cols-1 row-cols-sm-2 row-cols-md-2 row-cols-lg-4">', '{% if products %}') as $probe) {
    count_exact($cat, $probe, 1, 'cat_probe');
}
count_exact($cat, 'bs-ff-sort__ic', 0, 'cat_sort_ic_gone');
count_exact($cat, '<div class="bs-ff-chips"></div>', 0, 'cat_placeholder_gone');
$tailStart = "<script type=\"application/ld+json\">\n";
count_exact($oldCat, $tailStart, 1, 'cat_tail_start');
if (substr($cat, strpos($cat, $tailStart)) !== substr($oldCat, strpos($oldCat, $tailStart))) fail('category_tail_not_identical');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $cat), explode("\n", $oldCat))), CATEGORY);

/* ---- header.twig: cache token --------------------------------------------- */
$h = bust_token($files[HEADER]['text'], 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');

twig_gate($root, array(
    CART     => array($oldCart, $cart),
    CATEGORY => array($oldCat, $cat),
    HEADER   => array($files[HEADER]['text'], $h),
));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS    => encode_text($files[DSCSS], $ds),
    HEADER   => encode_text($files[HEADER], $h),
    CART     => encode_text($files[CART], $cart),
    CATEGORY => encode_text($files[CATEGORY], $cat),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-003-005-009_polish_report_20261004.md');
