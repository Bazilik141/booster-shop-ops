<?php
declare(strict_types=1);

/**
 * UX-009 — live search suggestions
 * =============================================================================
 * Chain     : runner 4. Requires runners 1–3 and 3b (RD-UX-qa-followups_20261004) applied first.
 * Handoff   : handoffs/handoff_UX-009_live-search_claude-code_20261004.md (+ INDEX, Claude review)
 * Design    : «UX-003 UX-005 UX-009 - макети.html», screen «Живий пошук» (ux-b-search.jsx LiveList /
 *             MobileOverlay, ux-b.css .ub-ls-*), states focus / loading / results / none / error.
 * Author    : Claude Code · 2026-10-04
 * Risk      : low — every page's header search. No module file, controller, URL or form markup touched.
 * DB changes: NONE.
 * RUN FROM  : ~/public_html    ->    php UX-009_live-search_20261004.php
 *
 * WHAT CHANGES
 *   boostershop-ds.css
 *     - new section «UX-009-LS» after the mobile dropdown block: compact rows (56px thumbnail | name clamped to 2
 *       lines over the price; discounted: new price --bs-danger + struck-through old 13px), no description
 *       (owner-confirmed fix for the over-tall mobile list), group headings 11.5px uppercase, category rows 44px
 *       with a chevron, «Усі результати» as a 44px bordered row with a right arrow (the module's Font Awesome
 *       caret hidden), CSS spinner on .ps-live-search-item-loading (its Font Awesome <i> hidden), no-results
 *       and error state rows. Desktop: dropdown 6px under the field, radius --bs-r-lg, shadow
 *       0 12px 32px rgba(17,24,39,0.16), scrolls inside itself below the sticky header.
 *       All selectors are scoped to #ps-live-search: the module's stylesheet loads after ds.css.
 *     - «A — MOBILE: ps-live-search dropdown position override» (source rule): box-shadow → none, border 0,
 *       padding 0 0 12px — full-width list under the field without frame or shadow.
 *   common/header.twig — the pslivesearch init only (the module file is not changed):
 *     - $.ajax gets timeout 8000 and an error handler. jQuery 'abort' is ignored, and a response or error is
 *       rendered only while the dropdown is open and the input still holds the query it was made for, so a late
 *       reply never overwrites newer results.
 *     - error / timeout / unusable JSON (the module throws on it) → one row, role="alert": «Не вдалося
 *       завантажити підказки» + secondary button «Шукати на сторінці результатів» that submits the existing
 *       search form (action index.php + hidden route/language + search). mousedown is cancelled on it so the
 *       input's focusout does not close the list before the click lands (Safari does not focus buttons on click).
 *     - no item at all → the whole list becomes one row: «Нічого не знайдено за «…»» (query inserted with
 *       .text(), never as HTML) + «Перевірте написання або спробуйте коротший запит.»; the three per-section
 *       «Нічого не знайдено» and «Усі результати» are gone with it.
 *     - after a render: product rows get bs-ls-product, other rows bs-ls-link + chevron; «Усі результати» gets
 *       the arrow. The loading row gets role="status" and a visually hidden «Завантаження…».
 *   ds.css ?v= token in header.twig (convention 8).
 *   Hooks kept (asserted): #ps-live-search-input, #ps-live-search, .ps-live-search-container,
 *   data-live-search-target, form action + hidden route/language, #bs-msearch, [data-bs-search-clear].
 *
 * UI/CSS DISCIPLINE
 *   Root cause of the old look: the module's vendor CSS (Bootstrap variables, column layout with the description
 *   and a right-hand price column). It is not editable here (module files are out of scope), so the DS section
 *   restyles it by specificity. No !important. Mobile edit made at the existing source rule. position values
 *   are the existing ones (absolute from the module, fixed in the mobile block); only top on desktop moves 6px.
 *   Magic numbers come from the approved design (56 thumb, 68 row, 44 rows/buttons, 8000ms timeout per handoff).
 *   No setTimeout added.
 *
 * SAFETY / ROLLBACK
 *   SHA-256 guard on both targets (3b output) → marker → anchors → hooks asserted → hazard scan → Twig parse gate
 *   on header.twig → CSS balance gate → backup → write → restore-all on failure.
 *   Idempotent: marker «UX-009-LS» in boostershop-ds.css and header.twig. Self-deletes.
 *   ROLLBACK: copy both files back from _patch_backups/UX-009_live-search_20261004-<ts>/, refresh the theme
 *   cache, Ctrl+F5 — only while runners 5–7 are not applied.
 *   TRIGGER: suggestions do not appear, a click on a suggestion does nothing, JS error from the header init.
 * =============================================================================
 */

const PATCH_ID = 'UX-009_live-search_20261004';
const MARKER   = 'UX-009-LS';
const TOKEN    = 'ux009ls-20261004';
const DSCSS    = 'catalog/view/stylesheet/boostershop-ds.css';
const HEADER   = 'catalog/view/template/common/header.twig';

const EXPECTED_SHA = array(
    DSCSS  => '@@SHA:catalog/view/stylesheet/boostershop-ds.css@@',
    HEADER => '@@SHA:catalog/view/template/common/header.twig@@',
);

@@LIB@@

$root = start_runner();
$files = array();
foreach (array(DSCSS, HEADER) as $rel) {
    $files[$rel] = load_text($root, $rel);
    out('file_preflight=ok:' . $rel . ' eol=' . ($files[$rel]['eol'] === "\r\n" ? 'crlf' : 'lf'));
}
if (marker_state($files, array(DSCSS, HEADER), MARKER)) finish_already_applied();
sha_guard($files, EXPECTED_SHA);

/* ---- header.twig: init ---------------------------------------------------- */
$h = $files[HEADER]['text'];
$hooks = array('id="ps-live-search-input"', 'id="ps-live-search"', 'ps-live-search-container', 'data-live-search-target="ps-live-search"',
    'action="index.php" method="get" role="search"', '<input type="hidden" name="route" value="product/search">',
    '<input type="hidden" name="language" value="{{ lang }}">', 'name="search"', 'id="bs-msearch"', 'data-bs-search-clear',
    "text_no_results: 'Нічого не знайдено'", "text_all_product_results: 'Усі результати'");
$hookCounts = array();
foreach ($hooks as $hook) $hookCounts[$hook] = substr_count($h, $hook);
foreach ($hookCounts as $hook => $n) if ($n < 1) fail('hook_missing=' . $hook);

$h = replace_one($h,
    "    \$input.data('bs-ps-live-search-ready', true);\n"
    . "    \$input.pslivesearch({\n"
    . "      source: function (request, response) {\n"
    . "        \$.ajax({\n"
    . "          url: 'index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=' + encodeURIComponent(request),\n"
    . "          dataType: 'json',\n"
    . "          success: function (json) { response(json || {}); }\n"
    . "        });\n"
    . "      },\n",
    <<<'JS'
    $input.data('bs-ps-live-search-ready', true);

    // UX-009-LS (2026-10-04): loading / no-results / error states and row classes on top of the module's render.
    // ps_live_search.js is not changed. A reply is used only while the list is open and the input still holds the
    // query it was made for, so a late reply never overwrites newer results.
    var $lsList = $('#ps-live-search');
    var lsArrow = '<svg class="bs-ls-arrow" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true" focusable="false"><path d="M3 8h10M9 4l4 4-4 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var lsChevron = '<svg class="bs-ls-chev" width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true" focusable="false"><path d="M5 2.5 9.5 7 5 11.5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    var lsIcons = {
      none: '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.7"/><path d="M14 14l4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>',
      error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4.5M12 17.5v0.01"/></svg>'
    };

    function lsIsCurrent(request) {
      return $lsList.hasClass('show') && $input.val() === request;
    }

    function lsState(kind, request) {
      var $row = $('<li class="bs-ls-state"></li>').addClass('bs-ls-state--' + kind).attr('role', kind === 'error' ? 'alert' : 'status');
      $row.append($('<span class="bs-ls-state__ic"></span>').html(lsIcons[kind]));
      if (kind === 'error') {
        $row.append($('<strong></strong>').text('Не вдалося завантажити підказки'));
        $row.append($('<button type="button" class="bs-btn bs-btn-secondary bs-ls-state__btn" tabindex="0"></button>').text('Шукати на сторінці результатів').append(lsArrow));
      } else {
        $row.append($('<strong></strong>').text('Нічого не знайдено за «' + request + '»'));
        $row.append($('<p></p>').text('Перевірте написання або спробуйте коротший запит.'));
      }
      $lsList.empty().append($row);
    }

    function lsDecorate(request) {
      var $items = $lsList.find('.ps-live-search-item');
      if (!$items.length) {
        lsState('none', request);
        return;
      }
      $items.each(function () {
        var $item = $(this);
        if ($item.find('strong.name').length) $item.addClass('bs-ls-product');
        else $item.addClass('bs-ls-link').append(lsChevron);
      });
      $lsList.find('a.ps-live-search-more').append(lsArrow);
    }

    $lsList.on('mousedown', '.bs-ls-state__btn', function (event) {
      event.preventDefault();
    });
    $lsList.on('click', '.bs-ls-state__btn', function () {
      var form = $input.closest('form').get(0);
      if (!form) return;
      if (form.requestSubmit) form.requestSubmit();
      else form.submit();
    });

    $input.pslivesearch({
      source: function (request, response) {
        $lsList.find('.ps-live-search-item-loading').attr('role', 'status').append('<span class="visually-hidden">Завантаження…</span>');
        $.ajax({
          url: 'index.php?route=extension/ps_live_search/module/ps_live_search.autocomplete&search=' + encodeURIComponent(request),
          dataType: 'json',
          timeout: 8000,
          success: function (json) {
            if (!lsIsCurrent(request)) return;
            try {
              response(json || {});
            } catch (error) {
              lsState('error', request);
              return;
            }
            lsDecorate(request);
          },
          error: function (xhr, status) {
            if (status === 'abort' || !lsIsCurrent(request)) return;
            lsState('error', request);
          }
        });
      },

JS
    , 'hdr_ls_init');
$h = bust_token($h, 'catalog/view/stylesheet/boostershop-ds.css', TOKEN, 'ds_css');
foreach ($hookCounts as $hook => $n) count_exact($h, $hook, $n, 'hdr_hook_kept');
twig_hazard_gate(implode("\n", array_diff(explode("\n", $h), explode("\n", $files[HEADER]['text']))), HEADER);

/* ---- boostershop-ds.css --------------------------------------------------- */
$ds = $files[DSCSS]['text'];
count_exact($ds, 'UX-009-LS', 0, 'ds_no_section_yet');
$ds = replace_one($ds,
    "    max-height: calc(100dvh - var(--bs-header-h, 63px));\n"
    . "    overflow-y: auto;\n"
    . "    border-radius: 0;\n"
    . "    z-index: 60;\n"
    . "    box-shadow: var(--bs-sh-pop);\n"
    . "  }\n"
    . "}\n",
    "    max-height: calc(100dvh - var(--bs-header-h, 63px));\n"
    . "    overflow-y: auto;\n"
    . "    border-radius: 0;\n"
    . "    z-index: 60;\n"
    . "    /* UX-009-LS: full-width list under the field, no frame or shadow (was box-shadow: var(--bs-sh-pop)). */\n"
    . "    border: 0;\n"
    . "    box-shadow: none;\n"
    . "    padding: 0 0 12px;\n"
    . "  }\n"
    . "}\n",
    'ds_mobile_dropdown');
$section = <<<'BS_LS_CSS'
@@FILE:section.css@@
BS_LS_CSS;
$ds = replace_one($ds, "/* RD-01-02-03C fixes 20260531 */\n", $section . "\n\n/* RD-01-02-03C fixes 20260531 */\n", 'ds_section_anchor');
css_balance_gate($ds, DSCSS);
twig_hazard_gate($section, DSCSS . '(section)');

twig_gate($root, array(HEADER => array($files[HEADER]['text'], $h)));
out('php_lint=not_applicable(no PHP targets)');

commit_files($root, $files, array(
    DSCSS  => encode_text($files[DSCSS], $ds),
    HEADER => encode_text($files[HEADER], $h),
), array());

finish_ok('refresh the OpenCart theme cache, Ctrl+F5, then the owner QA in diagnostics/UX-009_live-search_report_20261004.md');
